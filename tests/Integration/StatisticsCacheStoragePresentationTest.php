<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Craft;
use craft\cachecascade\CascadeCache;
use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\cache\DisposableCacheStoragePresentation;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\console\controllers\CacheController;
use lindemannrock\formieratingfield\controllers\SettingsController;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\models\Settings;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use yii\caching\ArrayCache;
use yii\caching\Cache;
use yii\caching\CacheInterface;
use yii\caching\DbCache;
use yii\caching\FileCache;
use yii\console\ExitCode;
use yii\redis\Cache as RedisCache;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

/**
 * Covers configured and effective statistics-cache presentation.
 *
 * @since 3.23.0
 */
final class StatisticsCacheStoragePresentationTest extends TestCase
{
    private CacheInterface $originalApplicationCache;
    private string $originalStorageMethod;
    private array $originalApplicationCacheDiagnostics;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $cache);
        $this->originalApplicationCache = $cache;
        $this->originalStorageMethod = FormieRatingField::$plugin->getSettings()->cacheStorageMethod;
        $this->originalApplicationCacheDiagnostics = $this->applicationCacheDiagnosticsProperty()->getValue();
    }

    protected function cleanupExternalState(): void
    {
        Craft::$app->set('cache', $this->originalApplicationCache);
        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = $this->originalStorageMethod;
        $this->applicationCacheDiagnosticsProperty()->setValue(null, $this->originalApplicationCacheDiagnostics);

        parent::cleanupExternalState();
    }

    public function testApprovedBaseClassifierLoadsFromTheLocalCandidate(): void
    {
        $expectedBasePath = realpath(dirname(__DIR__, 3) . '/base/src');
        self::assertIsString($expectedBasePath);

        foreach ([
            CacheBackendStatus::class,
            DisposableCacheStoragePresentation::class,
            DisposableCacheStoragePresenter::class,
            DisposableCacheStorageResolver::class,
            PluginHelper::class,
        ] as $class) {
            $filename = (new ReflectionClass($class))->getFileName();
            self::assertIsString($filename);
            self::assertStringStartsWith($expectedBasePath . DIRECTORY_SEPARATOR, (string)realpath($filename));
        }
    }

    public function testSupersededPluginStoragePolicyClassesAreAbsent(): void
    {
        $pluginCacheRoot = dirname(__DIR__, 2) . '/src/cache';

        foreach ([
            'StatisticsCacheStorageDecision.php',
            'StatisticsCacheStorageResolver.php',
            'StatisticsCacheStoragePresenter.php',
        ] as $filename) {
            self::assertFileDoesNotExist($pluginCacheRoot . '/' . $filename);
        }
    }

    public function testKnownApplicationBackendsHaveCompactPersistentPresentation(): void
    {
        $redis = (new ReflectionClass(RedisCache::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(RedisCache::class, $redis);

        $cases = [
            [$redis, 'Using Redis cache', 'Redis cache'],
            [new CascadeCache(), 'Using managed cache', 'Managed cache'],
            [new DbCache(), 'Using database cache', 'Database cache'],
            [new FileCache(), 'Using filesystem cache', 'Filesystem cache'],
        ];

        $managedHeadingCount = 0;
        foreach ($cases as [$cache, $heading, $utilityDescription]) {
            self::assertInstanceOf(CacheInterface::class, $cache);
            $presentation = $this->presentation($cache, 'craft', false);

            self::assertSame($heading, $presentation->headingKey);
            self::assertSame([], $presentation->explanationKeys);
            self::assertSame('success', $presentation->statusSeverity);
            self::assertFalse($presentation->filePathEligible);
            self::assertSame('Active', $presentation->utilityValueKey);
            self::assertSame($utilityDescription, $presentation->utilityDescriptionKey);
            $managedHeadingCount += str_contains($presentation->headingKey, 'managed') ? 1 : 0;
        }

        self::assertSame(1, $managedHeadingCount);
    }

    public function testMemoryUnknownAndUnavailableBackendsAvoidFalsePersistenceClaims(): void
    {
        $memory = $this->presentation(new ArrayCache(), 'craft', false);
        self::assertSame('Caching disabled', $memory->headingKey);
        self::assertSame('warning', $memory->statusSeverity);
        self::assertSame('Disabled', $memory->utilityValueKey);
        self::assertSame('Recomputed as needed', $memory->utilityDescriptionKey);
        self::assertSame(
            ['No suitable cross-request cache is available. Cache data is recomputed as needed.'],
            $memory->explanationKeys,
        );

        $unknown = $this->presentation(new PresentationUnknownCache(), 'craft', true);
        self::assertSame('Using application cache', $unknown->headingKey);
        self::assertSame('info', $unknown->statusSeverity);
        self::assertSame(['Cross-request persistence could not be confirmed.'], $unknown->explanationKeys);
        self::assertSame('Best effort', $unknown->utilityValueKey);
        self::assertSame('Application cache', $unknown->utilityDescriptionKey);

        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Injected cache resolution failure.');
        });
        $service = new PresentationStatisticsService();
        $decision = $service->getCacheStorageDecision('craft');
        $unavailable = (new DisposableCacheStoragePresenter())->present($decision);

        self::assertTrue($decision->isDisabled());
        self::assertSame('Caching disabled', $unavailable->headingKey);
        self::assertSame('warning', $unavailable->statusSeverity);
        self::assertSame('Disabled', $unavailable->utilityValueKey);
        self::assertSame('Recomputed as needed', $unavailable->utilityDescriptionKey);
    }

    public function testBothPersistedApplicationTokensUseTheSamePortableDecision(): void
    {
        foreach (['redis', 'craft'] as $token) {
            $presentation = $this->presentation(new CascadeCache(), $token, true);

            self::assertSame('Using managed cache', $presentation->headingKey);
            self::assertSame('success', $presentation->statusSeverity);
            self::assertSame([], $presentation->explanationKeys);
        }

        self::assertSame('redis', DisposableCacheStorageResolver::applicationOptionToken('redis'));
        self::assertSame('craft', DisposableCacheStorageResolver::applicationOptionToken('craft'));
        self::assertSame('craft', DisposableCacheStorageResolver::applicationOptionToken('file'));
    }

    public function testFileModeReflectsDurableAndEphemeralHostPolicy(): void
    {
        $durable = $this->presentation(new ArrayCache(), 'file', false);
        self::assertSame('Using file cache', $durable->headingKey);
        self::assertSame([], $durable->explanationKeys);
        self::assertSame('success', $durable->statusSeverity);
        self::assertTrue($durable->filePathEligible);
        self::assertSame('Active', $durable->utilityValueKey);
        self::assertSame('File cache', $durable->utilityDescriptionKey);

        $ephemeralManaged = $this->presentation(new CascadeCache(), 'file', true);
        self::assertSame('Using managed cache', $ephemeralManaged->headingKey);
        self::assertSame('success', $ephemeralManaged->statusSeverity);
        self::assertFalse($ephemeralManaged->filePathEligible);
        self::assertSame(
            ['This host has an ephemeral filesystem, so the application cache is used automatically.'],
            $ephemeralManaged->explanationKeys,
        );
        self::assertSame('Active', $ephemeralManaged->utilityValueKey);
        self::assertSame('Managed cache', $ephemeralManaged->utilityDescriptionKey);

        $ephemeralFile = $this->presentation(new FileCache(), 'file', true);
        self::assertSame('Caching disabled', $ephemeralFile->headingKey);
        self::assertSame('warning', $ephemeralFile->statusSeverity);
        self::assertFalse($ephemeralFile->filePathEligible);
        self::assertSame('Disabled', $ephemeralFile->utilityValueKey);
        self::assertSame('Recomputed as needed', $ephemeralFile->utilityDescriptionKey);

        $ephemeralUnknown = $this->presentation(new PresentationUnknownCache(), 'file', true);
        self::assertSame([
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            'Cross-request persistence could not be confirmed.',
        ], $ephemeralUnknown->explanationKeys);
    }

    public function testSettingsVariablesDoNotResolveRuntimePathOnEphemeralHosts(): void
    {
        Craft::$app->set('cache', new CascadeCache());
        $service = new PresentationStatisticsService();
        $service->ephemeral = true;
        $service->failOnCachePathAccess = true;
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);

        $settings = new Settings(['cacheStorageMethod' => 'file']);
        $controller = new SettingsController('settings', FormieRatingField::$plugin);
        $method = new ReflectionMethod(SettingsController::class, 'cacheTemplateVariables');
        $variables = $method->invoke($controller, $settings);

        self::assertIsArray($variables);
        self::assertSame('Using managed cache', $variables['cacheStorage']['file']->headingKey);
        self::assertSame(
            ['This host has an ephemeral filesystem, so the application cache is used automatically.'],
            $variables['cacheStorage']['file']->explanationKeys,
        );
        self::assertNull($variables['cacheStorage']['filePath']);
        self::assertSame(0, $service->cachePathAccesses);
    }

    public function testTemplateUsesBackendNeutralPresentationAndCompatibleToggle(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/cache.twig');
        self::assertIsString($template);
        $baseTemplate = file_get_contents(dirname(__DIR__, 3) . '/base/src/templates/_partials/field-cache-storage.twig');
        self::assertIsString($baseTemplate);

        self::assertStringContainsString("'lindemannrock-base/_partials/field-cache-storage'", $template);
        self::assertStringContainsString('applicationOptionToken: cacheStorage.applicationToken', $template);
        self::assertStringContainsString("configuredStorageToken == 'file' ? 'file' : 'application'", $baseTemplate);
        self::assertStringContainsString(
            'Choose where disposable cache data is stored. File caching automatically uses the application cache on ephemeral hosts.',
            $baseTemplate,
        );
        self::assertStringContainsString("label: 'File cache'|t('lindemannrock-base')", $baseTemplate);
        self::assertStringContainsString("label: 'Application cache'|t('lindemannrock-base')", $baseTemplate);
        self::assertStringContainsString('presentation: presentation', $baseTemplate);
        self::assertStringNotContainsString('yii\\redis\\Cache', $baseTemplate);
        self::assertStringNotContainsString('className', $baseTemplate);
        self::assertStringNotContainsString('Redis Not Configured', $baseTemplate);
    }

    public function testEveryDynamicPresentationStringExistsInTheEnglishCatalogue(): void
    {
        $presentations = [
            $this->presentation(new ArrayCache(), 'file', false),
            $this->presentation(new CascadeCache(), 'file', true),
            $this->presentation(new ArrayCache(), 'file', true),
            $this->presentation(new ArrayCache(), 'craft', false),
            $this->presentation(new PresentationUnknownCache(), 'craft', true),
            $this->presentation(new CascadeCache(), 'craft', false),
        ];

        $redis = (new ReflectionClass(RedisCache::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(RedisCache::class, $redis);
        foreach ([$redis, new DbCache(), new FileCache()] as $cache) {
            $presentations[] = $this->presentation($cache, 'craft', false);
        }

        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Injected cache resolution failure.');
        });
        $presentations[] = (new DisposableCacheStoragePresenter())->present(
            (new PresentationStatisticsService())->getCacheStorageDecision('craft'),
        );

        $english = require dirname(__DIR__, 3) . '/base/src/translations/en/lindemannrock-base.php';
        self::assertIsArray($english);
        foreach ($presentations as $presentation) {
            foreach ([$presentation->headingKey, $presentation->utilityValueKey, $presentation->utilityDescriptionKey] as $key) {
                self::assertArrayHasKey($key, $english, "Missing Base presentation key: {$key}");
            }
            foreach ($presentation->explanationKeys as $key) {
                self::assertArrayHasKey($key, $english, "Missing Base explanation key: {$key}");
            }
        }
    }

    public function testUtilityTemplateUsesCompactPresenterValues(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/utilities/index.twig');
        self::assertIsString($template);

        self::assertStringContainsString('cacheStorage.utilityValueKey', $template);
        self::assertStringContainsString('cacheStorage.utilityDescriptionKey', $template);
        self::assertStringContainsString("|t('lindemannrock-base')", $template);
        self::assertStringContainsString('{count: cacheCount|number}', $template);
        self::assertStringNotContainsString('Craft Application Cache', $template);
        self::assertStringNotContainsString('Cached statistics files:', $template);
    }

    public function testConsoleInfoDoesNotEnumerateFilesForApplicationOrDisabledStorage(): void
    {
        Craft::$app->set('cache', new ArrayCache());
        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = 'craft';
        $service = new PresentationStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $controller = new PresentationCacheController('cache', FormieRatingField::$plugin);

        self::assertSame(ExitCode::OK, $controller->actionInfo());
        self::assertSame(0, $service->fileCountCalls);
        self::assertStringContainsString('Status: Caching disabled', implode('', $controller->output));
        self::assertStringContainsString('Cache data is recomputed as needed.', implode('', $controller->output));
        self::assertStringNotContainsString('File cache path:', implode('', $controller->output));
    }

    /** @return DisposableCacheStoragePresentation */
    private function presentation(
        CacheInterface $cache,
        string $configuredStorage,
        bool $ephemeral,
    ): DisposableCacheStoragePresentation {
        Craft::$app->set('cache', $cache);
        $service = new PresentationStatisticsService();
        $service->ephemeral = $ephemeral;

        return (new DisposableCacheStoragePresenter())->present(
            $service->getCacheStorageDecision($configuredStorage),
        );
    }

    private function applicationCacheDiagnosticsProperty(): ReflectionProperty
    {
        return new ReflectionProperty(PluginHelper::class, 'applicationCacheDiagnostics');
    }
}

/**
 * Test seam for host and filesystem observations.
 *
 * @since 3.23.0
 */
final class PresentationStatisticsService extends StatisticsService
{
    public bool $ephemeral = false;
    public bool $failOnCachePathAccess = false;
    public int $cachePathAccesses = 0;
    public int $fileCountCalls = 0;

    public function getCacheFileCount(): int
    {
        $this->fileCountCalls++;
        return parent::getCacheFileCount();
    }

    protected function isEphemeralHost(): bool
    {
        return $this->ephemeral;
    }

    protected function getCachePath(): string
    {
        $this->cachePathAccesses++;
        if ($this->failOnCachePathAccess) {
            throw new \LogicException('Runtime statistics path must not be accessed.');
        }

        return parent::getCachePath();
    }
}

/**
 * Unknown application-cache double with no persistence claim.
 *
 * @since 3.23.0
 */
final class PresentationUnknownCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];

    protected function getValue($key)
    {
        return $this->values[$key] ?? false;
    }

    protected function getValues($keys)
    {
        return array_map(fn(string $key): mixed => $this->getValue($key), $keys);
    }

    protected function setValue($key, $value, $duration)
    {
        $this->values[$key] = $value;
        return true;
    }

    protected function setValues($data, $duration)
    {
        foreach ($data as $key => $value) {
            $this->values[$key] = $value;
        }

        return [];
    }

    protected function addValue($key, $value, $duration)
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        return $this->setValue($key, $value, $duration);
    }

    protected function deleteValue($key)
    {
        unset($this->values[$key]);
        return true;
    }

    protected function flushValues()
    {
        $this->values = [];
        return true;
    }
}

/**
 * Captures console output for cache presentation assertions.
 *
 * @since 3.23.0
 */
final class PresentationCacheController extends CacheController
{
    /** @var list<string> */
    public array $output = [];

    public function stdout($string)
    {
        $this->output[] = (string)$string;
        return strlen((string)$string);
    }
}

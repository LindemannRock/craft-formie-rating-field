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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\cache\StatisticsCacheStorageDecision;
use lindemannrock\formieratingfield\cache\StatisticsCacheStoragePresenter;
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

        foreach ([CacheBackendStatus::class, PluginHelper::class] as $class) {
            $filename = (new ReflectionClass($class))->getFileName();
            self::assertIsString($filename);
            self::assertStringStartsWith($expectedBasePath . DIRECTORY_SEPARATOR, (string)realpath($filename));
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

            self::assertSame($heading, $presentation['heading']);
            self::assertNull($presentation['explanation']);
            self::assertSame('success', $presentation['statusType']);
            self::assertNull($presentation['filePath']);
            self::assertFalse($presentation['usesFile']);
            self::assertSame('Active', $presentation['utilityValue']);
            self::assertSame($utilityDescription, $presentation['utilityDescription']);
            $managedHeadingCount += str_contains($presentation['heading'], 'managed') ? 1 : 0;
        }

        self::assertSame(1, $managedHeadingCount);
    }

    public function testMemoryUnknownAndUnavailableBackendsAvoidFalsePersistenceClaims(): void
    {
        $memory = $this->presentation(new ArrayCache(), 'craft', false);
        self::assertSame('Caching disabled', $memory['heading']);
        self::assertSame('warning', $memory['statusType']);
        self::assertSame('Disabled', $memory['utilityValue']);
        self::assertSame('Recomputed as needed', $memory['utilityDescription']);
        self::assertSame(
            'No suitable cross-request cache is available. Statistics are recomputed as needed.',
            $memory['explanation'],
        );

        $unknown = $this->presentation(new PresentationUnknownCache(), 'craft', true);
        self::assertSame('Using application cache', $unknown['heading']);
        self::assertSame('info', $unknown['statusType']);
        self::assertSame('Cross-request persistence could not be confirmed.', $unknown['explanation']);
        self::assertSame('Best effort', $unknown['utilityValue']);
        self::assertSame('Application cache', $unknown['utilityDescription']);

        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Injected cache resolution failure.');
        });
        $service = new PresentationStatisticsService();
        $decision = $service->getCacheStorageDecision('craft');
        $unavailable = (new StatisticsCacheStoragePresenter())->present($decision);

        self::assertTrue($decision->isDisabled());
        self::assertSame('Caching disabled', $unavailable['heading']);
        self::assertSame('warning', $unavailable['statusType']);
        self::assertSame('Disabled', $unavailable['utilityValue']);
        self::assertSame('Recomputed as needed', $unavailable['utilityDescription']);
    }

    public function testBothPersistedApplicationTokensUseTheSamePortableDecision(): void
    {
        foreach (['redis', 'craft'] as $token) {
            $presentation = $this->presentation(new CascadeCache(), $token, true);

            self::assertSame('Using managed cache', $presentation['heading']);
            self::assertSame('success', $presentation['statusType']);
            self::assertNull($presentation['explanation']);
        }

        $presenter = new StatisticsCacheStoragePresenter();
        self::assertSame('redis', $presenter->applicationOptionToken('redis'));
        self::assertSame('craft', $presenter->applicationOptionToken('craft'));
        self::assertSame('craft', $presenter->applicationOptionToken('file'));
    }

    public function testFileModeReflectsDurableAndEphemeralHostPolicy(): void
    {
        $durable = $this->presentation(new ArrayCache(), 'file', false, '/safe/runtime/statistics/');
        self::assertSame('Using file cache', $durable['heading']);
        self::assertNull($durable['explanation']);
        self::assertSame('success', $durable['statusType']);
        self::assertSame('/safe/runtime/statistics/', $durable['filePath']);
        self::assertTrue($durable['usesFile']);
        self::assertSame('Active', $durable['utilityValue']);
        self::assertSame('File cache · {count} entries', $durable['utilityDescription']);

        $ephemeralManaged = $this->presentation(new CascadeCache(), 'file', true, '/must/not/render/');
        self::assertSame('Using managed cache', $ephemeralManaged['heading']);
        self::assertSame('success', $ephemeralManaged['statusType']);
        self::assertNull($ephemeralManaged['filePath']);
        self::assertSame(
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            $ephemeralManaged['explanation'],
        );
        self::assertSame('Active', $ephemeralManaged['utilityValue']);
        self::assertSame('Managed cache', $ephemeralManaged['utilityDescription']);

        $ephemeralFile = $this->presentation(new FileCache(), 'file', true, '/must/not/render/');
        self::assertSame('Caching disabled', $ephemeralFile['heading']);
        self::assertSame('warning', $ephemeralFile['statusType']);
        self::assertNull($ephemeralFile['filePath']);
        self::assertSame('Disabled', $ephemeralFile['utilityValue']);
        self::assertSame('Recomputed as needed', $ephemeralFile['utilityDescription']);
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
        self::assertSame('Using managed cache', $variables['cacheStorage']['file']['heading']);
        self::assertSame(
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            $variables['cacheStorage']['file']['explanation'],
        );
        self::assertNull($variables['cacheStorage']['file']['filePath']);
        self::assertSame(0, $service->cachePathAccesses);
    }

    public function testTemplateUsesBackendNeutralPresentationAndCompatibleToggle(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/cache.twig');
        self::assertIsString($template);

        self::assertStringContainsString("value: cacheStorage.applicationToken", $template);
        self::assertStringContainsString("value === 'file' ? 'file' : 'application'", $template);
        self::assertStringContainsString(
            'Choose where disposable cache data is stored. File caching automatically uses the application cache on ephemeral hosts.',
            $template,
        );
        self::assertStringContainsString("label: 'File cache'|t('formie-rating-field')", $template);
        self::assertStringContainsString("label: 'Application cache'|t('formie-rating-field')", $template);
        self::assertStringContainsString('presentation.heading', $template);
        self::assertStringContainsString('presentation.explanation', $template);
        self::assertStringNotContainsString('Configured choice', $template);
        self::assertStringNotContainsString('Effective storage', $template);
        self::assertStringNotContainsString('Application cache backend', $template);
        self::assertStringNotContainsString('yii\\redis\\Cache', $template);
        self::assertStringNotContainsString('className', $template);
        self::assertStringNotContainsString('Redis Not Configured', $template);
        self::assertStringNotContainsString('config/app.php', $template);
    }

    public function testEveryDynamicPresentationStringExistsInTheEnglishCatalogue(): void
    {
        $presentations = [
            $this->presentation(new ArrayCache(), 'file', false, '/runtime/'),
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
        $presentations[] = (new StatisticsCacheStoragePresenter())->present(
            (new PresentationStatisticsService())->getCacheStorageDecision('craft'),
        );

        $english = require dirname(__DIR__, 2) . '/src/translations/en/formie-rating-field.php';
        self::assertIsArray($english);
        foreach ($presentations as $presentation) {
            foreach (['heading', 'explanation', 'utilityValue', 'utilityDescription'] as $field) {
                if ($presentation[$field] !== null) {
                    self::assertArrayHasKey($presentation[$field], $english, "Missing dynamic presentation key: {$presentation[$field]}");
                }
            }
        }
    }

    public function testUtilityTemplateUsesCompactPresenterValues(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/utilities/index.twig');
        self::assertIsString($template);

        self::assertStringContainsString('cacheStorage.utilityValue', $template);
        self::assertStringContainsString('cacheStorage.utilityDescription', $template);
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
        self::assertStringContainsString('Statistics are recomputed as needed.', implode('', $controller->output));
        self::assertStringNotContainsString('File cache path:', implode('', $controller->output));
    }

    /** @return array<string, bool|string|null> */
    private function presentation(
        CacheInterface $cache,
        string $configuredStorage,
        bool $ephemeral,
        ?string $filePath = null,
    ): array {
        Craft::$app->set('cache', $cache);
        $service = new PresentationStatisticsService();
        $service->ephemeral = $ephemeral;

        return (new StatisticsCacheStoragePresenter())->present(
            $service->getCacheStorageDecision($configuredStorage),
            $filePath,
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

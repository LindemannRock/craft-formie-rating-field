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
use craft\events\ModelEvent;
use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\console\controllers\CacheController;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\models\Settings;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionClass;
use ReflectionMethod;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\models\FieldLayout;
use yii\caching\ArrayCache;
use yii\caching\Cache;
use yii\caching\CacheInterface;
use yii\console\ExitCode;
use yii\redis\Cache as RedisCache;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

/**
 * Covers portable statistics-cache storage and invalidation behavior.
 *
 * @since 3.22.0
 */
final class StatisticsServicePortableCacheTest extends TestCase
{
    private CacheInterface $originalApplicationCache;
    private string $originalStorageMethod;
    private mixed $originalCacheDuration;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $cache);
        $this->originalApplicationCache = $cache;
        $this->originalStorageMethod = FormieRatingField::$plugin->getSettings()->cacheStorageMethod;
        $this->originalCacheDuration = Craft::$app->getConfig()->getGeneral()->cacheDuration;
    }

    protected function cleanupExternalState(): void
    {
        Craft::$app->set('cache', $this->originalApplicationCache);
        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = $this->originalStorageMethod;
        Craft::$app->getConfig()->getGeneral()->cacheDuration = $this->originalCacheDuration;

        parent::cleanupExternalState();
    }

    public function testApprovedBaseCacheApiLoadsFromTheLocalCandidate(): void
    {
        $expectedBasePath = realpath(dirname(__DIR__, 3) . '/base/src');
        self::assertIsString($expectedBasePath);

        foreach ([PluginHelper::class, CacheBackendStatus::class, ScopedCache::class, ScopedCacheResult::class] as $class) {
            $filename = (new ReflectionClass($class))->getFileName();
            self::assertIsString($filename);
            self::assertStringStartsWith($expectedBasePath . DIRECTORY_SEPARATOR, (string)realpath($filename));
        }

        self::assertTrue(method_exists(PluginHelper::class, 'getApplicationCacheOrLog'));
        self::assertTrue(method_exists(ScopedCache::class, 'invalidateScope'));
        self::assertTrue(method_exists(ScopedCache::class, 'invalidateFamily'));
    }

    public function testSettingsAcceptFileRedisAndCraftStorageTokens(): void
    {
        foreach (['file', 'redis', 'craft'] as $token) {
            $settings = new Settings(['cacheStorageMethod' => $token]);
            self::assertTrue($settings->validate(['cacheStorageMethod']), $token);
        }
    }

    public function testRedisTokenUsesDirectRedisThroughPortableCacheOperations(): void
    {
        $cache = new PortableRedisCache();
        $service = $this->applicationCacheService($cache, 'redis');

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['totalResponses' => 4]));
        $result = $this->read($service, self::TEST_FORM_ID);

        self::assertTrue($result->isHit());
        self::assertSame(['totalResponses' => 4], $result->value);
        self::assertGreaterThan(0, $cache->getCalls);
        self::assertGreaterThan(0, $cache->setCalls);
        self::assertSame(0, $cache->flushCalls);
    }

    public function testRedisAndCraftTokensAcceptCascadeCacheWithoutInspectingHiddenLayers(): void
    {
        foreach (['redis', 'craft'] as $token) {
            $cache = new CascadeCache();
            $service = $this->applicationCacheService($cache, $token);

            self::assertTrue($this->save($service, self::TEST_FORM_ID, ['token' => $token]));
            $result = $this->read($service, self::TEST_FORM_ID);

            self::assertTrue($result->isHit());
            self::assertSame(['token' => $token], $result->value);
            self::assertSame(CacheBackendStatus::BACKEND_MANAGED, CacheBackendStatus::fromCache($cache)->backend);
        }
    }

    public function testEphemeralFileModeUsesSuitableApplicationCacheWithoutTouchingRuntimeFiles(): void
    {
        $cache = new CascadeCache();
        $service = $this->applicationCacheService($cache, 'file', true);
        $service->failOnCachePathAccess = true;

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['totalResponses' => 8]));
        self::assertSame(['totalResponses' => 8], $this->read($service, self::TEST_FORM_ID)->value);
        self::assertTrue($service->clearCacheForForm(self::TEST_FORM_ID));
        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertSame(0, $service->cachePathAccesses);
        self::assertSame(0, $service->getCacheFileCount());
        self::assertSame(0, $service->cachePathAccesses);
    }

    public function testEphemeralFileModeWithUnsuitableCacheRecomputesWithoutRuntimeFiles(): void
    {
        $service = $this->applicationCacheService(new ArrayCache(), 'file', true);
        $service->failOnCachePathAccess = true;
        [$form, $field] = $this->unsavedRatingForm(self::TEST_FORM_ID);

        $statistics = $service->getFieldStatistics($form, $field);

        self::assertIsArray($statistics);
        self::assertArrayHasKey('generatedAt', $statistics);
        self::assertSame(0, $service->cachePathAccesses);
    }

    public function testUnavailableApplicationCacheRecomputesSafely(): void
    {
        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Injected application cache resolution failure.');
        });
        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = 'craft';
        $service = new InspectableStatisticsService();
        [$form, $field] = $this->unsavedRatingForm(self::TEST_FORM_ID);

        $statistics = $service->getFieldStatistics($form, $field);

        self::assertIsArray($statistics);
        self::assertArrayHasKey('generatedAt', $statistics);
    }

    public function testApplicationCacheUsesConfiguredPositiveTtlAndFiniteFallback(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');

        Craft::$app->getConfig()->getGeneral()->cacheDuration = 321;
        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['value' => 1]));
        self::assertSame(321, $cache->lastItemDuration());

        Craft::$app->getConfig()->getGeneral()->cacheDuration = 0;
        self::assertTrue($this->save($service, self::TEST_FORM_ID + 1, ['value' => 2]));
        $fallback = $cache->lastItemDuration();
        self::assertIsInt($fallback);
        self::assertGreaterThan(0, $fallback);
        self::assertSame(86400, $fallback);
    }

    public function testRepeatedReadsHitPortableCacheAndPreserveFalseyArrays(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        [$form, $field] = $this->unsavedRatingForm(self::TEST_FORM_ID);

        self::assertTrue($this->save($service, self::TEST_FORM_ID, [], $field));
        $first = $service->getFieldStatistics($form, $field);
        $second = $service->getFieldStatistics($form, $field);

        self::assertSame([], $first);
        self::assertSame([], $second);
        self::assertGreaterThanOrEqual(2, $cache->getCalls);
    }

    public function testScopeInvalidationPreservesAnotherForm(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        $otherFormId = self::TEST_FORM_ID + 1;

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['form' => 'target']));
        self::assertTrue($this->save($service, $otherFormId, ['form' => 'other']));
        self::assertTrue($service->clearCacheForForm(self::TEST_FORM_ID));

        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertSame(['form' => 'other'], $this->read($service, $otherFormId)->value);
    }

    public function testSubmissionEventsInvalidateOnlyTheAffectedFormScope(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        $otherFormId = self::TEST_FORM_ID + 1;
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['form' => 'target']));
        self::assertTrue($this->save($service, $otherFormId, ['form' => 'other']));

        $submission = new Submission(['formId' => self::TEST_FORM_ID]);
        $submission->trigger(Submission::EVENT_AFTER_SAVE, new ModelEvent());

        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertSame(['form' => 'other'], $this->read($service, $otherFormId)->value);

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['form' => 'target-refreshed']));
        $submission->trigger(Submission::EVENT_AFTER_DELETE);

        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertSame(['form' => 'other'], $this->read($service, $otherFormId)->value);
    }

    public function testCacheGenerationBatchPopulatesTheSameFamilyAndScopeAsNormalRequests(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $form = $this->seedRatingForm();
        self::assertIsInt($form->id);

        $job = new GenerateCacheJob([
            'formId' => $form->id,
            'fieldHandle' => 'satisfaction',
            'dateRange' => 'all',
            'groupBy' => null,
            'currentBatch' => 1,
            'totalBatches' => 1,
        ]);
        $job->execute(Craft::$app->getQueue());

        $field = $service->getRatingFieldByHandle($form, 'satisfaction');
        self::assertInstanceOf(Rating::class, $field);
        $cached = $this->read($service, $form->id, $field);
        self::assertTrue($cached->isHit());
        self::assertIsArray($cached->value);
        self::assertSame($cached->value, $service->getFieldStatistics($form, $field));
    }

    public function testConsoleClearAlwaysInvalidatesWithoutConsultingFileCount(): void
    {
        $service = new CountingClearStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $controller = new RecordingCacheController('cache', FormieRatingField::$plugin);

        $result = $controller->actionClear();

        self::assertSame(ExitCode::OK, $result);
        self::assertSame(1, $service->clearCalls);
        self::assertSame(0, $service->fileCountCalls);
        self::assertSame([
            "Clearing rating field statistics cache...\n",
            "Successfully cleared statistics cache.\n",
        ], $controller->output);
    }

    public function testFamilyInvalidationMakesAllPriorGenerationsUnreachableAndPreservesSentinel(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        $sentinel = new ScopedCache($cache, 'sentinel-plugin', 'statistics');

        self::assertTrue($this->save($service, self::TEST_FORM_ID, ['form' => 'one']));
        self::assertTrue($this->save($service, self::TEST_FORM_ID + 1, ['form' => 'two']));
        self::assertTrue($sentinel->set('sentinel', 'safe', 300));
        self::assertTrue($service->clearAllCache());

        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertTrue($this->read($service, self::TEST_FORM_ID + 1)->isMiss());
        self::assertSame('safe', $sentinel->get('sentinel')->value);
        self::assertSame(0, $cache->flushCalls);
    }

    public function testCacheFailuresDoNotBreakStatisticsAndDiagnosticsStayBounded(): void
    {
        $cache = new PortableApplicationCache();
        $cache->throwGet = true;
        $cache->throwSet = true;
        $service = $this->applicationCacheService($cache, 'craft');
        [$form, $field] = $this->unsavedRatingForm(self::TEST_FORM_ID);
        $messageOffset = count(Craft::getLogger()->messages);

        $first = $service->getFieldStatistics($form, $field);
        $second = $service->getFieldStatistics($form, $field);

        self::assertIsArray($first);
        self::assertIsArray($second);
        $messages = array_filter(
            array_slice(Craft::getLogger()->messages, $messageOffset),
            static fn(array $message): bool => ($message[2] ?? null) === 'formie-rating-field'
                && str_contains((string)($message[0] ?? ''), 'statistics cache'),
        );
        self::assertLessThanOrEqual(2, count($messages));
    }

    public function testDurableFileCacheFailuresReturnSoftly(): void
    {
        $temp = $this->createTrackedTempDirectory('rating-portable-file-cache-');
        $blockingFile = $temp . '/not-a-directory';
        self::assertNotFalse(file_put_contents($blockingFile, 'block'));

        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = 'file';
        $service = new InspectableStatisticsService();
        $service->cachePath = $blockingFile . '/';

        self::assertFalse($this->save($service, self::TEST_FORM_ID, ['value' => 1]));
        self::assertTrue($this->read($service, self::TEST_FORM_ID)->isMiss());
        self::assertTrue($service->clearCacheForForm(self::TEST_FORM_ID));
        self::assertSame(0, $service->getCacheFileCount());
    }

    public function testFileCountDoesNotEnumerateApplicationCache(): void
    {
        $cache = new PortableApplicationCache();
        $service = $this->applicationCacheService($cache, 'craft');
        $temp = $this->createTrackedTempDirectory('rating-file-count-');
        $service->cachePath = $temp . '/';
        self::assertNotFalse(file_put_contents($service->cachePath . '1-a.cache', '{}'));

        self::assertSame(1, $service->getCacheFileCount());
        self::assertSame(0, $cache->getCalls);
    }

    public function testPortableImplementationAvoidsRawRedisAndSharedCacheEnumeration(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/services/StatisticsService.php');
        self::assertIsString($source);

        self::assertStringNotContainsString('getRedisCacheOrLog', $source);
        self::assertStringNotContainsString('getCacheKeySet', $source);
        self::assertStringNotContainsString('executeCommand(', $source);
        self::assertStringNotContainsString('->flush(', $source);
        self::assertDoesNotMatchRegularExpression('/\b(SADD|SMEMBERS|SREM|SCARD|KEYS)\b/', $source);
        self::assertStringContainsString('return App::isEphemeral();', $source);
    }

    private function applicationCacheService(
        CacheInterface $cache,
        string $storageMethod,
        bool $ephemeral = false,
    ): InspectableStatisticsService {
        Craft::$app->set('cache', $cache);
        FormieRatingField::$plugin->getSettings()->cacheStorageMethod = $storageMethod;
        $service = new InspectableStatisticsService();
        $service->ephemeral = $ephemeral;

        return $service;
    }

    /** @return array{Form, Rating} */
    private function unsavedRatingForm(int $formId): array
    {
        $form = new Form();
        $form->id = $formId;
        $field = new Rating([
            'uid' => 'portable-cache-rating-field',
            'handle' => 'satisfaction',
            'label' => 'Satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
        ]);

        return [$form, $field];
    }

    private function seedRatingForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Portable cache prewarm ', 'form');
        $form->handle = $this->nextTestMarker('portableCachePrewarm', 'form');

        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [
                    [
                        'fields' => [
                            [
                                'type' => Rating::class,
                                'handle' => 'satisfaction',
                                'label' => 'Satisfaction',
                                'ratingType' => Rating::RATING_TYPE_STAR,
                                'minValue' => 1,
                                'maxValue' => 5,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestElement($form);

        return $form;
    }

    private function save(
        StatisticsService $service,
        int $formId,
        array $value,
        Rating|string $field = 'rating',
    ): bool {
        $method = new ReflectionMethod(StatisticsService::class, 'saveToCache');
        $result = $method->invoke($service, $formId, $field, 'all', null, $value, 'all');
        self::assertIsBool($result);

        return $result;
    }

    private function read(
        StatisticsService $service,
        int $formId,
        Rating|string $field = 'rating',
    ): ScopedCacheResult {
        $method = new ReflectionMethod(StatisticsService::class, 'getFromCache');
        $result = $method->invoke($service, $formId, $field, 'all', null, 'all');
        self::assertInstanceOf(ScopedCacheResult::class, $result);

        return $result;
    }
}

/**
 * Test seam for host and cache-path behavior.
 *
 * @since 3.22.0
 */
final class InspectableStatisticsService extends StatisticsService
{
    public bool $ephemeral = false;
    public bool $failOnCachePathAccess = false;
    public int $cachePathAccesses = 0;
    public ?string $cachePath = null;

    protected function isEphemeralHost(): bool
    {
        return $this->ephemeral;
    }

    protected function getCachePath(): string
    {
        $this->cachePathAccesses++;
        if ($this->failOnCachePathAccess) {
            throw new \LogicException('Statistics runtime cache path must not be accessed.');
        }

        return $this->cachePath ?? parent::getCachePath();
    }
}

/**
 * In-memory application cache with observable operations.
 *
 * @since 3.22.0
 */
class PortableApplicationCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];
    /** @var list<string> */
    public array $setKeys = [];
    /** @var list<int|null> */
    public array $setDurations = [];
    public int $getCalls = 0;
    public int $flushCalls = 0;
    public bool $throwGet = false;
    public bool $throwSet = false;

    public function get($key)
    {
        $this->getCalls++;
        return parent::get($key);
    }

    public function set($key, $value, $duration = null, $dependency = null)
    {
        $this->setKeys[] = (string)$key;
        $this->setDurations[] = is_int($duration) ? $duration : null;
        return parent::set($key, $value, $duration, $dependency);
    }

    public function flush()
    {
        $this->flushCalls++;
        return parent::flush();
    }

    public function lastItemDuration(): ?int
    {
        for ($index = count($this->setKeys) - 1; $index >= 0; $index--) {
            if (str_contains($this->setKeys[$index], ':item:')) {
                return $this->setDurations[$index];
            }
        }

        return null;
    }

    protected function getValue($key)
    {
        if ($this->throwGet) {
            throw new \RuntimeException('Injected cache read failure.');
        }

        return $this->values[$key] ?? false;
    }

    protected function getValues($keys)
    {
        return array_map(fn(string $key): mixed => $this->getValue($key), $keys);
    }

    protected function setValue($key, $value, $duration)
    {
        if ($this->throwSet) {
            throw new \RuntimeException('Injected cache write failure.');
        }

        $this->values[$key] = $value;
        return true;
    }

    protected function setValues($data, $duration)
    {
        foreach ($data as $key => $value) {
            $this->setValue($key, $value, $duration);
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
 * Redis-compatible cache double that never connects to Redis.
 *
 * @since 3.22.0
 */
final class PortableRedisCache extends RedisCache
{
    private ArrayCache $storage;
    public int $getCalls = 0;
    public int $setCalls = 0;
    public int $flushCalls = 0;

    public function init(): void
    {
        $this->storage = new ArrayCache();
    }

    public function get($key)
    {
        $this->getCalls++;
        return $this->storage->get($key);
    }

    public function set($key, $value, $duration = null, $dependency = null)
    {
        $this->setCalls++;
        return $this->storage->set($key, $value, $duration, $dependency);
    }

    public function add($key, $value, $duration = 0, $dependency = null)
    {
        return $this->storage->add($key, $value, $duration, $dependency);
    }

    public function delete($key)
    {
        return $this->storage->delete($key);
    }

    public function flush()
    {
        $this->flushCalls++;
        return $this->storage->flush();
    }
}

/**
 * Records whether console clearing is improperly gated by file enumeration.
 *
 * @since 3.22.0
 */
final class CountingClearStatisticsService extends StatisticsService
{
    public int $clearCalls = 0;
    public int $fileCountCalls = 0;

    public function clearAllCache(): bool
    {
        $this->clearCalls++;
        return true;
    }

    public function getCacheFileCount(): int
    {
        $this->fileCountCalls++;
        return 99;
    }
}

/**
 * Captures console output without writing to the test process streams.
 *
 * @since 3.22.0
 */
final class RecordingCacheController extends CacheController
{
    /** @var list<string> */
    public array $output = [];

    public function stdout($string)
    {
        $this->output[] = (string)$string;
        return strlen((string)$string);
    }
}

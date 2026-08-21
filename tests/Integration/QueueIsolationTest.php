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
use craft\db\Connection;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Queue as QueueHelper;
use craft\queue\Queue;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\services\StatisticsCacheScheduler;
use lindemannrock\formieratingfield\tests\Support\IsolatedQueue;
use lindemannrock\formieratingfield\tests\TestCase;

/**
 * Verifies that scheduler tests cannot mutate the permanent Craft queue.
 *
 * @since 3.23.0
 */
final class QueueIsolationTest extends TestCase
{
    public function testSchedulerQueueMutationsPreservePermanentJobs(): void
    {
        $ownerDb = $this->createOwnerDatabaseConnection();
        $ownerQueue = $this->newQueue($ownerDb);
        $testQueue = $this->newQueue(Craft::$app->getDb());
        $ownerBefore = $this->permanentCacheGenerationRows($ownerDb);
        $sentinelIds = [];
        $testJobId = null;

        try {
            $this->pushPermanentSentinels($ownerQueue, $ownerDb, $sentinelIds);
            $sentinelsBefore = $this->queueRowsById($ownerDb, $sentinelIds);
            $permanentBefore = $this->permanentCacheGenerationRows($ownerDb);

            self::assertCount(4, $sentinelsBefore);
            self::assertSame([3_600, 7_200, 10_800, 14_400], array_map('intval', array_column($sentinelsBefore, 'delay')));
            self::assertSame([400, 800, 1_200, 1_600], array_map('intval', array_column($sentinelsBefore, 'priority')));
            self::assertSame([101, 102, 103, 104], array_map('intval', array_column($sentinelsBefore, 'ttr')));
            self::assertSame([0, 1, 0, 0], array_map('intval', array_column($sentinelsBefore, 'fail')));
            self::assertNull($sentinelsBefore[0]['dateReserved']);
            self::assertNull($sentinelsBefore[0]['timeUpdated']);
            self::assertNotNull($sentinelsBefore[1]['dateFailed']);
            self::assertNotNull($sentinelsBefore[2]['dateReserved']);
            self::assertSame(1_787_313_600, (int) $sentinelsBefore[2]['timeUpdated']);
            self::assertSame(37, (int) $sentinelsBefore[3]['progress']);
            self::assertCount(4, array_unique(array_column($sentinelsBefore, 'job'), SORT_REGULAR));
            self::assertSame($sentinelsBefore, $this->queueRowsById($ownerDb, $sentinelIds));

            $testJobId = QueueHelper::push(
                new GenerateCacheJob([
                    'formId' => self::TEST_FORM_ID,
                    'reschedule' => false,
                    'scheduledMaster' => false,
                ]),
                256,
                45,
                105,
                $testQueue,
            );
            self::assertNotNull($testJobId);

            self::assertSame($permanentBefore, $this->permanentCacheGenerationRows($ownerDb));
            self::assertSame($sentinelsBefore, $this->queueRowsById($ownerDb, $sentinelIds));
        } finally {
            try {
                if ($testJobId !== null) {
                    $testQueue->release($testJobId);
                }
            } finally {
                try {
                    if ($sentinelIds !== []) {
                        $ownerDb->createCommand()->delete('{{%queue}}', ['id' => $sentinelIds])->execute();
                    }
                } finally {
                    $ownerDb->close();
                }
            }
        }

        $verificationDb = $this->createOwnerDatabaseConnection();
        try {
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($verificationDb));
        } finally {
            $verificationDb->close();
        }
    }

    public function testFailureCleanupRemovesOnlyShadowQueueRows(): void
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(IsolatedQueue::class, $queue);
        $ownerDb = $this->createOwnerDatabaseConnection();
        $ownerBefore = $this->permanentCacheGenerationRows($ownerDb);

        try {
            try {
                QueueHelper::push(new GenerateCacheJob([
                    'formId' => self::TEST_FORM_ID,
                    'reschedule' => false,
                    'scheduledMaster' => false,
                ]), 300, 15, 106, $queue);
                throw new \RuntimeException('Intentional queue-isolation failure path.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Intentional queue-isolation failure path.', $exception->getMessage());
            } finally {
                $queue->clearShadowRows();
            }

            self::assertSame(0, $this->countCacheGenerationRows(Craft::$app->getDb()));
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($ownerDb));
        } finally {
            $ownerDb->close();
        }
    }

    public function testConnectionTerminationDiscardsOnlyShadowQueueRows(): void
    {
        $ownerDb = $this->createOwnerDatabaseConnection();
        $ownerBefore = $this->permanentCacheGenerationRows($ownerDb);
        $isolatedDb = $this->createOwnerDatabaseConnection();
        $sourceQueue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $sourceQueue);
        $isolatedQueue = new IsolatedQueue([
            'db' => $isolatedDb,
            'mutex' => Craft::$app->getMutex(),
            'tableName' => $sourceQueue->tableName,
            'channel' => 'queue',
            'proxyQueue' => null,
        ]);

        try {
            QueueHelper::push(new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 200, 30, 107, $isolatedQueue);

            self::assertSame(1, $this->countCacheGenerationRows($isolatedDb));
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($ownerDb));

            $isolatedDb->close();

            $verificationDb = $this->createOwnerDatabaseConnection();
            try {
                self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($verificationDb));
            } finally {
                $verificationDb->close();
            }
        } finally {
            if ($isolatedDb->getIsActive()) {
                $isolatedDb->close();
            }
            $ownerDb->close();
        }
    }

    public function testExplicitCleanupDropsOnlyShadowQueueRows(): void
    {
        $ownerDb = $this->createOwnerDatabaseConnection();
        $ownerBefore = $this->permanentCacheGenerationRows($ownerDb);
        $isolatedDb = $this->createOwnerDatabaseConnection();
        $isolatedQueue = $this->newIsolatedQueue($isolatedDb);

        try {
            QueueHelper::push(new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 225, 35, 108, $isolatedQueue);

            self::assertSame(1, $this->countCacheGenerationRows($isolatedDb));
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($ownerDb));

            $isolatedQueue->closeShadow();

            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($isolatedDb));
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($ownerDb));
        } finally {
            $isolatedQueue->closeShadow();
            $isolatedDb->close();
            $ownerDb->close();
        }
    }

    public function testInitializationFailureDropsOnlyShadowQueueRows(): void
    {
        $ownerDb = $this->createOwnerDatabaseConnection();
        $ownerBefore = $this->permanentCacheGenerationRows($ownerDb);
        $isolatedDb = $this->createOwnerDatabaseConnection();

        try {
            try {
                $this->newIsolatedQueue($isolatedDb, true);
                self::fail('Queue-shadow initialization should have failed.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Intentional queue-shadow initialization failure.', $exception->getMessage());
            }

            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($isolatedDb));
            self::assertSame($ownerBefore, $this->permanentCacheGenerationRows($ownerDb));
        } finally {
            $isolatedDb->close();
            $ownerDb->close();
        }
    }

    public function testPostgresCleanupIsTemporaryRelationOnly(): void
    {
        $dsn = App::env('FORMIE_RATING_FIELD_TEST_PG_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('A disposable PostgreSQL DSN was not provided.');
        }

        $tableName = 'formie_rating_field_queue_isolation.queue';
        $ownerDb = $this->createPostgresDatabaseConnection($dsn);
        $testConnections = [];
        $sentinelIds = [];

        try {
            $ownerDb->createCommand('CREATE SCHEMA [[formie_rating_field_queue_isolation]]')->execute();
            $this->createPostgresQueueTable($ownerDb);
            $ownerQueue = $this->newQueue($ownerDb, $tableName);
            $this->pushPermanentSentinels($ownerQueue, $ownerDb, $sentinelIds, $tableName);
            $sentinelsBefore = $this->queueRowsById($ownerDb, $sentinelIds, $tableName);
            self::assertCount(4, $sentinelsBefore);

            $explicitDb = $this->createPostgresDatabaseConnection($dsn);
            $testConnections[] = $explicitDb;
            $explicitQueue = $this->newIsolatedQueue($explicitDb, false, $tableName);
            self::assertStringStartsWith('pg_temp_', $explicitQueue->tableName);
            QueueHelper::push(new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 225, 35, 108, $explicitQueue);
            self::assertSame(1, (int) (new Query())->from($explicitQueue->tableName)->count('*', $explicitDb));
            self::assertSame($sentinelsBefore, $this->queueRowsById($ownerDb, $sentinelIds, $tableName));
            $explicitQueue->closeShadow();
            self::assertSame(0, $this->postgresTemporaryQueueCount($explicitDb));
            self::assertSame($sentinelsBefore, $this->queueRowsById($explicitDb, $sentinelIds, $tableName));
            $explicitQueue->closeShadow();
            $explicitDb->close();

            $failureDb = $this->createPostgresDatabaseConnection($dsn);
            $testConnections[] = $failureDb;
            try {
                $this->newIsolatedQueue($failureDb, true, $tableName);
                self::fail('PostgreSQL queue-shadow initialization should have failed.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Intentional queue-shadow initialization failure.', $exception->getMessage());
            }
            self::assertSame(0, $this->postgresTemporaryQueueCount($failureDb));
            self::assertSame($sentinelsBefore, $this->queueRowsById($failureDb, $sentinelIds, $tableName));
            $failureDb->close();

            $terminationDb = $this->createPostgresDatabaseConnection($dsn);
            $testConnections[] = $terminationDb;
            $terminationQueue = $this->newIsolatedQueue($terminationDb, false, $tableName);
            QueueHelper::push(new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 200, 30, 107, $terminationQueue);
            self::assertSame(1, $this->postgresTemporaryQueueCount($terminationDb));
            $terminationDb->close();
            $terminationQueue->closeShadow();
            self::assertSame(0, $this->postgresTemporaryQueueCount($terminationDb));
            self::assertSame($sentinelsBefore, $this->queueRowsById($terminationDb, $sentinelIds, $tableName));
            $terminationDb->close();

            self::assertSame($sentinelsBefore, $this->queueRowsById($ownerDb, $sentinelIds, $tableName));
        } finally {
            foreach ($testConnections as $testConnection) {
                $testConnection->close();
            }
            $ownerDb->createCommand('DROP SCHEMA IF EXISTS [[formie_rating_field_queue_isolation]] CASCADE')->execute();
            $ownerDb->close();
        }
    }

    private function createOwnerDatabaseConnection(): Connection
    {
        $db = Craft::$app->getDb();
        $connection = new Connection([
            'dsn' => $db->dsn,
            'username' => $db->username,
            'password' => $db->password,
            'charset' => $db->charset,
            'tablePrefix' => $db->tablePrefix,
            'attributes' => $db->attributes,
        ]);
        $connection->open();

        return $connection;
    }

    private function createPostgresDatabaseConnection(string $dsn): Connection
    {
        $connection = new Connection([
            'dsn' => $dsn,
            'username' => (string) App::env('FORMIE_RATING_FIELD_TEST_PG_USER'),
            'password' => (string) App::env('FORMIE_RATING_FIELD_TEST_PG_PASSWORD'),
            'charset' => 'utf8',
            'schemaMap' => ['pgsql' => \craft\db\pgsql\Schema::class],
        ]);
        $connection->open();

        return $connection;
    }

    private function newQueue(Connection $db, ?string $tableName = null): Queue
    {
        $source = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $source);

        return new Queue([
            'db' => $db,
            'mutex' => $source->mutex,
            'tableName' => $tableName ?? $source->tableName,
            'channel' => $source->channel ?? 'queue',
            'mutexTimeout' => $source->mutexTimeout,
            'proxyQueue' => null,
        ]);
    }

    private function newIsolatedQueue(
        Connection $db,
        bool $failAfterShadowInstallation = false,
        ?string $tableName = null,
    ): IsolatedQueue {
        $source = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $source);

        return new IsolatedQueue([
            'db' => $db,
            'mutex' => $source->mutex,
            'tableName' => $tableName ?? $source->tableName,
            'channel' => $source->channel ?? 'queue',
            'proxyQueue' => null,
            'failAfterShadowInstallation' => $failAfterShadowInstallation,
        ]);
    }

    /**
     * @param list<string> $ids
     */
    private function pushPermanentSentinels(
        Queue $queue,
        Connection $db,
        array &$ids,
        string $tableName = '{{%queue}}',
    ): void {
        foreach ([
            [new GenerateCacheJob([
                'reschedule' => true,
                'scheduledMaster' => true,
                'recurringOwner' => StatisticsCacheScheduler::RECURRING_OWNER,
            ]), 400, 3_600, 101],
            [new GenerateCacheJob([
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 800, 7_200, 102],
            [new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 1_200, 10_800, 103],
            [new GenerateCacheJob([
                'formId' => self::TEST_FORM_ID,
                'fieldHandle' => 'rating',
                'dateRange' => 'last30days',
                'currentBatch' => 1,
                'totalBatches' => 1,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]), 1_600, 14_400, 104],
        ] as [$job, $priority, $delay, $ttr]) {
            $id = QueueHelper::push($job, $priority, $delay, $ttr, $queue);
            self::assertNotNull($id);
            $ids[] = $id;
        }

        $now = new \DateTimeImmutable('2026-08-21 12:00:00', new \DateTimeZone('UTC'));
        $db->createCommand()->update($tableName, [
            'fail' => true,
            'dateFailed' => Db::prepareDateForDb($now),
            'error' => 'Test-owned failed manual master sentinel.',
        ], ['id' => $ids[1]])->execute();
        $db->createCommand()->update($tableName, [
            'dateReserved' => Db::prepareDateForDb($now),
            'timeUpdated' => $now->getTimestamp(),
            'attempt' => 1,
        ], ['id' => $ids[2]])->execute();
        $db->createCommand()->update($tableName, [
            'progress' => 37,
            'progressLabel' => 'Test-owned concrete batch sentinel.',
            'attempt' => 2,
        ], ['id' => $ids[3]])->execute();
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    private function queueRowsById(Connection $db, array $ids, string $tableName = '{{%queue}}'): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = (new Query())
            ->from($tableName)
            ->where(['id' => $ids])
            ->orderBy(['id' => SORT_ASC])
            ->all($db);

        return $this->normalizeQueueRows($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function permanentCacheGenerationRows(Connection $db): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', StatisticsCacheScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'GenerateCacheJob'])
            ->orderBy(['id' => SORT_ASC])
            ->all($db);

        return $rows;
    }

    private function countCacheGenerationRows(Connection $db): int
    {
        return (int) (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', StatisticsCacheScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'GenerateCacheJob'])
            ->count('*', $db);
    }

    private function postgresTemporaryQueueCount(Connection $db): int
    {
        return (int) $db->createCommand(<<<'SQL'
SELECT COUNT(*)
FROM pg_catalog.pg_class
WHERE relnamespace = pg_catalog.pg_my_temp_schema()
  AND relname = 'queue'
  AND relpersistence = 't'
SQL)->queryScalar();
    }

    private function createPostgresQueueTable(Connection $db): void
    {
        $db->createCommand(<<<'SQL'
CREATE TABLE "formie_rating_field_queue_isolation"."queue" (
    "id" integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    "channel" varchar(255) NOT NULL DEFAULT 'queue',
    "job" bytea NOT NULL,
    "description" text,
    "timePushed" integer NOT NULL,
    "ttr" integer NOT NULL,
    "delay" integer NOT NULL DEFAULT 0,
    "priority" integer NOT NULL DEFAULT 1024,
    "dateReserved" timestamp(0),
    "timeUpdated" integer,
    "progress" smallint NOT NULL DEFAULT 0,
    "progressLabel" varchar(255),
    "attempt" integer,
    "fail" boolean DEFAULT false,
    "dateFailed" timestamp(0),
    "error" text
)
SQL)->execute();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeQueueRows(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach ($row as &$value) {
                if (is_resource($value)) {
                    rewind($value);
                    $contents = stream_get_contents($value);
                    if ($contents === false) {
                        throw new \RuntimeException('Unable to read a PostgreSQL queue payload.');
                    }
                    $value = $contents;
                }
            }
            unset($value);
        }
        unset($row);

        return $rows;
    }
}

<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

/**
 * Protects aggregate ordering, CI delegation, Act cleanup, and runner cleanup.
 *
 * @since 3.23.0
 */
final class QualityGateOrchestrationTest extends TestCase
{
    private const CONSTITUENTS = [
        'package-validation',
        'platform-compatibility',
        'composer-audit',
        'dependency-floor',
        'php-quality',
        'test-conventions',
        'phpunit',
        'frontend-javascript',
        'frontend-build-parity',
        'ci-contract',
        'package-boundary',
    ];

    public function testAggregateDeclaresEveryOwnedConstituentExactlyOnce(): void
    {
        $result = $this->runProcess(['bash', 'scripts/quality-gate', '--list']);
        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());

        $rows = array_values(array_filter(explode("\n", trim($result->getOutput()))));
        $ids = [];
        $families = [];
        foreach ($rows as $row) {
            [$id, $family] = explode("\t", $row, 2);
            $ids[] = $id;
            $families[] = $family;
        }

        self::assertSame(self::CONSTITUENTS, $ids);
        self::assertCount(count($families), array_unique($families));

        $composer = json_decode(
            (string)file_get_contents($this->packageRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame([
            'Composer\\Config::disableProcessTimeout',
            'bash scripts/quality-gate',
        ], $composer['scripts']['quality-gate']);
    }

    #[DataProvider('constituentProvider')]
    public function testEveryConstituentFailureStopsAndPropagatesExactStatus(string $failureId): void
    {
        [$probe, $log] = $this->createGateProbe();
        $result = $this->runProcess(
            ['bash', 'scripts/quality-gate', '--probe', $probe],
            [
                'FORMIE_RATING_FIELD_GATE_PROBE_LOG' => $log,
                'FORMIE_RATING_FIELD_GATE_FAIL_ID' => $failureId,
            ],
        );

        self::assertSame(71, $result->getExitCode(), $result->getErrorOutput());
        $ids = $this->probeIds($log);
        self::assertSame($failureId, end($ids));
        self::assertStringContainsString("{$failureId} failed with exit 71.", $result->getErrorOutput());
    }

    public static function constituentProvider(): array
    {
        return array_combine(self::CONSTITUENTS, array_map(
            static fn(string $id): array => [$id],
            self::CONSTITUENTS,
        ));
    }

    public function testCurrentWorkflowDelegatesOnlyToCanonicalGate(): void
    {
        $result = $this->runProcess(['bash', 'scripts/check-ci-workflow', '.github/workflows/ci.yml']);

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    }

    public function testPhpunitRunnerIncludesDisposableLifecycleProof(): void
    {
        $runner = (string)file_get_contents($this->packageRoot() . '/scripts/run-tests');

        self::assertSame(2, substr_count($runner, 'tests/Fixtures/Project/run.php --lifecycle-probe'));
        self::assertStringContainsString('FORMIE_RATING_FIELD_FIXTURE_SOURCE_VENDOR_ROOT', $runner);
    }

    public function testWorkflowValidatorRejectsPartialDuplicateCommands(): void
    {
        $root = $this->createTrackedTempDirectory('formie-rating-field-workflow');
        $workflow = $root . '/ci.yml';
        file_put_contents($workflow, <<<'YAML'
jobs:
  quality-gates:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - uses: ramsey/composer-install@v4
      - run: composer phpstan
      - run: composer quality-gate
YAML);

        $result = $this->runProcess(['bash', 'scripts/check-ci-workflow', $workflow]);

        self::assertNotSame(0, $result->getExitCode());
        self::assertStringContainsString('without partial duplicate constituents', $result->getErrorOutput());
    }

    public function testActFailurePropagatesAndRequestsRunnerCleanup(): void
    {
        $root = $this->createTrackedTempDirectory('formie-rating-field-act');
        $bin = $root . '/bin';
        $resources = $root . '/resources';
        mkdir($bin);
        mkdir($resources);
        mkdir($root . '/scripts');
        mkdir($root . '/.github/workflows', recursive: true);
        copy($this->packageRoot() . '/scripts/act-quality-gates', $root . '/scripts/act-quality-gates');
        copy($this->packageRoot() . '/scripts/check-ci-workflow', $root . '/scripts/check-ci-workflow');
        copy($this->packageRoot() . '/.github/workflows/ci.yml', $root . '/.github/workflows/ci.yml');
        $argumentLog = $root . '/act-arguments.log';
        $fakeAct = $bin . '/act';
        file_put_contents($fakeAct, <<<'SH'
#!/bin/sh
printf '%s\n' "$*" > "$FORMIE_RATING_FIELD_ACT_ARGUMENT_LOG"
touch "$FORMIE_RATING_FIELD_ACT_RESOURCE_ROOT/job-container"
touch "$FORMIE_RATING_FIELD_ACT_RESOURCE_ROOT/service-container"
case " $* " in
    *" --rm "*) rm -f "$FORMIE_RATING_FIELD_ACT_RESOURCE_ROOT"/* ;;
esac
exit 73
SH);
        chmod($fakeAct, 0700);

        $process = new Process(
            ['/bin/bash', 'scripts/act-quality-gates'],
            $root,
            [
                'PATH' => $bin . ':/usr/bin:/bin',
                'FORMIE_RATING_FIELD_ACT_ARGUMENT_LOG' => $argumentLog,
                'FORMIE_RATING_FIELD_ACT_RESOURCE_ROOT' => $resources,
            ],
        );
        $process->run();

        self::assertSame(73, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('--rm', (string)file_get_contents($argumentLog));
        self::assertSame([], array_values(array_diff(scandir($resources) ?: [], ['.', '..'])));
    }

    public function testRunnerTempsCleanAfterInjectedFailures(): void
    {
        $root = $this->createTrackedTempDirectory('formie-rating-field-runner-cleanup');
        $bin = $root . '/bin';
        mkdir($bin);
        $fakeComposer = $bin . '/composer';
        file_put_contents($fakeComposer, "#!/bin/sh\nexit 0\n");
        chmod($fakeComposer, 0700);

        $audit = $this->runProcess(
            ['bash', 'scripts/composer-audit'],
            [
                'PATH' => $bin . ':/usr/bin:/bin',
                'TMPDIR' => $root,
                'FORMIE_RATING_FIELD_COMPOSER_AUDIT_FORCE_TEMP' => '1',
                'FORMIE_RATING_FIELD_COMPOSER_AUDIT_FAIL_STAGE' => 'after-update',
            ],
        );
        self::assertSame(79, $audit->getExitCode());
        self::assertSame([], glob($root . '/formie-rating-field-composer-audit.*') ?: []);

        $lowest = $this->runProcess(
            ['bash', 'scripts/check-lowest-dependencies'],
            [
                'PATH' => $bin . ':/usr/bin:/bin',
                'TMPDIR' => $root,
                'FORMIE_RATING_FIELD_LOWEST_FAIL_STAGE' => 'after-update',
            ],
        );
        self::assertSame(77, $lowest->getExitCode());
        self::assertSame([], glob($root . '/formie-rating-field-lowest-dependencies.*') ?: []);

        $archive = $this->runProcess(
            ['bash', 'scripts/check-package-boundary'],
            [
                'TMPDIR' => $root,
                'FORMIE_RATING_FIELD_PACKAGE_BOUNDARY_FAIL_STAGE' => 'after-archive',
            ],
        );
        self::assertSame(78, $archive->getExitCode());
        self::assertSame([], glob($root . '/formie-rating-field-package-boundary.*') ?: []);
    }

    /** @return array{string, string} */
    private function createGateProbe(): array
    {
        $root = $this->createTrackedTempDirectory('formie-rating-field-gate-probe');
        $probe = $root . '/probe.sh';
        $log = $root . '/constituents.log';
        file_put_contents($probe, <<<'SH'
#!/bin/sh
printf '%s:%s\n' "$1" "$2" >> "$FORMIE_RATING_FIELD_GATE_PROBE_LOG"
if [ "$1" = "${FORMIE_RATING_FIELD_GATE_FAIL_ID:-}" ]; then exit 71; fi
exit 0
SH);
        chmod($probe, 0700);
        file_put_contents($log, '');

        return [$probe, $log];
    }

    /** @return list<string> */
    private function probeIds(string $path): array
    {
        $lines = array_values(array_filter(explode("\n", trim((string)file_get_contents($path)))));

        return array_map(static fn(string $line): string => explode(':', $line, 2)[0], $lines);
    }

    private function runProcess(array $command, array $environment = []): Process
    {
        $process = new Process($command, $this->packageRoot(), $environment);
        $process->setTimeout(60);
        $process->run();

        return $process;
    }

    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}

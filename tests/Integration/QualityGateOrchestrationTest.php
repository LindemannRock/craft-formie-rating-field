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
 * @since 3.22.0
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
        self::assertSame('bash scripts/run-tests --no-progress', $composer['scripts']['test']);
        self::assertSame('phpunit --colors=always', $composer['scripts']['test:direct']);
        self::assertSame(['@ci', '@test'], $composer['scripts']['ci:full']);
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

        self::assertSame(1, substr_count($runner, 'tests/Fixtures/Project/run.php --lifecycle-probe'));
        self::assertStringContainsString('FORMIE_RATING_FIELD_FIXTURE_SOURCE_VENDOR_ROOT', $runner);
        self::assertStringContainsString('tests/Fixtures/Project/run.php "$@"', $runner);
        self::assertStringNotContainsString('composer test', $runner);
    }

    #[DataProvider('runnerModeProvider')]
    public function testPublicTestRunnerInitializesItsVendorRoot(string $mode): void
    {
        [$package, $vendorRoot, $bin, $phpLog, $composerLog] = $this->createTestRunnerFixture($mode, true);
        $process = new Process(
            ['/bin/bash', $package . '/scripts/run-tests', '--no-progress'],
            $package,
            [
                'PATH' => $bin . ':/usr/bin:/bin',
                'FORMIE_RATING_FIELD_RUNNER_PHP_LOG' => $phpLog,
                'FORMIE_RATING_FIELD_RUNNER_COMPOSER_LOG' => $composerLog,
            ],
        );
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame([
            $vendorRoot . '|tests/Fixtures/Project/run.php --no-progress',
            $vendorRoot . '|tests/Fixtures/Project/run.php --lifecycle-probe',
        ], array_values(array_filter(explode("\n", trim((string)file_get_contents($phpLog))))));
        self::assertSame('', (string)file_get_contents($composerLog));
    }

    public static function runnerModeProvider(): array
    {
        return [
            'workspace' => ['workspace'],
            'standalone' => ['standalone'],
        ];
    }

    public function testPublicTestRunnerRejectsMissingStandaloneDependencies(): void
    {
        [$package, , $bin, $phpLog, $composerLog] = $this->createTestRunnerFixture('standalone', false);
        $process = new Process(
            ['/bin/bash', $package . '/scripts/run-tests', '--no-progress'],
            $package,
            [
                'PATH' => $bin . ':/usr/bin:/bin',
                'FORMIE_RATING_FIELD_RUNNER_PHP_LOG' => $phpLog,
                'FORMIE_RATING_FIELD_RUNNER_COMPOSER_LOG' => $composerLog,
            ],
        );
        $process->run();

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString(
            'Standalone PHPUnit requires package dependencies. Run composer install first.',
            $process->getErrorOutput(),
        );
        self::assertSame('', (string)file_get_contents($phpLog));
        self::assertSame('', (string)file_get_contents($composerLog));
    }

    public function testCraftExecUsesGlobalCraftClassWithoutImportWarning(): void
    {
        $smoke = (string)file_get_contents($this->packageRoot() . '/scripts/smoke-test');

        self::assertStringNotContainsString('use Craft;', $smoke);
        self::assertStringContainsString('\Craft::$app->getPlugins()', $smoke);
        self::assertStringContainsString('\Craft::$app->getModule("lindemannrock-base")', $smoke);
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

    #[DataProvider('workspaceBaseProvider')]
    public function testSecurityCheckAllowsComposerToResolveWorkspaceBase(?string $version): void
    {
        [$result, $commands] = $this->runDependencyScript('composer-audit', $version);

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        self::assertSame($version !== null, str_contains($commands, 'repositories.local-base'));
        self::assertStringContainsString('update --no-install --no-scripts', $commands);
        self::assertStringContainsString('audit --abandoned=report --locked', $commands);
        self::assertStringNotContainsString('Lowest-compatible dependency verification passed', $result->getOutput());
    }

    #[DataProvider('workspaceBaseProvider')]
    public function testMinimumVerificationUsesOnlyAnExactWorkspaceFloor(?string $version): void
    {
        [$result, $commands] = $this->runDependencyScript('check-lowest-dependencies', $version);

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        self::assertSame($version === '5.39.0', str_contains($commands, 'repositories.local-base'));
        self::assertStringContainsString('--prefer-lowest', $commands);
        self::assertStringContainsString(' ci', $commands);
        self::assertStringContainsString(
            'Lowest-compatible dependency verification passed with lindemannrock/craft-plugin-base 5.39.0.',
            $result->getOutput(),
        );
    }

    public static function workspaceBaseProvider(): array
    {
        return [
            'exact floor' => ['5.39.0'],
            'older minor' => ['5.38.2'],
            'newer minor' => ['5.40.0'],
            'standalone' => [null],
        ];
    }

    #[DataProvider('nonMinimumBaseProvider')]
    public function testMinimumVerificationRejectsAnyOtherResolvedBase(string $resolvedVersion): void
    {
        [$result, $commands] = $this->runDependencyScript(
            'check-lowest-dependencies',
            '5.39.0',
            $resolvedVersion,
        );

        self::assertSame(1, $result->getExitCode(), $result->getErrorOutput());
        self::assertStringContainsString("selected Base $resolvedVersion; expected 5.39.0", $result->getErrorOutput());
        self::assertStringNotContainsString(' ci', $commands);
        self::assertStringNotContainsString('verification passed', $result->getOutput());
    }

    public static function nonMinimumBaseProvider(): array
    {
        return [
            'below floor' => ['5.38.2'],
            'compatible newer patch' => ['5.39.1'],
            'unsupported major' => ['6.0.0'],
        ];
    }

    #[DataProvider('dependencyScriptProvider')]
    public function testDependencyResolutionFailurePropagatesWithoutClaimingCoverage(string $script): void
    {
        [$result, $commands] = $this->runDependencyScript($script, '5.39.0', updateStatus: 42);

        self::assertSame(42, $result->getExitCode(), $result->getErrorOutput());
        self::assertStringNotContainsString('audit --abandoned', $commands);
        self::assertStringNotContainsString(' ci', $commands);
        self::assertStringNotContainsString('verification passed', $result->getOutput());
    }

    public static function dependencyScriptProvider(): array
    {
        return [
            'security' => ['composer-audit'],
            'minimum' => ['check-lowest-dependencies'],
        ];
    }

    /** @return array{Process, string} */
    private function runDependencyScript(
        string $script,
        ?string $workspaceVersion,
        string $resolvedVersion = '5.39.0',
        int $updateStatus = 0,
    ): array {
        $root = $this->createTrackedTempDirectory('formie-rating-field-dependency-runner');
        $package = $workspaceVersion === null ? $root . '/package' : $root . '/plugins/formie-rating-field';
        foreach ([$package . '/scripts', $package . '/src', $package . '/tests', $root . '/bin', $root . '/tmp'] as $path) {
            mkdir($path, recursive: true);
        }
        foreach (['composer.json', 'phpstan.neon', 'ecs.php', 'scripts/' . $script] as $file) {
            copy($this->packageRoot() . '/' . $file, $package . '/' . $file);
        }
        if ($workspaceVersion !== null) {
            mkdir($root . '/plugins/base');
            file_put_contents($root . '/plugins/base/composer.json', json_encode([
                'name' => 'lindemannrock/craft-plugin-base',
                'version' => $workspaceVersion,
            ], JSON_THROW_ON_ERROR));
        }

        // Exercise runner orchestration without network resolution; real package
        // gates separately prove Composer resolution and minimum-version quality.
        $composer = $root . '/bin/composer';
        file_put_contents($composer, <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$FORMIE_RATING_FIELD_DEPENDENCY_COMMAND_LOG"
case " $* " in
    *" update "*) exit "$FORMIE_RATING_FIELD_DEPENDENCY_UPDATE_STATUS" ;;
    *" show "*) printf '{"versions":["%s"]}\n' "$FORMIE_RATING_FIELD_DEPENDENCY_RESOLVED_BASE" ;;
esac
SH);
        chmod($composer, 0700);
        $log = $root . '/commands.log';
        file_put_contents($log, '');
        $process = new Process(['bash', $package . '/scripts/' . $script], $root, [
            'PATH' => $root . '/bin:/usr/bin:/bin',
            'TMPDIR' => $root . '/tmp',
            'FORMIE_RATING_FIELD_COMPOSER_AUDIT_FORCE_TEMP' => '1',
            'FORMIE_RATING_FIELD_DEPENDENCY_COMMAND_LOG' => $log,
            'FORMIE_RATING_FIELD_DEPENDENCY_UPDATE_STATUS' => (string)$updateStatus,
            'FORMIE_RATING_FIELD_DEPENDENCY_RESOLVED_BASE' => $resolvedVersion,
        ]);
        $process->setTimeout(60);
        $process->run();
        self::assertSame([], array_values(array_diff(scandir($root . '/tmp') ?: [], ['.', '..'])));

        return [$process, (string)file_get_contents($log)];
    }

    /** @return array{string, string, string, string, string} */
    private function createTestRunnerFixture(string $mode, bool $withVendor): array
    {
        $root = $this->createTrackedTempDirectory('formie-rating-field-test-runner');
        $package = $mode === 'workspace' ? $root . '/plugins/formie-rating-field' : $root . '/package';
        $vendorRoot = $mode === 'workspace' ? $root . '/vendor' : $package . '/vendor';
        $bin = $root . '/bin';
        mkdir($package . '/scripts', recursive: true);
        mkdir($bin);
        copy($this->packageRoot() . '/scripts/run-tests', $package . '/scripts/run-tests');
        if ($mode === 'workspace') {
            file_put_contents($root . '/bootstrap.php', "<?php\n");
        }
        if ($withVendor) {
            mkdir($vendorRoot, recursive: true);
            file_put_contents($vendorRoot . '/autoload.php', "<?php\n");
        }

        $phpLog = $root . '/php.log';
        $composerLog = $root . '/composer.log';
        file_put_contents($phpLog, '');
        file_put_contents($composerLog, '');
        file_put_contents($bin . '/php', <<<'SH'
#!/bin/sh
printf '%s|%s\n' "$FORMIE_RATING_FIELD_FIXTURE_SOURCE_VENDOR_ROOT" "$*" >> "$FORMIE_RATING_FIELD_RUNNER_PHP_LOG"
exit 0
SH);
        chmod($bin . '/php', 0700);
        file_put_contents($bin . '/composer', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$FORMIE_RATING_FIELD_RUNNER_COMPOSER_LOG"
exit 86
SH);
        chmod($bin . '/composer', 0700);

        return [$package, $vendorRoot, $bin, $phpLog, $composerLog];
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

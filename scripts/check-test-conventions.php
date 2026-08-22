<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

/** @return list<string> */
function identifierSegments(string $identifier): array
{
    $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $identifier) ?? $identifier;
    $segments = preg_split('/[^A-Za-z0-9]+/', $spaced, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($segments) ? array_values($segments) : [];
}

function forbiddenReason(string $identifier): ?string
{
    $segments = array_map(static fn(string $segment): string => strtolower($segment), identifierSegments($identifier));
    foreach ($segments as $segment) {
        if (preg_match('/^(?:pr|post|a)\d+(?:\d+)?$/', $segment) === 1) {
            return 'work-history ID';
        }
        if (preg_match('/^batch\d+$/', $segment) === 1) {
            return 'numbered batch label';
        }
        if (in_array($segment, ['audit', 'debt', 'amendment', 'smoke', 'miscellaneous'], true)) {
            return 'work-history label';
        }
    }
    for ($index = 0, $count = count($segments) - 1; $index < $count; $index++) {
        $pair = $segments[$index] . '-' . $segments[$index + 1];
        if (in_array($pair, ['regression-batch', 'fix-batch', 'other-tests'], true)) {
            return 'catch-all or batch label';
        }
        if ($segments[$index] === 'batch' && ctype_digit($segments[$index + 1])) {
            return 'numbered batch label';
        }
    }

    return null;
}

/** @return list<array{path: string, identifier: string, reason: string}> */
function scanPhp(string $relativePath, string $source): array
{
    $tokens = token_get_all($source);
    $violations = [];
    $declarations = [T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION];
    if (defined('T_ENUM')) {
        $declarations[] = T_ENUM;
    }
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (!is_array($token) || !in_array($token[0], $declarations, true)) {
            continue;
        }
        for ($candidate = $index + 1; $candidate < $count; $candidate++) {
            $next = $tokens[$candidate];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($token[0] === T_FUNCTION && $next === '&') {
                continue;
            }
            if (!is_array($next) || $next[0] !== T_STRING) {
                break;
            }
            $reason = forbiddenReason($next[1]);
            if ($reason !== null) {
                $violations[] = ['path' => $relativePath, 'identifier' => $next[1], 'reason' => $reason];
            }
            break;
        }
    }

    return $violations;
}

function assertSelfTest(): void
{
    $source = '<?php final class Post15AuditTest { public function testFixBatch2(): void {} }';
    $violations = scanPhp('tests/Sample.php', $source);
    if (array_column($violations, 'identifier') !== ['Post15AuditTest', 'testFixBatch2']) {
        throw new RuntimeException('Test-convention guard self-test failed.');
    }
}

assertSelfTest();
$packageRoot = dirname(__DIR__);
$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($packageRoot . '/tests', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($packageRoot) + 1));
    $pathReason = forbiddenReason(pathinfo($relativePath, PATHINFO_FILENAME));
    if ($pathReason !== null) {
        $violations[] = ['path' => $relativePath, 'identifier' => pathinfo($relativePath, PATHINFO_FILENAME), 'reason' => $pathReason];
    }
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) {
        throw new RuntimeException("Unable to read {$relativePath}.");
    }
    array_push($violations, ...scanPhp($relativePath, $source));
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, "Forbidden test identifier: {$violation['path']} {$violation['identifier']} ({$violation['reason']})\n");
    }
    exit(1);
}

fwrite(STDOUT, "Test convention guard passed: no work-history or catch-all identifiers.\n");

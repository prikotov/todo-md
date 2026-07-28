#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * todo-md test runner — lightweight fixture-based tests, no external deps.
 *
 * Usage:
 *   php tests/run.php              run all tests
 *   php tests/run.php ValidateTest run tests matching a substring
 *
 * Each tests/*Test.php file registers closures via test()/testFn().
 * Assertions throw TestFailure; the runner catches and tallies.
 */

require_once __DIR__ . '/Support/Fixture.php';

$pkgRoot = dirname(__DIR__);
$only    = array_slice($argv, 1);
$only    = $only === [] ? null : $only[0];

$TESTS = [];

/**
 * Register a named test closure.
 */
function test(string $name, Closure $fn): void
{
    global $TESTS;
    $TESTS[] = [$name, $fn];
}

/**
 * Alias kept for readability — same as test().
 */
function testFn(string $name, Closure $fn): void
{
    test($name, $fn);
}

// ── Load test files ─────────────────────────────────────────────────────────

$testFiles = glob($pkgRoot . '/tests/*Test.php') ?: [];
sort($testFiles);

foreach ($testFiles as $file) {
    require_once $file;
}

// ── Filter ──────────────────────────────────────────────────────────────────

$selected = [];
foreach ($TESTS as [$name, $fn]) {
    if ($only === null || str_contains($name, $only)) {
        $selected[] = [$name, $fn];
    }
}

if ($selected === []) {
    fwrite(STDERR, "No tests matched \"$only\"." . PHP_EOL);
    exit(1);
}

// ── Run ─────────────────────────────────────────────────────────────────────

$passed  = 0;
$failed  = 0;
$skipped = 0;
$start   = hrtime(true);

foreach ($selected as [$name, $fn]) {
    Fixture::reset();
    try {
        $fn();
        echo "  ✓ $name" . PHP_EOL;
        $passed++;
    } catch (TestSkipped $e) {
        echo "  ~ $name  (skipped: {$e->getMessage()})" . PHP_EOL;
        $skipped++;
    } catch (TestFailure $e) {
        echo "  ✗ $name" . PHP_EOL;
        echo "      " . $e->getMessage() . PHP_EOL;
        $failed++;
    } catch (Throwable $e) {
        echo "  ✗ $name" . PHP_EOL;
        echo "      " . $e::class . ': ' . $e->getMessage() . PHP_EOL;
        echo '      ' . str_replace("\n", "\n      ", trim($e->getTraceAsString())) . PHP_EOL;
        $failed++;
    } finally {
        Fixture::cleanup();
    }
}

$elapsed = (hrtime(true) - $start) / 1e6;

echo PHP_EOL;
echo sprintf(
    '%d test(s): %d passed, %d failed, %d skipped (%.0f ms).',
    count($selected),
    $passed,
    $failed,
    $skipped,
    $elapsed,
) . PHP_EOL;

exit($failed > 0 ? 1 : 0);

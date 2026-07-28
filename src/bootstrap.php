<?php

declare(strict_types=1);

/**
 * Shared bootstrap for todo-md mutating CLI commands.
 * Requires the full module stack (Parser + Validator + Board) and provides
 * common CLI helpers.
 */

require_once __DIR__ . '/TodoMd/Parser.php';
require_once __DIR__ . '/TodoMd/Validator.php';
require_once __DIR__ . '/TodoMd/Board.php';

/**
 * Parse --key=value and --flag options from argv.
 *
 * @param list<string> $args
 * @return array{values: list<string>, opts: array<string, string>, flags: array<string, true>}
 */
function cliParseArgs(array $args): array
{
    $values = [];
    $opts   = [];
    $flags  = [];

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            $body = substr($arg, 2);
            $eq   = strpos($body, '=');
            if ($eq !== false) {
                $opts[substr($body, 0, $eq)] = substr($body, $eq + 1);
            } else {
                $flags[$body] = true;
            }
        } elseif (str_starts_with($arg, '-') && strlen($arg) > 1) {
            $flags[substr($arg, 1)] = true;
        } else {
            $values[] = $arg;
        }
    }

    return ['values' => $values, 'opts' => $opts, 'flags' => $flags];
}

function cliHelp(string $help): void
{
    echo $help;
}

/**
 * Run a transition command. Exits the process.
 *
 * @param list<string> $args
 */
function cliRunTransition(array $args, string $status, string $verb): void
{
    $parsed = cliParseArgs($args);

    if (isset($parsed['flags']['help']) || isset($parsed['flags']['h'])) {
        cliHelp(transitionHelp($verb, $status));
        exit(0);
    }

    $id = $parsed['values'][0] ?? null;
    if ($id === null) {
        fwrite(STDERR, "Error: task ID required.\n");
        cliHelp(transitionHelp($verb, $status));
        exit(1);
    }

    $backup = !isset($parsed['flags']['no-backup']);

    try {
        $root = \TodoMd\Board::resolveRoot($parsed['opts']['root'] ?? (getcwd() ?: '.'));
        echo \TodoMd\Board::transition($root, $id, $status, ['backup' => $backup]) . PHP_EOL;
        exit(0);
    } catch (\TodoMd\BoardException $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function transitionHelp(string $verb, string $status): string
{
    $bin = "todo-md-$verb";
    $upper = strtoupper($status);

    return <<<TXT
$bin — move a task/epic to status `$status`.

Usage:
  php vendor/bin/$bin <ID> [--no-backup]

Atomically sets `status: $status`, moves the file to the canonical folder,
rewrites relative markdown links (outbound in the moved file + inbound in
referencing files), and validates. On validation failure all changes are
rolled back.

Options:
  --no-backup  Skip writing a backup to .todo-md-backup/.
  --help       Show this help.

TXT;
}

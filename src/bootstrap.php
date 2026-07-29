<?php

declare(strict_types=1);

/**
 * Bootstrap for the todo-md CLI dispatcher.
 *
 * Requires the library modules (Parser + Validator + Board) and defines
 * every subcommand handler.  The single binary bin/todo-md dispatches here.
 */

require_once __DIR__ . '/TodoMd/Parser.php';
require_once __DIR__ . '/TodoMd/Validator.php';
require_once __DIR__ . '/TodoMd/Board.php';

use TodoMd\Board;
use TodoMd\BoardException;
use TodoMd\Parser;
use TodoMd\Validator;

// ── Shared helpers ──────────────────────────────────────────────────────────

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

function cliHelp(): void
{
    echo <<<'TXT'
todo-md — file-based kanban board for markdown tasks.

Usage:
  todo-md <command> [options]

Commands:
  init          Initialise a todo/ board in the current project
  create        Create a new task or epic
  start         Move task/epic to in_progress
  review        Move task/epic to review
  done          Move task/epic to done (done/)
  cancel        Move task/epic to cancelled (cancelled/)
  backlog       Move task/epic to backlog (backlog/)
  set           Edit a front-matter field
  validate      Validate task and epic files
  export-jsonl  Export metadata as JSON Lines
  dashboard     Render HTML dashboard from JSONL

Run `todo-md <command> --help` for command-specific help.

TXT;
}

/**
 * @param list<string> $args
 */
function cli_transition(array $args, string $status, string $verb): void
{
    $parsed = cliParseArgs($args);

    if (isset($parsed['flags']['help']) || isset($parsed['flags']['h'])) {
        echo transitionHelp($verb, $status);
        exit(0);
    }

    $id = $parsed['values'][0] ?? null;
    if ($id === null) {
        fwrite(STDERR, "Error: task ID required.\n");
        echo transitionHelp($verb, $status);
        exit(1);
    }


    $transitionOpts = [];

    // 'start' requires assignee — CLI forces correct behaviour
    if ($verb === 'start') {
        $assignee = $parsed['opts']['assignee'] ?? null;
        if ($assignee === null || trim($assignee) === '') {
            fwrite(STDERR, "Error: --assignee=<role> is required for 'start'.\n");
            echo transitionHelp($verb, $status);
            exit(1);
        }
        $transitionOpts['assignee'] = $assignee;
    }

    try {
        $root = Board::resolveRoot($parsed['opts']['root'] ?? (getcwd() ?: '.'));
        echo Board::transition($root, $id, $status, $transitionOpts) . PHP_EOL;
        exit(0);
    } catch (BoardException $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function transitionHelp(string $verb, string $status): string
{
    return <<<TXT
todo-md $verb — move a task/epic to status `$status`.

Usage:
  php vendor/bin/todo-md $verb <ID> [--assignee=<role>]

Atomically sets `status: $status`, moves the file to the canonical folder,
rewrites relative markdown links (outbound in the moved file + inbound in
referencing files), and validates. On validation failure all changes are
rolled back.

Options:
  --assignee=<role>  Assignee role (required for 'start').
  --help             Show this help.

TXT;
}

// ── Command: create ─────────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_create(array $args): void
{
    $parsed = cliParseArgs($args);

    if (isset($parsed['flags']['help']) || isset($parsed['flags']['h'])) {
        echo createHelp();
        exit(0);
    }

    $id = $parsed['values'][0] ?? null;
    if ($id === null) {
        fwrite(STDERR, "Error: task/epic ID required.\n");
        echo createHelp();
        exit(1);
    }

    $author = $parsed['opts']['author'] ?? null;
    if ($author === null || trim($author) === '') {
        fwrite(STDERR, "Error: --author=<role> is required.\n");
        echo createHelp();
        exit(1);
    }

    try {
        $root = Board::resolveRoot($parsed['opts']['root'] ?? (getcwd() ?: '.'));
        $msg  = Board::create($root, $id, [
            'type'       => $parsed['opts']['type'] ?? null,
            'title'      => $parsed['opts']['title'] ?? null,
            'value'      => $parsed['opts']['value'] ?? null,
            'complexity' => $parsed['opts']['complexity'] ?? null,
            'priority'   => $parsed['opts']['priority'] ?? null,
            'author'     => $parsed['opts']['author'] ?? null,
            'status'     => $parsed['opts']['status'] ?? null,
            'epic'       => $parsed['opts']['epic'] ?? null,
            'depends_on' => $parsed['opts']['depends-on'] ?? null,
        ]);
        echo $msg . PHP_EOL;
        exit(0);
    } catch (BoardException $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function createHelp(): string
{
    return <<<'TXT'
todo-md create — create a new task or epic.

Usage:
  php vendor/bin/todo-md create <ID> --type=<type> --author=<role> [options]

ID format:
  TASK-<category>-<name>  → task  (--type required)
  EPIC-<category>-<name>  → epic  (type forced to epic)

Options:
  --type=<type>        Task type (required for tasks): fix, feat, build, chore, ci, docs, style, refactor, perf, test, revert.
  --title=<title>      H1 title (default: derived from ID).
  --value=<V0-V4>      Business value (default: V2).
  --complexity=<C0-C5> Complexity (default: C2).
  --priority=<P0-P3>   Priority (default: P2).
  --author=<author>    Author role (required).
  --status=<status>    Initial status (default: todo).
  --epic=<EPIC-ID>     Epic this task belongs to.
  --depends-on=<ids>   Comma-separated plain IDs.
  --help               Show this help.

TXT;
}

// ── Command: set ────────────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_set(array $args): void
{
    $parsed = cliParseArgs($args);

    if (isset($parsed['flags']['help']) || isset($parsed['flags']['h'])) {
        echo setHelp();
        exit(0);
    }

    $id     = $parsed['values'][0] ?? null;
    $assign = $parsed['values'][1] ?? null;

    if ($id === null || $assign === null) {
        fwrite(STDERR, "Error: usage: todo-md set <ID> <field>=<value>\n");
        echo setHelp();
        exit(1);
    }

    $eqPos = strpos($assign, '=');
    if ($eqPos === false) {
        fwrite(STDERR, "Error: second argument must be <field>=<value>, got: $assign\n");
        echo setHelp();
        exit(1);
    }

    $field = substr($assign, 0, $eqPos);
    $value = substr($assign, $eqPos + 1);

    try {
        $root = Board::resolveRoot($parsed['opts']['root'] ?? (getcwd() ?: '.'));
        $msg  = Board::setField(
            $root,
            $id,
            $field,
            $value,
            [],
        );
        echo $msg . PHP_EOL;
        exit(0);
    } catch (BoardException $e) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function setHelp(): string
{
    return <<<'TXT'
todo-md set — point-edit a front-matter field.

Usage:
  php vendor/bin/todo-md set <ID> <field>=<value>

If <field> is `status`, the full transition runs (folder move + link rewrite).
Otherwise only the front matter changes in place.

Options:
  --help       Show this help.

TXT;
}

// ── Command: validate ───────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_validate(array $args): void
{
    $strict     = false;
    $configPath = null;
    $targets    = [];

    foreach ($args as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo validateHelp();
            exit(0);
        }

        if ($arg === '--strict') {
            $strict = true;
            continue;
        }

        if (str_starts_with($arg, '--config=')) {
            $configPath = substr($arg, strlen('--config='));
            continue;
        }

        if (str_starts_with($arg, '-')) {
            fwrite(STDERR, "Error: unknown option: $arg" . PHP_EOL);
            echo validateHelp();
            exit(1);
        }

        $targets[] = $arg;
    }

    if ($targets === []) {
        $targets[] = getcwd() ?: '.';
    }

    $files = [];
    foreach ($targets as $target) {
        $files = array_merge($files, Parser::findTodoFiles($target));
    }

    $files = array_values(array_unique($files));
    sort($files);

    if ($files === []) {
        echo "No .todo.md files found." . PHP_EOL;
        exit(0);
    }

    $errorCount   = 0;
    $warningCount = 0;
    $idIndex      = Parser::buildIdIndex($files);

    $config = ['roles' => [], 'agents' => [], 'strict' => false];
    if ($configPath !== null) {
        $config = Validator::loadConfigFile($configPath);
    } else {
        try {
            $config = Validator::loadConfig(Board::resolveRoot(getcwd() ?: '.'));
        } catch (BoardException $e) {
            // No todo/ directory above cwd — validate without project config.
        }
    }
    if ($strict) {
        $config['strict'] = true;
    }

    foreach ($files as $file) {
        $result   = Validator::validateFile($file, $idIndex, $config);
        $errors   = $result['errors'];
        $warnings = $result['warnings'];

        $errorCount   += count($errors);
        $warningCount += count($warnings);

        $relativeFile = Parser::makeRelativePath($file, getcwd() ?: '.');
        if ($errors === [] && $warnings === []) {
            echo "✓ $relativeFile" . PHP_EOL;
            continue;
        }

        echo ($errors === [] ? '!' : '✗') . " $relativeFile" . PHP_EOL;
        foreach ($errors as $error) {
            echo "  error: $error" . PHP_EOL;
        }
        foreach ($warnings as $warning) {
            echo "  warning: $warning" . PHP_EOL;
        }
    }

    echo PHP_EOL;
    echo sprintf(
        'Validated %d file(s): %d error(s), %d warning(s).',
        count($files),
        $errorCount,
        $warningCount,
    ) . PHP_EOL;

    exit($errorCount > 0 ? 1 : 0);
}

function validateHelp(): string
{
    return <<<'TXT'
todo-md validate — validate todo-md task and epic files.

Usage:
  php vendor/bin/todo-md validate [target-dir|file ...]

Options:
  --help          Show this help.
  --strict        Treat author/assignee format and unknown role/agent
                  warnings as errors (non-zero exit).
  --config=FILE   Project config file (default: <project-root>/.todo-md.php).
                  Lists canonical `roles` and `agents` for author/assignee checks.

TXT;
}

// ── Command: export-jsonl ───────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_export_jsonl(array $args): void
{
    $targets = [];

    foreach ($args as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo exportHelp();
            exit(0);
        }

        if (str_starts_with($arg, '-')) {
            fwrite(STDERR, "Error: unknown option: $arg" . PHP_EOL);
            echo exportHelp();
            exit(1);
        }

        $targets[] = $arg;
    }

    if ($targets === []) {
        $targets[] = getcwd() ?: '.';
    }

    $cwd   = getcwd() ?: '.';
    $files = [];

    foreach ($targets as $target) {
        $files = array_merge($files, Parser::findTodoFiles($target));
    }

    $files = array_values(array_unique($files));
    sort($files);

    if ($files === []) {
        fwrite(STDERR, 'No .todo.md files found.' . PHP_EOL);
        exit(0);
    }

    foreach ($files as $file) {
        $record = exportExtractRecord($file, $cwd);
        if ($record === null) {
            continue;
        }

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fwrite(STDERR, 'Warning: failed to encode ' . Parser::makeRelativePath($file, $cwd) . ': ' . json_last_error_msg() . PHP_EOL);
            continue;
        }
        echo $json . PHP_EOL;
    }

    exit(0);
}

function exportHelp(): string
{
    return <<<'TXT'
todo-md export-jsonl — export todo-md task/epic metadata as JSON Lines.

Usage:
  php vendor/bin/todo-md export-jsonl [target-dir|file ...]

Options:
  --help    Show this help.

TXT;
}

/**
 * @return array<string, mixed>|null
 */
function exportExtractRecord(string $file, string $cwd): ?array
{
    $content = file_get_contents($file);
    if ($content === false) {
        return null;
    }

    $content = Parser::removeBom($content);
    $parsed  = Parser::parseFrontMatter($content);
    $warnings = [];
    if ($parsed['error'] !== null) {
        $frontMatter = [];
        $body        = $content;
    } else {
        $frontMatter = Parser::parseSimpleYaml($parsed['frontMatter'], $warnings);
        $body        = $parsed['body'];
    }
    $id   = Parser::fileId($file);
    $kind = Parser::detectKind($id) ?? 'task';

    $dependsOn = trim($frontMatter['depends_on'] ?? '');
    if ($dependsOn === '') {
        $dependsOnList = [];
    } else {
        $parts         = preg_split('/\s*,\s*/', $dependsOn);
        $dependsOnList = $parts === false ? [] : $parts;
    }

    $firstChange = null;
    $lastChange  = null;
    if (preg_match('/^##\s+.*Change\s+History/im', $body, $hm, PREG_OFFSET_CAPTURE)) {
        $histBody = substr($body, $hm[0][1]);
        $dates    = [];
        if (preg_match_all('/^\|\s*(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}:\d{2}\s*\(\d{1,10}\))?\s*\|/m', $histBody, $mm)) {
            foreach ($mm[1] as $i => $y) {
                $dates[] = $y . '-' . $mm[2][$i] . '-' . $mm[3][$i];
            }
            if ($dates !== []) {
                sort($dates);
                $firstChange = $dates[0];
                $lastChange  = $dates[count($dates) - 1];
            }
        }
    }

    $folder = Parser::detectFolder($file);
    $status = Parser::valueOrNull($frontMatter['status'] ?? null);
    if ($status === null) {
        $status = match ($folder) {
            'done'      => 'done',
            'cancelled' => 'cancelled',
            'backlog'   => 'backlog',
            default     => 'todo',
        };
    }

    return [
        'id'         => $id,
        'kind'       => strtoupper($kind),
        'title'      => Parser::extractTitle($body, $id),
        'file'       => Parser::makeRelativePath($file, $cwd),
        'folder'     => $folder,
        'status'     => $status,
        'type'       => Parser::valueOrNull($frontMatter['type'] ?? null),
        'priority'   => Parser::valueOrNull($frontMatter['priority'] ?? null),
        'value'      => Parser::valueOrNull($frontMatter['value'] ?? null),
        'complexity' => Parser::valueOrNull($frontMatter['complexity'] ?? null),
        'epic'       => Parser::valueOrNull($frontMatter['epic'] ?? null),
        'depends_on' => $dependsOnList,
        'assignee'   => Parser::valueOrNull($frontMatter['assignee'] ?? null),
        'author'     => Parser::valueOrNull($frontMatter['author'] ?? null),
        'created'    => Parser::valueOrNull($frontMatter['created'] ?? null),
        'due'        => Parser::valueOrNull($frontMatter['due'] ?? null),
        'started'    => Parser::valueOrNull($frontMatter['started'] ?? null),
        'completed'  => Parser::valueOrNull($frontMatter['completed'] ?? null),
        'cost_plan'  => Parser::valueOrNull($frontMatter['cost_plan'] ?? null),
        'cost_fact'  => Parser::valueOrNull($frontMatter['cost_fact'] ?? null),
        'first_change' => $firstChange,
        'last_change'  => $lastChange,
    ];
}

// ── Command: dashboard ──────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_dashboard(array $args): void
{
    $argCount = count($args);
    $input    = null;
    $output   = null;
    $title    = 'Задачи';
    $base     = null;

    for ($i = 0; $i < $argCount; $i++) {
        $arg = $args[$i];

        if ($arg === '--help' || $arg === '-h') {
            echo dashboardHelp();
            exit(0);
        }

        if ($arg === '-o' || $arg === '--output') {
            $output = $args[$i + 1] ?? null;
            $i++;
            continue;
        }

        if (str_starts_with($arg, '--output=')) {
            $output = substr($arg, strlen('--output='));
            continue;
        }

        if (str_starts_with($arg, '--title=')) {
            $title = substr($arg, strlen('--title='));
            continue;
        }
        if ($arg === '--base') {
            $base = $args[$i + 1] ?? null;
            $i++;
            continue;
        }

        if (str_starts_with($arg, '--base=')) {
            $base = substr($arg, strlen('--base='));
            continue;
        }

        if ($arg === '-' || !str_starts_with($arg, '-')) {
            $input = $arg;
            continue;
        }

        fwrite(STDERR, "Error: unknown option: $arg" . PHP_EOL);
        echo dashboardHelp();
        exit(1);
    }

    $raw = $input === null || $input === '-'
        ? stream_get_contents(STDIN)
        : file_get_contents($input);

    if ($raw === false) {
        fwrite(STDERR, 'Error: cannot read input: ' . ($input ?? 'stdin') . PHP_EOL);
        exit(1);
    }

    $tasks = [];
    foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            fwrite(STDERR, 'Warning: skipping invalid JSON line' . PHP_EOL);
            continue;
        }
        $tasks[] = $decoded;
    }

    if ($tasks === []) {
        fwrite(STDERR, 'No task records in input.' . PHP_EOL);
        exit(0);
    }

    $html = dashboardRenderHtml($tasks, $title, $base);

    if ($output === null) {
        echo $html;
    } else {
        $written = file_put_contents($output, $html);
        if ($written === false) {
            fwrite(STDERR, "Error: cannot write output: $output" . PHP_EOL);
            exit(1);
        }
        fwrite(STDERR, "Wrote $output (" . count($tasks) . ' task(s)).' . PHP_EOL);
    }

    exit(0);
}

function dashboardHelp(): string
{
    return <<<'TXT'
todo-md dashboard — render a self-contained HTML dashboard from JSONL.

Usage:
  php vendor/bin/todo-md dashboard <input.jsonl|-> [-o out.html] [--title="..."] [--base=DIR]

Options:
  -o, --output=FILE   Write HTML to FILE (default: stdout).
  --title="TEXT"      Dashboard title (default: "Задачи").
  --base=DIR          Project root (absolute) to build file:// links to source .md files.
  --help              Show this help.

TXT;
}

/**
 * @param list<array<string, mixed>> $tasks
 */
function dashboardRenderHtml(array $tasks, string $title, ?string $base = null): string
{
    $absBase = null;
    if ($base !== null && $base !== '') {
        $resolved = realpath($base);
        $absBase = $resolved !== false ? $resolved : rtrim($base, '/');
    }
    foreach ($tasks as &$t) {
        $file = $t['file'] ?? null;
        if (!is_string($file) || $file === '') {
            continue;
        }
        $enc = implode('/', array_map('rawurlencode', explode('/', $file)));
        if ($file[0] === '/') {
            $t['url'] = 'file://' . $enc;
        } elseif ($absBase !== null) {
            $t['url'] = 'file://' . $absBase . '/' . $enc;
        }
    }
    unset($t);
    $dataJson = json_encode(
        $tasks,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
    );

    $template = <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>__TITLE__</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
  :root { color-scheme: light dark; }
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f6f7f9; color: #1b1f23; }
  header { background: #1f2937; color: #fff; padding: .55rem 1.25rem; display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
  header h1 { margin: 0; font-size: 1.05rem; font-weight: 600; white-space: nowrap; }
  header .sub { opacity: .6; font-size: .85rem; white-space: nowrap; }
  header .sub::before { content: "·"; margin-right: .5rem; opacity: .7; }
  main { padding: 1.25rem 1.5rem; }
  .cards { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem; }
  .card { background: #fff; border-radius: .5rem; padding: .85rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); min-width: 120px; }
  .card .n { font-size: 1.6rem; font-weight: 700; }
  .card .l { font-size: .8rem; opacity: .7; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
  .grid.stack { grid-template-columns: 1fr; }
  .grid.summary { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); align-items: start; }
  .grid.summary .cards { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .5rem; margin: 0; }
  .grid.summary .card { min-width: 0; padding: .6rem .7rem; }
  .grid.summary .card .n { font-size: 1.3rem; }
  .panel { background: #fff; border-radius: .5rem; padding: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
  .panel h2 { margin: 0 0 .75rem; font-size: .95rem; }
  .gantt-scroll { max-height: 640px; overflow-y: auto; }
  .gantt-canvas-box { position: relative; min-height: 220px; }
  .legend { display:flex; flex-wrap:wrap; gap:.45rem 1rem; padding:.35rem 0 0; font-size:.8rem; color:#475569; }
  .legend .item { display:flex; align-items:center; gap:.35rem; }
  .legend .sw { width:.85rem; height:.85rem; border-radius:3px; display:inline-block; border:1px solid rgba(0,0,0,.12); }
  .chart-box { position: relative; height: 260px; }
  .heat-wrap { overflow-x: auto; padding: .25rem 0; }
  .heat-months { display: inline-grid; grid-auto-flow: column; grid-template-rows: 13px; gap: 3px; margin: 0 0 3px 30px; }
  .heat-months span { font-size: 10px; line-height: 13px; width: 13px; white-space: nowrap; color: #6b7280; }
  .heat-body { display: flex; gap: 4px; }
  .heat-wdays { display: grid; grid-template-rows: repeat(7, 13px); gap: 3px; font-size: 9px; color: #6b7280; width: 26px; flex: none; }
  .heat-wdays span { line-height: 13px; }
  .heat-grid { display: inline-grid; grid-auto-flow: column; grid-template-rows: repeat(7, 13px); gap: 3px; }
  .heat-cell { width: 13px; height: 13px; border-radius: 2px; background: #ebedf0; }
  .heat-legend { display: flex; align-items: center; gap: 4px; font-size: 10px; color: #6b7280; margin-top: .5rem; }
  .heat-legend i { display: inline-block; width: 11px; height: 11px; border-radius: 2px; }
  .panel-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
  .panel-head h2 { margin: 0; }
  .chart-box.tall { height: 360px; }
  .muted { opacity: .6; font-size: .8rem; font-weight: 400; }
  .board-search { margin-left:auto; padding:.35rem .6rem; min-width:200px; flex:1 1 220px; max-width:460px; border:1px solid #d0d7de; border-radius:.375rem; background:inherit; color:inherit; font-family:inherit; font-size:.85rem; }
  .board-sort { padding:.3rem .5rem; border:1px solid #d0d7de; border-radius:.375rem; background:#eef1f4; color:#475569; font-size:.8rem; font-family:inherit; cursor:pointer; }
  .ct-link, .col-link { color: inherit; text-decoration: none; }
  .ct-link:hover, .col-link:hover { text-decoration: underline; }
  a { color: #2563eb; }
  footer { text-align: center; opacity: .5; font-size: .75rem; padding: 1.5rem; }
  /* tabs + kanban board */
  .tabs { display:flex; gap:.25rem; margin:0 0 0 auto; flex-wrap:nowrap; }
  .tab { background:rgba(255,255,255,.12); color:#fff; border:0; padding:.4rem .9rem; border-radius:.375rem; cursor:pointer; font-size:.85rem; font-family:inherit; }
  .tab:hover { background:rgba(255,255,255,.22); }
  .tab.active { background:#fff; color:#1f2937; font-weight:600; }
  .tab-pane.hidden { display:none; }
  .board { display:flex; gap:1rem; overflow-x:auto; align-items:flex-start; padding-bottom:.5rem; }
  .col { flex:1 1 0; min-width:240px; max-width:460px; background:#fff; border-radius:.5rem; box-shadow:0 1px 2px rgba(0,0,0,.06); }
  .col h3 { margin:0; padding:.55rem .7rem; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; display:flex; justify-content:space-between; align-items:center; gap:.4rem; border-bottom:2px solid var(--cc,#e2e8f0); }
  .col .count { background:#eef1f4; color:#475569; border-radius:999px; padding:0 .5rem; font-size:.72rem; }
  .col-body { padding:.4rem; display:flex; flex-direction:column; gap:.4rem; max-height:72vh; overflow-y:auto; }
  .card-task { background:#f6f7f9; border-radius:.375rem; padding:.45rem .55rem; border-left:3px solid var(--cs,#94a3b8); }
  .card-task .ct-id { font-weight:600; font-size:.78rem; display:flex; justify-content:space-between; align-items:center; gap:.3rem; }
  .card-task .ct-title { margin:.2rem 0; font-size:.82rem; }
  .card-task .ct-meta { font-size:.7rem; opacity:.65; }
  .pbadge { background:#e2e8f0; color:#334155; padding:0 .35rem; border-radius:.3rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
  .pbadge.p-P0 { background:#fee2e2; color:#991b1b; }
  .pbadge.p-P1 { background:#ffedd5; color:#9a3412; }
  .pbadge.p-P2 { background:#dbeafe; color:#1e40af; }
  .pbadge.p-P3 { background:#e2e8f0; color:#475569; }
  .sdot { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--cs,#94a3b8); margin-right:.3rem; vertical-align:middle; }
  .board-controls { display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem; flex-wrap:wrap; }
  .grp { background:#eef1f4; color:#475569; border:0; padding:.3rem .7rem; border-radius:.375rem; cursor:pointer; font-size:.8rem; font-family:inherit; }
  .grp:hover { background:#e2e8f0; }
  .grp.active { background:#1f2937; color:#fff; }
  .board.epic-sectioned { flex-direction:column; overflow-x:visible; }
  .epic-sec { background:#fff; border-radius:.5rem; box-shadow:0 1px 2px rgba(0,0,0,.06); padding:.6rem .75rem; }
  .epic-sec-head { margin:0 0 .5rem; padding-bottom:.4rem; font-size:.85rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; display:flex; justify-content:space-between; align-items:center; gap:.4rem; border-bottom:2px solid var(--cs,#e2e8f0); }
  .epic-sec-head .count { background:#eef1f4; color:#475569; border-radius:999px; padding:0 .5rem; font-size:.72rem; margin-left:.35rem; }
  .cols-wrap { display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-start; padding:.5rem 0; }
</style>
</head>
<body>
<header>
  <h1>__TITLE__</h1>
  <div class="sub" id="sub"></div>
  <nav class="tabs" id="tabs">
    <button type="button" class="tab active" data-tab="board">Доска</button>
    <button type="button" class="tab" data-tab="charts">Графики</button>
    <button type="button" class="tab" data-tab="gantt">Гант</button>
  </nav>
</header>
<main>
  <section class="tab-pane" id="pane-board">
    <div class="board-controls" id="grp">
      <span class="muted">Группировка:</span>
      <button type="button" class="grp active" data-grp="status">По статусам</button>
      <button type="button" class="grp" data-grp="epic">По эпикам</button>
      <span class="muted">Сортировка:</span>
      <select id="sort" class="board-sort">
        <option value="default">По умолчанию</option>
        <option value="name">По имени (А→Я)</option>
        <option value="priority">По приоритету (P0→P3)</option>
        <option value="created">По дате создания (новые)</option>
        <option value="closed">По дате закрытия (новые)</option>
      </select>
      <input id="search" type="search" class="board-search" placeholder="Фильтр по id, заголовку, эпику, статусу, приоритету…">
    </div>
    <div class="board" id="board"></div>
  </section>
  <section class="tab-pane hidden" id="pane-charts">
    <div class="grid summary">
      <div class="cards" id="cards"></div>
      <div class="panel heat-panel">
        <div class="panel-head"><h2>Тепловая карта поставок <span id="heatCount" class="muted"></span></h2><select id="heatPeriod" class="board-sort"><option value="3">3 мес</option><option value="6">6 мес</option><option value="12">12 мес</option></select></div>
        <div class="heat-wrap" id="heatMap"></div>
      </div>
    </div>
    <div class="grid">
      <div class="panel"><h2>По статусам</h2><div class="chart-box"><canvas id="statusChart"></canvas></div></div>
      <div class="panel"><h2>По приоритетам</h2><div class="chart-box"><canvas id="priorityChart"></canvas></div></div>
      <div class="panel">
        <div class="panel-head"><h2>Типы задач <span id="typeCount" class="muted"></span></h2><select id="typeStatus" class="board-sort"></select></div>
        <div class="chart-box"><canvas id="typeChart"></canvas></div>
      </div>
    </div>
    <div class="grid">
      <div class="panel"><h2>Time To Market <span id="ttmCount" class="muted"></span></h2><div class="chart-box"><canvas id="ttmChart"></canvas></div></div>
      <div class="panel"><h2>Cycle Time <span class="muted">дата закрытия × время разработки</span></h2><div class="chart-box"><canvas id="scatterChart"></canvas></div></div>
      <div class="panel"><h2>Throughput <span class="muted">закрыто задач в неделю</span></h2><div class="chart-box"><canvas id="throughputChart"></canvas></div></div>
    </div>
    <div class="grid stack">
      <div class="panel epic-panel"><h2>Задачи в эпиках по статусам</h2><div class="chart-box epic-box"><canvas id="epicChart"></canvas></div></div>
    </div>
    <div class="grid">
      <div class="panel">
        <div class="panel-head"><h2>Кто делает <span class="muted">(done)</span></h2><select id="whoDim" class="board-sort"></select></div>
        <div class="chart-box tall"><canvas id="whoChart"></canvas></div>
      </div>
    </div>
  </section>
  <section class="tab-pane hidden" id="pane-gantt">
    <div class="legend" id="ganttLegend"></div>
    <div class="grid stack">
      <div class="panel">
        <h2>Эпики <span id="epicGanttCount" class="muted"></span></h2>
        <div class="gantt-scroll"><div class="gantt-canvas-box"><canvas id="epicGanttChart"></canvas></div></div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Задачи эпика <span id="taskGanttCount" class="muted"></span></h2><select id="ganttEpicFilter" class="board-sort"></select></div>
        <div class="gantt-scroll"><div class="gantt-canvas-box"><canvas id="taskGanttChart"></canvas></div></div>
      </div>
    </div>
  </section>
</main>
<footer>Сгенерировано todo-md из __COUNT__ записей JSONL.</footer>
<script>
const TASKS = __DATA__;
const STATUS_ORDER = ["todo","backlog","in_progress","paused","blocked","review","done","cancelled"];
const STATUS_TITLE = { backlog:"Бэклог", todo:"Todo", in_progress:"В работе", paused:"Пауза", blocked:"Заблокировано", review:"Review", done:"Готово", cancelled:"Отменено" };
const SDLC = ["backlog","todo","in_progress","paused","blocked","review","done","cancelled"];
const STATUS_RANK = { backlog:0, todo:1, in_progress:2, paused:3, blocked:4, review:5, done:6, cancelled:7 };
const STATUS_COLOR_FULL = { backlog:"#94a3b8", todo:"#64748b", in_progress:"#f59e0b", paused:"#a16207", blocked:"#ef4444", review:"#3b82f6", done:"#16a34a", cancelled:"#cbd5e1" };
const PRIORITY_ORDER = ["P0","P1","P2","P3"];
const PRIO_CLASS = { P0:"p-P0", P1:"p-P1", P2:"p-P2", P3:"p-P3" };
const PALETTE = ["#2563eb","#16a34a","#d97706","#dc2626","#7c3aed","#0891b2","#db2777","#65a30d","#ca8a04","#475569"];

function countBy(tasks, key, order) {
  const counts = {};
  for (const t of tasks) {
    const v = t[key] || "(без)";
    counts[v] = (counts[v] || 0) + 1;
  }
  const labels = order ? [...order].filter(k => counts[k] !== undefined).concat(Object.keys(counts).filter(k => !(order||[]).includes(k))) : Object.keys(counts);
  return { labels: labels, data: labels.map(l => counts[l] || 0) };
}

function chart(id, agg, paletteOffset = 0) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, {
    type: "doughnut",
    data: { labels: agg.labels, datasets: [{ data: agg.data, backgroundColor: agg.labels.map((_, i) => PALETTE[(i + paletteOffset) % PALETTE.length]) }] },
    options: { plugins: { legend: { position: "right" } }, maintainAspectRatio: false }
  });
}

const statusAgg = countBy(TASKS, "status", STATUS_ORDER);
const priorityAgg = countBy(TASKS, "priority", PRIORITY_ORDER);
const epicTodo = {}, epicBack = {};
for (const t of TASKS) {
  if (t.kind === "EPIC" || !t.epic) continue;
  if (t.status === "todo") epicTodo[t.epic] = (epicTodo[t.epic] || 0) + 1;
  else if (t.status === "backlog") epicBack[t.epic] = (epicBack[t.epic] || 0) + 1;
}
const epicPrio = {};
for (const t of TASKS) if (t.kind === "EPIC") epicPrio[t.id] = t.priority || null;
const epicPR = { P0:0, P1:1, P2:2, P3:3 };
const epicPrioRank = (e) => (epicPrio[e] in epicPR ? epicPR[epicPrio[e]] : 99);
let epicSel = Object.keys(epicTodo);
if (epicSel.length < 10) {
  const fill = Object.keys(epicBack).filter(e => !epicTodo[e]).sort((a, b) => epicPrioRank(a) - epicPrioRank(b) || (epicBack[b] - epicBack[a]));
  epicSel = epicSel.concat(fill.slice(0, 10 - epicSel.length));
}
epicSel.sort((a, b) => epicPrioRank(a) - epicPrioRank(b) || (epicTodo[b] || 0) - (epicTodo[a] || 0) || (epicBack[b] || 0) - (epicBack[a] || 0));
const epicByStatus = {};
for (const e of epicSel) epicByStatus[e] = {};
for (const t of TASKS) {
  if (t.kind === "EPIC" || !t.epic) continue;
  if (!epicByStatus[t.epic]) continue;
  const s = t.status || "(нет)";
  epicByStatus[t.epic][s] = (epicByStatus[t.epic][s] || 0) + 1;
}

// summary cards
const cards = document.getElementById("cards");
const stats = {
  "Всего": TASKS.length,
  "TASK": TASKS.filter(t=>t.kind==="TASK").length,
  "EPIC": TASKS.filter(t=>t.kind==="EPIC").length,
  "Бэклог": TASKS.filter(t=>t.status==="backlog").length,
  "Активные": TASKS.filter(t=>["todo","in_progress","review","blocked","paused"].includes(t.status)).length,
  "Сделано": TASKS.filter(t=>t.status==="done").length,
};
for (const [l,n] of Object.entries(stats)) {
  const d = document.createElement("div"); d.className="card";
  d.innerHTML = '<div class="n">'+n+'</div><div class="l">'+l+'</div>';
  cards.appendChild(d);
}
function plural(n, one, few, many){ const m=n%100, k=n%10; return m>=11&&m<=14?many : k===1?one : k>=2&&k<=4?few : many; }
document.getElementById("sub").textContent = stats["Всего"] + " " + plural(stats["Всего"], "файл", "файла", "файлов");

chart("statusChart", statusAgg, 0);
chart("priorityChart", priorityAgg, 2);
// epic bar
const ectx = document.getElementById("epicChart");
if (ectx) {
  if (!epicSel.length) {
    ectx.parentElement.innerHTML = '<p class="muted">Нет активных задач (Todo/Бэклог) в эпиках.</p>';
  } else {
    ectx.parentElement.style.height = Math.max(260, epicSel.length * 32) + "px";
    new Chart(ectx, {
      type: "bar",
      data: { labels: epicSel, datasets: STATUS_ORDER.map((st,i)=>({ label: STATUS_TITLE[st]||st, data: epicSel.map(e=>(epicByStatus[e][st]||0)), backgroundColor: STATUS_COLOR_FULL[st]||PALETTE[i%PALETTE.length] })) },
      options: { indexAxis: "y", plugins: { legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 10 } } } }, maintainAspectRatio: false, scales: { x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }, y: { stacked: true } } }
    });
  }
}
// ── Gantt: lifecycle timeline (floating bars) ──
const DAY = 86400000;
const nowMs = Date.now();
function tsOf(s){
  if (s == null) return null;
  s = String(s).trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}:\d{2}\s*\((\d{1,10})\))?$/);
  if (!m) return null;
  if (m[4]) return Number(m[4]) * 1000;
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])).getTime();
}
function fmtDate(ms){ return new Date(ms).toLocaleDateString("ru-RU"); }
// shared lifecycle: start..end (ms) from a record's date fields, or null when undated
function lifecycle(t){
  const started = tsOf(t.started), due = tsOf(t.due), completed = tsOf(t.completed), created = tsOf(t.created);
  const firstCh = tsOf(t.first_change), lastCh = tsOf(t.last_change);
  if (started == null && due == null && completed == null && created == null && firstCh == null && lastCh == null) return null;
  let start = started ?? firstCh ?? created ?? completed ?? due ?? lastCh;
  let end = completed ?? lastCh ?? due ?? (started != null ? nowMs : null);
  if (end == null || end < start) end = start + DAY;
  if (end === start) end = start + DAY;
  return { start, end };
}
// non-epic task rows with lifecycle dates, plus per-epic work span (min..max of its tasks)
const taskGantt = [];
const epicTaskSpan = {};
for (const t of TASKS) {
  if (t.kind === "EPIC") continue;
  const lc = lifecycle(t);
  if (!lc) continue;
  const epic = (t.epic || "").trim();
  taskGantt.push({ id: t.id || "", title: t.title || "", status: t.status || "", epic, start: lc.start, end: lc.end });
  if (epic) {
    const sp = epicTaskSpan[epic] || (epicTaskSpan[epic] = { min: Infinity, max: -Infinity, n: 0 });
    sp.min = Math.min(sp.min, lc.start); sp.max = Math.max(sp.max, lc.end); sp.n++;
  }
}
const prRank = p => p in epicPR ? epicPR[p] : 99;
const epicTaskCount = {};
for (const t of TASKS) { if (t.kind !== "EPIC") { const e = (t.epic || "").trim(); if (e) epicTaskCount[e] = (epicTaskCount[e] || 0) + 1; } }
// epic rows: bar = span of the epic's task work (fallback to the epic's own lifecycle)
const epicGantt = [];
const epicsAll = TASKS.filter(t => t.kind === "EPIC");
for (const e of epicsAll) {
  const sp = epicTaskSpan[e.id];
  let start, end;
  if (sp && sp.n) {
    start = sp.min; end = sp.max;
    if (e.status !== "done" && e.status !== "cancelled") end = Math.max(end, nowMs);
  } else {
    const lc = lifecycle(e);
    if (!lc) continue;
    start = lc.start; end = lc.end;
  }
  if (end === start) end = start + DAY;
  const n = epicTaskCount[e.id] || 0;
  epicGantt.push({ id: e.id || "", title: e.title || "", status: e.status || "", label: (e.id || "") + " (" + n + ")", n, start, end });
}
epicGantt.sort((a, b) => a.start - b.start || a.end - b.end);
// epic filter grouped by SDLC status; default = highest-priority "todo" epic
const filterSel = document.getElementById("ganttEpicFilter");
const ganttEpicGroups = {};
for (const e of epicsAll) { const s = e.status || "(нет)"; (ganttEpicGroups[s] = ganttEpicGroups[s] || []).push(e); }
if (filterSel) {
  const order = SDLC.concat(Object.keys(ganttEpicGroups).filter(s => !SDLC.includes(s)));
  for (const s of order) {
    const list = (ganttEpicGroups[s] || []).slice().sort((a, b) => prRank(a.priority) - prRank(b.priority) || String(a.id).localeCompare(String(b.id)));
    if (!list.length) continue;
    const og = document.createElement("optgroup"); og.label = STATUS_TITLE[s] || s;
        for (const e of list) { const o = document.createElement("option"); o.value = e.id; o.textContent = (e.id || "") + " " + (e.title || "") + " (" + (epicTaskCount[e.id] || 0) + ")"; og.appendChild(o); }
    filterSel.appendChild(og);
  }
  const todo = (ganttEpicGroups["todo"] || []).slice().sort((a, b) => prRank(a.priority) - prRank(b.priority) || String(a.id).localeCompare(String(b.id)));
  filterSel.value = todo[0] ? todo[0].id : (epicsAll[0] ? epicsAll[0].id : "");
}
function renderGantt(canvasId, rows, countId, countText){
  const ctx = document.getElementById(canvasId);
  const cnt = document.getElementById(countId);
  if (cnt) cnt.textContent = countText;
  if (!ctx) return;
  const prev = Chart.getChart(canvasId);
  if (prev) prev.destroy();
  const box = ctx.parentElement;
  if (!rows.length) { if (box) box.style.height = "60px"; return; }
  if (box) box.style.height = Math.max(220, rows.length * 24) + "px";
  const xs = rows.flatMap(r => [r.start, r.end]);
  const xMin = Math.min(...xs), xMax = Math.max(...xs);
  const pad = (xMax - xMin) * 0.05 || DAY;
  new Chart(ctx, {
    type: "bar",
    data: { labels: rows.map(r => r.label || r.id), datasets: [{ data: rows.map(r => [r.start, r.end]), backgroundColor: rows.map(r => STATUS_COLOR_FULL[r.status] || "#94a3b8"), borderWidth: 0 }] },
    options: {
      indexAxis: "y", maintainAspectRatio: false,
      scales: { x: { type: "linear", min: xMin - pad, max: xMax + pad, ticks: { callback: v => fmtDate(v) } } },
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => { const r = rows[c.dataIndex]; return r.id + " — " + r.title + (r.n != null ? " (" + r.n + " задач)" : "") + ": " + fmtDate(r.start) + " → " + fmtDate(r.end); } } } }
    }
  });
}
renderGantt("epicGanttChart", epicGantt, "epicGanttCount", "(" + epicGantt.length + " эпиков)");
function renderTaskGantt(){
  const id = filterSel ? filterSel.value : "";
  const rows = taskGantt.filter(r => r.epic && r.epic === id).sort((a, b) => a.start - b.start || a.end - b.end);
  renderGantt("taskGanttChart", rows, "taskGanttCount", "(" + rows.length + " задач)");
}
if (filterSel) filterSel.addEventListener("change", renderTaskGantt);
renderTaskGantt();
// gantt color legend (statuses grouped by color — some share a shade)
(function(){
  const el = document.getElementById("ganttLegend");
  if (!el) return;
  const byColor = {};
  SDLC.forEach(s => { const c = STATUS_COLOR_FULL[s]; if (!c) return; (byColor[c] = byColor[c] || []).push(STATUS_TITLE[s] || s); });
  for (const c in byColor) {
    const it = document.createElement("span"); it.className = "item";
    const sw = document.createElement("span"); sw.className = "sw"; sw.style.background = c;
    const lbl = document.createElement("span"); lbl.textContent = byColor[c].join(", ");
    it.appendChild(sw); it.appendChild(lbl); el.appendChild(it);
  }
})();
// ── Time To Market: cycle-time (started → completed) distribution ──
const ttmCtx = document.getElementById("ttmChart");
if (ttmCtx) {
  const HOUR_MS = 3600000;
  const DAY_B = ["0–1 дн","2–3 дн","4–7 дн","8–14 дн","15–30 дн","31–60 дн","60+ дн"];
  const HOUR_B = ["0–1 ч","1–2 ч","2–4 ч","4–8 ч","8–24 ч","1–2 дн","2–7 дн","7+ дн"];
  const TTM_HOUR_MIN = 5;
  const all = [], startedH = [];
  for (const t of TASKS) {
    if (t.kind === "EPIC") continue;
    if (t.status !== "done") continue;
    const hasSt = tsOf(t.started) != null;
    const st = tsOf(t.started) ?? tsOf(t.first_change);
    const en = tsOf(t.completed) ?? tsOf(t.last_change);
    if (st == null || en == null || en < st) continue;
    const h = (en - st) / HOUR_MS;
    all.push(h);
    if (hasSt) startedH.push(h);
  }
  const hourMode = startedH.length >= TTM_HOUR_MIN;
  const times = hourMode ? startedH : all;
  const B = hourMode ? HOUR_B : DAY_B;
  const counts = B.map(() => 0);
  for (const h of times) {
    const d = h / 24;
    const i = hourMode
      ? (h < 1 ? 0 : h < 2 ? 1 : h < 4 ? 2 : h < 8 ? 3 : h < 24 ? 4 : d < 2 ? 5 : d < 7 ? 6 : 7)
      : (d <= 1 ? 0 : d <= 3 ? 1 : d <= 7 ? 2 : d <= 14 ? 3 : d <= 30 ? 4 : d <= 60 ? 5 : 6);
    counts[i]++;
  }
  let med = null;
  if (times.length) { times.sort((a,b)=>a-b); const m=Math.floor(times.length/2); med = times.length%2 ? times[m] : (times[m-1]+times[m])/2; }
  const medTxt = med == null ? "" : hourMode
    ? "(медиана " + (med < 1 ? Math.round(med*60) + " мин" : med < 24 ? Math.round(med) + " ч" : Math.round(med/24) + " дн") + ", " + times.length + " задач)"
    : "(медиана " + (med < 24 ? "менее 1 дн" : Math.round(med/24) + " дн") + ", " + times.length + " задач)";
  const ttmCountEl = document.getElementById("ttmCount");
  if (ttmCountEl) ttmCountEl.textContent = times.length ? medTxt : "";
  if (times.length) {
    new Chart(ttmCtx, {
      type: "bar",
      data: { labels: B, datasets: [{ label: "Задач", data: counts, backgroundColor: "#2563eb", borderRadius: 3 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: "От начала работы до завершения" } }, y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  } else {
    ttmCtx.parentElement.innerHTML = '<p class="muted">Нет завершённых задач с датами начала и завершения.</p>';
  }
}
// ── Delivered tasks (done + lifecycle dates): shared by heatmap / scatter / throughput ──
const DELIVERED = [];
const localMid = ms => { const d = new Date(ms); d.setHours(0,0,0,0); return d.getTime(); };
for (const t of TASKS) {
  if (t.kind === "EPIC" || t.status !== "done") continue;
  const st = tsOf(t.started) ?? tsOf(t.first_change);
  const en = tsOf(t.completed) ?? tsOf(t.last_change);
  if (st == null || en == null || en < st) continue;
  DELIVERED.push({ en, h: (en - st) / 3600000, hasSt: tsOf(t.started) != null });
}

// ── Heatmap: deliveries per day (GitHub-style) ──
(function () {
  const el = document.getElementById("heatMap");
  if (!el) return;
  if (!DELIVERED.length) { el.innerHTML = '<p class="muted">Нет доставленных задач с датами.</p>'; return; }
  const dayMs = 86400000;
  const byDay = {};
  for (const d of DELIVERED) { const k = localMid(d.en); byDay[k] = (byDay[k] || 0) + 1; }
  const dowMon = ms => (new Date(ms).getDay() + 6) % 7;
  const COLORS = ["#ebedf0", "#9be9a8", "#40c463", "#30a14e", "#216e39"];
  const MONTHS = ["Янв","Фев","Мар","Апр","Май","Июн","Июл","Авг","Сен","Окт","Ноя","Дек"];
  const plural = n => n === 1 ? "задача" : n < 5 ? "задачи" : "задач";
  function render(months) {
    el.innerHTML = "";
    // Trailing N-month window, Monday-aligned, ending today.
    const end = localMid(Date.now());
    let start = end - Math.round(months * 30.44) * dayMs;
    start -= dowMon(start) * dayMs;
    const visDays = Object.keys(byDay).map(Number).filter(k => k >= start && k <= end);
    const max = visDays.length ? Math.max(...visDays.map(k => byDay[k])) : 0;
    const lvl = n => n === 0 ? 0 : n <= Math.max(1, Math.round(max * 0.25)) ? 1 : n <= Math.round(max * 0.5) ? 2 : n <= Math.round(max * 0.75) ? 3 : 4;
    const monthsEl = document.createElement("div"); monthsEl.className = "heat-months";
    const grid = document.createElement("div"); grid.className = "heat-grid";
    let cur = start, prevMonth = -1;
    while (cur <= end) {
      const m = new Date(cur).getMonth();
      const mlbl = document.createElement("span"); mlbl.textContent = m !== prevMonth ? MONTHS[m] : ""; monthsEl.appendChild(mlbl);
      prevMonth = m;
      for (let i = 0; i < 7; i++) {
        const cellDay = cur + i * dayMs, cnt = byDay[cellDay] || 0;
        const c = document.createElement("div"); c.className = "heat-cell"; c.style.background = COLORS[lvl(cnt)];
        c.title = (cnt ? cnt + " " + plural(cnt) : "нет поставок") + " — " + fmtDate(cellDay);
        grid.appendChild(c);
      }
      cur += 7 * dayMs;
    }
    const body = document.createElement("div"); body.className = "heat-body";
    const wdays = document.createElement("div"); wdays.className = "heat-wdays";
    ["Пн","","Ср","","Пт","",""].forEach(w => { const s = document.createElement("span"); s.textContent = w; wdays.appendChild(s); });
    body.appendChild(wdays); body.appendChild(grid);
    el.appendChild(monthsEl); el.appendChild(body);
    const leg = document.createElement("div"); leg.className = "heat-legend";
    leg.innerHTML = "<span>меньше</span>" + COLORS.map(c => '<i style="background:' + c + '"></i>').join("") + "<span>больше</span>";
    el.appendChild(leg);
    const hc = document.getElementById("heatCount");
    if (hc) hc.textContent = "(" + visDays.reduce((s, k) => s + byDay[k], 0) + " задач за " + months + " мес)";
  }
  const periodSel = document.getElementById("heatPeriod");
  if (periodSel) periodSel.addEventListener("change", () => render(parseInt(periodSel.value, 10)));
  render(periodSel ? parseInt(periodSel.value, 10) : 3);
})();

// ── Cycle Time scatter + rolling median ──
const scatterCtx = document.getElementById("scatterChart");
if (scatterCtx) {
  if (!DELIVERED.length) {
    scatterCtx.parentElement.innerHTML = '<p class="muted">Нет доставленных задач с датами.</p>';
  } else {
    const hourMode = DELIVERED.filter(d => d.hasSt).length >= 5;
    const yDiv = hourMode ? 1 : 24;
    const pts = DELIVERED.map(d => ({ x: d.en, y: d.h / yDiv })).sort((a, b) => a.x - b.x);
    const WIN = Math.min(9, pts.length);
    const trend = [];
    for (let i = 0; i < pts.length; i++) {
      const a = Math.max(0, i - Math.floor(WIN / 2)), b = Math.min(pts.length, a + WIN);
      const win = pts.slice(a, b).map(p => p.y).sort((m, n) => m - n);
      trend.push({ x: pts[i].x, y: win.length % 2 ? win[(win.length - 1) / 2] : (win[win.length / 2 - 1] + win[win.length / 2]) / 2 });
    }
    new Chart(scatterCtx, {
      data: { datasets: [
        { type: "scatter", label: "Задача", data: pts, backgroundColor: "rgba(37,99,235,.5)", pointRadius: 3 },
        { type: "line", label: "Скользящая медиана", data: trend, borderColor: "#ef4444", backgroundColor: "#ef4444", pointRadius: 0, borderWidth: 2, tension: 0.3, fill: false }
      ] },
      options: { maintainAspectRatio: false, scales: { x: { type: "linear", title: { display: true, text: "Дата закрытия" }, ticks: { callback: v => fmtDate(v), maxTicksLimit: 8 } }, y: { title: { display: true, text: hourMode ? "Cycle time, ч" : "Cycle time, дн" }, beginAtZero: true } }, plugins: { legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 10 } } }, tooltip: { callbacks: { label: c => { const y = c.parsed.y; const yt = hourMode ? (y < 1 ? Math.round(y * 60) + " мин" : Math.round(y * 10) / 10 + " ч") : Math.round(y * 10) / 10 + " дн"; return (c.datasetIndex === 1 ? "медиана · " : "") + fmtDate(c.parsed.x) + " · " + yt; } } } } }
    });
  }
}

// ── Throughput: deliveries per week ──
const tpCtx = document.getElementById("throughputChart");
if (tpCtx) {
  if (!DELIVERED.length) {
    tpCtx.parentElement.innerHTML = '<p class="muted">Нет доставленных задач с датами.</p>';
  } else {
    const dayMs = 86400000;
    const byWeek = {};
    for (const d of DELIVERED) { const m = localMid(d.en); const wk = m - ((new Date(m).getDay() + 6) % 7) * dayMs; byWeek[wk] = (byWeek[wk] || 0) + 1; }
    const wks = Object.keys(byWeek).map(Number).sort((a, b) => a - b);
    const labels = [], data = [];
    for (let w = wks[0]; w <= wks[wks.length - 1]; w += 7 * dayMs) { labels.push(fmtDate(w)); data.push(byWeek[w] || 0); }
    new Chart(tpCtx, {
      type: "bar",
      data: { labels: labels, datasets: [{ label: "Закрыто", data: data, backgroundColor: "#16a34a", borderRadius: 3 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxTicksLimit: 12, autoSkip: true } }, y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }
}
// ── Type distribution (filterable by status) ──
const TYPE_COLOR = { feat:"#16a34a", fix:"#dc2626", docs:"#2563eb", refactor:"#7c3aed", test:"#d97706", build:"#0891b2", ci:"#ea580c", chore:"#64748b", perf:"#db2777", style:"#65a30d", revert:"#9333ea", research:"#0d9488", epic:"#475569" };
const typeStatusEl = document.getElementById("typeStatus");
let typeChartInst = null;
(function () {
  if (!typeStatusEl) return;
  const sel = document.getElementById("typeChart");
  if (!sel) return;
  const opt = document.createElement("option"); opt.value = "all"; opt.textContent = "Все статусы"; typeStatusEl.appendChild(opt);
  STATUS_ORDER.forEach(s => { const o = document.createElement("option"); o.value = s; o.textContent = STATUS_TITLE[s] || s; typeStatusEl.appendChild(o); });
  const distOf = tasks => { const m = {}; for (const t of tasks) { const v = t.type || "(без типа)"; m[v] = (m[v]||0)+1; } return Object.entries(m).sort((a,b)=>b[1]-a[1] || String(a[0]).localeCompare(String(b[0]),"ru")); };
  function build() {
    const st = typeStatusEl.value;
    const subset = st === "all" ? TASKS.filter(t=>t.kind!=="EPIC") : TASKS.filter(t=>t.kind!=="EPIC" && t.status===st);
    const entries = distOf(subset);
    const labels = entries.map(e=>e[0]), data = entries.map(e=>e[1]);
    const colors = labels.map((l,i)=>TYPE_COLOR[l] || PALETTE[i % PALETTE.length]);
    const cntEl = document.getElementById("typeCount");
    if (cntEl) cntEl.textContent = "(" + subset.length + " задач)";
    if (typeChartInst) {
      typeChartInst.data.labels = labels; typeChartInst.data.datasets[0].data = data; typeChartInst.data.datasets[0].backgroundColor = colors; typeChartInst.update();
    } else {
      typeChartInst = new Chart(sel, { type:"doughnut", data:{ labels, datasets:[{ data, backgroundColor: colors }] }, options:{ plugins:{ legend:{ position:"right", labels:{ boxWidth:12, padding:8, font:{size:10} } } }, maintainAspectRatio:false } });
    }
  }
  typeStatusEl.addEventListener("change", build);
  build();
})();

// ── Who does the work (assignee / role / agent) for done tasks ──
const _knownAgents = new Set(), _knownRoles = new Set();
for (const t of TASKS) {
  if (!t.assignee) continue;
  const m = String(t.assignee).match(/^(.+?)\s*\(([^)]+)\)\s*$/);
  if (m) { _knownRoles.add(m[1].trim()); _knownAgents.add(m[2].trim()); }
}
const roleOf = v => { if (!v) return null; const s = String(v); const m = s.match(/^(.+?)\s*\(([^)]+)\)\s*$/); if (m) return m[1].trim() || null; const k = s.trim(); return _knownAgents.has(k) ? null : k; };
const agentOf = v => { if (!v) return null; const s = String(v); const m = s.match(/^(.+?)\s*\(([^)]+)\)\s*$/); if (m) return m[2].trim(); const k = s.trim(); return _knownRoles.has(k) ? null : k; };
const whoDimEl = document.getElementById("whoDim");
let whoChartInst = null;
(function () {
  if (!whoDimEl) return;
  const sel = document.getElementById("whoChart");
  if (!sel) return;
  [["role","По роли"],["agent","По агенту"],["assignee","По исполнителю"]].forEach(([v,l]) => { const o=document.createElement("option"); o.value=v; o.textContent=l; whoDimEl.appendChild(o); });
  whoDimEl.value = "role";
  const TOP = 12;
  function build() {
    const dim = whoDimEl.value;
    const m = {};
    for (const t of TASKS) {
      if (t.kind === "EPIC" || t.status !== "done") continue;
      let key;
      if (dim === "role") key = roleOf(t.assignee) || "(без роли)";
      else if (dim === "agent") key = agentOf(t.assignee) || "(без агента)";
      else key = t.assignee || "(без исполнителя)";
      m[key] = (m[key]||0)+1;
    }
    const entries = Object.entries(m).sort((a,b)=>b[1]-a[1] || String(a[0]).localeCompare(String(b[0]),"ru"));
    let labels, data;
    if (entries.length <= TOP) { labels = entries.map(e=>e[0]); data = entries.map(e=>e[1]); }
    else { const top = entries.slice(0, TOP); labels = top.map(e=>e[0]).concat(["остальные"]); data = top.map(e=>e[1]).concat([entries.slice(TOP).reduce((s,e)=>s+e[1],0)]); }
    const colors = labels.map((l,i)=> l === "остальные" ? "#cbd5e1" : PALETTE[i % PALETTE.length]);
    if (whoChartInst) {
      whoChartInst.data.labels = labels; whoChartInst.data.datasets[0].data = data; whoChartInst.data.datasets[0].backgroundColor = colors; whoChartInst.update();
    } else {
      whoChartInst = new Chart(sel, { type:"bar", data:{ labels, datasets:[{ label:"Done", data, backgroundColor: colors, borderRadius:3 }] }, options:{ indexAxis:"y", maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ x:{beginAtZero:true, ticks:{precision:0}} } } });
    }
  }
  whoDimEl.addEventListener("change", build);
  build();
})();

function esc(s){ return String(s).replace(/[&<>]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;"}[c])); }

// ── Kanban board (groupable: status / epic) ──
function sortByStatusThenName(a, b) {
  const ra = STATUS_RANK[a.status] ?? 99, rb = STATUS_RANK[b.status] ?? 99;
  return ra - rb || String(a.title || "").localeCompare(String(b.title || "")) || String(a.id).localeCompare(b.id);
}
function sortByPriorityThenId(a, b) {
  return (a.priority || "Z").localeCompare(b.priority || "Z") || String(a.id).localeCompare(b.id);
}
function sortByTitle(a, b) {
  return String(a.title || "").localeCompare(String(b.title || "")) || String(a.id).localeCompare(b.id);
}
function sortByPriority(a, b) {
  const PR = { "P0":0, "P1":1, "P2":2, "P3":3 };
  const ra = a.priority in PR ? PR[a.priority] : 99;
  const rb = b.priority in PR ? PR[b.priority] : 99;
  return ra - rb || String(a.id).localeCompare(b.id);
}
function sortByCreatedDesc(a, b) {
  const ka = a.created || a.first_change || "", kb = b.created || b.first_change || "";
  if (!ka && !kb) return String(a.id).localeCompare(b.id);
  if (!ka) return 1; if (!kb) return -1;
  return kb.localeCompare(ka) || String(a.id).localeCompare(b.id);
}
function sortByClosedDesc(a, b) {
  const ka = a.completed || a.last_change || "", kb = b.completed || b.last_change || "";
  if (!ka && !kb) return String(a.id).localeCompare(b.id);
  if (!ka) return 1; if (!kb) return -1;
  return kb.localeCompare(ka) || String(a.id).localeCompare(b.id);
}
function columnSortFn(groupBy) {
  switch (boardSort) {
    case "name": return sortByTitle;
    case "priority": return sortByPriority;
    case "created": return sortByCreatedDesc;
    case "closed": return sortByClosedDesc;
    default: return groupBy === "epic" ? sortByStatusThenName : sortByPriorityThenId;
  }
}

function boardCard(t, groupBy) {
  const cs = STATUS_COLOR_FULL[t.status] || "#94a3b8";
  const pbadge = t.priority ? '<span class="pbadge '+(PRIO_CLASS[t.priority]||"")+'">'+esc(t.priority)+'</span>' : '';
  const idHtml = t.url ? '<a class="ct-link" href="'+esc(t.url)+'" target="_blank" rel="noopener">'+esc(t.id||"")+'</a>' : esc(t.id||"");
  const metaParts = [];
  if (groupBy !== "epic" && t.epic) metaParts.push(esc(t.epic));
  if (t.due) metaParts.push("до " + esc(t.due));
  const meta = metaParts.join(" · ");
  const c = document.createElement("div"); c.className = "card-task"; c.style.setProperty("--cs", cs);
  c.innerHTML =
    '<div class="ct-id"><span><span class="sdot"></span>'+idHtml+'</span>'+pbadge+'</div>'
    + '<div class="ct-title">'+esc(t.title||"")+'</div>'
    + (meta ? '<div class="ct-meta">'+meta+'</div>' : '');
  return c;
}
function makeColumn(title, count, colColor, tasks, sortFn, groupBy) {
  tasks.sort(sortFn || sortByPriorityThenId);
  const col = document.createElement("div"); col.className = "col";
  const h = document.createElement("h3"); h.style.setProperty("--cc", colColor);
  h.innerHTML = '<span>'+esc(title)+'</span><span class="count">'+count+'</span>';
  col.appendChild(h);
  const body = document.createElement("div"); body.className = "col-body";
  for (const t of tasks) body.appendChild(boardCard(t, groupBy));
  col.appendChild(body);
  return col;
}
let boardGroup = "status";
let boardQuery = "";
let boardSort = "default";
const boardEl = document.getElementById("board");
function renderBoard(groupBy) {
  boardGroup = groupBy;
  if (!boardEl) return;
  boardEl.innerHTML = "";
  boardEl.classList.toggle("epic-sectioned", groupBy === "epic");
  const q = boardQuery.toLowerCase().trim();
  const all = TASKS.filter(t => t.kind !== "EPIC");
  const tasks = q ? all.filter(t => [t.id,t.title,t.epic,t.status,t.priority].filter(Boolean).join(" ").toLowerCase().includes(q)) : all;
  if (!tasks.length) { boardEl.innerHTML = '<p class="muted">'+(q ? "Ничего не найдено." : "Нет задач для отображения.")+'</p>'; return; }
  if (groupBy === "epic") {
    const epicUrl = {}; for (const t of TASKS) if (t.kind === "EPIC" && t.url) epicUrl[t.id] = t.url;
    const bySt = {};
    for (const t of tasks) { const s = t.status || "(нет)"; (bySt[s] = bySt[s] || []).push(t); }
    const stOrder = SDLC.filter(s => bySt[s] && bySt[s].length).concat(Object.keys(bySt).filter(s => !SDLC.includes(s)));
    for (const s of stOrder) {
      const stasks = bySt[s];
      const byEp = {};
      for (const t of stasks) { const e = t.epic && String(t.epic).trim() ? t.epic : "(без эпика)"; (byEp[e] = byEp[e] || []).push(t); }
      const eps = Object.keys(byEp).filter(e => e !== "(без эпика)").sort((a, b) => byEp[b].length - byEp[a].length || a.localeCompare(b));
      if (byEp["(без эпика)"]) eps.push("(без эпика)");
      const sec = document.createElement("section"); sec.className = "epic-sec";
      const head = document.createElement("h3"); head.className = "epic-sec-head"; head.style.setProperty("--cs", STATUS_COLOR_FULL[s]||"#94a3b8");
      head.innerHTML = '<span><span class="sdot"></span>'+esc(STATUS_TITLE[s]||s)+'</span><span class="count">'+stasks.length+'</span>';
      sec.appendChild(head);
      const wrap = document.createElement("div"); wrap.className = "cols-wrap";
      for (const e of eps) {
        const ecards = byEp[e].slice().sort(columnSortFn(groupBy));
        const col = document.createElement("div"); col.className = "col";
        const ch = document.createElement("h3"); ch.style.setProperty("--cc", STATUS_COLOR_FULL[s]||"#94a3b8");
        const headInner = epicUrl[e] ? '<a class="col-link" href="'+esc(epicUrl[e])+'" target="_blank" rel="noopener">'+esc(e)+'</a>' : esc(e);
        ch.innerHTML = '<span>'+headInner+'</span><span class="count">'+ecards.length+'</span>';
        col.appendChild(ch);
        const body = document.createElement("div"); body.className = "col-body";
        for (const t of ecards) body.appendChild(boardCard(t, groupBy));
        col.appendChild(body);
        wrap.appendChild(col);
      }
      sec.appendChild(wrap);
      boardEl.appendChild(sec);
    }
  } else {
    const bySt = {};
    for (const t of tasks) { const s = t.status || "(нет)"; (bySt[s] = bySt[s] || []).push(t); }
    const order = SDLC.filter(s => bySt[s] && bySt[s].length).concat(Object.keys(bySt).filter(s => !SDLC.includes(s)));
    for (const s of order) boardEl.appendChild(makeColumn(STATUS_TITLE[s] || s, bySt[s].length, STATUS_COLOR_FULL[s] || "#94a3b8", bySt[s], columnSortFn(groupBy), groupBy));
  }
}
const grpEl = document.getElementById("grp");
if (grpEl) {
  grpEl.addEventListener("click", e => {
    const b = e.target.closest(".grp"); if (!b) return;
    grpEl.querySelectorAll(".grp").forEach(x => x.classList.toggle("active", x === b));
    renderBoard(b.dataset.grp);
  });
}
const searchEl = document.getElementById("search");
if (searchEl) {
  searchEl.addEventListener("input", e => { boardQuery = e.target.value; renderBoard(boardGroup); });
}
const sortEl = document.getElementById("sort");
if (sortEl) {
  sortEl.addEventListener("change", e => { boardSort = e.target.value; renderBoard(boardGroup); });
}
renderBoard("status");

// ── Tabs: switch panes, resize charts on reveal; remember active tab via URL hash ──
const tabsEl = document.getElementById("tabs");
const TABS = ["board","charts","gantt"];
function switchTab(name){
  if (!TABS.includes(name)) name = "board";
  const btn = tabsEl ? tabsEl.querySelector('.tab[data-tab="'+name+'"]') : null;
  if (tabsEl) tabsEl.querySelectorAll(".tab").forEach(b => b.classList.toggle("active", b === btn));
  document.querySelectorAll(".tab-pane").forEach(p => p.classList.toggle("hidden", p.id !== "pane-" + name));
  if (name === "charts") ["statusChart","priorityChart","ttmChart","epicChart","scatterChart","throughputChart","typeChart","whoChart"].forEach(id => { const c = Chart.getChart(id); if (c) c.resize(); });
  if (name === "gantt") ["epicGanttChart","taskGanttChart"].forEach(id => { const c = Chart.getChart(id); if (c) c.resize(); });
}
if (tabsEl) tabsEl.addEventListener("click", e => { const btn = e.target.closest(".tab"); if (!btn) return; location.hash = btn.dataset.tab; });
window.addEventListener("hashchange", () => switchTab((location.hash || "").slice(1)));
switchTab((location.hash || "").slice(1));
</script>
</body>
</html>
HTML;

    $html = $template;
    $html = str_replace('__TITLE__', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $html);
    $html = str_replace('__DATA__', $dataJson, $html);
    $html = str_replace('__COUNT__', (string) count($tasks), $html);

    return $html;
}

// ── Command: init ───────────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_init(array $args): void
{
    $force      = false;
    $targetArg  = null;
    $docsPath   = null;
    $agentsPath = null;

    foreach ($args as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo initHelp();
            exit(0);
        }

        if ($arg === '--force') {
            $force = true;
        } elseif (str_starts_with($arg, '--docs-path=')) {
            $docsPath = substr($arg, strlen('--docs-path='));
        } elseif (str_starts_with($arg, '--agents-path=')) {
            $agentsPath = substr($arg, strlen('--agents-path='));
        } else {
            $targetArg = $arg;
        }
    }

    $packageRoot = dirname(__DIR__);
    $targetDir   = $targetArg !== null ? realpath($targetArg) : getcwd();

    if ($targetDir === false || !is_dir($targetDir)) {
        fwrite(STDERR, 'Error: target directory does not exist: ' . ($targetArg ?? getcwd()) . PHP_EOL);
        exit(1);
    }

    $sourceDocs = $packageRoot . '/docs/todo-md';
    $docsPath   = $docsPath ?? 'docs/todo-md';
    $agentsPath = $agentsPath ?? 'todo/AGENTS.md';
    $todoDir    = $targetDir . '/todo';

    if (!is_dir($sourceDocs)) {
        fwrite(STDERR, "Error: package docs/ not found at $sourceDocs" . PHP_EOL);
        exit(1);
    }

    if ($force) {
        echo "  ⚠  --force: existing files will be overwritten." . PHP_EOL;
    }

    // ── 1. Create directory structure ──

    $directories = [
        $todoDir,
        $todoDir . '/backlog',
        $todoDir . '/done',
        $todoDir . '/cancelled',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "  Created  $dir/" . PHP_EOL;
        } else {
            echo "  Exists   $dir/" . PHP_EOL;
        }

        $gitkeep = $dir . '/.gitkeep';
        if (!file_exists($gitkeep)) {
            file_put_contents($gitkeep, '');
        }
    }

    // ── 2. Copy docs ──

    $targetDocs = $targetDir . '/' . $docsPath;

    if (!is_dir($targetDocs)) {
        mkdir($targetDocs, 0755, true);
    }

    $copied   = 0;
    $skipped  = 0;
    $updated  = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDocs, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourceDocs) + 1);
        $targetPath   = $targetDocs . '/' . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
            continue;
        }

        if (file_exists($targetPath)) {
            if ($force) {
                copy($item->getPathname(), $targetPath);
                echo "  Updated  $docsPath/$relativePath" . PHP_EOL;
                $updated++;
            } else {
                echo "  Skip     $docsPath/$relativePath (already exists)" . PHP_EOL;
                $skipped++;
            }
            continue;
        }

        $targetDirForFile = dirname($targetPath);
        if (!is_dir($targetDirForFile)) {
            mkdir($targetDirForFile, 0755, true);
        }

        copy($item->getPathname(), $targetPath);
        echo "  Copied   $docsPath/$relativePath" . PHP_EOL;
        $copied++;
    }

    // ── 3. Copy AGENTS.md ──

    $agentsSource = $packageRoot . '/todo/AGENTS.md';
    $agentsTarget = $targetDir . '/' . $agentsPath;

    if (file_exists($agentsSource)) {
        if (file_exists($agentsTarget)) {
            if ($force) {
                copy($agentsSource, $agentsTarget);
                echo "  Updated  $agentsPath" . PHP_EOL;
                $updated++;
            } else {
                echo "  Skip     $agentsPath (already exists)" . PHP_EOL;
                $skipped++;
            }
        } else {
            $agentsDir = dirname($agentsTarget);
            if (!is_dir($agentsDir)) {
                mkdir($agentsDir, 0755, true);
            }
            copy($agentsSource, $agentsTarget);
            echo "  Copied   $agentsPath" . PHP_EOL;
            $copied++;
        }
    }

    // ── 3b. Write .todo-md.php (project config for validate) ──

    $configTarget  = $targetDir . '/.todo-md.php';
    $configContent = <<<'PHP'
<?php

declare(strict_types=1);

// Project-level configuration for todo-md validate.
// Lists canonical roles and agents for author/assignee validation.
// Full reference: docs/todo-md/reference/CONFIG.md
return [
    // Канонические роли проекта (текст перед скобками). Пусто/отсутствует — роль
    // проверяется только по формату. Раскомментируйте и дополните под проект:
    // 'roles' => ['Бэкендер', 'Фронтендер', 'Девопс', 'Аналитик', 'Архитектор'],

    // Канонические агенты (lowercase-идентификатор в скобках). Пусто/отсутствует —
    // используется пакетный список из reference/AI_AGENTS.md.
    // 'agents' => ['codex-cli', 'codex', 'pi', 'kilocode'],

    // Считать нарушения author/assignee ошибками (аналог флага --strict).
    'strict' => false,
];
PHP;

    if (file_exists($configTarget)) {
        if ($force) {
            file_put_contents($configTarget, $configContent);
            echo "  Updated  .todo-md.php" . PHP_EOL;
            $updated++;
        } else {
            echo "  Skip     .todo-md.php (already exists)" . PHP_EOL;
            $skipped++;
        }
    } else {
        file_put_contents($configTarget, $configContent);
        echo "  Copied   .todo-md.php" . PHP_EOL;
        $copied++;
    }

    // ── 4. Update .gitignore ──

    // 4a. docs/.gitignore — docs path
    $gitignore    = $targetDir . '/docs/.gitignore';
    $gitignoreDir = dirname($docsPath) === 'docs' ? basename($docsPath) . '/' : $docsPath . '/';
    $entry        = $gitignoreDir;

    if (file_exists($gitignore)) {
        $content = file_get_contents($gitignore);
        if (!str_contains($content, $entry)) {
            $content = rtrim($content) . PHP_EOL . $entry . PHP_EOL;
            file_put_contents($gitignore, $content);
            echo "  Updated docs/.gitignore (+ $entry)" . PHP_EOL;
        }
    } else {
        if (!is_dir(dirname($gitignore))) {
            mkdir(dirname($gitignore), 0755, true);
        }
        file_put_contents($gitignore, $entry . PHP_EOL);
        echo "  Updated docs/.gitignore (+ $entry)" . PHP_EOL;
    }

    // 4b. agents path .gitignore — AGENTS.md
    $agentsGitignore = $targetDir . '/' . dirname($agentsPath) . '/.gitignore';
    $agentsEntry     = basename($agentsPath);

    if (file_exists($agentsGitignore)) {
        $content = file_get_contents($agentsGitignore);
        if (!str_contains($content, $agentsEntry)) {
            $content = rtrim($content) . PHP_EOL . $agentsEntry . PHP_EOL;
            file_put_contents($agentsGitignore, $content);
            echo '  Updated ' . dirname($agentsPath) . "/.gitignore (+ $agentsEntry)" . PHP_EOL;
        }
    } else {
        if (!is_dir(dirname($agentsGitignore))) {
            mkdir(dirname($agentsGitignore), 0755, true);
        }
        file_put_contents($agentsGitignore, $agentsEntry . PHP_EOL);
        echo '  Updated ' . dirname($agentsPath) . "/.gitignore (+ $agentsEntry)" . PHP_EOL;
    }

    // ── 5. Summary ──

    echo PHP_EOL;
    echo "Done. $copied copied, $updated updated, $skipped skipped." . PHP_EOL;
    echo PHP_EOL;
    echo 'Next steps:' . PHP_EOL;
    echo "  1. Add this line to your project's AGENTS.md:" . PHP_EOL;
    echo "     * Регламент работы с задачами: [`$agentsPath`]($agentsPath)." . PHP_EOL;
    echo "  2. Create tasks: php vendor/bin/todo-md create TASK-<category>-<name> --type=<type> --title=\"...\"" . PHP_EOL;
    echo '  3. Validate tasks: php vendor/bin/todo-md validate' . PHP_EOL;
}

function initHelp(): string
{
    return <<<'TXT'
todo-md init — initialise a todo/ kanban board in the current project.

Usage:
  php vendor/bin/todo-md init [target-dir] [--docs-path=<path>] [--agents-path=<path>] [--force]

  target-dir    — project root (default: current working directory).
  --docs-path   — relative path where docs will be copied (default: docs/todo-md).
  --agents-path — relative path where AGENTS.md will be copied (default: todo/AGENTS.md).
  --force       — overwrite existing files with fresh copies from the package.

TXT;
}

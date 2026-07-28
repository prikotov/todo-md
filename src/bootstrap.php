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

// ── Command: transition ─────────────────────────────────────────────────────

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

    $backup = !isset($parsed['flags']['no-backup']);

    try {
        $root = Board::resolveRoot($parsed['opts']['root'] ?? (getcwd() ?: '.'));
        echo Board::transition($root, $id, $status, ['backup' => $backup]) . PHP_EOL;
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
  php vendor/bin/todo-md $verb <ID> [--no-backup]

Atomically sets `status: $status`, moves the file to the canonical folder,
rewrites relative markdown links (outbound in the moved file + inbound in
referencing files), and validates. On validation failure all changes are
rolled back.

Options:
  --no-backup  Skip writing a backup to .todo-md-backup/.
  --help       Show this help.

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
            'backup'     => !isset($parsed['flags']['no-backup']),
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
  php vendor/bin/todo-md create <ID> --type=<type> [options]

ID format:
  TASK-<category>-<name>  → task  (--type required)
  EPIC-<category>-<name>  → epic  (type forced to epic)

Options:
  --type=<type>        Task type (required for tasks): fix, feat, build, chore, ci, docs, style, refactor, perf, test, revert.
  --title=<title>      H1 title (default: derived from ID).
  --value=<V0-V4>      Business value (default: V2).
  --complexity=<C0-C5> Complexity (default: C2).
  --priority=<P0-P3>   Priority (default: P2).
  --author=<author>    Author role (default: "Исполнитель (pi)").
  --status=<status>    Initial status (default: todo).
  --epic=<EPIC-ID>     Epic this task belongs to.
  --depends-on=<ids>   Comma-separated plain IDs.
  --no-backup          Skip backup.
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
            ['backup' => !isset($parsed['flags']['no-backup'])],
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
  php vendor/bin/todo-md set <ID> <field>=<value> [--no-backup]

If <field> is `status`, the full transition runs (folder move + link rewrite).
Otherwise only the front matter changes in place.

Options:
  --no-backup  Skip backup.
  --help       Show this help.

TXT;
}

// ── Command: validate ───────────────────────────────────────────────────────

/**
 * @param list<string> $args
 */
function cli_validate(array $args): void
{
    $targets = [];

    foreach ($args as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo validateHelp();
            exit(0);
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

    foreach ($files as $file) {
        $result   = Validator::validateFile($file, $idIndex);
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
  --help    Show this help.

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

    return [
        'id'         => $id,
        'kind'       => strtoupper($kind),
        'title'      => Parser::extractTitle($body, $id),
        'file'       => Parser::makeRelativePath($file, $cwd),
        'folder'     => Parser::detectFolder($file),
        'status'     => Parser::valueOrNull($frontMatter['status'] ?? null),
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
    $title    = 'Задачи todo-md';

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

    $html = dashboardRenderHtml($tasks, $title);

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
  php vendor/bin/todo-md dashboard <input.jsonl|-> [-o out.html] [--title="..."]

Options:
  -o, --output=FILE   Write HTML to FILE (default: stdout).
  --title="TEXT"      Dashboard title (default: "Задачи todo-md").
  --help              Show this help.

TXT;
}

/**
 * @param list<array<string, mixed>> $tasks
 */
function dashboardRenderHtml(array $tasks, string $title): string
{
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
  header { background: #1f2937; color: #fff; padding: 1rem 1.5rem; }
  header h1 { margin: 0; font-size: 1.25rem; }
  header .sub { opacity: .8; font-size: .85rem; margin-top: .25rem; }
  main { max-width: 1100px; margin: 0 auto; padding: 1.25rem; }
  .cards { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem; }
  .card { background: #fff; border-radius: .5rem; padding: .85rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); min-width: 120px; }
  .card .n { font-size: 1.6rem; font-weight: 700; }
  .card .l { font-size: .8rem; opacity: .7; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
  .panel { background: #fff; border-radius: .5rem; padding: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
  .panel h2 { margin: 0 0 .75rem; font-size: .95rem; }
  .table-wrap { background: #fff; border-radius: .5rem; padding: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
  .table-wrap input { padding: .4rem .6rem; width: 100%; box-sizing: border-box; margin-bottom: .75rem; border: 1px solid #d0d7de; border-radius: .375rem; background: inherit; color: inherit; }
  table { border-collapse: collapse; width: 100%; font-size: .85rem; }
  th, td { text-align: left; padding: .35rem .5rem; border-bottom: 1px solid #eaeef2; vertical-align: top; }
  th { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; opacity: .65; }
  .badge { display: inline-block; padding: .05rem .4rem; border-radius: .375rem; font-size: .72rem; background: #eef1f4; }
  .status-todo, .status-backlog { background: #e2e8f0; color: #334155; }
  .status-in_progress, .status-review { background: #fef3c7; color: #92400e; }
  .status-blocked, .status-paused { background: #fee2e2; color: #991b1b; }
  .status-done { background: #dcfce7; color: #166534; }
  .status-cancelled { background: #f1f5f9; color: #64748b; text-decoration: line-through; }
  a { color: #2563eb; }
  footer { text-align: center; opacity: .5; font-size: .75rem; padding: 1.5rem; }
</style>
</head>
<body>
<header>
  <h1>__TITLE__</h1>
  <div class="sub" id="sub"></div>
</header>
<main>
  <div class="cards" id="cards"></div>
  <div class="grid">
    <div class="panel"><h2>По статусам</h2><canvas id="statusChart"></canvas></div>
    <div class="panel"><h2>По приоритетам</h2><canvas id="priorityChart"></canvas></div>
    <div class="panel"><h2>По папкам</h2><canvas id="folderChart"></canvas></div>
    <div class="panel"><h2>По эпикам (топ-10)</h2><canvas id="epicChart"></canvas></div>
  </div>
  <div class="table-wrap">
    <input id="search" type="search" placeholder="Фильтр по id, заголовку, эпику, исполнителю…">
    <table>
      <thead><tr><th>ID</th><th>Статус</th><th>P</th><th>Эпик</th><th>Заголовок</th><th>Исполнитель</th></tr></thead>
      <tbody id="rows"></tbody>
    </table>
  </div>
</main>
<footer>Сгенерировано todo-md из __COUNT__ записей JSONL.</footer>
<script>
const TASKS = __DATA__;
const STATUS_ORDER = ["todo","backlog","in_progress","paused","blocked","review","done","cancelled"];
const STATUS_LABEL = {todo:"todo",backlog:"backlog",in_progress:"in_progress",paused:"paused",blocked:"blocked",review:"review",done:"done",cancelled:"cancelled"};
const PRIORITY_ORDER = ["P0","P1","P2","P3"];
const FOLDER_ORDER = ["active","backlog","done","cancelled"];
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
const folderAgg = countBy(TASKS, "folder", FOLDER_ORDER);
const epicCounts = {};
for (const t of TASKS) { if (t.epic) epicCounts[t.epic] = (epicCounts[t.epic]||0)+1; }
const epicLabels = Object.keys(epicCounts).sort((a,b)=>epicCounts[b]-epicCounts[a]).slice(0,10);

// summary cards
const cards = document.getElementById("cards");
const stats = {
  "Всего": TASKS.length,
  "TASK": TASKS.filter(t=>t.kind==="TASK").length,
  "EPIC": TASKS.filter(t=>t.kind==="EPIC").length,
  "Активные": TASKS.filter(t=>["todo","in_progress","review","blocked","paused"].includes(t.status)).length,
  "Сделано": TASKS.filter(t=>t.status==="done").length,
};
for (const [l,n] of Object.entries(stats)) {
  const d = document.createElement("div"); d.className="card";
  d.innerHTML = '<div class="n">'+n+'</div><div class="l">'+l+'</div>';
  cards.appendChild(d);
}
document.getElementById("sub").textContent = stats["Всего"] + " записей";

chart("statusChart", statusAgg, 0);
chart("priorityChart", priorityAgg, 2);
chart("folderChart", folderAgg, 4);
// epic bar
const ectx = document.getElementById("epicChart");
if (ectx) new Chart(ectx, {
  type: "bar",
  data: { labels: epicLabels, datasets: [{ data: epicLabels.map(l=>epicCounts[l]), backgroundColor: "#2563eb" }] },
  options: { indexAxis: "y", plugins: { legend: { display: false } }, maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});

// table
const rowsEl = document.getElementById("rows");
function renderRows(list) {
  rowsEl.innerHTML = "";
  for (const t of list) {
    const tr = document.createElement("tr");
    const st = t.status || "";
    tr.innerHTML = '<td>'+esc(t.id)+'</td>'
      + '<td><span class="badge status-'+st+'">'+esc(st)+'</span></td>'
      + '<td>'+esc(t.priority||"")+'</td>'
      + '<td>'+esc(t.epic||"")+'</td>'
      + '<td>'+esc(t.title||"")+'</td>'
      + '<td>'+esc(t.assignee||"")+'</td>';
    rowsEl.appendChild(tr);
  }
}
function esc(s){ return String(s).replace(/[&<>]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;"}[c])); }
renderRows(TASKS);
document.getElementById("search").addEventListener("input", e => {
  const q = e.target.value.toLowerCase().trim();
  if (!q) return renderRows(TASKS);
  renderRows(TASKS.filter(t => [t.id,t.title,t.epic,t.assignee,t.status,t.priority].filter(Boolean).join(" ").toLowerCase().includes(q)));
});
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

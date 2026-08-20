<?php

declare(strict_types=1);

/**
 * Fixture tests for bin/todo-md-validate — lock current behavior before the
 * shared-module refactor. Run against the real binary via subprocess.
 */

test('validate: clean board passes', function (): void {
    $root = Fixture::board([
        'todo/TASK-foo-bar.todo.md' => Fixture::taskFile('TASK-foo-bar', 'Bar task'),
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, "validate should pass on clean board\n$err");
    expectContains('1 file(s): 0 error', $out, 'summary line');
});

test('validate: missing required field fails', function (): void {
    $content = Fixture::taskFile('TASK-bad-task', 'Bad');
    // Strip the status line entirely
    $content = preg_replace('/^status:.*$/m', '', $content);
    $root = Fixture::board([
        'todo/TASK-bad-task.todo.md' => $content,
    ]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on missing status');
    expectContains('missing front matter field `status`', $out, 'missing-field error');
});

test('validate: invalid enum fails', function (): void {
    $content = Fixture::taskFile('TASK-enum-bad', 'Enum', ['status' => 'wat']);
    $root = Fixture::board(['todo/TASK-enum-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on invalid status');
    expectContains('`status` must be one of', $out, 'enum error');
});

test('validate: folder status mismatch fails', function (): void {
    // Task in done/ but status todo
    $content = Fixture::taskFile('TASK-folder-bad', 'Folder', ['status' => 'todo']);
    $root = Fixture::board(['todo/done/TASK-folder-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on folder/status mismatch');
    expectContains('must have `status: done`', $out, 'folder-status error');
});

test('validate: correct folder passes', function (): void {
    $content = Fixture::taskFile('TASK-folder-ok', 'Folder', ['status' => 'done']);
    $root = Fixture::board(['todo/done/TASK-folder-ok.todo.md' => $content]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, "done/ + status done should pass\n$err");
});

test('validate: broken local link fails', function (): void {
    $content = Fixture::taskFile('TASK-link-bad', 'Link') . "\n- [missing](does-not-exist.md)\n";
    $root = Fixture::board(['todo/TASK-link-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on broken link');
    expectContains('broken local markdown link', $out, 'broken-link error');
});

test('validate: template placeholder fails', function (): void {
    $content = Fixture::taskFile('TASK-tmpl-bad', 'Tmpl');
    $content = preg_replace('/^- Something is wrong\.$/m', '- <placeholder here>', $content);
    $root = Fixture::board(['todo/TASK-tmpl-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on placeholder');
    expectContains('template placeholder', $out, 'placeholder error');
});

test('validate: missing section fails', function (): void {
    $content = Fixture::taskFile('TASK-sec-bad', 'Sec');
    $content = preg_replace('/^## 1\..*Concept and Goal.*$/m', '## REMOVED', $content);
    $root = Fixture::board(['todo/TASK-sec-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on missing section');
    expectContains('missing section: Concept and Goal', $out, 'missing-section error');
});

test('validate: id mismatch fails', function (): void {
    $content = Fixture::taskFile('TASK-id-ok', 'Title');
    $content = preg_replace('/^# TASK-id-ok:/m', '# TASK-other:', $content);
    $root = Fixture::board(['todo/TASK-id-ok.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on ID mismatch');
    expectContains('does not match file ID', $out, 'id-mismatch error');
});

test('validate: unknown depends_on fails', function (): void {
    $content = Fixture::taskFile('TASK-dep-bad', 'Dep', ['depends_on' => 'TASK-ghost']);
    $root = Fixture::board(['todo/TASK-dep-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(1, $code, 'should fail on unknown dependency');
    expectContains('references unknown ID', $out, 'unknown-dep error');
});

test('validate: single file resolves depends_on from done/', function (): void {
    $done = Fixture::taskFile('TASK-finished', 'Finished', ['status' => 'done']);
    $task = Fixture::taskFile('TASK-needs-finished', 'Needs', ['depends_on' => 'TASK-finished']);
    $root = Fixture::board([
        'todo/done/TASK-finished.todo.md'  => $done,
        'todo/TASK-needs-finished.todo.md' => $task,
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', "$root/todo/TASK-needs-finished.todo.md"]);
    expectEquals(0, $code, "single-file validate should resolve a done/ dependency\n$out$err");
    expectNotContains('unknown ID', $out, 'no false unknown-ID error');
    expectContains('1 file(s): 0 error', $out, 'only the target file is validated');
});

test('validate: single file resolves epic from cancelled/', function (): void {
    $epic = Fixture::taskFile('EPIC-dead-thing', 'Dead', ['type' => 'epic', 'status' => 'cancelled']);
    $task = Fixture::taskFile('TASK-orphan', 'Orphan', ['epic' => 'EPIC-dead-thing']);
    $root = Fixture::board([
        'todo/cancelled/EPIC-dead-thing.todo.md' => $epic,
        'todo/TASK-orphan.todo.md'               => $task,
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', "$root/todo/TASK-orphan.todo.md"]);
    expectEquals(0, $code, "single-file validate should resolve a cancelled/ epic\n$out$err");
    expectNotContains('unknown ID', $out, 'no false unknown-ID error');
});

test('validate: single file still fails on truly unknown depends_on', function (): void {
    $task = Fixture::taskFile('TASK-dep-ghost', 'Ghost', ['depends_on' => 'TASK-ghost']);
    $root = Fixture::board(['todo/TASK-dep-ghost.todo.md' => $task]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', "$root/todo/TASK-dep-ghost.todo.md"]);
    expectEquals(1, $code, 'unknown dependency must still fail on single-file validate');
    expectContains('references unknown ID', $out, 'unknown-dep error');
});

test('validate: cancelled dependency is a warning', function (): void {
    $done = Fixture::taskFile('TASK-gone', 'Gone', ['status' => 'cancelled']);
    $active = Fixture::taskFile('TASK-active', 'Active', ['depends_on' => 'TASK-gone']);
    $root = Fixture::board([
        'todo/cancelled/TASK-gone.todo.md' => $done,
        'todo/TASK-active.todo.md'         => $active,
    ]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, 'cancelled dependency is warning not error');
    expectContains('warning', $out, 'warning marker');
});

test('validate: epic references work', function (): void {
    $epic = Fixture::taskFile('EPIC-big-thing', 'Big', ['type' => 'epic', 'status' => 'todo']);
    // Epic template needs slightly different fields (no depends_on/epic/branch, has pr)
    $task = Fixture::taskFile('TASK-child', 'Child', ['epic' => 'EPIC-big-thing']);
    $root = Fixture::board([
        'todo/EPIC-big-thing.todo.md' => $epic,
        'todo/TASK-child.todo.md'     => $task,
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, "valid epic+task should pass\n$out$err");
});

test('validate: --help works', function (): void {
    [$code, $out] = Fixture::runBin('todo-md', ['validate', '--help']);
    expectEquals(0, $code, '--help should exit 0');
    expectContains('todo-md validate', $out, 'help banner');
});

test('validate: bad author/assignee format is a warning', function (): void {
    $content = Fixture::taskFile('TASK-actor-bad', 'Actor', ['author' => 'codex-cli', 'assignee' => 'Бэкендер (Codex)']);
    $root = Fixture::board(['todo/TASK-actor-bad.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, 'format violations are warnings, not errors');
    expectContains('warning: `author` must use format', $out, 'author format warning');
    expectContains('warning: `assignee` must use format', $out, 'assignee format warning');
});

test('validate: --strict fails on author/assignee violations', function (): void {
    $content = Fixture::taskFile('TASK-strict-actor', 'Strict', ['author' => 'codex-cli']);
    $root = Fixture::board(['todo/TASK-strict-actor.todo.md' => $content]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
    expectEquals(1, $code, '--strict should fail on format violation');
    expectContains('error: `author` must use format', $out, 'strict promotes to error');
});

test('validate: empty assignee is allowed before work and when cancelled', function (): void {
    $root = Fixture::board([
        'todo/TASK-assignee-todo.todo.md' => Fixture::taskFile(
            'TASK-assignee-todo',
            'Todo',
            ['assignee' => '', 'status' => 'todo'],
        ),
        'todo/backlog/TASK-assignee-backlog.todo.md' => Fixture::taskFile(
            'TASK-assignee-backlog',
            'Backlog',
            ['assignee' => '', 'status' => 'backlog'],
        ),
        'todo/cancelled/TASK-assignee-cancelled.todo.md' => Fixture::taskFile(
            'TASK-assignee-cancelled',
            'Cancelled',
            ['assignee' => '', 'status' => 'cancelled'],
        ),
    ]);

    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
    expectEquals(0, $code, "empty assignee should be allowed for todo, backlog, and cancelled\n$out$err");
});

test('validate: empty assignee is rejected after work starts', function (): void {
    foreach (['in_progress', 'paused', 'blocked', 'review', 'done'] as $status) {
        $folder = $status === 'done' ? 'todo/done' : 'todo';
        $id = 'TASK-assignee-' . str_replace('_', '-', $status);
        $root = Fixture::board([
            "$folder/$id.todo.md" => Fixture::taskFile(
                $id,
                ucfirst(str_replace('_', ' ', $status)),
                ['assignee' => '', 'status' => $status],
            ),
        ]);

        [$code, $out] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
        expectEquals(1, $code, "empty assignee should fail for $status");
        expectContains(
            "error: `assignee` must not be empty for status `$status`",
            $out,
            "required assignee error for $status",
        );
    }
});

test('validate: non-empty assignee is validated even when optional', function (): void {
    $content = Fixture::taskFile(
        'TASK-assignee-optional-format',
        'Optional format',
        ['assignee' => 'unassigned', 'status' => 'backlog'],
    );
    $root = Fixture::board(['todo/backlog/TASK-assignee-optional-format.todo.md' => $content]);

    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
    expectEquals(1, $code, 'non-empty optional assignee should still use the canonical format');
    expectContains('error: `assignee` must use format', $out, 'optional assignee format error');
});

test('validate: --config validates roles and agents', function (): void {
    $content = Fixture::taskFile('TASK-cfg-actor', 'Cfg', ['author' => 'Бэкендер (codex-cli)', 'assignee' => 'Бэкендер (codex-cli)']);
    $root = Fixture::board(['todo/TASK-cfg-actor.todo.md' => $content]);
    $configFile = $root . '/.todo-md.php';
    file_put_contents($configFile, '<?php declare(strict_types=1); return ["roles" => ["Аналитик"], "agents" => ["pi"]];');
    [$code, $out] = Fixture::runBin('todo-md', ['validate', $root, '--config=' . $configFile]);
    expectEquals(0, $code, 'unknown role/agent are warnings by default');
    expectContains('`author` agent `codex-cli` is not a known agent', $out, 'config agent check');
    expectContains('`author` role `Бэкендер` is not in the project roles list', $out, 'config role check');
});

<?php

declare(strict_types=1);

/**
 * Fixture tests for state transitions and link rewriting.
 * Exercises the core Must Have: atomic status + folder + link sync.
 */

// ── Basic transitions ────────────────────────────────────────────────────────

test('transition: start sets in_progress + started date', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-start.todo.md' => Fixture::taskFile('TASK-tr-start', 'Start'),
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['start', 'TASK-tr-start', '--assignee=Test (pi)', '--root=' . $root]);
    expectEquals(0, $code, "start should succeed\n$err");

    $file = "$root/todo/TASK-tr-start.todo.md";
    expectEquals('in_progress', frontMatterField($file, 'status'), 'status');
    expect(null !== frontMatterField($file, 'started'), 'started date set');
});

test('transition: done moves to done/ and sets completed', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-done.todo.md' => Fixture::taskFile('TASK-tr-done', 'Done'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['done', 'TASK-tr-done', '--root=' . $root]);
    expectEquals(0, $code, 'done should succeed');

    expectFileMissing("$root/todo/TASK-tr-done.todo.md", 'old path gone');
    $file = "$root/todo/done/TASK-tr-done.todo.md";
    expectFileExists($file, 'moved to done/');
    expectEquals('done', frontMatterField($file, 'status'), 'status done');
    expect(null !== frontMatterField($file, 'completed'), 'completed date set');
});

test('transition: cancel moves to cancelled/ and sets cancelled date', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-cancel.todo.md' => Fixture::taskFile('TASK-tr-cancel', 'Cancel'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['cancel', 'TASK-tr-cancel', '--root=' . $root]);
    expectEquals(0, $code, 'cancel should succeed');

    $file = "$root/todo/cancelled/TASK-tr-cancel.todo.md";
    expectFileExists($file, 'moved to cancelled/');
    expectEquals('cancelled', frontMatterField($file, 'status'), 'status cancelled');
    expect(null !== frontMatterField($file, 'cancelled'), 'cancelled date set');
});

test('transition: backlog moves to backlog/', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-bl.todo.md' => Fixture::taskFile('TASK-tr-bl', 'BL'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['backlog', 'TASK-tr-bl', '--root=' . $root]);
    expectEquals(0, $code, 'backlog should succeed');

    $file = "$root/todo/backlog/TASK-tr-bl.todo.md";
    expectFileExists($file, 'moved to backlog/');
    expectEquals('backlog', frontMatterField($file, 'status'), 'status backlog');
});

test('transition: review sets review status', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-rev.todo.md' => Fixture::taskFile('TASK-tr-rev', 'Rev'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['review', 'TASK-tr-rev', '--root=' . $root]);
    expectEquals(0, $code, 'review should succeed');
    expectEquals('review', frontMatterField("$root/todo/TASK-tr-rev.todo.md", 'status'), 'status review');
});

test('transition: reverse move done → start brings back to todo/', function (): void {
    $root = Fixture::board([
        'todo/TASK-tr-rev2.todo.md' => Fixture::taskFile('TASK-tr-rev2', 'Rev2'),
    ]);
    Fixture::runBin('todo-md', ['done', 'TASK-tr-rev2', '--root=' . $root]);
    Fixture::runBin('todo-md', ['start', 'TASK-tr-rev2', '--assignee=Test (pi)', '--root=' . $root]);

    expectFileExists("$root/todo/TASK-tr-rev2.todo.md", 'back in todo/');
    expectFileMissing("$root/todo/done/TASK-tr-rev2.todo.md", 'gone from done/');
    expectEquals('in_progress', frontMatterField("$root/todo/TASK-tr-rev2.todo.md", 'status'), 'status');
});

test('transition: not found fails', function (): void {
    $root = Fixture::board([]);
    [$code, $stdout, $stderr] = Fixture::runBin('todo-md', ['start', 'TASK-ghost', '--assignee=Test (pi)', '--root=' . $root]);
    expectEquals(1, $code, 'should fail');
    expectContains('not found', $stderr, 'error message');
});

// ── Link rewriting ──────────────────────────────────────────────────────────

/** Create docs/ dir and a reference file under <root>/docs/. */
function makeRef(string $root, string $name = 'ref.md'): string
{
    $dir = "$root/docs";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = "$dir/$name";
    file_put_contents($path, '# Ref');

    return $path;
}

/** Inject a markdown link into the "## 4. План реализации (Implementation Plan)" section. */
function injectLink(string $root, string $file, string $markdown): void
{
    $content = file_get_contents("$root/$file");
    $content = preg_replace(
        '/^## 4\. План реализации \(Implementation Plan\)/m',
        "## 4. План реализации (Implementation Plan)\n\n$markdown",
        $content,
    );
    file_put_contents("$root/$file", $content);
}

test('links: outbound rebased when moving deeper', function (): void {
    $task = Fixture::taskFile('TASK-out-link', 'Out');
    $root = Fixture::board(['todo/TASK-out-link.todo.md' => $task]);
    makeRef($root); // creates <root>/docs/ref.md
    injectLink($root, 'todo/TASK-out-link.todo.md', '- [ref](../docs/ref.md)');

    Fixture::runBin('todo-md', ['done', 'TASK-out-link', '--root=' . $root]);

    $content = file_get_contents("$root/todo/done/TASK-out-link.todo.md");
    expectContains('](../../docs/ref.md)', $content, 'outbound link rebased one level deeper');
});

test('links: outbound rebased when moving shallower', function (): void {
    $task = Fixture::taskFile('TASK-out-link2', 'Out2', ['status' => 'done']);
    $root = Fixture::board(['todo/done/TASK-out-link2.todo.md' => $task]);
    makeRef($root);
    injectLink($root, 'todo/done/TASK-out-link2.todo.md', '- [ref](../../docs/ref.md)');

    Fixture::runBin('todo-md', ['start', 'TASK-out-link2', '--assignee=Test (pi)', '--root=' . $root]);

    $content = file_get_contents("$root/todo/TASK-out-link2.todo.md");
    expectContains('](../docs/ref.md)', $content, 'outbound link rebased one level shallower');
});

test('links: inbound updated in referencing files', function (): void {
    $root = Fixture::board([
        'todo/TASK-inbound.todo.md'  => Fixture::taskFile('TASK-inbound', 'In'),
        'todo/EPIC-has-link.todo.md' => Fixture::taskFile('EPIC-has-link', 'Epic', ['type' => 'epic']),
    ]);
    injectLink($root, 'todo/EPIC-has-link.todo.md', '- [ ] [TASK-inbound](TASK-inbound.todo.md)');

    Fixture::runBin('todo-md', ['done', 'TASK-inbound', '--root=' . $root]);

    $epicAfter = file_get_contents("$root/todo/EPIC-has-link.todo.md");
    expectContains('(done/TASK-inbound.todo.md)', $epicAfter, 'inbound link updated to done/');
    expectNotContains('](TASK-inbound.todo.md)', $epicAfter, 'old inbound link gone');
});

test('links: inbound updated on reverse move', function (): void {
    $root = Fixture::board([
        'todo/TASK-inb-rev.todo.md' => Fixture::taskFile('TASK-inb-rev', 'Rev'),
        'todo/EPIC-inb-rev.todo.md' => Fixture::taskFile('EPIC-inb-rev', 'Epic', ['type' => 'epic']),
    ]);
    injectLink($root, 'todo/EPIC-inb-rev.todo.md', '- [ ] [TASK-inb-rev](TASK-inb-rev.todo.md)');

    Fixture::runBin('todo-md', ['done', 'TASK-inb-rev', '--root=' . $root]);
    Fixture::runBin('todo-md', ['start', 'TASK-inb-rev', '--assignee=Test (pi)', '--root=' . $root]);

    $epicAfter = file_get_contents("$root/todo/EPIC-inb-rev.todo.md");
    expectContains('](TASK-inb-rev.todo.md)', $epicAfter, 'inbound link restored');
});

test('links: http links left untouched', function (): void {
    $task = Fixture::taskFile('TASK-http-link', 'Http');
    $root = Fixture::board(['todo/TASK-http-link.todo.md' => $task]);
    injectLink($root, 'todo/TASK-http-link.todo.md', '- [PR](https://github.com/foo/bar/pull/1)');

    Fixture::runBin('todo-md', ['done', 'TASK-http-link', '--root=' . $root]);

    $content = file_get_contents("$root/todo/done/TASK-http-link.todo.md");
    expectContains('https://github.com/foo/bar/pull/1', $content, 'http link untouched');
});

// ── Validation guard ─────────────────────────────────────────────────────────

test('guard: transition rolls back on validation failure', function (): void {
    $task = Fixture::taskFile('TASK-rb-test', 'RB');
    $root = Fixture::board(['todo/TASK-rb-test.todo.md' => $task]);
    $ref  = makeRef($root, 'exists.md');
    injectLink($root, 'todo/TASK-rb-test.todo.md', '- [ref](../docs/exists.md)');

    // Break the link target AFTER creating it
    unlink($ref);

    [$code, $stdout, $stderr] = Fixture::runBin('todo-md', ['done', 'TASK-rb-test', '--root=' . $root]);
    expectEquals(1, $code, 'should fail on broken link');
    expectContains('rolled back', $stderr, 'rollback message');

    expectFileExists("$root/todo/TASK-rb-test.todo.md", 'original file intact');
    expectFileMissing("$root/todo/done/TASK-rb-test.todo.md", 'no file in done/');
    expectEquals('todo', frontMatterField("$root/todo/TASK-rb-test.todo.md", 'status'), 'status unchanged');
});

// ── Set ──────────────────────────────────────────────────────────────────────

test('set: field updated in place', function (): void {
    $root = Fixture::board([
        'todo/TASK-set-test.todo.md' => Fixture::taskFile('TASK-set-test', 'Set'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['set', 'TASK-set-test', 'priority=P0', '--root=' . $root]);
    expectEquals(0, $code, 'set should succeed');
    expectEquals('P0', frontMatterField("$root/todo/TASK-set-test.todo.md", 'priority'), 'priority changed');
});

test('set: status delegates to transition', function (): void {
    $root = Fixture::board([
        'todo/TASK-set-status.todo.md' => Fixture::taskFile('TASK-set-status', 'SS'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['set', 'TASK-set-status', 'status=done', '--root=' . $root]);
    expectEquals(0, $code, 'set status should succeed');
    expectFileExists("$root/todo/done/TASK-set-status.todo.md", 'moved to done/');
    expectEquals('done', frontMatterField("$root/todo/done/TASK-set-status.todo.md", 'status'), 'status done');
});

test('set: branch field', function (): void {
    $root = Fixture::board([
        'todo/TASK-set-branch.todo.md' => Fixture::taskFile('TASK-set-branch', 'Branch'),
    ]);
    Fixture::runBin('todo-md', ['set', 'TASK-set-branch', 'branch=task/foo-bar', '--root=' . $root]);
    expectEquals('task/foo-bar', frontMatterField("$root/todo/TASK-set-branch.todo.md", 'branch'), 'branch set');
});

<?php

declare(strict_types=1);

/**
 * Fixture tests for the backup mechanism (Should Have).
 */

test('backup: created after transition', function (): void {
    $root = Fixture::board([
        'todo/TASK-bk-test.todo.md' => Fixture::taskFile('TASK-bk-test', 'BK'),
    ]);

    Fixture::runBin('todo-md-done', ['TASK-bk-test', '--root=' . $root]);

    expect(is_dir("$root/.todo-md-backup"), 'backup dir created');

    // The backup should contain a copy of the original file
    $backupDirs = glob("$root/.todo-md-backup/*", GLOB_ONLYDIR) ?: [];
    expect(count($backupDirs) >= 1, 'at least one backup snapshot');

    // Find the original task file in the backup
    $found = false;
    foreach ($backupDirs as $dir) {
        if (file_exists("$dir/todo/TASK-bk-test.todo.md")) {
            $found = true;
            break;
        }
    }
    expect($found, 'original file snapshot in backup');
});

test('backup: skipped with --no-backup', function (): void {
    $root = Fixture::board([
        'todo/TASK-nb-test.todo.md' => Fixture::taskFile('TASK-nb-test', 'NB'),
    ]);

    Fixture::runBin('todo-md-done', ['TASK-nb-test', '--root=' . $root, '--no-backup']);

    expect(!is_dir("$root/.todo-md-backup"), 'no backup dir');
});

test('backup: cleaned up on rollback', function (): void {
    // When validation fails, the backup from the failed attempt should be removed.
    $task = Fixture::taskFile('TASK-bk-rb', 'BKRb');
    $root = Fixture::board(['todo/TASK-bk-rb.todo.md' => $task]);
    $ref  = "$root/docs/ref.md";
    $dir  = dirname($ref);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($ref, '# R');
    $content = file_get_contents("$root/todo/TASK-bk-rb.todo.md");
    $content = preg_replace('/^## 4\. Implementation Plan/m', "## 4. Implementation Plan\n\n- [r](../docs/ref.md)", $content);
    file_put_contents("$root/todo/TASK-bk-rb.todo.md", $content);
    unlink($ref); // break the link

    Fixture::runBin('todo-md-done', ['TASK-bk-rb', '--root=' . $root]); // backup enabled

    // On rollback, the backup is cleaned up
    $backupDirs = glob("$root/.todo-md-backup/*", GLOB_ONLYDIR) ?: [];
    expectEquals(0, count($backupDirs), 'backup cleaned on rollback');
});

test('backup: multiple transitions create multiple snapshots', function (): void {
    $root = Fixture::board([
        'todo/TASK-bk-multi.todo.md' => Fixture::taskFile('TASK-bk-multi', 'Multi'),
    ]);

    Fixture::runBin('todo-md-start', ['TASK-bk-multi', '--root=' . $root]);
    Fixture::runBin('todo-md-review', ['TASK-bk-multi', '--root=' . $root]);
    Fixture::runBin('todo-md-done', ['TASK-bk-multi', '--root=' . $root]);

    $backupDirs = glob("$root/.todo-md-backup/*", GLOB_ONLYDIR) ?: [];
    expect(count($backupDirs) >= 3, 'three backups for three transitions (got ' . count($backupDirs) . ')');
});

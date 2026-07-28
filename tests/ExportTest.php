<?php

declare(strict_types=1);

/**
 * Fixture tests for bin/todo-md-export-jsonl — lock current behavior.
 */

test('export: produces valid JSONL', function (): void {
    $root = Fixture::board([
        'todo/TASK-exp-one.todo.md'     => Fixture::taskFile('TASK-exp-one', 'One'),
        'todo/done/TASK-exp-done.todo.md' => Fixture::taskFile('TASK-exp-done', 'Done', ['status' => 'done']),
        'todo/backlog/TASK-exp-bl.todo.md' => Fixture::taskFile('TASK-exp-bl', 'BL', ['status' => 'backlog']),
    ]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['export-jsonl', $root]);
    expectEquals(0, $code, "export should succeed\n$err");

    $lines = array_filter(explode("\n", trim($out)));
    expectEquals(3, count($lines), 'should produce 3 records');

    $records = array_map('json_decode', $lines);
    foreach ($records as $r) {
        expect($r !== null, 'each line is valid JSON');
    }
});

test('export: record fields', function (): void {
    $root = Fixture::board([
        'todo/TASK-exp-fields.todo.md' => Fixture::taskFile('TASK-exp-fields', 'Fields', ['priority' => 'P1']),
    ]);
    [$code, $out] = Fixture::runBin('todo-md', ['export-jsonl', $root]);
    expectEquals(0, $code, 'export ok');

    $rec = json_decode(trim($out), true);
    expectEquals('TASK-exp-fields', $rec['id'], 'id');
    expectEquals('Fields', $rec['title'], 'title');
    expectEquals('TASK', $rec['kind'], 'kind uppercase');
    expectEquals('todo', $rec['status'], 'status');
    expectEquals('P1', $rec['priority'], 'priority');
    expectEquals('active', $rec['folder'], 'folder=active for todo/');
});

test('export: folder detection', function (): void {
    $root = Fixture::board([
        'todo/done/TASK-fd-done.todo.md'     => Fixture::taskFile('TASK-fd-done', 'D', ['status' => 'done']),
        'todo/cancelled/TASK-fd-can.todo.md' => Fixture::taskFile('TASK-fd-can', 'C', ['status' => 'cancelled']),
        'todo/backlog/TASK-fd-bl.todo.md'    => Fixture::taskFile('TASK-fd-bl', 'B', ['status' => 'backlog']),
    ]);
    [$code, $out] = Fixture::runBin('todo-md', ['export-jsonl', $root]);
    expectEquals(0, $code, 'export ok');

    $folders = [];
    foreach (explode("\n", trim($out)) as $line) {
        $r = json_decode($line, true);
        $folders[$r['folder']] = true;
    }
    expect($folders['done'] ?? false, 'folder done detected');
    expect($folders['cancelled'] ?? false, 'folder cancelled detected');
    expect($folders['backlog'] ?? false, 'folder backlog detected');
});

test('export: --help works', function (): void {
    [$code, $out] = Fixture::runBin('todo-md', ['export-jsonl', '--help']);
    expectEquals(0, $code, '--help exit 0');
    expectContains('todo-md export-jsonl', $out, 'help banner');
});

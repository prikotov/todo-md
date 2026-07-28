<?php

declare(strict_types=1);

/**
 * Fixture tests for todo-md-create — skeleton creation, validation, collisions.
 * Uses --root=<board> so the subprocess operates on the fixture board.
 */

test('create: task skeleton validates', function (): void {
    $root = Fixture::board([]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['create', 
        'TASK-create-test', '--author=Test (pi)', '--root=' . $root, '--type=feat', '--title=Create Test',
    ]);
    expectEquals(0, $code, "create should succeed\n$err");
    expectContains('created', $out, 'success message');

    $file = "$root/todo/TASK-create-test.todo.md";
    expectFileExists($file, 'task file created');

    [$vCode] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $vCode, 'created task validates');

    expectEquals('feat', frontMatterField($file, 'type'), 'type set');
    expectEquals('todo', frontMatterField($file, 'status'), 'status todo');
    expect(null !== frontMatterField($file, 'created'), 'created timestamp set');
});

test('create: epic skeleton validates', function (): void {
    $root = Fixture::board([]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['create', 
        'EPIC-create-epic', '--author=Test (pi)', '--root=' . $root, '--title=Create Epic',
    ]);
    expectEquals(0, $code, "epic create should succeed\n$err");

    [$vCode] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $vCode, 'created epic validates');
});

test('create: collision rejected', function (): void {
    $root = Fixture::board([
        'todo/TASK-dup-id.todo.md' => Fixture::taskFile('TASK-dup-id', 'Dup'),
    ]);
    [$code, $stdout, $stderr] = Fixture::runBin('todo-md', ['create', 
        'TASK-dup-id', '--author=Test (pi)', '--root=' . $root, '--type=feat',
    ]);
    expectEquals(1, $code, 'collision should fail');
    expectContains('already exists', $stderr, 'collision message');
});

test('create: bad ID rejected', function (): void {
    $root = Fixture::board([]);
    foreach (['BadID', 'TASK_Bad', 'task-lower', 'TASK-UPPER'] as $badId) {
        [$code] = Fixture::runBin('todo-md', ['create', 
            $badId, '--author=Test (pi)', '--root=' . $root, '--type=feat',
        ]);
        expectEquals(1, $code, "should reject bad ID: $badId");
    }
});

test('create: missing type rejected for tasks', function (): void {
    $root = Fixture::board([]);
    [$code, $stdout, $stderr] = Fixture::runBin('todo-md', ['create', 
        'TASK-no-type', '--author=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'missing type should fail');
    expectContains('--type is required', $stderr, 'missing type message');
});

test('create: backlog status places in backlog/', function (): void {
    $root = Fixture::board([]);
    Fixture::runBin('todo-md', ['create', 
        'TASK-bl-create', '--author=Test (pi)', '--root=' . $root, '--type=feat', '--status=backlog',
    ]);
    expectFileExists("$root/todo/backlog/TASK-bl-create.todo.md", 'should be in backlog/');

    [$code] = Fixture::runBin('todo-md', ['validate', $root]);
    expectEquals(0, $code, 'backlog task validates');
});

test('create: custom metadata applied', function (): void {
    $root = Fixture::board([]);
    Fixture::runBin('todo-md', ['create', 
        'TASK-meta-test', '--author=Test (pi)', '--root=' . $root, '--type=fix',
        '--value=V3', '--complexity=C4', '--priority=P0',
    ]);
    $file = "$root/todo/TASK-meta-test.todo.md";
    expectEquals('fix', frontMatterField($file, 'type'), 'type');
    expectEquals('V3', frontMatterField($file, 'value'), 'value');
    expectEquals('C4', frontMatterField($file, 'complexity'), 'complexity');
    expectEquals('P0', frontMatterField($file, 'priority'), 'priority');
});

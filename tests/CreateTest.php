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

    [$vCode] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
    expectEquals(0, $vCode, 'created task validates in strict mode with an empty assignee');

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

    [$vCode] = Fixture::runBin('todo-md', ['validate', $root, '--strict']);
    expectEquals(0, $vCode, 'created epic validates in strict mode with an empty assignee');
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

// ── Model enrichment from harness env ───────────────────────────────────────

test('create: author gets model and thinking from pi env', function (): void {
    $root = Fixture::board([]);
    [$code, $out, $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-model', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'glm-5.3-flash', 'PI_REASONING_LEVEL' => 'high']);
    expectEquals(0, $code, "create should succeed\n$err");
    $file = "$root/todo/TASK-env-model.todo.md";
    expectEquals('Тест (pi@glm-5.3-flash~high)', frontMatterField($file, 'author'), 'author enriched');
    expectContains('Тест (pi@glm-5.3-flash~high)', (string) file_get_contents($file), 'change history uses enriched author');
});

test('create: author gets model without level when PI_REASONING_LEVEL is unset', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-model-only', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'glm-5.3-flash']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi@glm-5.3-flash)', frontMatterField("$root/todo/TASK-env-model-only.todo.md", 'author'), 'model without level');
});

test('create: --no-model keeps the author as written', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-raw', '--author=Тест (pi)', '--root=' . $root, '--type=feat', '--no-model',
    ], null, ['PI_MODEL' => 'glm-5.3-flash', 'PI_REASONING_LEVEL' => 'high']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi)', frontMatterField("$root/todo/TASK-env-raw.todo.md", 'author'), 'raw author');
});

test('create: explicit model in author is never overwritten', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-explicit', '--author=Тест (pi@k3.1-pro~low)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'glm-5.3-flash', 'PI_REASONING_LEVEL' => 'high']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi@k3.1-pro~low)', frontMatterField("$root/todo/TASK-env-explicit.todo.md", 'author'), 'explicit model kept');
});

test('create: non-pi agents are not enriched', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-codex', '--author=Тест (codex-cli)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'glm-5.3-flash', 'PI_REASONING_LEVEL' => 'high']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (codex-cli)', frontMatterField("$root/todo/TASK-env-codex.todo.md", 'author'), 'codex untouched');
});

test('create: malformed env model falls back to bare participant', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-env-bad', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'GLM 5.3 (test)', 'PI_REASONING_LEVEL' => 'High!']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi)', frontMatterField("$root/todo/TASK-env-bad.todo.md", 'author'), 'bad env ignored');
});

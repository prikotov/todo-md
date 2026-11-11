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

// ── Model via explicit --model / --thinking keys ────────────────────────────

test('create: --model and --thinking enrich a bare author', function (): void {
    $root = Fixture::board([]);
    [$code, $out, $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-model', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--model=glm-5.3-flash', '--thinking=high',
    ]);
    expectEquals(0, $code, "create should succeed\n$err");
    $file = "$root/todo/TASK-key-model.todo.md";
    expectEquals('Тест (pi@glm-5.3-flash~high)', frontMatterField($file, 'author'), 'author enriched');
    expectContains('Тест (pi@glm-5.3-flash~high)', (string) file_get_contents($file), 'change history uses enriched author');
});

test('create: --model without --thinking appends the model only', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-model-only', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--model=glm-5.3-flash',
    ]);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi@glm-5.3-flash)', frontMatterField("$root/todo/TASK-key-model-only.todo.md", 'author'), 'model without level');
});

test('create: without keys the author is untouched even with harness env set', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-raw', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
    ], null, ['PI_MODEL' => 'glm-5.3-flash', 'PI_REASONING_LEVEL' => 'high']);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (pi)', frontMatterField("$root/todo/TASK-key-raw.todo.md", 'author'), 'env must be ignored');
});

test('create: --model is rejected when the author already carries a model', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-conflict', '--author=Тест (pi@k3.1-pro~low)', '--root=' . $root, '--type=feat',
        '--model=glm-5.3-flash',
    ]);
    expectEquals(1, $code, 'conflicting --model should fail');
    expectContains('already carries a model', $err, 'conflict message');
});

test('create: --thinking without --model fails', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-level-only', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--thinking=high',
    ]);
    expectEquals(1, $code, '--thinking without --model should fail');
    expectContains('--thinking requires --model', $err, 'message');
});

test('create: malformed --model fails', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-bad-model', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--model=GLM 5.3',
    ]);
    expectEquals(1, $code, 'malformed --model should fail');
    expectContains('--model must be a lowercase id', $err, 'message');
});

test('create: --model outside the project models list fails', function (): void {
    $root = Fixture::board([]);
    file_put_contents($root . '/.todo-md.php', '<?php declare(strict_types=1); return ["models" => ["glm-5.3-flash"]];');
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-unknown-model', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--model=k3.1-pro',
    ]);
    expectEquals(1, $code, 'unknown model should fail');
    expectContains('not in the project models list', $err, 'message');
});

test('create: --thinking outside the project thinking_levels fails', function (): void {
    $root = Fixture::board([]);
    file_put_contents($root . '/.todo-md.php', '<?php declare(strict_types=1); return ["thinking_levels" => ["low", "medium"]];');
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-bad-level', '--author=Тест (pi)', '--root=' . $root, '--type=feat',
        '--model=glm-5.3-flash', '--thinking=ultra',
    ]);
    expectEquals(1, $code, 'unknown level should fail');
    expectContains('--thinking must be one of', $err, 'message');
});

test('create: keys work for non-pi agents too', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-codex', '--author=Тест (codex-cli)', '--root=' . $root, '--type=feat',
        '--model=gpt-5', '--thinking=minimal',
    ]);
    expectEquals(0, $code, "create should succeed\n$err");
    expectEquals('Тест (codex-cli@gpt-5~minimal)', frontMatterField("$root/todo/TASK-key-codex.todo.md", 'author'), 'any agent works');
});

test('create: malformed participant with keys fails', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'create', 'TASK-key-bad-participant', '--author=Тест', '--root=' . $root, '--type=feat',
        '--model=glm-5.3-flash',
    ]);
    expectEquals(1, $code, 'bare role without agent should fail with keys');
    expectContains('expected `Роль (агент)`', $err, 'message');
});

<?php

declare(strict_types=1);

/** Participant metadata is exercised through the public CLI, including rollback. */

require_once __DIR__ . '/../src/bootstrap.php';

test('participants: content helpers preserve CRLF and serialize lists', function (): void {
    $content = "---\r\nconsultants:\r\n    - 'Lead (pi)'\r\n    - Test (codex)\r\nreviewer: QA (pi)\r\n---\r\nBody\r\n";
    expectEquals('["Lead (pi)","Test (codex)"]', TodoMd\Board::getFieldFromContent($content, 'consultants'), 'list getter');
    $updated = TodoMd\Board::setFieldInContent($content, 'consultants', '[]');
    expectEquals("---\r\nconsultants: []\r\nreviewer: QA (pi)\r\n---\r\nBody\r\n", $updated, 'CRLF and body preserved');
    expectEquals('QA (pi)', TodoMd\Board::getFieldFromContent($updated, 'reviewer'), 'scalar getter');
    expectEquals(null, TodoMd\Board::getFieldFromContent($updated, 'approver'), 'absent field');
});

foreach (['task', 'epic'] as $kind) {
    test("participants: $kind creation emits optional fields", function () use ($kind): void {
        $root = Fixture::boardWithRealDocs();
        $id = strtoupper($kind) . '-participants';
        $args = ['create', $id, '--author=Test (pi)', '--title=Participants'];
        if ($kind === 'task') {
            $args[] = '--type=feat';
        }
        [$code, $out, $err] = Fixture::runBin('todo-md', $args, $root);
        expectEquals(0, $code, $out . $err);
        [$code, $out, $err] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
        expectEquals(0, $code, $err);
        $record = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
        expectEquals([], $record['consultants'], 'empty consultants');
        expectEquals([], $record['informed'], 'empty informed');
        expectEquals(null, $record['reviewer'], 'unassigned reviewer');
        expectEquals(null, $record['approver'], 'unassigned approver');
    });
}

test('participants: old files remain valid and export empty assignments', function (): void {
    $root = Fixture::board(['todo/TASK-old.todo.md' => Fixture::taskFile('TASK-old', 'Old')]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', '--strict'], $root);
    expectEquals(0, $code, $out . $err);
    [, $out] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
    $record = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    expectEquals([], $record['consultants'], 'old file has no consultants');
    expectEquals([], $record['informed'], 'old file has no informed participants');
    expectEquals(null, $record['reviewer'], 'old file has no reviewer');
    expectEquals(null, $record['approver'], 'old file has no approver');
});

test('participants: flow and block lists round-trip with quoting and comments', function (): void {
    $fields = [
        'consultants' => '["QA, security (pi)", \'Lead\'\'s team (codex)\'] # comment',
        'reviewer' => '"QA \\"security\\" #1 (pi)"',
        'approver' => "'Product (pi)'",
        'informed' => "\n  - Test (pi) # comment\n  # between items\n  - 'Lead (codex)'",
    ];
    $root = Fixture::board(['todo/TASK-list.todo.md' => Fixture::taskFile('TASK-list', 'List', $fields)]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['validate', '--strict'], $root);
    expectEquals(0, $code, $out . $err);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
    expectEquals(0, $code, $err);
    $record = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    expectEquals(['QA, security (pi)', "Lead's team (codex)"], $record['consultants'], 'flow list');
    expectEquals(['Test (pi)', 'Lead (codex)'], $record['informed'], 'block list');
    expectEquals('QA "security" #1 (pi)', $record['reviewer'], 'escaped quotes and hash');
    expectEquals('Product (pi)', $record['approver'], 'scalar');
});

foreach ([
    ['consultants', 'Test (pi)'],
    ['consultants', '"[]"'],
    ['consultants', '[Test (pi)'],
    ['consultants', '["Test (pi)",,]'],
    ['informed', '[[Test (pi)]]'],
    ['informed', '{role: Test}'],
    ['informed', '[null]'],
    ['reviewer', '[Test (pi)]'],
    ['approver', 'null'],
    ['reviewer', "\n  - Test (pi)"],
    ['consultants', "\n  role: Test (pi)"],
    ['consultants', "\n  - Test (pi)\n    - Lead (pi)"],
    ['consultants', "\n  - &lead Test (pi)"],
    ['approver', '*lead'],
    ['reviewer', '|'],
    ['informed', '[42]'],
    ['consultants', "[Test (pi)]\n  - Other (pi)"],
    ['consultants', "[]\nconsultants: []"],
] as [$field, $value]) {
    test("participants: reject invalid $field " . json_encode($value), function () use ($field, $value): void {
        $root = Fixture::board(['todo/TASK-invalid.todo.md' => Fixture::taskFile('TASK-invalid', 'Invalid', [$field => $value])]);
        [$code, $out] = Fixture::runBin('todo-md', ['validate'], $root);
        expectEquals(1, $code, $out);
        expectContains($field, $out, 'field-specific error');
        [$code, , $err] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
        expectEquals(1, $code, 'export must not silently discard malformed participants');
        expectContains($field, $err, 'export diagnostic');
    });
}

test('participants: duplicates are invalid', function (): void {
    $root = Fixture::board(['todo/TASK-duplicate.todo.md' => Fixture::taskFile('TASK-duplicate', 'Duplicate', [
        'informed' => '[Test (pi), Test (pi)]',
    ])]);
    [$code, $out] = Fixture::runBin('todo-md', ['validate'], $root);
    expectEquals(1, $code, $out);
    expectContains('duplicate participants', $out, 'duplicate diagnostic');
});

foreach (['consultants', 'reviewer', 'approver', 'informed'] as $field) {
    test("participants: $field respects actor format and project registries", function () use ($field): void {
        foreach (['Not an actor', 'Unknown (pi)', 'Test (unknown)'] as $actor) {
            $value = in_array($field, ['consultants', 'informed'], true) ? "[$actor]" : $actor;
            $root = Fixture::board([
                'todo/TASK-actor.todo.md' => Fixture::taskFile('TASK-actor', 'Actor', [$field => $value]),
                '.todo-md.php' => "<?php return ['roles' => ['Test'], 'agents' => ['pi']];",
            ]);
            [$code, $out, $err] = Fixture::runBin('todo-md', ['validate'], $root);
            expectEquals(0, $code, $out . $err);
            expectContains($field, $out, 'actor warning');
            [$code, $out] = Fixture::runBin('todo-md', ['validate', '--strict'], $root);
            expectEquals(1, $code, $out);
        }
    });
}

test('participants: set replaces block lists atomically and transitions preserve assignments', function (): void {
    $root = Fixture::board(['todo/TASK-set.todo.md' => Fixture::taskFile('TASK-set', 'Set', [
        'consultants' => "\n  - Test (pi)\n  # list comment\n  - Lead (pi)\n# next field comment",
        'informed' => '[Test (pi)]',
        'reviewer' => 'Lead (pi)',
    ])]);
    [$code, $out, $err] = Fixture::runBin('todo-md', ['set', 'TASK-set', 'consultants=[QA (pi)]', 'approver=Product (pi)'], $root);
    expectEquals(0, $code, $out . $err);
    $before = file_get_contents("$root/todo/TASK-set.todo.md");
    expectNotContains('  - Lead (pi)', $before, 'old list items removed');
    expectContains('# list comment', $before, 'list comment preserved');
    expectContains('# next field comment', $before, 'unrelated comment preserved');
    [$code] = Fixture::runBin('todo-md', ['set', 'TASK-set', 'consultants=invalid', 'reviewer=Other (pi)'], $root);
    expectEquals(1, $code, 'invalid list must roll back all fields');
    expectEquals($before, file_get_contents("$root/todo/TASK-set.todo.md"), 'byte-for-byte rollback');
    [$code, $out, $err] = Fixture::runBin('todo-md', ['backlog', 'TASK-set'], $root);
    expectEquals(0, $code, $out . $err);
    [, $out] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
    $record = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    expectEquals(['QA (pi)'], $record['consultants'], 'replacement survives transition');
    expectEquals(['Test (pi)'], $record['informed'], 'unrelated list preserved');
    expectEquals('Lead (pi)', $record['reviewer'], 'reviewer unchanged');
    expectEquals('Product (pi)', $record['approver'], 'approver set');
    [$code, $out, $err] = Fixture::runBin('todo-md', ['set', 'TASK-set', 'consultants=[]', 'reviewer='], $root);
    expectEquals(0, $code, $out . $err);
    [, $out] = Fixture::runBin('todo-md', ['export-jsonl'], $root);
    $record = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    expectEquals([], $record['consultants'], 'clear list');
    expectEquals(null, $record['reviewer'], 'clear scalar');
});

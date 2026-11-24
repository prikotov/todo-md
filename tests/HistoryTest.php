<?php

declare(strict_types=1);

/**
 * Fixture tests for the `history` command: appending rows to the
 * «История изменений (Change History)» table via Board::appendHistory.
 */

/** Strip the change history section lines from a fixture task file. */
function taskWithoutHistory(string $task): string
{
    $lines = preg_split('/\n/', $task) ?: [];
    $kept  = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, '## История изменений')) {
            break;
        }
        $kept[] = $line;
    }

    return implode("\n", $kept);
}

/** Read the last `|`-row of the change history table. */
function lastHistoryRow(string $file): string
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($file)) ?: [] as $line) {
        if (str_starts_with(trim($line), '|')) {
            $rows[] = trim($line);
        }
    }

    return (string) (array_pop($rows));
}

// ── Happy path ───────────────────────────────────────────────────────────────

test('history: appends row after the last table row', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-app.todo.md' => Fixture::taskFile('TASK-hist-app', 'App'),
    ]);
    $file = "$root/todo/TASK-hist-app.todo.md";
    $before = file_get_contents($file);

    [$code, $out, $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-app', 'Постановка уточнена',
        '--role=Разработчик (pi)', '--model=glm-5.3-flash', '--thinking=high', '--root=' . $root,
    ]);
    expectEquals(0, $code, "history should succeed\n$err");

    $after = (string) file_get_contents($file);
    $out   = trim($out);

    // The command output is the added row.
    expectEquals($out, lastHistoryRow($file), 'output equals the added row');

    // Row format: CLI date with unix timestamp, participant, change text.
    expectMatches('/^\| \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \(\d{9,}\) \| Разработчик \(pi@glm-5\.3-flash~high\) \| Постановка уточнена \|$/u', $out, 'row format');

    // Inserted right after the previous last row, before the trailing newline.
    $beforeTail = substr((string) $before, -80);
    $expected   = '| 2026-07-01 | Test (pi) | Создание задачи |' . "\n" . $out . "\n";
    expectContains($expected, $after, 'row inserted after the previous last table row');

    // Everything except the inserted line is untouched.
    expectEquals(
        str_replace($out . "\n", '', $after),
        $before,
        'nothing else changed',
    );
});

test('history: role with model is kept as-is, thinking requires model', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-m1.todo.md' => Fixture::taskFile('TASK-hist-m1', 'M1'),
    ]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-m1', 'Правка постановки', '--role=Аналитик (pi@glm-5.4)', '--root=' . $root,
    ]);
    expectEquals(0, $code, "existing model must be accepted\n$err");
    expectContains('Аналитик (pi@glm-5.4)', lastHistoryRow("$root/todo/TASK-hist-m1.todo.md"), 'participant intact');

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-m1', 'Ещё правка', '--role=Аналитик (pi)', '--thinking=high', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'thinking without model must fail');
    expectContains('--thinking requires --model', $err, 'withModel error surfaced');
});

// ── Input validation ─────────────────────────────────────────────────────────

test('history: --role is required', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-r1.todo.md' => Fixture::taskFile('TASK-hist-r1', 'R1'),
    ]);
    $file   = "$root/todo/TASK-hist-r1.todo.md";
    $before = file_get_contents($file);

    [$code, , $err] = Fixture::runBin('todo-md', ['history', 'TASK-hist-r1', 'Правка', '--root=' . $root]);
    expectEquals(1, $code, 'missing --role must fail');
    expectContains('--role', $err, 'role error');
    expectEquals($before, file_get_contents($file), 'file untouched');
});

test('history: pipe character in text is an error', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-p1.todo.md' => Fixture::taskFile('TASK-hist-p1', 'P1'),
    ]);
    $file   = "$root/todo/TASK-hist-p1.todo.md";
    $before = file_get_contents($file);

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-p1', 'a | b', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'pipe in text must fail');
    expectContains('|', $err, 'pipe error');
    expectEquals($before, file_get_contents($file), 'file untouched');
});

test('history: line breaks in text are an error', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-nl.todo.md' => Fixture::taskFile('TASK-hist-nl', 'NL'),
    ]);
    $file   = "$root/todo/TASK-hist-nl.todo.md";
    $before = file_get_contents($file);

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-nl', "two\nlines", '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'newline in text must fail');
    expectContains('line break', $err, 'newline error');
    expectEquals($before, file_get_contents($file), 'file untouched');

    [$code] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-nl', "two\rlines", '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'carriage return in text must fail');
    expectEquals($before, file_get_contents($file), 'file untouched');
});

test('history: empty text and empty role are errors', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-e1.todo.md' => Fixture::taskFile('TASK-hist-e1', 'E1'),
    ]);
    [$code] = Fixture::runBin('todo-md', ['history', 'TASK-hist-e1', '   ', '--role=Test (pi)', '--root=' . $root]);
    expectEquals(1, $code, 'empty text must fail');

    [$code] = Fixture::runBin('todo-md', ['history', 'TASK-hist-e1', 'Правка', '--role=  ', '--root=' . $root]);
    expectEquals(1, $code, 'empty role must fail');
});

// ── Board errors ─────────────────────────────────────────────────────────────

test('history: unknown task ID fails', function (): void {
    $root = Fixture::board([]);
    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-ghost', 'Правка', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'unknown ID must fail');
    expectContains('not found', $err, 'not-found error');
});

test('history: missing change history section is an error', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-nosec.todo.md' => taskWithoutHistory(Fixture::taskFile('TASK-hist-nosec', 'No')),
    ]);
    $file   = "$root/todo/TASK-hist-nosec.todo.md";
    $before = file_get_contents($file);

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-nosec', 'Правка', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'missing history section must fail');
    expectContains('Change History', $err, 'section error');
    expectEquals($before, file_get_contents($file), 'file untouched');
});

// ── Write safety ─────────────────────────────────────────────────────────────

test('history: CRLF file keeps the newline style', function (): void {
    $root = Fixture::board([
        'todo/TASK-hist-crlf.todo.md' => str_replace("\n", "\r\n", Fixture::taskFile('TASK-hist-crlf', 'CRLF')),
    ]);
    $file = "$root/todo/TASK-hist-crlf.todo.md";

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-crlf', 'Правка постановки', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(0, $code, "CRLF append should succeed\n$err");

    $after = (string) file_get_contents($file);
    // No bare \n outside of \r\n pairs.
    expectEquals(false, str_contains(str_replace("\r\n", '', $after), "\n"), 'no mixed line endings');
    // Trailing newline state is preserved (task file ends with a newline).
    expectEquals(true, str_ends_with($after, "\r\n"), 'trailing newline preserved');
});

test('history: validation failure rolls the file back', function (): void {
    $task   = str_replace('value: V2', 'value: V9', Fixture::taskFile('TASK-hist-rb', 'RB'));
    $root   = Fixture::board(['todo/TASK-hist-rb.todo.md' => $task]);
    $file   = "$root/todo/TASK-hist-rb.todo.md";
    $before = file_get_contents($file);

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-rb', 'Правка', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(1, $code, 'invalid file must fail after write');
    expectContains('rolled back', $err, 'rollback error');
    expectEquals($before, file_get_contents($file), 'file restored');
});

test('history: appends after the last row, not at section end', function (): void {
    $task = str_replace(
        '| 2026-07-01 | Test (pi) | Создание задачи |',
        "| 2026-07-01 | Test (pi) | Создание задачи |\n| 2026-07-02 | Test (pi) | Первая правка |",
        Fixture::taskFile('TASK-hist-pos', 'Pos'),
    );
    $root = Fixture::board(['todo/TASK-hist-pos.todo.md' => $task]);
    $file = "$root/todo/TASK-hist-pos.todo.md";

    [$code, , $err] = Fixture::runBin('todo-md', [
        'history', 'TASK-hist-pos', 'Вторая правка', '--role=Test (pi)', '--root=' . $root,
    ]);
    expectEquals(0, $code, "append should succeed\n$err");

    $after = (string) file_get_contents($file);
    $pos   = strpos($after, 'Первая правка');
    $new   = strpos($after, 'Вторая правка');
    expect(true, 'both rows present');
    expect($pos !== false && $new !== false && $new > $pos, 'new row after the previous last row');
    expectContains('| Создание задачи |', substr($after, strpos($after, 'Вторая правка') - 200, 200), 'history order intact');
});

// ── validate: author column (Should Have) ────────────────────────────────────

test('validate: history author column is checked by participant rule', function (): void {
    $task = str_replace(
        '| 2026-07-01 | Test (pi) | Создание задачи |',
        '| 2026-07-01 | Тимлид | Создание задачи |',
        Fixture::taskFile('TASK-hist-val', 'Val'),
    );
    $root = Fixture::board(['todo/TASK-hist-val.todo.md' => $task]);

    [$code, $out] = Fixture::runBin('todo-md', ['validate', 'TASK-hist-val'], $root);
    expectEquals(0, $code, 'bad author cell is a warning by default');
    expectContains('change history author', $out, 'warning reported');

    [$code, $out] = Fixture::runBin('todo-md', ['validate', '--strict', 'TASK-hist-val'], $root);
    expectEquals(0, $code, 'author cell warning is not escalated in strict mode');
    expectContains('change history author', $out, 'warning visible in strict output');
});

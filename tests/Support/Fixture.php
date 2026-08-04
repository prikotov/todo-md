<?php

declare(strict_types=1);

/**
 * Test support: exceptions, assertions, and a temp-board fixture helper.
 *
 * Fixture::board() spins up an isolated project root with the canonical
 * todo/ folder layout. Bin scripts run against it via Fixture::runBin().
 */

class TestFailure extends RuntimeException
{
}

class TestSkipped extends RuntimeException
{
}

// ── Assertions ───────────────────────────────────────────────────────────────

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function expectEquals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new TestFailure("$message\n        expected: $exp\n        actual:   $act");
    }
}

function expectContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        $snippet = strlen($haystack) > 300 ? substr($haystack, 0, 300) . '…' : $haystack;
        throw new TestFailure("$message\n        needle \"$needle\" not in:\n        $snippet");
    }
}

function expectNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        $snippet = strlen($haystack) > 300 ? substr($haystack, 0, 300) . '…' : $haystack;
        throw new TestFailure("$message\n        unexpected needle \"$needle\" in:\n        $snippet");
    }
}

function expectFileExists(string $path, string $message = ''): void
{
    if (!file_exists($path)) {
        throw new TestFailure(($message !== '' ? $message : 'expected file to exist') . ": $path");
    }
}

function expectFileMissing(string $path, string $message = ''): void
{
    if (file_exists($path)) {
        throw new TestFailure(($message !== '' ? $message : 'expected file to be absent') . ": $path");
    }
}

/**
 * Read a file's front-matter field value (simple regex, test-only).
 */
function frontMatterField(string $file, string $field): ?string
{
    $content = file_get_contents($file) ?: '';
    if (!preg_match('/^---\s*\n(.*?)\n---\n/s', $content, $m)) {
        return null;
    }
    if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.*)$/m', $m[1], $v)) {
        $val = trim($v[1], " \t\"'");

        return $val === '' ? null : $val;
    }

    return null;
}

// ── Fixture ──────────────────────────────────────────────────────────────────

class Fixture
{
    private static string $pkgRoot;
    /** @var list<string> */
    private static array $dirs = [];

    public static function pkgRoot(): string
    {
        return self::$pkgRoot ??= dirname(__DIR__, 2);
    }

    /**
     * Create an isolated project root with the canonical todo/ layout.
     *
     * @param array<string, string> $files  relative-path => content
     * @param array<string, string> $docs   relative-path-under-docs/todo-md => content
     */
    public static function board(array $files = [], array $docs = []): string
    {
        $root = sys_get_temp_dir() . '/todomd-' . bin2hex(random_bytes(6));
        mkdir($root, 0755, true);

        foreach (['todo', 'todo/backlog', 'todo/done', 'todo/cancelled'] as $dir) {
            mkdir("$root/$dir", 0755, true);
        }

        foreach ($files as $rel => $content) {
            $path = "$root/$rel";
            $dir  = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, $content);
        }

        if ($docs !== []) {
            foreach ($docs as $rel => $content) {
                $path = "$root/docs/todo-md/$rel";
                $dir  = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($path, $content);
            }
        }

        self::$dirs[] = $root;

        return $root;
    }

    /**
     * Create a board seeded with the real package docs/todo-md (templates + refs).
     */
    public static function boardWithRealDocs(array $files = []): string
    {
        $root = self::board($files);
        $src  = self::pkgRoot() . '/docs/todo-md';
        $dest = "$root/docs/todo-md";
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        self::copyDir($src, $dest);

        return $root;
    }

    /**
     * Write a minimal valid task file into the board.
     *
     * @param array<string, string> $overrides  front-matter field => value
     */
    public static function taskFile(string $id, string $title, array $overrides = []): string
    {
        $fm = [
            'type'       => 'feat',
            'created'    => '2026-07-01',
            'due'        => '',
            'started'    => '',
            'completed'  => '',
            'cancelled'  => '',
            'value'      => 'V2',
            'complexity' => 'C2',
            'priority'   => 'P2',
            'cost_plan'  => '',
            'cost_fact'  => '',
            'depends_on' => '',
            'epic'       => '',
            'author'     => 'Test (pi)',
            'assignee'   => 'Test (pi)',
            'branch'     => '',
            'pr'         => 'https://github.com/test/repo/pull/1',
            'status'     => 'todo',
        ];
        foreach ($overrides as $k => $v) {
            $fm[$k] = $v;
        }

        $lines = ['---'];
        foreach ($fm as $k => $v) {
            $lines[] = "$k: $v";
        }
        $lines[] = '---';
        $lines[] = '';
        $lines[] = "# $id: $title";
        $lines[] = '';
        $lines[] = '## 0. Простое описание (Human Brief)';
        $lines[] = '';
        $lines[] = '### Проблема простыми словами (Problem)';
        $lines[] = '- Something is wrong.';
        $lines[] = '';
        $lines[] = '### Варианты или путь решения (Solution Sketch)';
        $lines[] = '- Fix it.';
        $lines[] = '';
        $lines[] = '### Ожидаемый результат (Expected Result)';
        $lines[] = '- It works.';
        $lines[] = '';
        $lines[] = '## 1. Концепция и Цель (Concept and Goal)';
        $lines[] = '';
        $lines[] = '## 2. Контекст и Границы (Context and Scope)';
        $lines[] = '';
        $lines[] = '## 3. Требования, MoSCoW (Requirements)';
        $lines[] = '### 🔴 Обязательно (Must Have)';
        $lines[] = '- [ ] do it';
        $lines[] = '### ⚫ Won\'t Have (Не будем делать)';
        $lines[] = '- nothing else';
        $lines[] = '';
        $lines[] = '## 4. План реализации (Implementation Plan)';
        $lines[] = '1. [ ] plan';
        $lines[] = '';
        $lines[] = '## 5. Критерии приёмки (Definition of Done)';
        $lines[] = '- [ ] done';
        $lines[] = '';
        $lines[] = '## 6. Самопроверка (Verification)';
        $lines[] = '```bash';
        $lines[] = 'php vendor/bin/todo-md validate';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## История изменений (Change History)';
        $lines[] = '| Дата | Автор (роль) | Изменение |';
        $lines[] = '| :--- | :--- | :--- |';
        $lines[] = '| 2026-07-01 | Test (pi) | Создание задачи |';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * Run a package bin script and capture output.
     *
     * @param list<string> $args
     * @return array{int, string, string}  [exitCode, stdout, stderr]
     */
    public static function runBin(string $bin, array $args = []): array
    {
        $script = self::pkgRoot() . "/bin/$bin";
        $cmd    = array_merge(['php', $script], $args);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new TestFailure("failed to start bin/$bin");
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return [$code, $stdout, $stderr];
    }

    public static function cleanup(): void
    {
        foreach (self::$dirs as $dir) {
            self::rmrf($dir);
        }
        self::$dirs = [];
    }

    public static function reset(): void
    {
        self::$dirs = [];
    }

    private static function copyDir(string $src, string $dest): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $target   = "$dest/$relative";
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }
            $dir = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            copy($item->getPathname(), $target);
        }
    }

    private static function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::rmrf("$path/$item");
        }
        @rmdir($path);
    }
}

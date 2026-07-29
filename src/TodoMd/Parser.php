<?php

declare(strict_types=1);

namespace TodoMd;

/**
 * Shared parser, constants, and index for todo-md.
 *
 * Used by validate, export-jsonl, and the state commands. Pure read-side:
 * parse front matter, locate files, resolve links. No mutation.
 */
final class Parser
{
    public const TASK_TYPES = [
        'fix', 'feat', 'build', 'chore', 'ci', 'docs', 'style',
        'refactor', 'perf', 'test', 'revert',
    ];

    public const VALUES       = ['V0', 'V1', 'V2', 'V3', 'V4'];
    public const COMPLEXITIES = ['C0', 'C1', 'C2', 'C3', 'C4', 'C5'];
    public const PRIORITIES   = ['P0', 'P1', 'P2', 'P3'];
    public const STATUSES     = ['todo', 'backlog', 'in_progress', 'paused', 'blocked', 'review', 'done', 'cancelled'];
    public const ACTIVE_STATUSES = ['todo', 'in_progress', 'paused', 'blocked', 'review'];
    /** Canonical AI agents for `author`/`assignee`; see reference/AI_AGENTS.md. */
    public const AI_AGENTS = ['gemini-cli', 'codex-cli', 'codex', 'opencode', 'roocode', 'kilocode', 'pi'];

    /**
     * Canonical folder (relative to todo/) for each status.
     * '' means the todo/ root (active).
     */
    public const FOLDER_FOR_STATUS = [
        'todo'        => '',
        'in_progress' => '',
        'paused'      => '',
        'blocked'     => '',
        'review'      => '',
        'backlog'     => 'backlog',
        'done'        => 'done',
        'cancelled'   => 'cancelled',
    ];

    /** Lifecycle date field set by each transition. */
    public const LIFECYCLE_FIELD = [
        'in_progress' => 'started',
        'done'        => 'completed',
        'cancelled'   => 'cancelled',
    ];

    // ── Content helpers ──────────────────────────────────────────────────────

    public static function removeBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * @return array{frontMatter: string, body: string, error: ?string}
     */
    public static function parseFrontMatter(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false || $lines === [] || trim($lines[0]) !== '---') {
            return ['frontMatter' => '', 'body' => '', 'error' => 'missing YAML front matter opening delimiter ---'];
        }

        $closingLine = null;
        $lineCount   = count($lines);
        for ($i = 1; $i < $lineCount; $i++) {
            if (trim($lines[$i]) === '---') {
                $closingLine = $i;
                break;
            }
        }

        if ($closingLine === null) {
            return ['frontMatter' => '', 'body' => '', 'error' => 'missing YAML front matter closing delimiter ---'];
        }

        return [
            'frontMatter' => implode(PHP_EOL, array_slice($lines, 1, $closingLine - 1)),
            'body'        => implode(PHP_EOL, array_slice($lines, $closingLine + 1)),
            'error'       => null,
        ];
    }

    /**
     * @param list<string> $warnings
     * @return array<string, string>
     */
    public static function parseSimpleYaml(string $yaml, array &$warnings): array
    {
        $data  = [];
        $lines = preg_split('/\r\n|\r|\n/', $yaml) ?: [];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_-]*):(?:\s*(.*))?$/', $line, $matches)) {
                $warnings[] = sprintf('front matter line %d is not a simple key: value pair', $lineNumber + 2);
                continue;
            }

            $key         = $matches[1];
            $value       = self::stripInlineComment($matches[2] ?? '');
            $data[$key]  = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $data;
    }

    public static function stripInlineComment(string $value): string
    {
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length        = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                continue;
            }
            if ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                continue;
            }
            if ($char === '#' && !$inSingleQuote && !$inDoubleQuote) {
                $previous = $i === 0 ? ' ' : $value[$i - 1];
                if (ctype_space($previous)) {
                    return rtrim(substr($value, 0, $i));
                }
            }
        }

        return trim($value);
    }

    // ── File / ID helpers ────────────────────────────────────────────────────

    public static function fileId(string $file): string
    {
        return substr(basename($file), 0, -strlen('.todo.md'));
    }

    /**
     * @return ?string 'task' | 'epic' | null
     */
    public static function detectKind(string $id): ?string
    {
        if (str_starts_with($id, 'TASK-')) {
            return 'task';
        }
        if (str_starts_with($id, 'EPIC-')) {
            return 'epic';
        }

        return null;
    }

    /**
     * Normalise an arbitrary slug for use in an ID: lowercase, ASCII-friendly,
     * hyphen-separated.
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII;', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        $text = trim($text, '-');
        $text = preg_replace('/-{2,}/', '-', $text) ?? $text;

        return $text;
    }

    // ── File discovery ───────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    public static function findTodoFiles(string $target): array
    {
        $path = realpath($target);
        if ($path === false) {
            return [];
        }

        if (is_file($path)) {
            return str_ends_with($path, '.todo.md') ? [$path] : [];
        }

        $searchRoot = $path;
        if (basename($path) !== 'todo' && is_dir($path . '/todo')) {
            $searchRoot = $path . '/todo';
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $file = $item->getPathname();
            if (str_ends_with($file, '.todo.md')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Locate the file for a given task/epic ID anywhere under <root>/todo/.
     *
     * @return ?string absolute path or null if not found
     */
    public static function findFileById(string $root, string $id): ?string
    {
        $todoDir = $root . '/todo';
        if (!is_dir($todoDir)) {
            return null;
        }

        $expected = $id . '.todo.md';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($todoDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === $expected) {
                return $item->getPathname();
            }
        }

        return null;
    }

    // ── Index ────────────────────────────────────────────────────────────────

    /**
     * Build an ID → {kind, status, file} index from a list of files.
     * Files with unparseable front matter are skipped (they'll be flagged
     * in the validation pass).
     *
     * @param list<string> $files
     * @return array<string, array{kind: string, status: string, file: string}>
     */
    public static function buildIdIndex(array $files): array
    {
        $index = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $parsed = self::parseFrontMatter(self::removeBom($content));
            if ($parsed['error'] !== null) {
                continue;
            }

            $id   = self::fileId($file);
            $kind = self::detectKind($id);
            if ($kind === null) {
                continue;
            }

            $warnings    = [];
            $frontMatter = self::parseSimpleYaml($parsed['frontMatter'], $warnings);
            $index[$id]  = [
                'kind'   => $kind,
                'status' => $frontMatter['status'] ?? '',
                'file'   => $file,
            ];
        }

        return $index;
    }

    // ── Paths ────────────────────────────────────────────────────────────────

    public static function makeRelativePath(string $path, string $baseDir): string
    {
        $realPath = realpath($path) ?: $path;
        $realBase = realpath($baseDir) ?: $baseDir;

        if (str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            return substr($realPath, strlen($realBase) + 1);
        }

        return $path;
    }

    /**
     * Folder classification from an absolute file path.
     *
     * @return string 'active' | 'backlog' | 'done' | 'cancelled'
     */
    public static function detectFolder(string $file): string
    {
        if (preg_match('#/(done|backlog|cancelled)/#', str_replace('\\', '/', $file), $matches)) {
            return $matches[1];
        }

        return 'active';
    }

    /**
     * Canonical subfolder under todo/ for a status ('' = active root).
     */
    public static function folderForStatus(string $status): string
    {
        return self::FOLDER_FOR_STATUS[$status] ?? '';
    }

    // ── Markdown links ───────────────────────────────────────────────────────

    public static function normalizeMarkdownLinkTarget(string $rawTarget): ?string
    {
        $target = trim($rawTarget);
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '<') && str_contains($target, '>')) {
            $target = substr($target, 1, strpos($target, '>') - 1);
        } elseif (preg_match('/^(\S+)\s+["\'][^"\']*["\']$/', $target, $matches)) {
            $target = $matches[1];
        }

        return trim($target);
    }

    public static function shouldSkipLinkTarget(string $target): bool
    {
        if (str_starts_with($target, '#')) {
            return true;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1;
    }

    public static function stripLinkFragmentAndQuery(string $target): string
    {
        $withoutFragment = explode('#', $target, 2)[0];

        return explode('?', $withoutFragment, 2)[0];
    }

    /**
     * Extract all local markdown link targets from a body, returning
     * [fullMatch, rawTarget] pairs (excluding images with !, http, and
     * anchor-only links).
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function extractMarkdownLinks(string $body): array
    {
        if (!preg_match_all('/!?\[[^\]\n]*]\(([^)\n]+)\)/', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $links = [];
        foreach ($matches[1] as $i => [$target, $offset]) {
            // Skip image links (preceded by !)
            $fullMatch = $matches[0][$i][0];
            if (str_starts_with($fullMatch, '!')) {
                continue;
            }
            $normalized = self::normalizeMarkdownLinkTarget($target);
            if ($normalized === null || self::shouldSkipLinkTarget($normalized)) {
                continue;
            }
            $links[] = [$fullMatch, $normalized];
        }

        return $links;
    }

    // ── Title ────────────────────────────────────────────────────────────────

    public static function extractTitle(string $body, string $id): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.*)$/', $line, $matches)) {
                $heading   = trim($matches[1]);
                $prefix    = $id . ':';
                if (str_starts_with($heading, $prefix)) {
                    return trim(substr($heading, strlen($prefix)));
                }

                $colonPos = strpos($heading, ':');
                if ($colonPos !== false) {
                    return trim(substr($heading, $colonPos + 1));
                }

                return $heading;
            }
        }

        return '';
    }

    public static function valueOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

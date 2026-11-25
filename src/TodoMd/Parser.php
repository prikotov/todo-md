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
    public const AI_AGENTS = [
        'gemini-cli', 'codex-cli', 'codex', 'opencode', 'roocode', 'kilocode', 'kilo', 'pi',
        'omp', 'zcode', 'bb', 'kimi',
    ];
    /** Recognition dictionary for the `~level` suffix, union across major harnesses
     * (pi: off/low/medium/high/max; codex-cli: minimal/low/medium/high; etc.).
     * Levels are free-form words; projects can replace the dictionary via config['thinking_levels']. */
    public const THINKING_LEVELS = ['off', 'minimal', 'low', 'medium', 'high', 'max'];
    public const PARTICIPANT_LISTS = ['consultants', 'informed'];
    public const PARTICIPANT_FIELDS = ['consultants', 'reviewer', 'approver', 'informed'];

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
     * Participant fields additionally support string lists (flow or indented block).
     * Other fields retain the existing scalar-only contract.
     *
     * @param list<string> $warnings
     * @param list<string> $errors Participant syntax errors, never downgraded to warnings.
     * @return array<string, string|list<string>>
     */
    public static function parseSimpleYaml(string $yaml, array &$warnings, array &$errors = []): array
    {
        $data  = [];
        $lines = preg_split('/\r\n|\r|\n/', $yaml) ?: [];
        $activeParticipant = null;
        $blockList = false;
        $blockIndent = null;

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_-]*):(?:\s*(.*))?$/', $line, $matches)) {
                if ($activeParticipant !== null) {
                    if ($blockList && preg_match('/^( +)-[ ]+(.*)$/', $line, $item)
                        && ($blockIndent === null || $blockIndent === strlen($item[1]))) {
                        $blockIndent = strlen($item[1]);
                        try {
                            $data[$activeParticipant][] = self::parseParticipantString(self::stripInlineComment($item[2]));
                        } catch (\InvalidArgumentException $e) {
                            $errors[] = "`$activeParticipant`: " . $e->getMessage();
                        }
                    } else {
                        $errors[] = "`$activeParticipant` contains an unsupported continuation at line " . ($lineNumber + 2);
                    }
                } else {
                    $warnings[] = sprintf('front matter line %d is not a simple key: value pair', $lineNumber + 2);
                }
                continue;
            }

            $key = $matches[1];
            $value = self::stripInlineComment($matches[2] ?? '');
            $activeParticipant = in_array($key, self::PARTICIPANT_FIELDS, true) ? $key : null;
            $blockList = false;
            $blockIndent = null;
            if ($activeParticipant === null) {
                $data[$key] = trim($value, " \t\n\r\0\x0B\"'");
                continue;
            }
            if (array_key_exists($key, $data)) {
                $errors[] = "duplicate participant field `$key`";
            }
            try {
                if (in_array($key, self::PARTICIPANT_LISTS, true)) {
                    $blockList = $value === '';
                    $data[$key] = $blockList ? [] : self::parseParticipantList($value);
                } else {
                    $data[$key] = $value === '' ? '' : self::parseParticipantString($value);
                }
            } catch (\InvalidArgumentException $e) {
                $errors[] = "`$key`: " . $e->getMessage();
            }
        }

        return $data;
    }

    /** @return list<string> */
    private static function parseParticipantList(string $value): array
    {
        if (!str_starts_with($value, '[') || !str_ends_with($value, ']')) {
            throw new \InvalidArgumentException('expected a YAML string list');
        }
        $inner = trim(substr($value, 1, -1));
        if ($inner === '') {
            return [];
        }
        // Quoted strings may contain commas; nested collections are not supported.
        $pattern = '~\G\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\']|\'\')*\'|[^,\[\]{}"\']+)\s*(,|$)~u';
        $items = [];
        $offset = 0;
        while ($offset < strlen($inner)) {
            if (!preg_match($pattern, $inner, $match, 0, $offset)) {
                throw new \InvalidArgumentException('expected a YAML string list');
            }
            $items[] = self::parseParticipantString(trim($match[1]));
            $offset += strlen($match[0]);
        }

        return $items;
    }

    private static function parseParticipantString(string $value): string
    {
        if (preg_match('/^\'(?:[^\']|\'\')*\'$/u', $value)) {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if (str_starts_with($value, '"')) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                return $decoded;
            }
        } elseif ($value !== '' && !preg_match('/^[\[\]{}&*!|>@`\'"%?-]|:\s|[\r\n]/u', $value)
            && !preg_match('/^(?:null|true|false|~|[-+]?\d+(?:\.\d+)?)$/i', $value)) {
            return $value;
        }

        throw new \InvalidArgumentException('expected a participant string');
    }

    public static function stripInlineComment(string $value): string
    {
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length        = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $inDoubleQuote) {
                $i++;
                continue;
            }
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
     * Recursively collect .todo.md files under a target directory.
     *
     * $exclude holds directory paths relative to the todo/ root (for example
     * `['done', 'cancelled']`); files under them are skipped during recursive
     * discovery. An explicit file target is always returned regardless of
     * $exclude — explicit intent beats exclusion.
     *
     * @param list<string> $exclude
     * @return list<string>
     */
    public static function findTodoFiles(string $target, array $exclude = []): array
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

        $exclude = self::normaliseExclude($exclude);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $file = $item->getPathname();
            if (self::isExcludedPath($file, $searchRoot, $exclude)) {
                continue;
            }
            if (str_ends_with($file, '.todo.md')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Normalise exclusion entries: strings only, backslashes and surrounding
     * slashes stripped, empty entries dropped.
     *
     * @param list<string> $exclude
     * @return list<string>
     */
    private static function normaliseExclude(array $exclude): array
    {
        $normalised = [];
        foreach ($exclude as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim(str_replace('\\', '/', $entry), '/');
            if ($entry !== '') {
                $normalised[] = $entry;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * Whether a file lies under any of the excluded directories. Both the file
     * path and the entries are compared relative to the search root, so this
     * works on any OS and at any nesting depth.
     *
     * @param list<string> $exclude
     */
    private static function isExcludedPath(string $file, string $searchRoot, array $exclude): bool
    {
        if ($exclude === []) {
            return false;
        }

        $relative = str_replace('\\', '/', substr($file, strlen($searchRoot) + 1));
        foreach ($exclude as $dir) {
            if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Locate a file for a given task/epic ID anywhere under <root>/todo/.
     * When several files carry the same ID the first one (sorted) is returned;
     * use findAllFilesById() to detect that ambiguity.
     *
     * @return ?string absolute path or null if not found
     */
    public static function findFileById(string $root, string $id): ?string
    {
        $files = self::findAllFilesById($root, $id);

        return $files[0] ?? null;
    }

    /**
     * Locate ALL files carrying a given task/epic ID anywhere under
     * <root>/todo/. A healthy board returns a single path; more than one means
     * a duplicated ID and callers must refuse to act on it.
     *
     * @return list<string> absolute paths, sorted for deterministic order
     */
    public static function findAllFilesById(string $root, string $id): array
    {
        $todoDir = $root . '/todo';
        if (!is_dir($todoDir)) {
            return [];
        }

        $expected = $id . '.todo.md';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($todoDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === $expected) {
                $files[] = $item->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Find task/epic IDs carried by more than one file. A duplicate ID means
     * two contradictory copies of one task: validators and ID commands must
     * surface it instead of silently picking one file.
     *
     * @param list<string> $files absolute paths
     * @return array<string, list<array{file: string, status: string}>> duplicated ID => its files (sorted), statuses parsed from front matter ('' when unavailable)
     */
    public static function findDuplicateIds(array $files): array
    {
        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        $byId = [];
        foreach ($files as $file) {
            $id = self::fileId($file);
            if (self::detectKind($id) === null) {
                continue;
            }

            $status = '';
            $content = file_get_contents($file);
            if ($content !== false) {
                $parsed = self::parseFrontMatter(self::removeBom($content));
                if ($parsed['error'] === null) {
                    $warnings = [];
                    $frontMatter = self::parseSimpleYaml($parsed['frontMatter'], $warnings);
                    $status = is_string($frontMatter['status'] ?? null) ? $frontMatter['status'] : '';
                }
            }

            $byId[$id][] = ['file' => $file, 'status' => $status];
        }

        return array_filter($byId, fn (array $entries): bool => count($entries) > 1);
    }

    /**
     * One-line listing of a duplicated ID's files with statuses, for error
     * messages: `todo/a.todo.md (status: todo), todo/b.todo.md (status: done)`.
     *
     * @param list<array{file: string, status: string}> $entries
     */
    public static function formatDuplicateEntries(array $entries, string $root = ''): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            $file   = $root !== '' ? self::makeRelativePath($entry['file'], $root) : $entry['file'];
            $status = $entry['status'] !== '' ? $entry['status'] : 'no status';
            $parts[] = "$file (status: $status)";
        }

        return implode(', ', $parts);
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

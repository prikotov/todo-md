<?php

declare(strict_types=1);

namespace TodoMd;

/**
 * Board operations: atomic state transitions, creation, and field editing.
 *
 * Every mutating operation:
 *  1. Reads affected files into memory.
 *  2. Computes changes (status, folder move, outbound + inbound link rewrite).
 *  3. Writes a backup to .todo-md-backup/ (unless --no-backup).
 *  4. Applies changes to disk.
 *  5. Runs the validator; on failure rolls back ALL touched files.
 *
 * Throws BoardException on any failure (after rollback).
 */
final class Board
{
    private const BACKUP_DIR   = '.todo-md-backup';
    private const MAX_BACKUPS  = 20;

    // ══════════════════════════════════════════════════════════════════════════
    //  Path helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve the project root containing a todo/ directory.
     * Walks up from $cwd until todo/ is found.
     */
    public static function resolveRoot(string $cwd): string
    {
        $cwd = realpath($cwd) ?: $cwd;
        if (is_dir($cwd . '/todo')) {
            return $cwd;
        }

        $dir = $cwd;
        while ($dir !== '/' && $dir !== '') {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            if (is_dir($parent . '/todo')) {
                return $parent;
            }
            $dir = $parent;
        }

        throw new BoardException("no todo/ directory found in or above: $cwd");
    }

    /**
     * Normalise a relative path against a base, resolving . and .. without
     * requiring the target to exist on disk.
     */
    public static function resolvePath(string $base, string $relative): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $relative = str_replace('\\', '/', $relative);
            $base     = str_replace('\\', '/', $base);
        }

        if (str_starts_with($relative, '/')) {
            $absolute = $relative;
        } else {
            $absolute = rtrim($base, '/') . '/' . $relative;
        }

        $parts      = explode('/', $absolute);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($normalized !== []) {
                    array_pop($normalized);
                }
                continue;
            }
            $normalized[] = $part;
        }

        $result = implode('/', $normalized);

        return str_starts_with($absolute, '/') ? '/' . $result : $result;
    }

    /**
     * Compute a relative path from $fromDir to $toAbs (both normalised absolute).
     */
    public static function makeRelative(string $fromDir, string $toAbs): string
    {
        $fromParts = explode('/', trim(self::resolvePath($fromDir, '.'), '/'));
        $toParts   = explode('/', trim($toAbs, '/'));

        // strip common prefix
        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $up      = count($fromParts);
        $result  = str_repeat('../', $up) . implode('/', $toParts);

        return $result === '' ? '.' : $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Timestamp
    // ══════════════════════════════════════════════════════════════════════════

    public static function nowTimestamp(): string
    {
        return date('Y-m-d H:i:s') . ' (' . time() . ')';
    }

    public static function todayDate(): string
    {
        return date('Y-m-d');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Front matter manipulation (line-surgical, preserves formatting)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Set a front-matter field value in raw content. If the field exists,
     * replaces its value; otherwise inserts before the closing ---.
     */
    public static function setFieldInContent(string $content, string $field, string $value): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false || $lines === [] || trim($lines[0]) !== '---') {
            return $content;
        }

        $closing = null;
        $count   = count($lines);
        for ($i = 1; $i < $count; $i++) {
            if (trim($lines[$i]) === '---') {
                $closing = $i;
                break;
            }
        }
        if ($closing === null) {
            return $content;
        }

        $pattern = '/^' . preg_quote($field, '/') . ':/';
        $found   = false;
        for ($i = 1; $i < $closing; $i++) {
            if (preg_match($pattern, $lines[$i])) {
                $lines[$i] = "$field: $value";
                $found     = true;
                break;
            }
        }

        if (!$found) {
            array_splice($lines, $closing, 0, "$field: $value");
        }

        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        return implode($eol, $lines);
    }

    /**
     * Read a front-matter field value from raw content (null if absent).
     */
    public static function getFieldFromContent(string $content, string $field): ?string
    {
        $parsed = Parser::parseFrontMatter(Parser::removeBom($content));
        if ($parsed['error'] !== null) {
            return null;
        }
        $warnings    = [];
        $frontMatter = Parser::parseSimpleYaml($parsed['frontMatter'], $warnings);
        $value       = $frontMatter[$field] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Link rewriting
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Rebase all relative markdown links in $content from $fromDir to $toDir.
     * Preserves fragments and query strings.
     */
    public static function rewriteOutboundLinks(string $content, string $fromDir, string $toDir): string
    {
        if ($fromDir === $toDir) {
            return $content;
        }

        // [text](target)  — capture the full match and the target group
        return preg_replace_callback(
            '/(!?)\[([^\]\n]*)]\(([^)\n]+)\)/',
            static function (array $m) use ($fromDir, $toDir): string {
                [$full, $bang, $text, $rawTarget] = $m;
                // skip images
                if ($bang === '!') {
                    return $full;
                }

                $target = Parser::normalizeMarkdownLinkTarget($rawTarget);
                if ($target === null || Parser::shouldSkipLinkTarget($target)) {
                    return $full;
                }

                // split fragment/query
                $hashPos = strpos($target, '#');
                $qPos    = strpos($target, '?');
                $cutPos  = min(
                    $hashPos === false ? PHP_INT_MAX : $hashPos,
                    $qPos === false ? PHP_INT_MAX : $qPos,
                );
                $suffix  = $cutPos === PHP_INT_MAX ? '' : substr($target, $cutPos);
                $path    = $cutPos === PHP_INT_MAX ? $target : substr($target, 0, $cutPos);

                if ($path === '') {
                    return $full;
                }

                // absolute path — leave as-is
                if (str_starts_with($path, '/')) {
                    return $full;
                }

                $absTarget = self::resolvePath($fromDir, $path);
                $newRel    = self::makeRelative($toDir, $absTarget);

                return "[$text]($newRel$suffix)";
            },
            $content,
        ) ?? $content;
    }

    /**
     * Update links in OTHER .todo.md files that point to $oldFile, so they
     * point to $newFile instead. Returns the list of files that were modified.
     *
     * @return list<string>
     */
    public static function computeInboundRewrites(string $root, string $oldFile, string $newFile): array
    {
        if (realpath($oldFile) === realpath($newFile)) {
            return [];
        }

        $todoDir  = $root . '/todo';
        $oldNorm  = self::resolvePath($todoDir, '.'); // base for normalisation

        $allFiles = Parser::findTodoFiles($root);
        $changes  = [];

        foreach ($allFiles as $linker) {
            if (realpath($linker) === realpath($oldFile)) {
                continue;
            }

            $content = file_get_contents($linker);
            if ($content === false) {
                continue;
            }

            $linkerDir = dirname($linker);
            $modified  = preg_replace_callback(
                '/(!?)\[([^\]\n]*)]\(([^)\n]+)\)/',
                static function (array $m) use ($linkerDir, $oldFile, $newFile): string {
                    [$full, $bang, $text, $rawTarget] = $m;
                    if ($bang === '!') {
                        return $full;
                    }
                    $target = Parser::normalizeMarkdownLinkTarget($rawTarget);
                    if ($target === null || Parser::shouldSkipLinkTarget($target)) {
                        return $full;
                    }

                    $path = explode('#', $target, 2)[0];
                    $path = explode('?', $path, 2)[0];
                    if ($path === '' || str_starts_with($path, '/')) {
                        return $full;
                    }

                    $resolved = self::resolvePath($linkerDir, $path);
                    $oldReal  = self::resolvePath(dirname($oldFile), basename($oldFile));
                    $newReal  = self::resolvePath(dirname($newFile), basename($newFile));

                    if ($resolved !== $oldReal) {
                        return $full;
                    }

                    $newRel = self::makeRelative($linkerDir, $newReal);

                    // preserve fragment/query
                    $suffix = '';
                    $hpos   = strpos($target, '#');
                    $qpos   = strpos($target, '?');
                    if ($hpos !== false || $qpos !== false) {
                        $cut  = min($hpos !== false ? $hpos : PHP_INT_MAX, $qpos !== false ? $qpos : PHP_INT_MAX);
                        $suffix = substr($target, $cut);
                    }

                    return "[$text]($newRel$suffix)";
                },
                $content,
            ) ?? $content;

            if ($modified !== $content) {
                $changes[$linker] = $modified;
            }
        }

        return $changes;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Transition (start / review / done / cancel / backlog / todo)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Atomically transition a task/epic to a new status.
     *
     * @param array{backup?: bool} $opts
     * @return string success message
     * @throws BoardException
     */
    public static function transition(string $root, string $id, string $newStatus, array $opts = []): string
    {
        if (!in_array($newStatus, Parser::STATUSES, true)) {
            throw new BoardException("unknown status: $newStatus");
        }

        $file = Parser::findFileById($root, $id);
        if ($file === null) {
            throw new BoardException("task not found: $id");
        }

        $content   = file_get_contents($file);
        if ($content === false) {
            throw new BoardException("cannot read file: $file");
        }

        $oldStatus = self::getFieldFromContent($content, 'status');

        // 'done' requires a PR link
        if ($newStatus === 'done') {
            $pr = self::getFieldFromContent($content, 'pr');
            if ($pr === null || trim($pr) === '') {
                throw new BoardException("field `pr` must be set before done — use: todo-md set $id pr=<url>");
            }
        }

        // ── Compute new content ──────────────────────────────────────────────
        $newContent = $content;
        $newContent = self::setFieldInContent($newContent, 'status', $newStatus);

        $lifecycleField = Parser::LIFECYCLE_FIELD[$newStatus] ?? null;
        if ($lifecycleField !== null) {
            $newContent = self::setFieldInContent($newContent, $lifecycleField, self::nowTimestamp());
        }

        if (($opts['assignee'] ?? null) !== null) {
            $newContent = self::setFieldInContent($newContent, 'assignee', $opts['assignee']);
        }

        // ── Compute new path ─────────────────────────────────────────────────
        $subfolder  = Parser::folderForStatus($newStatus);
        $todoDir    = $root . '/todo';
        $newDir     = $subfolder === '' ? $todoDir : "$todoDir/$subfolder";
        $newPath    = "$newDir/" . basename($file);
        $oldDir     = dirname($file);
        $moved      = realpath($file) !== realpath($newPath);

        // ── Rewrite outbound links ───────────────────────────────────────────
        if ($moved) {
            $newContent = self::rewriteOutboundLinks($newContent, $oldDir, $newDir);
        }

        // ── Compute inbound rewrites ─────────────────────────────────────────
        $inbound = $moved ? self::computeInboundRewrites($root, $file, $newPath) : [];

        // ── Snapshot for rollback ────────────────────────────────────────────
        $snapshot = [
            $file => $content,
        ];
        foreach (array_keys($inbound) as $inboundFile) {
            $snapshot[$inboundFile] = file_get_contents($inboundFile) ?: '';
        }

        // ── Backup ───────────────────────────────────────────────────────────
        $backupDir = null;
        if ($opts['backup'] ?? true) {
            $backupDir = self::backup($root, $snapshot);
        }

        // ── Apply ────────────────────────────────────────────────────────────
        self::applyTransition($file, $newPath, $newContent, $inbound);

        // ── Validate ─────────────────────────────────────────────────────────
        $result = Validator::checkOnBoard($root, $newPath);
        if ($result['errors'] !== []) {
            self::rollback($snapshot, $newPath);
            self::cleanupBackup($backupDir);
            throw new BoardException(
                "validation failed after transition — rolled back:\n  "
                . implode("\n  ", $result['errors']),
            );
        }

        $verb = self::statusVerb($newStatus);
        $rel  = Parser::makeRelativePath($newPath, $root);
        $extra = $result['warnings'] !== []
            ? ' (' . count($result['warnings']) . ' warning(s))'
            : '';

        return "$verb $id → $newStatus ($rel)$extra";
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Create
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param array{
     *   type?: string, title?: string, value?: string, complexity?: string,
     *   priority?: string, author?: string, status?: string, epic?: string,
     *   depends_on?: string, backup?: bool
     * } $opts
     * @return string success message
     * @throws BoardException
     */
    public static function create(string $root, string $id, array $opts = []): string
    {
        $kind = Parser::detectKind($id);
        if ($kind === null) {
            throw new BoardException("ID must start with TASK- or EPIC- and be kebab-case: $id");
        }
        if (!preg_match('/^(TASK|EPIC)-[a-z0-9][a-z0-9-]*$/', $id)) {
            throw new BoardException("ID must be kebab-case (lowercase, hyphens): $id");
        }

        // collision check
        $existing = Parser::findFileById($root, $id);
        if ($existing !== null) {
            throw new BoardException("already exists: $id (" . Parser::makeRelativePath($existing, $root) . ')');
        }

        $status = $opts['status'] ?? 'todo';
        if (!in_array($status, Parser::STATUSES, true)) {
            throw new BoardException("unknown status: $status");
        }

        $title = $opts['title'] ?? self::defaultTitle($id);

        $isEpic = $kind === 'epic';
        $type   = $isEpic ? 'epic' : ($opts['type'] ?? null);
        if (!$isEpic && ($type === null || !in_array($type, Parser::TASK_TYPES, true))) {
            throw new BoardException(
                '--type is required for tasks, one of: ' . implode(', ', Parser::TASK_TYPES),
            );
        }

        $content = $isEpic
            ? self::renderEpicSkeleton($id, $title, $opts)
            : self::renderTaskSkeleton($id, $title, $type, $opts, $status);

        // ── Write to canonical folder ────────────────────────────────────────
        $subfolder = Parser::folderForStatus($status);
        $todoDir   = $root . '/todo';
        $dir       = $subfolder === '' ? $todoDir : "$todoDir/$subfolder";
        $newPath   = "$dir/$id.todo.md";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Backup (new file — no original to snapshot, but record the create)
        $backupDir = null;
        if ($opts['backup'] ?? true) {
            $backupDir = self::backup($root, []);
        }

        file_put_contents($newPath, $content);

        // ── Validate ─────────────────────────────────────────────────────────
        $result = Validator::checkOnBoard($root, $newPath);
        if ($result['errors'] !== []) {
            @unlink($newPath);
            self::cleanupBackup($backupDir);
            throw new BoardException(
                "validation failed after create — file removed:\n  "
                . implode("\n  ", $result['errors']),
            );
        }

        $rel = Parser::makeRelativePath($newPath, $root);

        return "created $id ($rel)";
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Set field
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Point-edit a front-matter field. If the field is `status`, delegates to
     * transition() so folder + links stay in sync.
     *
     * @param array{backup?: bool} $opts
     * @return string success message
     * @throws BoardException
     */
    public static function setField(string $root, string $id, string $field, string $value, array $opts = []): string
    {
        if ($field === 'status') {
            return self::transition($root, $id, $value, $opts);
        }

        $file = Parser::findFileById($root, $id);
        if ($file === null) {
            throw new BoardException("task not found: $id");
        }

        $content    = file_get_contents($file) ?: '';
        $newContent = self::setFieldInContent($content, $field, $value);
        if ($newContent === $content) {
            throw new BoardException("could not set field (no front matter?): $field");
        }

        $snapshot = [$file => $content];

        $backupDir = null;
        if ($opts['backup'] ?? true) {
            $backupDir = self::backup($root, $snapshot);
        }

        file_put_contents($file, $newContent);

        $result = Validator::checkOnBoard($root, $file);
        if ($result['errors'] !== []) {
            self::rollback($snapshot, null);
            self::cleanupBackup($backupDir);
            throw new BoardException(
                "validation failed after set — rolled back:\n  "
                . implode("\n  ", $result['errors']),
            );
        }

        $rel = Parser::makeRelativePath($file, $root);

        return "set $id.$field = $value ($rel)";
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Skeleton rendering
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, string> $opts
     */
    private static function renderTaskSkeleton(string $id, string $title, string $type, array $opts, string $status): string
    {
        $author     = $opts['author'] ?? 'Исполнитель (pi)';
        $now        = self::nowTimestamp();
        $dependsOn  = $opts['depends_on'] ?? '';
        $epic       = $opts['epic'] ?? '';

        $fm = [
            'type'       => $type,
            'created'    => $now,
            'due'        => '',
            'started'    => '',
            'completed'  => '',
            'cancelled'  => '',
            'value'      => $opts['value'] ?? 'V2',
            'complexity' => $opts['complexity'] ?? 'C2',
            'priority'   => $opts['priority'] ?? 'P2',
            'cost_plan'  => '',
            'cost_fact'  => '',
            'depends_on' => $dependsOn,
            'epic'       => $epic,
            'author'     => $author,
            'assignee'   => '',
            'branch'     => '',
            'pr'         => '',
            'status'     => $status,
        ];

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
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '### Варианты или путь решения (Solution Sketch)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '### Ожидаемый результат (Expected Result)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 1. Concept and Goal (Концепция и Цель)';
        $lines[] = '';
        $lines[] = '### Story (User Story)';
        $lines[] = '> (заполнить)';
        $lines[] = '';
        $lines[] = '### Goal (Цель по SMART)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 2. Context and Scope (Контекст и Границы)';
        $lines[] = '';
        $lines[] = '## 3. Requirements (Требования, MoSCoW)';
        $lines[] = '### 🔴 Must Have (Обязательно)';
        $lines[] = '- [ ] (заполнить)';
        $lines[] = '### ⚫ Won\'t Have (Не будем делать)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 4. Implementation Plan (План реализации)';
        $lines[] = '1. [ ] (заполнить)';
        $lines[] = '';
        $lines[] = '## 5. Definition of Done (Критерии приёмки)';
        $lines[] = '- [ ] (заполнить)';
        $lines[] = '';
        $lines[] = '## 6. Verification (Самопроверка)';
        $lines[] = '```bash';
        $lines[] = 'php vendor/bin/todo-md validate';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## 7. Risks and Dependencies (Риски и зависимости)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 8. Sources (Источники)';
        $lines[] = '';
        $lines[] = '## 9. Comments (Комментарии)';
        $lines[] = '';
        $lines[] = '## Change History (История изменений)';
        $lines[] = '| Дата | Автор (роль) | Изменение |';
        $lines[] = '| :--- | :--- | :--- |';
        $lines[] = "| $now | $author | Создание задачи |";
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, string> $opts
     */
    private static function renderEpicSkeleton(string $id, string $title, array $opts): string
    {
        $author = $opts['author'] ?? 'Исполнитель (pi)';
        $status = $opts['status'] ?? 'todo';
        $now    = self::nowTimestamp();

        $fm = [
            'type'       => 'epic',
            'created'    => $now,
            'due'        => '',
            'started'    => '',
            'completed'  => '',
            'cancelled'  => '',
            'value'      => $opts['value'] ?? 'V2',
            'complexity' => $opts['complexity'] ?? 'C2',
            'priority'   => $opts['priority'] ?? 'P2',
            'cost_plan'  => '',
            'cost_fact'  => '',
            'author'     => $author,
            'assignee'   => '',
            'status'     => $status,
            'pr'         => '',
        ];

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
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '### Варианты или путь решения (Solution Sketch)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '### Ожидаемый результат (Expected Result)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 1. Concept and Goal (Концепция и цель)';
        $lines[] = '';
        $lines[] = '## 2. Context and Scope (Контекст и границы)';
        $lines[] = '';
        $lines[] = '## 3. Requirements (Требования, MoSCoW)';
        $lines[] = '### 🔴 Must Have (Блокирующие требования)';
        $lines[] = '- [ ] (заполнить)';
        $lines[] = '### ⚫ Won\'t Have (Не в этот раз)';
        $lines[] = '- (заполнить)';
        $lines[] = '';
        $lines[] = '## 4. Solution Design (Техническое решение)';
        $lines[] = '';
        $lines[] = '## 5. Implementation Plan (План реализации)';
        $lines[] = '';
        $lines[] = '## 6. Definition of Done (Критерии приёмки эпика)';
        $lines[] = '- [ ] (заполнить)';
        $lines[] = '';
        $lines[] = '## Change History (История изменений)';
        $lines[] = '| Дата | Автор (роль) | Изменение |';
        $lines[] = '| :--- | :--- | :--- |';
        $lines[] = "| $now | $author | Создание эпика |";
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private static function defaultTitle(string $id): string
    {
        $rest  = preg_replace('/^(TASK|EPIC)-/', '', $id) ?? $id;
        $parts = explode('-', $rest);
        $parts = array_map('ucfirst', $parts);

        return implode(' ', $parts);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Backup / rollback
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, string> $files  path => original content
     * @return ?string backup directory path
     */
    private static function backup(string $root, array $files): ?string
    {
        $base = $root . '/' . self::BACKUP_DIR;
        if (!is_dir($base)) {
            mkdir($base, 0755, true);
        }

        // prune old backups
        $dirs = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        while (count($dirs) >= self::MAX_BACKUPS) {
            $oldest = array_shift($dirs);
            if ($oldest !== null) {
                self::rmrf($oldest);
            }
        }

        $stamp   = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $backupDir = "$base/$stamp";
        mkdir($backupDir, 0755, true);

        foreach ($files as $path => $content) {
            $rel    = ltrim(str_replace($root, '', $path), '/');
            $target = "$backupDir/$rel";
            $dir    = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($target, $content);
        }

        return $backupDir;
    }

    private static function cleanupBackup(?string $backupDir): void
    {
        if ($backupDir !== null && is_dir($backupDir)) {
            self::rmrf($backupDir);
        }
    }

    /**
     * @param array<string, string> $snapshot  path => original content
     */
    private static function rollback(array $snapshot, ?string $newPath): void
    {
        foreach ($snapshot as $path => $content) {
            file_put_contents($path, $content);
        }
        // remove the newly created file if it differs from any snapshot key
        if ($newPath !== null && !array_key_exists($newPath, $snapshot) && file_exists($newPath)) {
            @unlink($newPath);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Apply (write changes to disk)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, string> $inbound  linker-path => new content
     */
    private static function applyTransition(string $oldFile, string $newPath, string $newContent, array $inbound): void
    {
        // write inbound changes first
        foreach ($inbound as $linker => $content) {
            file_put_contents($linker, $content);
        }

        // write moved file
        $dir = dirname($newPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($newPath, $newContent);

        // remove old file if path changed
        if (realpath($oldFile) !== realpath($newPath) && file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private static function statusVerb(string $status): string
    {
        return match ($status) {
            'todo'        => 'started',
            'in_progress' => 'started',
            'review'      => 'moved',
            'done'        => 'completed',
            'cancelled'   => 'cancelled',
            'backlog'     => 'moved',
            default       => 'transitioned',
        };
    }

    private static function rmrf(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                self::rmrf("$path/$item");
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

class BoardException extends \RuntimeException
{
}

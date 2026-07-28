<?php

declare(strict_types=1);

namespace TodoMd;

/**
 * Validation logic shared by the validator binary and the state commands
 * (post-write check). Pure: returns errors/warnings, never mutates.
 */
final class Validator
{
    /**
     * Validate a single file against an ID index.
     *
     * @param array<string, array{kind: string, status: string, file?: string}> $idIndex
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function validateFile(string $file, array $idIndex): array
    {
        $errors   = [];
        $warnings = [];

        $content = file_get_contents($file);
        if ($content === false) {
            return ['errors' => ['cannot read file'], 'warnings' => []];
        }

        $content     = Parser::removeBom($content);
        $parsed      = Parser::parseFrontMatter($content);
        if ($parsed['error'] !== null) {
            return ['errors' => [$parsed['error']], 'warnings' => []];
        }

        $frontMatter = Parser::parseSimpleYaml($parsed['frontMatter'], $warnings);
        $body        = $parsed['body'];
        $id          = Parser::fileId($file);
        $kind        = Parser::detectKind($id);

        if ($kind === null) {
            $errors[] = 'file name must start with TASK- or EPIC- and end with .todo.md';
        }

        self::validateFrontMatter($frontMatter, $kind, $errors, $warnings);
        self::validateDependencies($frontMatter, $idIndex, $errors, $warnings);
        self::validateTitle($body, $id, $errors);
        self::validateSections($body, $kind, $errors);
        self::validateChangeHistory($body, $errors);
        self::validateFolderStatus($file, $frontMatter['status'] ?? '', $errors);
        self::validateMarkdownLinks($file, $body, $errors);
        self::validateNoTemplatePlaceholders($content, $errors);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate a single file against the whole board (builds index on the fly).
     * Used by state commands for the post-write check.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function checkOnBoard(string $root, string $file): array
    {
        $allFiles = Parser::findTodoFiles($root);
        $idIndex  = Parser::buildIdIndex($allFiles);

        return self::validateFile($file, $idIndex);
    }

    // ── Front matter ─────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $frontMatter
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private static function validateFrontMatter(array $frontMatter, ?string $kind, array &$errors, array &$warnings): void
    {
        $required = ['type', 'created', 'value', 'complexity', 'priority', 'author', 'assignee', 'status'];
        if ($kind === 'task') {
            $required = array_merge($required, ['depends_on', 'epic', 'branch', 'pr']);
        } elseif ($kind === 'epic') {
            $required[] = 'pr';
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $frontMatter)) {
                $errors[] = "missing front matter field `$key`";
            }
        }

        $type = $frontMatter['type'] ?? '';
        if ($kind === 'epic') {
            if ($type !== 'epic') {
                $errors[] = 'EPIC file must have `type: epic`';
            }
        } elseif ($kind === 'task' && !in_array($type, Parser::TASK_TYPES, true)) {
            $errors[] = '`type` must be one of: ' . implode(', ', Parser::TASK_TYPES);
        }

        self::validateDate('created', $frontMatter['created'] ?? '', $errors);
        self::validateEnum('value', $frontMatter['value'] ?? '', Parser::VALUES, $errors);
        self::validateEnum('complexity', $frontMatter['complexity'] ?? '', Parser::COMPLEXITIES, $errors);
        self::validateEnum('priority', $frontMatter['priority'] ?? '', Parser::PRIORITIES, $errors);
        self::validateEnum('status', $frontMatter['status'] ?? '', Parser::STATUSES, $errors);
        self::validateOptionalInteger('cost_plan', $frontMatter['cost_plan'] ?? '', $errors);
        self::validateOptionalInteger('cost_fact', $frontMatter['cost_fact'] ?? '', $errors);
        self::validateOptionalDate('due', $frontMatter['due'] ?? '', $errors);
        self::validateOptionalDate('started', $frontMatter['started'] ?? '', $errors);
        self::validateOptionalDate('completed', $frontMatter['completed'] ?? '', $errors);
        self::validateOptionalDate('cancelled', $frontMatter['cancelled'] ?? '', $errors);

        if (($frontMatter['depends_on'] ?? '') !== '') {
            foreach (explode(',', $frontMatter['depends_on']) as $dependency) {
                $dependency = trim($dependency);
                if (!preg_match('/^(TASK|EPIC)-[A-Za-z0-9][A-Za-z0-9_-]*$/', $dependency)) {
                    $errors[] = "`depends_on` contains invalid plain ID: $dependency";
                }
            }
        }

        if (($frontMatter['epic'] ?? '') !== '' && !preg_match('/^EPIC-[A-Za-z0-9][A-Za-z0-9_-]*$/', $frontMatter['epic'])) {
            $errors[] = '`epic` must be a plain EPIC-* ID or empty';
        }

        if (($frontMatter['pr'] ?? '') !== '' && !preg_match('#^https?://#', $frontMatter['pr'])) {
            $warnings[] = '`pr` is not an http(s) URL';
        }
    }

    /**
     * @param array<string, string> $frontMatter
     * @param array<string, array{kind: string, status: string}> $idIndex
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private static function validateDependencies(array $frontMatter, array $idIndex, array &$errors, array &$warnings): void
    {
        $idPattern = '/^(TASK|EPIC)-[A-Za-z0-9][A-Za-z0-9_-]*$/';

        foreach (explode(',', $frontMatter['depends_on'] ?? '') as $dependency) {
            $dependency = trim($dependency);
            if ($dependency === '' || preg_match($idPattern, $dependency) !== 1) {
                continue;
            }

            if (!array_key_exists($dependency, $idIndex)) {
                $errors[] = "`depends_on` references unknown ID: $dependency";
                continue;
            }

            if ($idIndex[$dependency]['status'] === 'cancelled') {
                $warnings[] = "`depends_on` references a cancelled task: $dependency";
            }
        }

        $epic = $frontMatter['epic'] ?? '';
        if ($epic !== '' && preg_match('/^EPIC-[A-Za-z0-9][A-Za-z0-9_-]*$/', $epic) === 1) {
            if (!array_key_exists($epic, $idIndex)) {
                $errors[] = "`epic` references unknown ID: $epic";
            }
        }
    }

    // ── Dates ────────────────────────────────────────────────────────────────

    /**
     * @param list<string> $errors
     */
    private static function validateDate(string $field, string $value, array &$errors): void
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2})\s*\((\d{1,10})\))?$/', $value, $m)) {
            $errors[] = sprintf('`%s` must use YYYY-MM-DD or "YYYY-MM-DD HH:MM:SS (unix_ts)" format', $field);
            return;
        }

        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors[] = sprintf('`%s` contains an invalid date', $field);
            return;
        }

        if (isset($m[4]) && ((int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 59)) {
            $errors[] = sprintf('`%s` contains an invalid time', $field);
            return;
        }

        if (isset($m[7])) {
            $local  = gmmktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
            $offset = $local - (int) $m[7];
            if ($offset < -12 * 3600 || $offset > 14 * 3600 || $offset % 900 !== 0) {
                $errors[] = sprintf('`%s` local time does not match the Unix timestamp (implied timezone offset is not plausible)', $field);
            }
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function validateOptionalDate(string $field, string $value, array &$errors): void
    {
        if ($value === '') {
            return;
        }

        self::validateDate($field, $value, $errors);
    }

    /**
     * @param list<string> $errors
     */
    private static function validateChangeHistory(string $body, array &$errors): void
    {
        if (!preg_match('/^##\s+.*Change History/im', $body, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $rest = substr($body, $m[0][1] + strlen($m[0][0]));
        if (preg_match('/^##\s+/m', $rest, $next, PREG_OFFSET_CAPTURE)) {
            $rest = substr($rest, 0, $next[0][1]);
        }

        $headerSeen = false;
        foreach (preg_split('/\r\n|\r|\n/', $rest) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '|')) {
                continue;
            }

            if (preg_match('/^\|[\s:|-]+\|\s*$/u', $trimmed)) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($trimmed, '|')));
            if (!$headerSeen) {
                $headerSeen = true;
                continue;
            }

            $date = $cells[0] ?? '';
            if ($date !== '') {
                self::validateDate('change history date', $date, $errors);
            }
        }
    }

    // ── Enums / scalars ──────────────────────────────────────────────────────

    /**
     * @param list<string> $allowed
     * @param list<string> $errors
     */
    private static function validateEnum(string $field, string $value, array $allowed, array &$errors): void
    {
        if (!in_array($value, $allowed, true)) {
            $errors[] = sprintf('`%s` must be one of: %s', $field, implode(', ', $allowed));
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function validateOptionalInteger(string $field, string $value, array &$errors): void
    {
        if ($value === '') {
            return;
        }

        if (!preg_match('/^\d+$/', $value)) {
            $errors[] = sprintf('`%s` must be an integer token count or empty', $field);
        }
    }

    // ── Body structure ───────────────────────────────────────────────────────

    /**
     * @param list<string> $errors
     */
    private static function validateTitle(string $body, string $id, array &$errors): void
    {
        if (!preg_match('/^#\s+([^:\n]+):\s+.+$/m', $body, $matches)) {
            $errors[] = 'missing H1 title in format `# ID: title`';
            return;
        }

        if (trim($matches[1]) !== $id) {
            $errors[] = sprintf('H1 ID `%s` does not match file ID `%s`', trim($matches[1]), $id);
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function validateSections(string $body, ?string $kind, array &$errors): void
    {
        $requiredSections = [
            'Human Brief'       => '/^##\s+0\.\s+Простое описание \(Human Brief\)/m',
            'Problem'           => '/^###\s+Проблема простыми словами \(Problem\)/m',
            'Solution Sketch'   => '/^###\s+Варианты или путь решения \(Solution Sketch\)/m',
            'Expected Result'   => '/^###\s+Ожидаемый результат \(Expected Result\)/m',
            'Concept and Goal'  => '/^##\s+\d+\.\s+Concept and Goal/m',
            'Context and Scope' => '/^##\s+\d+\.\s+Context and Scope/m',
            'Requirements'      => '/^##\s+\d+\.\s+Requirements/m',
            'Implementation Plan' => '/^##\s+\d+\.\s+Implementation Plan/m',
            'Definition of Done'  => '/^##\s+\d+\.\s+Definition of Done/m',
        ];

        if ($kind === 'task') {
            $requiredSections['Verification'] = '/^##\s+\d+\.\s+Verification/m';
        }

        foreach ($requiredSections as $section => $pattern) {
            if (!preg_match($pattern, $body)) {
                $errors[] = "missing section: $section";
            }
        }

        if (!preg_match('/^###\s+.*Must Have/m', $body)) {
            $errors[] = 'missing Must Have requirements subsection';
        }

        if (!preg_match('/^###\s+.*Won[’\']?t Have/m', $body)) {
            $errors[] = "missing Won't Have requirements subsection";
        }
    }

    // ── Folder / status ──────────────────────────────────────────────────────

    /**
     * @param list<string> $errors
     */
    private static function validateFolderStatus(string $file, string $status, array &$errors): void
    {
        $path = str_replace('\\', '/', $file);

        if (str_contains($path, '/todo/done/')) {
            if ($status !== 'done') {
                $errors[] = 'files in todo/done/ must have `status: done`';
            }
            return;
        }

        if (str_contains($path, '/todo/cancelled/')) {
            if ($status !== 'cancelled') {
                $errors[] = 'files in todo/cancelled/ must have `status: cancelled`';
            }
            return;
        }

        if (str_contains($path, '/todo/backlog/')) {
            if ($status !== 'backlog') {
                $errors[] = 'files in todo/backlog/ must have `status: backlog`';
            }
            return;
        }

        if ($status !== '' && !in_array($status, Parser::ACTIVE_STATUSES, true)) {
            $errors[] = 'active todo/ files must have one of statuses: ' . implode(', ', Parser::ACTIVE_STATUSES);
        }
    }

    // ── Links ────────────────────────────────────────────────────────────────

    /**
     * @param list<string> $errors
     */
    private static function validateMarkdownLinks(string $file, string $body, array &$errors): void
    {
        $links = Parser::extractMarkdownLinks($body);

        foreach ($links as [$fullMatch, $target]) {
            $path = self::stripLinkFragmentAndQuery($target);
            if ($path === '') {
                continue;
            }

            $decodedPath = rawurldecode($path);
            $candidate   = str_starts_with($decodedPath, '/')
                ? $decodedPath
                : dirname($file) . DIRECTORY_SEPARATOR . $decodedPath;

            if (!file_exists($candidate)) {
                $errors[] = sprintf('broken local markdown link `%s`', $target);
            }
        }
    }

    private static function stripLinkFragmentAndQuery(string $target): string
    {
        $withoutFragment = explode('#', $target, 2)[0];

        return explode('?', $withoutFragment, 2)[0];
    }

    // ── Placeholders ─────────────────────────────────────────────────────────

    /**
     * @param list<string> $errors
     */
    private static function validateNoTemplatePlaceholders(string $content, array &$errors): void
    {
        $placeholderPattern = '/<('
            . 'роль|имя агента|YYYY-MM-DD|ссылка на PR|тип задачи|статус|категория|'
            . 'название задачи|краткое название задачи|краткое-название|Название эпика|'
            . 'действие|ценность|ситуация\\/триггер|решение|результат'
            . ')[^>\n]*>/u';

        if (preg_match($placeholderPattern, $content, $matches)) {
            $errors[] = 'template placeholder found: ' . $matches[0];
        }

        if (preg_match('/^\s*[A-Za-z_][A-Za-z0-9_-]*:\s*<[^>\n]+>/m', $content, $matches)) {
            $errors[] = 'template placeholder found: ' . trim($matches[0]);
        }

        if (preg_match('/^\s*(?:[-*]|\d+\.)\s*(?:\[[ xX]\]\s*)?<[^>\n]+>\.?\s*$/m', $content, $matches)) {
            $errors[] = 'template placeholder found: ' . trim($matches[0]);
        }

        if (preg_match('/^\s*-\s*\[\s*]\s*\.\.\.\s*$/m', $content)) {
            $errors[] = 'unfinished checklist placeholder found: `- [ ] ...`';
        }

        if (preg_match('/^\s*-\s*\.\.\.\s*$/m', $content)) {
            $errors[] = 'unfinished list placeholder found: `- ...`';
        }
    }
}

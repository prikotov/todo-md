# Сравнение todo-md и GothenburgBitFactory/taskwarrior

Дата исследования: 2026-07-04.

Источник: [`GothenburgBitFactory/taskwarrior`](https://github.com/GothenburgBitFactory/taskwarrior), ветка `develop`, коммит `255a67abd86716f91ef5bdf7cf7ce34fe9e9c598` от 2026-06-25. Последний GitHub Release на момент исследования: `v3.4.2` от 2025-10-21. Сайт проекта: <https://taskwarrior.org>.

## Короткий вывод

- `taskwarrior` — не прямой аналог `todo-md`: это зрелый персональный CLI task manager с собственной базой данных, богатым языком фильтров, отчетами, синхронизацией, хуками и огромной экосистемой.
- `todo-md` — project/repo-local Markdown workflow для AI-разработки: задачи лежат в репозитории как `.todo.md` документы, валидируются и ревьюятся через Git.
- Главная разница:
  - `todo-md`: файлы задач являются частью проекта и code review; сильная постановка задач, эпики, Human Brief, DoR/DoD.
  - `taskwarrior`: CLI-first управление личной/командной задачной базой; сильные фильтры, даты, recurring tasks, urgency, custom reports, sync, hooks.
- Самое полезное, что можно взять как идеи: богатый filter/query language, custom reports, contexts, urgency score, dates (`due`, `wait`, `scheduled`, `until`), recurring tasks, annotations, append/prepend notes, undo, import/export JSON, hooks, completions, diagnostics, UDA-подобные custom fields.
- Не стоит брать: SQLite/TaskChampion как основной storage, изменяемые numeric IDs, глобальный `~/.task` workflow, сложность Taskwarrior целиком.
- Для `todo-md` Taskwarrior — ориентир не по формату хранения, а по **мощности CLI и модели метаданных/отчетов**.

## Метод оценки

Формат оценок: `Q/N/M`.

- `Q` — качество реализации (quality), 1 низкое, 10 очень высокое. Если фичи нет: `—`.
- `N` — нужность для `todo-md` (need), 1 можно обойтись, 10 критично.
- `M` — моя оценка зрелости/согласованности (maturity/fit): насколько фича встроена в модель проекта и поддерживаема, 1 слабая, 10 сильная. Если фичи нет: `—`.

Оценки субъективные, основаны на README, man pages, исходниках, CI, тестах и структуре проекта.

## Что общего

- CLI-first управление задачами.
- Есть статусы задач.
- Есть приоритеты.
- Есть проекты/группировка или аналоги.
- Есть tags/labels.
- Есть зависимости между задачами.
- Есть dates/timestamps в той или иной форме.
- Есть валидация/диагностика данных.
- Есть scriptability: команды можно вызывать из shell/scripts.
- Есть JSON import/export или потенциал machine-readable output.
- Есть MIT-лицензия.

## Главное различие в модели

| Область | todo-md | taskwarrior |
|---|---|---|
| Storage | `.todo.md` файлы в репозитории | `~/.task/taskchampion.sqlite3` через TaskChampion |
| Source of truth | Git-tracked Markdown | Локальная task database + sync |
| Scope | Проект/репозиторий | Пользовательская база задач, configurable data location |
| Review | Markdown diff в PR | CLI reports/history/export, не PR-native |
| Task body | Строгий документ с секциями | Description + annotations/metadata |
| AI workflow | AGENTS docs, task templates | Нет agent-specific workflow в core |
| Kanban | Папки/statuses | Reports/status filters, не kanban board |

## Сравнение фич

| Фича | todo-md Q/N/M | taskwarrior Q/N/M | Комментарий |
|---|---:|---:|---|
| CLI-first task management | 4/9/4 | 10/10/10 | У нас есть init/validate, но нет рабочего CLI. У Taskwarrior CLI — ядро продукта. |
| File-first Markdown storage | 9/10/9 | —/3/— | Наша ключевая фича. Taskwarrior хранит данные в SQLite/TaskChampion, не в Markdown. |
| Git-friendly task review | 9/9/9 | 4/4/4 | У нас задачи ревьюятся как файлы в PR. Taskwarrior можно экспортировать, но это не основной workflow. |
| Local database storage | —/3/— | 9/7/9 | Для личного task manager отлично. Для `todo-md` нежелательно: теряется прозрачный repo state. |
| Персональный глобальный task list | —/3/— | 10/7/10 | Taskwarrior силён как личный GTD. `todo-md` должен оставаться project-local. |
| Project-local board | 8/10/8 | 5/5/5 | Taskwarrior можно настроить через `TASKDATA/TASKRC`, но это не default mental model. |
| YAML front matter | 8/9/8 | —/3/— | У нас schema в Markdown. Taskwarrior metadata хранит в модели TaskChampion. |
| Строгая структура задачи | 8/9/8 | 4/5/5 | У нас Human Brief/Requirements/DoD. У Taskwarrior задача легче и менее формальна. |
| Human Brief | 8/8/8 | —/4/— | У Taskwarrior нет отдельной концепции человеческого описания проблемы. |
| SMART/MoSCoW/INVEST | 8/7/7 | —/3/— | У нас методология постановки. Taskwarrior про управление задачами, не про качество постановки. |
| Epics | 8/8/8 | 5/6/6 | У Taskwarrior есть project/parent/dependencies, но нет полноценного epic-документа. |
| Project field | 5/7/5 | 9/8/9 | Нам стоит добавить/нормализовать `project` или использовать `epic`/`tags`. |
| Tags | —/8/— | 10/9/10 | Taskwarrior tags и virtual tags очень сильны. У нас нет first-class tags. |
| Virtual tags | —/6/— | 9/7/9 | `BLOCKED`, `OVERDUE`, `READY`, etc. — отличная идея для `todo-md list`. |
| Statuses | 8/9/8 | 8/8/8 | У нас workflow богаче для PR (`review`, `cancelled`). У Taskwarrior зрелые `pending/completed/deleted/waiting/recurring`. |
| Status folders | 7/7/7 | —/2/— | Наша kanban-механика. Taskwarrior не двигает файлы. |
| Priority | 8/8/8 | 8/8/8 | У нас P0–P3. У Taskwarrior H/M/L + urgency. |
| Urgency score | —/8/— | 10/9/10 | Очень ценная идея: computed priority на основе due, tags, blocking, age, etc. |
| Value/complexity/priority модель | 8/8/8 | 5/5/5 | У Taskwarrior нет value/complexity, но urgency частично решает сортировку. |
| Cost tracking в токенах | 7/5/6 | —/2/— | Наша AI-budget фича. У Taskwarrior не про AI-cost. |
| Due date | —/7/— | 10/9/10 | У нас нет `due`. Стоит добавить optional. |
| Scheduled date | —/6/— | 9/7/9 | Полезно для задач, которые не должны всплывать раньше срока. |
| Wait date | —/6/— | 9/7/9 | Taskwarrior умеет скрывать waiting tasks до даты. Можно взять как `defer_until`. |
| Until/expiration | —/4/— | 8/5/8 | Для backlog cleanup полезно, но не core. |
| Recurring tasks | —/4/— | 9/6/9 | Для dev tasks редко нужно, но полезно для регулярных maintenance задач. |
| Calendar report | —/4/— | 8/5/8 | Nice-to-have после due dates. |
| Timesheet | —/5/— | 8/6/8 | Для lead/cycle и weekly reports полезно. |
| Start/stop active task | 5/7/5 | 9/8/9 | У нас `in_progress`, но нет `start/stop` команд и timestamps. |
| Done/delete/purge lifecycle | 7/8/7 | 9/8/9 | У нас done/cancelled папки. Taskwarrior имеет done/delete/purge + undo/history. |
| Undo | —/8/— | 9/9/9 | Очень полезно для CLI-мутирующих операций. Нам стоит добавить хотя бы backup/undo для `move/edit`. |
| History/change log | 5/7/5 | 9/8/9 | У нас Git history и optional Change History. Taskwarrior хранит change history и показывает `information`. |
| Annotations | 5/7/5 | 9/8/9 | У нас comments/notes свободно. Taskwarrior имеет first-class `annotate/denotate`. |
| Append/prepend notes | —/7/— | 9/8/9 | Очень полезно для agent progress/handoff. |
| Rich filters | —/10/— | 10/10/10 | Один из главных уроков: attribute modifiers, logical expressions, date filters. |
| Attribute modifiers | —/8/— | 10/9/10 | `before/after/by/none/any/has/startswith/word` — стоит адаптировать. |
| Logical filter expressions | —/7/— | 9/8/9 | AND/OR/XOR/parentheses полезны, но можно внедрять постепенно. |
| Search по body/title | —/8/— | 9/9/9 | Нам нужен `todo-md list --search`. |
| Custom reports | —/8/— | 10/9/10 | У Taskwarrior reports/columns/sort/filter configurable. Нам нужен хотя бы presets. |
| Columns customization | —/5/— | 9/7/9 | Для table output полезно, но не P0. |
| Contexts (named filters) | —/8/— | 9/8/9 | Очень полезно: `context work/home/...` можно адаптировать как saved filters. |
| Config file | —/9/— | 10/10/10 | У нас нет config. Taskwarrior `.taskrc` очень зрелый. |
| Config overrides через CLI/env | 5/7/5 | 10/9/10 | У нас только args. Taskwarrior поддерживает rc overrides, env, includes. |
| Include config/themes | —/4/— | 8/5/8 | Useful later, не core. |
| User-defined attributes (UDA) | —/8/— | 9/9/9 | Отличная идея для расширяемости без постоянного изменения schema. |
| Allowed values для custom fields | —/7/— | 8/8/8 | Нам пригодится для project-specific metadata. |
| Hooks | —/8/— | 9/9/9 | Taskwarrior hooks (`on-add`, `on-modify`, etc.) — хороший extension point. |
| Add-ons/scripts ecosystem | —/5/— | 10/7/10 | У Taskwarrior огромная экосистема. Для нас сначала нужен stable CLI. |
| Shell completions | —/5/— | 9/6/9 | Nice-to-have после CLI. |
| Diagnostics | 5/8/5 | 9/8/9 | У нас validator, но нет environment/config diagnostics. |
| Import/export JSON | —/9/— | 9/10/9 | Нам нужен JSON export/import для migrations, dashboards, integrations. |
| Script helper commands | —/7/— | 9/8/9 | `_ids`, `_get`, `_unique`, etc. — мощно для automation. |
| Sync | —/3/— | 9/7/9 | Taskwarrior sync — сильная фича, но `todo-md` должен использовать Git как sync. |
| Git sync backend | 8/7/8 | 8/6/8 | У нас Git-native через файлы. У Taskwarrior Git sync есть как backend, но не human-readable task files. |
| Cloud sync | —/2/— | 8/5/8 | Не нужно для `todo-md` core. |
| Taskserver/TaskChampion sync | —/2/— | 8/5/8 | Не соответствует repo-local идее. |
| Dependency/blocking reports | 5/9/5 | 9/9/9 | У нас depends_on формат есть, но нет reports/graph. |
| Dependency graph/cache | —/8/— | 8/8/8 | На `develop` есть работа над dependencyGraph. Нам нужен graph/cycles. |
| Parent/child | 6/7/6 | 7/7/7 | У нас это лучше закрывать эпиками. Но lightweight parent может быть полезен. |
| Recurrence import/migration | —/3/— | 8/5/8 | Низкий приоритет для dev tasks. |
| Data migrations | 5/8/5 | 8/8/8 | Taskwarrior имеет import-v2 и upgrade path. Нам нужна schema/migration policy. |
| Test suite | —/10/— | 10/10/10 | У Taskwarrior 243 test files и тысячи Python test defs. У нас тестов нет. |
| CI | 6/9/6 | 10/9/10 | У Taskwarrior coverage, Docker matrix, Rust checks, security audit, release checks. |
| Security/audit | —/5/— | 8/6/8 | Для PHP package можно позже добавить dependency audit, но зависимостей нет. |
| Packaging/distribution | 5/8/5 | 10/9/10 | Taskwarrior доступен в OS package managers. У нас Composer package. |
| Documentation/man pages | 7/8/7 | 10/9/10 | Taskwarrior docs очень зрелые. У нас docs хорошие, но меньше user-facing CLI docs. |
| Community/ecosystem | —/4/— | 10/6/10 | У Taskwarrior проект с 2006 года, тысячи stars, обсуждения, tools ecosystem. |
| MIT license | 9/8/9 | 9/8/9 | Оба MIT. |
| AI-agent workflow | 8/9/8 | —/4/— | У Taskwarrior нет специальных skills/agent protocol в core. |
| Branch/PR fields | 7/7/7 | —/3/— | Наша фича под development workflow. |
| Link validation | 8/8/8 | —/3/— | Taskwarrior не хранит Markdown docs как graph. |
| Template placeholder validation | 8/7/8 | —/3/— | У Taskwarrior задачи создаются CLI, нет markdown template leftovers. |

## Что есть у Taskwarrior, а у нас нет

### Стоит взять в ближайший roadmap

- Rich `list` / query language:
  - `status:`, `type:`, `priority:`, `value:`, `complexity:`, `assignee:`, `epic:`, `tag:`;
  - modifiers: `before`, `after`, `none`, `any`, `contains`;
  - search по title/body.
- Saved contexts:
  - `todo-md context define current 'status:in_progress or status:review'`;
  - `todo-md context use current`;
  - или проще: named filters в config.
- Custom reports:
  - `next`, `ready`, `blocked`, `overdue`, `stale`, `by-epic`, `by-assignee`;
  - configurable columns/sort позже.
- Urgency score:
  - вычислять `urgency` из priority, value, complexity, due, blocked/blocking, age, status;
  - использовать для `todo-md next`.
- Dates:
  - `due`;
  - `scheduled` / `defer_until`;
  - `started`, `completed`, `updated`.
- Annotations/progress notes:
  - `todo-md note <ID> "..." --timestamp`;
  - `todo-md note remove` позже.
- JSON import/export:
  - стабильный контракт для dashboards/migrations/integrations;
  - `todo-md export --json`, `todo-md import`.
- Hooks:
  - `hooks/on-create`, `hooks/on-move`, `hooks/on-done`, `hooks/on-validate`;
  - по умолчанию выключено или безопасно ограничено.
- Diagnostics:
  - `todo-md diagnostics` для package paths, config, docs presence, schema version, broken links.
- Undo/backup для mutating CLI:
  - минимум `.todo-md/undo/` snapshots или git-aware warning.

### Можно взять позже

- Recurring maintenance tasks.
- Calendar/timesheet reports.
- Shell completions.
- Custom fields (UDA) с типами и allowed values.
- Color themes/table customization.
- Script helper commands (`_ids`, `_get`, `_unique`).
- Sync-like adapters только как export/import, не как core storage.

### Лучше не брать

- SQLite/TaskChampion storage как основной формат.
  - Это противоречит `todo-md`: задачи должны быть Markdown-файлами в репозитории.
- Глобальный `~/.task` default.
  - `todo-md` должен быть project-local и воспроизводимым в checkout.
- Изменяемые numeric IDs.
  - У Taskwarrior ID — индекс рабочего набора и может меняться; persistent UUID скрыт.
  - Для `todo-md` публичный ID должен оставаться стабильным (`TASK-*`, `EPIC-*`).
- Полную сложность Taskwarrior query language сразу.
  - Начать с простых filters; выражения с `and/or` позже.
- Cloud/taskserver sync.
  - Git уже закрывает sync/history для нашего сценария.

## Что есть у нас, а у Taskwarrior нет

- Задачи как Markdown-документы в репозитории.
- Строгие шаблоны задач и эпиков.
- Human Brief.
- SMART, User Story, Job Story, MoSCoW, INVEST.
- Эпики как отдельные `EPIC-*.todo.md` файлы.
- Value/complexity/priority модель для планирования AI-разработки.
- Cost tracking в токенах.
- Branch/PR fields.
- Status-folder validation.
- Local Markdown link validation.
- Template placeholder validation.
- Init-скрипт, который копирует документацию и AGENTS rules в consumer project.
- AI-agent rules как first-class documentation.
- Git/PR-native review workflow.
- PHP/Composer integration для PHP-проектов.

## Чего нет у обоих

- Web UI/SaaS как core.
- Встроенная AI-agent claim/worktree/PR автоматизация уровня `kanban-md`.
- Встроенный LLM-review качества постановки задачи.
- Автоматическое создание GitHub PR из задачи.
- Автоматическое заполнение PR URL обратно в задачу.
- Автоматическая синхронизация labels/assignee с GitHub.
- Cost dashboard по токенам/деньгам.
- Markdown dependency graph visualization.
- TUI kanban board в core.
- Проверка свежести документации относительно diff кода.

## Что у нас может быть лишним или чрезмерным на фоне Taskwarrior

- Слишком тяжёлый template для маленьких задач.
  - Taskwarrior показывает ценность лёгкого `add/modify/done` workflow.
  - Решение: compact template для C0/C1, полный template для C2+ и эпиков.
- Обязательные `branch`/`pr` поля в каждой задаче.
  - В Taskwarrior нет VCS-specific metadata; оно не нужно всем задачам.
  - Решение: оставить поля, но валидировать lifecycle-aware: пусто допустимо до старта/PR.
- Status folders.
  - Taskwarrior показывает, что статус в metadata достаточно для многих reports.
  - Но для нас папки — понятный kanban. Решение: управлять ими только через CLI и чинить ссылки.
- Жёстко зашитые справочники.
  - Taskwarrior очень configurable.
  - Решение: добавить config overrides для statuses/priorities/types/agents/custom fields.

## Риски и наблюдения по Taskwarrior

- Taskwarrior — очень зрелый, но его мощность достигается ценой большой сложности.
  - Не нужно пытаться «догнать Taskwarrior» целиком.
- Storage TaskChampion/SQLite делает Taskwarrior быстрым и syncable, но хуже подходит для PR-review задач.
  - Для `todo-md` это anti-pattern.
- Изменяемые numeric IDs удобны для CLI, но опасны для долгоживущих ссылок.
  - Наши slug IDs лучше для документации, PR и branch names.
- Богатый query language может быстро усложнить parser.
  - Нужен incremental план: сначала простые `field:value` filters, затем modifiers, затем boolean expressions.
- У Taskwarrior огромная тестовая база.
  - Любое расширение `todo-md` CLI без tests будет рискованным.

## Приоритетный план развития todo-md после сравнения

### P0 — база для безопасного роста

1. Добавить тестовую инфраструктуру:
   - fixture projects;
   - tests для `init` и `validate`;
   - regression tests для links/placeholders/status-folder.
2. Проверить Composer package archive:
   - `docs/todo-md/` и `todo/AGENTS.md` должны попадать в dist.
3. Добавить config-файл:
   - `.todo-mdrc` или `todo-md.yml`;
   - paths/statuses/priorities/types/defaults/custom fields.
4. Усилить validator:
   - существование `depends_on`/`epic`;
   - self-dependency;
   - duplicate IDs;
   - dependency cycles later.

### P1 — Taskwarrior-like CLI minimum

1. `todo-md create`:
   - title/type/priority/value/complexity/assignee/tags/body;
   - создание из compact/full template.
2. `todo-md list`:
   - filters: status/type/priority/value/complexity/assignee/epic/tag/search;
   - outputs: table/json/compact.
3. `todo-md show <ID>`.
4. `todo-md move/start/stop/done/cancel`:
   - обновляет status;
   - переносит файл при необходимости;
   - чинит ссылки.
5. `todo-md note <ID>`:
   - append timestamped note;
   - удобно для progress/handoff.

### P2 — reports и умная приоритизация

1. Reports:
   - `next`;
   - `ready`;
   - `blocked`;
   - `overdue`;
   - `stale`;
   - `by-epic`.
2. Dates:
   - `due`;
   - `scheduled/defer_until`;
   - `started/completed/updated`.
3. Urgency score:
   - computed field;
   - configurable coefficients later.
4. Contexts/saved filters:
   - `current`, `review`, `blocked`, `release`.
5. JSON export/import.

### P3 — extensibility и ecosystem

1. Hooks:
   - on-create/on-move/on-done/on-validate.
2. Custom fields (UDA-like):
   - типы;
   - allowed values;
   - validation.
3. Shell completions.
4. Diagnostics.
5. Undo/repair commands.
6. Calendar/timesheet/metrics.

## Итоговая рекомендация

Taskwarrior не должен менять фундамент `todo-md`: наши задачи должны оставаться Markdown-файлами в Git.

Но Taskwarrior показывает, каким должен стать CLI-слой:

- мощные filters;
- saved contexts;
- reports;
- dates;
- urgency;
- annotations;
- undo;
- hooks;
- JSON import/export;
- diagnostics;
- большая тестовая база.

Коротко: у `todo-md` сильнее **task-writing standard**, у Taskwarrior сильнее **task-query/reporting engine**. Лучший путь — сохранить наш Markdown/AI-development формат и постепенно добавить Taskwarrior-like CLI поверх него.

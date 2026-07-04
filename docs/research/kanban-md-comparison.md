# Сравнение todo-md и antopolskiy/kanban-md

Дата исследования: 2026-07-04.

Источник: [`antopolskiy/kanban-md`](https://github.com/antopolskiy/kanban-md), ветка `main`, коммит `c6e47c78369440765885a51e01e7a8934c11e601` от 2026-06-18. Последний GitHub Release на момент исследования: `v0.34.1` от 2026-06-19.

## Короткий вывод

- `kanban-md` ближе к `todo-md`, чем `mdtask`: у него тоже **отдельный Markdown-файл на задачу** и **YAML front matter**.
- Главная разница:
  - `todo-md`: пакет правил, шаблонов и валидатора для строгой постановки задач; PHP/Composer; задача — полноценный документ с DoR/DoD.
  - `kanban-md`: готовый операционный task-management tool; Go single binary; CLI/TUI/metrics/claims/log/skills; задача легче, но workflow автоматизирован сильнее.
- Самое ценное, что стоит взять как идеи: полноценный CLI (`create/list/show/edit/move`), TUI/board summary, compact/JSON output, `pick --claim`, claims с expiry, WIP limits, activity log, metrics, `context`, installable/versioned skills, config migrations, self-healing ID/filename consistency.
- `kanban-md` имеет MIT-лицензию, поэтому юридический риск переиспользования идей ниже, чем у `mdtask`. Но прямое копирование кода всё равно не нужно: стек и архитектура другие.
- У `todo-md` сильнее качество постановки задачи: Human Brief, SMART, MoSCoW, INVEST, value/complexity/priority, cost tracking, эпики и строгая валидация структуры.
- У `kanban-md` сильнее эксплуатация: многопроцессный/многоагентный workflow, готовые команды, TUI, метрики и backward compatibility.

## Метод оценки

Формат оценок: `Q/N/M`.

- `Q` — качество реализации (quality), 1 низкое, 10 очень высокое. Если фичи нет: `—`.
- `N` — нужность для `todo-md` (need), 1 можно обойтись, 10 критично.
- `M` — моя оценка зрелости/согласованности (maturity/fit): насколько фича встроена в модель проекта и поддерживаема, 1 слабая, 10 сильная. Если фичи нет: `—`.

Оценки субъективные, но основаны на README, agent skills, исходниках, CI, тестах и структуре проекта.

## Что общего

- File-based kanban/task board.
- Задачи — отдельные Markdown-файлы.
- YAML front matter для метаданных.
- Нет базы данных, сервера и SaaS.
- Git-friendly формат: обычные файлы, читаемый diff.
- Есть init/bootstrap команды.
- Есть статусы, приоритеты, assignee/исполнитель.
- Есть зависимости между задачами.
- Есть правила/skills для AI-агентов.
- Есть CLI для проверки или управления задачами.
- Есть идея работы без vendor lock-in: файлы можно читать и править руками.

## Сравнение фич

| Фича | todo-md Q/N/M | kanban-md Q/N/M | Комментарий |
|---|---:|---:|---|
| File-first Markdown без DB/UI/server | 8/10/9 | 9/10/9 | Общая основа. `kanban-md` уже полноценный продукт, `todo-md` пока ближе к rules+validator. |
| Отдельный файл на задачу | 8/9/8 | 9/9/9 | Очень близкая модель. У нас ID в имени/H1, у них numeric ID + slug filename. |
| YAML front matter | 8/9/8 | 9/9/9 | У них полноценный YAML parser (`go.yaml`), у нас simple key-value parser без внешних зависимостей. |
| Markdown body задачи | 8/9/8 | 7/8/8 | У нас тело строго структурировано; у них свободное, легче для CLI и TUI. |
| Init/bootstrap | 8/9/8 | 8/9/8 | Оба умеют инициализировать структуру. У нас копируются docs/AGENTS, у них создаётся board config. |
| Конфигурация проекта | —/9/— | 9/10/9 | У нас нет `.todo-mdrc/config.yml`; у них `kanban/config.yml` с версиями, statuses, priorities, WIP, defaults, TUI. |
| Custom statuses | 6/7/6 | 9/8/9 | У нас статусы зашиты в валидаторе. У них workflow настраивается в config. |
| Custom priorities | 6/7/6 | 8/7/8 | У нас P0–P3 фиксированы. У них список priorities настраивается. |
| Kanban-представление | 5/9/5 | 9/10/9 | У нас папки и validator, но нет `board`/TUI. У них board summary и полноценный TUI. |
| Status folders | 7/7/7 | —/4/— | Наша фича. У них статус — только front matter, файл не двигается. Это уменьшает риск битых ссылок. |
| Single tasks directory | —/7/— | 8/8/8 | У них все задачи в `kanban/tasks/`. Нам можно взять как опциональный v2-режим или не расширять статусные папки. |
| CLI `create/add` | —/9/— | 9/10/9 | Один из главных gap у нас. У них создание с title/body/status/priority/tags/due/estimate/parent/deps/claim. |
| CLI `list` | —/9/— | 9/10/9 | У них фильтры, поиск, сортировка, grouping, limit, blocked/unblocked/claimed. Нам нужно сделать аналог. |
| CLI `show` | —/8/— | 8/9/8 | У нас только ручной просмотр файлов. Нужен `todo-md-view/show`. |
| CLI `edit` | —/8/— | 9/9/9 | У них batch edit множества полей. Нам нужны lifecycle/meta-команды. |
| CLI `move` lifecycle | —/8/— | 8/9/8 | У них `move --next/--prev`, batch move, timestamps, WIP/claim checks. Нам нужно status+folder sync. |
| CLI `delete/archive` | —/6/— | 8/7/8 | У них soft archive через статус `archived`, delete с confirm. У нас done/cancelled папки. |
| Batch operations | —/7/— | 8/8/8 | Полезно для backlog triage. У нас отсутствует. |
| JSON output | —/9/— | 9/10/9 | Очень важно для агентов и scripts. У нас нет. |
| Compact output для агентов | —/8/— | 9/9/9 | У них явно оптимизировано под токены. Нам стоит добавить `--compact`. |
| Table output | —/6/— | 8/7/8 | Полезно для людей, но ниже приоритет, чем JSON/compact. |
| `--dir` discovery | 5/7/5 | 8/8/8 | У нас validate принимает target; у них board discovery как `.git`, плюс global flag. |
| TUI | —/6/— | 9/8/9 | Сильно повышает human supervision. Не P0 для нас, но хороший P3. |
| Board summary | —/9/— | 9/9/9 | Нам нужен хотя бы CLI summary раньше TUI. |
| Metrics: throughput/lead/cycle/aging | —/7/— | 8/8/8 | У нас нет аналитики. Можно сделать после timestamps/schema. |
| Activity log | —/7/— | 8/8/8 | У них mutation log. У нас история только Git и task body. Полезно для multi-agent. |
| `context` для AGENTS/CLAUDE | —/8/— | 8/8/8 | У них команда генерирует board context block. Нам очень подходит для AI-агентов. |
| Agent skills | —/9/— | 9/9/9 | У них две installable skills: CLI reference и kanban-based-development. Нам стоит сделать свои. |
| Версионированная установка skills | —/8/— | 9/8/9 | `skill install/check/update/show`, поддержка Claude/Codex/Cursor/OpenClaw. У нас только копируем AGENTS.md. |
| Multi-agent claims | —/9/— | 8/10/8 | Очень ценная фича для параллельных агентов. У нас нет механизма claim/lock. |
| Claim timeout | —/8/— | 8/8/8 | Защищает от навсегда занятых задач. Нам стоит взять. |
| Require-claim statuses | —/8/— | 8/8/8 | Для `in_progress`/`review` полезно. У нас assignee есть, но не enforced. |
| `pick --claim` | —/9/— | 8/10/8 | Отличная агентская команда. В коде стоит отдельно аудировать реальную атомарность pick under concurrency. |
| `agent-name` | —/6/— | 8/7/8 | Небольшая, но удобная фича для claim identity. |
| Worktree-based workflow | 5/7/5 | 8/8/8 | У нас Git workflow docs внешние. У них skill явно ведёт agent через claim → worktree → merge → done. |
| Handoff command | —/8/— | 8/8/8 | Хорошая команда для review/blocked/user input. У нас blocked — только статус и текст. |
| Blocked state/reason | 6/8/6 | 8/8/8 | У нас `status: blocked` + текст. У них отдельные `blocked` и `block_reason`, фильтры. |
| Dependencies validation | 5/9/5 | 8/9/8 | У нас формат `depends_on` валидируется, существование нет. У них missing deps — error при create/edit. |
| Parent/subtasks | 6/7/6 | 7/8/7 | У нас эпики. У них numeric parent. Нам лучше сохранить эпики, но добавить validation связи. |
| Epics | 8/8/8 | 5/6/6 | У нас сильнее: отдельный `EPIC-*.todo.md`, шаблон, план. У них parent/task hierarchy проще. |
| WIP limits | —/7/— | 8/8/8 | Полезно для команд/агентов, особенно `in-progress`/`review`. |
| Classes of service | —/5/— | 8/6/8 | Kanban-практика: expedite/fixed-date/standard/intangible. Для нас не core, можно позже. |
| Due date / estimate | —/6/— | 8/7/8 | У нас есть complexity/cost, но нет due/estimate. Можно добавить optional. |
| Timestamps lifecycle | 5/8/5 | 8/8/8 | У нас только `created`; у них `created/updated/started/completed`. Нам нужны `updated/started/completed`. |
| Self-healing IDs/filenames/next_id | —/7/— | 8/8/8 | У них auto-repair duplicate IDs, filename mismatch, next_id drift. Нам лучше сначала warning/error, потом repair command. |
| Config schema migrations | —/8/— | 9/9/9 | У нас нет schema version. Нужно до расширения формата. |
| Backward compatibility policy | 6/9/6 | 9/9/9 | У нас принцип есть в AGENTS. У них есть кодовые migrations и fixtures. |
| Shell completions | —/4/— | 8/5/8 | Nice-to-have после CLI. |
| Release/Homebrew/binaries | —/5/— | 9/7/9 | У нас Composer package. У них GoReleaser/Homebrew/cross-platform binaries. Для PHP-пакета не нужно напрямую. |
| CI matrix | 6/9/6 | 9/9/9 | У нас минимальный PHP syntax/validate. У них Linux/macOS/Windows, lint, test, coverage. |
| Test suite | —/10/— | 9/10/9 | У них 102 test files и ~1545 test functions. У нас тестов нет. Это крупнейший engineering gap. |
| Runtime dependencies | 9/8/9 | 9/8/9 | У нас PHP only. У них static Go binary, но dev/runtime stack тяжелее на уровне исходников. |
| MIT license | 9/8/9 | 9/8/9 | Оба MIT — хорошо для adoption. |
| Human Brief | 8/8/8 | —/5/— | Наша сильная фича для качества постановки. |
| SMART/MoSCoW/INVEST | 8/7/7 | —/4/— | У них task body свободный; быстрее, но меньше quality gates. |
| Value/complexity/priority model | 8/8/8 | 5/5/5 | У них priority/class/due, но нет нашей value/complexity модели. |
| Cost tracking в токенах | 7/5/6 | —/3/— | Наша AI-budget фича. У них token-efficient output, но не cost accounting. |
| Link validation | 8/8/8 | —/5/— | У нас есть проверка local markdown links. У них файлы не двигаются, поэтому проблема меньше. |
| Placeholder validation | 8/7/8 | —/4/— | У нас полезно из-за шаблонов. У них create CLI снижает риск template leftovers. |
| Строгий validator command | 8/9/8 | 6/7/6 | У нас отдельный `todo-md-validate`. У них validation встроена в операции и consistency auto-repair, отдельного `validate` в README нет. |
| Русская документация | 8/8/8 | —/2/— | Наша фича для текущей аудитории. У них англоязычный публичный продукт. |

## Что есть у kanban-md, а у нас нет

### Стоит взять в ближайший roadmap

- Единый рабочий CLI:
  - `todo-md create` / `todo-md list` / `todo-md show` / `todo-md edit` / `todo-md move`.
  - Можно оставить текущие `todo-md-init` и `todo-md-validate`, но постепенно добавить unified entrypoint.
- JSON и compact output:
  - `--json` для scripts;
  - `--compact` для AI-агентов и меньшего расхода токенов.
- Config-файл:
  - `todo-md.yml` или `.todo-mdrc`;
  - paths, statuses, priorities, defaults, strict/compact templates, agent settings.
- Lifecycle timestamps:
  - `updated`, `started`, `completed`;
  - нужны для metrics и stale tasks.
- Dependency validation:
  - существование `depends_on`/`epic`;
  - запрет self-dependency;
  - позже — cycles.
- Claims:
  - `claimed_by`, `claimed_at`, `claim_timeout`;
  - `pick --claim` для параллельных агентов;
  - `handoff` для review/blocked/user input.
- Board summary:
  - counts by status/type/priority/assignee;
  - blocked/stale/overdue;
  - JSON/compact.
- Agent skills:
  - `todo-md` — command reference;
  - `todo-md-development` — выполнение задачи через claim/branch/PR/review/done.
- Тестовая база:
  - fixture tests для init/validate/create/move;
  - compatibility fixtures для старых task/config formats.

### Можно взять позже

- TUI как human supervision layer.
- Metrics: throughput, lead time, cycle time, aging WIP.
- Activity log of mutations.
- WIP limits для `in_progress` и `review`.
- Classes of service (`expedite`, `fixed-date`, `standard`, `intangible`) как optional extension.
- Shell completions.
- `context` command для обновления блока в `AGENTS.md`.
- Self-healing repair command:
  - `todo-md repair ids`;
  - `todo-md repair filenames`;
  - `todo-md repair links`.

### Брать осторожно

- Numeric IDs.
  - У `kanban-md` они удобны для CLI/TUI, но у нас человекочитаемые `TASK-<category>-<slug>` лучше ложатся в PR, branch и docs.
  - Можно добавить короткий numeric alias как internal field, но не заменять публичный ID без v2.
- Статус только в front matter без папок.
  - Это решает проблему битых ссылок при переносе задач, но ломает текущий workflow.
  - Лучше сначала сделать CLI, который сам переносит файл и чинит ссылки; потом подумать о v2 mode `single_tasks_dir`.
- Auto-repair на каждом запуске.
  - Удобно, но опасно для строгого пакета: команда чтения не должна неожиданно менять файлы.
  - Лучше начать с `validate` warnings/errors и отдельной `repair` команды.
- Полный TUI раньше базового CLI.
  - TUI ценен, но без стабильной модели create/list/show/edit/move он будет преждевременным.

## Что есть у нас, а у kanban-md нет

- Строгий формат постановки задачи как документа.
- Human Brief на русском языке.
- SMART, User Story/Job Story, MoSCoW, INVEST.
- Явные эпики `EPIC-*.todo.md`.
- Value/complexity/priority как отдельная модель планирования.
- Cost tracking в токенах.
- Branch/PR поля в lifecycle задачи.
- Локальная Markdown link validation.
- Template placeholder validation.
- Consumer docs package: `docs/todo-md/`, reference docs, templates.
- Composer/PHP integration для PHP-проектов.
- Status-folder validation (`todo/backlog`, `todo/done`, `todo/cancelled`).
- Более жёсткое Definition of Ready/Definition of Done в шаблонах.

## Чего нет у обоих

- Web UI/SaaS sync.
- Интеграция с GitHub Issues/Jira/Linear.
- Автоматическое создание GitHub PR из задачи.
- Автоматическое заполнение PR URL обратно в задачу.
- Автоматические labels в PR/issues из task metadata.
- Dependency graph visualization.
- Проверка циклов зависимостей как first-class команда.
- MCP/server API.
- Cost dashboard по токенам/деньгам.
- Автоматическая проверка, что документация обновлена относительно изменения кода.
- Семантическая валидация качества постановки через LLM-review.

## Что у нас может быть лишним или чрезмерным

- Полный task template для всех задач.
  - На фоне `kanban-md` видно, что маленькие задачи выигрывают от лёгкого формата.
  - Решение: добавить compact template для `C0/C1`, а полный template оставить для `C2+` и эпиков.
- Status folders как обязательная механика.
  - Они понятны, но создают проблему относительных ссылок и лишних moves.
  - Решение: не удалять, но сделать все перемещения только через CLI с auto-link-fix; позже рассмотреть single-dir mode.
- Ручное заполнение `branch`/`pr`.
  - В `kanban-md` такого нет; workflow живёт через CLI/skills.
  - Решение: оставить поля, но добавить команду заполнения из текущего git/GitHub контекста.
- `cost_plan/cost_fact` в каждом шаблоне.
  - Полезно для AI-budget, но не всем нужно.
  - Решение: optional + configurable validation.
- Справочник AI-агентов как статичный документ.
  - У `kanban-md` agent identity генерируется динамически (`agent-name`) и используется в claims.
  - Решение: оставить справочник для author/assignee, но добавить runtime claim identity.

## Риски и наблюдения по kanban-md

- Claims — сильная концепция, но при переносе идеи нужно сделать реальную атомарность всех mutating операций.
  - В просмотренном коде file lock явно используется для `create` и TUI-mutations; для `pick/edit/move` отдельный lock вокруг всей операции не очевиден.
  - Для `todo-md` лучше сразу проектировать lock/compare-and-swap для claim/move.
- Auto-repair на обычных командах удобно, но может неожиданно менять файлы.
  - Для нашего строгого валидатора лучше разделить `validate` и `repair`.
- `kanban-md init` по умолчанию предлагает добавить board в `.gitignore`.
  - Это хорошо для локального агентского board, но хуже для task history in repo.
  - `todo-md` лучше сохранять задачи в Git как продуктовый артефакт.
- Go single binary — отличная delivery model, но не совпадает с нашим PHP/Composer позиционированием.
  - Не нужно тащить Go/Node runtime в `todo-md`; фичи можно реализовать на PHP 8.4.

## Приоритетный план развития todo-md после сравнения

### P0 — закрыть engineering gap

1. Исправить/проверить Composer package archive:
   - `docs/todo-md/` и `todo/AGENTS.md` должны попадать в package dist.
2. Добавить тестовую инфраструктуру:
   - fixture projects;
   - тесты `todo-md-init`;
   - тесты `todo-md-validate`;
   - regression tests для links/placeholders/status folders.
3. Добавить schema version в front matter или package docs:
   - минимум поле/правило `todo_md_version` пока optional;
   - подготовить migration policy.
4. Усилить validator:
   - существование `depends_on` и `epic`;
   - self-dependency;
   - unknown local task links;
   - future: dependency cycles.

### P1 — сделать todo-md настоящим CLI-tool

1. Добавить unified CLI `bin/todo-md` или новые bin-команды:
   - `todo-md list`;
   - `todo-md show <ID>`;
   - `todo-md create`;
   - `todo-md edit/set`;
   - `todo-md move/start/review/done/cancel`.
2. Добавить output formats:
   - table для людей;
   - `--json` для scripts;
   - `--compact` для агентов.
3. Добавить config:
   - `.todo-mdrc` / `todo-md.yml`;
   - paths/statuses/defaults/validation strictness.
4. Сделать status transitions безопасными:
   - обновлять front matter;
   - перемещать файл в нужную папку;
   - чинить относительные ссылки.

### P2 — multi-agent workflow

1. Добавить claims:
   - `claimed_by`, `claimed_at`, `claim_timeout`;
   - `todo-md pick --claim <agent>`;
   - `todo-md release`.
2. Добавить handoff:
   - move to review/blocked;
   - append timestamped note;
   - release claim.
3. Добавить `agent-name`.
4. Добавить installable skills:
   - `todo-md` — command reference;
   - `todo-md-development` — claim → branch/worktree → implement → PR → done.

### P3 — supervision и аналитика

1. `todo-md board`:
   - counts by status/type/priority/value/assignee;
   - blocked/stale tasks.
2. `todo-md context`:
   - генерировать summary block для `AGENTS.md`.
3. `todo-md metrics`:
   - throughput;
   - lead/cycle time;
   - cost plan/fact summary;
   - aging WIP.
4. Optional TUI.
5. Optional `repair` commands:
   - IDs;
   - filenames;
   - moved links;
   - stale branch/PR fields.

## Итоговая рекомендация

`kanban-md` — лучший ориентир для операционного слоя `todo-md`.

Не стоит менять нашу сильную сторону — строгую постановку задач и эпики. Но нужно добавить то, чего сейчас не хватает:

- CLI для ежедневной работы;
- JSON/compact output для агентов;
- config и schema/migration policy;
- tests;
- claims/pick/handoff для параллельной работы;
- board summary/context/metrics;
- versioned installable skills.

Главная продуктовая идея: `todo-md` должен остаться **строгим task-writing standard для AI-разработки**, но получить **удобство управления**, сопоставимое с `kanban-md`.

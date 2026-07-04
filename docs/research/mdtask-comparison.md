# Сравнение todo-md и syabro/mdtask

Дата исследования: 2026-07-04.

Источник: [`syabro/mdtask`](https://github.com/syabro/mdtask), ветка `main`, коммит `ca259e415d33b805bfc507d634ad34b0e0659318` от 2026-07-02, версия npm-пакета `0.1.17`.

## Короткий вывод

- `todo-md` и `mdtask` решают близкую задачу: file-first задачи в Markdown, без базы данных, сервера и UI, с Git как источником истории.
- Главная разница в модели:
  - `todo-md`: отдельные `.todo.md` файлы задач и эпиков, YAML front matter, kanban-папки, строгий формат постановки, PHP/Composer-пакет для проектов-потребителей.
  - `mdtask`: задачи как checkbox-блоки внутри обычных `.md` спецификаций (specs), сильный CLI для просмотра/мутации задач, агентские skills и spec-driven development (SDD).
- Самое ценное, что стоит взять как идеи: `list/view/open`, `--json`, `.mdtaskrc`, автоназначение ID, установка agent skills, сильная тестовая база, устойчивый Markdown-сканер.
- Не стоит копировать код или тексты напрямую: у `mdtask` лицензия PolyForm Shield 1.0.0 с non-compete ограничением. Для `todo-md` безопаснее переиспользовать только идеи и реализовать их самостоятельно.
- В `todo-md` сильнее постановка задачи: Human Brief, SMART, MoSCoW, INVEST, value/complexity/priority, cost tracking, эпики, link validation и status-folder validation.
- В `todo-md` слабее продуктовая оболочка: мало CLI-команд, нет JSON/API для агентов, нет авто-ID, нет тестов init/validate, нет конфигурации проекта.

## Метод оценки

Формат оценок: `Q/N/M`.

- `Q` — качество реализации (quality), 1 низкое, 10 очень высокое. Если фичи нет: `—`.
- `N` — нужность для конкретного проекта (need), 1 можно обойтись, 10 критично.
- `M` — моя оценка зрелости/согласованности (maturity/fit): насколько фича хорошо встроена в модель проекта и поддерживаема, 1 слабая, 10 сильная. Если фичи нет: `—`.

Оценки субъективные, но основаны на просмотре README, specs, skills, исходников, CI и тестов.

## Что общего

- Markdown-файлы — source of truth.
- Нет базы данных, сервера, web UI.
- Git используется как история и механизм синхронизации.
- Есть CLI-валидатор или CLI-интерпретатор задач.
- Есть machine-readable метаданные задач.
- Есть статусы завершения и архивирования/перемещения завершённых задач.
- Есть зависимости между задачами.
- Есть правила для AI-агентов.
- Есть установка как dev/tooling dependency.
- Формат рассчитан на ручное редактирование и code review через diff.

## Сравнение фич

| Фича | todo-md Q/N/M | mdtask Q/N/M | Комментарий |
|---|---:|---:|---|
| File-first Markdown без DB/UI/server | 8/10/9 | 9/10/9 | Общая база. У `mdtask` модель лучше поддержана CLI, у нас — документацией и валидатором. |
| Git как sync/history | 7/9/8 | 8/9/9 | У обоих концептуально хорошо. У `mdtask` сильнее идея закрывать задачу вместе с обновлением spec. |
| Отдельные файлы задач `.todo.md` | 8/9/8 | —/4/— | Наша модель удобна для kanban, PR и больших постановок. `mdtask` сознательно выбрал inline-задачи в specs. |
| Задачи внутри specs одним файлом | —/5/— | 8/9/9 | Полезно для SDD, но конфликтует с нашей file-per-task моделью. Можно взять как optional spec-journal режим, не как замену. |
| YAML front matter | 8/9/8 | —/3/— | У нас богаче структура. У `mdtask` inline tokens проще и быстрее. |
| Inline metadata tokens (`#tag`, `!high`, `@key:value`) | —/6/— | 8/8/8 | Можно частично взять как быстрые labels/tags, но не заменять YAML. |
| Типы задач (`feat`, `fix`, `docs`, etc.) | 8/8/8 | —/4/— | У нас совместимо с Conventional Commits. У `mdtask` это можно выражать tags/properties, но нет справочника. |
| Статусы workflow | 8/9/8 | 6/7/7 | У нас богаче (`todo`, `in_progress`, `review`, `done`, etc.). У `mdtask` базово только open/done плюс properties. |
| Kanban-папки (`backlog`, `done`, `cancelled`) | 7/8/7 | —/4/— | Хорошо для визуального файлового workflow. Минус — нужно обновлять относительные ссылки. |
| Архив завершённых задач | 5/6/5 | 8/7/8 | У нас `done/`; у `mdtask` есть `archive` в `_archive.md`. Нам достаточно `done/`, но можно добавить архивирование по годам/релизам позже. |
| Эпики | 8/8/8 | 5/6/6 | У нас явные `EPIC-*.todo.md` и поле `epic`. У `mdtask` роль эпика частично выполняет spec/story group. |
| Dependencies/blockers | 6/8/6 | 8/8/8 | У нас валидируется формат `depends_on`, но не существование/статус ID. У `mdtask` `@blocked_by` влияет на `list`. |
| Приоритеты | 8/8/8 | 7/7/7 | У нас P0–P3 + value/complexity. У `mdtask` проще: `!crit`, `!high`, medium, `!low`. |
| Value/complexity/priority модель | 8/8/8 | —/4/— | Сильная наша фича для планирования. У `mdtask` есть первая строка business value, но нет формальной модели. |
| Cost tracking в токенах | 7/5/6 | —/2/— | У нас полезно для AI-budget, но не критично для core. Держать optional. |
| Human Brief | 8/8/8 | 6/6/7 | У нас отдельная обязательная секция. У `mdtask` близкая идея — первая business value line. |
| SMART / User Story / Job Story / MoSCoW / INVEST | 8/7/7 | 5/5/6 | У нас полно и строго. Риск: тяжело для маленьких задач. Нужен compact template. |
| Definition of Done / verification commands | 8/9/8 | 7/8/8 | У нас явно в шаблоне и валидаторе. У `mdtask` DoD в prose и behavior check в skill. |
| `Implemented:` журнал результата | —/7/— | 8/8/9 | Стоит взять: короткий outcome-блок после выполнения задачи полезен и для нас. |
| Обновление пользовательской spec-документации при закрытии задачи | —/6/— | 8/9/8 | Хорошая SDD-практика. Для нас можно сделать правилом: если задача меняет поведение, обновить README/docs. |
| Init/bootstrap CLI | 8/9/8 | —/3/— | У нас сильная фича: `todo-md-init` разворачивает структуру и docs в consumer project. У `mdtask` установка проще, без init. |
| Копирование правил для агентов в consumer project | 8/8/8 | 7/8/8 | У нас `todo/AGENTS.md`. У `mdtask` skills устанавливаются symlink/cache командой `install-skills`. |
| Agent skills как отдельные `SKILL.md` | —/8/— | 8/9/8 | Очень полезно для Codex/Claude-like harness. Можно добавить shippable skills поверх текущих docs. |
| Skill установки (`install-skills`) | —/7/— | 8/8/8 | Хорошая идея: автоматизировать установку agent skills вместо ручных ссылок. |
| CLI `list` | —/9/— | 9/10/9 | Главный gap. Нам нужен `todo-md-list` или подкоманда в unified CLI. |
| CLI `view/show` | —/8/— | 8/8/8 | Полезно для агентов и людей: быстро открыть полную задачу по ID. |
| CLI `open` в `$EDITOR` | —/6/— | 8/7/8 | Не критично, но повышает UX. Реализуется просто. |
| CLI `move` | —/7/— | 8/7/8 | У нас перемещение связано со статусом и ссылками, поэтому лучше делать status-команды, а не raw move. |
| CLI `set` metadata | —/7/— | 7/7/7 | Для нас нужны команды `set-status`, `assign`, `set-pr`, `set-branch`, `set-cost`. |
| CLI `ids` auto-assign | —/8/— | 8/8/8 | Очень полезно. У нас ID slug-based, поэтому нужен generator `TASK-<category>-<slug>` с collision handling. |
| Short numeric lookup | —/3/— | 7/6/7 | Для нас не подходит: наши ID человекочитаемые slug-based. Можно не брать. |
| `--json` для list/view | —/8/— | 8/9/9 | Важно для AI-агентов и внешних scripts. Нужно взять рано. |
| Конфиг `.mdtaskrc` | —/8/— | 8/9/8 | У нас нужны `todoPath`, `docsPath`, include/exclude, статусные папки, строгий/мягкий режим. |
| `MDTASK_PATH` env / `--path` precedence | 5/6/5 | 8/8/8 | У нас есть аргументы у init/validate, но нет единой конфигурации и env. |
| Include/exclude file patterns | —/7/— | 8/8/8 | Нужно для monorepo, `vendor`, архивов, generated docs. |
| Сканер `.md` с ripgrep/find fallback | 5/6/5 | 8/8/8 | У нас сканируем только `.todo.md`; проще, но менее гибко. Стоит улучшить устойчивость для symlinks/exclude. |
| Игнор checkbox-задач внутри code fences | —/4/— | 8/7/8 | Нам менее нужно, потому что `.todo.md` структурированы. Полезно только если добавим inline tasks. |
| Link validation | 8/8/8 | —/5/— | Сильная наша фича. У `mdtask` такого нет. |
| Template placeholder validation | 8/7/8 | —/4/— | Полезно для качества задач. У `mdtask` lightweight формат меньше нуждается. |
| Status-folder validation | 8/8/8 | —/3/— | Наша важная гарантия kanban-модели. |
| Filename/H1 ID validation | 8/8/8 | 7/7/7 | У нас ID связан с файлом и H1. У `mdtask` ID в checkbox header. |
| Проверка локальных связей `depends_on` / `epic` на существование | —/9/— | 6/7/6 | У нас сейчас нет, но очень нужно. У `mdtask` blockers проверяются через statusMap, но missing blocker считается unresolved, а не validation error. |
| Тестовая база | —/10/— | 9/10/9 | У `mdtask` около 400 test cases в 19 файлах. У нас пока только CI lint/syntax/validate текущей задачи. Это главный engineering gap. |
| CI | 6/9/6 | 8/9/8 | У нас CI минимальный. У `mdtask` lint+tests на Node 22. Нам нужны unit/integration tests для CLI. |
| Package manifest для публикации | 5/9/5 | 8/9/8 | У `mdtask` `files` явно включает dist/skills/README/LICENSE. У нас `archive.include` содержит только `/bin/`; нужно проверить, не выпадают ли docs из dist. |
| Русская документация | 8/8/8 | —/2/— | Наша фича под текущую аудиторию. Для внешнего adoption позже можно добавить English README/docs. |
| Лицензия | 9/8/9 | 4/6/4 | У нас MIT. У `mdtask` PolyForm Shield с non-compete — серьёзное ограничение для reuse. |

## Что есть у mdtask, а у нас нет

### Стоит взять в ближайший roadmap

- `list` с фильтрами и сортировкой.
  - Минимум: `todo-md-list --status --type --priority --assignee --json`.
  - Важность: высокая, потому что сейчас у нас есть validator, но нет обзорного рабочего CLI.
- `view/show <ID>`.
  - Быстрое получение полной задачи агентом без ручного `find`.
- `--json` для `list` и `view`.
  - Нужен стабильный контракт для AI-агентов, dashboards и scripts.
- `.todo-mdrc` или `todo-md.json`.
  - Настройки: `todo_path`, `docs_path`, `statuses`, include/exclude, strict mode.
- Auto-ID / create helper.
  - Для нас: генерация `TASK-<category>-<slug>.todo.md`, проверка collision, создание из шаблона.
- CLI-команды изменения метаданных.
  - `set-status`, `assign`, `set-pr`, `set-branch`, `set-cost`, `link-epic`.
- Более сильная валидация связей.
  - Проверять, что `depends_on` и `epic` указывают на существующие `TASK/EPIC`.
  - Предупреждать о циклах зависимостей.
- Agent skills как shippable слой.
  - `todo-md-add`, `todo-md-do`, `todo-md-review-task` поверх текущих AGENTS docs.
- Test suite.
  - Unit tests для YAML parser/validator.
  - Integration tests для init/validate/list/create/status transitions.
  - Fixture-based tests для consumer project.

### Можно взять позже

- `open <ID>` в `$EDITOR`.
- `archive` старых done задач по релизам/годам.
- `install-skills <dir>` с symlink/cache моделью.
- `Implemented:` outcome block при закрытии задачи.
- SDD-правило: если задача меняет поведение, обновить feature docs в том же PR.
- Табличный TTY-output и цветной вывод.

### Лучше не брать

- Short numeric lookup как основную модель ID.
  - У нас slug IDs понятнее и лучше читаются в PR/branch/task links.
- Полный переход на inline checkbox tasks внутри specs.
  - Это сломает нашу модель эпиков, kanban-папок и строгих задач.
- Код/тексты `mdtask` напрямую.
  - Лицензия с non-compete. Использовать только идеи.

## Что есть у нас, а у mdtask нет

- Composer/PHP 8.4 dev-package без Node.js runtime.
- Idempotent init в consumer project.
- Строгая задача как отдельный документ.
- YAML front matter с богатой схемой.
- Эпики как отдельный тип файла.
- Kanban-папки и status-folder validation.
- Human Brief на русском языке.
- SMART, User Story, Job Story, MoSCoW, INVEST.
- Value/complexity/priority как отдельная модель принятия решений.
- Cost tracking в токенах.
- Link validation после перемещения задач.
- Placeholder validation в задачах.
- Branch/PR поля в lifecycle задачи.
- MIT-лицензия.

## Чего нет у обоих

- Web UI или TUI board.
- Полноценная аналитика: lead time, cycle time, burndown, throughput.
- Dependency graph и critical path.
- Автоматическая проверка циклов зависимостей.
- Автоматическое создание branch/PR из задачи.
- Автоматическое обновление PR labels/status из front matter.
- Cost dashboard по `cost_plan` / `cost_fact`.
- Schema versioning и миграции старых задач.
- Locking/concurrency protection для параллельных агентов.
- Интеграции с GitHub Issues/Jira/Linear.
- MCP/server API.
- Автоматическая проверка свежести документации относительно кода.

## Что у нас может быть лишним или чрезмерным

- Обязательность полной SMART/MoSCoW/INVEST структуры для каждой маленькой задачи.
  - Риск: задачи становятся слишком тяжёлыми, агенты тратят контекст на форму.
  - Решение: добавить compact template для C0/C1 задач, сохранив полный template для C2+.
- `cost_plan` / `cost_fact` в core-шаблоне.
  - Полезно для AI-budget, но не всем проектам нужно.
  - Решение: оставить optional, не делать blocker при пустом значении.
- Справочник AI-агентов.
  - Полезен в конкретной экосистеме, но требует ручной синхронизации.
  - Решение: сделать override в config или consumer docs.
- Mandatory `branch` и `pr` поля.
  - Хорошо для GitHub workflow, но шумно для локальных задач и проектов без PR.
  - Решение: оставить поля, но не требовать заполнения до соответствующего lifecycle stage.

## Риски в нашем текущем проекте

- В `composer.json` указано `archive.include: ["/bin/"]`.
  - Риск: при сборке package archive документация `docs/todo-md/` может не попасть в дистрибутив, а `todo-md-init` ожидает её наличие.
  - Действие: проверить реальный Composer dist/install и, если нужно, включить `/docs/`, `/todo/AGENTS.md`, `README.md`, `LICENSE`.
- Нет тестов для CLI.
  - Риск: init/validate легко сломать при расширении.
  - Действие: добавить PHPUnit или self-contained PHP fixture tests без внешних runtime dependencies.
- Валидатор парсит YAML как simple key-value.
  - Это соответствует минимальным зависимостям, но нужно явно документировать ограничения.
- Нет CLI для нормальной работы с задачами.
  - Сейчас package больше похож на rules+validator, а не task-management tool.

## Приоритетный план развития

### P0 — стабилизировать основу

1. Проверить и исправить packaging: docs и `todo/AGENTS.md` должны попадать в устанавливаемый package.
2. Добавить тесты для `todo-md-init` и `todo-md-validate` на fixture-проектах.
3. Добавить validation существования `depends_on` и `epic`.
4. Добавить `--json` output в validator или отдельный `todo-md-list --json`.

### P1 — сделать рабочий CLI

1. `todo-md-list`:
   - фильтры: status/type/priority/value/complexity/assignee/epic;
   - сортировка: priority, value, complexity, created;
   - `--json`.
2. `todo-md-view <ID>`:
   - поиск по filename/H1/front matter;
   - человекочитаемый и JSON output.
3. `todo-md-create`:
   - создание задачи/эпика из шаблона;
   - генерация slug ID;
   - проверка collision.
4. `todo-md-set` или подкоманды lifecycle:
   - `start`, `pause`, `block`, `review`, `done`, `cancel`, `backlog`;
   - синхронизация status и папки.

### P2 — улучшить agent workflow

1. Добавить `skills/`:
   - `todo-md-add` — постановка задачи;
   - `todo-md-do` — выполнение задачи;
   - `todo-md-review` — проверка качества задачи.
2. Добавить `todo-md-install-skills <dir>`.
3. Добавить outcome-блок при завершении задачи:
   - `## Result` или `## Implementation Result`.
4. Добавить правило: задачи, меняющие поведение, должны обновлять README/docs в том же PR.

### P3 — аналитика и интеграции

1. `todo-md-report`:
   - количество задач по status/type/priority;
   - cost plan/fact;
   - overdue/stale tasks.
2. Dependency graph:
   - cycles;
   - blocked tasks;
   - critical path.
3. GitHub integration helpers:
   - заполнение `pr` из текущего branch PR;
   - labels из `type/priority/assignee`.
4. Optional TUI board.

## Итоговая рекомендация

Не менять фундаментальную модель `todo-md`: отдельные `.todo.md` задачи, эпики, YAML front matter и kanban-папки дают нам сильную постановку и понятный lifecycle.

Главное направление улучшений — не переписывать формат под `mdtask`, а добавить недостающий операционный слой:

- CLI для просмотра и изменения задач;
- JSON-контракт для агентов;
- config-файл;
- авто-ID/create workflow;
- тесты;
- agent skills;
- validation связей.

`mdtask` стоит рассматривать как хороший пример UX и engineering discipline, а не как источник кода.

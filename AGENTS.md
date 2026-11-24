# AGENTS.md — правила для AI-агентов

Правила для AI-агентов (Codex, OpenCode, Roo, Kilo, Pi и др.) по работе с данным пакетом.

---

## Проект

**prikotov/todo-md** — PHP-пакет: file-based kanban board для управления задачами в markdown-файлах с YAML front matter.

Пакет подключается к проекту-потребителю через `composer require --dev` и инициализируется командой `php vendor/bin/todo-md init`. В репозитории пакета: `php bin/todo-md init`.

### Состав

1. **CLI `bin/todo-md`** — единая точка входа, диспетчер подкоманд: `init`, `create`, `start`, `review`, `done`, `cancel`, `backlog`, `set`, `validate`, `export-jsonl`, `dashboard`.
2. **Документация** (`docs/todo-md/`) — конвенции, справочники, шаблоны задач и эпиков, копируемые командой `init`.

---

## Перед каждым пушем / PR

- **Проверять `composer.json`** на приватные зависимости — в CI нет доступа к VCS-репозиториям.
- **Локально:** `make check` — lint + валидация + тесты (то же, что в CI).
- **CI** запускается автоматически при пуше/PR (`.github/workflows/ci.yml` → `make check`).

## Merge policy (обязательно для AI-агентов)

- **Агент не мержит PR.** Merge делает владелец после ревью и апрува — в UI или `gh pr merge`; для агента это запрещено.
- **Прямые пуши в `master` запрещены** и заблокированы ruleset на GitHub (`master: PR-only`): deletion, non-fast-forward, обязательный PR и CI-чек `validate`. Любые правки `master` — только через ветку `task/*` и PR, см. [Ветки](vendor/prikotov/git-workflow/docs/git-workflow/branches.md).
- **Не обходить защиту ветки.** Если GitHub отклонил пуш или merge — остановиться и сообщить владельцу; не пытаться обойти через API, `--admin`, правку ruleset или смену default branch.
- **Завершение задачи — в ветке задачи:** финальный `chore(todo)`-коммит (перенос `todo/ → done/` целиком через `git add -A todo/`, поле `pr=`, строка «Истории изменений») делается в той же ветке `task/*` и попадает в `master` тем же PR.

---

## Принципы работы с кодом

- **PHP 8.4**, строгая типизация (`declare(strict_types=1)`).
- **Читаемость** важнее производительности. Осмысленные имена, минимальная вложенность.
- **Стабильность**: init-скрипт должен быть обратно совместим — проекты-потребители не должны ломаться при обновлении.

---

## Структура пакета

```text
bin/
  todo-md                    # Единая CLI-команда (диспетчер подкоманд)
src/
  TodoMd/
    Parser.php               # Парсинг front matter, YAML, путей, ссылок
    Validator.php            # Валидация задач и эпиков
    Board.php                # Атомарные переходы, link rewriting, rollback
  bootstrap.php              # Подключение модулей + CLI-функции всех подкоманд
docs/
  todo-md/                   # Документация, копируемая в проект-потребитель
    AGENTS_TASK_WRITING_GUIDE.md
    reference/               # Справочники: TYPES, STATUSES, VALUES, COMPLEXITY, PRIORITIES, COST, AI_AGENTS, GLOSSARY
    templates/               # Шаблоны: task.md, epic.md
tests/                       # Fixture-тесты
todo/                        # Внутренние задачи по доработке пакета
  AGENTS.md                  # Исходник правил работы с задачами, копируемый потребителю
```

---
## Правила написания документации

### Язык и стиль

- **Русский** с английским термином в скобках при первом упоминании.
- Формулировки как «пули» — короткие, чёткие, без воды.
- Каждый документ описывает **один подход** или **одну сущность**.

### Форматирование

- **Markdown** для всех документов.
- **Запрещён псевдокод** — только реальные примеры.
- Кодовые блоки с указанием языка.

---

## Команда `init` (`todo-md init`)

### Ключевые принципы

- **Idempotent** — повторный запуск безопасен, существующие файлы не перезаписываются (кроме `--force`).
- **Минимальные зависимости** — только PHP 8.4, без внешних библиотек.
- **Аргументы**: `[target-dir]`, `--docs-path=<path>`, `--agents-path=<path>`, `--force`, `--no-config` (не создавать/не перезаписывать `.todo-md.php`).

### Что делает

1. Создаёт `todo/`, `todo/backlog/`, `todo/done/`, `todo/cancelled/` (с `.gitkeep`).
2. Копирует `docs/todo-md/` в проект-потребитель.
3. Копирует `todo/AGENTS.md` пакета в `todo/AGENTS.md` потребителя (либо по `--agents-path`).
4. Обновляет `.gitignore` в `docs/` и `todo/`.

---

## Задачи по доработке пакета

Внутренние задачи хранятся в `todo/` в формате пакета (TASK-*.todo.md). Это задачи по развитию документации, шаблонов и init-скрипта.

---

## Git workflow

Правила работы с git — ветки, коммиты, PR, релизы, SemVer:

- [Ветки](vendor/prikotov/git-workflow/docs/git-workflow/branches.md)
- [Коммиты (Conventional Commits)](vendor/prikotov/git-workflow/docs/git-workflow/commits.md)
- [Pull Request](vendor/prikotov/git-workflow/docs/git-workflow/pull-request.md)
- [Code Review](vendor/prikotov/git-workflow/docs/git-workflow/code-review.md)
- [Релизы и CHANGELOG](vendor/prikotov/git-workflow/docs/git-workflow/release.md)

---

## Ссылки

- **README**: [README.md](README.md)
- **Документация пакета**: [docs/todo-md/](docs/todo-md/)
- **AGENTS.md (исходник правил для потребителя)**: [todo/AGENTS.md](todo/AGENTS.md)
- **Справочники**: [docs/todo-md/reference/](docs/todo-md/reference/)
- **Шаблоны**: [docs/todo-md/templates/](docs/todo-md/templates/)
- **CLI**: [bin/todo-md](bin/todo-md)

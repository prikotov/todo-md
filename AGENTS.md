# AGENTS.md — правила для AI-агентов

Правила для AI-агентов (Codex, OpenCode, Roo, Kilo, Pi и др.) по работе с данным пакетом.

---

## Проект

**prikotov/todo-md** — PHP-пакет: file-based kanban board для управления задачами в markdown-файлах с YAML front matter.

Пакет подключается к проекту-потребителю через `composer require --dev` и init-скрипт `bin/todo-md-init`.

### Состав

1. **Init-скрипт** (`bin/todo-md-init`) — CLI-команда, создаёт структуру `todo/` и копирует документацию в проект-потребитель.
2. **Валидатор задач** (`bin/todo-md-validate`) — CLI-команда, проверяет `.todo.md` задачи и эпики.
3. **Документация** (`docs/todo-md/`) — конвенции, справочники, шаблоны задач и эпиков, копируемые init-скриптом.

---

## Перед каждым пушем / PR

- **Проверять `composer.json`** на приватные зависимости — в CI нет доступа к VCS-репозиториям.
- **Запускать CI** — `.github/workflows/ci.yml`.

---

## Принципы работы с кодом

- **PHP 8.4**, строгая типизация (`declare(strict_types=1)`).
- **Читаемость** важнее производительности. Осмысленные имена, минимальная вложенность.
- **Стабильность**: init-скрипт должен быть обратно совместим — проекты-потребители не должны ломаться при обновлении.

---

## Структура пакета

```
bin/
  todo-md-init               # CLI-команда инициализации
  todo-md-validate           # CLI-команда валидации задач и эпиков
docs/
  todo-md/                   # Документация, копируемая в проект-потребитель
    AGENTS.md                # Правила работы с задачами (для AI-агентов потребителя)
    AGENTS_TASK_WRITING_GUIDE.md
    reference/               # Справочники: TYPES, STATUSES, VALUES, COMPLEXITY, PRIORITIES, AI_AGENTS, GLOSSARY
    templates/               # Шаблоны: task.md, epic.md
todo/                        # Внутренние задачи по доработке пакета
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

## Init-скрипт (`bin/todo-md-init`)

### Ключевые принципы

- **Idempotent** — повторный запуск безопасен, существующие файлы не перезаписываются (кроме `--force`).
- **Минимальные зависимости** — только PHP 8.4, без внешних библиотек.
- **Аргументы**: `[target-dir]`, `--docs-path=<path>`, `--agents-path=<path>`, `--force`.

### Что делает

1. Создаёт `todo/`, `todo/backlog/`, `todo/done/`, `todo/cancelled/` (с `.gitkeep`).
2. Копирует `docs/todo-md/` в проект-потребитель (без `AGENTS.md`).
3. Копирует `AGENTS.md` отдельно в `todo/AGENTS.md`.
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
- **AGENTS.md (для потребителя)**: [docs/todo-md/AGENTS.md](docs/todo-md/AGENTS.md)
- **Справочники**: [docs/todo-md/reference/](docs/todo-md/reference/)
- **Шаблоны**: [docs/todo-md/templates/](docs/todo-md/templates/)
- **Init-скрипт**: [bin/todo-md-init](bin/todo-md-init)
- **Валидатор задач**: [bin/todo-md-validate](bin/todo-md-validate)

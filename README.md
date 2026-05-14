# todo-md

### Файловый канбан для управления задачами в markdown

Задачи хранятся как `.md`-файлы с YAML front matter (статус, приоритет, сложность, тип). Переход между статусами — перемещение файла между папками: `todo/` → `todo/done/`. Без базы данных и UI — только файлы, git и консоль.

Пакет содержит правила, шаблоны и справочники для постановки задач. AI-агент получает их как контекст и следует формату при создании и обновлении задач.

---

## Правила

Определяют формат задач (статусы, типы, приоритеты, сложность) и правила работы AI-агентов.

- **Руководство по постановке задач** — обязательные метаданные, структура описания, критерии готовности
- **Типы задач** — `fix`, `feat`, `build`, `chore`, `ci`, `docs`, `style`, `refactor`, `perf`, `test`, `revert`, `epic`
- **Статусы** — `todo`, `in_progress`, `paused`, `blocked`, `review`, `backlog`, `done`, `cancelled`
- **Приоритеты** — `critical`, `high`, `medium`, `low`
- **Сложность** — оценка объёма работы
- **AI-агенты** — правила работы агентов с задачами

Руководство по постановке задач: [`AGENTS_TASK_WRITING_GUIDE.md`](docs/todo-md/AGENTS_TASK_WRITING_GUIDE.md).

---

## Шаблоны

Шаблоны задач и эпиков с YAML front matter:

- **task.md** — [`docs/todo-md/templates/task.md`](docs/todo-md/templates/task.md)
- **epic.md** — [`docs/todo-md/templates/epic.md`](docs/todo-md/templates/epic.md)

---

## Структура папок в проекте

```
todo/
├── AGENTS.md               ← правила для AI-агентов (из пакета)
├── backlog/                ← backlog-задачи
├── done/                   ← завершённые задачи
├── cancelled/              ← отменённые задачи
├── TASK-*.todo.md          ← активные задачи
└── EPIC-*.todo.md          ← активные эпики

docs/todo-md/              ← документация (из пакета)
```

---

## Установка в проект

```bash
composer require --dev prikotov/todo-md
```

В состав пакета входят:

- **Правила** — формат задач и правила работы
- **Шаблоны** — task.md, epic.md
- **Справочники** — типы, статусы, приоритеты, сложность

### Инициализация

```bash
php vendor/bin/todo-md-init
```

Создаёт структуру папок (`todo/`, `todo/backlog/`, `todo/done/`, `todo/cancelled/`) и копирует документацию в `todo/docs/`. Существующие файлы не перезаписываются.

---

## License

[MIT](LICENSE)

# prikotov/todo-md

File-based kanban board for managing tasks in markdown files.

## What is it

A set of conventions, templates and rules for managing project tasks as `.md` files with YAML front matter metadata. Tasks move through statuses via folder relocation (`todo/` → `todo/done/`).

## Structure

```
todo/
├── docs/                   ← documentation (from this package)
│   ├── AGENTS.md
│   ├── AGENTS_TASK_WRITING_GUIDE.md
│   ├── reference/        ← reference docs
│   │   ├── TYPES.md
│   │   ├── STATUSES.md
│   │   ├── VALUES.md
│   │   ├── COMPLEXITY.md
│   │   ├── PRIORITIES.md
│   │   ├── AI_AGENTS.md
│   │   └── GLOSSARY.md
│   └── templates/        ← task and epic templates
│       ├── task.md
│       └── epic.md
├── backlog/               ← backlog tasks (status: backlog)
├── done/                  ← completed tasks (status: done)
├── cancelled/             ← cancelled tasks (status: cancelled)
├── TASK-*.todo.md         ← active tasks
└── EPIC-*.todo.md         ← active epics
```

## Reference

After initialisation, your `todo/docs/` contains:

- **AGENTS.md** — rules for working with tasks (for AI agents).
- **AGENTS_TASK_WRITING_GUIDE.md** — how to write tasks.
- **reference/** — TYPES, STATUSES, VALUES, COMPLEXITY, PRIORITIES, AI_AGENTS, GLOSSARY.
- **templates/** — task.md, epic.md.

## Installation

```bash
composer require --dev prikotov/todo-md
```

## Initialisation

After installing the package, run the init command in your project root:

```bash
php vendor/bin/todo-md-init
```

Or specify a target directory:

```bash
php vendor/bin/todo-md-init /path/to/project
```

This will:
1. Create `todo/`, `todo/backlog/`, `todo/done/`, `todo/cancelled/` (with `.gitkeep` files).
2. Copy `docs/` from the package into `todo/docs/` (AGENTS.md, guides, references, templates).
3. Print next steps.

> **Note:** Existing files are never overwritten. Re-running the command is safe.

## License

MIT

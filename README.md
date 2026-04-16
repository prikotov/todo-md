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

## Usage

Copy the contents of this package into your project's `todo/` directory:

```bash
cp -r vendor/prikotov/todo-md/docs/ todo/docs/
```

Create the working directories:

```bash
mkdir -p todo/backlog todo/done todo/cancelled
```

Reference `docs/AGENTS.md` from your project's root `AGENTS.md`.

## License

MIT

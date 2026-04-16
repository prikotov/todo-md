# prikotov/todo-md

File-based kanban board for managing tasks in markdown files.

## What is it

A set of conventions, templates and rules for managing project tasks as `.md` files with YAML front matter metadata. Tasks move through statuses via folder relocation (`todo/` → `todo/done/`).

## Structure

```
todo/
├── AGENTS.md              ← rules for AI agents working with tasks
├── docs/                  ← reference documents
│   ├── AGENTS_TASK_WRITING_GUIDE.md
│   ├── TYPES.md
│   ├── STATUSES.md
│   ├── VALUES.md
│   ├── COMPLEXITY.md
│   ├── PRIORITIES.md
│   ├── AI_AGENTS.md
│   └── GLOSSARY.md
├── templates/             ← task and epic templates
│   ├── task.md
│   └── epic.md
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
cp -r vendor/prikotov/todo-md/templates/ todo/templates/
cp vendor/prikotov/todo-md/AGENTS.md todo/AGENTS.md
```

Create the working directories:

```bash
mkdir -p todo/backlog todo/done todo/cancelled
```

Reference `todo/AGENTS.md` from your project's root `AGENTS.md`.

## License

MIT

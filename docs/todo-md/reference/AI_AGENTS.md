# Список AI-агентов

Используйте эти идентификаторы для полей `Автор` и `Исполнитель` в задачах и эпиках. Эти же значения используются как метки (labels) в Pull Request.

- `gemini-cli` — Gemini CLI
- `codex-cli` — Codex из локального CLI
- `codex` — Codex из cloud-окружения
- `opencode` — OpenCode
- `roocode` — Roo Code
- `kilocode` — Kilo Code
- `pi` — Pi Coding Agent

*Список синхронизирован с git-workflow pull-request rules.*

Валидатор `todo-md validate` проверяет, что агент в полях `author`/`assignee` входит в этот список (по умолчанию — как warning; в режиме `--strict` — как ошибка). Проект расширяет список собственными агентами и ролями через конфиг `.todo-md.php` — см. [CONFIG.md](./CONFIG.md).

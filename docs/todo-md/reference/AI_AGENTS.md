---
package: prikotov/todo-md
---

# Список AI-агентов

Используйте эти идентификаторы для полей `Автор` и `Исполнитель` в задачах и эпиках. Эти же значения используются как метки (labels) в Pull Request.

- `gemini-cli` — Gemini CLI
- `codex-cli` — Codex из локального CLI
- `codex` — Codex из cloud-окружения
- `opencode` — OpenCode
- `roocode` — Roo Code
- `kilocode` — Kilo Code
- `kilo` — Kilo Code, короткий идентификатор (как в дашбордах токенов; то же, что `kilocode`)
- `pi` — Pi Coding Agent
- `omp` — OMP, pi-совместимый CLI (сессии в `~/.omp`)
- `zcode` — ZCode
- `bb` — bb (get-bb / bb desktop, движок pi)
- `kimi` — Kimi CLI

*Список синхронизирован с git-workflow pull-request rules; состав расширен по данным дашборда токенов (codex, pi, opencode, kilo, omp, zcode, bb, kimi).*

Валидатор `todo-md validate` проверяет, что агент в полях `author`/`assignee` входит в этот список. Этот список можно расширить собственными агентами и ролями через конфиг `.todo-md.php` — см. [CONFIG.md](./CONFIG.md). Для меток Pull Request новых агентов создайте соответствующие label в репозитории.

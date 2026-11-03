---
package: prikotov/todo-md
---

# Список LLM-моделей (Models)

Используйте эти идентификаторы для опционального поля `model` в задачах и эпиках. Поле фиксирует, какая LLM-модель выполняла работу, и используется для аналитики в дашборде (распределение done-задач по моделям).

## Формат

- Lowercase-идентификатор: `[a-z0-9][a-z0-9._-]*`. Пример: `gpt-4o`, `claude-sonnet-4`.
- Поле опциональное, заполняется вручную (автоматического определения модели нет).
- Не участвует в workflow (в отличие от `author`/`assignee`).

## OpenAI

- `gpt-5`
- `gpt-4.1`
- `gpt-4o`
- `o3`
- `o4-mini`

## Anthropic

- `claude-opus-4`
- `claude-sonnet-4`
- `claude-haiku-4`

## Google

- `gemini-2.5-pro`
- `gemini-2.5-flash`

## Meta

- `llama-4`

## Mistral

- `mistral-large`
- `mistral-small`

## Zhipu (GLM)

- `glm-4.6`
- `glm-5.3-flash`

## Others

- `deepseek-r1`
- `qwen-3`
- `kimi-k2`

## Валидация

Валидатор `todo-md validate` проверяет:

- формат (lowercase-идентификатор);
- вхождение в канонический список (см. выше) — нарушение выводится как warning, в режиме `--strict` или `strict: true` — как error;
- пустое значение допустимо всегда.

Список можно заменить или расширить через конфиг `.todo-md.php` (`models`) — см. [CONFIG.md](./CONFIG.md). Новые модели, которых нет в списке, добавляйте в конфиг своего проекта.

## Пример

```yaml
model: gpt-4o
```

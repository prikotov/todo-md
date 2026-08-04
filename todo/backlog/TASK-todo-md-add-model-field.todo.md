---
# Metadata (Метаданные)
type: feat
created: 2026-07-29
due:
started:
completed:
cancelled:
value: V2
complexity: C2
priority: P1
cost_plan:
status: backlog
depends_on:
epic:
author: Продакт (pi)
assignee: Бэкендер (pi)
model: gpt-4o
branch:
pr:
---

# TASK-todo-md-add-model-field: Добавить поле model для отслеживания LLM моделей

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Нет информации о том, какая LLM модель работала над задачей.
- Нельзя сравнить эффективность разных моделей в дашборде.
- Нет аналитики по популярности моделей среди задач.

### Варианты или путь решения (Solution Sketch)
- Добавить опциональное поле `model` в front matter задач и эпиков.
- Создать справочник MODELS.md с каноническим списком моделей.
- Добавить валидацию поля model через валидатор.
- Добавить экспорт поля model в export-jsonl.
- Добавить аналитику по моделям в дашборд (горизонтальный bar chart).

### Ожидаемый результат (Expected Result)
- Пользователь может указать модель в задаче через `model: gpt-4o`.
- Валидатор проверяет, что модель входит в канонический список (или список из конфига).
- Дашборд показывает распределение done-задач по моделям (top-12 + "остальные").

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **Job Story:** Когда я хочу проанализировать, какие модели используются чаще всего, я хочу видеть распределение задач по моделям в дашборде, чтобы оценить популярность и эффективность разных LLM.

### Цель по SMART (Goal)
*S (Specific) — Конкретно | M (Measurable) — Измеримо | A (Achievable) — Достижимо | R (Relevant) — Релевантно | T (Time-bound) — Ограниченно во времени*

Добавить поле `model` в систему todo-md: опциональное поле в front matter, валидация, экспорт, аналитика в дашборде.

## 2. Контекст и Границы (Context and Scope)
*   **Где делается:** docs/todo-md/templates/, docs/todo-md/reference/, src/TodoMd/, src/bootstrap.php
*   **Текущее поведение:** Поля author/assignee отслеживают роль и агента, но не модель.
*   **Границы (Out of Scope):** Не трогаем CLI команды (start/done/etc) — model заполняется вручную.

## 3. Требования, MoSCoW (Requirements)
*Приоритизированный список требований. Must — обязательно, Won't — явно не делаем.*
### 🔴 Обязательно (Must Have)
- [ ] Поле `model` в шаблонах task.md и epic.md после assignee.
- [ ] Справочник MODELS.md с каноническим списком моделей (OpenAI, Anthropic, Google, Meta, Mistral, Others).
- [ ] Константа Parser::MODELS с массивом идентификаторов моделей.
- [ ] Валидация поля model в Validator::validateModel (опционально, проверка по списку).
- [ ] Экспорт поля model в exportExtractRecord.
- [ ] Панель "Модели LLM" в дашборде (горизонтальный bar chart, done-задачи, top-12 + "остальные").
- [ ] Обновление CONFIG.md с секцией про model.
- [ ] Обновление AGENTS_TASK_WRITING_GUIDE.md с описанием поля model.

### 🟡 Желательно (Should Have)
- [ ] Поддержка кастомного списка моделей через `.todo-md.php` (config['models']).

### 🟢 Опционально (Could Have)
- [ ] Цветовая кодировка по провайдеру (OpenAI/Anthropic/Google/etc).
- [ ] Фильтр задач по модели в дашборде.

### ⚫ Не будем делать (Won't Have)
- [ ] Автоматическое определение модели по агенту (заполняется вручную).

## 4. План реализации (Implementation Plan)
*Пошаговый план работ. Заполняется исполнителем (агентом) перед стартом.*
1. [ ] Добавить `model: <модель LLM>` в task.md и epic.md.
2. [ ] Создать MODELS.md с каноническим списком моделей.
3. [ ] Добавить Parser::MODELS константу.
4. [ ] Добавить Validator::validateModel().
5. [ ] Вызвать validateModel в validateFrontMatter.
6. [ ] Добавить model в exportExtractRecord.
7. [ ] Добавить панель и chart для моделей в dashboardRenderHtml.
8. [ ] Обновить CONFIG.md и AGENTS_TASK_WRITING_GUIDE.md.

## 5. Критерии приёмки (Definition of Done)
*Базовые критерии готовности задачи. Можно дополнять конкретными требованиями.*
- [ ] Валидатор проверяет model (warning/strict mode).
- [ ] Дашборд показывает распределение по моделям.
- [ ] Документация обновлена (MODELS.md, CONFIG.md, AGENTS_TASK_WRITING_GUIDE.md).

## 6. Самопроверка (Verification)
*Укажите команды для самопроверки. Пример:*
```bash
php vendor/bin/todo-md validate todo/
php vendor/bin/todo-md dashboard todo/ -o /tmp/dashboard.html
```

## 7. Риски и зависимости (Risks and Dependencies)
*Потенциальные проблемы, блокеры, внешние зависимости.*
- Риск: много задач без model (старые задачи) — нормально, поле опциональное.
- Риск: новые модели не в списке — решается через config['models'].

## 8. Источники (Sources)
*Ссылки на документацию, RFC, связанные задачи.*
- [ ] [CONFIG.md](../../docs/todo-md/reference/CONFIG.md)

## 9. Комментарии (Comments)
*Дополнительная информация, примечания, важные нюансы.*

Поле model опциональное, рекомендуется для аналитики. Не участвует в workflow (в отличие от author/assignee).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
*Формат автора: Роль (агент); см. [ROLES.md](../../docs/todo-md/reference/ROLES.md), [AI_AGENTS.md](../../docs/todo-md/reference/AI_AGENTS.md), [CONFIG.md](../../docs/todo-md/reference/CONFIG.md)*
| 2026-07-29 10:00:00 (1785294000) | Продакт (pi) | Создание задачи |

---
type: feat
created: 2026-07-25
value: V2
complexity: C2
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Владелец проекта (pi)
assignee: Разработчик (pi)
branch:
pr:
status: todo
---

# TASK-todo-md-validator-dependency-check: Проверка существования зависимостей и эпиков в валидаторе

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
- Валидатор проверяет формат `depends_on` и `epic`, но не проверяет, что указанные TASK/EPIC реально существуют.
- Из-за этого задача с битой ссылкой на несуществующую зависимость проходит проверку.
- Это мешает и агентам (они не замечают свои ошибки), и людям (опечатки всплывают поздно).

### Варианты или путь решения (Solution Sketch)
- Собрать индекс ID всех `.todo.md` за один запуск валидатора и проверять `depends_on`/`epic` против него.
- Зависимость на `cancelled` — предупреждение, а не ошибка.

### Ожидаемый результат (Expected Result)
- Валидатор ловит ссылки на несуществующие задачи/эпики.
- Этот же индекс — фундамент для export/dashboard утилит.

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как владелец проекта, я хочу, чтобы валидатор ловил битые ссылки на зависимости и эпики, чтобы доска не содержала висячих связей.

### Goal (Цель по SMART)
В `bin/todo-md-validate` построить индекс ID всех найденных `.todo.md` и проверять каждое `depends_on` и `epic` на существование; зависимость на `cancelled` — warning.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `bin/todo-md-validate`.
*   **Текущее поведение:** формат `depends_on`/`epic` валидируется, существование — нет.
*   **Границы (Out of Scope):**
    *   Self-dependency и циклы — отдельная задача (позже, как warning).
    *   Перекрёстная проверка Markdown-ссылок уже есть — не трогаем.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Индекс ID всех `.todo.md` за один запуск.
- [ ] `depends_on`: каждый ID существует в индексе, иначе error.
- [ ] `epic`: ID существует и ведёт на EPIC, иначе error.
- [ ] Зависимость на `cancelled` — warning, не error.

### 🟡 Should Have (Желательно)
- [ ] Понятное сообщение: какой ID в каком файле не найден.

### 🟢 Could Have (Опционально)
- [ ] Self-dependency как warning.

### ⚫ Won't Have (Не будем делать)
- [ ] Полный поиск циклов (отдельная задача).

## 4. Implementation Plan (План реализации)
1. [ ] Собрать индекс ID (с kind) при обходе файлов.
2. [ ] Добавить `validateDependencies(file, frontMatter, index, errors, warnings)`.
3. [ ] Зависимость на `cancelled` → warning.
4. [ ] Fixture: задача с `depends_on: TASK-no-such` падает.

## 5. Definition of Done (Критерии приёмки)
- [ ] Битая `depends_on`/`epic` даёт error.
- [ ] Ссылка на `cancelled` даёт warning.
- [ ] Существующие валидные задачи проходят.

## 6. Verification (Самопроверка)
```bash
php bin/todo-md-validate .
```

## 7. Risks and Dependencies (Риски и зависимости)
- Ложные срабатывания на исторических/архивных ссылках — смягчается warning на `cancelled`.

## 8. Sources (Источники)
- [Сводный анализ 2026-07-25](../docs/research/2026-07-25-top-ideas-for-agent-and-human-ux.md) — №1.

## 9. Comments (Комментарии)
Парсер/индекс из этой задачи переиспользуется в export/dashboard утилитах (№3).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-25 | Владелец проекта (pi) | Создание задачи (№1 из сводного анализа) |

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
branch: feat/validator-dependency-check
pr: https://github.com/prikotov/todo-md/pull/14
status: done
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

## 1. Концепция и Цель (Concept and Goal)
### История (User Story)
> Как владелец проекта, я хочу, чтобы валидатор ловил битые ссылки на зависимости и эпики, чтобы доска не содержала висячих связей.

### Цель по SMART (Goal)
В `bin/todo-md-validate` построить индекс ID всех найденных `.todo.md` и проверять каждое `depends_on` и `epic` на существование; зависимость на `cancelled` — warning.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `bin/todo-md-validate`.
*   **Текущее поведение:** формат `depends_on`/`epic` валидируется, существование — нет.
*   **Границы (Out of Scope):**
    *   Self-dependency и циклы — отдельная задача (позже, как warning).
    *   Перекрёстная проверка Markdown-ссылок уже есть — не трогаем.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Индекс ID всех `.todo.md` за один запуск.
- [x] `depends_on`: каждый ID существует в индексе, иначе error.
- [x] `epic`: ID существует и ведёт на EPIC, иначе error.
- [x] Зависимость на `cancelled` — warning, не error.

### 🟡 Желательно (Should Have)
- [x] Понятное сообщение: какой ID в каком файле не найден.

### 🟢 Опционально (Could Have)
- [ ] Self-dependency как warning.

### ⚫ Не будем делать (Won't Have)
- [ ] Полный поиск циклов (отдельная задача).

## 4. План реализации (Implementation Plan)
1. [x] Собрать индекс ID (с kind) при обходе файлов.
2. [x] Добавить `validateDependencies(frontMatter, idIndex, errors, warnings)`.
3. [x] Зависимость на `cancelled` → warning.
4. [ ] Fixture: задача с `depends_on: TASK-no-such` падает (проверено black-box; постоянный suite — scope TASK-todo-md-state-commands).

## 5. Критерии приёмки (Definition of Done)
- [x] Битая `depends_on`/`epic` даёт error.
- [x] Ссылка на `cancelled` даёт warning.
- [x] Существующие валидные задачи проходят.

## 6. Самопроверка (Verification)
```bash
php bin/todo-md-validate .
```

## 7. Риски и зависимости (Risks and Dependencies)
- Ложные срабатывания на исторических/архивных ссылках — смягчается warning на `cancelled`.

## 8. Источники (Sources)
- [Сводный анализ 2026-07-25](../../docs/research/2026-07-25-top-ideas-for-agent-and-human-ux.md) — №1.

## 9. Комментарии (Comments)
Парсер/индекс из этой задачи переиспользуется в export/dashboard утилитах (№3).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-25 | Владелец проекта (pi) | Создание задачи (№1 из сводного анализа) |
| 2026-07-28 | Разработчик (pi) | Реализованы `buildIdIndex` + `validateDependencies`: индекс ID→{kind,status}, проверка существования depends_on/epic, зависимость на cancelled → warning. Мёртвая ветка kind-проверки epic удалена (формат EPIC-* + имя файла её делают недостижимой). Сценарии проверены black-box. |
| 2026-07-28 | Разработчик (pi) | PR #14 создан, status → review. |
| 2026-07-28 | Разработчик (pi) | Смерджено в PR #14, status → done, перенос в todo/done/. |

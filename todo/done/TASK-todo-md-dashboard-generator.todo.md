---
type: feat
created: 2026-07-25
value: V3
complexity: C3
priority: P2
cost_plan:
cost_fact:
depends_on: TASK-todo-md-lifecycle-dates
epic:
author: Владелец проекта (pi)
assignee: Разработчик (pi)
branch: feat/dashboard
pr: https://github.com/prikotov/todo-md/pull/16
status: done
completed: 2026-08-19 17:37:03 (1787161023)
---

# TASK-todo-md-dashboard-generator: Генератор дашбордов и диаграмм Ганта (HTML) из задач

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
- Людям для обзора нужно больше, чем канбан-папки + `ls`: агрегаты, распределения, диаграмма Ганта.
- Сейчас такого инструмента нет.

### Варианты или путь решения (Solution Sketch)
- Пайплайн `export → JSONL → dashboard`: утилита `todo-md-export-jsonl` собирает данные из `.todo.md` в JSONL; `todo-md-dashboard` строит self-contained HTML с чартами.
- Агентам CLI не нужен (они работают shell) — это инструмент для людей.

### Ожидаемый результат (Expected Result)
- Одна команда генерирует HTML-дашборд по задачам проекта: статусы, приоритеты, эпики, прогресс, Гант.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story)
> Как владелец проекта, я хочу HTML-дашборд и диаграмму Ганта по задачам, чтобы видеть состояние проекта одним взглядом.

### Цель по SMART (Goal)
Реализовать две утилиты: `bin/todo-md-export-jsonl` (`.todo.md` → JSONL на stdout) и `bin/todo-md-dashboard` (JSONL → self-contained HTML с Chart.js). Детерминированная генерация: те же файлы → тот же HTML.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `bin/todo-md-export-jsonl`, `bin/todo-md-dashboard`, `composer.json` (`bin`).
*   **Текущее поведение:** обзора нет, только `ls` по папкам.
*   **Границы (Out of Scope):**
    *   Read-side CLI для агентов (`list`/`show`) — отвергнут (shell сильнее).
    *   Live-обновление/dashboards как сервис — статический HTML.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] `todo-md-export-jsonl [target]` — рекурсивный обход `.todo.md`, JSONL на stdout (id, kind, title, status, type, priority, value, complexity, epic, depends_on, assignee, created, due/started/completed, cost_*).
- [ ] `todo-md-dashboard <input.jsonl> [-o out.html]` — self-contained HTML, данные встроены, Chart.js через CDN.
- [ ] Виджеты: распределение по статусам, приоритетам, эпикам; таблица задач.

### 🟡 Желательно (Should Have)
- [x] Диаграмма Ганта (по `due`/`started`/`completed`) — требует lifecycle-дат.
- [ ] Прогресс по `Must/Should` чек-листам — отложено (см. Comments).
- [x] Фильтр/поиск по таблице.

### 🟢 Опционально (Could Have)
- [~] Группировка по папкам (active/backlog/done/cancelled) — частично: `folder` экспортируется в JSONL, группировки в UI нет.
- [ ] Темплейт HTML выносится в отдельный файл — отложено.

### ⚫ Не будем делать (Won't Have)
- [ ] Бэкенд/сервер; live-обновление.

## 4. План реализации (Implementation Plan)
1. [x] `todo-md-export-jsonl` (переиспользует парсер валидатора).
2. [x] `todo-md-dashboard` (JSONL → HTML + Chart.js).
3. [x] Гант после добавления lifecycle-дат (зависимость `TASK-todo-md-lifecycle-dates`).
4. [x] Тест на реальном масштабе (TasK) — 481 файл: export 0.085 с, dashboard 0.042 с, headless Chrome без ошибок, все 10 графиков (вкл. оба Ганта) отрендерены.

## 5. Критерии приёмки (Definition of Done)
- [x] `export-jsonl` корректно разбирает front matter и H1 (481 запись; статусы совпадают с папками 1-в-1; title = H1 без ID-префикса).
- [x] `dashboard` открывается в браузере и показывает виджеты (headless Chrome: 10 canvas отрендерены, 0 ошибок консоли).
- [x] На масштабе TasK (~430 задач, фактически 481) работает без ошибок.

## 6. Самопроверка (Verification)
```bash
php bin/todo-md-export-jsonl . > /tmp/tasks.jsonl
php bin/todo-md-dashboard /tmp/tasks.jsonl -o /tmp/dashboard.html
```

## 7. Риски и зависимости (Risks and Dependencies)
- Гант по времени требует lifecycle-дат — зависит от `TASK-todo-md-lifecycle-dates`.
- CDN-зависимость Chart.js (офлайн не работает) — можно позже встроить локально.

## 8. Источники (Sources)
- [Сводный анализ 2026-07-25](../../docs/research/2026-07-25-top-ideas-for-agent-and-human-ux.md) — №3.

## 9. Комментарии (Comments)
`--json`/JSONL оправдан именно здесь — как слой данных генератора для людей, а не как агентская команда. MVP (экспорт + дашборд) делается сейчас; Гант — после lifecycle-дат.

**Масштабный тест (2026-08-20):** pipeline `export-jsonl → dashboard` прогнан на репозитории TasK (481 файл: 440 TASK + 41 EPIC). Оба пути вызова dashboard (каталог напрямую и через JSONL) дают идентичные встроенные данные. Отложено в будущие задачи: прогресс по Must/Should чек-листам в дашборде, группировка по папкам в UI, вынос HTML-темплейта в отдельный файл.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-25 | Владелец проекта (pi) | Создание задачи (№3); MVP в работу |
| 2026-08-20 | Разработчик (pi) | Гант реализован; масштабный тест на TasK (481 файл) пройден; Must Have и DoD закрыты |

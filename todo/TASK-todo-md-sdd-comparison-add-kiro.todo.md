---
# Metadata (Метаданные)
type: chore
created: 2026-08-05
due:
started:
completed:
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Владелец проекта (pi)
assignee: Архитектор (pi)
branch:
pr:
status: todo
---

# TASK-todo-md-sdd-comparison-add-kiro: Добавить Kiro (Amazon) в SDD-comparison research

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В существующем ресёрче SDD-comparison (TASK-todo-md-sdd-comparison-research) не хватает Kiro (Amazon) — важного SDD-инструмента.
- Без Kiro обзор неполный, а сравнение todo-md с ландшафтом SDD — неточное.
- Пользователь явно запросил добавление Kiro в scope ресёрча.

### Варианты или путь решения (Solution Sketch)
- Переоткрывать завершённую задачу не следует — это нарушает traceability и принцип "одна задача = один законченный result".
- Правильный подход: отдельная задача для расширения scope уже завершённого ресёрча.
- Задача зависит от TASK-todo-md-sdd-comparison-research (depends_on) — это явно показывает связь.

### Ожидаемый результат (Expected Result)
- Kiro (Amazon) добавлен во все секции существующего документа `docs/research/SDD-comparison.md`.
- Документ ресёрча остаётся валидным, проходит `make check`.
- В задаче-предшественнике (TASK-todo-md-sdd-comparison-research) обновлён Change History (опционально).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)
- **Зависимость:** эта задача расширяет scope завершённого ресёрча TASK-todo-md-sdd-comparison-research. Связь явно упомянута здесь, т.к. depends_on работает только для активных задач.

### Цель по SMART (Goal)
Добавить Kiro (Amazon) в существующий документ `docs/research/SDD-comparison.md` в течение 1 часа работы. Задача не включает проведение ресёрча — только расширение уже существующего обзора. Зависимость от TASK-todo-md-sdd-comparison-research явно указана в depends_on.

## 2. Контекст и Границы (Context and Scope)

*   **Где делается:** существующий документ `docs/research/SDD-comparison.md`.
*   **Текущее поведение:** документ покрывает OpenSpec, BMAD + 2–3 других инструмента.
*   **Границы (Out of Scope):**
    - Проводить ресёрч Kiro с нуля — не входит, задача только о добавлении.
    - Переписывать весь документ — только добавление секции про Kiro.
    - Обновлять README (это отдельная задача).
- **Почему отдельная задача, а не переоткрытие:** переоткрытие нарушает принцип "одна задача = один result" и traceability. Правильный подход — новая задача с явным упоминанием связи с предшественником.
## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] Добавить Kiro в секцию "Обзор инструментов" в `docs/research/SDD-comparison.md`.
- [ ] Заполнить карточку Kiro по той же структуре, что остальные инструменты: концепция / устройство / роль AI / область применения / плюсы-минусы.
- [ ] Добавить Kiro в сравнительную таблицу todo-md vs инструменты.
- [ ] Обновить блоки "сходство / отличие" если нужно.
- [ ] Документ проходит `make check` после изменений.

### 🟡 Желательно (Should Have)
- [ ] Добавить ссылку на репозиторий/документацию Kiro в секции Sources.
- [ ] Обновить Change History в TASK-todo-md-sdd-comparison-research (опционально).

### 🟢 Опционально (Could Have)
- [ ] Добавить Kiro в ментальную карту/диаграмму "ландшафт SDD-инструментов" (если есть).

### ⚫ Не будем делать (Won't Have)
- [ ] Переписывать весь документ.
- [ ] Проводить ресёрч Kiro с нуля (только добавить已知 информацию).

## 4. План реализации (Implementation Plan)

1. [ ] Найти репозиторий/документацию Kiro (Amazon) — использовать YouTube-видео из sources задачи-предшественника.
2. [ ] Прочитать существующий `docs/research/SDD-comparison.md` — понять структуру карточек инструментов.
3. [ ] Добавить секцию про Kiro в "Обзор инструментов" по существующей структуре.
4. [ ] Добавить Kiro в сравнительную таблицу.
5. [ ] Проверить валидность: `make check`.
6. [ ] (Опционально) Обновить Change History в TASK-todo-md-sdd-comparison-research.

## 5. Критерии приёмки (Definition of Done)

- [ ] Kiro добавлен в документ `docs/research/SDD-comparison.md`.
- [ ] Карточка Kiro соответствует структуре остальных инструментов.
- [ ] Kiro присутствует в сравнительной таблице.
- [ ] `make check` проходит.
- [ ] (Опционально) Change History в TASK-todo-md-sdd-comparison-research обновлён.

## 6. Самопроверка (Verification)

```bash
make check
grep -i "kiro\|amazon" docs/research/SDD-comparison.md
php bin/todo-md validate todo/TASK-todo-md-sdd-comparison-add-kiro.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)

- **Зависимость:** TASK-todo-md-sdd-comparison-research должна быть completed (это уже выполнено).
- Риск: информации про Kiro может быть мало — mitigate: использовать видео из sources.
- Риск: формат карточки Kiro может отличаться от остальных — mitigate: следовать существующей структуре.

## 8. Источники (Sources)

- Видео из задачи-предшественника: https://www.youtube.com/watch?v=JU13uM2b7WM
- Видео из задачи-предшественника: https://www.youtube.com/watch?v=EZwcBSX_Rbs
- Репозиторий/документация Kiro (Amazon) — найти из видео.

## 9. Комментарии (Comments)

- **Почему отдельная задача, а не переоткрытие:** переоткрытие нарушает принцип "одна задача = один result" и traceability. Правильный подход — новая задача с depends_on.
- **Почему type:chore, не docs:** это не создание нового документа, а расширение существующего.
- **Scope ограничен:** только добавление Kiro, не ресёрч с нуля.
- Зависимость от TASK-todo-md-sdd-comparison-research явно указана в depends_on — это делает связь очевидной.

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-05 | Владелец проекта (pi) | Создание задачи |

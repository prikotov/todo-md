---
# Metadata (Метаданные)
type: chore
created: 2026-08-05
due:
started: 2026-11-03 12:32:26 (1793709146)
completed: 2026-11-03 12:42:33 (1793709753)
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on: TASK-todo-md-sdd-comparison-research
epic:
author: Владелец проекта (pi)
assignee: Архитектор (pi)
branch: task/sdd-comparison-add-kiro
pr: https://github.com/prikotov/todo-md/pull/38
status: done
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
- **Зависимость:** эта задача расширяет scope завершённого ресёрча TASK-todo-md-sdd-comparison-research (ссылка в `depends_on`).

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
- [x] Добавить Kiro в секцию "Обзор инструментов" в `docs/research/SDD-comparison.md` (секция 2.4).
- [x] Заполнить карточку Kiro по той же структуре, что остальные инструменты: концепция / устройство / роль AI / область применения / плюсы-минусы.
- [x] Добавить Kiro в сравнительную таблицу todo-md vs инструменты (§1.1, §5).
- [x] Обновить блоки "сходство / отличие" если нужно (§0, §4, вывод §5, §10).
- [x] Документ проходит `make check` после изменений (85/85 тестов).

### 🟡 Желательно (Should Have)
- [x] Добавить ссылку на репозиторий/документацию Kiro в секции Sources (§11).
- [x] Обновить Change History в TASK-todo-md-sdd-comparison-research (строка о добавлении Kiro).

### 🟢 Опционально (Could Have)
- [ ] Добавить Kiro в ментальную карту/диаграмму "ландшафт SDD-инструментов" — ментальной карты в документе нет, добавлять нечего (N/A).

### ⚫ Не будем делать (Won't Have)
- [ ] Переписывать весь документ.
- [ ] Проводить ресёрч Kiro с нуля (только добавить已知 информацию).

## 4. План реализации (Implementation Plan)

1. [x] Найти репозиторий/документацию Kiro (Amazon) — вместо видео использована официальная документация kiro.dev (9 страниц, полнее видео).
2. [x] Прочитать существующий `docs/research/SDD-comparison.md` — понять структуру карточек инструментов.
3. [x] Добавить секцию про Kiro в "Обзор инструментов" по существующей структуре (§2.4).
4. [x] Добавить Kiro в сравнительные таблицы (§1.1, §5).
5. [x] Проверить валидность: `make check` (85/85).
6. [x] (Опционально) Обновить Change History в TASK-todo-md-sdd-comparison-research.

Материал извлечён через скилл knowledge-extraction (TasK): проект `sdd-kiro-research`, 9 source + свод результатов; ключевые цитаты верифицированы чанками.

## 5. Критерии приёмки (Definition of Done)

- [x] Kiro добавлен в документ `docs/research/SDD-comparison.md`.
- [x] Карточка Kiro соответствует структуре остальных инструментов (§2.4: определение-цитата, позиционирование, таблица фаз, ключевые тезисы).
- [x] Kiro присутствует в сравнительных таблицах (§1.1, §5).
- [x] `make check` проходит (85/85 тестов).
- [x] (Опционально) Change History в TASK-todo-md-sdd-comparison-research обновлён.

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

- Официальная документация Kiro (Amazon): `kiro.dev` — `/docs/specs/`, `/docs/specs/feature-specs/`, `/docs/specs/analyze-requirements/`, `/docs/specs/plan/`, `/docs/specs/correctness/`, `/docs/specs/best-practices/`, `/docs/steering/`, `/docs/hooks/`, лендинг
- (Извлечение через скилл knowledge-extraction: проект `sdd-kiro-research`, 9 source ready + свод результатов; вместо YouTube-видео предшественника — первоисточник-документация)

## 9. Комментарии (Comments)

- **Почему отдельная задача, а не переоткрытие:** переоткрытие нарушает принцип "одна задача = один result" и traceability. Правильный подход — новая задача с depends_on.
- **Почему type:chore, не docs:** это не создание нового документа, а расширение существующего.
- **Scope ограничен:** только добавление Kiro, не ресёрч с нуля.
- Зависимость от TASK-todo-md-sdd-comparison-research явно указана в depends_on — это делает связь очевидной.

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-05 | Владелец проекта (pi) | Создание задачи |
| 2026-08-20 | Владелец проекта (pi) | Восстановлен `depends_on` на TASK-todo-md-sdd-comparison-research: валидатор резолвит зависимости по всему дереву задач (v0.0.11) |
| 2026-11-03 19:38:07 (1793709487) | Архитектор (pi) | Ресёрч Kiro через knowledge-extraction (kiro.dev, 9 страниц): карточка §2.4, таблицы §1.1/§5, выводы; MoSCoW и план закрыты |

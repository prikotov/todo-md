---
type: fix
created: 2026-11-24 02:44:06 (1795488246)
due: 
started: 2026-11-24 03:38:55 (1795491535)
completed: 2026-11-24 03:41:38 (1795491698)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид (pi@glm-5.4~high)
assignee: Разработчик (pi@glm-5.3-flash~max)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/cli-assignee-and-set-fixes
pr: https://github.com/prikotov/todo-md/pull/46
status: done
---

# TASK-cli-assignee-and-set-fixes: Переходы с --assignee игнорируют исполнителя, а set с тем же значением падает

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `done`, `review`, `cancel`, `backlog` принимают `--assignee`, `--model`, `--thinking` согласно справке, но не применяют их. Воспроизведено 2026-11-24 на `master` во временном проекте:
    *   задача без исполнителя: `php bin/todo-md done TASK-repro --assignee="Разработчик (pi@glm-5.4~high)" --model=glm-5.4 --thinking=high` → `completed TASK-repro → done (1 warning(s))`, исполнитель остался пустым;
    *   задача с исполнителем `Разработчик (pi@glm-5.4~high)`: `php bin/todo-md review TASK-repro --assignee="Аналитик (pi@other~low)"` → статус `review`, исполнитель прежний.
- `set` поля, значение которого не меняется, завершается ошибкой: повторный `php bin/todo-md set TASK-repro "pr=https://example.invalid/1"` → `Error: could not set field (no front matter?): pr`, код выхода 1.
- Последствия: агент вписывает исполнителя руками или оставляет его пустым в нестрогом проекте; повторный `set` в скриптах обрывается, а сообщение указывает на несуществующую проблему с front matter.

### Варианты или путь решения (Solution Sketch)
- `cli_transition`: передавать `--assignee`, `--model`, `--thinking` в `Board::transition` для всех переходов — метод уже умеет записывать исполнителя.
- `Board::setField` / `setFields`: значение, которое не меняет файл, считать успехом без записи и сообщать «не изменилось».

### Ожидаемый результат (Expected Result)
- Переход с `--assignee` записывает исполнителя; без `--assignee` поведение прежнее. Повторный `set` того же значения завершается успешно и не трогает файл.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как агент, я хочу, чтобы `--assignee` действовал в любом переходе, а повторный `set` не падал, чтобы команды можно было запускать скриптом без ручных правок файла.

### Цель по SMART (Goal)
- Оба дефекта исправлены и покрыты регрессионными тестами, справка соответствует поведению, `CHANGELOG.md` обновлён, `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/bootstrap.php` (`cli_transition`, справка переходов); `src/TodoMd/Board.php` (`setField`, `setFields`); `tests/`.
*   **Текущее поведение:** `src/bootstrap.php:103` — опции передаются в `Board::transition` только при `$verb === 'start'`. `src/TodoMd/Board.php:693` и `:638` — «could not set field (no front matter?)», когда содержимое файла не изменилось.
*   **Границы (Out of Scope):**
    *   Смена семантики статусов и правила «исполнитель обязателен».
    *   Множественные присваивания в `set` — сделаны в TASK-todo-md-cli-fixes.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] `review`, `done`, `cancel`, `backlog` с `--assignee` записывают исполнителя до перехода; `--model` / `--thinking` применяются к нему так же, как в `start`. Без `--assignee` исполнитель не меняется.
- [ ] При отказе записи исполнителя переход не выполняется.
- [ ] `set <ID> field=<текущее значение>` — успех, файл не меняется, в выводе отмечено, что значение не изменилось.
- [ ] Сообщение об отсутствии поля в front matter остаётся только для действительно отсутствующего поля.
- [ ] Регрессионные тесты: переход с `--assignee` при пустом и при другом исполнителе; повторный `set`.
- [ ] `CHANGELOG.md`; `make check` зелёный.

### ⚫ Won't Have (Не будем делать)
- Изменение правила «исполнитель обязателен» для статусов.

## 4. План реализации (Implementation Plan)
1. [ ] Тесты на оба случая (red).
2. [ ] `cli_transition`: передавать опции во все переходы.
3. [ ] `setField` / `setFields`: неизменённое значение — успех.
4. [ ] `CHANGELOG.md`, `make check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] `php bin/todo-md done TASK-t --assignee="Разработчик (pi)" --model=glm-x --thinking=high` на задаче без исполнителя даёт `assignee: Разработчик (pi@glm-x~high)` и статус `done`.
- [ ] `php bin/todo-md review TASK-t --assignee="Аналитик (pi)"` меняет исполнителя.
- [ ] Дважды `php bin/todo-md set TASK-t "pr=https://example.invalid/1"` — оба вызова с кодом 0, после второго файл не меняется.
- [ ] Все пункты Must Have закрыты тестом; `make check` зелёный.

## 6. Самопроверка (Verification)
```bash
make check
php bin/todo-md validate todo --strict
```

## 7. Риски и зависимости (Risks and Dependencies)
- Поведение `done --assignee` меняется с «игнорируется» на «применяется». Проектов, полагающихся на игнорирование, не выявлено; исправление входит в патч-релиз.

## 8. Источники (Sources)
- Воспроизведение обоих случаев на `master` 2026-11-24 — команды и вывод в разделе 0.
- [TASK-todo-md-cli-fixes](TASK-todo-md-cli-fixes.todo.md) — прецедент: молчаливое игнорирование аргументов CLI заменяется явным поведением.

## 9. Комментарии (Comments)
- Задача поставлена из проекта-потребителя `stocks2`: из-за первого дефекта исполнитель в его задачах вписывался руками.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-24 02:44:06 (1795488246) | Тимлид (pi@glm-5.4~high) | Создание задачи |
| 2026-11-24 10:39:15 (1795491555) | Разработчик (pi@glm-5.3-flash~max) | Взята в работу — ветка task/cli-assignee-and-set-fixes, тесты и исправление cli_transition / setField / setFields |
| 2026-11-24 10:41:38 (1795491698) | Разработчик (pi@glm-5.3-flash~max) | Реализовано и закрыто — PR #46 (make check зелёный, 123 теста) |

# Статусы задач и эпиков

Список допустимых статусов для использования в поле `Статус`.

## Активные задачи (папка `todo/`)
Задачи, которые находятся в активной работе или готовы к ней.

- **todo**: Задача запланирована и готова к выполнению.
- **in_progress**: Активная работа над задачей.
- **paused**: Работа приостановлена (смена приоритетов, не блокировка).
- **blocked**: Работа остановлена из-за внешних факторов/зависимостей.
- **review**: Результат на проверке (Code Review).

## Бэклог (папка `todo/backlog/`)
Задачи, отложенные на будущее или требующие уточнения.

- **backlog**: Задача в бэклоге.

## Завершенные (папка `todo/done/`)
Задачи, работа над которыми завершена.

- **done**: Задача полностью выполнена и принята.

## Отмененные (папка `todo/cancelled/`)
Задачи, работа над которыми отменена.

- **cancelled**: Задача отменена.

## Workflow движения задач
```mermaid
stateDiagram-v2
    [*] --> backlog: Идея / Черновик
    backlog --> todo: Готова к работе (DoR)
    todo --> in_progress: В работе
    in_progress --> review: Готово (PR создан)
    review --> done: Принято (Merged)
    
    in_progress --> paused: Пауза
    paused --> in_progress: Возобновление
    
    in_progress --> blocked: Блокировка
    blocked --> in_progress: Разблокировка
    
    backlog --> cancelled: Отмена
    todo --> cancelled: Отмена
    in_progress --> cancelled: Отмена
    paused --> cancelled: Отмена
    blocked --> cancelled: Отмена
    review --> cancelled: Отмена
```

### Основные переходы
1.  **Создание:** Задача создается в `todo/backlog/` (статус `backlog`) или сразу в `todo/` (статус `todo`), если готова к работе.
2.  **В работу:** Из `backlog` перемещается в `todo/` и меняет статус на `todo`.
3.  **Выполнение:** При начале работы статус меняется на `in_progress`.
4.  **Приостановка/Блокировка:** При возникновении проблем статус меняется на `paused` или `blocked`. Файл остается в `todo/`.
5.  **Завершение:** После Code Review и Merge статус меняется на `done`, файл перемещается в `todo/done/`.
6.  **Отмена:** Если задача неактуальна, статус меняется на `cancelled`, файл перемещается в `todo/cancelled/`.

### CLI-команды для переходов

Смена статуса одной командой — атомарно правит `status`, переносит файл, чинит ссылки и валидирует:

```bash
php vendor/bin/todo-md create  TASK-name --type=feat --title="..."  # создание (status: todo)
php vendor/bin/todo-md start   TASK-name   # → in_progress
php vendor/bin/todo-md review  TASK-name   # → review
php vendor/bin/todo-md done    TASK-name   # → done (перенос в done/)
php vendor/bin/todo-md cancel  TASK-name   # → cancelled (перенос в cancelled/)
php vendor/bin/todo-md backlog TASK-name   # → backlog (перенос в backlog/)
```

Команды `start`, `done`, `cancel` автоматически проставляют lifecycle-даты (`started`, `completed`, `cancelled`).

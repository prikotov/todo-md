# Changelog

## [0.0.8] — 2026-07-31

### Added
- HTML dashboard (`todo-md dashboard`) с визуализацией доски
- JSONL export (`todo-md export-jsonl`) для машинной обработки
- Валидация ссылок `depends_on` и `epic` — проверка существования целевых задач
- Lifecycle date fields: `due`, `started`, `completed`, `cancelled`
- Валидация формата datetime в Change History согласно DATES.md
- Кросс-проверка локального времени и Unix timestamp
- Команды атомарных переходов: `start`, `review`, `done`, `cancel`, `backlog`
- Валидация полей `author` и `assignee`
- Сводный анализ идей и аудит-роадмап (research)

### Changed
- Формат lifecycle-дат: локальное время + UTC timestamp
- Уточнена логика группировки эпиков в описании доски

### Fixed
- Закрыт незакрытый bash code block в документации
- Убран лишний пустой перевод строки после изображений в заголовке
- Убрана битая ссылка на несуществующий MODELS.md

## [0.0.7] — 2026-07-22
- Первый публичный релиз

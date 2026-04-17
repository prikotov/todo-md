# Глоссарий терминов и аббревиатур

В этом документе собраны краткие описания методологий и понятий, используемых в [AGENTS_TASK_WRITING_GUIDE.md](../AGENTS_TASK_WRITING_GUIDE.md), со ссылками на авторитетные источники.

---

## Методологии формулирования

### SMART
Критерии качества постановки цели.
- **S (Specific)**: Конкретная.
- **M (Measurable)**: Измеримая.
- **A (Achievable)**: Достижимая.
- **R (Relevant)**: Релевантная (значимая).
- **T (Time-bound)**: Ограниченная по времени.

🔗 **Подробнее:** [Wikipedia: SMART criteria](https://ru.wikipedia.org/wiki/SMART)

### INVEST
Чек-лист для проверки качества User Story (и задач).
- **I (Independent)**: Независимая.
- **N (Negotiable)**: Обсуждаемая.
- **V (Valuable)**: Ценная.
- **E (Estimable)**: Оцениваемая.
- **S (Small)**: Компактная.
- **T (Testable)**: Тестируемая.

🔗 **Подробнее:** [Bill Wake: INVEST in Good Stories, and SMART Tasks](https://xp123.com/articles/invest-in-good-stories-and-smart-tasks/) (Первоисточник)

### MoSCoW
Метод приоритизации требований (управление scope).
- **Must**: Обязательно (иначе провал).
- **Should**: Следует сделать (если возможно).
- **Could**: Можно сделать (если останется время).
- **Won't**: Не будем делать в этот раз.

🔗 **Подробнее:** [Agile Business Consortium: MoSCoW Prioritisation](https://www.agilebusiness.org/moscow-prioritisation.html)

---

## Форматы описания требований

### User Story (Пользовательская история)
Описание требования на естественном языке с фокусом на ценность для пользователя.
Формула: *Как <роль>, я хочу <действие>, чтобы <цель>.*

🔗 **Подробнее:** [Mountain Goat Software: User Stories](https://www.mountaingoatsoftware.com/agile/user-stories)

### Job Story (Ситуационная история)
Альтернатива User Story, фокусирующаяся на контексте (триггере) и мотивации, а не на роли.
Формула: *Когда <ситуация>, я хочу <действие>, чтобы <результат>.*

🔗 **Подробнее:** [Intercom: Replacing The User Story With The Job Story](https://www.intercom.com/blog/replacing-the-user-story-with-the-job-story/)

---

## Процессы и критерии

### Definition of Ready (DoR)
"Критерии готовности к разработке". Список условий, которые должны быть выполнены, чтобы задачу можно было взять в работу (например: требования понятны, зависимости отсутствуют).

🔗 **Подробнее:** [Scrum Inc: Definition of Ready](https://www.scruminc.com/definition-of-ready/)

### Definition of Done (DoD)
"Критерии завершенности". Список условий, которые должны быть выполнены, чтобы задача считалась полностью сделанной (например: тесты прошли, код-ревью пройдено, документация обновлена).

🔗 **Подробнее:** [Scrum.org: Definition of Done](https://www.scrum.org/resources/blog/definition-done-what-does-it-really-mean)

---

## Типы работ

### Задача (Task)
Минимальная атомарная единица работы, описывающая конкретное изменение в системе. Задача должна соответствовать критериям [SMART](#smart) и [INVEST](#invest).
- **Файл**: `<ID>.todo.md`
- **Объем**: Ориентир — до 600 строк диффа.

### Эпик (Epic)
Крупная единица работы, которая объединяет набор связанных задач в одну общую цель. Эпик слишком велик, чтобы быть выполненным в рамках одной задачи.
- **Файл**: `EPIC-*.todo.md`
🔗 **Подробнее**: [Atlassian: Epics](https://www.atlassian.com/agile/project-management/epics)

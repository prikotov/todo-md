# Ресёрч — обзор SDD через три системы и позиция todo-md

> Обзор Spec-Driven Development (далее — SDD), выведенный из трёх зрелых систем
> (**BMAD Method**, **GitHub Spec Kit**, **OpenSpec**), и сравнение с подходом
> **todo-md** / **task-agents-playbook**.
>
> Цель: понять, что такое SDD, где находится todo-md относительно него и стоит ли
> заимствовать практики.

---

## 0. Executive Summary (Краткий вывод)

1. **SDD — это методология, в которой спецификация (specification) — отдельный
   от кода артефакт, описывающий ЧТО (intent), а не КАК (implementation), и
   являющийся контрактом / источником истины (source of truth) для реализации.**
   Все три системы сходятся в этом ядре, но по-разному решают, *что именно*
   специфицируется и *как долго* спецификация живёт.

2. **Ключевое различение, без которого сравнение бессмысленно: два уровня
   спецификации.**
   - **System Spec** — как система работает *сейчас* (текущее поведение).
   - **Change Spec** — что *изменить* в следующей итерации.

3. **SDD-инструменты заводят отдельный артефакт на *обоих* уровнях**
   (`specs/` + change-папки). **todo-md — только на уровне Change Spec**
   (задача = спецификация), а на уровне System Spec сознательно считает
   спецификацией **сам код** («code as spec»).

4. **Поэтому todo-md — это не «SDD-инструмент» и не «не-SDD», а альтернатива,
   которая конвергирует с SDD на уровне изменений (change spec) и расходится
   на уровне поведения системы (system spec).** Автор методологии называет это
   **task-driven development** («таска = spec»).

5. **Структурно todo-md / task-agents-playbook близок к BMAD**, а не к OpenSpec
   или spec-kit: роли, durable context (AGENTS.md как конституция), delivery loop
   с review и ретроспективой, разделение WHAT/WHY (задача) и HOW (конвенции).

---

## 1. Что такое Spec-Driven Development (определение)

### 1.1 Синтез из трёх систем

SDD — методология разработки, в которой **спецификация существует как отдельный
артефакт, описывающий намерение (что должно быть), а не реализацию (как оно
сделано), и является контрактом, направляющим и/или генерирующим код.**

Это ядро видно во всех трёх проанализированных системах, несмотря на разницу
в деталях:

| Признак | BMAD | GitHub Spec Kit | OpenSpec |
|---------|------|-----------------|----------|
| Отдельный артефакт спецификации | `SPEC.md` | `spec.md` (+`plan.md`, `tasks.md`) | `specs/*.md` |
| Описывает ЧТО, не КАК | «locks the WHAT before the HOW» | «the *what* before the *how*» | поведение системы (requirements) |
| Создаётся до реализации | Phase 2 Planning → Phase 3 Solutioning → Build | specify → plan → implement (до кода) | propose → specs → tasks → apply |
| Является контрактом / SoT | «canonical machine contract» | source of truth артефактов | «source of truth — how system currently behaves» |

### 1.2 Признаки SDD (согласованные)

1. **Separation of concerns (разделение):** спецификация отделена от кода и
   живёт в собственном артефакте (`specs/`, `spec.md`, `SPEC.md`).
2. **Intent-first (намерение прежде):** спецификация фиксирует ЧТО и ПОЧЕМУ
   (intent), реализация — КАК (how). См. ниже про разделение WHAT/HOW.
3. **Spec-before-code (спецификация прежде кода):** спецификация создаётся
   *до* реализации и направляет её (или генерирует).
4. **Contract / source of truth (контракт):** спецификация — источник истины,
   с которым сверяется реализация; рассинхрон (drift) считается проблемой.
5. **AI-agent-friendly (читаемо машиной):** спецификация структурно оформлена
   так, чтобы AI-агент мог её интерпретировать и действовать по ней.

### 1.3 Вариация: что специфицируется и как долго живёт

Здесь системы расходятся — и это важно для позиционирования todo-md.

**Что специфицируется:**
- **OpenSpec:** текущее поведение системы (`specs/`) + предлагаемые изменения
  (`changes/` с delta specs).
- **GitHub Spec Kit:** требования/намерение к фиче (`spec.md`); поведение системы
  явно НЕ агрегируется в единую «спецификацию системы» — это решает команда
  через модель персистентности (см. §1.4).
- **BMAD:** `SPEC.md` как «machine contract» пяти полей (Why, Capabilities,
  Constraints, Non-goals, Success signal) на уровне продукта/эпика; плюс
  «solutioning» (HOW) отдельной фазой.

**Как долго живёт спецификация (временна́я модель):**

GitHub Spec Kit явно опирается на таксономию Мартин Фаулера (Martin Fowler) из
трёх уровней:

- **Spec-first** — спецификацию пишут до кода, а потом *выбрасывают* (scaffolding).
- **Spec-anchored** — спецификацию сохраняют после реализации и используют для
  будущих изменений.
- **Spec-as-source** — спецификация = единственный человеко-редактируемый
  источник, реализации *регенерируются* из неё.

### 1.4 Модели эволюции артефактов (Spec Kit)

Spec Kit отдельно задаёт вопрос: что происходит с артефактами (`spec.md`,
`plan.md`, `tasks.md`), когда требования меняются. Три модели:

- **Flow-Back** — все артефакты и код влияют друг на друга; правки начинаются
  где угодно, команда вручную *сводит* (reconcile) их. Риск: silent divergence
  (незаметный рассинхрон).
- **Flow-Forward** — завершённые артефакты *иммутабельны*; новое требование →
  новая папка фичи. Аудит и traceability, но дублирование.
- **Living Spec** — `spec.md` = контракт, `plan.md`/`tasks.md` = производные;
  сначала меняют spec, потом перегенерируют производные.

> Эта таксономия понадобится в §6, чтобы точно позиционировать todo-md.

---

## 2. Три системы SDD: определения и ключевые формулировки

### 2.1 GitHub Spec Kit (`github/spec-kit`)

**Определение (из `docs/concepts/sdd.md`):**

> «Spec-Driven Development **flips the script** on traditional software
> development. For decades, code has been king — specifications were just
> scaffolding we built and discarded once the "real work" of coding began.
> Spec-Driven Development changes this: **specifications become executable**,
> directly generating working implementations rather than just guiding them.»

Это — самый радикальный манифест из трёх: код как источник истины («code has
been king») **объявляется проблемой**, а спецификации должны стать *исполняемыми*
(executable) и *порождать* реализацию, а не просто направлять её.

**Core Philosophy (4 принципа):**
- **Intent-driven development** — спецификации определяют «what» before «how».
- **Rich specification creation** — через guardrails (ограничения) и
  организационные принципы.
- **Multi-step refinement** — многошаговое уточнение, а не one-shot генерация
  из промпта.
- **Heavy reliance** на продвинутые AI-модели для интерпретации спецификации.

**Где спецификация:** артефакты фичи (`spec.md`, `plan.md`, `tasks.md`) в
отдельной директории. Поведение системы в целом не агрегируется в один файл —
команда выбирает модель эволюции (flow-back / flow-forward / living, см. §1.4).

**Что делает инструмент:** CLI `specify` инициализирует проект, генерирует
артефакты фичи, поддерживает brownfield-эволюцию. Фазы использования:
0-to-1 (greenfield), Creative Exploration (параллельные реализации),
Iterative Enhancement (brownfield).

### 2.2 BMAD Method (`bmad-code-org/BMAD-METHOD`)

**Определение (из README):**

> «**Agile Ai Driven Development** — turn an idea or change request into working
> software without giving up the thinking.»

> «Decisions stay explicit, context carries forward, and the process sizes
> itself to the work. Small changes go straight to build. Complex work gets the
> depth it needs.»

BMAD — единственный из трёх, кто прямо называет себя *agile* и явно допускает
**right-sized process** (масштабирование процесса под задачу): маленькое
изменение идёт сразу в build, сложное получает полную глубину планирования.

**Структура метода — 4 фазы (delivery loop Clarify → Plan → Build → Verify → Learn):**

| Фаза | Вопрос | Артефакты |
|------|--------|-----------|
| **1. Analysis** (optional) | Стоит ли делать? | `brief.md`, `prfaq.md`, research reports |
| **2. Planning** | WHAT и WHY? | `prd.md` (FRs/NFRs), UX (`DESIGN.md`/`EXPERIENCE.md`) |
| **3. Solutioning** | HOW? Какие единицы работы? | Architecture + Epics/Stories |
| **4. Build / Verify / Learn** | Реализация, проверка, ретро | код, отчёты, ретроспектива |

**Где спецификация:** `SPEC.md` — «canonical machine contract», создаваемый
командой `bmad-spec`:
> «Distill any intent input ... into a succinct SPEC.md contract + companions
> — **locks the WHAT before the HOW**.»
> «Five-field kernel: Why, Capabilities, Constraints, Non-goals, Success signal
> ... validated so every load-bearing source claim is preserved.»

**Ключевая идея Solutioning:**
> «Make technical decisions explicit and documented so all agents implement
> consistently.» (чтобы агенты не разошлись: один через REST, другой через GraphQL).

**Что ценно:** BMAD не ультра-радикален, как spec-kit. Он не требует
«исполняемых спецификаций» — он требует *явных, документированных решений* и
*durable context* (продуктовые и технические решения сохраняются и «несутся
вперёд» вместо пересказа в каждом чате).

### 2.3 OpenSpec (`Fission-AI/OpenSpec`)

**Определение (через TasK, исходные доки):**

> «Specs are the source of truth — they describe **how your system currently
> behaves**.»
> «Delta specs merge into main specs» (при архивации изменения).

OpenSpec — самый «классический» SDD из трёх: спецификация поведения системы
существует *всегда* и *обновляется*, а изменения живут отдельными папками
(`changes/`), которые при завершении *сливаются* (merge) в основную спецификацию.

**Workflow изменения:** Explore → Propose/New → Proposal → Specs (delta) →
Design → Tasks → Apply → Archive.

**Формат спецификации:** `### Requirement:` + сценарии `WHEN ... THEN ...`.

**Два ценных тезиса:**
- Архив изменений = «decision history» (история решений: почему сделано так).
- Артефакты — **«enablers, not gates»** (помогают, а не блокируют): гибкость,
  формальность не ради формальности.

---

## 3. Контекст: todo-md как часть task-agents-playbook

**todo-md нельзя оценивать изолированно** — это один из пакетов методологии
**task-agents-playbook** (остальные: `coding-standard`, `git-workflow`,
`task-orchestrator` + skeleton проекта). Поэтому корректно сравнивать с SDD
именно *методологию*, а todo-md — как её реализацию уровня изменений.

### 3.1 Что говорит автор методологии (из README playbook)

> «Spec-driven строится вокруг отдельного артефакта спецификации… В task-driven
> спецификация «упакована» прямо в задачу: **таска = spec**.»

Автор прямо противопоставляет свой подход SDD и называет его
**task-driven development** (разработка, управляемая задачами).

### 3.2 AGENTS.md как «конституция»

`AGENTS.md` — обязательные правила для AI-агента в проекте:
> «Приоритет: правила из AGENTS.md выше любых инструкций пользователя при
> конфликте.»

Это и есть **durable context** методологии (по терминологии BMAD): стабильный
набор правил, который «несётся вперёд» и не пересказывается в каждом запросе.
Аналог у BMAD — связка `brief.md` + `prd.md` + `SPEC.md`; у spec-kit — guardrails.

### 3.3 Роли

playbook задаёт **15 ролей** (Продакт, Аналитик, Архитектор, Лид, Бэкендер,
UI/UX, Фронтендер, Девопс, 3 Ревьювера, 2 Тестировщика, Технический писатель,
Копирайтер). Агент *загружает роль* перед выполнением запроса.

Это **прямая аналогия** BMAD с его «specialized perspectives» (product,
architecture, UX, dev, testing как named agents) — см. §5.3.

### 3.4 Рефлексия перед задачей

Перед выполнением запроса агент оценивает сложность / контекст / риск по шкале
0–10 и помечает запрос «проблемным», если любой порог превышен. Это встроенная
защита от авто-режима, аналогичная BMAD «without giving up the thinking».

### 3.5 todo-md как пакет

todo-md — file-based kanban: задачи в markdown с типизированным YAML front matter.
Команды: `init`, `create`, `start`, `review`, `done`, `cancel`, `backlog`,
`set`, `validate`, `export-jsonl`, `dashboard`.

Формализация задачи: enum-поля (`TYPES`, `STATUSES`, `VALUES`, `COMPLEXITY`,
`PRIORITIES`, `COST`, `AI_AGENTS`), фреймворки (User Story / Job Story, SMART,
MoSCoW, Definition of Done), структурный валидатор.

Traceability изменений: задача ↔ ветка ↔ PR ↔ коммиты; статусы, даты,
`depends_on`, эпик, git-история.

---

## 4. Два уровня спецификации: System Spec vs Change Spec

Это **центральный концептуальный ход** ресёрча. Без него сравнение todo-md с SDD
превращается в разговор о разном.

| Уровень | Вопрос | SDD-инструменты | todo-md |
|---------|--------|-----------------|---------|
| **System Spec** | Как система работает *сейчас*? | Отдельный артефакт: `specs/` (OpenSpec), `SPEC.md` (BMAD), living-модель (spec-kit) | **Сам код** (code as spec) — отдельного артефакта нет |
| **Change Spec** | Что *изменить*? | Change-папка с delta specs (OpenSpec), `changes/` (BMAD), feature-директория `spec.md` (spec-kit) | **Задача** (task = spec): цель, scope/out-of-scope, DoD, MoSCoW, User Story |

### 4.1 На уровне Change Spec — согласие

Все три SDD-системы и todo-md имеют спецификацию изменения, создаваемую *до*
реализации: WHAT (intent) прежде HOW. Разница лишь в носителе:
- OpenSpec — `changes/<id>/` с proposal/design/delta specs/tasks.
- BMAD — эпик/история + `SPEC.md`-contract для эпика.
- spec-kit — `spec.md` + `tasks.md` фичи.
- **todo-md — задача** с front matter и секциями (goal/scope/DoD).

### 4.2 На уровне System Spec — фундаментальное расхождение

Здесь пути расходятся:
- **SDD-инструменты** поддерживают отдельный артефакт текущего поведения системы
  и считают рассинхрон с кодом *проблемой*, требующей reconcile / merge /
  regeneration.
- **todo-md сознательно отказывается** от такого артефакта: спецификация текущего
  поведения системы — это **сам код**. Это и есть тезис **«code as spec»**.

---

## 5. Сравнение трёх систем между собой

| Критерий | GitHub Spec Kit | BMAD Method | OpenSpec |
|----------|-----------------|-------------|----------|
| Самоопределение | «specifications become **executable**» | «**Agile** Ai Driven Development» | «specs are the **source of truth**» |
| Отношение к «code as king» | **Критикует** («flips the script») | Не критикует, дополняет явными решениями | Не критикует, спецификация отдельно |
| Что специфицирует | намерение фичи (intent) | WHAT-контракт + HOW-solutioning | текущее поведение системы |
| Временна́я модель (Fowler) | выбор команды (first/anchored/as-source) | right-sized (зависит от размера работы) | spec-as-source (строго: merge в main specs) |
| Роли / перспективы | Bundles (role-based setups) | named agents (PM, Architect, Dev, QA, ...) | роли слабо выражены |
| Durable context | guardrails | brief + PRD + SPEC несутся вперёд | архив = decision history |
| Ригидность | гибкая (модели на выбор) | right-sized (skip для мелочи) | «enablers, not gates» |
| Радикальность | высокая (исполняемые спеки) | средняя (agile-обёртка) | средняя (классический SDD) |

**Вывод по трём:** спектр идёт от радикального «спецификации генерируют код»
(spec-kit) через pragmatic-agile «явные решения, несущиеся вперёд» (BMAD) к
классическому «спецификация поведения = источник истины» (OpenSpec).

---

## 6. Сравнение todo-md с SDD

### 6.1 Фундаментальная оппозиция: «code as spec» vs «code has been king»

Это самый показательный контраст во всём ресёрче.

**GitHub Spec Kit** открывает свой манифест прямой критикой:
> «For decades, **code has been king** — specifications were just scaffolding
> we built and discarded...»

То есть «код как источник истины» — это то, с чем SDD (в лице spec-kit)
**борется**.

**todo-md / playbook** принимает ровно противоположную позицию на уровне
System Spec: код и есть спецификация текущего поведения. «Code has been king» —
это не баг, а осознанный выбор.

| | spec-kit | todo-md |
|---|----------|---------|
| Код как источник истины (System Spec) | проблема → исправить через `specs/` | **принято** (code as spec) |
| Спецификация изменений (Change Spec) | `spec.md`/`tasks.md` фичи | задача (task = spec) |

Это не делает todo-md «хуже» или «лучше» — это два разных ответа на вопрос
«где живёт спецификация текущего поведения системы».

### 6.2 Сводная таблица: todo-md vs SDD-ядро

| Признак SDD (из §1.2) | SDD-инструменты | todo-md |
|-----------------------|-----------------|---------|
| Отдельный артефакт спецификации | Да (оба уровня) | Частично: **только Change Spec** (задача); System Spec = код |
| Описывает ЧТО, не КАК | Да | Да (задача = WHAT/WHY; КАК — в AGENTS.md/конвенциях) |
| Создаётся до реализации | Да | Да (задача создаётся до старта) |
| Контракт / source of truth | Да | Да, но контракт *изменения*, а не поведения системы |
| AI-agent-friendly | Да | Да (front matter, валидатор, роли) |

todo-md соответствует **4 из 5** признакам SDD на уровне изменений и расходится
только в первом (отсутствие отдельного system-spec артефакта).

### 6.3 Структурное сходство todo-md/playbook с BMAD

| Аспект | BMAD | task-agents-playbook |
|--------|------|----------------------|
| Роли / перспективы | named agents: PM, Architect, Dev, QA, UX... | 15 ролей (загружаются агентом) |
| Durable context | brief + PRD + SPEC несутся вперёд | AGENTS.md = «конституция» (правила выше инструкций) |
| Delivery loop | Clarify → Plan → Build → Verify → **Learn** | постановка → реализация → самопроверка → review → PR → **ретроспектива** |
| WHAT/HOW разделение | Planning (WHAT) / Solutioning (HOW) | задача (WHAT/WHY) / конвенции+coding-standard (HOW) |
| Right-sized / защита от авто-режима | «without giving up the thinking» | рефлексия (сложность/контекст/риск) перед задачей |

**Ключевая разница:** BMAD хранит WHAT в отдельном `SPEC.md`-контракте; playbook
вкладывает WHAT прямо в задачу («таска = spec») и держит HOW в `AGENTS.md` /
`coding-standard`. То есть playbook = «BMAD-подобная структура, но task-driven
носитель вместо spec-артефакта».

### 6.4 Traceability

| Тип traceability | SDD-инструменты | todo-md |
|------------------|-----------------|---------|
| **Требований (requirement traceability)** | Да: requirement → delta spec → реализация → архив | Нет (нет отдельной спеки требований) |
| **Изменений (change traceability)** | Частично (через changes/archive) | Да: задача ↔ ветка ↔ PR ↔ коммиты ↔ статусы ↔ даты ↔ depends_on/epic ↔ git |

Оба имеют traceability, но *разного типа*. todo-md силён в change traceability;
SDD — в requirement traceability.

---

## 7. Позиционирование через таксономию Fowler и модели Spec Kit

### 7.1 Уровень System Spec

В таксономии Фаулера (spec-first / spec-anchored / spec-as-source) для System
Spec **ни один уровень не применим к todo-md напрямую** — потому что у todo-md
просто нет отдельного артефакта текущего поведения. todo-md находится *вне* этой
оси: спецификация поведения = код.

Это и есть точная формулировка позиции: **todo-md — это code-as-spec для уровня
поведения системы**, что сознательно выводит его за рамки SDD-спектра именно на
этом уровне.

### 7.2 Уровень Change Spec

Здесь todo-md уверенно ложится в таксономию:
- **Spec-anchored** (Фаулер): задача пишется до реализации и *сохраняется* как
  артефакт изменения, используется для верификации соответствия (DoD, review,
  тесты) и не выбрасывается (spec-first). todo-md хранит задачи в `todo/`,
  `todo/done/` — задача живёт и после завершения.

### 7.3 Модель эволюции артефактов (Spec Kit)

Для Change Spec todo-md ближе всего к **Flow-Forward** по сути: завершённая
задача иммутабельна (переходит в `todo/done/`), статус финализируется. Новое
требование = новая задача (а не правка старой). При этом **traceability**
(task↔branch↔PR) решает ту же проблему, что flow-forward решает аудит-папками.

Отличие от SDD-моделей: у todo-md нет проблемы *drift между `spec.md` и кодом*,
потому что на уровне System Spec их просто два разных объекта не существует —
дрейфить некому.

---

## 8. Классификация: относится ли todo-md к SDD?

**Короткий ответ:** нет в строгом смысле, но не потому что «не дотягивает», а
потому что решает *другую задачу* — спецификацию изменений (task-driven), а не
спецификацию поведения системы (spec-driven).

**Развёрнутый ответ по уровням:**

| Уровень | todo-md | SDD? |
|---------|---------|------|
| **System Spec** | code as spec | **Нет.** Сознательная альтернатива SDD. |
| **Change Spec** | task = spec | **Да**, это spec-anchored спецификация изменения. |
| **Методология** | task-driven development (роли, конституция, loop) | Близка к BMAD-структуре, но task-driven носитель. |

**Авторский термин:** **task-driven development** — спецификация «упакована»
прямо в задачу, а не в отдельный spec-артефакт. Это самостоятельная ветка,
параллельная SDD, а не подмножество и не «SDD-лайт».

---

## 9. Фичи-кандидаты для todo-md (из трёх систем)

Отобрано по принципу: усилия малы / ценность высока при сохранении идеологии
code-as-spec для System Spec. Полный отказ от идеологии не предлагается.

### Высокий приоритет

- **«Decision history» для задач** (из OpenSpec): явная секция «почему решили
  так» в шаблоне задачи — дешёвая замена SDD-архива решений на уровне изменения.
- **`depends_on` / эпик как явный контрак-проверяемый граф** (частично есть):
  усилить валидатор, чтобы ловить циклы и висячие ссылки.

### Средний приоритет

- **Solutioning-секция для сложных задач** (из BMAD Phase 3): опциональное поле
  «архитектурное решение» (какие технические решения зафиксированы) — только для
  задач, затрагивающих several модулей/эпиков; мелочь пропускает (right-sized).
- **Модель персистентности как явный выбор** (из spec-kit): документировать в
  AGENTS.md, что завершённые задачи иммутабельны (flow-forward) — зафиксировать
  то, что уже де-факто делается.

### Низкий приоритет / отклонено

- **Отдельный `specs/` артефакт текущего поведения** — отклонено: противоречит
  идеологии code-as-spec.
- **Living-spec регенерация задач из спецификации** — отклонено: задачи —
  первичный артефакт в task-driven, а не производная.

---

## 10. Вывод

1. **SDD = спецификация как отдельный артефакт (intent, not implementation),
   контракт, создаваемый до кода.** Все три системы сходятся в этом ядре.
2. **todo-md / task-agents-playbook — это task-driven development**, отдельная
   методология, которая:
   - **сходится с SDD** на уровне спецификации изменений (задача = spec,
     WHAT-before-HOW, контракт, до реализации);
   - **расходится** на уровне спецификации поведения системы, сознательно
     принимая **code as spec** вместо отдельного spec-артефакта.
3. **Структурно playbook ближе всего к BMAD** (роли, durable context, delivery
   loop с review/ретро, разделение WHAT/HOW), но использует task-носитель вместо
   spec-артефакта.
4. **«Code has been king»** — то, с чем spec-kit борется, — для todo-md является
   осознанным выбором, а не недостатком. Это и есть фундаментальная оппозиция.
5. **Практический итог:** todo-md не нужно «становиться SDD». Целесообразные
   заимствования — точечные (decision history, явный solutioning для сложного,
   формализация модели персистентности) — сохраняют идеологию и усиливают слабые
   места (rationale, архитектурные решения для крупных задач).

---

## 11. Источники

### Проанализированные SDD-системы

- **GitHub Spec Kit** — `github/spec-kit`:
  - README.md, `docs/concepts/sdd.md`, `docs/concepts/spec-persistence.md`
  - (TasK-загрузка застряла в processing; доки читались напрямую через raw GitHub)
- **BMAD Method** — `bmad-code-org/BMAD-METHOD` (Breakthrough Method for Agile
  AI-Driven Development):
  - README.md, `docs/reference/workflow-map.md`, `docs/explanation/why-solutioning-matters.md`
  - (TasK-загрузка застряла в processing — репозиторий велик; читалось напрямую)
- **OpenSpec** — `Fission-AI/OpenSpec`:
  - доки запрошены через TasK (чат-сессии), статус source: ready

### Контекст методологии

- **task-agents-playbook** — `/home/dp/MyProjects/task-agents-playbook`:
  README.md (определение task-driven, «таска = spec»), AGENTS.md (конституция,
  15 ролей, рефлексия)

### Внешний авторитет

- **Martin Fowler** — «exploring-gen-ai / SDD-3-tools» (спек-first / spec-anchored
  / spec-as-source); цитируется в `docs/concepts/spec-persistence.md` репозитория
  spec-kit.

### todo-md (предмет сравнения)

- `README.md`, `docs/todo-md/` (конвенции, reference, templates), `bin/todo-md`,
  `AGENTS.md`

---

## 12. Ограничения и риски

### Ограничения ресёрча

- **GitHub Spec Kit и BMAD** не были успешно загружены в TasK (processing не
  завершён). Анализ основан на прямом чтении ключевых доков через raw GitHub.
  Для OpenSpec использовался TasK-чат с source ready.
- **В ресёрч намеренно включены только 3 системы** (по требованию задачи).
  Ранее исследовавшиеся sdd (z-hua), Helix, superspec, plaesy/spec-kit исключены.
- **GitHub Spec Kit** анализировался по официальному репо `github/spec-kit`
  (v0.12.x); поведение CLI проверялось по документации, не на реальном проекте.

### Риски

- **Терминологическая путаница «спецификация»**: термин перегружен. В ресёрче
  он разведён на System Spec / Change Spec — без этого сравнение становится
  некорректным (см. §4).
- **«code as spec» — позиция автора**, не индустриальный стандарт. Большинство
  SDD-ресурсов (включая spec-kit) её *отвергают*. Это не делает тезис неверным,
  но читатель должен понимать, что это сознательное идеологическое отклонение.
- **Глубина анализа playbook ограничена** уровнем README/AGENTS.md; детальные
  роли и `task-orchestrator` не разбирались дословно.

---

## Приложение A. Почему именно «code as spec» (краткое обоснование)

Для полноты — почему позиция «code as spec для System Spec» не наивна, а имеет
рациональное основание (это позиция автора playbook, подтверждённая анализом):

1. **Код — единственный артефакт, который гарантированно синхронен с собой.**
   Любой отдельный spec-артефакт текущего поведения рискует *дрейфовать* (та
   самая silent divergence, о которой предупреждает spec-kit в модели flow-back).
2. **Для изменения важнее специфицировать дельту (что сделать), а не текущее
   состояние.** Текущее состояние агент читает из кода; спецификация изменения
   живёт в задаче.
3. **Цена отдельного system-spec артефакта** — постоянный reconcile и риск
   устаревшей истины. todo-md платит эту цену *только* на уровне задачи
   (change spec), что дешевле.

**Когда эта позиция слаба:** при больших brownfield-системах, где текущее
поведение плохо выводится из кода (legacy, неявные контракты, распределённая
логика). Здесь SDD-инструменты (особенно BMAD established-projects и OpenSpec
с его `specs/`) дают реальную ценность — и здесь лежит зона потенциальных
заимствований для playbook (см. §9).

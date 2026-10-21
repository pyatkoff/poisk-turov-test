# poisk-turov-test — Autopilot Roadmap

## Текущий проверенный checkpoint — 21.10.2026, ORIGINAL NEXT

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, статус `NEXT_ORDINARY_UI_ACCEPTED_EXTERNAL_GATES_REMAIN`, SEARCH [#1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646), координация [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709). Обычный compiled UI-этап430/1280, включая1280×720, принят в точном scope; полная поставка, physical и production остаются отдельными gates.

| Уже закрыто | Точная основа и предел доказательства |
| --- | --- |
| UI и цельный обычный путь | Baseline source `3d7d50d17aec24cecc74fb25d04ee7015601daf1`, [audit6319858062](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6319858062):24 matched compiledPNG+receipts, Desktop9.5/Mobile10/Filters10/Selected10/Calendar9.5, FIRST baselines/UI0. Это не native430/physical и не wholeSearch9.5 |
| Действующий установленный NEXT | PR4796/source `9f8e14c8d3a60bbd6e26a1c3c7d9134cac953d64` → runtime release `c44a30c19a207005a9888b00429b84d49c83e3ee`, appSHA256 `e493b58973369146519b0f4d8e1ff640a6250c426899b24d90bffa885ed13f98`, dataSHA256 `c3c755d92c389a6e3496941eede2ba3a34364fcbbd92bf69ab834937672c1d92`. Все5 final-source checks и один stock39662545481 приняты. [Terminal/RELEASE6324876115](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6324876115), [audit6324888881](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6324888881): глубокая desktop-фильтрация и Reset открывают начало новых результатов; compiled1280×900/720 и installed snapshot1363×936 проверены отдельно, без нового полного балла |
| Реальный обычный путь и семья | [ANEX146015/2A6319827377](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6319827377): выбранная пара/контактный черновик/возврат. [Семья6321337269](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6321337269):2A+[5,5]/SU420-SU421/230151₽ за4/контакт без ввода/возврат. [Charter131890/2A6323775660](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323775660):одна returned pair/контакт→Back. Разные exact scopes; realLead0, не physical/infant/fuel breakdown/globalfinalverified |
| ANEX receiving и две реальные пары | SOURCE receiving [6323462398](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323462398) закрыт. Единственный INT case [6324054718](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6324054718): A→B→cachedA, обе153695₽ за2A, фактический quote receipt0→4→6→6. SEARCH принял ту же UI-границу в6324888881, без второго supplier run/source audit. CONSUMED/NO_REPLAY; не all40/infant/fuel/expiry-boundary proof |
| Выбор конкретных Rixos — форма | [6325145066](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6325145066): actual canonical queryRix, local684Belek+687Tekirova, Apply→reopen→изменение без Apply→Close→reopen сохраняют оба/OR. Installed1363×936, Find/quote0; это pre-submit draft, не alias authority/фактические submitted IDs/native430/physical. Список10 подсказок не полнота каталога и не лимит offercards |
| TV410 — прежнее принятое исправление | Sourceb8c/runtimec899/[terminal6322744838](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6322744838) +[receiving6322830603](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6322830603): истёкшая exact котировка/session теряют authority, повтор0HTTP; новый9f8 сохраняет data c3c. Новый provider410 не провоцируется, старый source не текущий runtime |
| Просмотр | Тот же PUBLIC project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, Site205/source74490263/[6317776266](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6317776266): LIVE открывает top-level NEXT, iframe — demo. Site203 остаётся согласованным product reference |

**Действующее разрешение:** прямое21.10.2026 [6323725979](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323725979) — обоснованные разовые проверки и серии, включая live/quote/repeated ANEX, автономно в stock limits с конечным scope/physical budget/reservation/claim; повторного per-check/per-series вопроса нет. Старый repeated-check approval-wait заменён. Consumed/UNKNOWN/отклонённые операции не повторять/не переименовывать; paid fallback, realLead/booking, production и защищённые изменения сохраняют отдельные допуски. Квоты/config не расширяются.

**Следующий результат:** не повторять закрытые receiving/A-B-A/TV410/4796/обычный UI corpus. INT [6325033841](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6325033841) владеет family/infant backend и своим будущим единственным сценарием; SEARCH потребляет только свою UI-границу того же принятого результата. Независимый подтверждённый существенный frontend-дефект исправляется своим owner; при отсутствии дефекта код/косметическая очередь не выдумываются. Rix aliases/actual submittedIDs требуют canonical LOCAL факта/штатного безопасного receipt, native430/physical — реальной capability; защищённая LeadC3/доставка и production — точного допуска. Это конечные условия прежней задачи, не новая per-N очередь.

**Разделение:** SEARCH — форма/выдача/фильтры/отображение цены/выбор/контакт/возврат; INT — supplier transport/auth/backend/price authority/физические stage facts. Один case/executor/receipt на общей границе; второй потребляет его, review ограничен delta. LiveTV+directANEX, SAMO paused; fuel0 только SAMO/search/Biblio, не Tourvisor/directANEX/final price. Missing/pending/expired не0/final; чужая цена не подтверждает новый выбор, fuel/markup/APD не начисляются повторно.

Оба пути заявки сохраняются: актуальная цена тура позволяет оставить рейсы менеджеру; optional exact pair требует собственной цены за всю группу. Keyboard/Tab/A11y-only запрещены; другие размеры/200%/Compare deferred. Native/physical/alias/submittedIDs/real delivery/production не подменяются compiled, form, source или частным баллом. История/snapshots/root next_action не исполняемая очередь. Собственная автоматизация и чужие задачи/модели/расписания не меняются. Этот factual sync — UI delta0, build/publisher/runtime0.

## История checkpoint PR4729 — 20.10.2026, до PR4775

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, очередь #1646; текущая координация [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709).
Датированные версии и прежние следующие действия ниже сохранены как история/no-replay;
их pins и оценки не заменяют этот checkpoint. Общие owner/protected ограничения действуют.

| Состояние | Проверенный результат |
| --- | --- |
| Установленный продукт PR4729 | source `5e1b10ff420af5bc18b59761ceed2e966b4eb6f4`, release `27dc8a8f166aa50e80c05d6d035d98b4b6f3f04b`; appSHA256 `b60417778442974b170416143ab6de946b5224af8c9b76de346998e933f625b7`, dataSHA256 `dc89305e513c876126615e12dd909b9124ebdcafe8ebc5b80c888c2555dd6223` |
| Exact-source CI и публикация | CI SUCCESS: Build Search3 whole-site preview artifact:39420221567, Search3 full visual migration:39420221579, Security guard:39420221619, Validate V2 lead source contracts:39420221688. Build `39420221567` / artifact `12262518315`; stock `39422251546` / deployment artifact `12264591042`; [terminal/readback](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6290872437). Predecessor a153 сохранён для rollback |
| Исправление и его границы | Canonical INT4720 + diagnostics INT4730 + ORIGINAL4729: неповторяемый отказ больше не предлагает бесконечный retry/обход через рейсы или контакт; временный retry и другое предложение сохранены. JSDOM430/1280 и существующий compiled terminal corpus — контролируемая проверка; stock readback отдельно подтверждает установленные байты. Новый actual supplier denial, physical Safari и реальная доставка N |
| Реальный current5e1 Rix2A manager-path | [terminal6293507531](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6293507531), [audit6293495176](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6293495176): Belek684/ownTV43292257689309/Интурист06–13Nov/7n/AI;401021₽ за2A, fuel19974₽ включён; контакт с рейсом через менеджера→Back/Close/Continue/черновик, iframe430×932→1280×720. Точная пара не выбиралась; CONSUMED/no-replay |
| Реальный current5e1 семейный exact-pair блок | [audit6294247011](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6294247011), [claim6294003240](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6294003240): Москва→Турция03–09Nov/7n/2A+[8]/AI/Belek684→328вариантов. OwnTV43296101711379/Интурист06–13Nov/deluxe room garden view; Southwind2S002/2S011,438472₽ за всю семью, fuel29961₽ включён. PairApply→контакты→Back/Close/Continue и тот же430×932→1280×720 сохраняют выбор/черновик; realLead0.6кадров/27observations, independent review ACCEPT; Selectedtour/ordinaryreturn2/2/2/2/2=10 FIRST bounded baseline/UI0, не wholeSearch9.5 |
| Исторический predecessor a153 — отдельный scope | [Egypt6290155004](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6290155004): Москва→Египет,5–11Dec2026,7ночей,2взрослых,AI;68отелей/575вариантов в части Tourvisor, ANEXfailed. Свой FUN&SUN25277420880495/SunnyDays9–16Dec/UJ708–707:128362→124838₽ за2взрослых; интерфейс показал fuel0; контакт→Back/черновик сохранён, realLead0. CONSUMED/no-replay; результат не перенесён на новый source |
| Диагностика и открытая зависимость | Безопасная initial-search диагностика установлена: [TV6290221240](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6290221240), [ANEX6289994513](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6289994513). Историческая причина и восстановление ANEX не установлены |

Следующий связный блок: принятая INT diagnosis/recovery ANEX и необходимый точный
price/fuel handoff в #2506/#4709, затем только затронутый ORIGINAL NEXT сценарий.
Rix2A и семейный Find/quote/pairApply/возврат завершены: не запускать их снова из
исторического next_action. Owner20Oct17:01 «давай большими блоками»: один связный
пользовательский результат, подтверждённые существенные исправления одним пакетом
и одна итоговая приёмка; не новая per-N/keyboard/device/cosmetic очередь.
Standing allowance6286202365 и прежние protected gates сохраняются. Actual wire-ID
provenance/HTTP/quota remaining/ANEX recovery/physical/real delivery и wholeSearch9.5
этим блоком не доказаны; полная lead-validation строка N. Own task verified enabled
[6292922298](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6292922298);
старые paused-заметки — история, iPhone/Safari deferred и не условие остановки.
Документы не меняют runtime, публикацию, расписания/timezone/prompt/чужие задачи; UI delta0.

## История журнала координации — 2026-10-01 (заменено #4709)

Все новые claims, handoff, результаты и разрешённые команды: [#4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217).
#3419, #2530 и #996 — история/no-replay; незавершённые claims и ограничения сохраняются.
При блокере зафиксировать причину и продолжать следующую доступную независимую задачу.
Маршрутизация и действующие исполнители: [coordination-journal.md](docs/project/coordination-journal.md).
Перенос журнала не разрешает повтор операций и не расширяет доступ или production-допуск.

## Development routing — owner direction 2026-09-09

Start with [AnyTour development](docs/project/anytour-development.md): four workstreams,
one coordinator in #4217, one owner per shared component. Use the fresh release
`AUTOPILOT_STATE.json.current_task` for product execution; its historical snapshots
and the inherited ANEX root state are not new queues. Integrations use their scoped
ANEX plan; SITE #1719 and SEO #1720 remain independent within existing permissions.
This routing preserves all protected contracts, production approval and applicable CI.


Updated: 2026-09-08

## Current owner direction — product/site work without duplicate layers (2026-09-08)

The active plan is [Search3 product development](docs/project/search3-product-development-plan.md)
and `AUTOPILOT_STATE.json.current_task`. Useful CSS/JS growth for better visuals,
usability and functionality is explicitly authorized. Historical 12KB/zero-growth
targets or mandatory per-packet savings do not define the current product goal.
Replace superseded implementations in the same packet; no stacked override layers.

**Do not stop after one PR if this invocation has time for the next safe step.**
Finish applicable CI/integration, record evidence, then continue the next product task.
A blocked item or production visual-approval wait does not block independent safe work.
Owner-authorized packets in the active plan may be implemented autonomously within existing DS2 and isolated preview; production visual approval remains separate.
Keep lean preview checks and all protected business/production boundaries.
Older roadmap/checkpoint sections below are historical where superseded here.



Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the owner-priority source; `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` owns architecture and `TEST_MATRIX.md` owns CI/test mapping.

### История Search3 execution — owner direction 2026-10-19, до PR4729

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, ORIGINAL NEXT; новый UI-кандидат не создаётся.

Исторический checkpoint PR4700 — 20.10.2026, до следующей публикации PR4729.

| Состояние | Точная основа и evidence |
| --- | --- |
| Исторический установленный продукт PR4700 | source `a153d12e500a7855b0ca15b2dfa8743354e63db2`, appSHA256 `1fece71fdc074ba152d62251bd942fbeb36df42fe775af85444e6611f173cc11`, published release `e8f359866b9b8eb6f98ed062b8234aa1f839cf34` |
| Ремонт и штатная публикация | PR4700: all8 exact-source checks/all7 workflows SUCCESS; build39393809671/artifact12251475296; stock39397377588/terminal[6287458863](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6287458863), predecessor1c7 retained |
| Проверенное поведение | Canonical OR/subset сохраняется при обычном клике; сбой источников не выдаётся за отсутствие туров; country minimum не подставляется под selectedhotelIds. Source+compiled430/1280 error evidence и installediframe430/1280 filter positive-control разделены; [audit6286876103](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6286876103). Без нового общего балла |
| Разовые поиски | Прямое решение владельца «можешь не спрашивать разрешения на разовые поиски», [6286202365](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6286202365): новый обоснованный обычный one-off/quote без повторного вопроса, в прежних лимитах/штатном UI. Не bulk/auto-drain/paid browser/repeatedANEX/реальная заявка/production и не изменение квот |
| Точная зависимость | Один Rix Find07:55:55 завершён с ошибками обоих источников, quote0/submit0, CONSUMED. Retained-log diagnosis/recovery — [INT6286723845](https://github.com/pyatkoff/poisk-turov-test/issues/2506#issuecomment-6286723845), без нового outage probe; actual installed failure/real submittedIDs/цена N |

Новые факты4700 не переносят старые оценки на изменённый artifact. Site203 reference,
Site204/PUBLIC project/history, production и соседние previews сохранены.

История принятой версии1c7 до ремонта4700 — точные прежние scopes:

| Состояние | Точная основа и evidence |
| --- | --- |
| Установленный продукт | source `1c7c3223190673726d5185c8946469f62e5294d3`, appSHA256 `68843eeefb1c9829448645e74a2608d187f12164094495ec406f876a157ae1a0`, published release `0576d6eb69cb31b80ca4d479e45a275f107a6e34` |
| Ремонт и публикация | PR4685; все5final-source gates SUCCESS; stock publisher39360500690/terminal[6282322588](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6282322588) consumed; predecessor3bdd retained |
| Ordinary430/1280 | [audit6282417033](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6282417033): installediframe430×932/1280×720, параметры/фильтры→тур→manager-flight либо optionalpair3/189000 за семью→контакты→возврат/черновик; recovery185000→187000 иReset исправлены, boundedFilters9→10(+1) |
| Retained360/390/768 | [audit6282656840](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6282656840):39PNG+4receipts exact1c7, materialgaplist пуст; первый boundedMobile360/39010, intermediate7689.5, Calendar-range9.5; UI delta0 |

Это принятые наблюдавшиеся UI-scopes, не wholeSearch9.5/globalDONE и не
native/physical/real-supplier/real-delivery acceptance. Остальные gates остаются
отдельными. Site203reference/Site204viewer, PUBLIC project/history и production
не меняются. Повторять принятый UI/публикацию или создавать косметический пакет нельзя.

### Историческое следующее действие PR4700 (заменено checkpoint PR4729)

Пакет4700/sourcea153 установлен в ORIGINAL NEXT; stock39397377588/readback6287458863 и installed iframe430/1280 filter positive-control приняты. Failed-source/OR/country-price исправление доказано source+compiled, actual installed failed-provider branch/realprice остаются отдельным N. Операция next-live-rix-mobile-20261020-v1 CONSUMED: не повторять старый Find/retry/Continue/quote. Следующее конкретное условие — принятая INT diagnosis/recovery по retained logs07:55:55, #2506/6286723845; до неё не запускать платный outage probe. Другой новый обоснованный разовый поиск/обычная проверка цены разрешены владельцем6286202365 без повторного вопроса, в прежних лимитах и штатном UI; это не bulk/auto-drain/repeatedANEX/realLead/production authority. Независимый новый handoff допускается в своём ownership; physical QA не общий predecessor. Не повторять неизменённые UI/CI/deploy и не создавать косметическую очередь; paused/schedule/timezone не меняются.

Условия внешних зависимостей, ownership и способы QA — в верхнем исполняемом
разделе [product plan](docs/project/search3-product-development-plan.md).

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

Historical phase record: current Search3 targets, routing and queue follow the
2026-10-19 execution section above, not the superseded issue/preview pins below.

AnyTour Design System 2.0 is the only canonical design system. Do not restore, introduce or reference Design System 1.0 as current. Treat the public site as one product across homepage → destination/country → hot/search → results → selected tour → lead.

Active owner-directed continuation is [Search3 site completion, issue #3419](https://github.com/pyatkoff/poisk-turov-test/issues/3419). Finish the new design across the site before expanding SEO or refactoring. The coherent whole-site preview is published from checked source `39d376436e3553d16755a7da1c77cb4c3f5156bf`; desktop real-catalogue acceptance reaches the lead form, unavailable-flight/retry branches pass deterministic browser coverage, result cards are readable at 375/430/1024/1440, localized decimal flight-price tradeoffs are internally consistent, and resort destination copy uses the reviewed grammatical form. Physical iPhone/Safari remains. The existing search-only deploy still accepts only nine search files and is unchanged. The stable DS2 mode/phase/stage identifiers remain unchanged; Search3 is the active substage.

Priority order after emergency overrides is: confirmed search/mobile defects → common site presentation → whole-site preview and handoff → truthful content/working links and offer freshness → end-to-end acceptance → owner-approved migration. Owner explicitly authorized Search3 presentation refactoring on 2026-09-05. Source ownership and reproducible build are in scope; broader technical refactor and mass SEO expansion remain deferred.

New design/layout concepts require explicit owner approval. Existing approved DS2 owners may be repaired and converged autonomously when a concrete defect is confirmed.

**Search3 release lock:** isolated preview publication is authorized; production draft [#1334](https://github.com/pyatkoff/poisk-turov-test/pull/1334) must not merge to `main` or replace production `/poisk-turov/` until the owner explicitly approves the concrete visual release. Current whole-site preview: `https://anytoour.ru/_preview/search3-site-candidate/`; the earlier search-only preview remains separate at `https://anytoour.ru/_preview/search3-candidate/poisk-turov/`. Preparing code, tasks, evidence and rollback is authorized; the remaining production approval does not block independent safe work.

**Historical Search2 lock:** #810 remains subject to its previous preview-only restriction. It is not the active development target; do not resume Search2 merely because old evidence below refers to it.

## Architecture and protections

- Loaded normalized result filters remain instant/local, target up to 100 loaded hotels. Do not trigger a new Tourvisor search for every result-filter click.
- Local result facets may only be offered when the loaded payload contains complete data for that facet; otherwise hide/reset the facet rather than silently filtering on partial data.
- Do not introduce generic client pagination as a replacement for the current progressive loading model.
- Preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
- Work only inside `pyatkoff/poisk-turov-test`.
- Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, existing lead transport/field mapping, AnyTour logo or verified destinations.
- Do not migrate unresolved legal/payment content.
- PR #254 remains deferred pending a fresh architecture review. `technical_refactor` remains deferred pending explicit owner direction.

## Historical DS2 production evidence through 2026-09-02

The following records are retained for provenance, not as the current Search3 queue or latest release status.

The shared DS2 shell is established across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages. Existing production gates cover required widths 375/430/768/1024/1440 and preserve search/results/selected-tour regressions.

Recent material convergence:

- PR #792 moved the factual footer styling into the shared footer owner and restored canonical footer behavior/touch targets across search and non-search routes.
- PR #800 normalized country hero resort badges to the shared 44 px DS2 vertical rhythm without changing them into interactions.
- PR #806 normalized country-detail and `/rb/` manager handoffs to the canonical `Помощь менеджера` wording.
- PR #813 closed the remaining `/how-to-buy/` wording mismatch: its `/contacts/` secondary CTA now also says `Помощь менеджера`; destination unchanged.
- PR #814 fixed a confirmed `/contacts/` interaction defect: four office telephone links plus the unified telephone link now use a shared 44 px minimum DS2 touch target. All PR gates were green. `Deploy anytoour.ru` run 33553029719 passed release copy, public-page verification, unchanged lead-bridge verification and live-search smoke; post-deploy standalone visual run 33553359626 passed at 375/430/768/1024/1440.
- PR #817 hardened the canonical five-width visual gate with an explicit live assertion for exactly five `/contacts/` phone targets and minimum 43.5 px measured height.
- The first main-push execution after #817 exposed a CI/deployment race while a concurrent country-content release was being copied, producing a transient `/country/russia/` 500 during the copy window rather than a stable runtime defect.
- PR #820 fixed that race: production `Visual standalone content live` no longer starts directly on main push; it runs authoritatively after successful `Deploy anytoour.ru`, while PRs still use the same local five-width audit. #820 passed Security + local five-width visual checks and merged as `de909ef035b31664851609f8180c0b1de77d969b`.
- The subsequent country-content `Deploy anytoour.ru` run 33553777247 completed successfully, including public-page verification, unchanged lead-bridge verification and live-search smoke. Authoritative post-deploy five-width visual run 33554159108 also passed, confirming the fully copied release at 375/430/768/1024/1440, including all five `/contacts/` phone targets and representative country pages.
- PR #842 hardened local result filtering: meal/category/rating/sea facets are exposed only when the currently loaded normalized payload has complete data for that facet. Production deploy run 33563761068 completed successfully; Tourvisor, pricing, analytics and lead contracts were unchanged.
- PR #843 fixed shared mobile breadcrumb readability on long country/page names. At widths up to 560 px the current breadcrumb can wrap instead of being clipped to an ellipsis; desktop/tablet are unchanged. PR security, standalone, bundle and visual gates passed before merge; merge commit is `ec4f7afb6eaab4af18cd951018ea0647fae73a3e`.
- PR #862 fixed a shared DS2 compact-header touch regression: at <=520 px the hamburger control had fallen to 38×38 px while navigation targets remained 44 px. The canonical shared header now keeps the compact control at 44×44 px. Merge `a8047188bc1b4d31294456a6f8d0cbdfdbbae501`; production deploy 33571150972 passed release validation, copy, public-page verification, unchanged lead bridge and live-search smoke. A concurrent subsequent SEO release also deployed successfully, and authoritative current post-deploy standalone visual run 33571329043 passed the requested 375/430/768/1024/1440 route audit, confirming the fix in the current live release.
- Search 2.0 preview now has a green five-width initial-form baseline on head `efdd19f4fe9799e3e36332d00729f675b1e94530`. Preview run #51 (`33573157764`) passed 375/430/768/1024/1440 with exactly five primary fields, truthful `All Inclusive` bound to canonical `food=7`, blue CTA, populated `7–10 ночей` summary, `Все фильтры`, correct mobile CTA-before-quick order, no page JS errors and `overflow=false` at every width. The 375 evidence was visually inspected: quick choices scroll internally while the document remains viewport-width, and the nights summary stays on one line. No new CSS layer was added; production remains release-locked.
- PR #870 closed another shared DS2 interaction inconsistency in selected-tour: `Вернуться к предложениям` used a 40 px minimum target and the hotel-description toggle used 38 px. Both now use the canonical 44 px DS2 touch rhythm in the existing `ds2-selected-tour-convergence-v1.css`. All eight PR gates passed, including five-width selected-tour visual evidence and the deterministic baseline. Merge `4ddf997599d69fadf96fd0842cc4ff9e561e187b`; production V2 deploy `33573370464` passed copy, Verify V2 and Live search smoke, and selected-tour post-deploy visual run `33573588616` passed.
- That release also exposed a stale generic V2 post-deploy assertion, not a runtime defect: every five-width search/result/header/phone/overflow check was green, but the workflow still required removed legacy `.v2-site-community`. PR #872 changed the gate to require one canonical `.ds2-site-footer` and zero legacy community blocks while retaining all other assertions. It merged as `2ae43e3dffce44a607fb14254056438ca8de554b`; direct production five-width run `33573805145` then passed.

Search/Tourvisor, analytics/Metrika, pricing, lead transport, logo, verified destinations and unresolved legal/payment content were untouched by these DS2 fixes.

## Resume point

Search3 candidate `470474414a3930f1f6c095a8dbbc187075b253c0` is published in the isolated search preview. CI `33925976956` and deployment `33926206799` passed; deployment PR #1339. Evidence covers 375/430/768/1024/1440 and wide 1920/2048. Owner confirmed the iPhone calendar opens; system night-picker appearance and Safari alignment still require device confirmation. This evidence is not whole-site or real lead-delivery acceptance.

Draft #1334 contains the production import, old-search route, client-facing copy, independent home night ranges/child ages, and date/night/party-preserving links from snapshot/month pages. These changes are prepared and checked, not published on the main site. The previous 7.3/10 score is historical; no new whole-product score has been assigned.

Continue from `AUTOPILOT_STATE.json` current task/queue and [issue #4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217):

1. Keep the published whole-site preview isolated and exact; source `47e54a02`, deploy `33942600148`, deployment record #1346.
2. Complete physical iPhone/Safari acceptance. Desktop home → search → hotel → tour → flight → summary → lead form passed without sending a lead; deterministic Chromium also covers empty-flight retry, recovery and the fallback path to the lead form without invoking lead transport.
3. Supplier normalization of ages `0/17` from requested `2+2` to offer placement `3+1` is confirmed by a control search. The UI now labels this truthfully as the tour operator's placement and keeps original ages in the lead context; do not change Tourvisor, price or lead contracts.
4. Resolve confirmed broken legal/payment links only from existing approved content; record missing content rather than invent terms.
5. Obtain owner visual approval on a specific release, retain previous deployment artifact/configuration, verify rollback and `/poisk-turov-old/`, then execute the approved migration and post-deploy verification.

Published in the current exact preview: result cards from `17b674fc54fa6b49aaa73379bcbdc35bfccfda28` use taller mobile hotel photos and readable fact/action typography while preserving a compact desktop layout. Visual run `33941679993` passed at 375/430/1024/1440; artifact `9962036028` was inspected. Date, nights, meal and flight are not repeated below the price; that line contains only the party composition. The current source additionally fixes localized decimal flight-price tradeoffs without changing raw Tourvisor values, price selection or lead payloads. Live verification showed `72 832 ₽` → `90 050 ₽` with the correct `+17 218 ₽`, then reached the lead form without submission.

Detailed status and legacy-search limits: `docs/project/search3-site-finish-and-legacy.md`. Issue #2 is a machine-maintained CI signal, not the product task list. An hourly scheduled development task is enabled. It must continue beyond one PR whenever time remains for another safe step; it is not a nonstop process. PR #254, technical refactor and unresolved legal-content authoring remain deferred.

Owner continuation rule: **do not stop after one PR if the current run has time for the next safe step**. Update the handoff after each checked change and continue. A final production visual-approval gate blocks production publication only, not other authorized safe preparation.

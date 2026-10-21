# AnyTour — единая организация разработки

## Текущий проверенный checkpoint — 21.10.2026, ORIGINAL NEXT

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, очередь #1646 и координация [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709). Текущий результат — установленная минимальная диагностика PR4778 и один затронутый обычный поиск. История/no-replay ниже не является очередью.

| Состояние | Проверенный результат |
| --- | --- |
| Фактически установлен PR4778 | source `41fbe711e11bede04890b9150a41220e3bb24a17`, runtime release `93914a8a523e13fa315c578bc874f0e037c3a57f`; appSHA256 `380b45b506ae946e61dcada8e7ad17ce7d106c1c5a1f00b24fbcfe391345f072` неизменён, dataSHA256 `00ac6716b43c3837dca0ce9b36f680e90b7655c9fb4e943b5401c697e0a45d95` |
| Проверки и одна публикация | Все6 применимых exact-source workflows/7checks SUCCESS с первой попытки; canonical52/inventory160/bridge PASS. Build39527075039/SOURCE12312993243, stock39528574999/READY12315030107/DEPLOY12314650725:843файла/9routes200/43assets/readback. SOURCE/READY/DEPLOY независимо приняты раздельно; [publisher RELEASE6305962224](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6305962224), predecessor9977/rollback сохранён |
| Работающий эффект | В существующих строгих post200 guards сохраняется один из8 фиксированных локальных clientGuard, без body/ключей/PII и без изменения условий допуска цены/identity/party/date. Существующий terminal/noReplay/возврат сохранён; UI/app/CSS/composition не менялись, численная UI delta0 |
| Один новый affected ordinary control | [terminal/RELEASE6306078713](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6306078713), [audit6306085730](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6306085730): один Find01:46:28.632Z, fresh4–10Nov2026/7n/2A/AI/Москва→Турция4 →75отелей294UIварианта/partial. Свой DG HOTELS ROSE RESORT/Economy Room/AI/4–11Nov/listing96117RUBunconfirmed; один expand →01:47:26.498Z `HTTP200/client_projection_unconfirmed/clientPhase=projection/clientGuard=envelope`. Quote/pair/contact/retry/providerContinue-drain/lead UIintents0; context CONSUMED/no-replay |
| Фактическая UI-приёмка этого отказа | Actual samechild iframe430×932↔1280×720↔430: неподтверждённая96117 за всех/условия/ошибка и выбор сохраняются через other-tour→samecardContinue→selectedANEXrow→terminal. Та же одна warning, без нового providerintent. Два actualterminalJPG независимо просмотрены/hashverified; возврат — reported ROOT DOMtrace, не полный независимый trace. Не native/physical; transitional1280 исключён |
| Единственная следующая граница | [INT→SEARCH6306074777](https://github.com/pyatkoff/poisk-turov-test/issues/2506#issuecomment-6306074777): read-only сверка producer/receiver первого составного envelope/hotel/tours guard. Именно этот guard теперь доказан; отдельное неверное поле/условие и supplier cause NOT_ESTABLISHED. Старый14–20Dec PUBLICbody NOT_RETAINED по6305535503; новый body не извлекался. Producer допускает expanded+emptyhotels в некоторых случаях, но это не доказательство данного ответа |

Следующее действие — принять bounded authoritative envelope contract/facts от существующего INT-owner; исправлять только подтверждённое несоответствие в существующем owner. Если нужные safe facts не сохранены, явно NOT_RETAINED; никаких private-session/body/новых reader/endpoint, повторного Find/expand/probe или ослабления guards. Не запускать ещё один общий диагностический цикл по предположению. PR4778 source/CI/stock/обычный affected control завершены, claims RELEASED; документационный merge не новая runtime-версия и не требует публикации.

Exact ANEX tour→verified whole-party price→optional pair→contact остаётся N до quote. Старые14–20Dec9977/7–13Dec5e1/Rix/family/FUNSUN операции и оценки сохраняют свои exact scopes/no-replay, не перенесены на41fbe. WholeSearch9.5/real delivery/production не объявлены готовыми. Текущий обычный UI scope430/1280; keyboard запрещён,200%/Compare/прочие размеры отложены. Native/physical — отдельная внешняя проверка, не причина паузы доступной работы. PUBLICSite204/project/history, чужой design-v2, main/production/соседние preview и настройки автоматизаций не менялись. Автопилот текущей задачи проверен enabled; handoff продолжается без новой задачи/score tracker.

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

## История организации поставки — поручение владельца 19.10.2026, до PR4729

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

Единственная очередь и таблица внешних gates — верхний исполняемый раздел
[product plan](search3-product-development-plan.md), синхронный с `current_task`.
Клавиатурные проходы запрещены;200%/Compare deferred. Ownership, protected
контракты и exact допуски сохранены. Этот control-only пакет не меняет
опубликованный продукт, расписания или enabled/paused; UI delta0.

### Сохранённые датированные receipts — история/no-replay

Датированные факты прежнего3bdd до ремонта (история/no-replay):

Owner20Oct02:34 прямо разрешил один exact поиск/TVtour quote без предварительно подтверждённого резерва и сообщил5000/сутки. Этот конкретный допуск уже CONSUMED: [terminal6280546977](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6280546977), [audit6280542177](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6280542177). Москва→Турция03–09Nov/7n/2A/AI:59отелей/381вариант, статус partial; exactTVtour99280056379835/ownhotel687/RIXOS/LOTI/04–11Nov/268848RUB за2A→managercontact→Back/Close/Continue/draft PASS. Это native1363×936, не430/1280/physical, не цена выбранной пары и не реальная доставка.5000 — owner-reported дневной лимит, не измеренный остаток и не изменение serverconfig. Не повторять поиск/quote и не ждать прежний admission заново.

Owner20Oct02:43 «делай»: Site204/source d71b84cae73d947438b16407dfb527f6769843f9 добавил отдельную [рамку установленного NEXT](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/next-preview?width=430&mode=demo) в том же PUBLICproject/history. Deployment appgdep_6ad6ba5442f48191be103403d20f90e2 succeeded; saved appgver_a6d4f56bc84881919b15e748a5b2596e. Donor Site203/его UI и установленный3bdd/app7ae9ba не менялись. По умолчанию явное offlinefamily; liveFORM отдельно, без searched/autoFind; arbitraryURL/query не принимаются. PUBLICQA [audit6280721648](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6280721648): child actual430×932/1280×720 + exact8script graph; managercontact186400/2A+[0,8], resize→Back/Close/Continue сохраняет выбор/черновик. Это новый installediframe scope, не native top-level/physical и не общая9,5. Нового live не было.

Observed compiled430/1280 corpus6280199658/audit6280193716 сохранён: FIRST bounded
Mobile2/2/2/2/2=10/UI0, Desktop9.5/Selectedtour10/Calendar9.5 лишь свои scopes.
Новые live/iframe receipts не переносят эти баллы на весь installed/mobile/Search.

## SEARCH/SITE: исполняемое поручение visual/UX9,5–10 — 10.10.2026

Историческая детализация: матрицу размеров ниже заменяет owner20.10 00:52 —
только430/1280 до цельного UI≥9,5, затем остальные ширины. Завершённые Site203
и transfer/family/Calendar не возобновляются из этой таблицы.

По прямому поручению владельца подготовить сначала цельные desktop/mobile
макеты и кликабельный прототип всего пути. Действующая детализация —
[Search3 product plan](search3-product-development-plan.md), раздел
«Поручение владельца10.10.2026». Это уточнение существующего
`S3_PRODUCT_CURRENT_ACCEPTANCE`, не новый координатор, tracker или расписание.

| Роль | Ближайший законченный результат |
| --- | --- |
| SITE/design #1719 | В существующем публичном Sites проекте один целостный draft: параметры/выдача/фильтры → отель/вариант/выбранный тур → контакт/возврат; desktop1280/mobile390 + адаптив360/430/768 |
| SEARCH #1646 | Exact текущие owners/data/state ограничения и сохранённый корпус для дизайна; перенос полного пакета после конкретного visual+behavior approval |
| Независимый QA через #4217 | Короткий baseline затрагиваемых экранов, review макетов/prototype, затем сопоставление того же NEXT по SEARCH-QUALITY-1 |
| Координатор #4217 | Свободные scoped claims, один интегратор, handoff, конкретные версии и следующий безопасный шаг |

Baseline, дизайн и полная оценка идут параллельно; завершение всех55 критериев,
physical Safari и готовность всех данных не являются предварительным условием
создания макетов. Цель≥9,5 в каждой полностью измеренной применимой строке;
макет/prototype/NEXT/physical/real supplier отмечаются отдельно в существующем
audit. Новый общий средний балл не вводится. Первый design deliverable —
рабочая ссылка/exact версия, минимум восемь основных desktop/mobile кадров,
переходы и состояния; один статусный отчёт его не заменяет.

Единственная визуальная приёмка ближайшего пакета — просмотр всей готовой
кликабельной версии по внешнему виду и поведению. Новые концепции получают
explicit owner approval перед переносом в NEXT. Внутри approved решения
подтверждённые дефекты устраняются автономно. PR4502 и Site106 сохраняются draft,
Site100/package34 и завершённый PR4501 не открываются заново.

До будущего source-пакета заново проверить реальную карту подключения:
NEXT target `v2/visual-search/index.php` → `v2/visual-search/app.js`;
исторические таблицы `v2/index.php`/`src/search3/` ниже не дают права править
неподключённый owner. SITE не пишет NEXT renderer/CSS параллельно SEARCH.
Один writer общего файла/проектной версии; независимая подготовка и QA параллельны.
INT/LOCAL/MATCH дают только существующий bounded фактический handoff, не
переносят supplier/DB очередь в дизайн.

При ожидании approval/CI/данных сохранить конкретный artifact/checkpoint и
продолжать независимую разрешённую работу, затем возобновлять тот же пакет.
Старый source-reduction `next_action` в current_task заменяется этим поручением;
завершённые refactor receipts/no-replay/guards сохраняются в Git и журнале.
Protected contracts, SAMO paused, бюджеты, применимый CI, NEXT publisher и
production approval остаются. Расписания/модели/enabled/paused не изменены.

Поручение владельца от 2026-09-09: улучшать интеграции ANEX/Андромеды,
сопоставление отелей, поиск, сайт и SEO параллельно, с одной общей координацией.
Репозиторий: только `pyatkoff/poisk-turov-test`.

## Старт и источники текущего состояния

1. Прочитать `AGENTS.md`, этот документ и scoped AGENTS/README затронутого компонента.
2. Выбрать направление в таблице ниже; прочитать его актуальный state/план и issue.
3. Освежить head ветки, открытые PR, последние сообщения владельца/координатора и
   активные CI/deploy. Исторический SHA или старый автоматический prompt не заменяет свежую точку.
4. Объявить задачу и точные общие файлы в [#4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217).
   Если их уже меняет другой исполнитель — передать ему требование либо взять независимый файл.

Этот документ владеет распределением работы. `AGENTS.md` и `OWNER_PRIORITY.json`
сохраняют правила допуска/приоритеты; `ARCHITECTURE.md` — архитектурные инварианты;
`TEST_MATRIX.md` и применимые workflows — проверки. Этот пакет не меняет их защиты.
Состояние исполнения не дублируется в этом документе или в расписаниях:

| Что нужно узнать | Авторитетная точка |
| --- | --- |
| Общие приоритеты, владение общими файлами, передача между направлениями | Этот документ и #4217 |
| Текущий исполняемый продуктовый пакет общего Search3/сайта | Свежая release: `AUTOPILOT_STATE.json.current_task`; конкретные evidence — профильный audit |
| Порядок продуктовых этапов | `docs/project/search3-product-development-plan.md` на свежей release |
| Текущие supplier/API задачи и доказательства | `docs/integrations/anex-search3-autopilot.md` на свежей ANEX-ветке |
| Текущее массовое сопоставление отелей и evidence ladder | `docs/project/hotel-matching-autopilot.md` и MATCH #1971 |
| Конкретная задача и завершённые/незавершённые условия | Issue направления, linked PR и его артефакт |
| Что действительно опубликовано | Exact source/artifact и успешный deploy/readback; не один head ветки |
| Предыдущие действия/неудачи/измерения | Датированные audit/history; не повторная очередь исполнения |

`AUTOPILOT_STATE.json` из старой ANEX-основы не является состоянием общего сайта.
В release `current_stage.next_action` — указатель на `current_task`, а не вторая очередь.
Новая запись состояния должна исправлять активный указатель; не добавлять ещё один
конкурирующий «Текущий checkpoint» над историей. Историю сохранять с датой и ссылкой.

## Пять направлений

| Код / направление | Ответственность и результат | Очередь | Рабочая основа |
| --- | --- | --- | --- |
| INT — интеграции и provider data | Supplier transport/auth/search/package/price, provider response normalization, сохранённые supplier observations и контракты границы с SEARCH | umbrella draft #1493; Андромеда #1717; состав цены #1685 отдельным пакетом | Сейчас `feature/anex-search-adapter-20260907`; перенос в release отдельным согласованным пакетом |
| MATCH — hotel identity | Массовое Tourvisor ↔ ANEX ↔ Andromeda сопоставление, evidence, accepted/manual/conflict/exclusion registry delta и остаточный review | #1971; история выполненных пакетов #1759 | Matching source на свежей `feature/anex-search-adapter-20260907`; организационный план в release |
| SEARCH — поиск и выдача | Форма, lifecycle, карточки/фильтры/сортировка, общая выдача, выбор/возврат и контекст конкретного предложения | #1646; текущий release-пакет в `current_task`, координация #4217 | `release/search3-production-ready-v1`; существующий ANEX addon временно остаётся в своей ветке |
| SITE — сайт и путь к покупке | Общий shell, главная и страницы, навигация, правдивый контент, переходы в поиск | #1719; существующий продуктовый план | Свежая release |
| SEO — органический поиск | Типы посадочных, URL/metadata, ссылки, содержательность и правила будущей индексации | #1720; существующий #395 — зависимость данных/история | Свежая release; сначала read-only аудит и требования |

Координатор в #4217 выбирает ближайшие пакеты по пользе, устраняет пересечения,
собирает совместимые результаты и ведёт единое опубликованное состояние.
Рабочие чаты/агенты — исполнители этих задач; переписка не создаёт новую ветку продукта.
INT может иметь независимые задачи ANEX и Андромеды, но не владеет очередью hotel identity.
MATCH не меняет supplier transport/search/price/package и получает provider evidence через явный контракт.
Полное сопоставление каталога не является условием улучшения поиска или начала SITE/SEO.

## Владельцы компонентов — текущие пути, без массового переноса файлов

| Компонент | Основной владелец | Реальные точки изменения / взаимодействия |
| --- | --- | --- |
| Native search form | SEARCH | `v2/index.php`; `src/search3/behavior/search-form.js` только shell/busy/edit |
| Локальные Search3 фильтры / selected handoff | SEARCH | `src/search3/behavior/results/local-hotel-filter.js`, `behavior/summary-cta.js` |
| Оформление формы, карточек и selected | SEARCH | `src/search3/styles/entry-native-controls.css`, `styles/results-layout.css` |
| Общие results/selected/lifecycle | SEARCH | `v2/results-renderer-v5.js`, `tour-controller-v4.js`, `runtime-v3.js`, `search-lifecycle-v6.js`, `search-continue-v6.js`, `catalogs-v2.js` |
| Supplier transport и response normalization | INT | `app/integrations/anex-client.php`, `anex-search.php`, `anex-preview-gateway.php`, `anex-normalizer.php` |
| Принятые ANEX/Andromeda hotel identities / manual / pair exclusions | MATCH | действующие mapping/identity registries; сохранять текущую БД, manual решения и exclusions; shared path только после claim #4217 |
| Matching diagnostics / bridge / review datasets | MATCH | существующие `scripts/diagnostics/*matching*`, Andromeda/ANEX bridge diagnostics и новый bounded MATCH tooling; не создавать дублирующий resolver без необходимости |
| Граница provider → Search3 | INT + SEARCH, один исполнитель на пакет | `v2/api-anex-search3-preview.php` и соответствующая Andromeda projection; input/projection меняются по согласованному контракту |
| Временный own-preview addon | SEARCH в ANEX-ветке | `v2/anex-search3-preview-v1.js`; не копировать поверх актуального UI и не добавлять третий renderer |
| Панель matching-review и сохранённые досье | MATCH; auth/runtime contract с INT при необходимости | `app/admin/anex-review/*`, `v2/anex-hotel-review.php`, существующие bounded diagnostics/importer |
| Общая оболочка и страницы | SITE | `v2/site-page-shell-v1.php`, `site-header-v2.*`, `site-footer-v1.*`, `home-v1.php`, `home-entry-v1.php`, общие стили и route templates |
| Переходы / свежесть | SITE; контракт с SEARCH/INT | `v2/site-path-v1.php`, `offer-freshness-v1.php`, country/resort/month renderers |
| SEO policy / metadata / ссылки | SEO; mixed renderer через SITE/SEARCH | `v2/seo-page-*-v1.php`, `seo-internal-links-v1.php`, `seo-structured-data-v1.php`, `seo-content-catalog-v1.php` |
| Индексация и публикация SEO | Координатор + отдельный допуск владельца | `v2/seo-config.php`, `seo-launch-slice-v1.php`, `seo-sitemap-candidates-v1.php`, `sitemap.xml`, `robots.txt` |

`v2/index.php`, `home-v1.php`, `site-page-shell-v1.php`, `country-page-v1.php`,
`seo-*-page-v1.php`, `site-path-v1.php`, `offer-freshness-v1.php` и catalog readers
смешивают несколько направлений: их меняет один объявленный исполнитель.
Префикс `v2/seo-*` не даёт владения всеми runtime/данными/launch guards этого семейства.
Соседнее направление передаёт контракт/пример и получает результат в том же пакете.

Источники/восемь generated assets определяет `src/search3/manifest.json` и существующая
сборка; точная актуальная карта — `src/search3/README.md`. Пустые provenance-слоты
не считаются живыми владельцами. Целевая папочная схема `ARCHITECTURE.md` не даёт
повода переносить рабочие файлы или создавать новый слой ради структуры.

## Одна выдача для нескольких поставщиков

Tourvisor, ANEX и будущая Андромеда предоставляют отдельные source/offer/search
identity и нормализованные данные. Карточки/фильтры/выбор принадлежат общему SEARCH.
Оператор тура и источник API — разные поля; совпадающие числа ID разных API не связывают отели.
Принятые связи, manual priority, pair exclusions и evidence provenance принадлежат MATCH и сохраняются.

Общий provider contract описан в `docs/integrations/multi-provider-search-plan.md`
на ANEX-ветке. Это целевой контракт, не заявление о готовом универсальном registry:
`hotel-identities.php` пока pilot JSON registry, действующие provider resolvers проверяются по свежему source.
Текущей public ANEX projection ещё не хватает полного offer/search context для выбора.

Передача INT → SEARCH включает: source SHA, целевую свежую release SHA, ограниченный
список файлов, поля/fixtures и неизвестные сведения, supplier budget, проверки,
checked/published/deferred, issue принимающего владельца. MATCH передаёт только проверенную
hotel identity/provenance и не подменяет offer/search/package identity. Затем:
1. SEARCH/INT фиксируют один provider contract и владельца общих файлов в #4217; MATCH отдельно claim-ит shared registry paths, если они нужны.
2. Короткая ветка от свежей release получает только необходимый bounded пакет.
3. Актуальные UI-владельцы подключают данные; календарь/фильтры/выбор проходят узкий
   совместный сценарий. Новый поиск инвалидирует старые ответы каждого источника.
4. После паритета перенесённая функция прекращает отдельное развитие в addon.
   Legacy код удаляется только после подтверждения отсутствия нужных consumers.

Не merge всю старую ANEX-основу поверх release и не включать DS2/старые layers из неё.
Draft #1493 остаётся изолированным umbrella до конкретного допуска интеграции.
Андромеда #1717: использовать официальный контракт/доступ и проверенные ограниченные ответы;
ANEX HOTELS30/7 дней/лимиты не переносить на неё по предположению.

## Рабочий пакет и параллельность

Одна задача описывает:

```text
Направление / issue / ответственный исполнитель:
Польза и наблюдаемый дефект либо согласованный сценарий:
База branch + SHA; точные owned paths; общие файлы/зависимости:
Входные сохранённые данные/контракт; отсутствующие сведения:
Критерий готовности; узкие проверки; применимые CI:
Исходный PR/SHA → checked → published artifact/route:
Блокер/deferred; следующий доступный шаг:
```

Статус задачи: ready / in_progress / blocked / done. Отдельно записывать
implemented, checked, preview-published и production-approved; они не взаимозаменяемы.
Для документации done означает проверенные ссылки/согласованность, без build/deploy.

Рабочие ветки короткие: `feat/<направление>-<задача>`, `fix/...`, `docs/...` от
нужной свежей базы; постоянные ANEX/release остаются только существующими integration bases.
Перед каждым интегрированием освежать head и проверять чужие изменения; без force-push.
На один общий файл — один активный writer, на одну задачу — один source PR.
Чтение/подготовка независимых компонентов параллельны; shared edits и deploy последовательны.
В #4217 claim включает issue, branch/PR, paths и последний подтверждённый шаг.
Зависший claim не снимается по таймеру: сначала проверить PR/CI и текущего исполнителя.

Автопилотные очереди разделены по пяти направлениям. MATCH #1971 — самостоятельная очередь,
а не подзадача INT: она читает `docs/project/hotel-matching-autopilot.md`, current DB/receipts
и историю #1759. INT не продолжает matching `next_action` из старых checkpoints. Общая продуктовая
работа ведёт SEARCH/SITE и независимую SEO-подготовку; supplier/API задачи идут через INT.
Не создавать дублирующие одинаковые очереди. В начале каждого запуска читать текущие документы,
не хранить многократно переписанные старые checkpoints в prompt. Если блокируется одна операция —
сохранить причину и взять независимую задачу. Не останавливаться после одного PR, пока в запуске
есть следующий безопасный шаг.

## Приоритет, проверки и публикация

Авария/потеря заявок/искажение данных выше плановой работы. Затем пользовательский
поиск/выбор и интеграционный шов, сайт/переходы; matching развивается независимо и
не блокирует SEARCH/SITE, SEO-архитектура идёт независимо, массовое расширение страниц
ждёт проверенных шаблонов и допуска публикации.

Один связный source-пакет → узкие необходимые проверки → применимые обязательные CI →
интеграция. Сборку выполнять один раз для пакета; successful exact artifact переиспользовать.
Визуально смотреть затронутое поведение, не повторять весь путь ради документов.
Непроверенное deferred, красный обязательный gate не passed. Защиты/лимиты не ослаблять.
Полезный рост CSS/JS допустим; удалять заменённые owners/правила/handlers в том же пакете,
показывать прирост initial/full/inline отдельно, без новых stacked override skins.

Публикация только штатным точным артефактом: own ANEX-preview для его текущего пакета,
whole-site preview для актуальной release. Один исполнитель публикации по маршруту;
перед повтором проверить реальные runs и артефакт. Production #1334/main — отдельное
визуальное одобрение конкретной версии; старый preview source не является новым release.
Метрика/goals, lead transport/field mapping, protected API URL/payload, price arithmetic,
catalog/manual решения и соседние проекты сохраняются. Production indexation/robots/
canonical/redirects/sitemap не менять без отдельного явного согласования владельца.

## Подробные планы и модели

По поручению владельца от 2026-09-09 подготовлен [подробный план реализации](anytour-roadmap/README.md):
[INT](anytour-roadmap/integrations.md), [SEARCH](anytour-roadmap/search.md),
[SITE](anytour-roadmap/site.md), [SEO](anytour-roadmap/seo.md),
[порядок интеграции и выпуска](anytour-roadmap/delivery.md), [модельные роли](anytour-roadmap/models.md).
Отдельный план массового hotel identity — [MATCH](hotel-matching-autopilot.md), issue #1971.
Перед назначением нового пакета читать его зависимости и приёмку; ARCH/BUILD/REVIEW/ASSIST
подбирать по модельной матрице. Это backlog с проверяемыми результатами, не новая
копия current_task. Готовые стадии и занятые source-пакеты не перезапускаются.
Две рабочие основы сохраняются; provider INT→SEARCH и identity MATCH — ограниченные handoff
с одним owner на каждый общий файл. Настройка моделей фоновых задач не считается изменённой
от записи рекомендации в документах.

## Ближайшие задачи — ссылки, без второй копии очереди

- MATCH: #1971; продолжать только от CURRENT DB после `hotel-full-catalog-delta-1759-20260911-v2`.
  Сначала cross-provider bridge и сохранённое evidence, затем Tourvisor→ANEX operator link→
  `HOTELLIST`/`hotelCode`, manual только последним. Completed/unknown operation не replay.
- SEARCH: продолжить актуальный `current_task` release и #1646; конкретный point-тур
  должен сохранить свой search context до выбора/возврата. Готовые point/reveal/filter
  пакеты не повторять; минимумы источников не объявлять одинаковыми пакетами.
- INT: #1685 — отдельный владелец состава цены; #1717 — supplier/API/package контракт Андромеды.
  Matching #1971 не является INT-задачей. Блокер API не блокирует остальные задачи.
  Старые supplier unknown/completed не переигрывать.
- SITE: #1719, оставшиеся реальные сценарии приёмки и один подтверждённый дефект.
- SEO: #1720, матрица существующих типов страниц/данных/URL/indexability; старые
  main-based SEO PR сначала сверить с актуальной release, не запускать автоматически.

Уточнение владельца 2026-09-09: развивать продукт до уровня ведущих ОТА.
Новая продуктовая последовательность, критерии сравнения и границы будущего
онлайн-бронирования находятся в [действующем product plan](search3-product-development-plan.md),
подробности — в [SEARCH](anytour-roadmap/search.md). `current_task`/`active_queue_ids`
переключаются на первый полезный пакет выдачи; завершённая доступная приёмка
TV-only остаётся evidence, physical Safari/production approval — внешними gates.
Это не новый координатор/план исполнения и не разрешение на production или SEO-indexation.

Отчёт владельцу: новое улучшение и ссылка, что фактически проверено/опубликовано,
существенный блокер и следующий шаг. Число PR, алиасов или неизменный snapshot не
подменяют результат; нулевой прогресс без новых фактов не требует повторного уведомления.

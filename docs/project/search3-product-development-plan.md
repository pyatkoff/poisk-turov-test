# Search3 — продуктовый план и критерии качества

## Текущий проверенный checkpoint — 21.10.2026, ORIGINAL NEXT

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, очередь #1646 и координация [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709). Текущий результат — установленная минимальная диагностика PR4778 и один затронутый обычный поиск. История/no-replay ниже не является очередью.

| Состояние | Проверенный результат |
| --- | --- |
| Фактически установлен PR4778 | source `41fbe711e11bede04890b9150a41220e3bb24a17`, runtime release `93914a8a523e13fa315c578bc874f0e037c3a57f`; appSHA256 `380b45b506ae946e61dcada8e7ad17ce7d106c1c5a1f00b24fbcfe391345f072` неизменён, dataSHA256 `00ac6716b43c3837dca0ce9b36f680e90b7655c9fb4e943b5401c697e0a45d95` |
| Проверки и одна публикация | Все6 применимых exact-source workflows/7checks SUCCESS с первой попытки; canonical52/inventory160/bridge PASS. Build39527075039/SOURCE12312993243, stock39528574999/READY12315030107/DEPLOY12314650725:843файла/9routes200/43assets/readback. SOURCE/READY/DEPLOY независимо приняты раздельно; [publisher RELEASE6305962224](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6305962224), predecessor9977/rollback сохранён |
| Работающий эффект | В существующих строгих post200 guards сохраняется один из8 фиксированных локальных clientGuard, без body/ключей/PII и без изменения условий допуска цены/identity/party/date. Существующий terminal/noReplay/возврат сохранён; UI/app/CSS/composition не менялись, численная UI delta0 |
| Один новый affected ordinary control | [terminal/RELEASE6306078713](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6306078713), [audit6306085730](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6306085730): один Find01:46:28.632Z, fresh4–10Nov2026/7n/2A/AI/Москва→Турция4 →75отелей294UIварианта/partial. Свой DG HOTELS ROSE RESORT/Economy Room/AI/4–11Nov/listing96117RUBunconfirmed; один expand →01:47:26.498Z `HTTP200/client_projection_unconfirmed/clientPhase=projection/clientGuard=envelope`. Quote/pair/contact/retry/Continue/lead UIintents0; context CONSUMED/no-replay |
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

## Исполняемый порядок — решение владельца 19.10.2026

Уточнение владельца20.10.2026: закончить сам поиск в NEXT, прекратить дробление
работы на технические проходы и частные баллы. Запрет клавиатурных проходов
принят явно. Это та же задача `S3_PRODUCT_CURRENT_ACCEPTANCE`, тот же
SEARCH-QUALITY-1 и существующие#4217/#1646/#1719; новый tracker не создаётся.
Этот раздел и свежие terminal receipts главнее исторических очередей ниже.

### История принятого результата PR4700 — 20.10.2026

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

### История предыдущего corpus3bdd — 20.10.2026

[Ordinary closeout6280199658](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6280199658)
и [audit6280193716](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6280193716)
закрыли оценку наблюдавшегося учебного пути current3bdd: форма → выдача →
отель/тур → необязательные рейсы → контакты → возврат. Существенных подтверждённых
недостатков для ремонта в этом корпусе нет; повторять оценку или создавать
косметический пакет не требуется.

Это **закрытый наблюдавшийся corpus, не полная приёмка установленного поиска**.
Compiled430×900 Mobile2/2/2/2/2=10 — первый ограниченный baseline, не рост и
не общая оценка mobile. Compiled1280/short720 Desktop9,5, Selectedtour10 и
Calendar9,5 сохранены в своих scopes. Новый установленный desktop pointer-путь
filter → zero-match → explicit budget recovery → свой тур/manager-contact →
Back/Close/reset/sort/Continue принят отдельно; native430/physical и реальная
цена/доставка из него не следуют. Source3bdd/app7ae9ba не менялись.
Частный baseline не разблокирует автоматически остальные размеры или globalDONE.

### Конечный результат и рабочие размеры

Удобный цельный путь обычного пользователя: параметры → выдача/фильтры →
отель/варианты → конкретный тур и итог за всю группу → необязательные рейсы →
контакты → возврат с сохранением выбора и допустимого черновика.

Исторический exact1c7 observed ordinary430/mobile и1280/desktop, включая
short1280×720, принят6282417033; конечный compiled360/390/768 остаток принят6282656840 по retained
exact1c7 кадрам/receipts без новых прогонов. Installed/native/physical этих размеров
не принимаются автоматически; это не общая приёмка поиска.200% и Compare/shortlist deferred.
Обязательные штатные final-source CI/workflows сохраняются; дополнительных
ручных или платных прогонов на остальные размеры нет.

### Основная поставка — существующий NEXT

Target: [ORIGINAL NEXT](https://anytoour.ru/_preview/search3-next-candidate/visual-search/)
на `release/search3-production-ready-v1`, repo `pyatkoff/poisk-turov-test`.
Исторический установленный продукт PR4700 `a153d12e500a7855b0ca15b2dfa8743354e63db2`,
appSHA256 `1fece71fdc074ba152d62251bd942fbeb36df42fe775af85444e6611f173cc11`;
[publication/readback6287458863](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6287458863).
Predecessor1c7/readback6282322588 сохранён для rollback;3bdd — более ранняя история.
Source/CI/merge/publication/runtime — разные статусы.

PUBLIC design-lab project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`,
Site203/source `8c826b44bce5713adc04b5d06db19c4f6f3d67cd`,
evidence `dd7a48c0b018bd3b55a69a584de1f50d68b617fd` уже согласован
[6270421744](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6270421744)
и перенесён. Повторная приёмка прототипа не заменяет работу над NEXT.
Возвращаться в тот же project/history/public только для необходимого согласования
новой композиции. Foreign `release/search3-design-v2`/его preview исключены;
совпадающие shared paths требуют свободного claim независимо от URL/ветки.

Завершённое сохраняется как evidence, а не следующая очередь:

| Пакет | Точная завершённая основа |
| --- | --- |
| Семейный путь4647 | source564ef1b1/release22850f1f, [6274857599](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6274857599); тот же3bdd UI, обычный3A+[0,12] путь/возврат/черновик |
| Calendar4649 | sourcee8ba1e0e/release9130a6aa, [6278527732](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6278527732);18кадров/6receipts/15clock receipts, все4CI успешны,843product payload hashes сохранены |
| Итоговый shortDesktop review | [6279680382](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6279680382), [audit6279688566](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6279688566): compiled430/1280 incl720 Desktop2/1.5/2/2/2=9.5 FIRST baseline/UI delta0 |
| Старые реальные операции | Root6263876510/live6264963728, публикации и TinyFish6279010055 consumed/no-replay; реальные Rix desktop6272447072 остаются своим exact scope |

Дополнительный PUBLIC Site203 QA6279500567 и установленный клавиатурный проход
6279680382 завершены. Повторять их нельзя; клавиатурный проход после нового
запрета не является следующим действием или gate обычной поставки.

### Завершённые операции и маршрут просмотра

Owner20Oct02:34 прямо разрешил один exact поиск/TVtour quote без предварительно подтверждённого резерва и сообщил5000/сутки. Этот конкретный допуск уже CONSUMED: [terminal6280546977](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6280546977), [audit6280542177](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6280542177). Москва→Турция03–09Nov/7n/2A/AI:59отелей/381вариант, статус partial; exactTVtour99280056379835/ownhotel687/RIXOS/LOTI/04–11Nov/268848RUB за2A→managercontact→Back/Close/Continue/draft PASS. Это native1363×936, не430/1280/physical, не цена выбранной пары и не реальная доставка.5000 — owner-reported дневной лимит, не измеренный остаток и не изменение serverconfig. Не повторять поиск/quote и не ждать прежний admission заново.

Owner20Oct02:43 «делай»: Site204/source d71b84cae73d947438b16407dfb527f6769843f9 добавил отдельную [рамку установленного NEXT](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/next-preview?width=430&mode=demo) в том же PUBLICproject/history. Deployment appgdep_6ad6ba5442f48191be103403d20f90e2 succeeded; saved appgver_a6d4f56bc84881919b15e748a5b2596e. Donor Site203/его UI и установленный3bdd/app7ae9ba не менялись. По умолчанию явное offlinefamily; liveFORM отдельно, без searched/autoFind; arbitraryURL/query не принимаются. PUBLICQA [audit6280721648](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6280721648): child actual430×932/1280×720 + exact8script graph; managercontact186400/2A+[0,8], resize→Back/Close/Continue сохраняет выбор/черновик. Это новый installediframe scope, не native top-level/physical и не общая9,5. Нового live не было.

Действует правило из раздела [«Следующее действие»](#следующее-действие). Physical QA и принятые внешние handoffs независимы; доступность/допуск устанавливаются для выбранного scope.

Приёмка проходит обычными кликами/тапами через этот точный installed runtime.
Смена ширины в рамке не перезагружает child; каждый новый полный reload/mode switch
начинает новый просмотр. Уже проверенные действия не запускаются ради покрытия.
Исторический exact1c7 ordinary430/1280 UI-scope закрыт6282417033, retained360/390/768 —6282656840;
currenta153 имеет отдельно compiled failed-source и installed healthy positive-control, без полной installed error-приёмки;
завершённые действия не повторять. Код — только при существенном подтверждённом
дефекте; прежний1c7 corpus не принимает новыйa153 автоматически. Iframe не означает native430/physicalSafari/safearea и не принимает
реальные supplier prices/lead delivery. При недоступности child не делать proxy,
не менять security headers/carrier/server/browser install. Sourceprepared/CI/
published/installed/physical/live остаются разными статусами.

Для нового обоснованного ordinary supplier one-off/normalquote действует standing
допуск владельца6286202365: повторного вопроса не нужно, прежние stock limits/accounting
сохраняются. Bulk/auto-drain/paid browser/repeatedANEX/реальная заявка/production
требуют своих exact gates; consumed one-run6280498998 и Rix6286499106 не повторяются. Native/physical, canonicalRix и INT/production зависимости
не объединяются в круговой AND-gate. Не создавать ещё один admission для уже
завершённой операции или повторять её под новым именем.

### Способ приёмки и пределы текущего этапа

Основной способ — мышь на desktop и тапы в mobile scope. **Клавиатурные
пользовательские проходы, Tab-циклы, отдельные A11y-only/keyboard/focus задачи
не выполнять и не ставить в очередь.** Не начинать отдельную правку рамок по
кадру клавиатурного теста. Изменение выделения допустимо лишь при подтверждённом
существенном неудобстве обычного клика/тапа в текущем связанном пакете.

Существующую рубрику12/11/5 и шкалу не менять. Запрещённый владельцем клавиатурный
scope сохраняет честныйN/deferred, не даёт фиктивных баллов и не становится
блокером обычной UI-поставки. Physical/native430, реальная цена/данные/доставка
и production сохраняют собственные evidence scopes/разрешения; compiled и donor
их не подменяют. Ни документ, ни первый baseline не являются ростом UI.
Не запускать отдельную очередь проверок или переписывание плана без нового
существенного факта/прямого уточнения владельца.

### Незакрытые зависимости и точные условия продолжения

Это остаток существующего плана, не новый tracker и не поручение другим потокам.

| Область / состояние | Действующий ответственный | Следующее конкретное действие | Условие закрытия |
| --- | --- | --- | --- |
| Installed TV/manager-путь — COMPLETED/CONSUMED6280546977 | SEARCH, explicit owner allowance6280498998 | Сохранить exact RIXOStour99280056379835/268848RUB/2A и возврат; не повторять поиск/quote | Текущий TVoffer→managercontact/Back/Close/Continue PASS в native1363; не optionalpair/fullsource/430/realLead |
| Исторический exact1c7 installediframe430/1280 — ordinary path ACCEPTED6282417033; currenta153 — ограниченный positive-control; native/physicalSafari — N | SEARCH + QA; same Site204 viewer, затем реальное устройство для physical | Принятый1c7 путь/retained360/390/768 не повторять; physicaliPhone/Safari430 проверять при доступном штатном маршруте, отдельно от независимых handoffs | Exact1c7 observed ordinary430/1280 UI scope закрыт; currenta153 failed-provider installed — N, source/compiled proof отдельно. PhysicalSafari/safearea только своим фактом. Iframe/native/physical/compiled не смешивать |
| Rix aliases / submitted exactIDs — отдельные N | Canonical данные у LOCAL; применение у SEARCH | Проверить доступность alias только штатным canonical read/handoff; submittedIDs включать в новый обоснованный ordinary поиск по standing6286202365 без повторного вопроса, не повторять consumed и не делать платный поиск ради ввода | Canonical alias факт и фактические OR exactIDs приняты в своих scopes. Неизвестный/отсутствующий alias не угадывается и не чинится новым matching в SEARCH |
| ANEX repeated/allpair authority — отдельный INT handoff отсутствует в последнем чтении | Действующий INT#2506; SEARCH принимает | Прочитать новый конкретный handoff/contract и exact quote/receipt semantics при его появлении; принятую optional-one-pair authority не расширять | Реализованный принятый bounded контракт и exact операция/total; proposal caps3/8/9 не authority. Не блокирует независимо допущенный TV/manager-path |
| Реальная доставка заявки / LeadC3 — N, preview delivery выключена | Owner-reviewed lead transport, затем SEARCH | После отдельного точного разрешения защищённого scope проверить штатные pending/error/retry/success; до него сохранять локальную проверку/контакты отдельно | Реальная доставка доказана своим receipt; preview validation/quote expiry её не заменяют. Не условие закрытого учебного UI corpus |
| Production — отдельное approval | Владелец + штатный publisher | Предъявить конкретный готовый artifact после применимых gates; публиковать только по отдельному approval | Production artifact/receipt/runtime подтверждены; preview/source/план не являются допуском |

Остальные применимые N остаются в существующем SEARCH-QUALITY-1 с точным
недостающим экраном/действием. Они закрываются в том же связанном пользовательском
пути при доступном разрешённом scope; из N не создаётся бесконечная очередь
покрытия. Внешний блокер не подменяется фиктивным passed и не объявляет все
независимые разрешённые действия заблокированными.

### Маршрут проверки и действующие разрешения

Stock compiled-browser workflow и существующие owners уже дали6viewport
acceptance;430/1280 результаты переиспользуются в текущей рабочей матрице,
360/390/768 приняты6282656840 как конечный retained scope без новых прогонов. Все результаты принадлежат
своему source/artifact.
CloudChrome доступен для установленного desktop; top-level resize не подтверждён.
С20Oct Site204 даёт фактически проверенный installediframe430×932/1280×720,
с тем же NEXTrenderer и сохранением выбора наresize (audit6280721648).
Это разрешённый публичный UI-маршрут без нового платного fallback; physical/native
не заявляются. Capability повторно проверять только при изменении/конкретном
отказе. Без нового действия/риска не повторять installed/compiled/CI/deploy.
TinyFish metered; попытка4df685a1 с отсутствующим resize завершена/consumed
[6279010055](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6279010055),
мобильная приёмка ею не получена. Повтор того же setup, платный fallback по
умолчанию, local server/browser install и смена carrier ради обхода запрещены.

Прямое owner19.10.2026 23:32 «разрешаю» выполнило gate конкретного Calendar
пакета; allowance fulfilled/consumed PR4649. PR4647 также consumed.
Не открывать самостоятельные QA-only пакеты из каждогоN или частного балла.
Необходимая regression подтверждённого разрешённого продуктового исправления
следует обычным gates. Прямое owner20.10 уточнение закрепляет обычную цельную
поставку; supplier/lead/production и чужие claims не расширяются.

### Организация каждого прохода

- Один продуктовый WIP и максимум один ещё не просмотренный visual candidate.
  Один writer точных paths, один интегратор; независимый read-only review
  параллельно. Отдельный publisher — только для готового изменённого artifact.
- На старте освежить heads/PR/CI/claims, текущие сообщения #4217/#1646/#1719,
  верхний исполняемый раздел и `current_task`. Для уже полностью прочитанных
  инструкций/consumer map сравнить SHA: неизменённый текст переиспользовать;
  изменённые и новые scoped-инструкции прочитать до действия.
- До записи закрепить в существующем claim target/project/branch/exact paths,
  исходный source, ожидаемый пользовательский результат, доступный путь проверки,
  минимальные кадры/действия и непокрытый scope. Claims не истекают по давности.
- Во время исправления выполнять узкие проверки по его риску. Все применимые
  обязательные проверки — на законченном source; успешный byte-identical результат
  переиспользовать. Новый source/failure/изменённый риск дают причину нового запуска.
- Для правки управления: diff/JSON/неизменность protected state,
  независимый review и применимые owner-policy/security gates. UI/browser/deploy
  не запускать ради плана. Draft/ready, workflows и allowlists не менять для CI.
- Подтверждённые исправления approved UI: focused/compiled → обязательные CI
  на финальном source → разрешённый release merge → эксклюзивный штатный
  NEXT-only publisher exact artifact → readback → установленное затронутое действие.
  Новый дизайн сначала проходит whole-version visual+behavior approval в том же
  design-lab; разрешение плана не согласует невиданный UI.

### Завершение, зависимости и отчёт

В существующем SEARCH-QUALITY-1/audit сохранить12 исторических строк,
11 обязательных, пять критериев0/1/1.5/2, N без суммы. Проверенные неизменённые
критерии сохраняют exact evidence; первый baseline не рост. Порог≥9,5 проверяется
по каждой ключевой применимой полностью измеренной строке; среднее не скрывает
слабый mobile. Mockup/clickable/compiled/installed/physical/real supplier раздельны.

Закрытый наблюдавшийся UI corpus не переоткрывается из старого next_action.
Admission и каждое внешнее доказательство получают собственный terminal в существующей
координации; один BLOCKED scope не превращается в глобальный стоп доступной работы.
Пакет предъявляется на итоговый просмотр, когда его оставшиеся действия проверены,
подтверждённые существенные дефекты устранены, применимые gates выполнены и audit
показывает фактический результат. Полное поручение не объявляется завершённым при
N в ключевом применимом критерии. Отдельные внешние зависимости перечисляются
точно, без фиктивного passed и без блокировки независимой разрешённой работы:

Точные исполнители, условия и следующий факт перечислены в таблице выше.
Приёмка установленного UI и внешние зависимости фиксируются раздельно;
глобальное DONE не выставляется до обязательных gates и применимых критериев.

Один значимый результат: #4217 — claim/terminal/RELEASE и следующий шаг;
#1646 — изменённая оценка с пятью основаниями/evidence; #1719 — стадия и ссылка.
Не копировать весь один и тот же отчёт в три журнала. Владельцу — работающий эффект
или новый точный блокер, компактная таблица до/после только при новом результате.
Документы/CI/версия не улучшают UI: для этой организации delta0.

Если безопасный следующий шаг отсутствует, зафиксировать одно конкретное условие
продолжения и сохранить пакет. Не заменять его повторным аудитом/пустым уведомлением;
блокер одного маршрута не объявлять остановкой всего проекта.

**200% текста и Compare/shortlist — deferred/not_measured**. Новые прогоны/UI
для200% не добавлять и отсутствие покрытия не считать блокером. Сохраняются оба
application пути: актуальный тур с рейсами менеджеру либо optional exact pair с
собственной подтверждённой ценой за группу. Missing/pending/expired не0/final;
fuel/markup/APD не начисляются повторно. Supplier/API/price/FX/lead/Metrika/DB/
MATCH/LOCAL/main/production/siblings/workflows/secrets защищены прежними правилами;
новые paid/live/real lead требуют exact допуска/budget/reservation/executor.
Расписания, enabled/paused, модели, настройки и другие задания не изменяются.

## Поручение владельца 10.10.2026: цельный SEARCH visual/UX 9,5–10

**Активный результат ближайшего этапа — полный пакет макетов и кликабельный
прототип пути desktop + mobile, а не очередная отдельная карточка или отчёт.**
Это уточнение действующей очереди `S3_PRODUCT_CURRENT_ACCEPTANCE`, SEARCH #1646,
SITE/design #1719 и координации #4217; новый roadmap, score tracker и расписание
не создаются. Прежние завершённые этапы, source/publication receipts и ограничения
сохраняются. Более ранний гейт «сначала измерить все55 критериев, потом начинать
макеты» заменён порядком ниже: короткий baseline затрагиваемых экранов, дизайн
и полная оценка идут параллельно. Непроверенные supplier/physical области
не блокируют создание макетов и не считаются проверенными.

### Цель и один способ оценки

Рубрика остаётся **SEARCH-QUALITY-1**: 11 обязательных строк × те же пять
критериев, Compare/shortlist deferred. Цель — **не менее9,5/10 в каждой
применимой полностью измеренной строке**, стремление к10/10; общий средний
балл не скрывает слабый mobile. Шкала0/1/1,5/2 и `not_measured` не меняется.
Итог9,5 требует всех пяти измерений: максимум один1,5, остальные2.
Конкретное существенное неудобство означает незакрытый критерий, а не
произвольное округление до9,5.

Макет, кликабельный прототип, compiled fixture, установленный NEXT, physical
и real supplier — разные evidence scopes **в существующем audit**. Макет может
доказать визуальные критерии; поведение, exact identity, price authority,
архитектурная чистота и реальная доступность подтверждаются соответствующими
проверками. Статичная картинка не закрывает эти строки. Физический Safari и
реальные заявки не получают ни фиктивного passed, ни штрафа к unrelated visual.
До полной сопоставимой приёмки не объявлять весь поиск9,5–10.
Не оценивать новый макет по одному удачному экрану, а NEXT — по другой методике.

### Три крупных связанных блока

| Блок | Законченный пользовательский результат | Макеты и состояния |
| --- | --- | --- |
| 1. Параметры → выдача → отель | Полная форма и редактирование поездки; понятный календарь; достоверные фильтры и сортировка; обозримая выдача с фото, условиями, ценой и действием | Initial/expanded/family form, date/night/party controls, календарь sparse/full/unknown, loaded/partial/empty/error results, active filters/zero-match/reset, фото/error, short/long hotel cards |
| 2. Отель → вариант → выбранный тур | Понятные различия номера, питания, оператора, дат и цены; выбор точного offer; ясный состав и статус цены/рейсов; смена варианта и возврат | Variants/selected variant, selected-tour summary, price loading/pending/verified/changed/expired, flights known/unknown/empty/error, сохранённый выбор и Back |
| 3. Выбранный тур → контакт → возврат | Однозначный следующий шаг; контактный draft и отдельное согласие; понятные ошибки; возвращение к тому же туру и выдаче | Lead form, validation/pending/error/disabled submit, Cancel/Back/reopen, изменения условий и сброс согласия, keyboard/focus и короткий экран |

Первый дизайн-пакет содержит **все три блока как один продукт**. Они дают
ограниченные implementation packets после согласования, не три конкурирующих
версии. Уже принятые формы/фото/календарь/переходы сохранять там, где они проходят
критерии. PR4501 не повторять. PR4502 — неутверждённый кандидат мобильной
иерархии; Site106/package35 — неутверждённый вариант выбора тура. Их можно
использовать как материал для общего прототипа, нельзя принимать/публиковать
автоматически. Site100/package34 и история одобрения сохраняются.

### Первое исполняемое задание SITE/design #1719

Продолжать **существующий публичный проект «AnyTour Search3 — дизайн»**
`appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, сохраняя аудиторию и историю.
Зафиксировать текущие owner instructions/версию и объявить свободный scoped
claim до изменений. Собирать новые макеты как явно draft внутри этого проекта;
не обновлять старый приватный прототип и не подменять approved reference.

1. Взять краткий baseline тех же экранов установленного NEXT и уже проверенные
   сохранённые данные. Записать exact source, сценарий и конкретные ограничения
   в существующий audit; полную оценку продолжать параллельно, не ждать её
   завершения для начала макетов. Новые supplier searches не нужны.
2. Подготовить цельную композицию основных экранов **форма / выдача /
   выбранный тур / контактная форма** на390 и1280: минимум восемь основных
   кадров, плюс раскрытия календаря, фильтров, фото и вариантов и нужные
   состояния из таблицы. Мобильная композиция самостоятельная.
3. Проверить360/430/768, короткий desktop1280×720, длинные названия/цены,
   семьи0/1/2/3 ребёнка и отсутствующие данные. Увеличение текста200% —
   deferred/not_measured по позднему решению владельца; без отдельного прогона
   и без блокирования макета/переноса. Interaction проверять в кликабельном
   прототипе и точно называть scope.
4. Включить реальные сохранённые примеры с происхождением и контролируемые
   демонстрационные состояния; не выдумывать рейтинг, отзывы, гарантию цены,
   наличие, время подтверждения, багаж, fuel или преимущества.
5. Собрать кликабельный полный путь: параметры → фильтры/выдача →
   фото/варианты → exact tour/цена/рейсы → контакт → возврат/редактирование.
   Работают Apply/Cancel/Back, локальные изменения и явно демонстрационные
   loading/empty/error/unknown/changed-price. Прототип не отправляет заявки
   и не доказывает supplier acceptance.
6. До показа владельцу исправить замечания собственной и независимой
   проверки всего пакета. Выход: рабочая ссылка, exact версия/source,
   просмотренные desktop/mobile кадры, переходы/состояния, оценки применимых
   критериев и оставшиеся конкретные ограничения. **Статусный отчёт без
   этих материалов не считается выполнением.**

### Конкретные визуальные требования к ближайшему пакету

- Сохранить полный состав основной формы и единственный календарь; полная
  форма открыта при вводе/редактировании, в выдаче — понятный контекст поездки
  и действие изменения. Компактность не достигается скрытием обязательных полей.
- Верх выдачи содержит необходимый контекст, открытую полосу **семи дат/цен
  с месяцем/годом**, одно объяснение статуса данных и доступные фильтры.
  Убирать повторные заголовки/пояснения; выигрыш высоты проверять на том же корпусе.
- Desktop: понятная левая колонка фильтров и доступные нижние действия на коротком
  экране. Mobile: одно понятное раскрытие фильтров, Apply/Cancel, активные значения
  и сброс; фиксированные элементы не перекрывают содержание, CTA и клавиатуру.
- Карточка: неизменное крупное полноширинное mobile фото **1.8**, читаемые
  название/место; рядом с ценой и CTA краткие **даты · ночи · питание** того же
  exact offer, при необходимости номер/оператор. Подробности доступны без дублей.
  Новый порядок этих блоков — часть полного согласуемого макета; PR4502 не
  устанавливает его автоматически. Фото открывается и имеет понятный возврат.
- Варианты внутри отеля сравнимы по номеру/питанию/оператору/датам/итогу,
  выбор однозначен. Минимум отеля, конкретная цена, сохранённая цена и ожидаемая
  проверка различимы без технической терминологии.
- Выбранный тур даёт одну сводку предложения и одну область цены/действия.
  Указания по рейсам находятся в разделе рейсов; исходная цена явно обозначена
  при pending. Неизвестное не zero/final. После успешной цены рейсы уточняет
  менеджер по действующему контракту; неподтверждённая цена не маскируется CTA.
- Контактный шаг сохраняет выбранный тур, показывает понятные labels,
  ошибки и согласие. Нативные controls, понятный focus и доступные44px цели;
  не обещать бронь/оплату/мгновенное подтверждение вместо заявки менеджеру.
- Единые brand assets, типографика, сетка, отступы, цвета и иконки на всех экранах.
  Оригинальный AnyTour logo не менять. Не добавлять новый renderer/style layer
  при переносе; замещённые правила удалять вместе с заменой.

### Назначения и параллельность

| Исполнитель | Задание в ближайших запусках | Граница |
| --- | --- | --- |
| SITE/design #1719 | Цельный пакет макетов и кликабельный прототип всех трёх блоков; доводка до визуальных критериев9,5 перед показом | Один writer существующего Sites проекта; не пишет NEXT app.js/shared CSS параллельно SEARCH |
| SEARCH #1646 | Передать exact owner/data/state constraints и сохранённый корпус, проверить реализуемость; затем переносить только согласованный полный пакет | Пока дизайн draft, закрывать только подтверждённые дефекты уже approved поведения в свободных owners; новые концепции в NEXT не внедрять |
| Независимый QA через #4217 | Baseline и проверка полного макета/prototype, затем того же NEXT; конкретные замечания по SEARCH-QUALITY-1 | Read-only исходники, одинаковые данные/ширины/сценарии, отдельные evidence scopes; тесты не заменяют визуальный осмотр |
| INT / LOCAL / MATCH | Передавать существующие подтверждённые факты и bounded contracts по запросу владельца UI | Новый supplier/DB/identity backlog сюда не переносить; их полная готовность не блокирует дизайн |

Три design-блока можно прорабатывать параллельно при непересекающихся файлах и
одном интеграторе; shared components/tokens/renderer и публикация последовательны.
Приёмка целостности общая. Координатор #4217 собирает пакет, устраняет пересечения,
фиксирует handoff и выбирает следующий безопасный этап.

### Гейты и продолжение до результата

| Этап | Проверяемый выход и следующий шаг |
| --- | --- |
| Макеты/прототип | Exact версия и все кадры/переходы; independent review и исправления; доступные применимые визуальные строки≥9,5, остальные критерии с точным scope или not_measured |
| Решение владельца | Один просмотр всей готовой кликабельной версии одновременно по внешнему виду и поведению. Записать конкретный visual+behavior approval перед новым решением в NEXT; не согласовывать отдельно каждый отступ/кнопку |
| Перенос SEARCH | Утверждённый handoff → существующие реально подключённые owners → focused/compiled и применимый exact-head CI. Skipped/pending и старый SHA не passed |
| NEXT | Отдельный exclusive publisher, exact artifact/receipt/readback; повторить те же сценарии/данные/кадры и рубрику. Расхождения устранять до закрытия блока; перенос сам по себе не повышает оценку |
| Production/реальные проверки | Физический iPhone/Safari, real supplier и реальная доставка имеют собственные evidence и допуски; production — отдельное одобрение конкретной версии |

До переноса QA проходит пять существующих задач: найти отель; сравнить два
варианта поездки внутри поиска (не deferred compare); объяснить цену/состав;
изменить условия и вернуться; открыть контактную форму нужного тура.
Пилот4из5 участников из прежнего плана — отдельный not_measured до проведения,
не выдумывать результаты и не ждать участников для подготовки макетов.

При занятости owner, ожидании CI/approval или внешнем блокере сохранить artifact,
точную причину и ближайший независимый шаг в #4217; продолжать безопасную часть.
После возобновления брать тот же пакет, не перезапускать roadmap/завершённый
аудит. UNKNOWN сначала читать, не replay. Если безопасных шагов нет, назвать
подготовленный результат и единственный фактический блокер.

**Этот план не является одобрением4502/Site106, новой публикацией, запуском
расписания или свидетельством фоновой работы.** Existing scheduler prompts,
schedule/enabled/paused состояния не меняются. Live остаётся по свежему поручению
**Tourvisor + direct ANEX; SAMO paused**. URL/payload/auth, money/fuel/authority,
lead/CRM/Metrika/goals, DB/LOCAL/MATCH, main/production/SEO и соседние проекты
сохраняются. Новые supplier операции/реальные заявки этим документом не запускаются.

Актуализация очереди: **2026-09-29** по уточнению владельца
[#1646 comment5857899726](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5857899726).
Примеры Onlinetours, Level.Travel и Travelata от 2026-09-19 и требование
«минимум поиск на 9 из 10» сохраняются.
Владелец SEARCH — #1646; единственная текущая координация — #4217. #3419, #2530 и #996 — история.
Общая организация: [AnyTour development](anytour-development.md).
Основа: свежая `release/search3-production-ready-v1`.

Этот документ определяет продуктовые требования и порядок, а не дублирует журнал
исполнения. Перед каждым запуском читать AGENTS/scoped инструкции, свежие
`AUTOPILOT_STATE.json.current_task` и `continuation_policy.active_queue_ids`, heads,
#4217/#1646, открытые PR/CI/deploy и exact owned paths. Старые snapshots, pins,
`next_action` и названия уже выполненных стадий не являются новой очередью.
При расхождении датированного состояния с новым поручением владельца или свежими
исходниками использовать актуальное поручение и evidence, записав обнаруженный drift.
Не создавать второй current_task/координатор или повторную автоматизацию.

## Действующее поручение и границы

**Текущий исполняемый интерфейс — только существующий
[`/_preview/search3-next-candidate/visual-search/`](https://anytoour.ru/_preview/search3-next-candidate/visual-search/).**
Перед выбором пакета доказать, что target действительно подключает изменяемый owner:
`v2/visual-search/index.php` загружает `v2/visual-search/app.js`.
`v2/prototype-search/app.js` — отдельный интерфейс; его исправление не продвигает
приёмку target. Общие data/lifecycle/calendar/lead зависимости менять только после
подтверждения подключения и свежей передачи владения в #4217. Исторические пути
DS2/renderer/controller ниже не заменяют эту проверку.

Очередь запуска: основной поиск → выдача → конкретный offer → актуализация → рейсы →
честная цена → preview заявка и возврат. Отдельные «Мой тур»/saved-tour, избранное и
сравнение отложены до нового прямого поручения; скрытые target-кнопки не возвращать.
Выбранное предложение в текущем поиске и его возврат остаются в этой очереди.
Визуальный refactor, MATCH и INT fuel-learning не входят в неё.

Live выдача: Tourvisor + direct ANEX; SAMO/Andromeda paused по решению08.10.2026. DB offers/prices служат
календарю, истории и SEO; поток live → DB, никогда DB → live. Локальные профили,
фото и matching сохраняются. Первый поиск — один логический старт источника и
первые порции, SAMO page1, direct dates первоначально первая неделя; продолжение
только явной кнопкой с бюджетом. Unknown не zero, estimate не final_verified;
SEARCH не вводит собственную формулу топлива.

Полноценная OTA-форма имеет приоритет над старой компактной формой.
Будущая production `/poisk-turov/`/PR #1334 требует отдельного допуска; работа
над preview, зелёный CI и локальная проверка заявки не являются таким допуском.
Минималистичная форма допустима на главной, но её развитие принадлежит SITE #1719.
В этой очереди исполняются только SEARCH #1646 и координация #4217. SITE #1719,
SEO #1720, INT #1717/#1685 и MATCH #1971/история #1759 имеют других владельцев;
API, matching и получение/наполнение контента здесь не выполняются.

Постоянно видимые основные параметры формы страницы поиска: город вылета,
страна/направление, даты, ночи, туристы/дети, курорт/регион, конкретный отель/его
название, звёздность/категория и питание. Цена легко доступна в основном search flow.
Эти поля не прятать в «Ещё фильтры» ради компактности. Сохранять нативные контролы,
URL hydration, FormData и единственного владельца формы.

**Туроператор — фильтр полученной выдачи**, на desktop в левой колонке, на mobile
в раскрытии фильтров. При необходимости он может оставаться вторичным advanced-полем,
но не постоянно видимым primary-параметром. Не ограничивать initial supplier search
оператором без отдельной задачи/явной причины. Provider/source и tour operator — разные поля.
Аэропорт прилёта, район/субкурорт, тип/рейтинг отеля, прямой/чартер, услуги и прочие
вторичные параметры основной формы могут оставаться в «Ещё фильтры».

Основной визуальный ориентир — предоставленный «Интерфейс поиска туров AnyTour.png»
(Library ID `libfile_bcd3a18be788819193fbc1924082e3ca`) и согласованные макеты.
Это ориентир композиции, не доказательство текущего соответствия. Не переносить из
макета неподтверждённые отзывы, гарантии, моментальное подтверждение или «купить сейчас».
Не возвращать старые DS1/ANEX UI owners и не создавать вторую форму/renderer/filter owner.

### Разбор мобильного видео — решение владельца 2026-09-29

Владелец одобрил реализацию замечаний к текущему target («Давай делать», 10:57 МСК).
Это уточнение действующего плана, не отдельная версия ТЗ. Диагностика:
[#1646 comment5886028928](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5886028928).

- Город вылета: поиск по доступным городам, текущий/недавние сверху, остальные по алфавиту;
  один канонический select сохраняет значение. Смена города не запускает поиск туров.
- Направления: основные страны в устойчивом редакционном порядке, остальные по алфавиту
  (не выдавать это за измеренную популярность). Курорты группировать по LOCAL parentId/kind;
  не менять соответствия и не смешивать регионы и субкурорты в плоский список.
- Календарь: выбранное питание должно участвовать в точной DB-выборке. Исторические
  native meal IDs связывать с AnyTour только через принятый каталог. Не подставлять
  более дешёвую цену другого питания, неизвестное соответствие или DB-тур в live выдачу.
  Бюджет «От/До» применяется включительно к последней известной цене каждого точного
  сегмента, совместно с питанием, регионом и составом туристов. Старую более дешёвую
  запись не возвращать вместо последней цены, вышедшей за бюджет. Нет подходящей
  истории — неизвестная цена, без автоматического запуска поиска поставщиков.
- Питание: устойчивый порядок — любое, AI, UAI, завтраки, полупансион, полный пансион,
  без питания; дополнительные подтверждённые варианты сохраняются. Выбор не переставляет строки.
- Выдача: явное «Изменить поиск», без дублирующего ряда звёзды/питание/бюджет;
  применённые фильтры и основная кнопка фильтров остаются. После окончания поиска
  большая плашка исчезает; частичная выдача получает короткое предупреждение.
- Календарь выдачи: компактная полоса дат/цен открывает полный календарь, без двух
  заголовков и пустого контейнера в свёрнутом состоянии.
- Конкретный тур: сначала номер/питание/условия, затем явный переход к актуализации
  и рейсам, затем заявка. Возврат использует сохранённый результат шага без повторного запроса.
  Изменение полного итога после выбора рейса видно относительно цены выбранного предложения.
- Багаж относится к конкретным рейсам; повторяющиеся общие фразы о перелёте и
  неподтверждённых условиях убираются. Фокус клавиатуры сохраняется без рамки вокруг
  программно сфокусированного заголовка. Размещение и туристы не перекрываются на телефоне.

### Заявка без выбранных рейсов — уточнение владельца 2026-09-29

Рейсы может уточнить менеджер. После успешного получения цены конкретного тура
переход к заявке не требует загрузки/выбора рейсов. «Уточнить рейсы» — отдельное
необязательное действие. Ошибка, пустой список или неподтверждённая цена перелёта
не блокируют форму: передаётся полученная цена тура и явное «Рейс уточнит менеджер».
Для Tourvisor запрос данных тура `/tours/{id}` не равен актуализации корзины
`/tours/{id}/flights`: без последней показываем «Цена предложения».
По действующему контракту Tourvisor известный сбор уже учтён в возвращённой цене;
показываем «включён в цену» только для сбора из того же ценового ответа, без сложения.
Это не гарантия будущей цены/наличия; неизвестный сбор не превращается в ноль,
правило Tourvisor не переносится на прямые ANEX/SAMO.
Недоступное/истёкшее предложение и предложение без валидной цены остаются заблокированы.
Успешно проверенные условия сохраняются при возврате к тому же предложению в текущем
поиске даже без рейсов; повторная навигация не запускает quote/flights. Новый поиск
очищает эти результаты. Реальная отправка в preview остаётся отключена.
Мобильную браузерную проверку исполнитель выполняет самостоятельно; запись владельца
нужна только для дополнительного неповторяемого контекста, а не как обязательный этап.

### Примеры конкурентов — поручение владельца 2026-09-19

Повторно получены мобильные снимки полного пути трёх сервисов и два PDF:
карточка Throne Beach Resort & Spa (Onlinetours) и выдача туров (Travelata).
Это визуальные наблюдения из материалов владельца, не исследование конверсии или
доказательство качества внутренних алгоритмов конкурентов. Сохраняем фирменный
стиль AnyTour и согласованный макет; заимствуем проверяемые приёмы взаимодействия.

| Наблюдение в примерах | Применение в Search3 | Приёмка |
| --- | --- | --- |
| Onlinetours: крупные панели выбора даты, диапазона ночей и туристов; явная кнопка с выбранными значениями | Дать выбору понятное подтверждение и удобные области нажатия. Основные поля Search3 остаются видимыми; существующие native controls/FormData сохраняются | До подтверждения результаты и условия не меняются; после — выбранные значения точны. Закрытие, клавиатура, длинные значения и семья с детьми не теряют данные |
| Onlinetours: цены рядом с датами; Travelata: обозримая полоса дат | Развивать единственный текущий календарь сохранённых наблюдений. Выбор дня выделен отдельно от самой низкой цены; отдельное действие запускает поиск на выбранную дату | Перебор/выбор дат не вызывает supplier search; чтение DB не является live выдачей. Выбранные даты сохраняются до явного поиска через существующий однократный submit; другие поля неизменны. Нет цены — не ноль |
| Travelata: поиск страны, курорта или отеля с понятной текущей выборкой | Упростить нахождение направления/отеля в существующих справочниках, показать загрузку, пустой ответ и ошибку. Не маскировать ошибку каталога под отсутствие отеля | Выбор соответствует фактическому ID; быстрый ввод и смена страны не показывают старые подсказки. Мобильная клавиатура не закрывает результаты и действие |
| Onlinetours/Travelata: крупное фото, отель, место, рейтинг, условия и цена в читаемой иерархии | Сохранить одну карточку отеля и понятное открытие фотографий; отделить общие факты от конкретного минимального предложения | Фото открывается и следует за миниатюрой; цена/питание/ночи относятся к одному offer. Нет выдуманных отзывов, шкалы рейтинга, расстояний или гарантий |
| Level.Travel: номер и питание сгруппированы, соседние даты/длительности легко сравнить | Сделать список вариантов внутри отеля обозримым и сравнимым на телефоне, с точными условиями каждого предложения | Группировка не смешивает разные room/meal/operator/offer identity. Любое действие ведёт к тому же конкретному туру и сохраняет путь назад |
| Travelata: отдельные блоки «туда/обратно», багаж, состав и различие ночей в туре/отеле | Упростить сводку выбранного тура и переход к существующей заявке менеджеру | Показываются только подтверждённые данные. Неизвестное остаётся неизвестным; неизвестный сбор не превращается в окончательную цену. Отправка заявки/паспортные поля/онлайн-оплата не добавляются из чужого сценария |

Минимум владельца **9/10** — порог результата; ранее согласованная цель **9.5 по
каждой оси SEARCH-QUALITY-1** ниже не отменяется. Новую шкалу или усреднение,
скрывающее слабый мобильный сценарий, не вводить. До сквозной приёмки одного exact
source на общем корпусе нельзя объявлять поиск достигшим 9/10. Отдельные зелёные
PR, снимки и изменения календаря не повышают автоматически остальные оси.

Порядок продуктовых пакетов остаётся единым: параметры поездки → выдача/фильтры →
отель/конкретный offer → актуализация/рейсы → честная цена → preview заявка/возврат.
Исторический пакет возврата #3001 проверяется по свежему статусу и не перезапускается.
Независимые изменения календаря и фото выполняются в их действующих владельцах.
Остановленный подготовительный refactor-pass не возобновляется этим поручением.

## Порядок оставшейся SEARCH-работы

Это уточнение текущей приёмки `S3_PRODUCT_CURRENT_ACCEPTANCE`, не семь новых очередей.
Действующие `S3_OTA_PROVIDER_PARITY` и `S3_PRODUCT_APPROVED_MIGRATION` сохраняют свои
внешние зависимости и допуски. Детализация существующих сценариев —
[SEARCH backlog](anytour-roadmap/search.md); его выполненные стадии не перезапускаются.
Один writer на shared renderer/controller/lifecycle/CSS; каждый следующий пакет сначала
проверяется на наличие свежего claim и уже выполненного исправления.


### Подготовительный технический refactor-pass — поручение владельца 2026-09-12/13

Исторический этап, остановлен: уточнение от 2026-09-27 не возобновляет его и не
возвращает compare/shortlist в запуск. Ниже сохранены прежние критерии для provenance.
Владелец ранее одобрил этот этап первым и включил его в существующий SEARCH-автопилот.
Это ограниченная подготовка внутри `S3_PRODUCT_CURRENT_ACCEPTANCE`; global DS2 mode,
исторический `technical_refactor` и `active_queue_ids` не переключаются.

1. Сверить фактическую карту включений/owners формы, renderer, controller, lifecycle,
   локальных filters/shortlist и CSS с `src/search3/manifest.json`,
   `v2/search3-presentation-v1.php`, bundle и README. Retired provenance slots
   не восстанавливать и не считать вторым активным owner.
2. Проследить форму → URL → результаты/projection → compare → selected → возврат:
   кто хранит состояние, кто инвалидирует старый поиск и где значения теряются.
   Предварительно подтверждённый остаток — отсутствие обратной записи условий в URL.
3. Проверить конкурирующие handlers/rules и дубли display helpers. Первый кандидат —
   существующая operator display identity renderer против raw operator keys в facet;
   затем meal summary против локальной meal classification. Реальные supplier
   identities/значения и различия питания сохраняются.
4. Проверить source/generated/hash и существующие guards. Выполнять маленькие
   обоснованные исправления в текущем owner, удаляя заменённый код в том же пакете.
   Широкие переносы файлов и новый слой не являются результатом этого этапа.
5. CSS-очистку, зависящую от новой композиции, выполнять вместе с соответствующим
   UI-пакетом. Уже claimed #2261 не дублировать; до правки любого shared path читать #4217.

**Выход из подготовительного этапа:** в существующем
[техническом audit](search3-technical-audit-20260912.md) записаны exact base/карта owners,
проверенные контракты, конкретные исправления/CI и оставшиеся ограниченные пакеты либо
точные dependencies. После фиксации completed проход не начинается заново каждый час:
автопилот продолжает незавершённый пункт и затем продуктовый порядок ниже.
Сборка, blocked artifact access или занятый owner не дают права обходить gate;
пока один пакет blocked, выполнять независимый разрешённый SEARCH-шаг.
Refactor не повышает визуальную оценку без exact browser evidence.

| Приоритет | Конкретное улучшение | Проверяемый результат / граница |
| --- | --- | --- |
| 1. Полная основная форма | Сохранить все обязательные поля; улучшить читаемость длинных направлений/отелей, выбор диапазонов и размещение возрастов рядом с туристами | Все primary-поля видимы; значения из URL и после редактирования не теряются; 0/1/2/3 ребёнка и граничные возраста сохраняют исходный mapping; disabled/loading/error понятны. Не переделывать справочники или API |
| 2. Desktop geometry и самостоятельный mobile | Сбалансированные два/несколько рядов, выровненные группы/контролы, соразмерный CTA, без пустых колонок и растянутых child-age; компактность mobile без потери полей | Приёмка точных скриншотов, а не только overflow. Проверять затронутые промежуточные ширины и невысокий desktop; возраста логически и визуально связаны с туристами. Уже принятые сетки не переписывать заново |
| 3. Календарь | Читаемые даты и цены, понятный минимум и выбор дня, ясная связь с условиями формы | Единственный текущий календарь; выбор пользователя сохраняется при rerender. Цена из DB — ранее найденное наблюдение для точного контекста поездки, не live availability/final price; нет данных — не ноль. Изменение условий инвалидирует чужие цены. Выбор даты/локальная фильтрация не запускают supplier search; поиск запускается явным действием |
| 4. Стартовые параметры и result filters | Название отеля, курорт/район, звёздность, питание, цена, туроператор; видимые активные значения, доступный сброс, понятный нулевой результат | Фильтры работают в текущем canonical owner по загруженной выдаче без supplier search; цена/питание/оператор проверяются на одном конкретном offer. При неполных данных facet скрывается/сбрасывается; счётчик не обещает полноту рынка. Нижние фильтры доступны на коротком desktop; mobile использует те же элементы |
| 5. Выдача, карточки, цена/CTA | Иерархия фото → отель/место → полезные факты → условия поездки → цена/действие; убрать повторы; сделать варианты обозримыми | Улучшать отображение уже доступных проверенных фото/рейтинга/местоположения, не изобретать контент. Факты и цена соответствуют representative offer после фильтра. Групповой минимум, конкретная цена, сохранённая цена и требующая проверки цена не смешиваются; source отделён от оператора; понятен следующий шаг |
| 6. Конкретный offer → актуализация → рейсы → preview заявка | Сводка номера/питания/туристов/рейса/состава и возврат к результатам в текущем поиске | Сохранить exact hotel/offer/search identity и выбранные условия. Только подтверждённые данные; неизвестные сборы/багаж/рейсы не объявляются включёнными, estimate не final_verified. Возврат не повторяет уже потреблённый supplier шаг. Handoff сохраняет выбранное предложение и допустимый контактный черновик; согласие не переносится на другой тур. Lead transport/field mapping не менять, реальные заявки не отправлять |
| 7. Накопленная mobile/desktop acceptance | Цельный путь: параметры → локальные фильтры → конкретный offer → актуализация/рейсы → честная цена → preview заявка → возврат/редактирование | Проверить затронутые состояния, длинные названия/суммы, семью, empty/error/partial/stale, клавиатуру и фокус. Physical Safari/iPhone отдельно passed/deferred. Green отдельного пакета не означает качество всего поиска 9.5 |

Рейтинг, пляж/расстояния, тип и услуги становятся result-фасетами только при достаточной
подтверждённой полноте данных и явной политике unknown. Новые фото/описания/отзывы,
новый рыночный календарь, карта, price-watch и уведомления не включаются скрыто:
это отдельные входы INT/content/владельца канала. Их отсутствие не блокирует улучшение
имеющейся карточки, но и не считается решённым CSS-правкой.

### Уже выполненное не считать новой работой

Проверять свежий статус PR и их exact evidence. В частности, #2079 (mobile preferences
и приёмка реального served route) и #2080 (sparse calendar grid) уже имеют release-merge;
старый красный прогон #2079 не является текущим заданием исправить ту же ошибку.
#2076 был no-op для Search3; #2074 затрагивал исключённый compatibility CSS — эти изменения
не являются доказательством улучшения фактически подключённого интерфейса.

История формы/фасетов/selected/shortlist и опубликованных пакетов сохранена по ссылкам
в конце документа. Не объявлять существующие функции отсутствующими по названию файла
или одному fixture-скриншоту. Наличие тестовых данных не доказывает живой supplier availability.

## Неизменная оценка: 12 параметров отдельно

Цель владельца — **не менее 9.5/10 по каждому ключевому параметру**, а не высокий средний
балл за счёт архитектуры или одного зелёного пакета. Параметр ниже цели остаётся открытым
до исправления либо точной внешней зависимости; dependency не означает готовность.

Следующая таблица — фиксированная рубрика версии `SEARCH-QUALITY-1`, не новая текущая
оценка. В каждой строке пять проверяемых критериев, по 0–2 балла каждый. Для каждого:
0 — отсутствует/критически сломан; 1 — работает с существенным ограничением;
1.5 — остаётся конкретное небольшое неудобство; 2 — критерий выполнен в указанном
проверенном scope. `not_measured` не заменяется нулём или двумя: без evidence итог
строки не вычисляется. Дробные баллы — экспертная оценка по рубрике, не измерение конверсии.

| Параметр | Пять неизменных критериев |
| --- | --- |
| Desktop visual | Сетка и выравнивание; плотность/отступы; типографика/длинный текст; размеры CTA и контролов; форма/rail/карточки на промежуточной ширине и коротком экране |
| Mobile visual | Самостоятельная композиция; читаемость без потери полей; доступность действий; длинный контент и семейная форма; отсутствие перекрытий/лишней прокрутки в проверенном браузере |
| Соответствие целевому макету | Структура формы; положение календаря; композиция фильтров/выдачи; иерархия карточек/цены/CTA; согласованный mobile и бренд без недоказанных обещаний |
| Полнота основной формы | Город/направление; даты/ночи; туристы/дети/возраста; регион/отель/категория/питание; доступная цена и корректное разделение primary/advanced без primary-оператора |
| Filters / decision UX | Нужные достоверные facets; активные значения/счётчики; сброс/zero-match recovery; сочетание условий на одном offer; сохранность состояния без supplier calls |
| Карточки и price/CTA | Медиа/название/место; достоверные факты без дублей; условия representative offer; различение минимума/конкретной/сохранённой/непроверенной цены; однозначное доступное действие |
| Календарь | Читаемость дат/цен; геометрия sparse/full набора; правдивый минимум и область покрытия; синхронизация фильтра/догрузки/disclosure; точный выбор даты через существующий submit |
| Compare / shortlist — deferred вне запуска | Историческая рубрика сохранена: добавление/удаление/лимит; обозримые различия; exact identity; историческая цена и недоступность; reload/storage-error/focus recovery. До нового прямого поручения не исполнять и не включать в launch gate |
| Selected tour | Exact offer context; номер/питание/туристы; подтверждённые рейсы либо честный unknown; согласованный итог/состав без новых формул; смена/возврат без подмены тура |
| Lead-form handoff | Правильный выбранный тур; понятное действие и фокус; validation/pending/error; сохранность допустимого черновика и отдельное согласие; контролируемый возврат без реальной отправки |
| Accessibility / state UX | Keyboard/focus; labels/семантика; читаемость/размер целей; loading/empty/error/partial; восстановление и сохранность состояния |
| Архитектурная чистота / стабильность | Один реально подключённый owner; удаление заменённого кода; source/generated/hash; изоляция и protected contracts; регрессии/гонки/повторяемость проверок |

Для каждой новой оценки фиксировать: exact source SHA, release и published status,
артефакт/скриншоты, браузер/ширины/данные/сценарии, пять частных баллов, предыдущую
оценку по этой же рубрике, причину изменения и нерешённую зависимость. Сохранять это
в существующем профильном audit и #1646, не заводить второй state/score tracker.
Изменение рубрики требует версии и пересчёта baseline; две разные методики нельзя
показывать как улучшение или ухудшение продукта.

**Оценки из переписки 8.6 → 8.0 и условные 9.4 у ОТА не являются измеренным baseline.**
Их нельзя использовать для заявления о регрессии или точной разнице 0.8/1.4.
Новая документация, green CI, число PR, перенос кода, непроверенный контент и чужой
незавершённый handoff сами по себе не повышают баллы. Физический Safari/iPhone,
реальная доставка заявки и коммерческий пилот имеют отдельные статусы; непроверенное
не становится passed или произвольным штрафом к unrelated визуалу.

## Сравнение с ОТА и бизнес-результат

Сравнивать одинаковые задачи, даты, состав туристов, устройства и по возможности
одинаковые предложения; различия доступных данных явно учитывать. Сначала:
найти отель → сравнить две поездки → объяснить цену/состав → изменить условия и
вернуться → дойти до формы контакта с нужным предложением. Карточка с неполными
fixture-данными не доказывает плохое покрытие живого каталога.

Документированные ориентиры — Travelata (фильтры, фото/отзывы, условия выбора) и
Level.Travel (варианты/сравнение, календарь, перелёты). Источники прежнего обзора
сохранены в исторической версии плана; перед новым сравнением проверять актуальный UI.
Это не утверждение, что конкурент оценён в 9.4. Внутреннюю архитектуру ОТА без доступа
не оценивать; точный разрыв не публиковать без сопоставимого исследования.

Пользовательский пилот: предложенный порог — минимум 4 из 5 участников выполняют
каждую из пяти задач без подсказки и правильно называют условия/цену. До проведения
`not_measured`; малый пилот не доказывает статистическое превосходство или конверсию.
Скорость измерять на одном корпусе до/после: первый полезный результат, завершение,
реакция фильтра/раскрытия, network/projection/render, cold/warm, размер выборки.
Median/p95 только при достаточных данных. CSS/JS initial/full/inline считать отдельно;
перемещение/lazy-loading не объявлять удалением или ускорением без замера.

Коммерческий эффект — только после отдельного production-допуска, по имеющимся
разрешённым данным с периодом/denominator/источником. Новые цели/события Метрики,
уведомления, онлайн-оплата, бронь/документы/личный кабинет — отдельные согласованные
задачи. Наш текущий целевой результат — выбор и заявка менеджеру, не OTA checkout.

## Исполнение, проверки и публикация

Один связный пакет: fresh short branch → exact claim #4217 → изменение текущего owner
→ source/generated/hash вместе → узкие тесты и применимые Security/owner/isolation
checks → exact artifact → визуальная проверка затронутых widths → merge в release
при green → полезная накопленная публикация → readback/receipt.

Проверять реальный served route и фактически подключённый CSS/JS. Изменение теста
не доказывает его выполнение: сверять invocation/detection и артефакт. При реальном
дефекте исправлять runtime; ошибочный тест исправлять по подтверждённому контракту,
не ослабляя значимые assertions ради зелёного результата. Не восстанавливать retired fixtures.
Для UI нужны точные screenshots; overflow-тестов недостаточно. Каждый пакет проверяет
затронутые ширины/состояния, полная накопленная матрица — отдельный полезный рубеж.
Docs-only: проверка ссылок/согласованности и применимые guards, без asset build/browser/deploy.

Полезный рост разрешён; заменённые owners/rules/handlers удалять в том же пакете.
Большой технический refactor-pass не подменяет продуктовую очередь: сначала доказанный
дефект/польза, затем ограниченное исправление. При blocked-пакете записывать причину
и продолжать независимый SEARCH-шаг. **Не останавливаться после одного PR, если есть
время и следующий безопасный шаг.** Не повторять отказы инструментов, trigger-only
commits, завершённые supplier operations или публикацию без проверки её фактического исхода.

`checked`, `merged`, `preview-published`, `production-approved` — разные состояния.
Активация preview с успешными HTTP checks, но красным финальным verification step,
не считается полностью зелёной публикацией; сначала зафиксировать receipt и точный
остаточный read-only verification, а не повторять deploy.

Публиковать только `/_preview/search3-site-candidate/`, один свободный publisher,
штатный exact artifact, noindex, disabled real leads, counter0, rollback и fingerprints.
Production/main не менять, кроме отдельно уже одобренной preview infrastructure.
#1334/#1493 production migration требует отдельного явного согласования конкретной версии.
Tourvisor/API URL/payload, price arithmetic, lead transport/field mapping, Metrika/goals,
analytics, catalog/manual mappings, server config и production SEO-indexation не менять.
Не отправлять реальные заявки. Полная готовность INT/MATCH не блокирует независимый SEARCH.


## Живой аудит 2026-09-12: ближайшие пакеты до 9.5

[Дополнение к существующему техническому/визуальному аудиту](search3-technical-audit-20260912.md)
содержит exact source/release/publication pins, реальные browser-сценарии,
пять компонент SEARCH-QUALITY-1 по каждой оси, найденные причины и acceptance matrix.
Это более позднее evidence, не вторая очередь или state tracker.

Осмотренный published desktop: полнота формы **8.0/10**, filters/decision **8.0/10**,
карточки/price/CTA **6.5/10** в указанном там scope. По остальным строкам полного
измерения нет; особенно mobile и соответствие недоступному approved image —
`not_measured`. Не переносить эти оценки на ещё не опубликованный release.

Ближайшая конкретизация действующего порядка:

1. Полный primary flow и постоянный контекст поездки: после submit сейчас скрыта
   вся форма, URL не хранит условия, hotel chooser ограничен загруженным списком.
   Исправлять существующие owners; полнота нового каталога — внешняя dependency.
2. Принять текущую desktop-family работу [#2261](https://github.com/pyatkoff/poisk-turov-test/pull/2261),
   включая три возраста, 1199/1200 и невысокий desktop; не создавать второго CSS writer.
3. Календарь: 3-day live grid, локальные минимумы и точная дата проверены;
   добрать sparse/full/mobile/unknown/transient-zero corpus. Не повторять #2080
   и не считать однодневное скрытие новым дефектом без изменения требования.
4. В существующем lifecycle обеспечить URL round-trip/reload/Back; в текущих
   facets переиспользовать reviewed operator display identity, улучшить точный
   бюджет и согласованность meal labels. Local filters — ноль supplier searches.
5. Структурно уменьшить пустоты cards/expanded offers, убрать ложные counts
   одинакового питания, объяснять readiness минимальной цены до раскрытия.
   Уже merged #2246 и другие density-пакеты учитывать в свежем baseline.
6. Compare: правильная сетка 1/2/3, раскрытие и восстановление условий сохранённого
   снимка; selected не оттесняется полным compare. Не переиспользовать stale identity.
7. Selected/lead: ясные unknown fee/flight labels и контекст возле контактов;
   учесть уже merged #2204 и fresh controller/date claim. Проверки ошибок/draft/
   consent через controlled fixtures, без реальных заявок и новых price/lead contracts.
8. Накопленная mobile/desktop/mockup/a11y приёмка — все 12 строк отдельно ≥9.5.
   Отсутствующий reference, непроверенный mobile или внешний handoff не считать passed.

Publisher SSH reset и недоступные для просмотра mobile/reference artifacts отмечены
как точные зависимости в audit. Они не запрещают независимый SEARCH-пакет,
но не позволяют заявить публикацию/визуальную приёмку. Этот docs-only план
не запускает supplier operations, build/deploy или production migration.

## История и актуальные доказательства

- [План до этой актуализации, immutable release 827fb8ab](https://github.com/pyatkoff/poisk-turov-test/blob/827fb8abc0b68cf6fa2ac6d9e1f6f391d72fe85c/docs/project/search3-product-development-plan.md): прежние этапы, опубликованные пакеты, исходные источники сравнения ОТА и dated evidence сохранены в Git без переписывания истории.
- [Архив до 2026-09-09](search3-product-development-history-20260909.md).
- [SEARCH — детализация существующих сценариев](anytour-roadmap/search.md).
- Текущий claim, checked/merged/published pins и blockers — свежие #4217/#1646 и профильный audit; не копировать сюда новый конкурирующий checkpoint.

Смена формулировок плана не утверждает, что перечисленные улучшения уже реализованы,
опубликованы или достигли 9.5. Следующий пакет выбирается по текущему дефекту/evidence,
а не по старому неисполненному `next_action`.

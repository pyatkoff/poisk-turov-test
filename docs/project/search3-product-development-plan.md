# Search3 — продуктовый план и критерии качества

## Текущая очередь Search3 — 26.10.2026

Актуализировано 27.10.2026 после публикации PR4946. Один исполняемый план для
`S3_PRODUCT_CURRENT_ACCEPTANCE`; статус
`NEXT_ORDINARY_UI_ACCEPTED_EXTERNAL_GATES_REMAIN` сохраняется.
[SEARCH1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646) ведёт пользовательский результат;
[4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709) хранит владельца, claims и terminal.
Исторические checkpoint, `resume_evidence` и старые следующие действия не являются заданиями.

**Установленный ORIGINAL NEXT:** [поисковик](https://anytoour.ru/_preview/search3-next-candidate/visual-search/),
source `62a7f3d08fcc9a12151ceb97a40de7aba0d41218` → runtime release
`48563aadfee26af0c975b78dfd00952c2c66ee46`.
[Публикация и RELEASE](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451707739),
[проверка установленного текста](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451787711).
PR4946 исправил два пояснения: выбранный тур передаётся менеджеру после перехода
в Telegram/MAX и запуска бота. Бронирование и оплата на сайте не выполняются.
Согласованная композиция — прежний Site226 / PR4909; нового design-кандидата нет.
Историческая оценка9.5/9.5 относится только к принятому compiled ordinary430/1280,
включая720. Новая публикация и документация не повышают её и не доказывают готовность всего поиска.

### Один ближайший пользовательский результат

Выбранный тур → запуск бота по подписанной ссылке → карточка того же тура у туриста →
те же условия и нужный менеджер в Manager2. Уже установленный bot1202 поддерживает
карточку, в том числе для первого посетителя; естественное получение новой карточки
ещё не подтверждено. SEARCH ведёт тот же пакет, INT — только подтверждённый новый
backend/price факт. Второй исполнитель или параллельная серия поиска не создаются.

| Пакет | Текущее состояние и следующее действие | Условие завершения |
| --- | --- | --- |
| Поиск → точный тур → новая карточка в боте | Существующий `search3-post1202-native-preview-20261027`, [владелец и checkpoint](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451184937): один Find дал188отелей/409вариантов, затем исходная сессия стала недоступна. Quote/freeze/CREATE/native/send0, новой ссылки нет. Исполнение RELEASED; сохраняются identity, точное состояние и резерв. Следующий шаг — пассивно восстановить только ту же сессию у исходного исполнителя, если она вернётся | Только фактический результат того же case подтверждает тур, полный состав, цену с её статусом, native-карточку и Manager2/менеджера. Пока исходная сессия недоступна — конкретный environment blocker; не повторять Find, не запускать другую платформу/дату/профиль как обход и не реконструировать ссылку |
| Цена и приём выбранных условий | [Принятый INT-контракт](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6445236990) допускает честно обозначенную сохранённую TV-цену со сроком и basis, `checked`, `finalPriceVerified=false`, `browser-observation-only`. Freeze не продлевает срок. Old MAX119887₽ [уже истёк и consumed](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451325789); это не цена нового case | Сохранены exact offer/party/search/pair и квалификация цены. Более сильный `final_verified` требует реального подходящего supplier proof и своего принятого контракта. Native entry, сообщение туриста, получение менеджером и physical phone имеют отдельные фактические receipts; установка bot1202 сама по себе их не доказывает |
| Пояснения и публикация | PR4946 source/4checks/парные compiled430×900 и1280×720 приняты; один штатный NEXT publisher40570004014/attempt1 завершён, predecessor сохранён. Installed DOM-readback1363×936 подтвердил обе строки и отсутствие overflow без Find | Закрыто в указанном scope. Compiled430/1280 не объявляются новым native/physical проходом. Не повторять сборку, публикацию или source/frame-приёмку ради документа |
| Ускорение CI | [PR4931](https://github.com/pyatkoff/poisk-turov-test/pull/4931) и [PR4945/terminal](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6450802576) завершены. Системная подготовка в сравненных запусках668→15с; локальная обработка одного корпуса786кадров/963файлов156.6→91.5с, все выходные байты одинаковы | Применимые exact-source проверки пройдены, прежнее покрытие сохранено. Это отдельные наблюдения подготовки и encoder, не среднее/p95/обещание42% ускорения всего CI или поиска. Закрытые прогоны и corpus не повторять |

Блокер старого ANEX6418719912 больше не задаёт следующий шаг всего SEARCH:
его операция consumed/no-replay, а текущая граница и её неизвестные факты описаны выше.
При конкретном новом backend-дефекте INT получает один точный handoff; отсутствие
нового факта не создаёт задания на ещё один collector/reader или supplier-пробу.
Независимое существенное frontend-исправление допускается по подтверждённому дефекту,
без перезапуска принятого corpus и косметической очереди.

### Три этапа готовности

| Этап | Состояние |
| --- | --- |
| Согласованный ordinary UI | Принят в своих исторических exact scopes. PR4946 установлен; оценки прежнего corpus и compiled/frame/publication/readback различаются |
| Функциональный NEXT | Открыт: естественное получение новой bot1202-карточки и того же Manager2 результата не подтверждено. Physical phone отдельно. Прямая цена `checked/false` не становится `final_verified` от отправки в бот |
| Production и коммерческий пилот | Открыты: конкретная полная версия, применимые gates и отдельное одобрение владельца. Производственный эффект не измерен; цели Метрики и lead-механизм не меняются |

### Действующий порядок продолжения

Для публикации **ORIGINAL NEXT** уже существует штатный
`.github/workflows/update-search3-local-preview.yml` на main: созданная OWNER-команда
`/update-search3-next-preview <source> <release> <build_run> <artifact> <previous_source>`
в4709 связывается с фиксированным NEXT target, проверяет predecessor и сохраняет rollback.
[Маршрутизация проверена по текущему main](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451605153).
`Publish checked Search3 site preview` — другой carrier; ожидание его browser dispatch
не блокирует уже допущенную NEXT-команду. Новый publisher/allowlist/transport не нужен.
Перед каждой будущей публикацией заново проверить текущие source/release/claims и
применимые exact-source gates; завершённую4946 не отправлять повторно.

Standing [6323725979](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323725979)
сохраняет автономные обоснованные проверки в stock limits с конечным scope/budget/claim.
5000/сутки — бюджет разработки и тестов данного исполнителя, не общий лимит пользователей
или измеренный баланс. Текущий консервативный учёт4640 включает прежние UNKNOWN,
остановленный резерв1536 и32на pristine bootstrap; это не фактический расход/остаток/refund.
Чужие и consumed/UNKNOWN/refused операции не повторять и не переименовывать.
Paid fallback, realLead/booking, protected contracts и production сохраняют отдельные допуски.
Keyboard/Tab/A11y-only запрещены; новые размеры/200%/Compare deferred, существующий обязательный CI сохранён.

**Обновление:** текущий результат и один следующий шаг — здесь; один terminal/RELEASE
со ссылками — в4709. STATE хранит текущие pins и ссылки. История ниже не переписывается.
Расписания, модели, enabled/paused и чужие задачи не меняются.


## Исторический checkpoint PR4927 — 26.10.2026

Следующий сохранённый блок — точный прежний checkpoint, до улучшения плана.
Его формулировки «следующий результат» и прежние references — история, не очередь.
Актуальные действия определяет верхний раздел и свежие события4709.

<details>
<summary>Прежний checkpoint и полный набор доказательств — без изменений</summary>

## Текущий проверенный checkpoint — 26.10.2026, ORIGINAL NEXT

Тот же `S3_PRODUCT_CURRENT_ACCEPTANCE`, статус `NEXT_ORDINARY_UI_ACCEPTED_EXTERNAL_GATES_REMAIN`, SEARCH [#1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646), координация [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709). Обычный compiled UI-этап430/1280, включая1280×720, принят в точном scope; полная поставка, physical и production остаются отдельными gates.

| Уже закрыто | Точная основа и предел доказательства |
| --- | --- |
| Действующий установленный NEXT | PR4926/source `03638572ed7634797fe690f774282e288c06c4b7` → runtime release `e9b2bc741da26107cd5878a492739c0de3a837e1`, [terminal6417128363](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6417128363), [readback6417167272](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6417167272). All4 applicable exact-source gates SUCCESS; build40325298693/artifact12649769394 → stock40329694345/receipt12653472504.845files/43visualassets/9routes200, production/sibling unchanged, predecessor5c9c retained. Installed `messenger-flow-v1.js?v=579e307c0a45` matches exact compiled hash. Source/CI/deploy/readback не новый UI-балл |
| Упрощение обычного пути — принятый PR4909 | Site226 → source `6cb2592417f121ca8566f32bea798aa11ee24d5e`; убран дублирующий экран параметров, room→contact2→1 при прежнем explicit new-price acceptance. [Audit6404297016](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6404297016): compiled430/1280incl720 **9.5/9.5** с5reasons в каждом exactscope, не wholeSearch/physical. [Installed6406164959](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6406164959): SAME offline price-change186400→195400→contacts→Back/continue, draft retained. CONSUMED/NO_REPLAY |
| Отказ перехода в бот — принятый PR4920 | Понятное фиксированное объяснение/телефон/Back; Check только для status существующей операции; тот же failed selector не повторяет token/select/freeze. [Audit6409669527](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6409669527):6affected compiledPNG430/1280short + bounded installed offlinefamily1363×936 contact/draft/Back/continue,186400за2A+[0,8], consentfalse/submit0. Synthetic отказ не причина historicalphone и не natural installed proof. Ordinary9.5/9.5 unchanged delta0; fullerror/realdelivery N без суммы |
| Повторное открытие контактов во время pending preparation — PR4926 | Подтверждённая гонка двух fresh capture одного selector исправлена в существующем lifecycle owner: Back/reopen использует один token/select/freeze и сохраняет failure/draft. Source32flowgroups;10 affected compiled430/1280 frames via7PNG; installed DOM-only readback совпал с hash. Fictional deferred request, supplier0/CREATE0/lead0; natural delivery/physical/native430/production и historical TV-after-filter cause не доказаны. Композиция и ordinary9.5/9.5 неизменны, UI delta0 |
| Исторический UI baseline3d7 до последующих пакетов | Baseline source `3d7d50d17aec24cecc74fb25d04ee7015601daf1`, [audit6319858062](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6319858062):24 matched compiledPNG+receipts, Desktop9.5/Mobile10/Filters10/Selected10/Calendar9.5, FIRST baselines/UI0. Это не native430/physical и не wholeSearch9.5 |
| Прежнее исправление PR4811 — статус цены номера | PR4811/source `15d1e2947a72d80a336434fcec8cebb90970a570` → runtime release `0683a51ff0c72e347eb8a4c02c2fd69b179f510c`, appSHA256 `a45dbe60335e51cf5c4b549c2bfb51fa813e5cf544553b9ce001378766716431`, hotel-detailsSHA256 `11181d9845ef1d2e373283adb32f761bf1826969d55f40109147849eb5d51850`, dataSHA256 `c3c755d92c389a6e3496941eede2ba3a34364fcbbd92bf69ab834937672c1d92` unchanged. Все5 final-source checks и один stock39742252376 приняты; [publisher6335567621](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6335567621). [Terminal/RELEASE6335661236](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6335661236), [audit6335648109](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6335648109): при сравнении и выборе номера сохраняется пометка происхождения/неподтверждённости цены; сумма за всех, exact key и выбранная строка сохраняются после «К отелю». Compiled430/1280/short720 и installed offline1363×936 проверены отдельно; bounded C4 1.5→2, полная строка N, baseline3d7/quality без изменений |
| Предыдущее исправление PR4796 — фильтры | PR4796/source `9f8e14c8d3a60bbd6e26a1c3c7d9134cac953d64` → runtime release `c44a30c19a207005a9888b00429b84d49c83e3ee`, appSHA256 `e493b58973369146519b0f4d8e1ff640a6250c426899b24d90bffa885ed13f98`, dataSHA256 `c3c755d92c389a6e3496941eede2ba3a34364fcbbd92bf69ab834937672c1d92`. Все5 final-source checks и один stock39662545481 приняты. [Terminal/RELEASE6324876115](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6324876115), [audit6324888881](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6324888881): глубокая desktop-фильтрация и Reset открывают начало новых результатов; compiled1280×900/720 и installed snapshot1363×936 проверены отдельно, без нового полного балла |
| Реальный обычный путь и семья | [ANEX146015/2A6319827377](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6319827377): выбранная пара/контактный черновик/возврат. [Семья6321337269](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6321337269):2A+[5,5]/SU420-SU421/230151₽ за4/контакт без ввода/возврат. [Charter131890/2A6323775660](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323775660):одна returned pair/контакт→Back. Разные exact scopes; realLead0, не physical/infant/fuel breakdown/globalfinalverified |
| ANEX receiving и две реальные пары | SOURCE receiving [6323462398](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323462398) закрыт. Единственный INT case [6324054718](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6324054718): A→B→cachedA, обе153695₽ за2A, фактический quote receipt0→4→6→6. SEARCH принял ту же UI-границу в6324888881, без второго supplier run/source audit. CONSUMED/NO_REPLAY; не all40/infant/fuel/expiry-boundary proof |
| Выбор конкретных Rixos — форма | [6325145066](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6325145066): actual canonical queryRix, local684Belek+687Tekirova, Apply→reopen→изменение без Apply→Close→reopen сохраняют оба/OR. Installed1363×936, Find/quote0; это pre-submit draft, не alias authority/фактические submitted IDs/native430/physical. Список10 подсказок не полнота каталога и не лимит offercards |
| TV410 — прежнее принятое исправление | Sourceb8c/runtimec899/[terminal6322744838](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6322744838) +[receiving6322830603](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6322830603): истёкшая exact котировка/session теряют authority, повтор0HTTP; PR4796/9f8 сохранил data c3c. Новый provider410 не провоцируется, старый source не текущий runtime |
| Исторический просмотр Site205 | Тот же PUBLIC project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, Site205/source74490263/[6317776266](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6317776266): LIVE открывает top-level NEXT, iframe — demo. Site203 остаётся согласованным product reference |

**Действующее разрешение:** прямое21.10.2026 [6323725979](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323725979) — обоснованные разовые проверки и серии, включая live/quote/repeated ANEX, автономно в stock limits с конечным scope/physical budget/reservation/claim; повторного per-check/per-series вопроса нет. Старый repeated-check approval-wait заменён. Consumed/UNKNOWN/отклонённые операции не повторять/не переименовывать; paid fallback, realLead/booking, production и защищённые изменения сохраняют отдельные допуски. Квоты/config не расширяются.

**Следующий результат:** собственная UI-граница ОДНОГО нового принятого natural INT handoff/executor/case/receipt либо связное исправление нового существенного подтверждённого frontend-дефекта. Пакет4926 fresh-capture pending-contact race завершён: SOURCE036385/RELEASEe9b2, terminal6417128363, DOM-readback6417167272; его source/CI/publisher/readback/fictional affected case CONSUMED_NO_REPLAY. INT владеет supplier/backend/price/stage facts; SEARCH не запускает параллельную диагностику, подготовку контракта или supplier-серию. Пока нового результата/дефекта нет, закрытые пакеты4920/4926, ordinary UI corpus и supplier/browser cases не повторять и косметический пакет не создавать. Native430/physical, canonical aliases/actual submittedIDs, natural failure/real delivery/LeadC3 и production сохраняют отдельные owner/gates; wholeSearch не DONE. Standing6323725979 разрешает обоснованные проверки/серии в stock limits с конечным scope/budget/reservation/claim без per-check/per-series вопроса; consumed/UNKNOWN/отклонённое не повторять, paid fallback/realLead/booking/protected/prod отдельно.

**Разделение:** SEARCH — форма/выдача/фильтры/отображение цены/выбор/контакт/возврат; INT — supplier transport/auth/backend/price authority/физические stage facts. Один case/executor/receipt на общей границе; второй потребляет его, review ограничен delta. LiveTV+directANEX, SAMO paused; fuel0 только SAMO/search/Biblio, не Tourvisor/directANEX/final price. Missing/pending/expired не0/final; чужая цена не подтверждает новый выбор, fuel/markup/APD не начисляются повторно.

Оба пути заявки сохраняются: актуальная цена тура позволяет оставить рейсы менеджеру; optional exact pair требует собственной цены за всю группу. Keyboard/Tab/A11y-only запрещены; другие размеры/200%/Compare deferred. Native/physical/alias/submittedIDs/real delivery/production не подменяются compiled, form, source или частным баллом. История/snapshots/root next_action не исполняемая очередь. Собственная автоматизация и чужие задачи/модели/расписания не меняются. Этот factual sync фиксирует принятые4909/4920/4926; UI delta0. Публикация и один DOM-only readback относятся к завершённому пакету4926; сам docs-sync не запускает browser/build/publisher/runtime. Ссылки ниже сохраняют свои исторические exact scopes и не возобновляют операции.

</details>

## История и актуальные доказательства

- [Полный план до чистки — 21.10.2026](search3-product-development-plan-history-20261021.md): все прежние checkpoint, решения, no-replay, PR/SHA, оценки и evidence сохранены дословно; это архив, а не очередь.
- [Архив до 09.09.2026](search3-product-development-history-20260909.md).
- [Подробные сценарии SEARCH](anytour-roadmap/search.md) — backlog, не вторая копия текущей задачи.
- Активные claims и события после checkpoint — [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709), SEARCH [#1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646) и `AUTOPILOT_STATE.json.current_task` свежей release. Датированный архив не возобновляет закрытые операции.

## Исполняемый порядок — решение владельца 19.10.2026

### Конечный результат и рабочие размеры

Обычный путь: параметры → выдача/фильтры → отель/варианты → точный тур и итог за всю
группу → необязательные рейсы → контакты → возврат с сохранением выбора и допустимого
черновика. Текущий UI-этап — 430/mobile и1280/desktop, включая1280×720.
Другие размеры,200% текста и Compare/shortlist deferred. Завершённые retained
360/390/768 и обычный compiled430/1280 corpus не становятся новой очередью.
Существующие обязательные final-source CI сохраняются.

### Основная поставка — существующий NEXT

Целевой runtime: [ORIGINAL NEXT](https://anytoour.ru/_preview/search3-next-candidate/visual-search/),
repo `pyatkoff/poisk-turov-test`, branch `release/search3-production-ready-v1`.
Design-lab: тот же PUBLIC project `appgprj_6abcd9299ef88191bbce1fc1bf8c767a`, история
и аудитория сохраняются. Текущая согласованная композиция — Site226/PR4909; Site203/source8c826b44 и
[6270421744](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6270421744) — история.
Новая композиция требует конкретного whole-version visual+behavior approval.
Site205: LIVE открывает top-level NEXT, iframe — demo. Не возвращать cross-site live iframe.
Foreign `release/search3-design-v2` и его preview принадлежат другому исполнителю;
shared paths требуют свободного claim независимо от URL/ветки.

### Следующее действие

Следовать верхней [текущей очереди](#текущая-очередь-search3--26102026) и свежему terminal/claim4709.
Таблица зависимостей ниже уточняет отдельные gates, не заменяет ближайший результат.
При расхождении dated checkpoint и нового события сверять source/CI/установку отдельно.
Source-ready не означает installed; старые Find/quote/публикации/UNKNOWN не повторять.

## Действующее поручение и границы

**Текущий исполняемый интерфейс — только существующий
[`/_preview/search3-next-candidate/visual-search/`](https://anytoour.ru/_preview/search3-next-candidate/visual-search/).**
Перед выбором пакета доказать, что target действительно подключает изменяемый owner:
`v2/visual-search/index.php` загружает `v2/visual-search/app.js`.
`v2/prototype-search/app.js` — отдельный интерфейс; его исправление не продвигает
приёмку target. Общие data/lifecycle/calendar/lead зависимости менять только после
подтверждения подключения и свежей передачи владения в #4709. Исторические пути
DS2/renderer/controller ниже не заменяют эту проверку.

Очередь запуска: основной поиск → выдача → конкретный offer → актуализация → рейсы →
честная цена → preview заявка и возврат. Отдельные «Мой тур»/saved-tour, избранное и
сравнение отложены до нового прямого поручения; скрытые target-кнопки не возвращать.
Выбранное предложение в текущем поиске и его возврат остаются в этой очереди.
Визуальный refactor, MATCH и INT fuel-learning не входят в неё.

Live выдача: Tourvisor + direct ANEX; SAMO/Andromeda paused по решению08.10.2026. DB offers/prices служат
календарю, истории и SEO; поток live → DB, никогда DB → live. Локальные профили,
фото и matching сохраняются. Первый поиск — один логический старт источника и
первые порции активных источников, direct dates первоначально первая неделя; продолжение
только явной кнопкой с бюджетом. Unknown не zero, estimate не final_verified;
SEARCH не вводит собственную формулу топлива.

Полноценная OTA-форма имеет приоритет над старой компактной формой.
Будущая production `/poisk-turov/`/PR #1334 требует отдельного допуска; работа
над preview, зелёный CI и локальная проверка заявки не являются таким допуском.
Минималистичная форма допустима на главной, но её развитие принадлежит SITE #1719.
В этой очереди исполняются только SEARCH #1646 и координация #4709. SITE #1719,
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

### Сохраняемые решения по интерфейсу и данным

- Форма: один canonical select, поиск доступного города; текущие/недавние сверху,
  остальные по алфавиту. Смена города не запускает supplier search. Страны — в
  устойчивом редакционном порядке, курорты — по принятым LOCAL parentId/kind.
  Питание: любое, AI, UAI, завтраки, полупансион, полный пансион, без питания;
  дополнительные подтверждённые варианты сохраняются, выбор не меняет порядок.
- Calendar history: только принятые meal mappings; последние точные сегменты,
  полный состав туристов, питание/регион и включительный бюджет «От/До» совместно.
  Старый более дешёвый snapshot не заменяет последнюю цену вне бюджета.
  Нет подходящей истории — unknown без автоматического supplier search.
- Выдача: явное «Изменить поиск», локальные applied filters/счётчики/сброс;
  без повторного ряда звёзд/питания/бюджета. Одна полоса семи дат/цен с месяцем/годом
  открывает полный календарь. После окончания поиска большая плашка исчезает,
  partial получает короткое честное предупреждение; свёрнутый календарь без пустот/дублей.
- Desktop — левая колонка фильтров и доступные действия на коротком экране;
  mobile — одно раскрытие с Apply/Cancel, без перекрытий контента и CTA.
  Карточка сохраняет крупное полноширинное mobile фото1.8 и открытие/возврат;
  даты · ночи · питание рядом с ценой/CTA принадлежат тому же exact offer.
- Номер/питание/условия → актуализация/необязательные рейсы → контакты.
  Одна сводка и область цены; минимум, конкретная/сохранённая/pending цена различимы.
  Изменение итога после выбора рейса видно; багаж относится к конкретным рейсам,
  непроверенные условия/отзывы/рейтинг/преимущества не выдумываются.
- Обычный возврат сохраняет filters/date/sort/room/exactIDs, OR exactIDs,
  draft/apply/cancel, выбранный тур/рейс и контакты. Смена identity сбрасывает consent;
  повторы навигации не запрашивают supplier заново. Семьи/длинные имена остаются читаемыми.
- Контакты: понятные labels/ошибки/согласие и доступные цели44px; заявка менеджеру
  без обещаний брони/оплаты/мгновенного подтверждения. Единые бренд/типографика/сетка,
  оригинальный AnyTour logo неизменен; замещённые handlers/стили удаляются с заменой.
  Отдельные keyboard/focus проверки не назначаются: действует поздний запрет владельца.

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

Действующий остаток после установленного PR4926/source036385/runtimee9b2 и принятых4909/4920. Закрытые shared A/B/A, form-only684/687 и все прежние runtime/cases сохраняют exact evidence/no-replay; это не новое задание. Ближайший результат — верхняя текущая очередь.

| Область / состояние | Ответственный | Следующее действие | Доказательство закрытия |
| --- | --- | --- | --- |
| Native430 / physicalSafari / safearea — отдельные N | SEARCH + QA при штатной capability | Только фактически доступный native/physical ordinary путь; не повторять принятый compiled430/1280 corpus или закрытые supplier cases | Свой exact native/physical факт; compiled/iframe/technical1363×936 его не подменяют |
| Rix: form684/687 принята; aliases / actual submittedIDs — N | LOCAL — canonical факты; SEARCH — применение | При accepted canonical alias/handoff и штатном безопасном actual-ID receipt проверить применение в новом обоснованном ordinary intent; не запускать supplier-пробу ради поиска наблюдаемости | Canonical alias и фактические OR submittedIDs в своих scopes. Pre-submit draft/DOMlocal IDs/список10 подсказок не wire/full-inventory proof |
| ANEX SOURCE и bounded A/B/cachedA закрыты; family/infant отдельный текущий INT scope | INT#2506; SEARCH — только consumer | Следовать свежему активному INT2506/4709 и его текущему claim; старый owner6325033841 не исполняемая команда. Один допустимый собственный family/infant результат принимает SEARCH только на UI-границе; receiving6323462398/двухпарный6324054718 не повторять | Exact full-party/infant/currency/whole-gross safe facts от INT; существующая двухпарная приёмка не доказывает infants/fuel/expiry или все40пар |
| LeadC3 / реальная доставка — N, preview delivery выключена | Owner-reviewed lead transport; SEARCH — consumer | После точного допуска защищённого scope проверить штатные pending/error/retry/success; локальный контакт/возврат отдельно | Свой real-delivery receipt; UI/quote expiry/preview validation его не доказывают |
| Production — отдельное approval | Владелец + штатный publisher | Предъявить конкретную полную поставку после применимых gates; публиковать только по отдельному approval | Exact approved artifact/receipt/runtime. Текущий installed036385/runtimee9b2, прежние9f8/art12373527335 и baseline3d7 не получают production-допуск от плана |

Остальные применимые N сохраняют конкретный пробел в существующем SEARCH-QUALITY-1. Они проверяются в связанном пользовательском пути при доступном разрешённом scope; отдельная косметическая/QA-only очередь не возникает. Один внешний gate не останавливает независимый разрешённый шаг. Keyboard запрещён;200%/Compare/другие размеры deferred.

### Маршрут проверки и действующие разрешения

Использовать сохранённые exact-source compiled430/1280 evidence и реально доступную
штатную capability. CloudChrome доказывает свой installed desktop scope, не native430
или physicalSafari. Demo iframe, compiled и physical не подменяют друг друга.
Мышь/тапы — основной путь; keyboard запрет и отдельные внешние gates сохраняются.
TinyFish metered; consumed6279010055 не повторять. Нет нового платного fallback,
preview server/browser install, proxy или смены carrier ради обхода.

Постоянное разрешение владельца21.10.2026 [6323725979](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6323725979) заменяет старое ожидание per-check/per-series approval, включая repeated ANEX. Обоснованные разовые проверки и серии выполняются автономно в штатном executor/stock UI с конечным scope/physical budget/reservation/claim до расхода. SharedTVANEX3000/сутки, directANEX60/мин, Andromeda5млн/месяц и реальные account/rate guards сохраняются; owner-reported5000 не измеренный остаток и не изменение config. Consumed/UNKNOWN/отклонённые операции не повторяются/не переименовываются; auto-drain/платный fallback/реальная заявка/booking/production и защищённые изменения требуют своих отдельных допусков. Один INT/SEARCH case/исполнитель/receipt, без параллельной копии supplier diagnosis или полного аудита.

### Организация каждого прохода

- Один продуктовый WIP и максимум один ещё не просмотренный visual candidate.
  Один writer точных paths, один интегратор; независимый read-only review
  параллельно. Отдельный publisher — только для готового изменённого artifact.
- На старте освежить heads/PR/CI/claims, текущие сообщения #4709/#1646/#1719,
  верхний исполняемый раздел и `current_task`. Для уже полностью прочитанных
  инструкций/consumer map сравнить SHA: неизменённый текст переиспользовать;
  изменённые и новые scoped-инструкции прочитать до действия.
- До записи закрепить в существующем claim target/project/branch/exact paths,
  исходный source, ожидаемый пользовательский результат, доступный путь проверки,
  минимальные кадры/действия и непокрытый scope. Claims не истекают по давности.
- Перед новой продуктовой сборкой согласовать корневой AUTOPILOT.md и другие общие файлы с их writer; зафиксировать их версии. Перед публикацией проверить source/release projection тем же штатным правилом. Изменение общего файла после build требует честно согласованного пакета, а не ослабления guard или повтора sealed команды. Docs-only/state sync не запускает build/publisher.
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

Один значимый результат: #4709 — claim/terminal/RELEASE и следующий шаг;
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
обоснованные live-проверки/серии следуют standing6323725979 и конечному budget/reservation/stockexecutor без нового per-check/per-series вопроса. Paid fallback/realLead/production и защищённые изменения сохраняют отдельный exact допуск.
Расписания, enabled/paused, модели, настройки и другие задания не изменяются.

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


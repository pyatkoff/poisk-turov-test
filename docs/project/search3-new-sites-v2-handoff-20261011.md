# Новый AnyTour Sites v2 → Search3: подготовленный контракт переноса

Дата: 11.10.2026. Статус: **PREPARED — представление и взаимодействия сопоставлены с существующими владельцами; код NEXT ещё не изменён и не опубликован**. Очередь SEARCH [#1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646), SITE/design [#1719](https://github.com/pyatkoff/poisk-turov-test/issues/1719), координация [#4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217). Scoped claim [6110240685](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6110240685).

## Конкретная дизайн-основа и границы решения

В текущем диалоге владелец потребовал создать **отдельный новый Sites**, сохранив прежний design-lab и Search3. После просмотра новой desktop-версии и четырёх мобильных кадров владелец поручил подготовить перенос в Search3. Основа этой подготовки:

| Параметр | Зафиксированное значение |
| --- | --- |
| Новый сайт | [AnyTour Search — final](https://anytour-search-design-final.nifty-fawn-6604.chatgpt.site) |
| Мобильный просмотр | [390 px](https://anytour-search-design-final.nifty-fawn-6604.chatgpt.site/mobile-preview?width=390) |
| Project | `appgprj_6acb7b6ce6e0819191db8389fd7b3fca` |
| Версия | 2 |
| Native version | `appgprj_6acb7b6ce6e0819191db8389fd7b3fca~appgver_2d5e899f4af48191b0f7fc50c5ec1c24` |
| Source commit | `00fe6734c08acf234756bb59e5b61dbfecf41941` |
| Состав представления | `src/anytour-fragment.html`, b4; отдельный `src/product-desktop.css` |
| SHA256 исходного fragment | `cc4dde79750e45cbb9e236fd909e9050c44ab0d14c68ad9b5470b9d368c3e2fb` |
| Release для read-only сопоставления | `037b58086dec5166ef485f4cea3992d79f9b2d8b` |
| Последний установленный NEXT source на момент подготовки | `3724eabf9a0dd94cf68af61e6561affc5ae7cbec` |

До любого implementation packet перечитать свежие release head, claims и NEXT receipt. Это снимок для подготовки, не вечный deploy predecessor.

Прежний [design-lab](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site), его история, активный claim6109872618, quality51/53 и [исторический handoff Sites100](search3-design-handoff.md) сохранены. Поручение текущего диалога о новом отдельном Site имеет приоритет для этой подготовки над более ранней строкой roadmap «продолжать существующий публичный проект». Этот документ не объявляет все варианты старого проекта утверждёнными, не меняет общий roadmap и не создаёт новую систему оценки.

Цель кодового этапа — перенести цельное представление на существующий NEXT, сохраняя его реальные данные и канонические действия. Макет не является новым поисковым runtime. Подготовка не даёт разрешения переключить main/production или отправить реальную заявку.

## Бренд и композиция

Сохранить точный принятый логотип, его пропорции и Aeroport 400/500/700. Уже имеющиеся фирменные assets предпочтительнее реконструкции из текста. Основной домен — `anytoour.ru`. Базовая палитра: blue `#2743CB`, light blue `#6F99FF`, orange `#FF510C`; новый reference использует page `#F5F7FB`, paper `#FFFFFF`, body `#182844`, muted `#657186`, border `#E4E8F1`, soft blue `#EEF1FD`.

Основные параметры сильнее дополнительных пожеланий; главное действие оранжевое, выбор и подтверждение редактора синие. Desktop даты/ночи/туристы стоят в одной строке при ширине контейнера ≥740 px; редактор расположен ниже. Mobile сохраняет принятую самостоятельную композицию. Не переносить prototype class aliases и desktop override механически в действующий каскад NEXT: заменить затронутые правила в текущем owner.

В product UI не переносить «Состояния», переключатель устройств/ширины, симуляцию вкладок и служебные пояснения лаборатории. Избранное и сравнение остаются под существующими paused/deferred gates; сердечки из прототипа не активируют эти функции.

## Проверенные seams действующего NEXT

Вход живого NEXT — `v2/visual-search/index.php`, который собирает существующий live graph и подключает `app.js`. `index.html` содержит offline graph, который PHP заменяет в live mode; копирование донорского полного HTML/JS нарушило бы этот контракт.

Текущие presentation owners лежат в `v2/visual-search/`. Legacy/native presentation modules `src/search3/` и их восемь generated assets — другая композиция; не редактировать их вместо NEXT и не править generated файлы независимо от build.

| Область reference | Существующий target и сохраняемый контракт |
| --- | --- |
| Форма и возврат к выдаче | `index.html`, `app.js`: `editSearch`, `cancelSearchEdit`, `updateSearchUI`, `commitSearchDraft`; сохранённые условия и URL |
| Даты / ночи / туристы | `app.js`: `openDates`, `openNights`, `openGuests`, `openChildAge`, `handleSearchParameterAction`; текущие drafts, Apply/Cancel и child-age review |
| Выдача / карточки / статусы | `app.js`: `hotelOffers`, `hotelOfferPredicate`, `renderResults`, `renderSearchStatus`; реальные eligible offers, progressive inventory, counts и source lifecycle |
| Календарь выдачи | `app.js`: `resultCalendarModel`, `loadResultCalendar`, `renderCalendarStrip`, `selectDate`; `index.html#price-calendar`, `styles.css`, `mobile-controls-v1.css`; текущие price readers и selected date |
| Фильтры | `filter-panel-v1.js` и `app.js`: `openFilters`, `closeFilters`, `filterDraft`; counts по текущим eligible facts, draft/Apply/Cancel |
| Отель / варианты / фотографии | `hotel-details-v1.js`, `offer-list-v1.js`; `app.js`: `renderHotelRooms`, `openAllOffers`, `loadOfferList`; текущая lazy загрузка, точные строки offer и канонические фото |
| Галерея | `app.js`: `openGallery`, `renderGallery`, текущие `gallery={id,index}` и `h.photos`; реальные счётчик/состав фото, keyboard/swipe и возврат к тому же отелю |
| Выбранный тур / рейсы / цена | `app.js`: `renderRealOffer`, `selectedTourOfferSnapshot`, `sameSelectedTourConditions`, `quoteSelectedOffer`, `applyFlightPair`; канонический exact offer, проверка пары/цены/expiry/generation |
| Контакт и возврат | `app.js`: `openLeadPreview`; live `AnyTourPrototypeLead` из `v2/prototype-search/lead.js` и сессии `V2TourController.createLeadSession/createProviderLeadSession`; канонический payload/consent/no-replay |
| Общая геометрия и навигация | `styles.css`, `mobile-controls-v1.css`, существующие `showModal`/modal-history owners; сохранение focus/scroll/drafts и keyboard viewport fix |

`lead-runtime-v1.js` является generated output владельца `scripts/build/search3-js/lead-runtime.cjs`, а не самостоятельным местом ручной правки. Flight picker, supplier adapters, lifecycle и canonical lead owners не заменяются донором.

`v2/visual-search/preview-lead.js` принадлежит offline graph и не является владельцем live контакта. Контактная композиция и политика используют существующий `v2/prototype-search/lead.js`; этот handoff не предполагает правок generated lead transport.

## Обязательное поведение новой основы

**Мобильный календарь выдачи закрыт по умолчанию** — явное актуальное решение владельца. Desktop календарь открыт. В текущем NEXT есть selected date, но нет отдельного состояния раскрытия: добавить небольшое presentation state в существующий owner, accessible toggle с `aria-expanded`/`aria-controls`, сохраняя день и раскрытие при возврате из отеля/тура и при локальных фильтрах/сортировке. Новая явная поисковая отправка может вернуть collapsed default. Toggle не начинает supplier search и не меняет price reader, query, count или inventory. Выбранный день остаётся понятен в компактном summary.

**Ночи:** 1–28; первый тап — точное число, второй — нормализованный диапазон, третий — новое точное число. Разница границ ≤10; пресеты 7/10/14/21. Границы solid blue, внутренние дни soft blue; явное подтверждение выбранного значения. Эта логика уже есть в текущем `openNights`/action owner; переносить композицию, не создавать второй обработчик. Донор использует inline editor, NEXT modal; выбор структуры относится к цельному пакету формы и должен сохранять существующий modal/history/viewport контракт. Для любых ограничений дат/партии сверить текущий provider contract, а не выводить новые ограничения из demo constants.

**Туристы:** возраст на возвращение; 0 — допустимый возраст, null — незаполненный. Изменение дат/ночей сохраняет введённые возраста и требует перепроверки; не вычислять день рождения или реальный return flight через hotel nights. Cancel/Escape/смена редактора возвращают snapshot; Apply сохраняет draft, Search отправляет полный набор условий через прежний owner.

**Направление:** сохранить текущие канонические IDs, multiple selected hotels, проверку страны, восстановление URL и отмену устаревших lookup responses. Один демонстрационный отель в donor не сужает уже поддерживаемый выбор нескольких отелей в Search3.

**Выдача:** минимальная цена относится к eligible offers при всех текущих ограничениях и ко всем туристам. «Смотреть тур» выбирает тот exact offer, чьи условия показаны; «Об отеле» и «Все туры» отдельны. Partial/error сохраняют пригодные результаты. Empty recovery снимает только одно доказуемо полезное условие, сохраняет остальные; если польза неизвестна, предлагает изменение сохранённого поиска.

**Варианты и выбранный тур:** номер, питание, даты, ночи, оператор, группа и total остаются связанными с тем же supplier offer. `+price` — разница с минимумом текущего видимого eligible списка. На mobile убрать повтор минимума/CTA на вкладке вариантов, сохранить нужное пояснение; неизвестные рейсы/багаж/страховка/сборы обозначаются честно.

**Изменение цены:** в доноре есть явное принятие новой цены, а текущий `quotePriceChangeHTML` прежде всего выводит уведомление. Это изменение взаимодействия с price authority, не только CSS. Подготовить control через существующий exact quote/lead contract и профильного владельца: consent привязан к конкретным old/new total, currency, offer/search/flight-pair и quote revision; смена/expiry/pending сбрасывает его. Нажатие не создаёт supplier receipt и не даёт authority неподтверждённому total.

**Контакт:** отображать точный выбранный тур; имя optional только в пределах действующего canonical payload contract, телефон и отдельное privacy consent обязательны. Pending/error/retry/Back/reopen сохраняют действующие guards и draft; никакого локального success по валидности формы. В NEXT отправка остаётся отключённой. Реальное получение заявки — отдельное согласованное тестирование.

## Материал, который запрещено использовать как реальные данные

В новом donor `makeHotels()` искусственно меняет цены по ночам/партии, создаёт варианты и присваивает одни и те же отели разным странам; `priceCalendarPage()` генерирует price factors; `renderTour()` подставляет аэропорты/время/багаж и return через date+nights. Lead показывает локальный success после `reportValidity()`. Gallery демонстрирует одну фотографию.

Не переносить эти функции, synthetic IDs, inventories, price factors, generic flight facts и local success в NEXT даже как fallback. Использовать существующие dictionaries/profile IDs, saved calendar observations, provider qualified offers, quote/flight receipts и canonical lead owner. SAMO pause, TV + direct ANEX, LOCAL/MATCH/DB, money/fuel arithmetic и transport остаются у действующих владельцев.

## Конкретный порядок implementation

Перед runtime implementation отдельно зафиксировать целостное visual + behavior решение владельца по **этой точной новой версии2**, как требует текущий product plan. Разрешение на подготовку этого handoff и scoped claim не подменяют это решение. Просмотренные отдельные кадры и offline проверки не объявляются полной приёмкой нового layout; не переносить другую, более позднюю версию молча.

1. **Форма и выдача как один пакет.** Свежий release/claim; заменить затронутую markup/CSS композицию у существующих owners, оформить текущие date/night/party editors, добавить mobile calendar disclosure. Сохранить каталоги, query, cancellation, progressive results, counts, navigation и keyboard bounds.
2. **Отель и точный тур.** На той же основе перенести варианты/selected summary/price hierarchy; сохранить lazy loading, pagination, exact supplier offer и flight authority. Explicit price acceptance предварительно сопоставить с профильным price/lead contract.
3. **Контакт и связанные состояния.** В существующей форме перенести композицию exact review, validation/pending/error/return; не менять payload/transport/реальные подтверждения. Не создавать второй renderer, event handler или style layer.

Это последовательные ограниченные пакеты одного выбранного дизайна, не новые макеты и не три параллельных writer по одним файлам. До runtime правок — отдельный fresh claim на точные свободные source/test/build paths. Старый Site, чужие claims и unapproved PR4502 не используются как автоматически разрешённый patch.

## Доказательства и условия завершения

В текущем диалоге просмотрен native screenshot именно версии2: default desktop form; SHA256 PNG `e3445e36992ec37384f0d3e1a70e8788f533ad09353431d76352387c1b6915e8`. Overview screenshot Site был stale и не использован как v2 evidence. Также локально просмотрены четыре пользовательских mobile кадра 11.10.2026 16:26:26 / 16:26:49 / 16:27:12 / 16:27:31: ночи7–10, выдача, варианты отеля, выбранный тур. Частично обрезанный низ скриншота ночей не доказывает clipping runtime.

40 offline DOM сценариев прототипа прошли; это поведение mock, не layout engine или real supplier evidence. Разговорная оценка видимых экранов 9–9,5/10 не заменяет **SEARCH-QUALITY-1**, не закрывает все55 критериев, physical Safari, short keyboard viewport или server lead acceptance. Новый score tracker не создаётся.

Для затронутого code packet: narrow существующие tests на Apply/Cancel/Back и ночи7–10; child0/null/reconfirm; calendar closed/open/selected-day/return; exact offer→tour→lead и неизменный total; partial/error/empty; длинные реальные названия и широкие цены. Релевантные существующие entry/result/calendar/offer-list/selected-session/lead-runtime suites выбираются по diff. Если затронут generated owner — build/check/import hashes и source+output commit вместе.

Перед NEXT публикацией нужны зелёные применимые проверки на **финальном SHA**, просмотр rendered compiled evidence затронутых экранов на390/1280 и значимых пограничных ширинах, затем fresh exclusive publisher claim, существующая one-shot exact-artifact команда и readback. Не повторять terminal PR4525 publisher command6108118997/run38132402512. Physical Safari и real supplier scopes остаются отдельными. Docs-only подготовка не запускает build/deploy/full journey.

Этот handoff не является receipt публикации, утверждением production-ready или командой merge production draft1334.


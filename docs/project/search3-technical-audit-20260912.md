# Search3 — технический и визуальный аудит, 2026-09-12

Область: SEARCH #1646, координация #996. Поручение владельца: проверить
накопленный продукт и причины повторных дефектов, затем выполнять целевые
структурные исправления. Это датированное evidence, **не новая очередь исполнения**.
Перед продолжением читать свежие [общие правила](../../AGENTS.md),
[организацию разработки](anytour-development.md),
[продуктовый план](search3-product-development-plan.md), `current_task` и
`continuation_policy.active_queue_ids` в release `AUTOPILOT_STATE.json`,
актуальные #996/#1646, heads, claims и PR/CI.

Поручение владельца 2026-09-11 о полной OTA-форме страницы поиска имеет приоритет
над исторической «компактной формой». Туроператор — фильтр выдачи либо вторичный
параметр, не постоянное primary-поле и не неявное ограничение начального поиска.

## Граница проведённого обзора

Прочитаны архитектурные правила, карта исходников/сборки и действующие границы
формы, каталогов, lifecycle, renderer, локальных фильтров, shortlist и selected/lead
handoff. Сопоставлены реальные browser-тесты, их workflow-триггеры и артефакты.
Это обзор архитектуры и целевая проверка SEARCH, **не завершённый построчный аудит
всего репозитория и не полная приёмка всех пользовательских сценариев**.

API, matching/content, SITE/SEO, lead transport/field mapping, арифметика цены,
аналитика, конфигурация сервера и production не редактировались. Проверки выполнялись
без supplier-запросов и реальных заявок. Локальная навигация к артефакту получила
`ERR_BLOCKED_BY_ADMINISTRATOR`; обход адресом, портом, proxy или browser policy
не выполнялся. Для приёмки использован существующий разрешённый CI-маршрут.

## Подтверждённые находки

| Находка | Доказательство / текущий владелец | Результат |
| --- | --- | --- |
| На широком экране календарь оставляет пустые колонки, когда дат меньше семи | `v2/current-price-calendar-v1.css`; падение `calendar-readability.cjs:45` в run 34652416712 / job 103437351709 | Исправлено #2080: auto-fit заполняет редкую строку, от семи дат остаются семь колонок |
| Изменение календаря не включает его browser-приёмку | CSS/JS календаря и helper отсутствовали в detection существующего build workflow | Исправлено #2080; добавлены необходимые пути, без нового workflow или ослабления gate |
| Тест геометрии формы проверял собственную копию HTML и не вызывался в актуальном workflow | `tests/search3-entry-geometry-browser.cjs`: synthetic shell, ручная форма, eval; путь присутствовал в detection, но invocation отсутствовал | В #2079 заменён тем же тестом настоящей страницы, добавлены invocation и test-only trigger |
| Мобильные primary preferences занимают излишние отдельные строки | Снимки текущей формы; canonical `src/search3/styles/entry-native-controls.css` | В #2079 пары «категория + питание» и «цена от + до» на 375/430; курорт/отель на отдельных полных строках, на <=350 всё в одну колонку |
| Возраст детей отделён от блока туристов и растянут на промежуточном desktop | `v2/index.php` выводит единственный `#childAges` после `.main-fields` с hotel/price preferences; exact 1024 screenshot | **Открыто**: перенос того же владельца к `.search-group--party`, не ещё одна проекция формы |
| Формат даты неоднороден в карточках | `v2/results-renderer-v5.js::priceContext()` использует raw `String(t.date)`, строки туров также выводят raw date; fixture 1440 смешивает DD.MM.YYYY и YYYY-MM-DD | **Открыто**: единый display-only формат, без изменения supplier/date payload |
| Полнота поиска конкретного отеля недостаточна для country-wide name discovery | `v2/catalogs-v2.js::loadHotels` требует страну и курорт и использует существующую ограниченную выборку 100; primary hotel — native select | **Открыто**: проверить разрешённый catalog contract, затем bounded SEARCH решение или точная внешняя зависимость; не возвращать legacy autocomplete owner |

Наличие старого имени файла/версии либо пустого provenance-слота не доказывает
существование второй живой реализации. Восемь output paths из `src/search3/manifest.json`
сохранены. Удаление совместимых модулей возможно только после проверки consumers.
Найденные расхождения между тестами и реальным маршрутом важнее косметического
разбиения файлов. Заменённый synthetic test удалён в том же пакете, не оставлен
параллельным «новой» приёмке.

## Проверенный пакет календаря #2080

- Исходная release: `311530102d1b7695c2d51fa1082c35893eb617cb`.
- Checked source: `f5bf927c08054a3d628ff205a4096168a7149a25`.
- Release merge: `8886c3ab78f6f14df1ed673d2bbd0f45278fc69e`.
- Security `34654541842` и exact build/browser `34654541840`: success.
- Site artifact `10284853655`, ZIP SHA-256 `dd5e2e0f8cf109c01e4edf1fc9868e05e7512da0b9811af551e0cbeb32bcd96d`.
- Entry artifact `10284888511`, ZIP SHA-256 `9d2e7327b749db9da8d0f5e52b20b666fbee1d9a52ac0ea87354b3203d9b259b`.

Изменены три пути: canonical `v2/current-price-calendar-v1.css`, существующий
`tests/search3-calendar-readability.cjs` и build workflow. Календарь — прямой v2-owner,
не один из восьми generated Search3 assets; их исходники, outputs и import hashes
этим пакетом не менялись.

Приёмка: **49 случаев на реальном served-маршруте** — семь ширин и семь количеств дат
(2/3/6/7/8/14/21). Отдельный локальный изолированный CSS/renderer fixture прошёл 91
сочетание; это не ещё 91 полный end-to-end сценарий. Снимки редкого/полного календаря
на 375/1024/1199/1440 и мобильного keyboard focus просмотрены. Исходная проверка
пустых колонок сохранена, дополнены неполные недели, точные даты/цены, минимум,
размеры кнопок, скролл и видимость фокуса. Новых supplier-запросов нет.

## Проверенный пакет формы и приёмки #2079

- Checked source: `c023b4632a979d568e03c70e4658ff898224c5e7`.
- Source tree: `880c10285a8391918433b5bbc54f52a2aa5cf704`.
- Release merge: `827fb8abc0b68cf6fa2ac6d9e1f6f391d72fe85c`.
- Security `34656690667` и exact build/browser `34656690679`: success.
- Site artifact `10286421700`, ZIP SHA-256 `5ac085746f903160be9d3926f4ded4f88cd83ab30035715b76412c699341e018`.
- Browser artifact `10286541339`, ZIP SHA-256 `974e71920512278736af77a796874dd2925849e8c5690a3c0c1db2d35fb34c91`.

Пять изменённых путей: canonical entry CSS source, generated `v2/search3-entry-v1.css`,
только соответствующий `productionSha256` в `search3-production-import.json`,
существующий entry-geometry test и его trigger/invocation в существующем build.
Source/generated/hash проверены вместе. Entry CSS: 2789 -> 2932 bytes (+143),
SHA-256 `4d9a1964f6c0915bfbe6b69acccff07e314250e6a32bf49344964d20edfc94ab`.
Новое поведение не переносилось в второй form owner или JS-handler.

Проверены **10 настоящих served widths**: 350/375/430/760/761/1024/1025/1199/1200/1440;
**40 состояний** с 0/1/2/3 детьми, возрастами 0/17/6, сохранением трёх взрослых,
ночей 7–10 и точного FormData. Дополнительно прошли текущая выдача, shortlist,
существующий native-entry путь и 49 calendar cases. Во всех 718 файлах exact payload
проверены hashes и размеры. Просмотрены exact form screenshots на
350/375/430/760/1024/1440. Ошибка загрузки справочников в этих снимках — намеренно
заблокированный offline fixture, не доказательство дефекта live-каталогов.

При переносе теста выявлены и исправлены две ошибки измерения, не ошибки продукта:
closed-details descendants могли сохранять boxes, хотя не были видимы; разбиение
serialized `gridTemplateColumns` по пробелам принимало `repeat/minmax` за лишние
колонки. Финальный тест измеряет видимые клетки по реальным рядам. Требования
к 1/2/3 колонкам, 14 primary controls + одному возрасту, читаемости и CTA сохранены.
Никакого runtime-исправления desktop footer не делалось: финальные данные показывают
совпадение top у closed extras и CTA на 760/1024/1440.

## Публикация: активировано, финальная проверка не завершена

Выполнена **одна** штатная накопленная публикация #2080 + #2079, без пересборки:

- Run `34657056398`, job `103451640227`; общий результат **failure**.
- Trusted main controller: `26ca33b043464f98976c826332a4810069b44e84` (не изменён этим запуском).
- Exact source/release/build/artifact: c023b463 / 827fb8ab / 34656690679 / 10286421700.
- Receipt artifact `10286492018`, ZIP SHA-256 `ef572fef49f54bafb7b3802727d9c6a3a17ad2a91e03539b1a0dc177d0019712`.
- Единственный маршрут публикации: `/_preview/search3-site-candidate/`.

Скачанная receipt фиксирует activation 718 files, completion.status=published,
9 routes HTTP 200, served asset hashes checked, noindex=true, counter=0,
disabled lead HTTP 403, rollback_retained=true, supplier_searches=0 и real_leads=0.
Trusted `remote.complete()` проверяет текущий payload и защищённые production/INT
fingerprints до записи completion; этот шаг завершился.

Затем в `scripts/deploy/search3_preview_publish.py:275`, внутри `finally`,
**дополнительный** `remote('snapshot', {})` оборвался с SSH255:
`kex_exchange_identification: read: Connection reset by peer`.
Статус receipt был выставлен до этого чтения; `status=published` и
`production_unchanged=true` не превращают весь красный workflow в зелёный.
Откат по этому исключению в finally не запускался.

Корректное состояние: checked=true, merged=true, preview activated=true,
HTTP/assets/remote completion checked=true, **final independent snapshot=deferred**,
production-approved=false. Не сообщать «ничего не опубликовано» или «всё проверено».
Не повторять deploy/старую команду и не создавать временный verification workflow.
Сохранённые receipt/source/artifact нужны для отдельной разрешённой read-only сверки;
блокер зафиксирован в #996, publisher claim освобождён.

## Матрица качества: что ещё не принято

Цель владельца — не ниже 9.5 по каждому параметру отдельно. Этот аудит не выставляет
новых численных оценок без полной проверки и не выводит их из green отдельного PR.

| Параметр | Доказано в этом проходе | Оставшееся ограничение |
| --- | --- | --- |
| Desktop visual | Геометрия/снимки формы на проверенных ширинах; редкий календарь без пустой полосы | Структура семьи и баланс всей страницы ещё не приняты |
| Mobile visual | Основные preferences попарно, <=350 безопасно, exact screenshots | Полный путь, длинные значения и физическое устройство не приняты |
| Соответствие макету | Только перечисленные компоненты | Отдельного полного сравнения с целевым макетом в этом проходе нет |
| Полнота основной формы | Обязательные native controls видимы; оператор вторичный | Country-wide поиск названия отеля и группировка возрастов открыты |
| Filters / decision UX | Существующие results regressions прошли | Полная матрица фасетов трёх источников и неполных данных отдельно |
| Карточки / price / CTA | Существующая выдача и CTA regressions | Согласованность формата даты открыта; price arithmetic не менялась |
| Календарь | Редкие/полные недели, минимум, фокус, мобильный скролл | Не полная приёмка date-picker и всех сценариев редактирования дат |
| Compare / shortlist | Существующая shortlist regression | Полный сравнительный продуктовый сценарий не переоценён |
| Selected tour | Архитектурная граница прочитана | Новый полный provider-selected сценарий не проверен |
| Lead-form handoff | Сохранён protected transport; preview отправка заблокирована | Доставка реальной заявки не проверялась и не отправлялась |
| Accessibility / state UX | Native controls, часть keyboard/recovery checks | Полная accessibility/state матрица и физический Safari deferred |
| Архитектурная чистота / стабильность | Удалена synthetic-копия формы, закрыты два CI coverage gaps | Весь проект построчно не проверен; family owner и финальный publisher readback требуют продолжения |

## Требования к следующему структурному исправлению формы

Это рекомендации аудита для действующего SEARCH roadmap, не автономная вторая очередь.
После свежего claim следующий приоритет — группировка семьи: один и тот же
`#childAges` рядом с туристами, без дублирования controls и без растягивания одного
возраста по форме. Сохранить URL hydration, имена полей/FormData, 0–3 детей,
включая 0/17 лет, disabled/reset/busy и legacy-путь. Проверять actual served markup,
а не новую копию формы; закрытое/раскрытое состояние, widths 350/375/430/1024/1440,
и визуальное равновесие desktop отдельно от отсутствия overflow.

Готовые календарь/mobile пакеты не повторять. Изменённый компонент получает узкую
регрессию и обязательные owner/security/isolation gates; документация не требует
нового browser/build/deploy. Заменённые CSS/JS rules или handlers удалять в том же
пакете. Физический Safari/iPhone, production migration #1334/#1493 и производственная
приёмка остаются отдельными gates владельца.


## Дополнение: живой browser-аудит и план 9.5, 2026-09-12 UTC

Это более поздний осмотр опубликованного Search3 по поручению владельца
«осмотри всё и составь актуальный план». Предыдущие разделы сохраняют свои даты
и scope; их старые blockers не перезапускаются. В этом дополнении были обычные
поиски через пользовательскую форму preview, чтение выбранного Tourvisor-тура и
локальные действия. Реальные заявки не отправлялись.

### Что именно проверено

- Свежая release-база: `e2ec8c799e300db463951dad877c421973334a53`.
- Осмотрен только `/_preview/search3-site-candidate/poisk-turov/`, встроенный
  Chromium; desktop screenshot canvas 1348×926, DOM viewport около 1348–1363×936
  в зависимости от полосы прокрутки. Это один desktop-сеанс, не mobile acceptance.
- Последняя подтверждённая публикация: source
  `4b3ece32995f88f1d7b624857223bce690056bef`,
  release на момент публикации `986f89e75582396968ad3c30bd2c9badd7d7a414`,
  [publisher 34708708798](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34708708798).
  Прочитан успешный log/receipt; query fingerprints реально подключённых
  entry/filter/bundle assets совпали с manifest этого source.
  Независимый побайтовый download/readback в этой сессии не выполнен.
- Более поздняя попытка
  [34718576444](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34718576444)
  красная: `ssh:255 / kex_exchange_identification: Connection reset by peer`
  до активного переключения. Это внешний publisher blocker; повторять deploy
  из SEARCH-аудита нельзя. Merged release не равен увиденному preview.
- `current_task` по-прежнему `S3_PRODUCT_CURRENT_ACCEPTANCE`; активные очереди:
  она же, `S3_OTA_PROVIDER_PARITY`, `S3_PRODUCT_APPROVED_MIGRATION`.
  Старые published pins/compact-form next_action в state отстают от свежих
  #996/#1646. Они не становятся новым поручением.
- Прочитаны реальные owners: native form, entry CSS, lifecycle, URL hydration,
  renderer, local facets, shortlist, current-price-calendar, selected/lead
  controller и import manifest. Это не полный security-аудит всех строк repo.

Сценарии: исходная форма и 3 ребёнка; Москва → Турция, 12.10.2026, 7 ночей,
2 взрослых, AI, без initial operator; до 586 загруженных отелей; ARES CITY,
раскрытие 8 вариантов; локальный hotel-name + конфликтующая 5★, zero-match и
удаление фильтра; сохранение двух Tourvisor-предложений; переход в selected,
перелёты/стоимость и фокус телефона; native required validation пустой формы без
отправки; возврат; открытие URL во второй вкладке. Отдельно Кемер, 12–14.10.2026,
7 ночей, 2 взрослых, AI: три календарных даты, локальный ARES CITY/ARES BLUE,
раскрытие и выбор 13 октября (оба date-поля стали 2026-10-13).

Данные живые и менялись при догрузке: 80 → 107 → 134 отеля во втором сценарии —
не performance-замер и не доказательство полного покрытия поставщиков.
В source facet были Tourvisor и Андромеда. Наличие оператора ANEX не доказывает
direct-ANEX source. Его полная live-доступность в этом сеансе не подтверждена.

### Подтверждённые наблюдения и их причины

| Наблюдение | Следствие / текущий owner |
| --- | --- |
| Все требуемые primary-поля присутствуют в исходной форме; оператор находится в advanced и result rail | Не создавать новую форму и не возвращать compact-only набор. После поиска форма целиком скрыта, toolbar не показывает даты/туристов/условия: требуется постоянный понятный контекст поездки и полный основной flow |
| Три возраста стоят вертикально в правой части группы, растягивая весь ряд; под ночами большая пустая полоса | Причина в >=1200 rule `entry-native-controls.css`. Свежий [#2261](https://github.com/pyatkoff/poisk-turov-test/pull/2261) уже владеет этой работой. Проверить также 3 ребёнка, а не только common 1–2; второй writer запрещён |
| URL после submit остаётся пустым route; новая вкладка открывает исходные даты/ночи/питание | В прочитанном lifecycle есть URL hydration, но нет обратной записи параметров. Нужен round-trip в существующем owner: search → URL → reload/Back, без нового supplier mapping |
| Конкретный отель доступен после региона; в ограниченном списке Кемера ARES CITY отсутствует, хотя он есть в результатах | Полноценный поиск отеля по названию не завершён. Локальную навигацию по уже загруженным вариантам улучшать в SEARCH; полноту первичного каталога/новый контракт отдавать точной внешней dependency |
| Свернутая desktop-карточка занимает около 620 px; много места у fact cells/operator badges, широкие действия; раскрытые варианты длинные | Структурная композиция в текущем renderer/results CSS, соразмерные действия и обозримые альтернативы. Не объявлять уже merged #2144/#2246 отсутствующими |
| В ARES CITY показано «3 варианта питания», при этом варианты содержат разные записи AI/All Inclusive/«Всё включено» | `hotelSummary` считает различные display strings, тогда как local filter уже имеет meal families. Согласовать представление в canonical owner, сохранить реальные различия AI/UAI/Soft AI, не менять supplier значения |
| В operator facet одновременно «Анекс Тур»/«Anex Tour», «Интурист»/«Intourist», «Fun&Sun»/«Fun&Sun (RU)» | Renderer уже имеет reviewed display aliases через `operatorIdentity`, filter использует lowercase raw text. Переиспользовать существующую display identity локально; не заводить вторую таблицу и не менять catalog/manual matching |
| Hotel-name, active chips, 0-match и снятие 5★ работают; питание/цена/operator/source пересекаются на одном offer в source | Сохранить эти свойства. Улучшения нужны точности бюджета, согласованности значений и сохранению состояния; local filters не должны вызывать submit |
| Групповой минимум ARES CITY 79 670 ₽ получен из Андромеды; доступный выбранный TV-вариант 104 092 ₽ | Свернутая карточка должна объяснять статус минимального предложения до раскрытия. Не пересчитывать цену, не обещать одинаковую готовность selection у разных sources |
| Два сохранённых предложения занимают две из трёх колонок; справа пусто; сравнение остаётся над selected | Сетка по фактическому числу 1/2/3, обозримые различия, управляемое раскрытие на desktop, сохранённый контекст без вытеснения выбранного тура |
| После reopen сохранённые снимки есть, но «Нет в текущей выдаче», без восстановления условий | Exact identity/historical status корректны. Нужен явный путь восстановить исходные условия и повторно найти предложения. Нельзя переиспользовать старый offer как новую подтверждённую цену |
| Selected Tourvisor показывает правильные отель/104 092 ₽/12.10/7 ночей/2 взрослых/AI/номер; переход к телефону работает | Это подтверждённый happy path. Сборы «—», уточняемые рейсы/багаж требуют понятных unknown-labels. 49 вариантов перелёта не были полностью проверены; delivery/actualization parity всех sources не оценена |
| Календарь на 3 даты ровный, цена подписана как минимум найденных; hotel filter меняет минимумы; выбор дня выставляет обе границы | Sparse fix #2080 существует. При одной найденной дате календарь скрыт по текущему правилу, это не записано как новый баг. Первое подозрение на самораскрытие не подтвердилось в повторном стабильном тесте; transient 0→results остаётся regression case, не подтверждённым дефектом |

### Оценка SEARCH-QUALITY-1

Пять компонент вектором идут в неизменном порядке из
[канонической рубрики](search3-product-development-plan.md).
`N` = `not_measured`; при хотя бы одном N итог /10 не рассчитывается.
Это экспертные оценки только указанного published desktop scope, не оценка
неопубликованной release-версии, всего рынка, конверсии или physical устройств.
Предыдущего сопоставимого baseline SEARCH-QUALITY-1 нет; дельта не заявляется.

| Параметр | Пять компонент | Итог | Что мешает 9.5 / завершённой оценке |
| --- | --- | --- | --- |
| Desktop visual | 1 / 1 / 1.5 / 1 / N | not_measured | Семейная сетка, плотность cards/compare, размеры result CTA; промежуточные и короткие экраны не осмотрены в этом сеансе |
| Mobile visual | N / N / N / N / N | not_measured | Нужен просмотр actual screenshots и интерактивный путь на mobile, а не вывод по CSS |
| Соответствие макету | N / N / N / N / N | not_measured | Сам approved reference image недоступен для просмотра |
| Полнота основной формы | 2 / 2 / 1.5 / 1 / 1.5 | **8.0/10** | Основной набор есть; неудобные возраста, ограниченный hotel chooser, исчезающий после submit основной контекст/доступ к цене |
| Filters / decision UX | 1 / 2 / 2 / 2 / 1 | **8.0/10** | Display aliases операторов расходятся, точный бюджет неудобен, URL/reload не сохраняет flow; существующие chips/reset/same-offer projection работают |
| Карточки и price/CTA | 1.5 / 1 / 1.5 / 1 / 1.5 | **6.5/10** | Растянутая композиция, ложное число meal labels, минимум из требующего проверки source, тяжёлые альтернативы |
| Календарь | 2 / N / 2 / 2 / 2 | not_measured | Проверены 3 даты, локальный минимум и точный submit; полная неделя/длинный диапазон и mobile не просмотрены |
| Compare / shortlist | N / 1 / 2 / 2 / 1 | not_measured | 2-card layout и reload dead end подтверждены; лимит/полный storage/focus failure corpus не перепроверены |
| Selected tour | 2 / 2 / 1.5 / 1.5 / N | not_measured | Unknown fees/flight readability; смена рейса, stale и вся матрица возврата не перепроверены |
| Lead-form handoff | 2 / 2 / N / N / 1.5 | not_measured | Happy path/phone focus/пустой required проверены; draft, consent reset, pending/error требуют controlled fixture |
| Accessibility / state UX | N / 1.5 / 1.5 / N / 1 | not_measured | Нет полного keyboard/zoom/announcement/error corpus; reload теряет условия |
| Архитектурная чистота / стабильность | 2 / N / N / 2 / N | not_measured | Карта активных owners/protected границ проверена; не выполнены заново весь cleanup, source/generated parity, races/security matrix |

Критерий получает 2 только после проверки в заявленном scope. Для приёмки строки
в 9.5 допустимо не более одного небольшого остатка на 1.5 и четыре подтверждённых
критерия по 2; существенный дефект на 1 или N не позволяет закрыть строку.
Никакой средний балл и green отдельного PR не компенсируют незакрытую строку.

### Исполнимые пакеты внутри существующей SEARCH-очереди

Это последовательность улучшений текущей `S3_PRODUCT_CURRENT_ACCEPTANCE`,
не новый список active_queue_ids. Точные paths/tests каждого пакета заново
claim в #996; перечисленное ниже — границы owner, не заранее захваченные файлы.

| Порядок | Пакет / конкретный результат | Приёмка и граница |
| --- | --- | --- |
| 1 | Полная форма и постоянный контекст поездки: основные параметры/цена остаются понятными в search flow, hotel chooser имеет честную доступность/поиск по уже полученным названиям | Исходная форма → результаты → изменить → возврат, все primary-поля, closed advanced, длинный отель, 0/1/2/3 ребёнка. `v2/index.php` + текущие form/control owners. Новые API/catalog completeness — dependency, не скрытая доработка |
| 2 | Desktop geometry: принять #2261 и устранить оставшийся дисбаланс family/control groups в том же owner | 1199/1200/1366/1440/1600, короткий desktop 1024×600, mobile regression 375/430. Проверить 3 возраста и визуальную композицию; основной CTA уже соразмерен в увиденном кадре, не переделывать его без нового дефекта |
| 3 | Calendar acceptance: дополнить 3-day live evidence полной/редкой матрицей; исправлять только воспроизводимый остаток | 1/2/3/7/21 дней, gaps/unknown, date boundary, local budget/meal/operator filters, догрузка, disclosure после transient zero, exact single submit. Текущий `v2/current-price-calendar-v1.js` и его CSS; новый рыночный календарь не входит |
| 4a | URL/state round-trip в существующем lifecycle: текущий запрос можно повторно открыть и восстановить | from/country/date range/nights/adults/child ages/region/hotel/stars/meal/price проходят submit → URL → reload → Back/Forward без потери; local state отдельно от supplier fields, operator не добавляется в initial query. Никаких PII, lead draft или consent в URL |
| 4b | OTA result facets: единая существующая operator display identity, последовательные meal labels, точный бюджет, видимые активные условия | Общие aliases не дробят один reviewed brand; source отдельный; same-offer tests price+meal+operator; счётчики/zero/reset/неполные facets; на каждом local edit 0 supplier searches. Не расширять таблицы matching |
| 5a | Cards: плотная desktop композиция с фото/местом, короткими фактами, соразмерным блоком цены/действия | Side-by-side before/after на frozen корпусе 1/8/20 offers, длинные имена/цены, 1025/1200/1366/1440/1600 и затронутый mobile. Цена и действие видны вместе; смысл не теряется ради снижения высоты |
| 5b | Offer decision: убрать ложные meal-count aliases, сделать различимые условия вариантов и readiness минимума | Сохраняются exact IDs/цены/валюта/source/operator; AI/UAI/Soft AI не смешиваются без контракта. Merged #2246 сначала учитывать как baseline. Сортировка внутри раскрытия должна иметь понятный порядок и устойчивость при догрузке |
| 6 | Compare: 1/2/3-column composition по числу записей, обозримые различия, управляемое раскрытие, восстановление условий исторического снимка | 2 и 3 предложения, удаление/лимит, reload/corrupt/quota, focus, stale; selected не оттесняется полным compare. Данные snapshot не заменяют новую exact identity; восстановить текущую доступность без контракта нельзя |
| 7 | Selected: компактная связная сводка условий и неизвестных сборов/рейсов, понятный выбор перелёта и возврат | Подтвердить на опубликованной версии уже merged #2204; fresh status date-fix owner из #996 до claim controller. Смена рейса/unknown/stale/back проверяется без новой price arithmetic. Provider parity и fuel truth — INT handoff |
| 8 | Lead handoff: компактный контекст тура у контактов, доступные ошибки/ожидание/повтор и допустимый draft | Без реальных заявок: controlled validation/pending/error, consent отдельно и сбрасывается при нужной смене тура, focus и возврат. Никакого lead transport/field mapping/analytics изменения |
| 9 | Накопленная независимая desktop/mobile/mockup/a11y acceptance всего пути | Единый exact artifact, реальный served route и все 12 строк SEARCH-QUALITY-1. WebKit automation и physical Safari/iPhone раздельно; неосмотренное остаётся N/deferred |

Отдельный safe шаг, пока #2261 владеет CSS: подготовить/claim 4a или 4b после
fresh recheck отсутствия writer на lifecycle/renderer/filter owner. UI-реализация
не входит в этот docs-only audit PR.

### Обязательная матрица приёмки и внешние зависимости

- Desktop: 1024×600, 1199/1200 breakpoint, 1366/1440/1600; mobile:
  360/375/390/430, 768 portrait. Это целевой corpus; он не заявляется выполненным.
  Сценарии: семья 0/1/2/3 ребёнка, длинные названия, 0/1/много отелей/вариантов,
  разные доступные sources и unknown fields.
- На затронутом пакете проверять его widths/states и exact screenshots; полную
  матрицу повторять на полезном накопленном рубеже, не ради количества прогонов.
  Keyboard-only, visible focus, labels, 200% zoom, loading/partial/empty/error,
  Back/reload и локальное восстановление входят в итоговую a11y/state приёмку.
- **Reference dependency:** `Интерфейс поиска туров AnyTour.png`,
  `libfile_bcd3a18be788819193fbc1924082e3ca`, указан в каноническом плане,
  но бинарный файл в этой сессии не получен. Соответствие не оценено.
- **Mobile evidence dependency:** текущий Browser не предоставил смену viewport;
  полученные GitHub artifact download URLs при чтении вернули HTTP 403 / 1010.
  Скриншоты CI не были осмотрены, блок не обходился. Получить доступ к exact
  images штатным способом либо использовать доступную среду mobile viewport.
- **Publisher dependency:** SSH reset выше; подтвердить актуальный статус
  свободного publisher и deployment prerequisites накопленного release перед
  любой публикацией. Новые data/schema изменения других owners не публиковать
  автоматически как часть SEARCH CSS-пакета.
- **INT/MATCH dependency:** полная первичная доступность hotel catalog,
  direct ANEX и Andromeda selection/flight/fuel parity, подтверждённые неизвестные
  поля. SEARCH делает честный текущий UI и точный handoff, не подменяет контракты.
- SITE/footer/SEO остаются у своих owners. Визуал футера не захватывается ради
  повышения SEARCH-оценки. Production migration #1334/#1493 отдельно согласуется.
- Physical Safari/iPhone, delivery реальной заявки и пользовательский пилот:
  deferred/not_measured. Для пилота сохранить правило плана: минимум 4 из 5
  участников выполняют каждую из пяти задач без подсказки и верно называют цену
  и условия; это не измерение конверсии.

### Скриншоты этого осмотра и статус

Лично просмотрены исходные browser captures:
`search3-audit-family-desktop-1789250884537.jpg`,
`search3-audit-expanded-desktop-1789251136187.jpg` (фактически кадр ещё
свернутой карточки, не evidence раскрытия),
`search3-audit-compare-desktop-1789251311873.jpg`,
`search3-audit-selected-desktop-1789251364620.jpg` (нижняя часть lead),
`search3-audit-selected-summary-1789251581839.jpg`,
`search3-audit-calendar-desktop-1789252261442.jpg`.
Это captures текущей сессии, а не заново осмотренные CI-артефакты; бинарные файлы
не включены в docs PR. Сценарии раскрытых вариантов дополнительно зафиксированы
в live DOM. Мгновенный screenshot после click мог отставать на один кадр, поэтому
не трактовался как новый runtime defect.

Результат дополнения: подтверждённый desktop audit, частичный числовой baseline
и конкретная последовательность работ. **Не 9.5 acceptance, не новое UI-исправление,
не preview publication и не production approval.** Для docs-only пакета достаточно
diff/link/claim consistency и применимых CI guards; source/generated/hash
сохраняются неизменными.


## Технический refactor-pass: текущие guards, 2026-09-13

Выполняется по явному уточнению владельца «в автопилот и делаем» после browser audit.
Подготовительный порядок и актуальный `current_task` закреплены в #2271:
merge `78819dd10225bfc9f07a9e045cc1dd079c4b7c45`;
Security `34724509988` и state validation `34724509996` прошли.
Единственный существующий ежечасный SEARCH-автопилот обновлён на месте.

### Выполненная техническая проверка

Exact baseline runtime: release `78819dd10225bfc9f07a9e045cc1dd079c4b7c45`
(от предшествующей `3c21d805...` отличаются только plan/current_task).
Получены точные исходники, manifest, существующие build tools и публичные outputs.
Pinned `npm ci` в этой среде прошёл; прежний чужой npm blocker сюда не переносится.

- `python3 scripts/build/search3_assets.py --check`: **8 assets, passed**.
- `python3 tests/search3_source_build_test.py`: **13/13 passed**,
  включая drift, идемпотентность, fail-before-write, private includes/CSS и path boundary.
- `node scripts/build/search3-js/shared-runtime.cjs --check`: **passed**,
  source bytes 138276 → generated 120726. Это существующая компактизация baseline,
  **не экономия от текущего пакета**.
- Подтверждены четыре активных presentation behavior owners из README/manifest:
  native form shell, local facets, shortlist и summary CTA. Восемь public paths
  и порядок включений сохраняются; retired slots не становятся owners.
- Renderer и local filter по-прежнему имеют разные operator display keys;
  meal summary считает сырые labels. URL hydration существует, обратной записи
  условий поиска нет. Это прежние подтверждённые остатки, а не завершённые исправления.
- Уважены claims #2261 на entry CSS/import manifest и selected-date-v4
  (#996 comment5648815213) на controller/shared runtime. Пересекающиеся записи
  source/generated/hash не начаты. Блок занятости снимается только свежим claim/readback.

### Первый независимый кодовый пакет

Claim #996 comment5649311860, branch
`refactor/search3-current-presentation-guards-20260913`.
Точные изменённые paths: `tests/search3_production_presentation_test.py` и этот audit.

Найдено 53 проверки прежней композиции в отключённом классе на 890 строк.
Многие ссылались на уже удалённых CSS/JS-владельцев. Их сохранение затрудняло понимание
реальной защиты Search3; выбор исполняемого класса зависел от наличия исторического
`search3-half-size-reset.json`, а не от текущего runtime contract.

Изменение:

- удалён отключённый старый класс и его неиспользуемые imports;
- все **14 существующих live tests и setUp сохранены с идентичным AST**;
  protected-controller reconstruction, price/lead/business calls, local-only facets,
  shortlist isolation и одна runtime closure на маршрут не изменены;
- три по-прежнему применимых guard-метода перенесены **без изменения AST** в live suite:
  отсутствие preview simulation markers в public assets, отсутствие старой
  supplier-party overlay, отсутствие второго Search3 footer;
- текущие проверки исполняются напрямую, исторический audit-файл больше не служит
  переключателем тестового поколения; число live tests выросло **14 → 17**;
- размер файла **1210 → 335 строк**. Удалены строки тестового технического долга;
  размер/поведение загружаемых пользователем CSS/JS этим пакетом не изменяется.

Локально три возвращённых guard-теста прошли (3/3). Существующий whole-site workflow
уже включает этот test path и запускает полный PHP/business/isolation корпус на
exact PR head; его checks/merge receipt фиксируются в #996/#1646 после завершения.
Новый workflow, упрощённые assertions, runtime patch или trigger-only commit не нужны.
Этот test-only пакет не требует новой визуальной оценки или deploy; screenshots
прошлого аудита не выдаются за новую приёмку.

### Продолжение без повторного старта

Карта текущих presentation owners и оба канонических build baseline проверены.
Независимая очистка guards реализована; итог CHECKED/MERGED определяется свежим receipt
PR в #996. Весь refactor-pass пока **не закрыт**: следующие ограниченные пункты —
согласовать единственную operator/meal display логику и owner состояния form/URL,
после освобождения нужных shared generated paths либо с одним согласованным writer.
Затем действующий продуктовый порядок и 12 отдельных ≥9.5 acceptance.

Повторять полный исторический обзор или эти успешно проверенные build cases каждый
час без новых изменений не требуется. На свежем release проверять изменившиеся
контракты/claims и брать следующий незавершённый пункт. Производственные approvals,
supplier/API/price/lead/analytics, SITE/SEO/INT/MATCH и physical-device deferred
сохраняют прежние границы.

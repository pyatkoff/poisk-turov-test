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
- размер файла **1210 → 336 строк**. Удалены строки тестового технического долга;
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


## Завершённые пакеты и граница URL-состояния, 2026-09-13

Сверено с release [8a78a709](https://github.com/pyatkoff/poisk-turov-test/commit/8a78a709061de18f1ac5028b3dd6ca1f42e2e111), свежими #996/#1646,
открытыми PR и exact CI. Таблица ниже уточняет результаты предыдущих датированных
разделов; их старые «открыто» и pending-формулировки не являются новой очередью.

| Пакет | Подтверждённый результат | Source / release merge и evidence |
| --- | --- | --- |
| #2272 | Удалён отключённый test owner; 17 текущих guards проходят. Повторять эту очистку не нужно | [ec0620a4](https://github.com/pyatkoff/poisk-turov-test/commit/ec0620a43199875dd3351adc792e16bcda37c46d) → [281c17ff](https://github.com/pyatkoff/poisk-turov-test/commit/281c17ff3533ce7a32a4bb5f63aef390ab848d30); Security 34724871213, whole-site 34724871206 |
| #2279 | Существующий hotel-summary test актуализирован и действительно подключён к renderer CI; runtime не менялся | [eb203d8c](https://github.com/pyatkoff/poisk-turov-test/commit/eb203d8c826ae5a0f4bf61c4ff6b07f9ef5e9fea) → [37523e78](https://github.com/pyatkoff/poisk-turov-test/commit/37523e7837a7f2461602adcd92f24fb1bcfba6b2); Security 34726053737, renderer 34726053747 |
| #2281 | Каждый entry journey JSON привязан к exact source SHA; runtime/UI не менялись | [1109f30a](https://github.com/pyatkoff/poisk-turov-test/commit/1109f30a2eb80e25a0f67551c876a76f24ee76e6) → [e905c960](https://github.com/pyatkoff/poisk-turov-test/commit/e905c9606d64dec00cae285019dae8a839bbfe43); Security 34726399766, whole-site 34726399796, site artifact 10307199652 |
| #2261 | Desktop-family geometry исправлена в canonical CSS; 1–2 возраста выровнены в ограниченной группе, третий переносится внутри неё | [e9b89fcb](https://github.com/pyatkoff/poisk-turov-test/commit/e9b89fcb7137d0942a76d642a76531fb28affcd1) → [a4e591c1](https://github.com/pyatkoff/poisk-turov-test/commit/a4e591c10800fb13b7a0fe9705fc3f494a1e8078); [точные ширины и визуальный receipt](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5649482828) |
| #2276 | Выбор open/closed календаря сохраняется через локальные 0/1/restored dates; новый поиск сохраняет первоначальное раскрытие | [bb8d9711](https://github.com/pyatkoff/poisk-turov-test/commit/bb8d971123ae23a98566bce7caf7783f2e481180) → [0758836b](https://github.com/pyatkoff/poisk-turov-test/commit/0758836b54d3fd4b1ee0c8f41599cc4db1e2b2b7); [375/1440 visual и green CI](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5649576503) |
| #2282 | Повреждённые даты из URL получают существующий fallback до PHP-парсера; обычные даты/aliases/семья/ночи сохранены | [82f2d936](https://github.com/pyatkoff/poisk-turov-test/commit/82f2d93627079d6f9e2df723471c15d39facd88d) → [283f3db7](https://github.com/pyatkoff/poisk-turov-test/commit/283f3db732e57a450339cdecf818cbdd193f6b32); [baseline fatal и final green](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5649537573) |
| #2285 | Primary-бюджет принимает точные суммы в рублях: 155500–200750 больше не блокируются native step=1000. Суммы не округляются; legacy step сохранён | [4fb09556](https://github.com/pyatkoff/poisk-turov-test/commit/4fb095560d45dd9d39d8a92c751a0cd41a321e93) → [be7f5fe8](https://github.com/pyatkoff/poisk-turov-test/commit/be7f5fe884937b32f06232e9cd56bb136a9ffad4); [точная визуальная приёмка](https://github.com/pyatkoff/poisk-turov-test/pull/2285#issuecomment-5649653514) |

| #2300 | Полная primary-форма остаётся видимой при выдаче, календаре, loading/empty/error; старые hide/show rules и editing-class handlers удалены. Selected сохраняет свой режим | [a7b385e7](https://github.com/pyatkoff/poisk-turov-test/commit/a7b385e713d7e93dc40b7fb08ea636f276950b99) → [f15d1c30](https://github.com/pyatkoff/poisk-turov-test/commit/f15d1c30792609b921496c069cdd43010ff0fa60); [375/1024/1440 и viewport 1440×700](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5651977581), [10-file readback](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5652001024) |
| #2303 | Текущие условия сохраняются в URL; reload/Back/Forward восстанавливают семью, даты, ночи, питание и точный бюджет. Operator/provider/source, произвольные query keys, PII и consent не входят в initial query | [fbf7179e](https://github.com/pyatkoff/poisk-turov-test/commit/fbf7179e9188d8411b6de5d1eb6ea02105757eb1) → [df35e478](https://github.com/pyatkoff/poisk-turov-test/commit/df35e4783992e54772bd049e2cfb1addde289a5d); [375/1440 exact URL receipts и visual](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5652068394) |
| #2305 | Operator facet и карточки используют существующую operatorIdentity; aliases не дробят бренд, один отель с разными операторами получает выбор, неполные данные скрывают/сбрасывают facet. Provider и operator пересекаются на одном offer | [98187c75](https://github.com/pyatkoff/poisk-turov-test/commit/98187c75178e715ae8ada098482b6e8cfbe380b5) → [8a78a709](https://github.com/pyatkoff/poisk-turov-test/commit/8a78a709061de18f1ac5028b3dd6ca1f42e2e111); [visual/JSON 375/1440](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5652082095), [URL/operator exact readback](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5652089991) |

Все десять пакетов CHECKED+MERGED. Для #2285 final whole-site 34727758775,
navigation 34727758774 и Security 34727654231 успешны; оба merged файла
прочитаны обратно и совпали с checked source. Выполнены 15 ширин, 60 состояний
семьи и exact/zero/unset/negative/reversed budget guards. Геометрический стенд
намеренно блокирует справочники: для чистой проверки budget validator заданы
только fixture departure=1/country=4; FormData и price-поля lifecycle взяты
из настоящей served-формы. Это не проверка живой supplier availability.

Приёмку exact изображений #2261/#2276/#2285/#2300/#2303/#2305 выполнил исполнитель с рабочим
доступом к артефактам; ссылки выше сохраняют происхождение подтверждения.
Локальный HTTP 403 при чтении артефакта не обходился. Успешный CI без просмотра
изображений не был засчитан как визуальная приёмка.

### Постоянная форма и единственный URL owner

Подтверждённое скрытие формы закрыто в #2300: accepted native grid сохранён,
`#tourSearch` больше не зависит от наличия карточек/placeholder в results.
«Изменить поиск» переводит фокус к существующему departure control; второй формы
и нового CSS owner нет. Исходная 1440-short full-page картинка была дубликатом;
приёмка использует исправленный exact viewport PNG 1440×700 из artifact
10314345889. Его ZIP SHA-256:
`70b9a57d5e135d7e2158fcd61ee8dcf29f1bb6984e9302bb86724d7698b053ed`.

В #2303 остаётся существующая цепочка:
`v2/index.php` / `form-defaults.php` → native FormData →
`search-lifecycle-v6.js` validation → URL → snapshot → прежний `search_start`.
`boot()` по-прежнему ожидает catalogs.init и hydrateUrlState;
`url-primary-catalog-sync-v1.js` не переносился и не дублировался.
Manual changed conditions создают одну history entry, boot canonicalization
заменяет текущую. Один lifecycle-owned popstate восстанавливает документ
через прежний SSR/catalog/auto-search путь. История, attribution и fragment
проверены; operator не переносится в initial query. Реальные supplier/lead
запросы в стенде заблокированы, вызовы submit проверены через runtime stub.

Первый URL head `65675159` не закрывал полную приёмку; он заменён accepted
`fbf7179e` с Back/Forward, исключением operator и non-null source SHA
в сохранённых URL receipts. Artifact 10314735429, ZIP SHA-256:
`2a6c1f6b772e14d40bf2893f274580297d82ee6b7d1a01a2c044a00f56f93a77`.
Приёмка не доказывает все возможные history transitions:
[review same-query/fragment-only popstate](https://github.com/pyatkoff/poisk-turov-test/pull/2303#issuecomment-5652061881)
остаётся точным непроверенным остатком. Не объявлять его выполненным по
обычному reload/Back/Forward сценарию и не запускать лишний supplier search
при локальном изменении состояния.

### Остаток подготовки и следующий продуктовый шаг

Старый selected-date-v4 claim больше не блокирует форму/URL/generated import:
владелец явно передал общие файлы, что
[зафиксировано в #996](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5651885647).
Старая branch/work сохранена; selected-date остаётся отдельным roadmap пунктом.
Перед новым shared edit по-прежнему нужны свежие release/PR/CI и один exact writer.

Разные operator display keys закрыты #2305 без новой таблицы aliases/matching.
Exact artifact 10314326197, ZIP SHA-256:
`1d2adcb9cc83135dbb56e53dce10b980d2a64de8b3c3571cd9b29c1b50cfea1b`.
Исходные offer/provider identities и цены не меняются; local facets не
вызывают supplier search. Нижеследующее остаётся в существующем SEARCH roadmap:

- Проверка same-query history transition выше; остальная URL приёмка не повторяется.
- Meal summary: не считать aliases разными вариантами и не смешивать AI/UAI/Soft AI.
- Calendar focus/scroll на rerender — отдельный существующий writer/PR #2302;
  читать его свежий статус, не создавать второй calendar owner.
- Country-wide hotel-name UX, плотность карточек, compare, selected, lead handoff
  и цельная mobile/desktop acceptance по уже зафиксированным 12 критериям.

### Публикация и уровень готовности

[Receipt run 34726731028](https://github.com/pyatkoff/poisk-turov-test/issues/996#issuecomment-5649576434)
подтверждает `failed_not_accepted`, `rollback=rolled_back` и
`production_unchanged=true`. После активации накопленного preview проверка
`production_lead_health` ожидала `v2-hmac-bridge-bitrix-lead`, а read-only
ответ содержал `v2-direct-bitrix-lead`. Прежний preview восстановлен.
Эта зависимость требует отдельного решения владельца lead-контракта;
переигрывать публикацию или ослаблять guard нельзя.

Карта owners/build и перечисленные corrections завершены и не перезапускаются.
Указанные непроверенные state/display и продуктовые пункты остаются открытыми; весь refactor-pass и
12 осей ≥9.5 не объявляются завершёнными. Новый числовой балл не назначен.
CHECKED/MERGED не означает preview-published или production-approved.
Physical Safari/iPhone остаётся deferred; production/main и защищённые
supplier/price/lead/analytics/matching/content/SITE/SEO границы сохранены.



## Технический refactor-pass завершён, 2026-09-14

Это финальный closeout только ограниченного подготовительного pass из поручения
владельца. Exact runtime release:
[`6c22b2c9`](https://github.com/pyatkoff/poisk-turov-test/commit/6c22b2c9d92634e009cb2de55aa0bf40eaac3bb3).
Предыдущие датированные формулировки «pass не закрыт» сохраняют исторический
контекст, но больше не являются текущим статусом или новой очередью.

### Итоговая карта подключённых owners и цепочки

| Участок | Единственный текущий owner / проверенная связь |
| --- | --- |
| Form и defaults | `v2/index.php`, `v2/form-defaults.php`; presentation glue только `src/search3/behavior/search-form.js` |
| Submit, URL, reload, Back/Forward | `v2/search-lifecycle-v6.js` и существующий catalog hydration; второго submit/popstate owner не найдено |
| Results и display identity | `v2/results-renderer-v5.js`; `src/search3/behavior/results/local-hotel-filter.js` делегирует ему `mealIdentity` и `operatorIdentity` |
| Compare / shortlist | `src/search3/behavior/results/shortlist.js` хранит exact loaded offer snapshot и выбирает через существующий `.direct-tour`, не через второй controller |
| Selected / return | `v2/tour-controller-v4.js` единолично обрабатывает `.direct-tour`, selected state и return event; `src/search3/behavior/summary-cta.js` остаётся presentation/lead-handoff glue |
| Build | Один ordered manifest, восемь public Search3 assets; retired slots остаются пустыми/provenance и не возвращены как owners |

Проверенная цепочка:
`form → lifecycle/FormData → canonical URL → renderer → local facets → shortlist exact offer → .direct-tour → tour-controller → selected → v2:tour-returned → restored results/focus`.
Конкурирующих submit, popstate, selection или renderer handlers в подключённом
runtime не найдено. Provider/source и tour operator остаются разными полями.

### Закрытые причины и corrections

- #2272 удалил отключённый исторический test owner и сохранил/усилил исполняемые
  guards; повторять этот cleanup не требуется.
- #2303 закрыл form → URL → reload/Back/Forward для primary условий без PII,
  consent, provider или operator в initial supplier query.
- #2305 перевёл result facet на существующий `operatorIdentity`; отдельная таблица
  display aliases или новый matching owner не добавлены.
- [#2411](https://github.com/pyatkoff/poisk-turov-test/pull/2411) закрепил одну
  canonical projection multi-offer/single-offer карточек, exact offer для
  Select/Compare и отдельное отображение provider/operator без второго renderer.
- [#2417](https://github.com/pyatkoff/poisk-turov-test/pull/2417) закрыл прежний
  exact остаток same-query/fragment-only history: переход остаётся same-document,
  не создаёт document или supplier request и восстанавливается тем же lifecycle.
- [#2421](https://github.com/pyatkoff/poisk-turov-test/pull/2421) закрыл последний
  display-normalization остаток: AI+/BB+/HB+/FB+/RO+/SC+ больше не смешиваются
  с базовыми кодами; обычные canonical aliases сохраняются. Local facet использует
  тот же renderer identity; исходные supplier values/payload не меняются.

Для #2421 source `f5a4c504360923ba0449d490dd7f4a5cde314289` проверен Security
`34837089513`, renderer-date `34837089525`, primary-catalog `34837089586`
и whole-site `34837089595`; все успешны. Browser tier прошёл 26 состояний на
320–1440 px с `raw_served_parity=1`, `external_calls=0`, `lead_sent=0`.
Exact artifact `10344892850`, ZIP SHA-256
`2dc4cdfc85180a176162b16137fdea91f92f34398f1b113976984cb4dffa5b6b`;
лично просмотрены `meal-filter-375.png` и `meal-filter-1440.png`.
Source/generated shared runtime вошли вместе.

**Текущий статус: `TECHNICAL_REFACTOR_PASS_COMPLETE` для указанного bounded
scope.** Это не новый глобальный refactor mode и не утверждение, что продуктовый
roadmap либо 12 осей SEARCH-QUALITY-1 достигли 9.5. Связанная с композицией CSS
очистка остаётся частью соответствующего UI-пакета. В частности, #2402 сохраняет
свой exact entry CSS/generated/import claim; мобильный zero-match gap result facets
требует отдельного свободного result-CSS/hash пакета после fresh claim recheck.
Эти продуктовые остатки не отменяют завершение технической карты/цепочки/guards.

Preview для release `6c22b2c9...` в этом closeout не публиковался и
production-approved не объявляется. `main`, production, API/payload, price
arithmetic, lead transport/mapping, analytics, matching/content, SITE/SEO/INT
не менялись; supplier-запросы и реальные заявки не выполнялись.

## Cloud Browser: живая приёмка Search3, 2026-09-15

Поручение владельца — пройти фактический
`https://anytoour.ru/_preview/search3-site-candidate/poisk-turov/`
кликами как турист, затем исправлять только SEARCH #1646/#996. Это новый
браузерный проход, не перезапуск завершённого технического refactor-pass.
Fresh release перед исправлением: `e1c27485e15077abecaa2c7959f06a7213885839`.
[Claim #1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-5679130148)
и запись в body #996 фиксируют единственного writer.

### Фактически пройдено

Cloud Browser / Chromium, CSS viewport **1363×936**. Указанный viewport —
desktop; не выдавать этот проход за mobile или physical Safari.

| Сценарий | Наблюдение |
| --- | --- |
| Основная форма | Выбраны Москва → Египет, 05.10.2026, 7 ночей, 2 взрослых. Даты/native selects работают; расширенные фильтры раскрываются |
| Живая выдача | В однодневном поиске получены 145 отелей. Форма свернулась; фильтры слева; реальные фотографии отелей отображаются |
| Тур из Tourvisor | SWISS HEAVEN SHARMING INN, Sunmar, 05.10.2026, 7 ночей: выбор открыл конкретный тур, UJ614/UJ613, багаж и стоимость 143169 ₽. «Продолжить к заявке» открыл правильный тур/рейс и сфокусировал телефон. Реальная заявка не отправлялась |
| Возврат и пустой фильтр | «Изменить тур» вернул выдачу; запрос `zzzzzz` показал 0 из 145 и действие сброса. Сброс восстановил отели; редактирование поиска сохранило 05.10.2026 |
| Календарь | После изменения конца диапазона на 07.10.2026 и завершения загрузки показаны три даты с ценами. Нажатие 7 октября записало `dateFrom=2026-10-07&dateTo=2026-10-07`, сохранив 7 ночей и 2 взрослых |
| Семейная форма | Выбор трёх детей показал три отдельных native select возраста 0–17; реальный поиск семьи этим действием не выполнялся |
| Сравнение | В поиске на 07.10 добавлены SWISSOTEL RESORT EL QUSIER и SWISSOTEL SHARM EL SHEIKH. Два разных тура сохранили цены 207445/255434 ₽, питание и номера; «Только различия» вынес общие дату/7 ночей/2 взрослых отдельно. Поочерёдное удаление оставило правильный второй тур, затем закрыло сравнение |
| Андромеда, один offer | Детали Sharming Inn / Intourist открылись; «Проверить цену и рейсы» завершилось «Не удалось подтвердить предложение». Это failed-сценарий выбранного offer, не доказательство отказа всех Андромеда-туров и не выполненный INT fix |

В живой странице подтверждены старые source-подписи и отсутствие описания/галереи
в групповой карточке. #2519 (описание/галерея), #2526 (удаление source-фасета)
и ограничение первого раскрытия тремя вариантами уже присутствуют в свежей
release, но не в осмотренном preview. Эти исправления повторно не реализуются.
DOM подключал `search3-results-filters-v1.js?v=927762ecf9c6d967` и shared bundle
`v=827c69d37cfc7498`; robots содержал `noindex,follow,max-image-preview:large`.

### Подтверждённый новый SEARCH-дефект

После сворачивания формы в `resultsTools` остаются только «Предложения», счётчик,
редактирование и сортировка. Нет сводки города/направления, дат, ночей и туристов.
Причина подтверждена и на свежем source: legacy `resultsSearchSummary` исключён
из Search3, а текущий `search-form.js` управляет только раскрытием редактора.

Исправление принадлежит существующему `search-form.js` и `resultsTools`, с CSS
в текущем `results-layout.css`. Сводка должна отражать принятый lifecycle snapshot,
сохраняться при несохранённых правках формы и меняться только при новом принятом
поиске. `resultSummary`, FormData, API, цена, lead, analytics и retired summary
owners сохраняют свои прежние контракты. Точные проверки/PR — в #1646. Позднее подключение presentation owner также восстанавливает сводку из уже принятого lifecycle snapshot; dirty draft для этого не используется.

### Открытые gates

**Мобильная живая приёмка и повторный браузерный проход после публикации не
выполнены.** Документированный Cloud Browser не предоставляет viewport/emulation
API; клавиатурное переключение не изменило 1363×936. Это ограничение текущего
управления, не доказательство неисправности mobile.

Штатная preview-публикация через новый комментарий #996 заблокирована лимитом
2500 комментариев. Тот же publisher на main поддерживает `workflow_dispatch`,
но подключённые инструменты не предоставляют dispatch; локальные `gh` и GitHub
token отсутствуют. Publisher/его допуски не менялись, исторические deployment
запуски не переигрывались. До точной публикации и readback нельзя считать новые
изменения preview-published, live-accepted или production-approved.

Общая оценка 9.5/10 не назначалась. Этот проход не меняет INT/MATCH/content,
production, доставку заявки или Метрику.

### Продолжение Cloud Browser acceptance после перехода на #2530, 2026-09-15

Владелец повторно поручил полноценную SEARCH-приёмку и исправления в #1646/#2530.
Временный authorization-only scope предыдущего прохода завершён в #2532; он не
является ограничением нового поручения. #996 теперь только read-only история.
Свежий baseline: release `10a0c39c7ed27a1c76523d1820381668db0e7ef4`, source
`81e4629bdb4a56c57e568270394b10dab22d8857`, build `34961149173`, artifact
`10394185246`, успешный publisher `34963612416`, receipt `10394760287`.
Это публикация существующих #2526/#2529, а не доказательство приёмки #2531.

Повторный фактический desktop-путь в Cloud Browser, 1363×936:
- Москва → Египет, вылет 05–07.10.2026, ровно 7 ночей, 2 взрослых: загрузилось
  222 отеля; форма схлопнулась; календарь показал три отдельные даты.
- SWISS HEAVEN SHARMING INN: после загрузки 83 вариантов сначала показаны 3;
  «Показать ещё 3» сохраняет первые строки и раскрывает 6 из 83. Каждая строка
  содержит точную дату и 7 ночей, а не поисковый диапазон длительности.
- Sunmar, 05.10.2026: выбранный тур 143 169 ₽; после смены рейса на 4S318/UJ613
  итог 149 029 ₽ и доплата 5 860 ₽ согласованы со сводкой заявки; телефон получает
  фокус. Возврат к выдаче работает. Реальные заявки и контактные данные не отправлялись.
- Нулевой результат по имени `zzzzzz-no-match` показывает 0 из 222 и понятный сброс.

**Подтверждённый остаточный дефект: описание до выбора не опубликовано функционально.**
У карточки `data-hotel-id=453` после загрузки всё ещё одно supplier-фото
`https://static.tourvisor.ru/hotel_pics/main400/453.jpg` (400×267), нет описания или
галереи. При прямом открытии в Cloud Browser точного адреса, который строит текущий
renderer, `/_preview/search3-site-candidate/data/hotel-details-read-v1.php?hotelId=453`,
фактическая страница показывает Apache `Forbidden` / `You don't have permission to
access this resource.` Это обычный запрет serving route, не bot detection.

Read-only source trace: build-search3-whole-site-preview.yml генерирует PHP allowlist
только для index.php, bundle-v1.php и preview-lead-disabled.php; local-read endpoint
в нём отсутствует. Renderer сохраняет неуспешный ответ как null до reload. Browser
fixtures перехватывают этот запрос, а PHP builtin server не применяет .htaccess:
их зелёный результат не доказывает Apache-доступность. Кроме того, preview намеренно
не содержит config.php, от которого зависит fallback DB bootstrap: одного разрешения
PHP может оказаться недостаточно. Наличие/отсутствие данных hotel453 не доказано.
Описание в selected Tourvisor приходит другим маршрутом и не закрывает этот дефект.
Нужен отдельный согласованный preview-local-read routing/INT handoff с реальным HTTP
readback, без blanket-разрешения PHP, копирования секретов или production-изменений.

#2531 возобновлён как существующий bounded source-пакет, с сохранением CSS #2529.
Файл import hashes сериализуется с текущим writer #2538; отсутствие файла в его diff
не считается передачей владения. Final-head CI/screenshots и live readback требуются
после окончательной сборки и публикации; предыдущий green head `2f560def` не заменяет их.

Cloud Browser по-прежнему не объявляет viewport/emulation API. Дополнительная проверка
browser zoom shortcut также оставила 1363×936, DPR1. Mobile live acceptance остаётся
not_measured; будущие CI PNG на 375/430 — evidence точного артефакта, а не Cloud Browser
mobile и не физический Safari/iPhone. Оценка 9.5 или полная приёмка не заявлены.


## SEARCH-QUALITY-1 — Site121/full-path42, 2026-10-10

Это обязательный отчёт качества **реального продуктового пакета**, а не новая
очередь, второй tracker или пересчёт прежних разговорных баллов. Исторические
записи выше сохраняются. Полная матрица и причины по каждому затронутому критерию:
[SEARCH #1646/comment6096285423](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6096285423);
[product #1719/comment6096287276](https://github.com/pyatkoff/poisk-turov-test/issues/1719#issuecomment-6096287276).
Канонический журнал публикации/claims остаётся #4217.

### Exact artifact и сопоставимый scope

- Первый формальный baseline этого сценарного scope: Site118/package41/v164,
  source `75eaf8407449a912f21cceba2d296d17d9659573`. Прежние разговорные
  8–8.5/9–9.5 и покрытие52/55 не являются качественным baseline.
- Установленный кликабельный прототип: **Site121/package42/v165**,
  source `0b7786ae3e94015388d688a4786f4fc0223ca48f`;
  [публичный review route](https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=390).
  Deployment `appgdep_6aca073306a48191b9afb061f5d51cea` SUCCEEDED
  2026-10-10T09:37:05Z; native archive SHA-256
  `1e693aed3ab55b7a2a3e6886dd7cdaaece95c68d3a5c0c6a8b3cf9680151a132`.
- Acceptance/evidence-only source
  `ad778ab870a4b0af33d58c5496e0775b5c32481f` в existing Site source.
  `dist` byte-identical установленному0b7786: новое сохранение/публикация не выполнялись.
  `qa/fullpath42/runtime-receipt.json` и `CHECKPOINT.md` сохраняют точные
  hashes, исходные/финальные кадры, действия и границы.
- Chrome frames360/390/430/768/1280 и short desktop1280×720; сохранённый
  canonical snapshot и явно помеченные demos, supplier0/real-lead0.
  Восемь основных кадров form/results/tour/contact на390/1280.
  Шесть applicable compiled-script suites PASS, syntax/diffcheck PASS;
  jsdom не имеет layout engine.
- Фото и найденные5 Rix IDs относятся ко всем1047 отелям retained snapshot,
  независимо от24 rendered cards. Это не оценка полноты живого NEXT-каталога.
  Не выдумывались aliases, фото/звёзды, supplier/MATCH/LOCAL contracts.

### Неизменная рубрика:12 исторических строк,11 обязательных

Пять частных баллов в прежнем каноническом порядке, значения0/1/1.5/2.
`N = not_measured`: сумма строки не вычисляется. Баллы — экспертная оценка
указанных проверенных сценариев, не измерение конверсии. Проверенные неизменившиеся
критерии сохраняют прежнее exact evidence; byte-identical блоки не проверялись заново
ради балла. Compare/shortlist остаётся deferred вне launch gate.

| Параметр | Baseline118: пять баллов; сумма | Current121: пять баллов; сумма | Delta / незакрытый критерий |
| --- | --- | --- | --- |
| Desktop visual | 2/1.5/2/2/N; not_measured | 2/1.5/2/2/N; not_measured | Проверенные0; full intermediate/short rail+long-content corpus отсутствует |
| Mobile visual | 2/2/2/N/1.5; not_measured | 2/2/2/N/1.5; not_measured | Проверенные0; full0–3 family/long/200% corpus отсутствует |
| Соответствие целевому макету | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Полное exact approved-reference сравнение отсутствует |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; поля/draft/exact multi-ID сохранены |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; facets/count/reset/zero/same-offer/state сохранены |
| Карточки и price/CTA | 2/2/2/2/1.5;9.5 | 2/2/2/2/2;10 | +0.5; price/status/CTA вместе в tablet row |
| Календарь | 2/N/2/2/2; not_measured | 2/N/2/2/2; not_measured | Проверенные0; sparse/full geometry corpus отсутствует |
| Compare / shortlist — deferred вне запуска | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Исключён из11 обязательных, не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; exact context/authority/unknown/return сохранены |
| Lead-form handoff | 2/1.5/2/2/2;9.5 | 2/1.5/2/2/2;9.5 | 0; changed-price pair360 consent ниже первого fold |
| Accessibility / state UX | 2/2/N/2/2; not_measured | 2/2/N/2/2; not_measured | Проверенные0; full readability/targets/200% corpus отсутствует |
| Архитектурная чистота / стабильность | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Полного comparable five-criterion baseline нет |

**Карточки — причины всех пяти баллов.** Медиа/название/место2→2
(настоящие фото либо честный placeholder); достоверные факты без дублей2→2
(existing projection/source invariants); условия representative offer2→2
(exact date/nights/room/meal/operator); различение minimum/concrete/saved/unverified
price2→2 (authority не менялась); доступное действие1.5→2
(тот же RIA96953₽ на768: price/status/CTA в одной полной строке).
Сумма9.5→10. Baseline `qa/results41/anytour-after-results768.jpg`;
after `qa/fullpath42/after/fullpath42-after-card768.jpg` и results390/1280.
Числовое поднятие CTA из кадров с различным scroll offset не заявляется.

**Lead — причины всех пяти баллов.** Правильный выбранный тур2→2
(exact offer/party/ages0/8/current pair06:05+08:05/whole-tour187700₽);
действие/фокус1.5→1.5 (deferred family360/390 умещает phone/consent/CTA,
changed-price pair360 требует scroll); validation/pending/error2→2
(actual consent focus/scroll плюс compiled late/duplicate/closed guards);
draft/отдельное согласие2→2 (phone retained, changed pair consent reset);
контролируемый возврат без реальной отправки2→2
(actual Back-to-detail и explicit close→nativeForward сохраняют
review/phone/consent/187700). Existing nested Back расходует Forward entry;
nested Back→Forward не объявлен completed. Сумма9.5→9.5.
Baseline `qa/fullpath42/before/fullpath42-before-contact-family390.jpg`;
финальные family360/390/1280, pair360/390, validation360/history-forward360
в `qa/fullpath42/final/`. Восстановление временно потерянных child ages —
исправление invariant, не рост балла. Pre-age-fix contact/capture races исключены.

Наследованное exact evidence измеренных строк:
Form `qa/results41/form-behavior.json` →
`qa/fullpath42/form/behavior.json` + `logs/form-final.log`;
Filters `qa/results41/cards-filters-behavior.json` →
`qa/fullpath42/logs/results.log`;
Selected/Lead `qa/results41/decision-behavior.json` →
`qa/fullpath42/behavior/decision-behavior.json`,
`return/selection-return.json`, `logs/decision-final.log`,
`logs/selection-return-final.log`.
Site120 after-кадры формы/picker/cards/tour используются только для неизменившихся
блоков, parity120→121 записана отдельно. Контакт принят по финальному121.

### Стадии и следующий крупный блок

**Макеты:** восемь основных desktop/mobile кадров готовы, общая визуальная сумма
не назначена без long/200% полного scope.
**Кликабельный прототип:** Site121, Cards10/Lead9.5 только в указанном scope;
`PROTOTYPE_PENDING_OWNER_ACCEPTANCE` перед NEXT migration.
**Установленный NEXT:** без изменений, PR4501/source
`9b74bdfae2fa9244be52e822d64db1e7282e6901`,
[terminal6091085104](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6091085104).
UI delta0; макет/prototype баллы ему не наследуются.

Physical iPhone/Safari/OS keyboard/safe-area/200% **UNVERIFIED** для этого artifact,
отдельно от Chrome/jsdom. Полный0–3 family/long-content/200% scope not_measured;
общего9.5–10 нет. Architecture/CI/количество версий не компенсируют слабый mobile.

Следующий разрешённый цельный дизайн-блок: mobile family/long conditions/changed-price
path360/390/430, большая выборка выбранных отелей и200% readability/focus/scroll.
Подтверждённые слабые точки — consent ниже первого fold при changed price360
и chips/footer, оставляющие мало видимых hotel rows. Existing owners и рубрика
сохраняются; новый полный прототип требует принятия владельцем перед NEXT transfer.
Supplier/real lead/provider/money/DB/LOCAL/MATCH/analytics/main/production/schedules
этим пакетом не менялись.



## SEARCH-QUALITY-1 — Site122/mobile43, 2026-10-10

Это обязательная запись результатов завершённого продуктового prototype-пакета.
Исторические записи выше сохранены. Формальная матрица и причины:
[SEARCH #1646/comment6096572288](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6096572288),
[product #1719/comment6096572430](https://github.com/pyatkoff/poisk-turov-test/issues/1719#issuecomment-6096572430).
Scoped audit claim #4217/comment6096567581. Старый audit claim RELEASE6096307906.

SEARCH-QUALITY-1 — Site122/mobile43, 10.10.2026. Подтверждённый продуктовый результат и сопоставимые баллы.

**Artifact / стадии.** Существующий публичный Site122/package43/v166, installed source `3012d12be8e5104eb3538b7ffb443d6e5c2e77dd`, deployment `appgdep_6aca1070366c8191a7ffddca6b8c7dd8` SUCCEEDED2026-10-10T10:16:33.205968Z. Native get_site_version source/archive readback совпал: sha256 `044e3883ff83a3cfee82666036a4fa88ebe14a694dfb394ca52bd8917b3e5f2e`. Actual footer43/v166.
Публичный маршрут: https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=430
Acceptance/evidence-only source `0f5c3f5e633a27d9f3579c84ce5f60fb12e6ba58`: `dist` byte-identical3012, `qa/mobile43/runtime-receipt.json`, before/final JPG, focused logs. Второе save/deploy не выполнялось.
Макеты: новые реальные desktop/mobile contact/picker кадры в существующем цельном пути. Кликабельный прототип: Site122, **PROTOTYPE_PENDING_OWNER_ACCEPTANCE_BEFORE_NEXT**. Установленный NEXT: **без изменений**, PR4501/source`9b74bdfae2fa9244be52e822d64db1e7282e6901`, terminal6091085104; delta0, макет/prototype баллы ему не наследуются.

Рубрика прежняя:12 исторических строк/11 обязательных, Compare deferred; пять критериев в каноническом порядке, значения0/1/1.5/2. N=not_measured запрещает сумму строки. Предыдущая сопоставимая оценка — Site121/formal report6096285423, а не conversational8–8.5/52из55. Экспертная оценка перечисленного Chrome corpus, не конверсия.

| Параметр | Previous121: 5 баллов; сумма | Current122: 5 баллов; сумма | Delta / изменение или недостающая проверка |
| --- | --- | --- | --- |
| Desktop visual | 2/1.5/2/2/N; not_measured | 2/1.5/2/2/N; not_measured | Измеренные0; full intermediate/short rail+long-content corpus отсутствует |
| Mobile visual | 2/2/2/N/1.5; not_measured | 2/2/2/N/2; not_measured | **C5+0.5**: consent выше footer360, picker footer ниже84px; C4 full long-content/text200% corpus отсутствует |
| Соответствие целевому макету | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Полного exact approved-reference сравнения нет |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; exact IDs/OR/cap20/draft сохранены |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; прежние facets/count/reset/zero/state сохранены |
| Карточки и price/CTA | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; прежний tablet gain не начислен повторно |
| Календарь | 2/N/2/2/2; not_measured | 2/N/2/2/2; not_measured | Измеренные0; sparse/full geometry corpus отсутствует |
| Compare / shortlist — deferred вне запуска | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Вне11 обязательных; не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; offer/pair/party/price/fuel authority сохранены |
| Lead-form handoff | 2/1.5/2/2/2;9.5 | 2/2/2/2/2;10 | **+0.5**: changed-price contact360 phone/consent/CTA одновременно доступны |
| Accessibility / state UX | 2/2/N/2/2; not_measured | 2/2/N/2/2; not_measured | Измеренные0; full readability/targets/text200% corpus отсутствует |
| Архитектурная чистота / стабильность | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Полного comparable5-criterion baseline нет |

**Lead, все5 причин.** Правильный выбранный тур2→2: отель/date/nights/party/каждый child age0/8 (и actual0/8/17), exact pair06:05/08:05, whole-group187700 сохранены. Понятное действие/фокус1.5→2: same360×800 changed-price family2 before consent-row bottom727.765625 при footer663.828125; after604.953125, scroll0. Раньше64px под footer, теперь59px свободно; screenshot before/after одинаковых данных. Phone и consent focus при последовательной validation actual сохранены. Validation/pending/error2→2: existing six-state compiled corpus PASS, никаких real-send; late/duplicate/closed/generation gates не ослаблены. Draft/отдельное согласие2→2: actual test-only phone/consent Back→reopen сохраняются; compiled exactpairchange reset PASS. Контролируемый возврат2→2: existing return/independent-other-offer/passive checks сохраняют context и не запускают реальные операции. **9.5→10 только в этом corpus**, не весь продукт.

**Mobile, частные причины.** C1/C2/C3=2→2 с exact прежним evidence; C4=N: actual2/3children и compiled0/1/2/3 не заменяют full long-content/text200% coverage. C5«отсутствие перекрытий/лишней прокрутки в проверенном браузере»1.5→2: limiting360 contact исправлен;390 consent620.828125<footer707.828125;430708.828125<footer795.828125, без заявленного числового подъёма430. Actual0/8/17 retained во всех width changes; нет horizontal overflow на360/390/430/768/1280. Полные room/meal/baggage/operator факты доступны в native review на768/1280. Rix5IDs4234/813/748/1823/686: footer214.5→130.5px (−84), full chosen names/removal в стабильном native disclosure, clearOR«Любой из выбранных отелей», Apply exactIDs URL/results5отелей·5вариантов. Late lookup не закрывает disclosure и не теряет input focus. Short430×480 query/button доступны; это уменьшенное окно, **не OSkeyboard**.
Финальные430 кадры: top-frame + отдельный bottom с consent/CTA; top-only не является доказательством всей формы. Capture races/cached clips/fullPage timeout не объявляются regression и исключены из acceptance.

**Измеренные fully-covered затронутые строки без роста.** Form2/2/2/2/2: (1)все поля сохранены;(2)canonical source/aliases/photo facts прежние,1047retainedhotels независимо24cards;(3)exactmultiIDsOR/cap20;(4)query не меняет selection/Cancel откатывает/ApplyURL;(5)late/native selected focus и removal возвращают focus. Шесть applicable suites PASS, form включает8/20IDs на5widths/21stcap.
Filters2/2/2/2/2 и Selected2/2/2/2/2 переносятся с exact причин/evidence6096285423; current decision/return/results logs подтверждают сохранение. У этих строк новых частных улучшений нет.
Карточки пять причин прежние: genuine-media-or-placeholder, sourcefacts, concreteconditions, price-status distinction, availableCTA — все2→2; renderer/cards/date/sort byte-identical, повторного tablet прироста нет.

Наследованное evidence: Site121/formal report6096285423/#1719/6096287276, auditPR4506/release58b4cce8; qa/fullpath42 sourcead778ab. Новое: qa/mobile43/form/behavior.json (large8/20IDs), decision/decision-behavior.json, return/selection-return.json; logs/form-final.txt/decision.txt/return.txt/results.txt/editors.txt/interactions.txt; final/rix5* и family3-pair360/390/430-bottom,review768/1280. Незатронутые byte-identical блоки не перепроверялись ради балла.

**Границы и следующий цельный блок.** Physical iPhone/Safari/OSkeyboard/safe-area/text-only200% UNVERIFIED для122. Нет общей оценки9.5–10. Следующий разрешённый блок — actual long hotel/room/meal + family0–3 readable path360/390/430 и text200% в существующих владельцах, без all55-before-mockup; отдельное exact evidence до повышения C4/accessibility. Полный прототип требует принятия владельцем перед NEXT переносом. Supplier0/real-lead0; SAMOpaused, MAIN/production/DB/provider/money/LOCAL/MATCH/analytics/workflows/schedules не менялись. Canonical sourceclaim6096360802/publisher6096475218/auditclaim6096567581, terminal в#4217 после audit exact-headCI/release/readback.

## SEARCH-QUALITY-1 — long44 / Site125, 2026-10-10

**Артефакт и стадии.** Установлен существующий публичный Site125/package44/v167,
source `a77f525c4f9cacf2da4f20b58eb2702a5f619943`,
native version `appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_a03fc74e759c81918e2f8901f4bc641d`,
deployment `appgdep_6aca1e836c2c8191a2d6843d6c5692b1` SUCCEEDED
2026-10-10T11:16:34.629213Z. Exact source/deployment/native archive readback совпал;
storage SHA256 `a3eccac6826e7567ac5f267bb6563e5fe504614738a679fa896a9dc5df609a0a`.
Исходный stock-helper gzip SHA256 `52faccdcec80ae231f46b24afef5c2a6d5e0a5a967b14355d40f98ba7a598caf`
— отдельная byte provenance, не равенство нормализованному native tar.
Actual footer44/v167. Acceptance/evidence-only source `4c2700072446532acb0012a33a6755b7f0f95b9e`: `dist` byte-identical установленномуa77, clean checkout; дополнительного save/deploy для evidence не было. Публичный проверенный маршрут:
https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=360

Предыдущая сопоставимая оценка: Site122/package43/v166/source3012d12,
[полный formal report6096572288](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6096572288),
evidence-only source `0f5c3f5e633a27d9f3579c84ce5f60fb12e6ba58`.
Рубрика не меняется:12 исторических строк,11 обязательных, Compare deferred;
вектор из пяти канонических критериев, значения0/1/1.5/2.
N=not_measured: при одном N сумму строки не вычислять. Это экспертная оценка
указанного corpus, не конверсия. Прежние разговорные8–8.5/9–9.5 и52/55 не baseline.

| Параметр | Previous122: пять баллов; сумма | Current125: пять баллов; сумма | Delta / evidence или конкретный пробел |
| --- | --- | --- | --- |
| Desktop visual | 2/1.5/2/2/N; not_measured | 2/1.5/2/2/N; not_measured | Измеренные0; density1.5 не закрыта. Нет полного intermediate/short-screen form+rail+card corpus |
| Mobile visual | 2/2/2/N/2; not_measured | 2/2/2/N/2; not_measured | Измеренные0; новый long5483/short480 сценарий устранён, но C4 full long/family/text200% ещё отсутствует |
| Соответствие целевому макету | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Нет полного одинакового exact approved-reference сравнения формы/rail/фильтров/cards/mobile |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; full primary fields, exact hotel5483 и8/20IDs/cap/draft/Cancel/Apply сохранены |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; те же facets/count/reset/same-offer/state без supplier calls |
| Карточки и price/CTA | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; renderer/price authority прежние, current long title/place/status/action доступны |
| Календарь | 2/N/2/2/2; not_measured | 2/N/2/2/2; not_measured | Измеренные0; sparse/full geometry corpus целиком не завершён |
| Compare / shortlist — deferred вне запуска | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Вне11 обязательных; не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; exact offer/room/meal/party/unknown/group-total/back сохранены |
| Lead-form handoff | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; current contact owner не изменён; точное previous43 actual evidence и latest compiled guards сохранены |
| Accessibility / state UX | 2/2/N/2/2; not_measured | 2/2/N/2/2; not_measured | Измеренные0; full target/readability/text200% corpus отсутствует |
| Архитектурная чистота / стабильность | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Нет полного сопоставимого пяти-критериального baseline; owner/isolation evidence не превращено в общий балл |

**Новый работающий эффект, одинаковые данные.** Canonical retained hotel5483:
RAMADA PLAZA BY WYNDHAM ISTANBUL ATAKOY (EX. RAMADA HOTEL AND SUITES ISTAMBUL ATAKOY),
Бакыркёй, Турция,5 canonical stars, существующий guest score4.8. Фото отсутствует:
честный placeholder, без обогащения каталога. Exact сохранённый offer:
Standard City View / Завтраки /5→12Oct /7ночей /2взрослых /FUN&SUN /144525₽.
Это cached snapshot от23.09, не актуальная bookable/final verified цена.

На360 fixed hotel header168→61px; scrolling body415.609375→522.609375
при360×800 и114.796875→221.796875 при360×480: **+107px** в обоих matched scopes.
Полное canonical имя и география перенесены в доступный scrolling body;
полное имя осталось accessible dialog label. Первое About открывает identity
через existing positionHotelSection, Rooms сохраняет exact выбранный key.
В compact tripbar длинный список заменён страной/count; полный выбор доступен
через форму и полное имя existing edit button. Compact168.421875→98.375px на360.
Нет нового renderer/helper/style owner или override layer.

Во время actual124 acceptance найден отдельный реальный дефект: fixed bar
bottom98.375, title top88.375 —10px перекрыты. Current125 existing html/updateCompactSearch
измеряет actual bar+8 перед explicit results scroll. Settled360 title108.375
при bar98.375 — **10px свободно**,390/430 также gap10;768/1280 barhidden/titlevisible.
Doc/body horizontal overflow0 на пяти widths. Original hidden-bar fallbacks сохранены.
Late font/content-only reflow без resize/scroll отдельным observer не покрыт;
точное after-font/resize evidence не превращено в обещание всех возможных reflows.

Actual360×480 Rooms: scroll276, heading155.09375 ниже nav138.8125,
focus hotel-rooms-heading, прежний exact offer key. CheckConditions показывает
«Цена и рейсы пока не подтверждены», запрещает заявку; Back возвращает ту же
выбранную строку/144525 и focusCheckConditions, close возвращает focusОбОтеле.
Query Rix показывает5 snapshot suggestions, оставляя5483 выбранным; Cancel picker
и отдельно Cancel формы после закрытого modal возвращают1hotel/1offer/1–7Oct/2взрослых
и full-named edit-button focus. Согласие/contacts/generation/price gates не изменены.

**Все пять причин в затронутых fully-covered строках, канонический порядок.**

- Form: C1 city/destination2→2 — Москва/Турция и exact long5483 сохраняются;
  C2 dates/nights2→2 — исходные1–7Oct/7nights, calendar boundaries/edit cancellation из exact previous corpus и current suite;
  C3 tourists/children/ages2→2 — families0/1/2/3 compiled и previous43 actual0/8/17;
  C4 region/hotel/category/meal2→2 — настоящая geography/star, exact multi-ID OR/cap20,
  query не выбирает, Cancel откатывает, Apply сохраняет URL/filter;
  C5 accessible budget/primary-advanced separation2→2 — прежние primary fields и budget доступны,
  primary operator не добавлен. Сумма10→10.
- Filters: C1 trustworthy facets2→2; C2 active values/counts2→2; C3 reset/zero recovery2→2;
  C4 same-offer combination2→2; C5 state preservation without supplier calls2→2.
  Exact inherited fullpath42/formal6096285423 плюс latest results/editor suites; сумма10→10.
- Cards: C1 media/name/place2→2 — real media или honest placeholder, full long name/place;
  C2 facts/no duplicates2→2 — canonical projection прежняя; C3 representative exact offer2→2 —
  dates/nights/room/meal/operator совпадают; C4 minimum/concrete/saved/unverified2→2 —
  snapshot price честно требует проверки; C5 unambiguous accessible action2→2 —
  existing СмотретьТур и price/action row сохранены. Exact previous tablet gain не начислен повторно.
  Current five-width actual и results suite; сумма10→10.
- Selected: C1 exact context2→2 —5483/key сохранён; C2 room/meal/party2→2 —
  StandardCityView/BB/2adults и previous family corpus; C3 verified flights or honestunknown2→2 —
  текущий cached offer не превращён в live; C4 agreed total/composition/no new arithmetic2→2 —
 144525 за группу остаётся saved/unverified, fuel не добавлялся повторно;
  C5 change/return without tour substitution2→2 — actualCheckConditions→Back→samekey,
  compiled independent-other-offer/party/pair guards. Сумма10→10.
- Lead: C1 right tour2→2; C2 action/focus2→2; C3 validation/pending/error2→2;
  C4 permitted draft/separate consent2→2; C5 controlled return/no real send2→2.
  Неперепроверенная byte-identical contact композиция переносится с точным
  previous43 family3/pair/phone/consent frames и report6096572288;
  current six-state/return suites подтверждают сохранность changed-price,
  late/duplicate/closed/expiry/independent-other-offer и consent reset. Сумма10→10
  только в том прежнем bounded Chrome corpus, не physical/real delivery.

**Visual partial причины и независимая проверка.** Mobile C1/C2/C3=2→2:
единая mobile композиция, full canonical identity/place/status, доступные действия
в actual360×480. C4=N→N: один длинный отель не закрывает full long room/meal/baggage/operator
+family0–3+text200% corpus. C5=2→2: previous narrow corpus исправен, новый long-context
дефект устранён и evidence расширено, но критерию уже было2; не начислять ещё0.5.
Desktop C1=2→2 сетка; C2=1.5→1.5 density остаётся; C3=2→2 типографика/longidentity;
C4=2→2 CTA/контролы; C5=N→N промежуточные/короткие экраны полного пути не завершены.
Независимый read-only reviewer подтвердил actual settled mobile, profile/rooms/unknown,
delta0, отсутствие новой существенной визуальной проблемы в просмотренном scope.

**Evidence и исключения.** `qa/long44/runtime-receipt.json`,
`source-checks.json`, `capture-scope.json`, `CHECKPOINT.md`; matched before360
в `before/mobile44-long-profile360*-before.jpg`, actual124 profile in`after124/`
(profile owners byte-identical125), actual125 in`final/`: clearance-card360-settled,
resize390/430/768/1280, profile360-short-settled, rooms360-short, unknown360-short.
Before файлы labelled768/1280 показывают430/768 соответственно: оригиналы сохранены,
но **исключены из matched-width comparison**, wide numerical gain не заявлен.
`excluded/` содержит intermediate smooth-scroll/viewport-paint и raced dependent
Cancel captures; controlled closed-modal Cancel отдельно успешно проверен,
непричастные product defects по raced кадрам не объявлены.

Шесть latest applicable compiled-script suites exit0/PASS:
verify-mobile-form (long5483,8/20IDs/21stcap/fivewidths),
verify-selection-return, verify-search-editor, verify-sites-interactions,
verify-decision-prototype, verify-results-block. Syntax/diff PASS.
JSdom не имеет layout engine, поэтому rectangles берутся только из actual Chrome.
Harness scrollTo support добавлен по фактическому отсутствующему jsdom API,
значимые assertions не ослаблены. Нет нового supplier request или real lead.

Site123/e34 и124/257 — известные terminal публикации того же пакета44:
первый runtime metadata defect166/43→167/44 исправлен двумя existing constants;
затем proven results overlap устранён в125. Не UNKNOWN/no-replay и не качественный
рост за количество версий. Publisher6096918226 RELEASE6096980721;
sourceclaim6096684702 и auditclaim6096980897 завершаются после evidence/audit readback.

**Стадии не наследуют оценки.** Макет: реальные текущие desktop/mobile layout-кадры
общего пути дополнены long profile; общий visual score остаётсяnot_measured.
Кликабельный прототип: exact Site125 выше, bounded строки из таблицы и actual actions,
PROTOTYPE_PENDING_OWNER_ACCEPTANCE_BEFORE_NEXT. Установленный NEXT остаётся
PR4501/source`9b74bdfae2fa9244be52e822d64db1e7282e6901`, terminal6091085104:
UI delta0, новый прототип ему не переносился и его баллы не наследуются.
Physical iPhone/Safari/OSkeyboard/safearea/text-only200% UNVERIFIED;
уменьшенный480px wrapper — browser viewport, не OSkeyboard/physical приёмка.

**Следующий цельный разрешённый этап.** Закрывать оставшийся C4/a11y corpus:
long hotel/room/meal/baggage/operator +family0–3 на360/390/430 с увеличением текста,
сохраняющим полноту имени, context и fixed действия на form→hotel→tour→contact→Back;
parallel short desktop/intermediate form+rail/card acceptance для C5.
Использовать тот же coherent product и current owners, не all55-before-mockups,
не новую косметическую очередь. Исправлять только наблюдаемый остаток, сохранять
exact baseline при каждом gain. Физическую проверку и approval полного concrete
prototype перед NEXT переносом вести отдельно. Необоснованный общий9.5–10 не заявлен;
live TV+directANEX, SAMOpaused, MAIN/production/provider/money/DB/LOCAL/MATCH/
analytics/workflows/прочие schedules этим пакетом не менялись.

## SEARCH-QUALITY-1 — decision45 / Site126, 2026-10-10

**Результат.** В существующем desktop/mobile прототипе убран большой пустой фото-блок в карточках без фото; полное длинное название получает ширину условий, маршрут показывает страну/число отелей, повтор в active-chip компактен. Полная canonical identity доступна в карточке/форме/accessible edit/removal. Один существующий card/style/summary owner; реальные mobile-фото1.8 и открытая семидневная лента не изменены.

**Exact artifact.** Site126/package45/v168, pushed source `982ff8122a7dcfc55ee72e0862922ff3a8bff96d`;
native version `appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_8015c288567481918724dd3b03168e53`;
deployment `appgdep_6aca2eacf6b88191aa4395b466c157e6` SUCCEEDED
2026-10-10T12:25:33.229220Z. Stock archive local gzip SHA256
`dd9f59f73fbb0c95431c6d009926be6df10b08995f066dda01f59f39d9db196f`;
native normalized tar SHA256
`e393082abe873d4ddc4427a6ba5d105067c6386d1fd7afcd7e0d965fe4934610`.
Exact native source/version/deployment/archive readback совпал; actual footer45/v168.
Acceptance-only source `2f4ed60019bcfc01f6e31874114499dcdd57d80d` retains actual screenshots/receipt/review; dist/hosting byte-identical installed982ff812. Второго save/deploy для evidence нет.
Public URL verified by actual Chrome: https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=430

**Оценка и previous.** Тот же SEARCH-QUALITY-1,12 исторических строк/11 обязательных, Compare deferred; пять канонических критериев0/1/1.5/2. N=not_measured, строка с N не суммируется. Previous exact Site125/a77, acceptance4c270007, report[#1646/6097030721](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6097030721). Это экспертная оценка объявленного corpus, не конверсия и не новая методика. Unchanged criteria carry exact cited previous evidence; разговорные8–9 и coverage52/55 не baseline.

| Параметр | Previous125: пять баллов; сумма | Current126: пять баллов; сумма | Delta; эффект/точный пробел |
| --- | --- | --- | --- |
| Desktop visual | 2/1.5/2/2/N; not_measured | 2/2/2/2/N; not_measured | **C2 +0.5**, остальные measured0; matched1280 blank-photo/title/summary исправлены. C5 full intermediate/short desktop form+rail+card отсутствует |
| Mobile visual | 2/2/2/N/2; not_measured | 2/2/2/N/2; not_measured | Measured0; current360short/390/430 читаемы. C4 full long room/meal/baggage/operator+family0–3+text200% не закрыт |
| Соответствие целевому макету | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Нет полного одинакового exact approved-reference form/calendar/filters/card/mobile comparison |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; full context/edit/draft/Cancel/Apply/exact IDs сохраняются |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; chip visual compact, exact removal/count/facets/reset/same-offer/state прежние |
| Карточки и price/CTA | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; already2 criteria не получают повторного роста; actual no-photo exact5483, факты/price/action доступны |
| Календарь | 2/N/2/2/2; not_measured | 2/N/2/2/2; not_measured | Measured0; sparse/full geometry corpus целиком отсутствует, rail owner byte-identical |
| Compare / shortlist — deferred | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Вне11 обязательных; не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; exact cached offer/room/meal/party/unknown/total/back сохраняются |
| Lead-form handoff | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; contact owner byte-identical, previous43 exact actual path + latest compiled guards carry; no real delivery |
| Accessibility / state UX | 2/2/N/2/2; not_measured | 2/2/N/2/2; not_measured | Measured0; полный readability/targets/text200% corpus отсутствует |
| Архитектурная чистота / стабильность | N/N/N/N/N; not_measured | N/N/N/N/N; not_measured | Нет полного comparable5-criteria baseline; unchanged owner/isolation checks не превращены в quality sum |

**Matched before/after и действия.** Canonical snapshot5483:
RAMADA PLAZA BY WYNDHAM ISTANBUL ATAKOY (EX. RAMADA HOTEL AND SUITES ISTAMBUL ATAKOY),
Бакыркёй/Турция/5★/existing guest4.8. Настоящих фото в retained snapshot нет.
Exact cached offer `andromeda%3Acached%3Aandromeda%3A80ce636bba307fc5f3e6ceca112447a8eae98fd86620d97ebe5d74eb09aa46ec`:
Standard City View / Завтраки /FUN&SUN /5→12Oct /7ночей /2взрослых /144525₽.
Saved/unverified не final_price_verified/bookable; никакой арифметики сборов.

Actual1280 same data: empty photo278.6875×334.4375→honest679×40px;
full title332.84375×108→611.53125×54px (4→2строки). Card931×397.4375→931×393.4375:
главный gain — распределение пространства/читаемость, **не обещание резкого сокращения общей высоты**.
Маршрут больше не повторяет всё canonical имя; full edit aria сохраняет его и географию.
Active chip321.625px вместо полного длинного повторения, full title/aria/exact5483 неизменны.
Actual768 full final card: longtitle389.53125×108, price210px, CTA48px,
все факты/144525/действие видимы без overlap; page horizontal overflow0.

**Поправка mobile before/after:** direct before-image inspection показывает уже4строки
при20px, а after также4строки при18px/actual100.75px. География была видна и прежде.
Ранее в commentary по неверно восстановленному измерению сказано5→4/география появилась;
это явно исправлено владельцу.139px не retained exact evidence, удалено из receipt.
Это не baseline recalibration/рост/падение Mobile; никакого gain за него не начислено.
При360×480 header61/body221.796875/footer168.390625 сохранены, overflow0;
390 profile body584.265625/title100.75/footer148.09375 и430 body666.984375/title75.5625/footer148.09375,
без overlap/overflow. Wrapper480 — browser viewport, не OSkeyboard evidence.

Actual Rooms settled scroll265/focus hotel-rooms-heading; CheckConditions показывает honestunknown
и запрещает заявку; Back возвращает exact key/room/meal/party/144525/body265;
close возвращает focusОбОтеле. Через новую summary edit открывается полное имя в форме.
QueryRix не меняет выбранный5483/focusdestination-query, показывает exact retained
4234/813/748/1823/686 с существующими genuine photo URLs; query ничего не покупает.
PickerCancel после подтверждённого закрытия + отдельно formCancel возвращают
hotel5483/filter5483/1offer/первоначальные даты/party и full-named edit focus.

**Пять причин затронутых fully-covered строк, canonical order.**

- Form2/2/2/2/2→то же10: C1 city/destination — новая компактная summary открывает полную canonical форму5483;
  C2 dates/nights —1–7Oct/7night и previous boundaries/cross-year guards сохранены;
  C3 tourists/children/ages — previous43 actual0/8/17 и current compiled0–3 carry;
  C4 region/hotel/category/meal — exact IDs OR/cap20/21st rejection, query/Cancel/Apply current source suite и actual Cancel;
  C5 accessible budget/primary-advanced separation — прежние controls/budget, no primary operator. Unchanged reasons не новый gain.
- Filters2/2/2/2/2→10: C1 trustworthy facets прежние; C2 chip/count retains exact value5483/full removal aria;
  C3 reset/zero — previous fullpath42/6096285423+latest results suite;
  C4 same-offer combination и C5 no-supplier state — тот же previous corpus+current suite.
- Cards2/2/2/2/2→10: C1 honest media/name/place, no-photo conditional действительно определяется existing photos.length;
  C2 canonical facts без нового дублирования; C3 same representative exact room/meal/operator/date/party;
  C4 saved/unverified price и minimum/concrete distinction сохранены; C5 full accessible action,
  actual tablet+desktop CTA visible. Density gain записан только в DesktopC2, не повторно в уже2 card criteria.
- Selected2/2/2/2/2→10: C1 exact5483/key; C2 same StandardCityView/BB/2adults;
  C3 honestunknown вместо выдуманных flights; C4 total144525 за группу/saved composition без повторногоfuel;
  C5 actual Back/close + current independent-other-offer/party/pair guards. Previous exact family corpus carry.
- Lead2/2/2/2/2→10 в прежнем bounded Chrome scope: C1 right-tour, C2 action/focus,
  C3 validation/pending/error, C4 contactdraft/separateconsent и C5 controlledreturn/no real send.
  Owner не изменён; exact previous43 family3/pair/phone/consent frames and6096572288 carry,
  current selection-return/decision suite verifies changed-price/late/duplicate/closed/expiry/consent reset.
  Это не physical или real lead transport acceptance.

**Частные visual и независимый QA.** Existing read-only reviewer /root/assessment_evidence
просмотрел paired1280 summary/results, final768 card, settled360/390/430 profile,
terminal Rooms/unknown. DesktopC1grid2, C2density1.5→2, C3typography2,
C4CTAcontrols2, C5N; нового существенного дефекта в этих exact frames нет.
MobileC1composition2/C2readability2/C3actions2/C4N/C5overlap-scroll2;
полная строка и общая9.5–10 не объявлены. Mobile correction отдельно передана reviewer.
Непроверенный критерий остаётся N, отсутствующая повторная проверка не0/регрессия.

**Evidence.** `qa/decision45/before/card1280.jpg`, `summary1280.jpg` actual Site125 this pass;
`before/profile360-short.jpg` exact carry `qa/long44/final/long44-final-profile360-short-settled.jpg`.
Current `after/summary1280.jpg`, `card1280.jpg`, `card768.jpg`,
`profile360-short.jpg`, `profile390-settled.jpg`, `profile430.jpg`,
`rooms360-short-settled.jpg`, `unknown360-short.jpg` and mobile JSON rectangles.
`runtime-receipt.json`/README/REVIEW with comparison scope, exclusions and correction.
Intermediate `profile390.jpg` (preceding closed-card paint), `rooms360-short.jpg`
(smooth-scroll pending), `tablet768.jpg` (card below viewport) are retained **and excluded**
from final modal/room/card visual acceptance; settled successors above are authoritative.
Six applicable actual compiled/jsdom suites exit0: editor/interactions/mobile-form/selection-return/
decision-prototype/results-block; syntax/diffPASS. These use local fixtures only, no layout engine;
actual screenshot/rectangles are separately Chrome, supplier0/reallead0.

**Стадии и следующий блок.** Макет: actual current desktop/mobile layout frames,
partial DesktopC2+0.5, full visual rowsN. Кликабельный прототип: установлен exactSite126 выше,
actual path/state tested, bounded fully-covered rows10, fullprototype pending owner approval beforeNEXT.
Установленный NEXT теперь чужой completedPR4511/source
`73a8e38d2b179cc7334c547e093207ba052082d3`,release
`4a5d84348f6fcce0a39c288ebe7f3f72c4e790cd`,
terminal[#4217/6097387169](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6097387169):
retained LOCAL reader enabled, Site126 UX не переносился; его баллы NEXT не наследуются.
Новый NEXT overlay не принят этими Site screenshots. Никакой повтор publisher6097341097/run38050989267.

Следующий цельный разрешённый этап: mobile long hotel/room/meal/operator/family0–3
form→hotel→exacttour→contacts→Back с увеличенным текстом; parallel short desktop form/rail/card
в существующих owners. Заполнить только отсутствующие/затронутые exact scenes и исправлять
наблюдаемые препятствия, переносить byte-identical evidence; не all55-before-mockup/newtracker.
Physical iPhone/Safari/OSkeyboard/safearea/text-only200% UNVERIFIED; concrete fullprototype approval
перед NEXT переносом отдельно. Нет MAIN/production/provider/money/DB/LOCAL/MATCH/lead/analytics/
workflows/secrets/чужих schedules изменений; liveTV+directANEX, SAMOpaused сохраняются.
Sourceclaim6097349819/publisher6097466847/auditclaim6097515037 terminal после audit exact-headCI/release/readback.


## SEARCH-QUALITY-1 — contact46 / Site127, 2026-10-10

**Работающий эффект.** На мобильном экране заявки после смены пары рейсов
первое поле телефона теперь полностью видно без прокрутки. Свёрнутая сводка
сохраняет название, даты/ночи, группу и точные возраста детей; полные номер,
питание, оператор, рейсы и багаж раскрываются в прежнем review. Итог за всех
и действие остаются на экране. Исправлен конкретный family-contact дефект,
существующие application/contact/style owners; money/identity/eligibility/
draft/consent/transport owners не менялись. Новой формы/renderer/helper/override слоя нет.

**Exact artifact.** Site127/package46/v169, pushed source
`59ab358019454c953e7726991f7356cf6f607da3`;
native version
`appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_a6fbb069497c81918b86f1c8c64ee83b`;
deployment `appgdep_6aca399b449c8191a4dac8c9f0240464` SUCCEEDED
2026-10-10T13:12:11.639501Z. Stock local gzip SHA256
`6abb9dece0aa3dcc75f36f3f0eab9edae01d8d8a4020dff587ea0209388c6564`;
native normalized tar SHA256
`6513c82913c8ad0fd335211e870db2a14d8882a9687bd89a3914418f02c53261`.
Exact native source/version/deployment/archive readback совпал;
actual footer46/v169. Acceptance-only source `1354712a8f299750415c227bcc50bdec48d9a173`
retains current images, receipt and independent review; changed paths only
qa/contact46/**, dist/hosting byte-identical installed59ab358. Второго save/deploy нет.
Public URL verified by actual Chrome:
https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=360

**Оценка, сопоставимость и новый scope.** Та же SEARCH-QUALITY-1:
12 исторических строк/11 обязательных, Compare deferred, те же пять
критериев0/1/1.5/2. N=not_measured, с N сумма не вычисляется. Previous exact
Site126/source982ff812/acceptance2f4ed600, formal report
[#1646/6097561905](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6097561905).
Прежние measured criteria переносятся с указанным exact evidence.
Прежний MobileC5=2 относится к profile corpus и не объявлен падением.
Новый short-family-contact corpus имеет первый formal baseline на retained
before126; он не заменяет прежний scope и не является новой рубрикой/tracker.
Экспертная оценка не измеряет конверсию.

| Параметр | Previous126: пять баллов; сумма | Current127: пять баллов; сумма | Delta; доказательство или недостающий критерий |
| --- | --- | --- | --- |
| Desktop visual | 2/2/2/2/N; N | 2/2/2/2/N; N | measured0; no full matched desktop gain. C5 intermediate/short form+rail+cards целиком отсутствует |
| Mobile visual — прежний profile scope | 2/2/2/N/2; N | 2/2/2/N/2; N | measured0; unchanged profile criteria carry. C4 full long room/meal/operator/baggage+family0–3/text200 не закрыт |
| Соответствие целевому макету | N/N/N/N/N; N | N/N/N/N/N; N | Нет полного exact approved-reference form/calendar/filters/card/mobile сравнения |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; owner byte-identical, previous exact actual evidence + current full five-width compiled suite |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; exact facets/chips/reset/same-offer/state carry, results suite PASS |
| Карточки и price/CTA | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; source owner byte-identical, prior125→126 density gain не повторён |
| Календарь | 2/N/2/2/2; N | 2/N/2/2/2; N | measured0; full sparse/full geometry отсутствует, owner byte-identical |
| Compare / shortlist — deferred | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Вне11 обязательных, не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0 в bounded prototype scope; actual same pair/party/total/Back + compiled independent offers |
| Lead-form handoff | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0 в bounded scope; actual validation/focus/review/consent/return и current compiled guards |
| Accessibility / state UX | 2/2/N/2/2; N | 2/2/N/2/2; N | measured0; labels/focus/state retained, полный readability/targets/text200 corpus отсутствует |
| Архитектурная чистота / стабильность | N/N/N/N/N; N | N/N/N/N/N; N | Нет полного comparable5-criteria baseline; focused invariants не превратились в quality sum |

**Наблюдаемый прирост в новом одинаковом scope существующего MobileC5.**

| Канонический критерий и scope | Before126 baseline | After127 | Delta | Основание |
| --- | --- | --- | --- | --- |
| MobileC5 — отсутствие перекрытий/лишней прокрутки, initial short-family-contact360/390/430×480 | 1.5/2 — первый baseline | 2/2 | +0.5 | Поле телефона из-под первого viewport перемещено полностью в видимую body; итог/action остаются на экране |

Это +0.5 конкретного критерия в одинаковом расширенном scope, **не +0.5
к придуманной полной Mobile сумме**, не повторная награда за предыдущий profile2
и не baseline recalibration. MobileC1composition2/C2readability2/C3actions2/C4N/
C5profile2 с новым accepted contact2; полная сумма остаётсяN.

**Matched before/after.** Тот же явно отмеченный demo AzureBayResort,
1→8Oct/7nights, 2adults/children0+8; Standard demo-room/all-inclusive/demo-operator.
Применена пара2: outbound06:05→10:40 SVO→AYT, inbound08:05→12:40 AYT→SVO,
airlineA/demo, baggage20kg/hand5kg. Whole-party187700 вместо186400;
demonstration_price, не live/final_price_verified и не supplier recalculation.

Actual360/390/430×480, review collapsed, initial scroll0:
phone top469.953125/bottom515.953125→top332.265625/bottom378.265625;
body bottom343.828125→380.71875; footerheight136.171875→99.28125;
CTA134–140×70.78125. Phone теперь целиком внутриbody, horizontaloverflow0.
Имя/даты/ночей/точные возраста0+8 видны, «Условия» однозначно раскрываются;
доступное summary сохраняет полное «Условия тура и перелёта».
Не уменьшаются genuine mobile photo1.8/открытая seven-price month/year rail/form/calendar.

Actual768/1280 iframe modal/phone/footer geometry без overflow;
before outer-wrapper wide frames частично обрезаны и не доказывают
полный desktop visual gain. Дополнительно actual full desktop1363×936,
application review open по существующему desktop default: две колонки,
контакты/phone/consent/CTA видны без overlap, footer/action48.39px.
Это supplemental after coverage, не замена matched1280 baseline.
Reopen application defaults collapsed наmobile как прежний renderer;
перенос open-state через этот переход не заявлен. Native viewport change
сохраняет текущий review state; отдельный compiled native review-state PASS.

**Действия и пять причин fully-covered затронутых строк.**

- Lead2/2/2/2/2=10 в bounded прототипном scope, delta0:
  C1 правильный same exact offer/дат/ночей/party0+8/187700, доступные room/meal/operator;
  C2 первое поле/action видимы и actual settled error показывает phonefocus;
  C3 empty-phone и consent errors видимы; pending/unknown/expired guards current suites;
  C4 valid test phone+checked no-error и actual reopen; compiled draft preservation и
  consent revocation для другого offer/другой применённой pair;
  C5 controlled Back сохраняет состав/условия/187700, compiled passive/cancel guards,
  zero real sending. Эти2 не являются real lead transport/physical acceptance.
- Selected2/2/2/2/2=10 в bounded scope, delta0:
  C1 offer/key/generation и distinct offers guards byte-identical+current tests;
  C2 Standard/all-inclusive/demo-operator/ages0+8 actual, families0–3 semantic tests;
  C3 full rendered demo-flight/baggage facts и existing honestunknown gates;
  C4 same whole-party187700/changed-price status без новых money/fuel formulas;
  C5 native Back/reopen и compiled cancel/other-offer/consent без подмены тура.
- Form2/2/2/2/2=10 carry:
  C1 city/destination и C2 dates/nights прежние exact43/45 actions;
  C3 children0–3/ages semantic current suite плюс previous43 actual0/8/17;
  C4 canonical IDs OR/cap20/21st rejection/query/Cancel/Apply previous45+current suite;
  C5 budget/primary-advanced previous exact evidence. Owner byte-identical, gain0.
- Filters2/2/2/2/2=10 carry:
  C1 facets; C2 chips/count; C3 reset/zero; C4 same-offer combinations;
  C5 no-supplier state предыдущий exact42/45 corpus и current results suite, gain0.
- Cards2/2/2/2/2=10 carry:
  C1 media/name/place; C2 nonduplicated facts; C3 representative offer;
  C4 minimum/concrete/saved distinction; C5 accessible CTA previous45
  actual1280/768/360/390/430 + current suite. Этот owner byte-identical, gain0.

**Проверки и независимый review.** Шесть final applicable compiled/jsdom suites
exit0/PASS: mobile-form, selection-return, decision-prototype, results-block,
search-editor, sites-interactions; syntax/diffPASS. Initial MODULE_NOT_FOUND
runs не дошли до assertions, retained dependency-missing logs не passing evidence.
Family-summary age assertion initially выявил потерю возрастов в collapsedsummary;
implementation исправлена до единственной публикации, assertion не ослаблен.
Final rerun selection-return/decision PASS. JSdom без layout engine; реальные
rectangles/screenshots отдельно actual Chrome.

Read-only reviewer /root/contact46_independent_qa лично осмотрел matched360/390/430,
settled empty-phone/consent/checked/Back/reopen, aftertablet/wide и full1363 desktop.
Существенного нового дефекта в scope не найдено; extended MobileC5 baseline1.5→2
поддержан. Полные Mobile/Desktop строки не закрыты, физический scope отсутствует.
Review не писал files/browser/issues и не публиковал. Обработка redacted field
values не обходилась: draft assertion отдельно compiled, actual settled image
показывает только заведомо учебный телефон0.

**Retained evidence.** `qa/contact46/before/contacts-{360,390,430}.jpg/json`;
wide before768/1280 markedpartial. `after/contacts-{360,390,430}.jpg/json`;
`after/after768-collapsed.jpg/json`, `after/after1280.jpg/json`,
`after/desktop-full-settled.jpg`+`desktop-full.json`,
`after/phone-error-settled.jpg`, `contact-checked-settled.jpg`,
`back-tour-settled.jpg`, `reopen-settled.jpg`;
`runtime-receipt.json`+`REVIEW.md`+`README.md` и focused receipts/logs.
Три preceding-paint frames в excluded/ не визуальные доказательства:
phone-error.jpg/contact-focus.jpg/desktop-full.jpg; early error-JSON scroll
не accepted screenshot geometry. Несинхронизированные missing files не coverage.
After alias duplicates — те же retainedbytes, не дополнительные проверки.

**Стадии и следующий крупный блок.** Макет: реальные desktop/mobile layout,
контактный MobileC5 gain+0.5, full visual rowsN. Кликабельный прототип:
exact Site127/source59ab358 установлен и relevant actual actions приняты в scope,
PROTOTYPE_PENDING_FULL_OWNER_ACCEPTANCE_BEFORE_NEXT.
NEXT остаётся отдельным published source73a8e38d2b179cc7334c547e093207ba052082d3,
terminal[#4217/6097387169](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-6097387169);
новый Site UX не переносился, его баллы NEXT не наследуются и повторный deploy
не запускался. Физические iPhone/Safari/OSkeyboard/safearea/text-only200%,
real delivery и user pilotUNVERIFIED.

Следующий связный разрешённый этап: long hotel/room/meal/operator/baggage и
семьи0–3 form→hotel→exacttour→contacts→Back на360/390/430 с supported увеличенным
текстом; добирать отсутствующий corpus и устранять наблюдаемые препятствия
в существующих owners. Full DesktopC5 короткая/промежуточная форма+rail+card
остаётся отдельным точным пробелом. Не повторять byte-identical блоки ради баллов,
не ждать all55 до макета, не создавать новый tracker/дизайн или косметическую очередь.
Full concrete prototype approval beforeNEXT — отдельный гейт. Нет MAIN/production/
supplier/real lead/provider/money/DB/LOCAL/MATCH/analytics/workflow/чужих schedules
изменений; свежие liveTV+directANEX/SAMOpaused сохранены.
Source6097770426/publisher6097853495/audit6097913058 RELEASE после
exact audit sourceCI/release/readback.


## SEARCH-QUALITY-1 — quality47 / Site128, 2026-10-10

**Работающий эффект.** Исправлены подтверждённые маленькие области нажатия в связанном
пути форма→локальный поиск/фильтры→даты/ночи→возврат к туру: строки питания44px,
очистка/сброс44px, планшетные Close/месяц44×44, семь кнопок ночей на360px теперь
не уже44px. Изменены только существующие CSS owners и package/cache labels.
Нового renderer/helper/style owner/override слоя нет. Calendar trigger/result reset/
facet expansion уже44+ и не менялись; скрытый saved-tour shortcut не менялся/не оценивался.
Семантика query/ID/OR/draft/Apply/Cancel/price/lead не переписана.

**Exact artifact и стадия.** Site128/package47/v170, опубликованный pushed source
`b5956a6a40845342e3ea59e4d79555139f9958be`, version
`appgprj_6abcd9299ef88191bbce1fc1bf8c767a~appgver_1c078dcf506481918e5465d88eb44445`;
deployment `appgdep_6aca47ebcf64819182a197f328a94d2f` SUCCEEDED
2026-10-10T14:13:15.238711Z. Stock gzipSHA256
`b1022be89ad48af2708cdf2d73aeceb57cf117ff0b97160adb846b6ae66e178b`;
native normalized tarSHA256
`062aae05301f8d53aad561b0c44079644a743fe2d6e59dee66429cc35c0513ae`.
Native exact source/version/deployment/archive readback и actual footer47/v170 совпали.
QA-only pushed source `fc72fb8d315204e9443cc4d09f0f2bda48f06596` меняет
только qa/quality47/**; dist/hosting byte-identical опубликованномуb5956a6.
Save/deploy выполнены один раз. Public/history/project/audience сохранены:
https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/mobile-preview?width=390
PROTOTYPE_PENDING_FULL_OWNER_ACCEPTANCE_BEFORE_NEXT.

**Фиксированная оценка.** SEARCH-QUALITY-1 не меняется:12historical/11required,
Compare deferred, пять0/1/1.5/2. N=not_measured, сумма строки сN не вычисляется.
Previous exact Site127/source59ab358/acceptance1354712, formal
[#1646/6097948283](https://github.com/pyatkoff/poisk-turov-test/issues/1646#issuecomment-6097948283)
и audit contact46 выше. Прежние exact measured criteria carry, отсутствие нового
coverage не0/регрессия. Новый normal-scale touch subset имеет первый comparable
baseline существующих критериев, не новую методику/tracker и не перекалибровку
старого profile2. Expert scores не являются измерением конверсии.

| Параметр | Previous127: C1/C2/C3/C4/C5; сумма | Current128: C1/C2/C3/C4/C5; сумма | Delta; evidence или точный пробел |
| --- | --- | --- | --- |
| Desktop visual | 2/2/2/2/N; N | 2/2/2/2/N; N | measured0; C5 full matched intermediate/short form+rail+cards corpus ещё не закрыт |
| Mobile visual — прежний profile scope | 2/2/2/N/2; N | 2/2/2/N/2; N | measured0; C4 full long room/meal/operator/baggage+family0–3/text200 не закрыт |
| Соответствие целевому макету | N/N/N/N/N; N | N/N/N/N/N; N | Нет полного exact approved-reference пяти-сценарного сравнения |
| Полнота основной формы | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; same owners/state carry плюс actual family3/Cancel/recovery и current compiled |
| Filters / decision UX | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; current actual check/reset/Apply, unchanged same-offer contract |
| Карточки и price/CTA | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0; existing card owner bytes unchanged, exact45/46 evidence carry |
| Календарь | 2/N/2/2/2; N | 2/N/2/2/2; N | measured0; sparse/full geometry whole corpus C2 остаётсяN; current Next/Cancel не закрывает его |
| Compare / shortlist — deferred | N/N/N/N/N; deferred | N/N/N/N/N; deferred | Вне11required, не возобновлён |
| Selected tour | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0 bounded prototype; family0/8/17, demo pair2/187700/Back actual и current guards |
| Lead-form handoff | 2/2/2/2/2;10 | 2/2/2/2/2;10 | 0 bounded prototype; exact contact46 validation/draft/consent carry, current family3 return |
| Accessibility / state UX | 2/2/N/2/2; N | 2/2/N/2/2; N | measured0; full C3 readability/target corpus/text200 отсутствует; city Clear focus gap остаётся |
| Архитектурная чистота / стабильность | N/N/N/N/N; N | N/N/N/N/N; N | Нет полного comparable пяти-критериального baseline |

**Новый сопоставимый subset, без выдуманной общей суммы.**

| Канонический критерий и scope | Before127 — первый baseline | After128 | Delta | Подтверждённое устранение неудобства |
| --- | --- | --- | --- | --- |
| MobileC3 — размер известных undersized form/filter/night/recovery controls, normal scale | 1.5/2 | 2/2 | +0.5 | meal39040→44; clear/reset39029.59375→44; night36043.421875→44.28125 приheight54 |
| DesktopC4 — tablet768 picker Close и month navigation, normal scale | 1.5/2 | 2/2 | +0.5 | Close/Next36×36→44×44, те же snapshot2/1–7Oct/calendar facts |
| A11yC3 — тот же target-size subset | 1.5/2 | 2/2 partial | same evidence, не отдельный gain | full criterion остаётсяN; readability/text200 не принят |

Эти delta относятся только к размерам конкретных ранее неудобных controls,
не к полному Mobile/Desktop/A11y и не отдельный балл за каждую кнопку.
MobileC3/profile2 и DesktopC4/profile2 сохраняются в исходном scope.
Contact46MobileC5+0.5 и decision45DesktopC2+0.5 повторно не начисляются.
Summary390 geometry358×32→358×44 подтверждена, но ей не добавляется ещё score.

**Actual browser evidence/actions.** before/after CSS-pixel JSON:
meal-row390350×40→350×44; form-filter reset133.390625×29.59375→133.390625×44;
meal-query reset113.171875×29.59375→113.171875×44;
departure/destination empty reset119.21875×29.59375→119.21875×44.
Nights360 seven columns, body/scrollWidth360;430 body/scrollWidth430,
first54.28125×54, sticky confirmation доступно на480height.
Calendar768 snapshot2/1–7Oct/7nights same retained23.09 prices, unknownNovember honest;
Next показываетNovember/December, pending Apply всё ещё1–7Oct, Cancel сохраняетдаты.
Form1280×720 body/scroll1265/1265, all fields/search/Cancel visible.
Supplemental card768 shows full FOUR-G card; first-card rect top-281.625 is not
a full first-card visual acceptance.

After mainframes form/results/selected/contact390 и1280×720 лично осмотрены,
plus affected controls360/390/430/768. Explicit family demo2adults/ages0/8/17:
local empty-query clearing не меняет city/country; night1 draft cancelled→7;
result meal checked/applied, form filter reset cancelled→mealAllInclusive retained.
Search→tour→pair2→contacts→controlled Back сохраняет 06:05→10:40SVO→AYT,
08:05→12:40AYT→SVO, baggage20kg/hand5kg и187700 whole-party demo total.
186400→187700 is demonstration_price, не live/final_price_verified/repeated supplier pricing.
Current actual snapshot filter AllInclusive count291→reset1047 separately recorded;
all operations snapshot/demo only, supplier0/reallead0.

**Остающееся наблюдаемое неудобство.** Departure empty-query «Очистить запрос»
очищаетvalue, но input.focused=false: для продолжения ввода нужен tap.
Beforefocus отсутствует, поэтому это не доказанный regression и не negative delta.
CSS не выдаётся за улучшение keyboard/focus. Следующий связный mobile long-content/
recovery package должен исправить existing clear owner после fresh claim.

**Пять причин полностью covered строк; все пять2, gain0.**
- Form: C1city/destination visible; C2date/night Cancel сохраняет1–7Oct/7;
  C3exact children0–3 иage guards current suite + actual0/8/17; C4canonicalID OR/cap20/
  query/Cancel/Apply previous43/45 exact evidence/current suite, owner unchanged;
  C5budget/primary-advanced carry + current cancelled reset preservesmeal.
- Filters: C1facets actual; C2check/count; C3reset/zero-state previous42/45+current;
  C4same-offer combination current results suite; C5local no-provider state/action gates.
- Cards: C1media/name/place; C2nonduplicate facts; C3representative offer; C4minimum/
  concrete/saved distinction; C5accessible CTA exact45/46 frames+current results suite,
  owner byte-identical, current supplemental768 не новое улучшение.
- Selected: C1identity/generation/distinct offers; C2exactroom/meal/operator/ages;
  C3rendered pair/baggage and honestunknown; C4whole-party total/current changed-price
  guards withoutmoney/fuel formula changes; C5Back/reopen/cancel/independentoffer carry
  + actual family3/187700 return.
- Lead: C1sameoffer/party/total; C2phone/action availability previous46/current390/1280;
  C3actual previous46 phone/consent errors + current late/duplicate/expiry/unknown guards;
  C4draft/consent reset previousexact46/current suites; C5controlled Back/no real sending.
  Это bounded prototype scores, не live booking/lead delivery/physical acceptance.

**Checks/review/limitations.** Six applicable compiled/jsdom suites exit0:
mobile-form/search-editor/results-block/decision-prototype/selection-return/sites-interactions;
syntax/diffPASS. Exact artifacts/logs retained; jsdom has no layout engine.
Independent read-only /root/quality47_prior_evidence personally viewed16 actual frames;
no new visual blocker in shown product, target-size subset supported; state/data/viewport
differences explicitly excluded from whole composition/route gain. It changed no files,
browser, claims/comments and published nothing. Full physicaliPhone/Safari/OSkeyboard/
safearea/text-only200% remainsUNVERIFIED. Regular/short emulation is not physicalkeyboard.

Retained qa/quality47/{before,after,logs}/**, before/after-measurements.json,
README/REVIEW/runtime-receipt. afterselected390 shows186400 beforepair;
selected1280/contactafter shows187700 afterpair2, not one unchanged state.
beforeform1280 is2adults/Любое; afterfamily3/AllInclusive, no matchedcompositiongain.
beforemeal390 nestedBack/footer and afterdirectmeal differ; only same-control size
supports gain. excludedcropped contacts1280-short/formfilters390 not acceptance.
after/formfilters390-full snapshot2 supplemental, not matchedfamily3 composition.
No repeat save/deploy for QA-only source.

**Стадии/следующий блок.** Макет: current actual layout и bounded target-size gain;
full visual rowsN. Clickable: installedSite128 relevant controls/path accepted in scope,
pending fullowner approval beforeNEXT. Installed NEXT remains separate
source73a8e38d2b179cc7334c547e093207ba052082d3/terminal6097387169; thisUX not transferred,
delta0 and no scoreinheritance. New goal9.5–10 has not been confirmed.
Next permitted block: whole mobile longhotel/room/meal/operator/baggage+families0–3
form→hotel→tour→contacts→Back with supported increasedtext and recoveryfocus;
separately finish missing short/intermediate desktop form/rail/cards. Fix observed
defects in existing owners, do not restartall55/roadmap/newtracker/newdesign.
No MAIN/production/supplier/real lead/provider/money/DB/LOCAL/MATCH/analytics/workflow/
secret/other schedule action. Fresh liveTV+directANEX/SAMOpaused preserved.
Source6098308051/publisher6098371799/audit6098501460 RELEASE after exact audit CI/merge/readback.

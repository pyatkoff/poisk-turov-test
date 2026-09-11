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

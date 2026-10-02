# Search3 NEXT — завершение текущего refactor pass

2026-10-02 UTC. **Три этапа завершены. Один накопленный NEXT опубликован и принят в проверенном объёме.** Очередь текущего прохода закрыта; дополнительные мелкие оптимизации её не продлевают.

## 1. Измеренный остаток закрыт

В `mergeSearchResults()` операторский набор и meal-фасеты теперь собираются одним обходом итоговых нормализованных предложений. На 1000 предложений чтения элементов этого inventory уменьшаются **2000 → 1000**, промежуточный `flatMap` на 1000 элементов устранён. Клонирование входных предложений остаётся отдельной необходимой работой: показатель относится именно к двум прежним итоговым обходам.

Сохранены порядок операторов, замена повторных hotel ID, placeholders избранного/сравнения, meal-нормализация и конфликтующие meal ID. Reference- и mutation-проверки покрывают preview/live, sparse/inherited массивы и меняющие исходные данные getters. Этот результат — сокращение повторной работы, без заявления об ускорении страницы.

Изменены только `v2/visual-search/app.js`, профильный results-rendering тест и два digest app.js в migration manifest. [PR #4338](https://github.com/pyatkoff/poisk-turov-test/pull/4338) проверен и слит в release.

## 2. Итоговая сверка крупных скриптов

База сравнения — ранее опубликованный DESIGN source `30b003c0972f76cabdc8cbaf194684cf97eeddf5`; результат — `1525640b410ab602960f9e6cf2e427b8ecc5293e`. Оба графа собраны одинаковым compiler Terser 5.51.2, Node 24.19.0; gzip — сумма отдельных assets, уровень 9. Served здесь означает размер скомпилированных файлов артефакта, а не измеренные сетевые байты.

### Суммарные размеры

Все значения в байтах. Complete включает начальный граф и два cold-модуля.

| Граф JS | Исходники: было → стало | Served: было → стало | Gzip served: было → стало | Δ served / gzip |
|---|---:|---:|---:|---:|
| LIVE initial | 577773 → 580847 | 438324 → 439841 | 133388 → 133901 | +1517 / +513 |
| Cold offer-list + hotel-details | 33154 → 33425 | 27174 → 27197 | 9761 → 9837 | +23 / +76 |
| LIVE complete | 610927 → 614272 | 465498 → 467038 | 143149 → 143738 | +1540 / +589 |
| Offline initial | 373407 → 376481 | 293835 → 295352 | 91726 → 92239 | +1517 / +513 |
| Offline complete | 406561 → 409906 | 321009 → 322549 | 101487 → 102076 | +1540 / +589 |

CSS не менялся: `styles.css` 244221 B + `mobile-controls-v1.css` 2691 B = **246912 B**. Начальный LIVE JS+CSS: **685236 → 686753 B**; полный JS+CSS: **712410 → 713950 B**. HTML, fonts, images, fixture JSON и сторонние ресурсы в эти суммы не входят.

Накопленный выпуск увеличивает полный JS на **1540 B served / 589 B gzip**. Последняя merge-правка отдельно: readable app **+26 B**, source gzip **+14 B**, compiled served **−15 B**, compiled gzip **−2 B**. Ни весь проход, ни последняя правка не выдаются за доказанное ускорение браузера или уменьшение накопленного веса.

### Конечный список крупных JS (исходник ≥10 KB)

Served/gzip — итоговый compiled файл. Полный машинный inventory приложен в соседнем JSON.

| Скрипт относительно v2/ | Source | Served | Gzip served |
|---|---:|---:|---:|
| `visual-search/app.js` | 302809 | 237854 | 69500 |
| `prototype-search/data.js` | 126244 | 87876 | 24372 |
| `tour-controller-v4.js` | 33956 | 26479 | 8872 |
| `visual-search/offer-list-v1.js` | 20652 | 16301 | 5733 |
| `visual-search/flight-picker-v18.js` | 17957 | 13448 | 4866 |
| `search3-canonical-profiles-v1.js` | 16810 | 11466 | 4038 |
| `lead-form-guard-v1.js` | 16809 | 15365 | 4483 |
| `search3-local-db-provider-v1.js` | 15777 | 11258 | 4185 |
| `prototype-search/lead.js` | 15395 | 12735 | 4014 |
| `visual-search/fixture-data.js` | 14808 | 11806 | 4932 |
| `visual-search/local-db-parser.js` | 13497 | 9862 | 3775 |
| `visual-search/hotel-details-v1.js` | 12773 | 10896 | 4104 |
| `visual-search/recorded-data.js` | 11702 | 10280 | 4223 |
| `prototype-search/search-lifecycle-v1.js` | 10384 | 6293 | 2222 |

`app.js` остаётся владельцем 299 function declarations, `data.js` — 107. Вместе они занимают **325730 B, 74.06%** начального LIVE JS. Крупные части app: `renderDestination` 8368 B и `handleSearchParameterAction` 7887 B; data: `expandAnexGroup` 4476 B, `verifyAnexPackage` 4239 B, `verifyAndromeda` 4216 B. Это структурный остаток, а не повод открывать очередной пакет единичных вызовов.

### Браузерные измерения на одинаковом сценарии

Baseline compiled Chromium run [36931907793](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36931907793) и итоговый [36982752961](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36982752961) использовали одинаковые fictional fixtures и harness. На 390/768/1280 px interaction receipts совпали; JSON-измерения карточки на 360/390/430 px и hotel scroll на 390/768/1280 px совпали полностью.

Например, на 390 px карточка имеет ширину 358 и высоту 628.28125 px, стрелки галереи 44×44 px, CTA 157.078125×48 px; цена не переносится. При расширении hotel content внутренняя позиция scroll 769 px сохраняется. Визуально проверены mobile results и desktop application из итогового compiled артефакта: пересечений и обрезанных элементов не обнаружено.

Дополнительно один и тот же hosted offline snapshot измерен до и после активации, viewport 1363×936:

| Метрика | До | После |
|---|---:|---:|
| Отели / предложения | 1047 / 1453 | 1047 / 1453 |
| Отрисованные карточки | 24 | 24 |
| Даты календаря | 7 | 7 |
| Checkbox строки фильтров | 97 | 97 |
| Горизонтальный overflow | false | false |
| Первая карточка: ширина × высота | 1024 × 341.1875 | 1024 × 341.1875 |

Порядок, цены и размеры первых трёх карточек тоже совпали. App cache key изменился с `730b21cf8040` на опубликованный `c07fb409d3f6`.

**Page latency / p50 / p95 не измерены.** Текущий harness даёт поведение и геометрию; счётчики операций и DOM не подменяют временной benchmark.

### Конечный backlog после завершённого прохода

| Пункт | Подтверждённая структурная проблема | Условие отдельной будущей работы |
|---|---|---|
| F1 | Большой app.js совмещает форму, results/facets, календарь, selected tour, gallery/history | Одна осмысленная граница ответственности с явным состоянием/API и acceptance возврата/recovery. Перенос ради размера файла не является результатом. |
| F2 | Shared data.js совмещает provider windows/continuations, нормализацию и package/quote verification | Ограниченный handoff INT → SEARCH с владельцем каждого контракта. Direct ANEX/auth/URL/payload/price/fuel сохраняются. |
| F3 | Начальный LIVE граф остаётся eager для selected-tour/lead/provider частей; cold split ограничен двумя presentation modules | Сначала одинаковые bootstrap/request и first/cold/warm timing измерения; затем одна доказанная lazy-граница с ordering/retry/recovery acceptance. |

F1–F3 отложены и **не входят в очередь этого прохода**. Малозначительные идеи по единичным вызовам относятся к следующему backlog. Новых микро-пакетов, изменений data.js или снятия provider guards здесь нет.

## 3. Один накопленный NEXT и приёмка

Источник `1525640b410ab602960f9e6cf2e427b8ecc5293e`, source tree `04fefe66c0cc5a9be754152e3d600d3c49af3ccf`, release `4a88cdd02dd609ecf1eaf35cae2241c807d0a706`.

Точные CI gates SUCCESS: Security [36982752970](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36982752970), lead contracts [36982753066](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36982753066), compiled build [36982753004](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36982753004), focused + full compiled browser [36982752961](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36982752961). Build artifact **11215797301** переиспользован; повторной сборки для публикации не было.

Единственная команда #4217 comment **5948130210** → publisher [36983822609](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36983822609), attempt **1**, SUCCESS. Terminal receipt [5948222518](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-5948222518); зеркало #1646 comment **5948222721**. Deployment artifact **11216885537** скачан, SHA-256 проверен: `a3c8b2816e3a0de068e2525f9e8738ccc872cd19b2fdde15419b3a13c2b6c35d`. Все source/build/browser/deployment digests приведены в JSON.

Guarded activation: **836 files**, **9 routes HTTP 200**, **39 visual assets verified**; live-connected visual route, noindex, lead HTTP **403**, LOCAL-only data HTTP **403**, counter **0**, supplier searches **0**, real leads **0**. Production и соседние preview fingerprints не изменились; predecessor/rollback сохранены. Команда и publisher terminal/no-replay, claim RELEASED.

| Приёмка | Доказанный результат |
|---|---|
| Поиск | Полный compiled fixture сценарий с тремя источниками, progressive merge и scope/recovery; опубликованный snapshot даёт 1047/1453; pristine live bootstrap не запускает поиск до submit. |
| Фильтры | Hosted 4★: 395/522, URL stars=4; reset возвращает 1047/1453. Fixture facet focus, сортировка и retained choices прошли. |
| Календарь | Hosted 5 Oct: 974/1122, URL date=2026-10-05; fixture observed/calendar/budget/recovery и общая геометрия совпали. |
| Карточка / hotel | Photos/about/rooms загрузились, два snapshot offers; compiled gallery, cold failure/retry, inner scroll и history возврат прошли. |
| Выбранный тур | Hosted сохраняет 5–12 Oct, Standard, завтраки, Интурист, 96953 RUB, fuel «уточняется» и блокирует отправку на стенде. Fixture flight/meal choices, price/fuel, draft и возврат прошли; verified TV total 133500.5 RUB, SAMO 125500 RUB, unknown/zero fuel и retained ANEX estimate проверены. |

Hosted приёмка не выявила page-origin console errors. Supplier/lead HTTP из реальных бизнес-сценариев не вызывались.

**Отдельно не проверены:** физический Safari/iPhone; реальные supplier search/flight/quote сценарии; доставка реальной заявки; browser latency benchmark. Они не смешиваются со статусом завершённого рефакторинга. Этот отчёт не даёт production-допуск.

Подробные числа и provenance: [search3-refactor-final-20261002.json](search3-refactor-final-20261002.json). Текущий проход завершён; конечный backlog не возобновляет его автоматически.

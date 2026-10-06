# Search3 NEXT — завершённый структурный проход, 02.10.2026

Весь согласованный проход **завершён**: компонент фильтров реализован, итоговый анализ общего data.js записан, начальный граф измерен и UI выбора рейсов переведён на загрузку по требованию. Один накопленный source PR **#4340** прошёл обязательную CI, merged в release и опубликован одним NEXT-выпуском. Приёмка опубликованного поиска, фильтров, календаря, карточки, выбранного тура и рейсов завершена.

Предыдущий проход с mergeSearchResults 2000→1000 чтений уже закрыт в [предыдущем отчёте](search3-refactor-final-20261002.md). Он не переоткрывался. Этот новый проход начат по последующему поручению владельца «давай делать», в пределах F1/F2/F3. Анализ F2 и будущая реализация data.js различаются явно.

## Три конечных результата

| Пункт | Что завершено | Граница ответственности |
|---|---|---|
| F1 | Вынесены 22 функции представления фильтров в `AnyTourFilterPanelV1.create`. Размер app.js 302809→283143 B, декларации функций 299→279. Новый компонент 23308 B / 23 функции. | Компонент владеет markup/query/expanded sections/focus/caret/section navigation; host сохраняет matching, draft apply, поиск, history и provider-состояние. Свежие getters сохраняют заменяемые hotels/draft. Общий compareMealLabels сохранён для search picker. |
| F2 | [Полный анализ data.js и конечный INT→SEARCH контракт](search3-data-structure-20261002.md): все 126244 B / 107 функций распределены по восьми зонам, описаны transport/state/quote границы. | Runtime data.js **не изменён**: blob `b3d5ae68f2246e3f1f6e22a216970ab628b4942e`, compiled 87876 B / gzip 24372 B. Чистая meal projection — будущий single-executor handoff, без заявленной реализации. Свежие INT планы прочитаны на `24699a22b7d4123b4e6ec387d7193c0c360e2d03`. |
| F3 | Eager summaries и public API сохранены; `flight-picker-ui-v1.js` загружается при открытии picker: bootstrap 0 / cold 1 / warm additional 0 запросов в compiled browser. | Shared promise, 15 s timeout/retry, guards token/draft/selected offer/modal/open. Поздний результат закрытого/заменённого окна не рисует UI; до готовности отсутствует apply footer. HTML/PHP/compiler/cache связывают exact compiled файл. |

Provider transports/auth/endpoints, request generations/receipts, matching, цены, fuel и lead contracts сохранены. Третий cold owner обслуживается в JSDOM harness по точному локальному пути; runtime не предзагружается ради теста. Все прежние assertions и supplier/lead=0 сохранены.

## Размеры: полный граф и начальная загрузка

До — опубликованный source `1525640b410ab602960f9e6cf2e427b8ecc5293e`; после — `59a216330fbd6bae28340cbb0c856a35f54b7d03`. Terser policy прежняя; gzip — сумма независимых gzip файлов из exact asset manifests, не фактически измеренный серверный wire transfer.

| Граф | Source raw B, до→после | Compiled B, до→после | Compiled gzip B, до→после |
|---|---:|---:|---:|
| Начальный LIVE | 580847→572379 | 439841→434984 | 133901→133360 |
| Начальный offline | 376481→368013 | 295352→290495 | 92239→91698 |
| Cold UI | 33425→46192 | 27197→37044 | 9837→13574 |
| Полный LIVE | 614272→618571 | 467038→472028 | 143738→146934 |
| Полный offline | 409906→414205 | 322549→327539 | 102076→105272 |

Начальный граф меньше на **4857 compiled B / 541 gzip B**. Полный граф больше на **4990 compiled B / 3196 gzip B** из-за компонентных границ. Это структурное изменение с меньшей начальной загрузкой, а не сокращение всего набора JS. Новый cold flight UI: **12767 raw / 9847 compiled / 3737 gzip B**. CSS не менялся.

## Браузерные измерения

Контролируемое сравнение **одного и того же compiled candidate**: flight UI eager против deferred. Fictional catalogue, Chromium, пять пар новых contexts на каждой ширине, чередование порядка; всего 20 наблюдений. Test-only eager injection перед app не является product feature. Это не latency-сравнение старого опубликованного head с новым и не production p50/p95.

| Ширина | Eager initial JS B | Deferred initial JS B | Медиана ready eager→deferred, ms |
|---|---:|---:|---:|
| 390 | 444831 | 434984 | 175.8→179.1 |
| 1280 | 444831 | 434984 | 180.6→180.2 |

Из начальной загрузки контролируемо убраны 9847 B picker UI. **Ускорение готовности формы не подтвердилось**; latency improvement не заявляется. Все двадцать observations, resource sizes/durations и DOMContentLoaded приведены в JSON.

Одиннадцать общих JSON receipts/geometry совпали с предыдущим exact browser artifact: основной journey, hotel inner scroll 390/768/1280, карточки 360/390/430, 24 flight pairs, offer-list/hotel-details lazy failure/retry, terminal offers. Full compiled journey сохраняет progressive facets/focus/history, TV total 133500.5 RUB и fuel 20686, unknown/explicit zero, SAMO 125500 RUB/terminal recovery/no replay, ANEX retained estimate, draft/recovery. `false` в receipt для неприменимого viewport-сценария не заменяется на фиктивный success; весь обязательный job завершился SUCCESS.

## Точные исходники, CI и единственный NEXT

- Source [#4340](https://github.com/pyatkoff/poisk-turov-test/pull/4340): `59a216330fbd6bae28340cbb0c856a35f54b7d03`, tree `774f54fa0f608d354ccda0bad3877542a00ee37f`; 20 claimed paths. Merged release `8f54d33c6279feba85b23f8b963d4aacf292a2a7`, tree совпадает с проверенным source.
- Exact-head Security [36992108707](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36992108707), lead [36992108690](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36992108690), build [36992108697](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36992108697), focused + full browser [36992108677](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36992108677) — **SUCCESS**. Reviews/threads отсутствуют. Build artifact **11219234614**: SHA256 `2c2c12c8b6f6689c370e25139fbfe83542fabf7810f8e7cb07b699ee240f7349`; все **838 payload hashes/sizes** проверены. Browser artifact **11219868889**: SHA256 `5349f94eeba07a1c67bc4a4176f3d3f60f5b087f00c155468208f50126aab033`.
- Единственная команда **5949786598**, publisher [36992900244](https://github.com/pyatkoff/poisk-turov-test/actions/runs/36992900244), attempt **1**, **SUCCESS**. Deployment **11220331373**, SHA256 `1b2a71ea531102534b60acfba7a5775258cf3df0c083d71d15fcf7f84199dfbc`, скачан и проверен. Predecessor `1525640b410ab602960f9e6cf2e427b8ecc5293e`. [Hosted terminal 5950090374](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-5950090374), зеркало #1646 **5950090783**. Команда не повторялась.

Guarded activation: **838 files / 41 visual assets / 9 routes 200**; visual live-connected, noindex, lead HTTP **403**, LOCAL-only HTTP **403**, counter **0**, supplier searches **0**, real leads **0**. Production/sibling preview unchanged; predecessor/rollback retained. Source/integration/publisher claims RELEASED; единственный final audit/current_task claim заканчивается docs-only checked merge. Docs checkpoint не запускает ещё одну публикацию.

## Приёмка опубликованной версии

Hosted cache readback: app `2e32a351c457`, filter `12e126b4ccfc`, flight core `cc81de4192e7`, cold flight UI `d188d4cbec05`, data.js `c56324e713a0` unchanged. Cloud Chromium viewport 1363×936.

| Приёмка | Результат |
|---|---|
| Pristine LIVE | 17 eager scripts, после bootstrap форма enabled, cards=0, пустой search context; поиск поставщикам не отправлялся. Cold UI указан в head и отсутствует среди eager scripts. |
| Snapshot | 1047 отелей / 1453 тура; 24 cards / 7 calendar days / 97 checkboxes; overflow=false. Первые три cards width1024, heights341.1875/298.4375/308.4375, цены96953/133353/136582 RUB — exact parity с predecessor. |
| Фильтры | 4★ даёт395/522; reset возвращает1047/1453. |
| Календарь | 5 октября даёт974/1122. |
| Hotel | Фото4, about/service highlights, один Standard room / два snapshot offers; section navigation работает. |
| Snapshot tour | 5–12 Oct, Standard, завтраки, Интурист96953 RUB; fuel неизвестен, заявка на стенде заблокирована. |
| Fictional flight UI | На первом открытии появились24 пары,6 видимых. Variant2: 186400→187700 RUB/+1300, SVO06:05/08:05, baggage20kg/carryon5kg, explicit zero fuel. Apply, warm reopen, back сохраняют выбор и условия. Числа сетевых запросов0/1/0 подтверждены CI instrumentation; DOM script удаляется loader после загрузки и не служит счётчиком запросов. |

Ошибки page-origin console: **0**; extension metadata messages отдельно от ошибок сайта. Снимок выбранного демонстрационного тура сохранён. Реальные supplier/lead HTTP бизнес-сценарии не выполнялись.

**Отдельно не проверены:** физический Safari/iPhone; реальные supplier search/flight/quote; доставка реальной заявки; production-scale latency. Они не препятствуют закрытию принятого структурного прохода и не превращаются в утверждение production-готовности.

## Конечный backlog и остановка

1. Pure meal projection data.js — только после single-executor INT→SEARCH handoff из отдельного уже подготовленного контракта. Анализ завершён; runtime-реализация не входит в этот выпуск.
2. Физические устройства и реальные supplier/lead-сценарии — отдельная приёмка с собственными условиями и бюджетом.
3. Другие крупные app boundaries/production profiling — возможный будущий ограниченный scope владельца. Одновызовные оптимизации и новая O-очередь автоматически не запускаются.

Этот проход закрыт. Подробные значения и provenance: [search3-structural-final-20261002.json](search3-structural-final-20261002.json). Предыдущие отчёты и историческое состояние сохранены; меняется только верхний `AUTOPILOT_STATE.json.current_task`.

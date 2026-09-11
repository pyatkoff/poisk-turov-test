# AnyTour INT — матрица фильтров и свойств трёх источников

Дата: 2026-09-11. Источники: Tourvisor, прямой ANEX и Andromeda. Source-аудит ниже выполнен на INT `6b3665195456ce0581498588476a7bb4b9a6a719` после #2068. `reports/three-provider-filter-matrix-20260911.json` — сохранённый исторический snapshot, а не автоматически обновляемое состояние текущих контрактов.

Главная исполняемая очередь — `docs/integrations/three-provider-adaptation-autopilot.md` и свежие claims в #996. Эта матрица описывает семантику; наличие старого пункта или snapshot не разрешает повторять completed/reserved/unknown операции.

## Как читать статус

- `verified` — текущий adapter/contract умеет поле с подтверждённой семантикой в указанной границе.
- `local_only` — поле применяется после ответа поставщика по локальному каталогу; это не upstream-фильтр.
- `unsupported` — текущий adapter сознательно отклоняет непустой фильтр/свойство.
- `unknown` — данные могут встречаться, но общей доказанной семантики/проекции ещё нет.

Raw numeric ID одного поставщика никогда не считается ID другого. Реализованный source-only DTO или проверенная классификация label не доказывают supplier-filter parity, одинаковый package или выкладку в preview/production. Для этого нужны отдельные current evidence и разрешённый handoff.

## Владение

Эта очередь выполняет P0 цена/топливо, P1 поисковые observations, P2 эту матрицу, P4 offer contract и подготовку P5. **P3 hotel matching/identity выполняется отдельным диалогом в #1759.** Здесь новые `external hotel id` только сохраняются/передаются как evidence; mapping writer и acceptance rules не меняются.

## Что уже одинаково достаточно для общего поиска

| Поле | Tourvisor | ANEX | Andromeda | Общий статус |
| --- | --- | --- | --- | --- |
| Город вылета | verified | verified dictionary mapping | verified saved dictionary | verified |
| Страна | verified | verified dictionary mapping | verified loaded catalog | verified |
| Даты | verified, <=21 дней gateway | verified, Search3 ANEX берет первые 7 дат | verified | verified |
| Ночи | verified | verified | verified | verified |
| Взрослые / дети / возраст | verified | verified | verified | verified |
| Отель | local TV id | accepted mapping / observation | accepted mapping / observation | verified identity boundary |
| Валюта RUB | verified | verified supplier currency/converted RUB | verified, RUB only | verified |

## Где сейчас есть честный local-only fallback

| Поле | Tourvisor | ANEX | Andromeda | Что это значит |
| --- | --- | --- | --- | --- |
| Регион / курорт | upstream | local projection | local projection | нельзя заявлять supplier parity |
| Субрегион | upstream | local projection | local projection | нужен provider dictionary |
| Звёзды / category | upstream | local catalog filter | local catalog filter | raw supplier star key не универсален |
| Rating | upstream | local catalog | local catalog | фильтр честно local_only |
| Диапазон цены | upstream | post-filter | post-filter | поставщик мог вернуть больше предложений |

### Category / stars: проверенный source boundary

На указанном INT SHA:

- `app/integrations/anex-normalizer.php` сохраняет supplier `star` как raw label внутри `hotel`; это не universal category ID.
- `v2/api-anex-search3-preview.php`, `anytour_anex_search3_project()`, сравнивает локальный `catalog_hotels.category` с `hotelCategory`. Фильтрация идёт по принятой local identity и локальным metadata, а не по supplier star key.
- `v2/api-andromeda-search3-preview.php`, `anytour_andromeda_search3_project()`, читает тот же локальный `category` и вызывает общий локальный projector. Его построитель supplier params не переводит `hotelCategory` в доказанный supplier category filter.

Поэтому ANEX/Andromeda остаются `local_only`. Cast/default `0` в legacy projection не является доказательством категории «0 звёзд». Новый canonical property должен различать неизвестное значение, supplier raw label и local catalog category; рейтинг и hotel type нельзя подставлять вместо звёздности. До подтверждённых provider dictionaries и fixtures не добавлять универсальный numeric star mapper и не объявлять upstream parity. Этот аудит не меняет projection, каталог или mappings.

## Питание: label contract отдельно от supplier filter

`app/integrations/three-provider-meal-family.php` **уже реализован**: классифицирует явные RO/BB/HB/FB/AI/UAI labels, сохраняет raw label, `plus` и `without_alcohol`; неизвестные labels и числовые строки не получают подтверждённую family. `cross_provider_equivalence_verified` и `package_equivalence_verified` остаются false. Повторно создавать этот normalizer не нужно.

После #2068 `app/integrations/three-provider-offer-contract.php` проверяет заявленные family/qualifiers через этот существующий normalizer: `BB` не может заявить `AI`, `UAI` не схлопывается в `AI`, признак «без алкоголя» или «plus» нельзя потерять или придумать. Явное `family=null` остаётся unknown даже для знакомого label; автоматического повышения статуса нет.

**Это не расширяет upstream-фильтры.** `AI` остаётся текущим общим поисковым baseline:

- Tourvisor принимает текущий Search3 meal ID.
- Direct ANEX принимает `meal=7`, но supplier request не ограничивается доказанным supplier meal dictionary; AI фильтруется в projection.
- Andromeda текущий `meal=7` переводит в `MEAL=5`; live broad evidence реально вернул AI.

RO/BB/HB/FB/UAI label classification уже существует, но включать эти supplier filters по совпадению числовых ID нельзя. Следующий пакет этого семейства должен закрывать конкретный доказанный dictionary/projection gap, а не заново реализовывать классификацию labels.

## Room и placement

`app/integrations/three-provider-room-placement.php` **уже реализован** и переиспользуется canonical offer contract после #2067. Хранит raw + normalized display labels отдельно для room и placement; numeric IDs не принимаются как labels. Normalization имеет только `display_label_only` смысл и не доказывает package identity.

Fuzzy-слияние room names запрещено: `Standard Room`, `Standard Room A Block`, `Standard Land View`, `Economy Room` — разные значения, пока не доказано обратное.

Tourvisor и direct ANEX возвращают placement в сохранённых проверенных offers. Текущая Andromeda Search3 projection его не передаёт; отсутствующий placement остаётся `null`/unknown и не восстанавливается из room. До отдельного подтверждения источника placement не является equality-key трёх источников. Само наличие DTO не означает, что пропуск в supplier projection уже исправлен.

## Operator

`provider` и `operator` — разные поля; отдельный source-only контракт `app/integrations/three-provider-operator.php` уже используется общим offer DTO. Он не разрешает универсальный operator filter.

Tourvisor поддерживает operator filter. Direct ANEX provider сам ANEX и публичный adapter `operatorIds` отклоняет. Andromeda публично также отклоняет `operatorIds`; только внутренний diagnostic gate разрешает строго `OPERATORS=5` для ANEX-only тестов. Универсальный operator filter включать нельзя до provider-specific dictionary.

## Hotel types / services / arrival / direct / charter

Tourvisor gateway умеет эти параметры upstream. ANEX и Andromeda сейчас явно отклоняют непустые `hotelTypes`, `hotelServices`, `arrivalId`, `onlyDirect=true`, `onlyCharter=true`. Значит общий UI не должен делать вид, что это одинаковые upstream-фильтры всех источников.

## Цена и топливный сбор — сохранённое live evidence

Этот раздел — историческое cohort evidence broad-v2, не результат текущего source-only запуска и не разрешение replay.

Broad-v2 run `34547654033`, artifact `10179615450`, сценарий Moscow → Turkey, 05.10.2026, 7 ночей, 2 взрослых, AI, только ANEX:

- direct ANEX: 300 received offers, 209 mapped / **91 unmapped**, 27 AI offers в сравнительной проекции; observations сохранены для 300 unique hotels;
- Andromeda: 50 received, 44 mapped на page 1 из 2, 44 AI offers;
- Tourvisor: 19 hotel groups, 10 AI offers, во всех 10 есть `fuelCharge`;
- pair tuples: ANEX↔Andromeda 24, ANEX↔Tourvisor 6, Andromeda↔Tourvisor 7;
- **6 exact triple-aligned display tuples**.

Во всех 6 triple-aligned samples:

`direct ANEX search price == Andromeda search price`

и

`Tourvisor displayed/search price - ANEX/Andromeda search price == Tourvisor fuelCharge`

Примеры:

| Отель / room | ANEX | Andromeda | Tourvisor | TV fuelCharge |
| --- | ---: | ---: | ---: | ---: |
| Alexius Beach / Standard Room | 119 114 | 119 114 | 150 824 | 31 710 |
| Ares City / Standard Room | 112 730 | 112 730 | 144 440 | 31 710 |
| Ares Blue / Standard Room | 119 114 | 119 114 | 150 824 | 31 710 |
| Green Gold / Standard Room | 119 952 | 119 952 | 149 548 | 29 596 |
| Aperion Beach / Garden Room | 131 047 | 131 047 | 162 757 | 31 710 |
| Tal Beach / Standard Room | 124 662 | 124 662 | 156 372 | 31 710 |

Это **сильное cohort evidence**, что в этих ANEX tours direct ANEX и Andromeda search price идут без компонента, который Tourvisor показывает как `fuelCharge` и уже включает в свою цену. Но это ещё не разрешение менять общую арифметику: supplier package identity и universal/final-price contract не подтверждены.

Поэтому канонически храним отдельно:

- `search_price`;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`.

Отсутствующий fuel = `unknown`, не `0`.

## Уже реализованные source-only компоненты — не повторять

| Семейство | Текущий компонент | Что его наличие не доказывает |
| --- | --- | --- |
| Meal labels | `app/integrations/three-provider-meal-family.php` | universal supplier meal IDs / upstream filters |
| Room / placement | `app/integrations/three-provider-room-placement.php` | одинаковый package / наличие Andromeda placement |
| Operator | `app/integrations/three-provider-operator.php` | universal operator dictionary |
| Availability | `app/integrations/three-provider-availability.php` | supplier-confirmed bookability |
| Flight/baggage capability | `app/integrations/three-provider-flight-details.php` | загруженные flights / право на автоматический get_flights |
| Раздельные price facts | `app/integrations/three-provider-money-facts.php` | право складывать search/fuel/additional / final price |
| Общий offer DTO и контекст | `app/integrations/three-provider-offer-contract.php`, `app/integrations/three-provider-offer-context.php` | live rollout / ownership SEARCH / разрешение selection |

Следующий пакет выбирать из свежего главного roadmap после проверки исходников и claims. Для P2 нужен конкретный недостающий provider dictionary или доказанная потеря поля с bounded fixture; для P4/P6 — проверенный adapter-to-DTO handoff без изменения SEARCH renderer/controller. Неподтверждённые свойства остаются `local_only`/`unknown`, а не превращаются в новую очередь переписывания уже готовых компонентов. P0/P1 сохраняют приоритет, но live scenarios допустимы только при свободном supplier lane, зелёных budgets/CI и новом разрешённом operation; reserved/unknown cases не повторять. P3 matching остаётся внешней зависимостью и получает только observation handoff.

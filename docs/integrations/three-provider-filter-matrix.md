# AnyTour INT — матрица фильтров и свойств трёх источников

Дата: 2026-09-11. Источники: Tourvisor, прямой ANEX и Andromeda. Машиночитаемая версия — `reports/three-provider-filter-matrix-20260911.json`.

## Как читать статус

- `verified` — текущий adapter/contract умеет поле с подтверждённой семантикой в указанной границе.
- `local_only` — поле применяется после ответа поставщика по локальному каталогу; это не upstream-фильтр.
- `unsupported` — текущий adapter сознательно отклоняет непустой фильтр/свойство.
- `unknown` — данные могут встречаться, но общей доказанной семантики/проекции ещё нет.

Raw numeric ID одного поставщика никогда не считается ID другого.

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

## Питание

`AI` — единственная food-family, которая сейчас имеет достаточный общий baseline.

- Tourvisor принимает текущий Search3 meal ID.
- Direct ANEX сейчас принимает `meal=7`, но supplier request не ограничивается доказанным supplier meal dictionary; AI фильтруется в projection.
- Andromeda текущий `meal=7` переводит в `MEAL=5`; live broad evidence реально вернул AI.

Остальные RO/BB/HB/FB/UAI и т. п. пока нельзя объявлять общими только по числовым ID. Следующий пакет должен построить явный family mapping из supplier labels/dictionaries с хранением одновременно raw label и canonical family.

## Что пока не унифицировано

### Room

Raw room label есть во всех трёх источниках и уже полезен для price parity. Но fuzzy-слияние room names запрещено: `Standard Room`, `Standard Room A Block`, `Standard Land View`, `Economy Room` — разные значения, пока не доказано обратное. P4 должен хранить raw + normalized label без потери различий.

### Placement

Tourvisor и direct ANEX возвращают placement в проверенных offers. Текущая Andromeda Search3 projection его теряет, поэтому в broad-v2 он `null`. До исправления placement не является equality-key трёх источников.

### Operator

`provider` и `operator` — разные поля. Tourvisor поддерживает operator filter. Direct ANEX provider сам ANEX и публичный adapter `operatorIds` отклоняет. Andromeda публично также отклоняет `operatorIds`; только внутренний diagnostic gate разрешает строго `OPERATORS=5` для ANEX-only тестов. Универсальный operator filter включать нельзя до provider-specific dictionary.

### Hotel types / services / arrival / direct / charter

Tourvisor gateway умеет эти параметры upstream. ANEX и Andromeda сейчас явно отклоняют непустые `hotelTypes`, `hotelServices`, `arrivalId`, `onlyDirect=true`, `onlyCharter=true`. Значит общий UI не должен делать вид, что это одинаковые upstream-фильтры всех источников.

## Цена и топливный сбор — live evidence

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

## Следующие P2 пакеты

1. **Meal family dictionary** — RO/BB/HB/FB/AI/UAI и supplier aliases без raw-ID equivalence.
2. **Room + placement contract** — raw + normalized, добавить потерянный Andromeda placement, не делать fuzzy package merge.
3. **Operator contract** — provider отдельно от operator, supplier-specific mapping.
4. **Availability enum** — raw value + canonical enum, unknown сохраняется.
5. **Flight/baggage capability DTO** — optional details; Andromeda `get_flights` не запускать автоматически.
6. **Fuel/additional DTO** — использовать P0 evidence, но не менять price arithmetic до достаточной выборки и подтверждённого supplier contract.

После этих пакетов P4 собирает единый provider-neutral offer DTO. P3 matching остаётся внешней зависимостью и получает только observation handoff.

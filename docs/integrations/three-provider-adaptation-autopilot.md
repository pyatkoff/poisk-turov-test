# AnyTour INT — автопилот полной адаптации Tourvisor + ANEX + Andromeda

Дата: 2026-09-11. Репозиторий: только `pyatkoff/poisk-turov-test`.
Рабочая INT-основа: свежая `feature/anex-search-adapter-20260907`.
Координация: #996. Основные issues: #1685 (цена/топливо), #1717 (Andromeda package/selection), #1759 (hotel identity/matching), #1647 (data/review).

Этот документ задаёт последовательность автономной INT-работы до полной адаптации трёх источников. Он не заменяет `docs/project/anytour-development.md`, `AGENTS.md` или scoped state: перед каждым запуском автопилот обязан перечитать свежие head/issues/PR/CI и не повторять завершённые/unknown операции.

## Цель

Получить одну честную и совместимую модель тура, в которой Tourvisor, прямой ANEX и Andromeda могут:

1. искать туры по единому набору поддержанных критериев;
2. ссылаться на один локальный hotel identity, когда соответствие доказано;
3. сохранять внешние отели без `local_id` как наблюдения для дальнейшего matching;
4. отдавать нормализованные room/placement/meal/operator/availability/flight данные без выдуманных соответствий;
5. показывать поисковую цену, доплаты/топливный сбор и финально подтверждённую цену как разные факты;
6. передавать конкретный выбранный offer в общий SEARCH selected-tour flow;
7. безопасно перепроверять состав и цену без автоматического бронирования.

Production, отправка реальных заявок, `bron`/`bron_ticket`, изменение lead contract, Metrika/goals и production SEO в эту очередь не входят.

## Текущая база измерений

На старте этого плана фактический DB checkpoint из #1759 после full-catalog + delta passes:

- ANEX accepted links: 13 623; уникальных локальных Tourvisor targets: 11 667;
- Andromeda unique local targets: 6 929;
- all-three local identities: 3 417;
- exactly-two: 11 762;
- оставшиеся неоднозначные/конфликтные записи не принимать ослаблением matcher.

Это baseline для дельты, а не повод повторять completed imports.

## Метрики автопилота

Каждый существенный пакет должен обновлять только применимые метрики:

- `triple_identity_count` — local hotels, имеющие Tourvisor+ANEX+Andromeda;
- `exactly_two_identity_count`;
- `unmapped_observed_external_hotels` по provider/country и частоте поиска;
- `search_scenarios_completed` и покрытие country/date/nights/party/meal/star;
- `three_source_price_candidates` — сравнимые туры на одном current triple-mapped hotel;
- `fuel_semantics_verified` отдельно для TV/ANEX/Andromeda;
- `canonical_filter_coverage` по матрице ниже;
- `offer_contract_coverage` по provider;
- `final_price_verified_samples` по provider/operator;
- `selected_offer_e2e_accepted` по provider.

Нельзя улучшать метрику путём ослабления identity/price guards.

---

# Этап P0 — цена и топливные/дополнительные сборы

**Приоритет №1.** До завершения этого этапа `search price` нигде не объявляется финальной ценой по предположению.

### P0.1 ANEX-only parity на одном и том же отеле

Для текущего уникального triple-mapped local hotel выполнять сравнение:

- direct ANEX — только ANEX;
- Andromeda — `OPERATORS=5` (ANEX only);
- Tourvisor — оператор ANEX в исходном запросе.

Совмещать предложения только по доказанному local identity и нормализованному display tuple:
`date + nights + adults/children + meal + room`, placement сохранять отдельно, пока оно не доступно/унифицировано во всех трёх projection.

Не считать совпадение цены доказательством одного supplier package.

### P0.2 Разделить денежные факты

Каноническая модель должна хранить раздельно:

- `search_price`;
- `search_currency`;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`;
- источник/метод и timestamp каждого значения.

Правила:

- Tourvisor `fuelCharge` хранить отдельно; не прибавлять автоматически к `price` без доказанной семантики текущего API/UI;
- ANEX search price не считать final; `AdditionalPricesDaily` — отдельное evidence, пока не подтверждён exact contract/authorization;
- Andromeda `action=price` не получает выдуманный fuel=0; отсутствие отдельного поля = unknown;
- buyer/customer money и agency cost никогда не объединять fallback-логикой.

### P0.3 Минимальная доказательная выборка

До фикса общей арифметики собрать минимум:

- 10 сравнимых ANEX-tour samples по Турции на разных hotel/room/date;
- 5 Египет, если ANEX доступен в тех же трёх источниках;
- минимум 3 случая с ненулевым `fuel/additional`, если такие реально возвращаются;
- минимум 3 случая без дополнительного сбора;
- минимум 3 разных room/placement combinations.

Если поставщик/метод отвечает unknown — sample сохраняется unknown и не replay автоматически.

**DONE P0:** документированная семантика денег каждого источника + тесты + реальные sanitized samples. Любая арифметика final price делается только после этого.

---

# Этап P1 — матрица разнообразных поисков и накопление базы

Цель — тесты одновременно улучшают coverage данных, а не расходуются на однотипные запросы.

## P1.1 Search observation ledger

Каждый разрешённый read-only тестовый поиск сохраняет нормализованное observation evidence:

- provider/source/operator;
- критерии поиска;
- external hotel id + local id/null;
- hotel name/country/region/subregion/star/category/coords, если реально известны;
- date/nights/pax/child ages;
- meal/room/placement;
- availability/flight flags;
- search price/currency;
- fuel/additional fields отдельно;
- timestamp/source request lineage.

Для `local_id=null` запись обязательна: она формирует очередь будущего matching. Цена никогда не используется как самостоятельное доказательство hotel identity.

## P1.2 Матрица сценариев

Автопилот выбирает сценарии так, чтобы максимизировать новые комбинации, а не повторять последнюю:

- страны core8 сначала: Turkey, Egypt, Thailand, Maldives, UAE, Cuba, Sri Lanka, Vietnam;
- разные даты в доступном будущем окне;
- nights: 6/7/8/10/12/14, когда provider поддерживает;
- 2 adults baseline + варианты 1/3 adults;
- family cases: 2+1 child разного возраста, затем 2 children;
- meals: сначала AI, затем другие meal-family только после появления verified mapping;
- звёзды: без фильтра / 3 / 4 / 5 после verified source mapping;
- hotel-scoped и broad search;
- разные resorts/regions после verified mapping.

На один hourly run — ограниченный batch. Начальное правило: **до 3 новых сценариев**, если предыдущие не unknown и supplier budgets/CI зелёные. Если сценарий дал большое число новых external IDs, следующий run приоритизирует другое country/date/party измерение, а не тот же запрос.

## P1.3 Выбор следующего сценария

Score сценария повышают:

1. страна/дата/party/meal/star combination ещё не проверялась;
2. высокий бизнес-приоритет страны;
3. ожидается много unmapped observations;
4. есть triple-mapped hotels для price parity;
5. нужен regression sample после изменения adapter/filter mapping.

Понижают score:

- недавно выполненный почти идентичный запрос;
- provider unknown/reserved checkpoint;
- неподтверждённый filter mapping;
- риск превышения supplier limits.

**DONE P1:** существует воспроизводимый ledger и широкий набор observation data, который регулярно пополняет price history и unmatched-hotel queue.

---

# Этап P2 — единая матрица фильтров и свойств

Никакой supplier numeric ID не считается универсальным ID.

## Canonical filter matrix

Для каждого поля статус только `verified`, `local_only`, `unsupported`, `unknown`:

| Canonical field | Tourvisor | ANEX | Andromeda | Цель |
| --- | --- | --- | --- | --- |
| departure | verified baseline | verified dictionary mapping | verified dictionary mapping | полный parity |
| country | verified baseline | verified dictionary mapping | verified saved catalog | полный parity |
| region/resort | local catalog | частично/local projection | пока ограниченно | verified provider mapping |
| subregion | local catalog | local projection | пока ограниченно | verified provider mapping |
| hotel | local ID | accepted ANEX mapping / observation | accepted Andromeda mapping / observation | полный parity |
| hotel category/stars | local catalog | supplier star label + local filter | saved catalog label требует проверки | semantic mapping, не raw key |
| rating | local catalog | local-only filter | local-only filter | честно помечать local_only |
| meal | TV/local | сейчас AI verified | сейчас AI verified | family dictionary: RO/BB/HB/FB/AI/UAI/... |
| room | offer text | offer text | offer text | canonical normalized label + raw label |
| placement | offer text | offer text | projection gap | добавить честный source field |
| operator | TV operator | ANEX fixed/current | Andromeda operator | operator != provider |
| hotel services | есть в Search3 | unsupported upstream | unsupported | либо verified mapping, либо local_only |
| hotel types | есть в Search3 | unsupported upstream | unsupported | либо verified mapping, либо local_only |
| arrival airport | TV/Search3 | unsupported | unsupported | verified mapping до upstream filter |
| direct flight | TV | unsupported | unsupported | verified semantics before enable |
| charter | TV | unsupported | unsupported | verified semantics before enable |
| date/nights | verified | verified | verified | parity |
| adults/children/ages | verified | verified | verified | parity |
| price range | TV/search | local post-filter | local post-filter | общая UX semantics |
| currency | verified | RUB conversion/search | RUB | canonical money contract |
| availability | verified/source | supplier | supplier | normalize enum, preserve raw evidence |
| flight/baggage | TV | separate details | separate methods | common optional detail DTO |
| fuel/additional | TV separate field | AdditionalPricesDaily | unknown/search; package/quote later | P0/P5 |

Автопилот должен создавать отдельный маленький пакет на одно семейство справочника/полей, а не огромный rewrite всех providers.

**DONE P2:** Search3 знает, какие фильтры можно отправить upstream каждому provider, какие можно применять только локально и какие нельзя показывать как поддержанные.

---

# Этап P3 — hotel identity coverage

Продолжать #1759 только по свежей DB delta.

Приоритет очереди:

1. exact accepted cross-provider bridge;
2. exact name/alias + country + compatible geography;
3. tight coordinates + clear name-margin;
4. exact geography + strong fuzzy winner margin;
5. manual review.

Не принимать автоматически:

- >5 km coordinate conflicts;
- category/star conflict, пока dictionary semantics не проверены;
- non-unique same-name candidates;
- manual/rejected pair conflicts;
- Russia/Abkhazia и страны вне продаваемого scope владельца.

Unmapped external IDs из P1 сортировать прежде всего по `search_count`, recency и бизнес-приоритету страны.

**DONE P3:** не «100% каталога любой ценой», а практически полное покрытие реально встречающихся в поиске продаваемых отелей + прозрачная manual/conflict очередь.

---

# Этап P4 — единый canonical offer contract

Единый Search3 offer DTO должен различать:

- `provider` — tourvisor/anex/andromeda;
- `operator` — фактический ТО;
- `local_hotel_id`;
- provider search/offer identity;
- date/nights/pax;
- raw + normalized meal;
- raw + normalized room;
- raw + normalized placement;
- availability;
- flight/detail capability flags;
- search price money fact;
- fuel/additional money facts;
- `final_price_verified=false` по умолчанию;
- source timestamps/freshness.

Private supplier IDs/SID/credentials не выходят в browser DTO. Новый поиск инвалидирует старый provider context.

**DONE P4:** SEARCH может рендерить и выбирать предложения всех трёх источников без provider-specific UI веток.

---

# Этап P5 — package/quote и финальная цена

Зависит от ответа SAMO по `claiminc`/`broninit` и от фактических P0 evidence.

- `broninit` использовать только как разрешённый package/details шаг, не booking;
- unknown никогда не replay автоматически;
- после подтверждения supplier contract отдельно определить необходимость/семантику `calc`, `get_flights`, ANEX additional prices;
- price change должен возвращать old/new/delta/timestamp/source и требовать явного принятия пользователя в SEARCH;
- состав пакета и цена клиента извлекаются allowlist-проекцией, без PII/agency cost.

**DONE P5:** для выбранного тура можно честно получить актуальный package/price и отличить unchanged/changed/unavailable.

---

# Этап P6 — selected-tour flow для трёх источников

Совместно с SEARCH #1646, один UI owner.

Сценарий:

`search → filters → hotel → offer variants → select → package/price check → accept changed price if needed → existing lead-form handoff → return`.

Acceptance обязательно включает:

- Tourvisor offer;
- direct ANEX offer;
- Andromeda offer как минимум двух операторов после готовности;
- A→B rapid selection race;
- stale/expired provider context;
- return без лишнего supplier search;
- ошибка одного provider не сбрасывает два других;
- mobile + desktop.

Реальную заявку не отправлять в тестовой приёмке.

---

# Этап P7 — release readiness

Перед переносом из integration branch:

1. fresh source/release heads;
2. contract/test matrices green;
3. current preview exact publication/readback;
4. supplier budgets and no-replay checkpoints intact;
5. money semantics documented;
6. filter matrix documented;
7. unmatched/manual queues имеют понятный остаток;
8. no lead/Metrika/Tourvisor protected contract drift;
9. rollback/provenance для опубликованного preview;
10. production migration только отдельным явным owner approval.

---

# Hourly autopilot policy

Каждый запуск:

1. Прочитать свежие `AGENTS.md`, release `docs/project/anytour-development.md`, этот план, scoped `anex-search3-autopilot.md`, #996 и issues #1685/#1717/#1759/#1647.
2. Проверить свежий INT head, открытые PR/CI/actions и active claims. Не дублировать активный writer.
3. Выбрать **один главный пакет** по порядку P0→P7. Если он blocked — записать точный blocker и взять независимый пакет следующего этапа, который не нарушает зависимость.
4. Для каждого package: fresh short branch → claim #996 → implementation → focused tests → Security/applicable CI → merge в INT только при зелёном результате → live/read-only execution только если уже разрешено действующими owner rules.
5. После merge, если остаётся безопасный следующий шаг в текущем запуске — продолжить. Не останавливаться после одного PR.
6. Completed/unknown supplier operation никогда не replay. Новый алгоритм = новый operation/checkpoint/scenario.
7. Supplier searches должны приносить observation value: новый сценарий, новые prices либо новые external hotel IDs. Не тратить запросы ради повторного отчёта.
8. Price/fuel fields сохранять раздельно; никакой новой price arithmetic до P0 DONE.
9. Hotel price/name similarity никогда не принимает mapping самостоятельно.
10. Уведомлять владельца только о новом значимом результате, blocker requiring decision или изменении money/filter semantics.

## Стоп-гейты

Нужен отдельный owner approval, если требуется:

- production/main вне уже одобренной preview-control инфраструктуры;
- booking (`bron`, `bron_ticket`) или реальная заявка;
- изменение Tourvisor protected URL/payload/price arithmetic;
- изменение lead contract;
- Metrika/goals/analytics;
- необратимая schema/server/platform операция;
- ослабление manual/pair/conflict guards.

Ожидание ответа SAMO блокирует только зависимый package/quote шаг, но не P1/P2/P3/P4 source-only работу.

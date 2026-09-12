# AnyTour INT — Tourvisor + direct ANEX + Andromeda

Актуализировано: 2026-09-13. Репозиторий: только `pyatkoff/poisk-turov-test`.
INT-база: свежая `feature/anex-search-adapter-20260907`. Координация: #996.
Рабочие issues: #1685, #1717, #1647. #1759 — только внешний identity dependency.

Этот файл — исполняемый current-only roadmap INT/data/API. Свежие issue, merged source, CI и readback сильнее любого старого checkpoint/next_action. Подробная история остаётся в issues/PR; сюда не дублировать завершённые очереди.

## 1. Обязательный pre-run

Перед каждым запуском перечитать свежие:

1. `AGENTS.md`;
2. release `docs/project/anytour-development.md`;
3. `docs/integrations/anex-search3-autopilot.md`;
4. этот roadmap;
5. #996, #1685, #1717, #1647;
6. #1759 только как current accepted identity baseline/dependency;
7. fresh INT head, open PR/CI/actions и active claims.

Один writer на shared path. Short branch от fresh INT base → exact claim #996 → focused tests + Security/applicable CI → merge только green. Без force-push. SAFE/MEDIUM source-only пакеты продолжать автономно; HIGH-risk новое действие требует отдельного допуска.

## 2. Scope / hard boundaries

INT владеет:

- supplier transport/auth/read-only search;
- provider dictionaries и filter semantics;
- observation ledger;
- money/fuel/additional evidence;
- provider-neutral offer/quote/handoff contracts;
- разрешённой Andromeda package/quote source pipeline.

INT не владеет:

- P3 hotel matching / identity reconciliation — EXTERNAL (#1759);
- mapping writers, acceptance/manual/conflict queues, bulk reconciliation;
- SEARCH #1646 renderer/controller/UI;
- SITE #1719 и SEO #1720;
- lead transport/field mapping;
- Metrika/goals/analytics;
- production SEO;
- server/platform config;
- Tourvisor protected URL/payload/price arithmetic.

Нельзя отправлять реальные заявки/бронирования. `bron` и `bron_ticket` запрещены.

## 3. No-replay / supplier guards

- `completed`, `reserved`, `unknown` supplier operation не replay.
- Новый supplier scenario = новый `operation_id`, checkpoint, criteria digest и source SHA.
- Переименование checkpoint не превращает replay в новую операцию.
- Legacy UNKNOWN `broninit` sealed/no-replay.
- Старый Egypt-v3 case historical/no-replay.
- PHP diagnostic `$out += [...]` bug уже исправлен; второй fix не создавать.
- `PRICES[].id` подтверждён как полный `claiminc` для Andromeda package flow.
- Historical identity counts не current truth; свежий #1759/DB readback авторитетнее.

## 4. Current money / P0 semantics

Turkey broad-v2 ANEX-only parity дал 6 exact aligned tuples на current accepted triple identities:

- direct ANEX `search_price` == Andromeda `OPERATORS=5` `search_price` во всех 6;
- Tourvisor displayed/group price minus этот search price == Tourvisor `fuelCharge` во всех 6;
- это cohort evidence, не универсальная formula и не proof одного package.

Всегда хранить раздельно:

- `search_price` + currency;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`;
- source/method/timestamp/context provenance.

Отсутствующий fuel/additional = `unknown`, не `0`. Ничего автоматически не складывать. Agency cost и customer money не fallback друг в друга. Protected price arithmetic не менять.

Для одного exact Andromeda claim доказана и sealed цепочка:

`broninit 124864 RUB → get_flights → unique outbound+return changeservice → calc 135643 RUB`.

`final_price_verified=true` относится только к exact verified claim. Это доказывает, что search/package и calc quote могут отличаться, но не доказывает fuel formula.

### 4.1 AdditionalPricesDaily — текущий P0 blocker

#2236/#2237 дали первый bounded cohort: для B2B `tour=778` ставка `13549.9 RUB/adult`, две взрослые ставки `27099.8`, сопоставимый Tourvisor fuel/delta `27100`. Это evidence применимости adult-rate внутри этого cohort, а не разрешение округлять, складывать или переносить формулу на другие предложения.

#2250/#2251 на сохранённых concrete GREEN GOLD предложениях доказали две разные SearchTour family/flight bands: `tourKey=2637` имеет базы `119448`/`122366` и Tourvisor fuel `20846`; `tourKey=1797` имеет базы `133310`/`136541` и Tourvisor fuel `29184`. При этом `AdditionalPricesDaily(tour=2637)` и `AdditionalPricesDaily(tour=1797)` вернули одинаковый two-adult candidate `29184.4`. Следовательно, числовой B2B параметр `tour` **не установлен** как тот же namespace, что SearchTour `tourKey` / `supplier_tour_program_id`.

#2258/#2262/#2263 дополнительно доказали для exact program1797, что Tourvisor fuel/final зависит от выбранной flight combination при неизменной direct-ANEX базе: default PC1457+PC1456 даёт `133310 + 29184 = 162494`, alternate TK3155+PC1456 — `133310 + 32311 = 165621`. `AdditionalPricesDaily=29184.4` совпадает только с default band и не объясняет alternate band. Search price/base, Tourvisor `fuelCharge`, AdditionalPricesDaily, package money и final/quote money остаются отдельными фактами.

#2265 inspected six concrete SearchTour rows. Доступны provider-scoped `tourKey`, `programTypeKey`, `spoKey`, `partnerIncomingKey`, `packetType`, `currencyKey` и другие поля, но отдельного поля, явно связывающего SearchTour с B2B `AdditionalPricesDaily.tour`, не найдено. `tour`/`tourAlt` там являются labels, а не доказанным B2B id. Не пробовать `programTypeKey`, `spoKey` или другие числа как B2B `tour` по догадке.

#2267 закрыл ещё один direct read-only путь: one-shot SearchTour search+expand по exact GREEN GOLD cohort завершён COMPLETE/NO-REPLAY, и у всех шести concrete rows под `freights` нет ни одного money/surcharge-like значения (`money=[]`). Ранее FreightMonitor также не дал money. Поэтому SearchTour + FreightMonitor read-only schemas не содержат недостающие `20846/29184` как готовый exact fuel fact.

Текущий точный P0 blocker: нужен **authoritative ANEX B2B tour dictionary/lookup либо supplier-issued binding** от concrete SearchTour/CATCLAIM/freight package к `AdditionalPricesDaily.tour`. До этого direct-ANEX production fuel arithmetic выключена, новые blind numeric `tour` probes запрещены, отсутствующий fuel остаётся `unknown`. Saved Tourvisor observations можно исследовать как отдельное zero-new-supplier evidence, но они не становятся direct-ANEX supplier authority и не разрешают runtime arithmetic. Если authoritative binding недоступен, следующий независимый полезный шаг — genuinely new P1 observation scenario при green budget/owner-control; P2/P4/P6/P7 ради заполнения очереди не расширять.

## 5. Закрытые source-side contracts — второй слой не создавать

Без нового evidence/regression не открывать заново:

- dates/nights/adults/children/ages;
- canonical meal key/families/qualifiers;
- room/placement raw+normalized semantics;
- availability с честным `unknown`;
- flight/detail capability flags;
- provider != operator;
- strict money provenance + `observed_at`;
- canonical offer envelope/dedupe/context guards;
- air-search capability boundary;
- departure/country provider-specific mapping boundary;
- region/resort/subregion geography boundary;
- stars/category boundary;
- rating/services/types local-only boundaries;
- provider search coverage/exhaustion semantics;
- provider-neutral P1 observation evidence + immutable scenario lineage;
- direct ANEX retained concrete-offer bridge;
- Andromeda quote integrity + canonical quote money;
- P6 provider-neutral SEARCH handoff.

### 5.1 Direct ANEX retained offer

#2081 merged `e92253bbcadfc5228f3fa233f64be5cd1983228f`:

- random `search_ref` + opaque source-qualified `offer_ref`;
- original normalized facts retained;
- one-shot group expansion с durable unknown-before-transport guard;
- supplier-free saved concrete-offer read;
- current mapping/catalog/generation/search/fixed-expiry guards;
- group minimum нельзя выдать за concrete selected offer;
- private supplier IDs не публикуются.

Receiving wiring принадлежит SEARCH #1646.

### 5.2 Andromeda quote integrity

#2088/#2090/#2092/#2094:

- durable `reserved` до supplier call;
- `reserved/unknown` запрещают semantic replay;
- completed read supplier-free и только после current-context revalidation;
- malformed/zero/extra money fail closed;
- search/package/quote money раздельны;
- `final_price_verified=true` только через verified Andromeda quote;
- `search_price_fuel_relation=unknown`, `arithmetic_applied=false`;
- verified quote обязан совпасть с exact current provider/operator/local hotel + digest/generation/page/TTL/search money;
- private claim/session/offer IDs и flight payload не переходят browser boundary;
- selection/booking disabled/false.

### 5.3 Air search/filter

#2100/#2101:

- Tourvisor имеет verified catalog/discovery evidence для arrivals + `onlyDirect`/`onlyCharter` catalog paths;
- это не разрешает менять protected Tourvisor tour-search payload;
- upstream supplier-search filtering для arrival/direct/charter пока `unknown`/fail-closed;
- direct ANEX/Andromeda capability без нового provider contract остаётся unknown;
- raw airport/supplier IDs provider-specific; cross-provider equivalence=false.

### 5.4 Region / resort / subregion

#2112 merged `8223edf0efc054ec927367d8d385d5729fc4f817`; exact head `ed393add386fcc9cbb6d17bfcae7a663ef91956b`.
Focused `34676124554` SUCCESS: 74; aggregate `34676124543` SUCCESS; Security `34676124544` SUCCESS.

- supplier region/resort/subregion ID/label = bounded provider-scoped observation only;
- numeric-looking supplier IDs остаются opaque strings, including leading zeroes;
- canonical local geography `verified` per-level только из current accepted local identity;
- generic upstream geography `unknown`, `allowed=false`, provider-specific mapping required;
- current direct ANEX adapter generic geography forwarding = `not_implemented` (это факт текущей wiring, не утверждение об API ANEX);
- Tourvisor/Andromeda generic forwarding = `not_verified`;
- geography equality/similarity не identity proof и не mapping authority.

### 5.5 Departure / country

Direct ANEX package #2114 merged `e92dbd6e85ee8e273dda59f761a895b00ac7b828`; exact head `7b51b0bdb9326fdc8a7c3389418b068795283232`.
Focused `34676419118` SUCCESS: 73; Security `34676419019` SUCCESS.

Direct ANEX current source доказывает provider-specific mapping contract:

1. browser/local numeric departure/country IDs **не** становятся supplier IDs;
2. server читает authoritative active local `catalog_departures/catalog_countries` names;
3. supplier departure выбирается exact unique name match из `SearchTour_TOWNFROMS`;
4. supplier country выбирается exact unique name match из `SearchTour_STATES`, lookup scoped resolved supplier departure (`TOWNFROMINC`);
5. только после этого verified mapping разрешает provider-specific `TOWNFROMINC/STATEINC`;
6. missing/ambiguous mapping fail closed.

Andromeda follow-up #2116 merged `865b96f4b96f04d4d602dfba88ac3b7c390b5bd8`; exact head `7a5be19998702f812483319ea3bafd37aeda280d`.
Focused `34676677127` SUCCESS: 82; aggregate `34676677124` SUCCESS; Security `34676677093` SUCCESS.

Andromeda current source доказывает отдельный provider-specific contract:

1. request country обязан совпадать с installed saved `local_country_id`;
2. current active local departure name разрешается exact unique match в saved supplier `TOWNFROM` dictionary;
3. supplier country берётся из installed saved catalog pin `STATEINC` для этого local country slice;
4. только при доказанных exact departure mapping + explicit country pin provider-specific supplier IDs считаются usable;
5. local numeric form IDs не становятся supplier IDs; missing/mismatched context fail closed.

Это **не** тот же mapping contract, что у direct ANEX: ANEX country dictionary разрешается departure-scoped exact lookup, Andromeda country сейчас pinned installed catalog slice. Supplier IDs остаются opaque/provider-specific; cross-provider equivalence=false. Tourvisor departure/country mapping остаётся `not_verified_in_this_boundary`; protected payload не менять. Departure/country similarity не hotel identity proof.

### 5.6 Hotel category / rating / services / types

#2103/#2104/#2107/#2108/#2110:

- supplier category/star/rating/service/type values = observations only;
- canonical category/rating/services/types получают authority только из current accepted local identity/current local catalog;
- rating/services/types остаются local-only и generic upstream запрещён;
- supplier numeric-looking IDs/scores/codes не универсальны;
- cross-provider equivalence=false;
- similarity не hotel identity proof;
- services/types strict dedupe обязан сохранять opaque numeric-string type (`"77"` не превращать в integer key).

### 5.7 Search coverage / exhaustion

#2118 merged `3fd3b8ae4bb63bfaa3f3514dd586d10bafe4dbf7`; exact head `b33d3266f787158012cfe6eb81b6d85e9162d61e`.
Focused `34677474785` SUCCESS: 101; aggregate `34677474771` SUCCESS; Security `34677474772` SUCCESS.

- direct ANEX current search fixes `PRICEPAGE=1`; without a verified total-page/continuation contract its observed rows are `bounded`, never automatically exhaustive;
- Andromeda result is `complete` only when one exact search context retains every advertised page `1..PAGES_COUNT`; page 1 of N is `partial`;
- Tourvisor `status=complete` alone does not prove result exhaustion; canonical `complete` requires an explicit continuation round and verified no-growth evidence;
- bounded/partial rows remain useful observations, but counts from different providers are not proven comparable;
- coverage/count equality never proves hotel identity, package identity or price equivalence and never authorizes mapping/arithmetic changes.

### 5.8 P1 observation evidence

#2120 merged `7a935bf3445e98488e893fae4034dea2b67f2168`; P7 follow-up #2122 merged `0a07ea2c000094a3b6d98a96101b4519e8c1c50f`.
Aggregate exact head `731403082238bcad0685a85d3deda1af384612fa`: run `34678908441` SUCCESS; Security `34678908436` SUCCESS.

- one provider-neutral evidence envelope covers already observed Tourvisor/direct-ANEX/Andromeda rows;
- every observation binds the existing canonical search window, meal, room/placement, availability, money and provider-specific coverage contract;
- immutable scenario lineage is mandatory: `operation_id`, scenario revision, criteria digest, source SHA and relative checkpoint path;
- external hotel ID remains an opaque provider-scoped string, including numeric-looking IDs/leading zeroes;
- `local_hotel_id=null` is explicit `unmapped` evidence only; actually available name/country/geography/star/coords may travel to #1759/#996, never as mapping authority;
- supplier labels, coordinates, counts/coverage and price similarity are not hotel/package/price-equivalence proof;
- observation contract sets mapping/identity decision and replay authority false;
- P7 runs this suite with PHP warnings promoted to failures; no network/DB/booking primitives are allowed in the aggregate boundary.

## 6. P6 source handoff — DONE

#2096 merged `13fef359e80b7eb24c6569b76433babbb44c2dbe`; CI `34665905819` SUCCESS, Security `34665905799` SUCCESS.

Один browser-safe INT→SEARCH DTO покрывает Tourvisor search offer, direct ANEX concrete saved offer, Andromeda search offer и Andromeda verified quote. Перед projection повторно валидируются retained/context/nested facts, stale/race/TTL/local identity guards. Private supplier refs не публикуются. Search offer остаётся quote unknown/final false; final verified допустим только через verified Andromeda quote. На INT boundary всегда selection disabled / booking false.

Receiving wiring, renderer/controller/selected-state/publication принадлежат SEARCH #1646.

## 7. P7 source release readiness — GREEN

Latest exact-head gate после #2122:

- money facts: 60;
- offer contract: 189;
- retained context: 33;
- air search/filter: 114;
- region/resort/subregion: 74;
- departure/country: 82;
- search coverage: 101;
- search observation evidence: 169;
- hotel category: 57;
- hotel rating: 69;
- hotel services: 67;
- hotel types: 67;
- quote envelope: 54;
- INT→SEARCH handoff: 97;
- direct ANEX bridge: 115.

Aggregate `34678908441` SUCCESS: **1348 offline contract checks**. Security `34678908436` SUCCESS. Static boundary: no network/DB/booking primitives; no synthetic arithmetic; selection/booking disabled; provider-specific IDs, bounded result counts and unmapped observation evidence не получают universal/mapping authority.

Это source-side readiness, не production approval и не UI/publication acceptance.

## 8. Исполняемый приоритет дальше

Порядок:

`P0 money/fuel/additional → P1 observation matrix → P2 only genuine new provider-specific semantics → P4/P5 regression/new evidence only → P6/P7 maintenance only if regression → SEARCH handoff`.

P3 identity/matching никогда не fallback-задача INT.

После #2096/#2097/#2100/#2101/#2103/#2104/#2107/#2108/#2110/#2112/#2114/#2116/#2118/#2120/#2122 запрещено создавать второй quote/handoff/air/geography/departure-country/category/rating/services/types/search-coverage/observation wrapper ради активности.

### P0/P1 — следующий eligible work

Новый supplier search разрешён только если:

- related case не completed/reserved/unknown replay;
- applicable CI/budgets green;
- scenario materially different;
- есть новая price/fuel/additional observation, materially new scenario или external hotel IDs;
- максимум 3 новых supplier scenarios за run.

Diversity priority:

- core8: Turkey, Egypt, Thailand, Maldives, UAE, Cuba, Sri Lanka, Vietnam;
- future dates;
- nights 6/7/8/10/12/14;
- adults 1/2/3;
- family 2+1, затем 2 children with ages;
- broad и hotel-scoped;
- new room/placement или ещё не observed fuel/additional state предпочтительнее почти идентичного поиска.

ANEX-only parity только current accepted triple-mapped subject:

direct ANEX ↔ Andromeda `OPERATORS=5` ↔ Tourvisor с ANEX в исходном request.

Tuple alignment: `current local identity + date + nights + party + canonical meal + room`; placement отдельно до доказанного parity.

Coverage обязан идти рядом с observation: direct ANEX `PRICEPAGE=1` считать bounded, Andromeda complete только после всех advertised pages одного search context, Tourvisor complete только после explicit continue/no-growth evidence. Нельзя сравнивать provider counts как exhaustive, если соответствующий coverage contract этого не доказывает.

Для `local_id=null` использовать merged P1 observation envelope: сохранять provider/country/external id/name/geography/star/coords/search criteria только если реально доступны, вместе с coverage + immutable scenario lineage, и передавать #1759/#996. Mapping writes запрещены.

### P2 — status matrix

Statuses: `verified | local_only | unsupported | unknown`. Raw supplier numeric IDs никогда не universal.

| Family | Current rule |
| --- | --- |
| departure/country | direct ANEX verified exact unique provider dictionaries; Andromeda verified exact saved departure dictionary + installed local-country/supplier-STATEINC pin; Tourvisor not verified/protected |
| region/resort/subregion | generic boundary closed: supplier observation-only; local canonical only current accepted identity; upstream unknown/disabled |
| hotel | current accepted identity либо observation; P3 writes external |
| stars/category | supplier label observation-only; canonical current-local only |
| rating | local_only/closed |
| services | local_only/closed |
| types | local_only/closed |
| meal | verified canonical family/key contract closed |
| room/placement | raw+normalized contract closed; placement kept separate until parity |
| operator | provider != operator; supplier codes private |
| arrival airport/direct/charter | Tourvisor discovery evidence only; supplier-search forwarding unknown/fail-closed |
| dates/nights/party/ages | verified/closed |
| availability | source/raw evidence; absent proof = unknown |
| flights/baggage | optional details capability; never auto-fetch by generic contract |
| fuel/additional | separate money facts; never synthetic total |

Не создавать новый generic P2 wrapper, если family уже имеет current rule. Следующий P2 пакет допустим только при **новом provider-specific evidence**, которое безопасно повышает конкретный status/capability. Tourvisor protected payload не трогать ради симметрии. Если такого evidence нет — возвращаться к genuinely new P0/P1 evidence, а не создавать wrapper churn.

## 9. Observation contract

Merged `AnyTourThreeProviderSearchObservation` — единственная source-side P1 envelope для новых наблюдений. Когда реально доступны, она сохраняет:

- provider + operator;
- country/region/subregion/geography;
- provider-scoped opaque external hotel id;
- current `local_id` или null/unmapped;
- raw name, stars/category, coords;
- exact search criteria/date/nights/party/child ages;
- raw+normalized meal/room/placement;
- availability evidence;
- search price/currency;
- fuel/additional states отдельно;
- search coverage state + provider-specific exhaustion evidence;
- `observed_at` + immutable source request lineage.

Каждый supplier scenario получает `operation_id`, checkpoint path, scenario revision, criteria digest и source SHA. Observation envelope не даёт replay/mapping/package/price-equivalence authority и не заменяет #1759 identity decision.

## 10. Definition of useful progress / stop gates

Полезный пакет даёт минимум одно:

- новый доказанный money/fuel/additional fact;
- новый non-replay observation scenario;
- new external-hotel evidence handoff без mapping write;
- new provider-specific filter/property evidence;
- regression fix canonical offer/quote/handoff guards;
- новый подтверждённый package/quote state без booking;
- устранённый stale-state/unsafe-replay risk.

Не создавать PR/checkpoint churn без результата.

Отдельное разрешение обязательно для production/main, `bron`/`bron_ticket`, real booking/application, Tourvisor protected contract/arithmetic, lead contract, Metrika/goals, production SEO, server/platform config, irreversible schema/data operation, weakening manual/pair/conflict guards и любого нового HIGH-risk supplier action.

Source-side INT readiness не production approval. Preview/publication/readback только по current exact owner-control и соответствующему владельцу.

# AnyTour INT — автономная адаптация Tourvisor + direct ANEX + Andromeda

Дата актуализации: 2026-09-12. Репозиторий: только `pyatkoff/poisk-turov-test`.
INT-база: свежая `feature/anex-search-adapter-20260907`.
Координация: #996. Рабочие issues: #1685, #1717, #1647. #1759 — только внешний identity dependency.

Этот документ — исполняемый roadmap INT/data/API. Он не заменяет `AGENTS.md`, release `docs/project/anytour-development.md` и scoped `docs/integrations/anex-search3-autopilot.md`. Свежие issue/merged code/CI/readback всегда сильнее исторического текста.

## 0. Scope и владельцы

INT владеет только:

- supplier transport/auth/read-only search;
- provider dictionaries и честной filter/property semantics;
- observation ledger;
- money/price/fuel/additional evidence;
- canonical provider-neutral offer/quote DTO и fixtures;
- Andromeda package/quote source pipeline в разрешённых границах;
- bounded INT → SEARCH handoff contract без владения UI.

Не брать из этой очереди:

- **P3 hotel matching / identity reconciliation — EXTERNAL, #1759**;
- SEARCH #1646 renderer/controller/UI;
- SITE #1719;
- SEO #1720;
- mapping writers/acceptance/manual/conflict queues;
- lead transport/field mapping;
- Metrika/goals/analytics;
- production SEO;
- server/platform config;
- Tourvisor protected URL/payload/price arithmetic.

Для #1759 разрешено только читать fresh current identity baseline и передавать evidence о новых external hotel IDs. Никаких mapping writes или bulk reconciliation из INT.

---

## 1. Обязательный fresh-state guard перед каждым запуском

Перед выбором пакета:

1. перечитать свежие `AGENTS.md`;
2. перечитать release `docs/project/anytour-development.md`;
3. перечитать `docs/integrations/anex-search3-autopilot.md`;
4. перечитать этот roadmap;
5. перечитать свежие #996, #1685, #1717, #1647;
6. #1759 читать только как dependency/current identity truth;
7. получить fresh INT head, open PR/CI/actions и active claims;
8. проверить, что shared path не имеет другого writer;
9. только после этого выбирать SAFE/MEDIUM пакет.

Правила supersession:

- исторический `next_action` не становится очередью автоматически;
- `completed`, `reserved` или `unknown` supplier operation не replay;
- новый supplier scenario получает новый `operation_id`, checkpoint и criteria digest;
- смена имени checkpoint не превращает replay в новую операцию;
- fresh issue/merged code/readback сильнее старого checkpoint;
- live/read-only execution подчиняется текущим owner rules, а не старому разрешению.

Уже закрытые исторические факты, которые **не открывать заново**:

- bug PHP `$out += [...]` исправлен; current diagnostic overwrite semantics корректны;
- старый Egypt-v3 case закрыт как historical; не replay;
- `PRICES[].id` подтверждён как полный `claiminc` для Andromeda package flow;
- legacy UNKNOWN `broninit` остаётся sealed/no-replay;
- `bron` и `bron_ticket` запрещены;
- historical identity counts не считать current truth — авторитетен fresh #1759/DB readback.

---

## 2. Current proven baseline

### 2.1 P0 money/fuel evidence

Turkey broad-v2 ANEX-only parity дал 6 exact aligned display tuples на current accepted triple identities:

- direct ANEX `search_price` == Andromeda ANEX-only `search_price` во всех 6;
- Tourvisor displayed/group price minus этот search price == Tourvisor `fuelCharge` во всех 6;
- это cohort evidence, а не универсальная формула;
- отсутствие fuel/additional поля = `unknown`, не `0`;
- совпадение цены не доказывает один package и не принимает mapping.

Canonical money факты всегда раздельны:

- `search_price` + currency;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`;
- source/method/timestamp/context provenance.

Ничего автоматически не складывать. Search/package/quote/final не fallback друг в друга.

### 2.2 Закрытые source semantics

Без нового regression evidence не открывать заново:

- date/nights/adults/children/ages;
- canonical meal key/families и qualifiers;
- availability с честным `unknown`;
- flight/detail capability flags;
- provider != operator;
- strict money provenance;
- `observed_at`/freshness;
- canonical offer envelope strictness/dedupe;
- direct ANEX saved concrete-offer read/context boundary;
- Andromeda durable quote no-replay/persisted-money validation;
- canonical verified-quote money handoff;
- exact quote provenance/context envelope.

### 2.3 Andromeda supplier/package contract

Подтверждено:

- `PRICES[].id` → полный `claiminc`;
- `broninit` = package composition/details, не booking;
- `get_flights` только когда он нужен selected-quote flow;
- auto-selection только при однозначной outbound+return паре;
- `changeservice`/`calc` относятся к quote/package verification;
- private claiminc/SID/UID не публикуются;
- typed transport retry не превращается в semantic replay;
- реальные заявки, `bron`, `bron_ticket` запрещены.

На одном явно выбранном fresh claim доказана read-only цепочка:

- `broninit`: `124864 RUB`;
- `get_flights`;
- 2× `changeservice` для единственной outbound+return пары;
- `calc`: `135643 RUB`;
- для **этого exact claim** `final_price_verified=true`.

Completed chain запечатан/no-replay. Он доказывает, что package/search price может отличаться от calc quote, но не доказывает fuel formula или универсальную арифметику.

### 2.4 Direct ANEX P4/P6 boundary

PR #2081 merged в INT как `e92253bbcadfc5228f3fa233f64be5cd1983228f`:

- существующий `ANYTOUR_ANEX_SEARCH3` хранит real random `search_ref`;
- opaque source-qualified `offer_ref`;
- исходные normalized facts;
- one-shot group expansion с durable unknown-before-transport checkpoint;
- supplier-free saved concrete-offer read;
- current mapping/catalog/generation/search/fixed-expiry guards;
- group minimum не может стать selected offer;
- private supplier IDs не публикуются.

Следующий direct ANEX шаг — только receiving handoff владельцу SEARCH. INT не редактирует SEARCH renderer/controller/UI.

### 2.5 Andromeda quote integrity и provider-neutral money

#2088 + #2090 закрыли semantic replay и corrupted completed-result reuse:

- `reserved` пишется до supplier call;
- `reserved/unknown` навсегда запрещают повтор;
- completed replay supplier-free и только после current-context revalidation;
- persisted `search/package/final` money повторно валидируется;
- malformed/zero/extra money keys и inconsistent quote/flight state fail closed.

#2092 merged как `510166858b4a4443e0fb2a869b92292cc2706aea`:

- `withVerifiedQuote()` принимает только canonical untampered Andromeda search-money state;
- `search_price`, optional `package_buyer_price`, `quote_price` остаются отдельными facts;
- sources: `andromeda_search` / `andromeda_package` / `andromeda_quote`;
- `final_price_verified=true` только по verified quote;
- `search_price_fuel_relation=unknown`;
- `arithmetic_applied=false`;
- Tourvisor/direct ANEX quote enrichment остаётся unsupported без отдельного supplier contract.

### 2.6 NEW: exact verified-quote provenance/context envelope

PR #2094 merged в INT как `62ec85a5143840ef62c0628fa56a814b2d81ddbd`.
Exact head `3ed213817ac1b7e01b11e274eea8a2d4b198fe2b`; focused run `34665514483` SUCCESS (54 checks), Security `34665514441` SUCCESS.

Теперь provider-neutral verified Andromeda quote может пересечь INT handoff только если:

- canonical search offer заново проходит retained-offer validation;
- retained envelope точно соответствует offer identity;
- current provider/operator/local-hotel + digest identity + generation/page совпадают;
- TTL ещё current;
- quote `provider/local/operator/search_price` совпадает с canonical offer;
- quote имеет только verified, no-flight-choice-required, booking-disabled state;
- package/final money имеют строгую `{amount,currency}` форму;
- canonical enrichment идёт только через #2092 money layer.

Output содержит digest-only offer identity/provenance + separate canonical money. Private supplier identities и flight payload не переходят этот boundary. `selection_state=disabled`, `booking_enabled=false`. Supplier/network/SSH/DB/mapping/booking/UI/production effects этого пакета = 0.

Это закрывает прежний P5/P6 gap «exact provenance/context envelope around canonical money handoff».

---

## 3. Исполняемый приоритет

`P0 money/fuel/additional → P1 observation matrix → P2 filter/property semantics → P4 canonical offer → P5 package/quote/final → P6 bounded SEARCH handoff → P7 release readiness`

P3 identity/matching = EXTERNAL и никогда не становится fallback-задачей INT.

Если текущий stage blocked, зафиксировать blocker и взять следующий независимый SAFE/MEDIUM INT package.

---

## P0 — money / fuel / additional

### P0.1 ANEX-only parity

Сравнивать только current accepted triple-mapped subject:

- direct ANEX;
- Andromeda `OPERATORS=5`;
- Tourvisor с ANEX в **исходном** request.

Tuple alignment только при одинаковых:

`current local identity + date + nights + party + canonical meal + room`.

`placement` хранить отдельно до доказанного parity.

### P0.2 Money state machine

Для каждого факта:

- state `known | unknown | unsupported | stale`;
- amount/currency только если `known`;
- source method/provider/operator;
- observed_at;
- search/selection lineage.

Особые правила:

- Tourvisor `fuelCharge` — отдельный reported fact;
- ANEX `AdditionalPricesDaily` — отдельный fact только после exact contract/current allowance;
- Andromeda search не получает synthetic fuel=0;
- agency cost и buyer/customer money не смешивать;
- protected price arithmetic не менять без отдельного доказанного contract + owner decision.

### P0.3 Новый sample

Новый supplier sample только если он приносит новый evidence и не replay. Приоритет:

1. новая future date/party/nights на triple-mapped hotel;
2. новый room/placement;
3. другая core8 country с verified filter/identity mapping;
4. family case;
5. ещё не наблюдавшийся additional/fuel state.

UNKNOWN/reserved/completed criteria не использовать повторно.

---

## P1 — read-only search matrix + observation ledger

Каждый разрешённый search должен дать минимум одно:

- новую price observation;
- materially different scenario;
- новые external hotel IDs.

На один run максимум **3** новых supplier scenarios и только при зелёных applicable CI/budgets.

Core8: Turkey, Egypt, Thailand, Maldives, UAE, Cuba, Sri Lanka, Vietnam.

Разнообразить:

- future dates;
- nights 6/7/8/10/12/14;
- adults 1/2/3;
- family 2+1, затем 2 children с ages;
- AI baseline;
- broad и hotel-scoped;
- meals/stars/regions только после verified mapping semantics.

Observation, когда реально доступно:

- provider + operator;
- country/region/subregion/geography;
- external hotel id;
- current `local_id` или null;
- raw name, stars/category, coords;
- criteria/date/nights/party/ages;
- raw+normalized meal/room/placement;
- availability/flight flags;
- search price/currency;
- fuel/additional states отдельно;
- observed_at + source lineage.

`local_id=null` → evidence в #1759/#996; никаких mapping writes.

Каждый scenario получает `operation_id`, checkpoint path, scenario revision, criteria digest, source SHA.

---

## P2 — canonical filter/property matrix

Никакой raw numeric supplier ID не универсален. Статусы только:

`verified | local_only | unsupported | unknown`.

| Family | Tourvisor | direct ANEX | Andromeda | Rule |
| --- | --- | --- | --- | --- |
| departure | verified | dictionary mapping | dictionary mapping | provider-specific IDs |
| country | verified | dictionary mapping | saved catalog mapping | provider-specific IDs |
| region/resort | local/catalog | partial | limited/verify | no upstream without mapping |
| subregion | local/catalog | partial | limited/verify | separate semantics |
| hotel | local ID | accepted mapping/observation | accepted mapping/observation | P3 writes external |
| stars/category | local | supplier label | catalog label/verify | semantic, not raw key |
| rating | local | local_only | local_only | no upstream promise |
| meal families | verified | verified families only | verified families only | raw + canonical key |
| room | offer text | offer text | offer text | raw + normalized |
| placement | offer text | offer text | partial/gap | keep separate |
| operator | TV operator | ANEX | Andromeda raw/canonical evidence | operator != provider |
| hotel services/types | local | unsupported upstream | unsupported upstream | local_only |
| arrival airport | capability/local | unknown until proven | unknown until proven | no invention |
| direct/charter | TV capability | unknown until proven | unknown until proven | capability evidence |
| dates/nights | verified | verified | verified | closed family |
| adults/children/ages | verified | verified | verified | closed family |
| price/currency | source | source + local post-filter | source + local post-filter | money provenance |
| availability | source | source/unknown | source/unknown | raw + canonical state |
| flights/baggage | details | separate details | selected quote methods | optional detail DTO |
| fuel/additional | reported separately | separate method | package/quote separate | P0/P5 |

Один PR = одно bounded field family, без broad provider rewrite.

---

## P3 — EXTERNAL hotel identity/matching

Не исполняется этим автопилотом.

INT может только:

1. читать fresh #1759/DB baseline;
2. использовать accepted current local identity;
3. сохранять observation с `local_id=null`;
4. передавать external-id evidence в #1759/#996.

INT не может принимать mapping, менять matcher/writer/manual/conflict queue или запускать bulk reconciliation. Price similarity не является identity proof.

---

## P4 — canonical offer contract

Provider-neutral offer сохраняет:

- provider отдельно от operator;
- current local hotel identity или null observation state;
- digest/public-safe provider offer identity только по разрешённому contract;
- date/nights/party/ages;
- raw+normalized meal/room/placement;
- availability + raw evidence;
- flight/detail capability;
- search price/currency;
- fuel/additional отдельно;
- package/quote/final states отдельно;
- observed_at/freshness;
- search/selection context guards.

Private supplier offer ID, claiminc, SID/UID, credentials и agency-only money не публиковать.

Новый search инвалидирует stale provider context. A→B race не может вернуть A result в B context.

SEARCH renderer/controller не менять. INT готовит contract, DTO, fixtures и evidence.

---

## P5 — package / quote / final

Разрешённая модель Andromeda:

`selected source offer → broninit → get_flights only if required → unique flight pair only → changeservice if required → calc`

На каждом шаге:

- current/stale context guard;
- private IDs server-side;
- sanitized state;
- typed status;
- durable no-replay;
- no booking/application.

Exact completed 124864→135643 case не повторять.

#2092 + #2094 закрывают canonical verified-money + exact-context handoff source-side. Следующая P5 работа допускается только если появится **новый** независимый supplier-contract gap/evidence; не создавать второй quote path и не запускать новый live quote только ради дополнительного sample.

UI acceptance принадлежит SEARCH.

---

## P6 — bounded handoff в SEARCH

INT не владеет UI. Current source-side prerequisite теперь закрыт для:

- direct ANEX saved concrete offer/context (#2081);
- Andromeda durable verified quote (#2088/#2090);
- canonical separate money facts (#2092);
- exact offer+quote current-context envelope (#2094).

**Следующий безопасный INT package:** provider-neutral handoff acceptance fixtures/contract matrix, без нового supplier call и без SEARCH-owned source edits.

P6 fixture matrix должна покрыть минимум:

- Tourvisor search offer без synthetic quote;
- direct ANEX group minimum vs concrete saved offer;
- Andromeda search offer → current verified quote envelope;
- provider != operator;
- unknown/unavailable quote;
- A→B selection race;
- stale/expired context;
- provider failure isolation;
- canonical money separation;
- sanitized payload без private supplier IDs;
- selection/booking disabled на INT boundary.

Receiving SEARCH owner сам подключает contract к renderer/controller/selected state и проходит свои publication/browser gates. INT не меняет эти файлы.

---

## P7 — release readiness

Перед release handoff:

1. fresh INT/release heads;
2. focused/contract tests green;
3. Security/applicable CI green;
4. supplier no-replay/budget guards intact;
5. money semantics documented;
6. filter matrix актуальна;
7. P3 остаётся внешним dependency без hidden writes;
8. preview publication/readback только по current exact control;
9. no lead/Metrika/Tourvisor protected-contract drift;
10. rollback/provenance известны;
11. production migration только отдельным owner approval.

---

## 4. Execution loop

Для каждого пакета:

1. выбрать один bounded SAFE/MEDIUM INT package по P0→P1→P2→P4→P5→P6→P7;
2. если stage blocked — записать точный blocker и взять независимый следующий INT package;
3. short branch от fresh INT base;
4. exact claim в #996: issue/branch/owned paths/dependencies/risk;
5. один writer на shared path;
6. минимальная реализация;
7. focused tests;
8. Security + applicable CI;
9. merge в INT только green;
10. live/read-only execution только если current owner rules разрешают;
11. после merge продолжить следующий safe step;
12. обновить этот roadmap свежим confirmed state;
13. владельцу сообщать только substantive result/blocker/proven semantic change.

Без force-push. Не перетирать чужие файлы. Не создавать PR ради пустого checkpoint churn.

---

## 5. Stop gates

Отдельное разрешение обязательно для:

- production/main вне exact previously allowed preview-control;
- `bron`, `bron_ticket`, реальной заявки/бронирования;
- изменения Tourvisor protected URL/payload/price arithmetic;
- lead contract/transport/field mapping;
- Metrika/goals/analytics;
- production SEO;
- server/platform config;
- irreversible schema/data operation;
- ослабления manual/pair/conflict guards;
- нового HIGH-risk supplier action.

SAFE/MEDIUM source-only INT packages продолжаются автономно.

---

## 6. Definition of useful progress

Пакет полезен только если даёт минимум одно:

- новый доказанный money/fuel/additional fact;
- новый non-replay observation scenario;
- external-hotel evidence handoff без mapping write;
- закрытое filter/property family;
- усиленный canonical offer/quote guard;
- подтверждённый package/quote state без booking;
- bounded SEARCH handoff fixture;
- устранённый stale-state/unsafe-replay риск.

Не присылать неизменившиеся почасовые отчёты.
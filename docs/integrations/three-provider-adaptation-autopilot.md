# AnyTour INT — автономная адаптация Tourvisor + direct ANEX + Andromeda

Дата актуализации: 2026-09-12. Репозиторий: только `pyatkoff/poisk-turov-test`.
INT-база: свежая `feature/anex-search-adapter-20260907`.
Координация: #996. Рабочие issues: #1685, #1717, #1647. #1759 — только внешний identity dependency.

Этот документ — исполняемый roadmap INT/data/API. Он не заменяет `AGENTS.md`, release `docs/project/anytour-development.md` и scoped `docs/integrations/anex-search3-autopilot.md`. Главный принцип: **свежий подтверждённый state всегда сильнее исторического checkpoint/next_action внутри документа или старого задания**.

## 0. Scope и владельцы

INT владеет только supplier/data/API частью:

- transport/auth/read-only search;
- provider dictionaries и честная filter semantics;
- observation ledger;
- money/price/fuel/additional evidence;
- canonical provider-neutral offer DTO/fixtures;
- Andromeda package/quote source pipeline в разрешённых границах;
- bounded handoff contract для SEARCH без владения UI.

Не брать из этой очереди:

- **P3 hotel matching / identity reconciliation — EXTERNAL, #1759**;
- SEARCH #1646 renderer/controller/UI;
- SITE #1719;
- SEO #1720;
- lead transport/field mapping;
- Metrika/goals/analytics;
- production SEO;
- server/platform config;
- Tourvisor protected URL/payload/price arithmetic.

Для #1759 разрешено только читать свежий baseline и передавать evidence о новых external hotel IDs. Запрещены mapping writers, acceptance queues, bulk reconciliation и ослабление manual/pair/conflict guards.

---

# 1. Fresh-state / supersession guard

Перед **каждым** автономным запуском:

1. перечитать свежие `AGENTS.md`;
2. перечитать `docs/project/anytour-development.md` из release;
3. перечитать scoped `docs/integrations/anex-search3-autopilot.md`;
4. перечитать этот roadmap;
5. перечитать свежие #996, #1685, #1717, #1647;
6. #1759 читать только как current identity baseline/dependency;
7. получить fresh INT head, open PR/CI/actions и active claims;
8. убедиться, что shared path не имеет другого writer;
9. только после этого выбирать пакет.

### Правила supersession

- Исторический `next_action` не становится очередью автоматически.
- `completed`, `reserved` или `unknown` supplier operation не replay.
- Если старое задание противоречит свежему issue/merged code/readback, старый текст считается historical snapshot и **не исполняется**.
- Новый алгоритм/новая попытка supplier scenario = новый `operation_id`, новый checkpoint и новые критерии. Нельзя маскировать replay переименованием checkpoint.
- Любая live/read-only supplier execution должна соответствовать **текущим** owner rules, а не старому разрешению из checkpoint.

### Уже superseded факты — не открывать заново

1. Исторический diagnostic bug `PHP $out += [...]` уже исправлен: текущий runner использует overwrite-семантику (`array_replace`) для `status/offers/details`. Не создавать второй PR «на исправление» этой ошибки.
2. Старый Egypt-v3 diagnostic case считать закрытым историческим case. После bugfix не replay; следующий P0/P1 test обязан иметь новые criteria/operation/checkpoint.
3. Старое ожидание ответа SAMO по `PRICES[].id`/`claiminc` **снято свежим подтверждённым контрактом**: `PRICES[].id` является полным `claiminc` для соответствующего package flow. Исторический UNKNOWN `broninit` при этом остаётся sealed/no-replay.
4. `bron` и `bron_ticket` по-прежнему запрещены. Подтверждение `claiminc` не является разрешением бронировать или отправлять реальную заявку.
5. Фиксированные identity counts ниже/в старых комментариях — только historical baseline. Свежий #1759/DB readback авторитетнее и никогда не модифицируется из INT.

---

# 2. Доказанный current baseline

## 2.1 Money / P0 evidence

Свежая доказательная выборка Turkey broad-v2 для ANEX-only parity дала 6 exact aligned display tuples на current accepted triple identities:

- direct ANEX `search_price` совпал с Andromeda ANEX-only `search_price` во всех 6;
- разница Tourvisor displayed/group price и этих search prices совпала с Tourvisor `fuelCharge` во всех 6;
- это **не** доказывает общий supplier package и **не** разрешает менять price arithmetic;
- отсутствие fuel/additional поля = `unknown`, не `0`.

Следовательно текущая модель обязана хранить отдельно:

- `search_price` + currency;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`;
- source/method/timestamp/provenance каждого денежного факта.

Ничего из этих полей автоматически не складывать.

## 2.2 Уже закрытые P2/P4 пакеты

Не открывать заново без regression evidence:

- date/nights/adults/children/ages semantics;
- AI meal semantics + canonical `meal_key`;
- availability с честным `unknown`;
- flight/detail capability flags;
- `provider != operator` и operator evidence;
- strict money provenance;
- `observed_at` / freshness evidence;
- canonical envelope strictness/dedupe guards.

## 2.3 Andromeda P5 supplier contract

Подтверждено и уже отражено в source guards:

- `PRICES[].id` → полный `claiminc`;
- `broninit` используется только как package composition/details step, не как booking;
- private claiminc/SID/UID не публикуются в browser DTO;
- typed transport retry не превращается в semantic replay;
- `get_flights` вызывается только когда он действительно нужен source pipeline;
- auto-selection flights допустим только при однозначной outbound+return паре;
- `changeservice`/`calc` относятся к quote/package verification, не к booking;
- legacy UNKNOWN operation не replay;
- реальные заявки, `bron`, `bron_ticket` запрещены.

Новый live package/quote capture выполняется только по текущему exact owner-control/allowlist. Source-only SAFE/MEDIUM пакеты могут продолжаться независимо.

## 2.4 Current P5/P6 execution evidence — не повторять и не обобщать

На одном свежем явно выбранном Andromeda claim уже доказана полная read-only цепочка без booking:

- `broninit`: run `34605764015`, исходная выбранная туристическая цена `124864 RUB`;
- `get_flights`: run `34607247181` SUCCESS;
- ровно требуемая outbound+return пара выбрана через 2× `changeservice`: run `34608731086` SUCCESS;
- `calc`: run `34609344978` SUCCESS;
- exact-claim tourist buyer price изменилась `124864 → 135643 RUB`;
- для **этого конкретного claim** `final_price_verified=true`.

Это доказывает рабочий порядок `broninit → get_flights → changeservice → calc` для данного external-flight case и доказывает, что search/package price может отличаться от итоговой calc price. Это **не** универсальная формула, не fuel arithmetic и не разрешение автоматически складывать сборы. Этот completed chain не replay.

Direct ANEX P4/P6 common-session boundary также уже source-complete: PR #2081 merged в INT как `e92253bbcadfc5228f3fa233f64be5cd1983228f`. Существующий `ANYTOUR_ANEX_SEARCH3` хранит реальные random `search_ref`, opaque source-qualified `offer_ref`, исходные normalized facts, one-shot group expansion с durable unknown-before-transport checkpoint и supplier-free saved concrete-offer read. Current mapping/catalog/generation/search/fixed-expiry guards обязательны; group minima не становятся selected offer; private supplier IDs не публикуются. Source merge не означает preview publication или SEARCH acceptance.

Следующий P6 шаг по direct ANEX — только bounded receiving handoff владельцу SEARCH с сохранением этих refs/expansion/details contract. INT не редактирует SEARCH renderer/controller/UI и не обходит отдельные SEARCH gates.

---

# 3. Приоритет очереди

Исполняемый порядок:

`P0 money/fuel/additional → P1 observation search matrix → P2 filter/property semantics → P4 canonical offer contract → P5 package/quote/final → P6 bounded SEARCH handoff → P7 release readiness`

**P3 identity/matching = EXTERNAL** и не участвует в выборе INT-пакета. Если INT упирается в unmapped hotel, он сохраняет evidence и передаёт его в #1759/#996, после чего берёт независимый INT-пакет.

---

# Этап P0 — цена, fuel и additional

## P0.1 ANEX-only parity

Сравнивать только current accepted triple-mapped subject:

- direct ANEX;
- Andromeda с `OPERATORS=5`;
- Tourvisor с ANEX в **исходном** supplier request.

Сравнение допустимо только при одинаковых:

`current local identity + date + nights + party + meal + room`.

`placement` хранить отдельно, пока parity не доказан. Совпадение цены не доказывает один package и не принимает mapping.

## P0.2 Money state machine

Для каждого provider money fact хранить минимум:

- state: `known | unknown | unsupported | stale`;
- amount/currency, только если known;
- source method;
- supplier/operator context;
- observed_at;
- search/selection lineage.

Правила:

- Tourvisor `fuelCharge` — отдельный reported fact;
- ANEX `AdditionalPricesDaily` — отдельный fact только после exact contract/разрешённого вызова;
- Andromeda search money не получает synthetic fuel=0;
- agency cost и buyer/customer money никогда не fallback друг в друга;
- защищённую price arithmetic не менять в этой очереди без отдельного доказанного контракта и owner decision.

## P0.3 Следующие samples

Новый sample разрешён только если он добавляет evidence и не является replay. Приоритет:

1. новый future date/party/nights на triple-mapped hotel;
2. новый room/placement;
3. другая core8 country с verified identity/filter mapping;
4. family case;
5. additional/fuel state, который ещё не наблюдался.

UNKNOWN/reserved supplier case не повторять.

**P0 DONE** только когда search/fuel/additional/package/quote semantics документированы раздельно и есть sanitized reproducible samples. Это не означает автоматического включения новой арифметики.

---

# Этап P1 — read-only search matrix и observation ledger

Каждый разрешённый supplier search обязан принести хотя бы одно:

- новую price observation;
- новый materially different scenario;
- новые external hotel IDs.

Почти идентичный запрос ради отчёта запрещён.

## P1.1 Observation contract

Сохранять, когда реально доступно:

- provider + operator;
- country/region/subregion/geography;
- external hotel id;
- current `local_id` или `null`;
- raw hotel name;
- stars/category;
- coordinates;
- search criteria;
- date/nights/party/child ages;
- raw+normalized meal/room/placement;
- availability/flight flags;
- search price/currency;
- fuel/additional states отдельно;
- observed_at + source request lineage.

Для `local_id=null` никаких mapping writes. Evidence отправляется #1759/#996 с provider/country/external id/name/geography/star/coords/criteria, если эти поля фактически были получены.

## P1.2 Scenario diversity

Core8 сначала: Turkey, Egypt, Thailand, Maldives, UAE, Cuba, Sri Lanka, Vietnam.

Разнообразить:

- future dates;
- nights: 6/7/8/10/12/14;
- adults: 1/2/3;
- family: 2+1, затем 2 children с ages;
- AI baseline;
- другие meals/stars/regions только после verified mapping;
- broad и hotel-scoped cases.

За один run — максимум **3 новых supplier search scenarios**, и только если предыдущие cases не `unknown/reserved`, supplier budgets в норме и применимый CI green.

## P1.3 Operation identity

Каждый новый supplier scenario получает явные:

- `operation_id`;
- `checkpoint_path`;
- scenario revision;
- exact criteria digest;
- source head SHA.

Если criteria/operation уже sealed как completed/unknown/reserved — не использовать повторно.

---

# Этап P2 — canonical filter/property matrix

Никакой raw numeric supplier ID не универсален.

Статусы только:

`verified | local_only | unsupported | unknown`.

| Canonical family | Tourvisor | direct ANEX | Andromeda | Правило |
| --- | --- | --- | --- | --- |
| departure | verified baseline | dictionary mapping | dictionary mapping | provider-specific IDs |
| country | verified baseline | dictionary mapping | saved catalog mapping | provider-specific IDs |
| region/resort | local/catalog | partial projection | limited/verify | не отправлять upstream без mapping |
| subregion | local/catalog | partial projection | limited/verify | отдельная semantics |
| hotel | local ID | accepted mapping/observation | accepted mapping/observation | P3 writes external |
| stars/category | local catalog | supplier label | catalog label/verify | semantic, не raw key |
| rating | local | local_only | local_only | не обещать upstream |
| meal families | TV/local | verified families only | verified families only | raw + canonical key |
| room | offer text | offer text | offer text | raw + normalized |
| placement | offer text | offer text | partial/gap | raw отдельно до parity |
| operator | TV operator | ANEX | Andromeda operator | operator != provider |
| hotel services | Search3/local | unsupported upstream | unsupported upstream | local_only |
| hotel types | Search3/local | unsupported upstream | unsupported upstream | local_only |
| arrival airport | TV/Search3 | unknown/unsupported until proven | unknown/unsupported until proven | не выдумывать |
| direct flight | TV capability | unknown/unsupported until proven | unknown/unsupported until proven | capability evidence |
| charter | TV capability | unknown/unsupported until proven | unknown/unsupported until proven | capability evidence |
| dates/nights | verified | verified | verified | current closed family |
| adults/children/ages | verified | verified | verified | current closed family |
| price/currency | verified/source | source + local post-filter | source + local post-filter | money provenance |
| availability | verified/source | source/unknown | source/unknown | enum + raw evidence |
| flights/baggage | details | separate details | separate methods | optional detail DTO; Andromeda get_flights execution proven only for selected quote path |
| fuel/additional | separate reported field | separate method/evidence | search unknown; package/quote separate | P0/P5 |

Каждый PR — одно небольшое семейство полей. Не делать broad provider rewrite.

---

# Этап P3 — EXTERNAL hotel identity/matching

**Не исполняется этим автопилотом.**

INT имеет право только:

1. прочитать fresh #1759/DB identity baseline;
2. использовать уже accepted current local identity;
3. записать observation с `local_id=null`;
4. передать evidence/priority external IDs в #1759/#996.

INT не имеет права:

- принимать mapping;
- менять matcher/mapping writer;
- менять acceptance/manual/conflict queue;
- запускать bulk reconciliation;
- использовать price similarity как identity proof.

Historical counts (3417 all-three, 11762 exactly-two, 11667 ANEX unique local, 6929 Andromeda unique local) — только snapshot начала плана. Никогда не использовать их как current truth без fresh readback.

---

# Этап P4 — canonical offer contract

Provider-neutral DTO обязан сохранять:

- provider отдельно от operator;
- current `local_hotel_id` или null observation state;
- public-safe provider offer reference, если контракт позволяет;
- date/nights/party/child ages;
- raw+normalized meal;
- raw+normalized room;
- raw+normalized placement;
- availability + raw evidence;
- flight/detail capability;
- search price + currency;
- fuel/additional states отдельно;
- package/quote/final states отдельно;
- observed_at/freshness;
- search/selection context guard.

Private supplier offer identity, claiminc, SID/UID, credentials и agency-only money не публиковать.

Новый поиск инвалидирует stale provider context. A→B rapid selection не может вернуть A-result в B-context.

SEARCH renderer/controller не менять. INT готовит bounded DTO, fixtures, contract tests и handoff evidence.

---

# Этап P5 — package/quote/final price

P5 больше **не** заблокирован старым вопросом `PRICES[].id`/`claiminc`; supplier contract подтверждён. Но это не отменяет live guards.

Разрешённая модель:

`selected source offer → broninit(package composition) → get_flights only if required → unambiguous flight selection only → changeservice if contract requires → calc quote`

На каждом шаге:

- stale/current context guard;
- private identifiers server-side only;
- sanitized artifact;
- typed status;
- no semantic replay;
- no booking/application action.

`quote_price` ≠ `search_price` ≠ `final_price_verified` по умолчанию.

Price change contract должен уметь вернуть:

- old/new;
- delta;
- currency;
- timestamp;
- source/operator;
- status `unchanged | changed | unavailable | unknown`.

Exact selected-claim chain из §2.4 уже доказал изменение `124864 → 135643 RUB` после выбора обязательных рейсов и `calc`. Не запускать его повторно ради подтверждения. Следующая source задача — использовать уже существующий private verified-quote contract/handoff и сохранять exact-claim provenance; не превращать один sample в общую price arithmetic.

Включение UI acceptance принадлежит SEARCH.

Любой новый live package/quote capture — только по current exact owner-control. `bron`, `bron_ticket`, реальная заявка — стоп-гейт.

---

# Этап P6 — bounded handoff в SEARCH

INT не владеет UI. Готовится только stable handoff contract для:

`search → offer select → package/quote check → changed-price state → existing lead handoff`.

INT acceptance fixtures должны покрывать:

- Tourvisor offer;
- direct ANEX offer;
- Andromeda offer;
- operator provenance;
- unavailable/unknown quote;
- A→B selection race;
- stale/expired context;
- provider failure isolation;
- sanitized payload без private supplier IDs.

Direct ANEX INT-side common-session prerequisite закрыт #2081: SEARCH-получатель должен сохранить `search_ref`/`offer_ref`, явно вызывать expansion/concrete saved read и не подменять group minimum выбранным туром. Это handoff evidence, а не разрешение INT менять SEARCH-owned renderer/controller/UI.

SEARCH #1646 принимает этот contract отдельно своим owner/writer.

---

# Этап P7 — release readiness

До release handoff:

1. fresh INT/release heads;
2. contract/focused tests green;
3. Security/applicable CI green;
4. supplier no-replay/budget guards intact;
5. money semantics documented;
6. canonical filter matrix актуальна;
7. P3 остаётся внешним dependency без скрытых writes;
8. preview publication/readback только по текущему exact control;
9. no lead/Metrika/Tourvisor protected contract drift;
10. rollback/provenance известны;
11. production migration — только отдельный owner approval.

---

# 4. Автономный execution loop

Для каждого пакета:

1. выбрать один bounded SAFE/MEDIUM INT-пакет по P0→P1→P2→P4→P5→P6→P7;
2. если stage blocked — записать точный blocker и взять независимый следующий INT-пакет;
3. создать fresh short branch от текущей INT-базы;
4. в #996 объявить exact issue/branch/owned paths/dependencies/risk;
5. один writer на shared path;
6. реализовать минимальный пакет;
7. focused tests;
8. Security и применимый CI;
9. merge в INT только при green;
10. supplier/live execution — только если разрешено current owner rules;
11. после merge продолжить следующий безопасный шаг, если он есть;
12. сообщать владельцу только новый существенный результат, точный blocker/решение или доказанное изменение money/filter semantics.

Не делать force-push и не перетирать чужие файлы.

---

# 5. Risk / stop gates

Отдельное разрешение обязательно для:

- production/main вне уже явно разрешённого exact preview-control;
- `bron`, `bron_ticket`, реальной заявки/бронирования;
- изменения Tourvisor protected URL/payload/price arithmetic;
- lead contract/transport/field mapping;
- Metrika/goals/analytics;
- production SEO;
- server/platform config;
- irreversible schema/data operation;
- ослабления manual/pair/conflict guards;
- нового HIGH-risk supplier action.

SAFE/MEDIUM source-only INT-пакеты продолжаются автономно.

---

# 6. Definition of useful progress

Пакет считается полезным, только если даёт минимум одно:

- новый доказанный money/fuel/additional fact;
- новый non-replay observation scenario;
- новый external-hotel evidence handoff без mapping write;
- закрытое семейство filter/property semantics;
- усиленный canonical offer contract/guard;
- подтверждённый package/quote state без booking;
- bounded handoff fixture для SEARCH;
- устранённый stale-state/unsafe-replay риск.

Не создавать PR только ради обновления checkpoint текста без нового state/guard. Исключение — docs change, который предотвращает реальный unsafe replay или ownership conflict, как этот fresh-state guard.

# AnyTour INT — Tourvisor + direct ANEX + Andromeda

Актуализировано: 2026-09-12. Репозиторий: только `pyatkoff/poisk-turov-test`.
INT-база: свежая `feature/anex-search-adapter-20260907`. Координация: #996.
Рабочие issues: #1685, #1717, #1647. #1759 — только внешний identity dependency.

Этот файл — исполняемый INT/data/API roadmap. Свежие issue, merged code, CI и readback сильнее любого исторического checkpoint/next_action.

## 1. Scope и обязательный pre-run

Перед каждым запуском перечитать свежие:

1. `AGENTS.md`;
2. release `docs/project/anytour-development.md`;
3. `docs/integrations/anex-search3-autopilot.md`;
4. этот roadmap;
5. #996, #1685, #1717, #1647;
6. #1759 только как current accepted identity baseline/dependency;
7. fresh INT head, open PR/CI/actions и active claims.

INT владеет supplier transport/auth/read-only search, dictionaries/filter semantics, observation ledger, money/fuel/additional evidence, provider-neutral offer/quote/handoff contracts и разрешённой Andromeda package/quote source pipeline.

Не брать из этой очереди:

- P3 hotel matching / identity reconciliation — EXTERNAL (#1759);
- mapping writers, acceptance/manual/conflict queues, bulk reconciliation;
- SEARCH #1646 renderer/controller/UI;
- SITE #1719 и SEO #1720;
- lead transport/field mapping, Metrika/goals/analytics;
- production SEO, server/platform config;
- Tourvisor protected URL/payload/price arithmetic.

Один writer на shared path, short branch от fresh base, exact claim в #996, focused tests + Security/applicable CI, merge только green, без force-push. SAFE/MEDIUM source-only пакеты продолжаются автономно. HIGH-risk новое действие требует отдельного допуска.

## 2. No-replay и stop guards

- `completed`, `reserved`, `unknown` supplier operation не replay.
- Новый supplier scenario = новый `operation_id`, checkpoint, criteria digest и source SHA.
- Переименование checkpoint не делает replay новой операцией.
- Legacy UNKNOWN `broninit` sealed/no-replay.
- `bron` и `bron_ticket` запрещены; реальные заявки/бронирования запрещены.
- Старый Egypt-v3 case historical/no-replay.
- PHP diagnostic `$out += [...]` bug уже исправлен; второй fix не создавать.
- `PRICES[].id` подтверждён как полный `claiminc` для Andromeda package flow.
- Historical identity counts не current truth; свежий #1759/DB readback авторитетнее.

## 3. Current proven money semantics

Turkey broad-v2 ANEX-only parity дал 6 exact aligned tuples на current accepted triple identities:

- direct ANEX `search_price` == Andromeda `OPERATORS=5` `search_price` во всех 6;
- Tourvisor displayed/group price minus этот search price == Tourvisor `fuelCharge` во всех 6;
- это cohort evidence, не универсальная формула и не proof одного package.

Money facts всегда раздельны:

- `search_price` + currency;
- `fuel_charge_reported`;
- `additional_prices_reported`;
- `package_buyer_price`;
- `quote_price`;
- `final_price_verified`;
- source/method/timestamp/context provenance.

Отсутствующий fuel/additional = `unknown`, не `0`. Ничего автоматически не складывать. Agency cost и customer money не fallback друг в друга. Protected price arithmetic не менять.

Для одного exact Andromeda claim доказана и уже sealed цепочка:

`broninit 124864 RUB → get_flights → unique outbound+return changeservice → calc 135643 RUB`.

`final_price_verified=true` относится только к этому exact claim. Это доказывает, что package/search и calc quote могут отличаться, но не доказывает fuel formula.

## 4. Закрытые source-side контракты — не делать второй слой

Без regression evidence не открывать заново:

- dates/nights/adults/children/ages;
- canonical meal key/families/qualifiers;
- availability с честным `unknown`;
- room/placement raw+normalized semantics;
- flight/detail capability flags;
- provider != operator;
- strict money provenance и `observed_at`;
- canonical offer envelope/dedupe/context guards;
- air-search capability boundary: catalog/discovery evidence отдельно от upstream supplier-search filtering;
- stars/category evidence boundary: supplier label observational only, canonical category только через current accepted local identity;
- hotel rating: local-only canonical fact, supplier score observational only;
- hotel services/amenities: local-only canonical list, supplier labels/codes observational only;
- hotel types: local-only canonical list, supplier labels/codes observational only.

### Direct ANEX

#2081 merged `e92253bbcadfc5228f3fa233f64be5cd1983228f`:

- real random `search_ref` + opaque source-qualified `offer_ref`;
- original normalized facts retained;
- one-shot group expansion с durable unknown-before-transport guard;
- supplier-free saved concrete-offer read;
- current mapping/catalog/generation/search/fixed-expiry guards;
- group minimum нельзя выдать за concrete selected offer;
- private supplier IDs не публикуются.

Дальнейшее receiving wiring принадлежит SEARCH; INT не меняет renderer/controller/UI.

### Andromeda quote integrity

#2088/#2090:

- durable `reserved` до supplier call;
- `reserved/unknown` запрещают semantic replay;
- completed read supplier-free и только после current-context revalidation;
- persisted search/package/final money и quote/flight state повторно валидируются;
- malformed/zero/extra money fields fail closed.

#2092 merged `510166858b4a4443e0fb2a869b92292cc2706aea`:

- canonical search/package/quote money остаются раздельными;
- `final_price_verified=true` только через verified Andromeda quote;
- `search_price_fuel_relation=unknown`;
- `arithmetic_applied=false`;
- Tourvisor/direct ANEX quote enrichment unsupported без отдельного доказанного contract.

#2094 merged `62ec85a5143840ef62c0628fa56a814b2d81ddbd`:

- verified quote должен совпасть с exact current retained provider/operator/local hotel + digest identity + generation/page + non-expired TTL;
- quote local/operator/search money обязаны совпасть с canonical offer;
- принимается только quote_verified/final verified/no-flight-choice-required/booking-disabled state;
- output содержит digest-only provenance и separate canonical money;
- private claim/session/offer IDs и flight payload не переходят boundary;
- selection/booking остаются disabled/false.

Focused run `34665514483` SUCCESS (54), Security `34665514441` SUCCESS.

### Air search/filter semantics

#2100 merged `bdaeb8856fffff4018d8ed154bb8d73a705d8abf`; exact PR head `c6c9ab2f0849f64e1547e7bb8484bd3e17dd8010`.
Focused run `34668203993` SUCCESS: 114 checks. Security `34668204017` SUCCESS.

Canonical P2 boundary для `arrival_airport`, `direct_flight`, `charter` теперь явный:

- Tourvisor current source имеет **verified catalog/discovery evidence**: существующий arrivals catalog и `onlyDirect`/`onlyCharter` catalog paths;
- это доказательство относится только к catalog/discovery и **не** разрешает автоматически добавлять/менять protected Tourvisor tour-search payload;
- upstream supplier-search filter status для всех трёх families пока `unknown`, forwarding fail-closed;
- direct ANEX и Andromeda catalog/search-filter capability остаётся `unknown`, пока не доказан provider-specific contract;
- raw numeric airport/supplier IDs не универсальны;
- cross-provider equivalence=false.

Не создавать второй air-filter contract ради тех же статусов. Новое evidence может только безопасно повысить конкретный provider/family status отдельным bounded пакетом.

### Hotel stars/category semantics

#2103 merged `89a530a81b68bd9518913432345369e2771e101d`; exact PR head `006756aa2631fa3a1b6bb8920ebc396990b094f5`.
Focused run `34668469610` SUCCESS: 57 checks. Security `34668469602` SUCCESS.

Canonical P2 boundary для stars/category:

- provider supplier `star` / category label сохраняется только как bounded raw observation;
- numeric-looking supplier label вроде `5` не преобразуется автоматически в canonical local category;
- canonical category `1..5` имеет status `verified` только когда она приходит вместе с явно current accepted local identity и source=`current_local_identity`;
- supplier-label equivalence не доказана;
- raw supplier numeric IDs не универсальны;
- cross-provider category equivalence=false;
- совпадение категории/звёздности никогда не является hotel identity proof и не запускает mapping write.

Не создавать второй category wrapper ради тех же правил. Новое supplier evidence может повышать только явно доказанный provider-specific capability, не identity authority.

### Hotel rating semantics

#2107 merged `1bbf4e538115a29c10c7c539d783552cdc6827d5`; exact PR head `bacbd6023bee5bb19084b8fdf33b1fbcd01c2221`.
Focused run `34671228420` SUCCESS: 69 checks. Security `34671228371` SUCCESS.

Canonical P2 boundary для customer/hotel rating:

- rating остаётся local Search3/catalog facet;
- canonical rating имеет status `verified` только из current accepted local identity и валидного local `>0..5` значения;
- supplier/raw score (`4.7`, `9.2/10`, label и т.п.) сохраняется только как bounded observation;
- supplier rating нельзя автоматически масштабировать/нормализовать в local rating;
- generic upstream supplier filter запрещён (`local_only`, `allowed=false`);
- numeric-looking supplier score не универсален;
- cross-provider equivalence=false;
- совпадение rating никогда не является hotel identity proof.

### Hotel services/amenities semantics

#2108 merged `c095df6b5094e442a3e0066121d5df2f66bc2992`; exact final PR head `9e4b7aa95897a2f9ddf3e3e065df8d9ecfe35135`.
Focused run `34673612499` SUCCESS: 67 checks. Aggregate `34673612490` SUCCESS. Security `34673612515` SUCCESS.

Canonical P2 boundary для hotel services/amenities:

- canonical services — local Search3/catalog list и имеют status `verified` только при current accepted local identity и явно предоставленном local list;
- explicit local empty list отличается от unknown/null;
- supplier labels/codes/lists сохраняются только как bounded observations;
- supplier service code/ID не универсален и не переводится автоматически в local service;
- cross-provider equivalence=false;
- generic upstream supplier filtering запрещён (`local_only`, `allowed=false`);
- service similarity никогда не является hotel identity proof и не создаёт mapping write.

Первый aggregate CI на PR корректно обнаружил PHP key coercion numeric-string supplier code (`"77"` → integer key). Реализация исправлена на strict string-preserving dedupe; финальный exact head зелёный. Не возвращаться к associative-key dedupe, которое меняет тип opaque supplier code.

### Hotel types semantics

#2110 merged `13598d6cebf55a8819128a99095e21f6fbbedf68`; exact PR head `a3d04fd07b4c973467a7797eabc18c3df3f67213`.
Focused run `34673815843` SUCCESS: 67 checks. Aggregate `34673815828` SUCCESS. Security `34673815810` SUCCESS.

Canonical P2 boundary для hotel types:

- canonical types — local Search3/catalog list и имеют status `verified` только при current accepted local identity и явно предоставленном local list;
- explicit local empty list отличается от unknown/null;
- supplier type labels/codes/lists сохраняются только как bounded observations;
- numeric-looking/opaque supplier type code остаётся строкой, provider-specific и не переводится автоматически в local type;
- cross-provider equivalence=false;
- generic upstream supplier filtering запрещён (`local_only`, `allowed=false`);
- type similarity никогда не является hotel identity proof и не создаёт mapping write.

Former `rating/services/types` P2 family теперь полностью закрыта source-side. Не создавать второй wrapper для этих трёх local-only families без нового provider-specific evidence или regression.

## 5. P6 source handoff — DONE

#2096 merged `13fef359e80b7eb24c6569b76433babbb44c2dbe`.
Exact head `12e5b2fc9d700b1e607af2af95f083670134278e`.
CI `34665905819` SUCCESS: 97 provider-neutral handoff checks + 115 unchanged direct-ANEX bridge checks. Security `34665905799` SUCCESS.

Один стабильный browser-safe INT→SEARCH DTO теперь покрывает:

- Tourvisor canonical search offer;
- direct ANEX canonical concrete saved offer;
- Andromeda canonical search offer;
- Andromeda current verified quote envelope.

Перед projection он реконструирует retained offer и fail-closes nested tampering meal/room/placement/availability/flight/money/timestamp/party. A→B race, stale/expired/generation/page/local mismatch отклоняются. Private supplier refs не публикуются. Search offer остаётся quote unknown/final false. `final_price_verified=true` допустим только через verified Andromeda quote. На INT boundary всегда `selection_state=disabled`, `booking_enabled=false`.

Receiving wiring, renderer/controller/selected-state и публикация принадлежат SEARCH #1646. Это больше не INT source gap.

## 6. P7 source release readiness — GREEN

Base gate #2097 merged `d37b7eff514354a773ea773c75fd782c1312bf87`.
Air-semantics extension #2101 merged `828cab90b39a51879433c266f90d2c6bd1617912`.
Hotel-category extension #2104 merged `94d4f4a4247bad1a9155391ba9af71e2c1385f81`.
Hotel-rating extension #2107 merged `1bbf4e538115a29c10c7c539d783552cdc6827d5`.
Hotel-services extension #2108 merged `c095df6b5094e442a3e0066121d5df2f66bc2992`.
Hotel-types extension #2110 merged `13598d6cebf55a8819128a99095e21f6fbbedf68`; exact PR head `a3d04fd07b4c973467a7797eabc18c3df3f67213`.
Latest aggregate readiness run `34673815828` SUCCESS; Security `34673815810` SUCCESS.

На одном exact head без network/secrets/DB прошли:

- money facts: 60 checks;
- offer contract: 189;
- retained context: 33;
- air search/filter semantics: 114;
- hotel stars/category semantics: 57;
- hotel rating semantics: 69;
- hotel services semantics: 67;
- hotel types semantics: 67;
- verified quote envelope: 54;
- INT→SEARCH handoff: 97;
- direct ANEX saved-offer bridge: 115;
- static boundary: no network/DB/booking primitives, no synthetic arithmetic, selection/booking disabled, air upstream filtering fail-closed, supplier category/rating/services/types facts не получают identity/canonical authority.

Итого aggregate contract matrix: **922 checks**.

Это означает **source-side readiness INT contracts**, а не production approval и не UI/publication acceptance.

## 7. Исполняемый приоритет дальше

Порядок остаётся:

`P0 money/fuel/additional → P1 observation matrix → P2 filter/property semantics → P4/P5 regression only if new evidence → P6/P7 maintenance only if regression → handoff to SEARCH`.

P3 identity/matching никогда не становится fallback-задачей INT.

После #2096/#2097/#2100/#2101/#2103/#2104/#2107/#2108/#2110 запрещено создавать второй quote/handoff/air-filter/category/rating/services/types wrapper ради активности. Следующая INT работа допустима только если даёт новый evidence/value.

### P0/P1 — следующий eligible work

Новый supplier search разрешён только если:

- предыдущий related case не `unknown/reserved/completed` replay;
- applicable CI/budgets green;
- scenario materially different;
- он приносит новую price/fuel/additional observation, новый meaningful scenario или новые external hotel IDs;
- максимум 3 новых supplier scenarios за run.

Приоритет scenario diversity:

- core8: Turkey, Egypt, Thailand, Maldives, UAE, Cuba, Sri Lanka, Vietnam;
- future dates;
- nights 6/7/8/10/12/14;
- adults 1/2/3;
- family 2+1, затем 2 children с ages;
- broad и hotel-scoped;
- meal/stars/regions только после verified mapping semantics;
- new room/placement или ещё не наблюдавшийся fuel/additional state предпочтительнее почти идентичного поиска.

ANEX-only parity сравнивать только current accepted triple-mapped subject:

direct ANEX ↔ Andromeda `OPERATORS=5` ↔ Tourvisor с ANEX в исходном request.

Tuple alignment: `current local identity + date + nights + party + canonical meal + room`; placement отдельно до доказанного parity.

Для `local_id=null` сохранять provider/country/external id/name/geography/star/coords/search criteria, если фактически доступны, и передавать #1759/#996. Никаких mapping writes.

### P2 — eligible только uncovered semantic family

Statuses: `verified | local_only | unsupported | unknown`. Raw supplier numeric IDs никогда не универсальны.

| Family | Rule |
| --- | --- |
| departure/country | provider-specific dictionary mapping |
| region/resort/subregion | не отправлять upstream без verified mapping; следующий semantic audit должен сначала определить фактически существующие provider dictionaries/capabilities, а не создавать generic ID translation |
| hotel | current accepted identity либо observation; P3 writes external |
| stars/category | raw supplier label = observation only; canonical 1..5 только из current accepted local identity; no identity/equivalence proof |
| rating | local_only/closed; canonical 0..5 only from current accepted local identity; supplier score observation only |
| services | local_only/closed; canonical list only from current accepted local identity; supplier labels/codes observation only |
| types | local_only/closed; canonical list only from current accepted local identity; supplier labels/codes observation only |
| meal | raw + canonical key; verified families only |
| room/placement | raw + normalized; placement separate до parity |
| operator | provider != operator; supplier codes private |
| arrival airport/direct/charter | Tourvisor catalog/discovery verified only; upstream supplier-search status unknown/fail-closed для всех; direct ANEX/Andromeda discovery unknown; raw IDs provider-specific |
| dates/nights/party/ages | verified/closed family |
| availability | source/raw evidence, unknown когда нет доказательства |
| flights/baggage | optional detail capability, не auto-fetch |
| fuel/additional | separate reported facts, никогда synthetic total |

Один PR = одно bounded field family, без broad provider rewrite.

## 8. Observation contract

Когда данные реально доступны, сохранять:

- provider + operator;
- country/region/subregion/geography;
- external hotel id;
- current `local_id` или null;
- raw name, stars/category, coords;
- exact search criteria/date/nights/party/child ages;
- raw+normalized meal/room/placement;
- availability/flight flags;
- search price/currency;
- fuel/additional states отдельно;
- observed_at + source request lineage.

Каждый supplier scenario получает `operation_id`, checkpoint path, scenario revision, criteria digest и source SHA.

## 9. Definition of useful progress

Пакет полезен только если даёт минимум одно:

- новый доказанный money/fuel/additional fact;
- новый non-replay observation scenario;
- новый external-hotel evidence handoff без mapping write;
- закрытый ранее unknown P2 filter/property family;
- реальный regression fix canonical offer/quote/handoff guards;
- новый подтверждённый supplier package/quote state без booking;
- устранённый stale-state/unsafe-replay риск.

Не создавать PR/checkpoint churn без такого результата. Пользователю сообщать только substantive result, exact blocker/solution или доказанное изменение semantics.

## 10. Release/stop gates

Отдельное разрешение обязательно для production/main, `bron`/`bron_ticket`, реальной заявки/бронирования, Tourvisor protected contract/arithmetic, lead contract, Metrika/goals, production SEO, server/platform config, irreversible schema/data operation, ослабления manual/pair/conflict guards и любого нового HIGH-risk supplier action.

Source-side INT readiness не является production approval. Preview/publication/readback выполняются только по текущему exact owner-control и соответствующему владельцу.

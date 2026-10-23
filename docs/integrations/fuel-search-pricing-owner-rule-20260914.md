# Search fuel surcharge owner rule — 2026-09-14

## Owner clarification — 2026-10-22: monitor first, evaluate accuracy later

- Retain Tourvisor tour/search-stage and default-flight fuel amounts separately, including differences. Exact equality is not an intake requirement. Missing tour fuel stays null; explicit zero remains a separate fact.
- Explicit supplier zero is also retained from search rows and the default-flight amount, including `0.00`. It is not filled in for missing values and cannot be borrowed from a positive alternative pair. This only fixes monitoring selection; it does not apply the SAMO/Biblio-Globus zero policy to Tourvisor or change supplier totals.
- New Tourvisor observations carry private `monitoring` facts in the existing direction store. They cannot automatically confirm a reusable pricing rule, even when two or more samples match. Legacy observations remain unchanged.
- The 80% accuracy objective is evaluated later on the accumulated cohort, including differing samples. It is neither a 20% per-sample tolerance nor a requirement to discard disagreements. This change does not claim an achieved accuracy score or define a new error tolerance.
- A differing fuel observation does not invalidate Tourvisor actualization. The existing tour/flights responses and money remain unchanged; no extra supplier requests are added. SAMO listing estimates, direct ANEX/APD and final-price verification remain separate.
- Biblio-Globus fuel=0 applies only to SAMO search listings. It must not override Tourvisor or direct ANEX amounts. SAMO/Andromeda remain paused.

Owner clarification for INT pricing:

- Search result price must include the applicable fuel/air surcharge for every passenger in the package.
- The surcharge is selected by the flight program/date, not by hotel.
- Adults and children are counted separately; child surcharge may differ from adult surcharge.
- Direct ANEX source: `AdditionalPricesDaily` (`price_adult` / `price_chd`, or the matching converted fields when the search-price currency requires them).
- Andromeda source: transport directory / transport surcharge for the offer's flight program/date, with adult/child amounts when supplied.
- Search display estimate: `base search package price + adult surcharge * adults + child surcharge * children`.
- Missing surcharge stays `unknown`; never substitute zero.
- This is the search-result estimate. Final price is still replaced by supplier actualization / selected-flight calculation later.
- Do not block search-result surcharge arithmetic on final quote/calc availability.
- Preserve base search price, surcharge facts, and final actualized/quote price as separate evidence fields even when the search display amount is summed.


## Owner clarification — 2026-09-18: regular vs charter listing and persisted APD

The direct-ANEX listing contract has two explicit price states.

### Regular / GDS

- A supplier-grounded regular/GDS offer (currently observed as `freightExternal=Y`) is allowed into search immediately at the direct ANEX search price.
- It is displayed with the badge **«Регулярный рейс»**.
- Hover/focus/tap disclosure: **«Возможна доплата за регулярный рейс. Точную стоимость необходимо уточнить.»**
- The amount remains `search_price` / `confirmation_required`; it is not relabeled as APD, fuel-inclusive or final.
- An empty `AdditionalPricesDaily` response is retained as evidence that no APD row was returned for that context. It is never converted to numeric zero.

### Charter / internal ANEX flight

- A supplier-grounded internal/charter offer (currently observed as `freightExternal=N`, non-GDS) must use the applicable APD rate before it becomes a customer listing.
- Listing amount: `base_search_price + adult_rate * adults + child_rate * children`.
- The displayed flight badge is **«Чартерный рейс»**.
- Unknown flight class is never guessed as charter or regular.

### APD storage identity

Persist APD supplier evidence by:

`supplier_program_id + date_beg + nights + supplier_currency_id`.

Passenger count is not part of the supplier-cache key. The database stores adult and child rates separately; the current party amount is derived locally. This means one supplier APD read can price multiple party compositions without replaying the same program/date/night request.

Persist these states separately:

- `rate`: exactly one validated APD row, with native and converted adult/child rates;
- `empty`: `totalCount=0` and `data=[]`, explicitly not zero money;
- `ambiguous`: multiple/unusable APD rows; never customer-admitted as a charter price.

### Program registry and prewarm

Every direct-ANEX search/expand should passively record the observed supplier program, departure, country, provider currency and explicit flight class. Conflicting charter/regular evidence becomes `mixed` and is excluded from automatic charter prewarm.

Before morning demand, a bounded background worker refreshes recently observed unambiguous charter program/date/night/currency contexts, prioritizing near departures and commonly observed nights. During live search, stale/missing contexts are refreshed in parallel and persisted. Workers must deduplicate by the exact APD context and stay within the supplier rate limit.

### AnyTour DB-first target

All concrete offers are persisted, but with distinct money states:

- charter: APD-derived listing amount ready;
- regular: direct search amount, confirmation required.

Base search money, APD rate evidence, computed party surcharge and later selected/actualized price remain separate facts. The current `anytour_offers` readiness contract therefore needs a follow-up extension for the regular/search-price state; this pricing rule does not silently overload `finalPriceReady`.

## Proposed monitoring export — 2026-10-23 (NOT AUTHORIZED / NOT IMPLEMENTED)

The retained five-context public baseline is evidence-only
(`83bd1826748f38ac472ab48b71ab405c1f4113d0`); it does not measure the current
private monitoring population or post-4834 captures. The existing ROOT gateway
discards the return of passive capture and has no aggregate retrieval action.
This proposal grants no read, export, endpoint, control or publication authority.

### Exact proposed boundary

- Producer: only the validated normalized observation envelope already loaded by
  the existing successful `captureFlights` → `append` path for the current
  operator/direction. No additional file, log, pending payload, config or DB read;
  no directory enumeration, historical replay or replacement of the rejected
  PR4824 reader. If the envelope is unavailable in that normal path, export is absent.
- Scope: one current operator/direction; at most the existing 128 retained rows.
  This is a bounded retained window, never the all-time population. Describe
  fresh, expired and legacy/non-monitoring rows separately. Missing input/result
  is unavailable, never numeric zero.
- Proposed delivery: one optional `X-AnyTour-Fuel-Monitoring` HTTP response header
  on the existing normal `flights` response, base64url JSON, at most 2048 bytes.
  No new action/URL/polling endpoint, background worker, supplier call or UI flow.
  The Tourvisor JSON body, status and money must remain byte-identical.
- Payload allowlist: schema version, source marker, current operator family,
  bounded window limit/from/to, retained/fresh/expired/legacy counts, and buckets
  by currency plus unit. Each bucket contains sample/paired/matched/different/
  unavailable counts and the sum of absolute stage differences in integer minor
  units. No raw rows, tour/offer/hotel/flight IDs, hashes identifying customers,
  tokens, paths, response bodies, per-customer amounts or private error text.
- Amount differences compare the two validated facts of the SAME observation;
  do not mix currencies/units or substitute a tour amount for missing flight fuel.
  Zero reference amounts are counted separately for any later percentage analysis.
  A missing bucket/header, invalid data, overflow or oversized result stays
  unavailable. Export failure cannot change the successful supplier response.
- Always report `complete_population=false`, `accuracy_evaluated=false` and
  `final_price_verified=false`. Stage equality/difference is descriptive, not
  predictive accuracy, a rejection gate, an automatic pricing rule or a 20% tolerance.
  Infant fees and SAMO/Biblio policy remain outside this export.

### Acceptance and authority still required

Before implementation, explicitly review/authorize this exact data exposure and
its existing ROOT gateway publication/auth boundary. Current standing supplier-check
permission is not that authorization. ROOT/source/publisher gates must remain intact;
do not add a privileged reader or widen a carrier to obtain this output.

After that separate authority: one writer on the existing store/passive/gateway
owners, narrow fixtures for mixed currencies/units, zero/missing/expiry/window bounds,
redaction, overflow and observer failure, applicable exact-source CI and independent
delta review; private runtime and ROOT installation are separate existing guarded
stages. Reuse successful source/install evidence. The first justified new ordinary
case may consume the header once; do not create a paid zero/outage/coverage probe.
Until those stages complete, population size, post-fix persistence and 80% accuracy
remain NOT_MEASURED. This document itself changes no application or access.

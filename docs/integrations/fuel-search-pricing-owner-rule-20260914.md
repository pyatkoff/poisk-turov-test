# Search fuel surcharge owner rule — 2026-09-14

## Owner clarification — 2026-10-22: monitor first, evaluate accuracy later

- Retain Tourvisor tour/search-stage and default-flight fuel amounts separately, including differences. Exact equality is not an intake requirement. Missing tour fuel stays null; explicit zero remains a separate fact.
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

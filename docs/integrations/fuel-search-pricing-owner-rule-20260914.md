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

## Bounded monitoring export — 2026-10-23

The five-context public baseline at `83bd1826748f38ac472ab48b71ab405c1f4113d0`
is historical evidence, not a measurement of the current private population.
The existing successful Tourvisor `captureFlights` path now prepares an optional
`X-AnyTour-Fuel-Monitoring` response header from normalized retained observations
already loaded in `Store::append`. It adds no file/log/pending/config/DB read,
directory enumeration, endpoint, worker, supplier HTTP or ROOT gateway edit.
PR4824's rejected private snapshot reader remains closed and unexecuted.

### Exact boundary

- One current operator/direction, at most the existing 128 retained rows.
  The header is a bounded window, never the all-time population. The current
  response provides the direction context; no direction/offer/customer identifier
  or digest is exported.
- Delivery is one optional base64url JSON header value, at most 2048 bytes,
  after successful normal flights capture and lock release. Supplier JSON body,
  status and money, existing receipt schema and default append return are unchanged.
  Skipped/rejected/failed capture emits no header. CLI emits nothing unless a test
  supplies an explicit sink; unavailable headers or exporter failure are inert.
- Payload allowlist: `schema_version`, fixed `source`, `operator_family`,
  `as_of`, `window_limit`, `observed_from`, `observed_to`,
  `retained_count`, `fresh_count`, `expired_count`, `legacy_count`,
  `buckets`, `complete_population`, `accuracy_evaluated`,
  `final_price_verified`. Times are UTC Unix seconds.
- Fresh/expired counts apply only to monitoring rows; legacy/non-monitoring rows
  are separate, including Andromeda provenance. The three counts sum to retained.
  Fresh means observed no later than `as_of` and expiry strictly later than it.
  Window times include all retained rows; buckets include fresh monitoring only.
- Each currency-plus-unit bucket contains only `currency`, `unit`,
  `sample_count`, `paired_count`, `matched_count`, `different_count`,
  `unavailable_count`, `zero_reference_count`,
  `absolute_difference_minor_sum`. Paired = matched + different;
  samples = paired + unavailable. The integer sum uses two decimal places of
  the stored monetary representation, no FX, averaging, division or rounding.
  Unknown units stay in their own bucket and acquire no pricing meaning.
- Differences compare the two validated facts of the SAME observation.
  Missing tour fuel is unavailable, not zero. A real zero reference is counted
  separately; no percentage is computed. Raw rows, amounts per customer, IDs,
  hashes, tokens, paths, private errors and response bodies are absent.
- Invalid rows, duplicate evidence, mixed directions, future observations,
  integer overflow or oversized output suppress the header. Observer failure
  cannot affect successful persistence or the supplier response.
- `complete_population=false`, `accuracy_evaluated=false`,
  `final_price_verified=false` always. Equality/difference describes stages,
  not prediction accuracy, a rejection gate, an automatic rule or a 20% tolerance.
  Infant fees and SAMO/Biblio policy remain outside this export.

### Source, installation and measurement

This bounded implementation follows the owner's direct continuation and coordination
claim #4709/6361744186. Existing source, independent-review, CI and stock runtime
installation gates remain in force; no protected control or publication permission
is widened. The two existing private helpers are installed by the existing runtime
carrier. ROOT source/auth and supplier request contract remain unchanged.

Narrow fixtures cover mixed currencies/units, zero/missing, expiry/legacy,
128-row retention, exact integer arithmetic, redaction, idempotence, size limits,
observer/emitter failures and response transparency. Source/test success,
runtime installation and actual new capture are separate evidence stages.
The first justified ordinary new case may consume its header once; do not create
a paid zero/outage/coverage probe or replay old receipts to fill this window.
Until that capture, current population size, post-install persistence and 80%
accuracy remain NOT_MEASURED. Even a header reports only its bounded retained window.

### Native HTTP regression and observation handoff — 2026-10-23

The existing passive-intake suite now exercises default PHP `header()` through
one ephemeral localhost PHP server, in addition to the existing injected sink
fixtures. Retained supplied zero emits exactly one bounded header; skipped and
rejected capture emit none. All three preserve HTTP200 and byte-identical mock
supplier JSON. The fixture keeps `allow_url_fopen=0`, uses only loopback sockets,
and adds no supplier request, product endpoint or runtime change. Removing only
the native emission branch must fail this regression; an injected sink alone
cannot prove that branch.

This automated transport fixture is separate from actual post-install evidence.
The consumed JIVA case at `4f54d562255b5f7ac0c6c87b9190dc29872392cf`
proves only its recorded ordinary UI path; the supported browser observation API
did not expose response headers. It does not establish backend capture, raw
supplier zero, an isolated infant fee, current private population or80%accuracy.

The producer contract is already installed. The remaining minimal consumer
handoff belongs to the existing SEARCH response-client owner: during an already
justified ordinary successful TV flights response, forward the existing optional
`X-AnyTour-Fuel-Monitoring` value (base64url, at most2048 bytes) to an existing
diagnostic observation surface. Reuse that SAME response/case and retain the
explicit false population/accuracy/final-verification flags. No new request,
storage, polling, UI requirement, state/lifecycle change, alternate carrier or
private reader is needed or authorized by this handoff. Missing/unreadable stays
unavailable; do not repeat consumed JIVA or another paid case to fill it.
The current `v2/runtime-v3.js` already exposes
`V2Runtime.on(action, fn(data, response))`; its existing `observedFetch` passes
the original response to listeners. The consumer therefore needs no runtime
transport change. A concrete minimal registration for its existing observer is:

```js
V2Runtime.on('flights', (_data, response) => {
  const value = response?.headers?.get('X-AnyTour-Fuel-Monitoring');
  if (response?.ok && typeof value === 'string' && value.length <= 2048 &&
      /^[A-Za-z0-9_-]+$/.test(value)) {
    console.info('AnyTourFuelMonitoringV1', value);
  }
});
```

This forwards only the already-reviewed encoded header to a local diagnostic
surface; it does not log response bodies, query URLs, IDs or raw errors.
The observer is optional, has no product state/DOM writes, does not evaluate
the payload, and must be registered once by the existing consumer owner.
This is a concrete handoff example, not installed consumer code.
INT does not edit the SEARCH-owned client through this test/document packet.

### Tourvisor operator-side GDS availability — owner-forwarded support, 2026-10-23

The owner forwarded a Tourvisor support reply in the current conversation,
recorded in [INT queue6363730002](https://github.com/pyatkoff/poisk-turov-test/issues/2506#issuecomment-6363730002).
This is an owner-forwarded supplier-support statement, not an HTTP/log receipt
or independently verified status page:

- Coral Travel and Sunmar GDS tour actualization currently fails for technical
  reasons on the operators' side. Support describes a general problem rather
  than a particular hotel or API request.
- Charter actualization may work; that does not verify a particular charter.
- Tourvisor says changing API requests/settings is unnecessary and cannot
  resolve the operator-side GDS issue.

This establishes the reported availability boundary and supplier-side reason,
not the historical HTTP code, curl errno, elapsed time or request correlation.
Do not repair AnyTour transport/auth/parameters/timeouts for this reported cause
or generalize the outage to all charters, other TV operators or direct ANEX.
Unknown/regular flight type is not automatically a verified GDS/charter identity;
do not borrow ANEX's classification or trust a caller-supplied URL flag.

The existing explicit owner instruction6289177263 keeps Coral/Sunmar listing-only:
no actualization, flights, final calculation or failure retry. The support reply
does not itself repeal that operational gate. Any future charter exception
requires an explicit policy change and authoritative operator/offer/flight-type
binding; neither is fabricated in this packet. No supplier probe, configuration
change, fallback, transport change or guard relaxation follows from the reply.

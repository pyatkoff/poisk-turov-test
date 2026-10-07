# ANEX selected package calculation

Observed 2026-09-28 in the official public agent frontend, not inferred from APD
or FreightMonitor. This is implementation evidence; offline fixtures are not
supplier acceptance. The current Search3 specification remains #1646.

Primary sources:
- https://agent.anextour.ru/_next/static/chunks/1udgln78pgir4.js
- https://agent.anextour.ru/_next/static/chunks/13hw4d__9yhhs.js
- https://agent.anextour.ru/_next/static/chunks/3k5d_6let514q.js
- https://agent.anextour.ru/_next/static/chunks/35epmo161dihn.js

The agent client uses axiosBearer against https://api.anextour.ru. Each request
is POST multipart FormData; lang=ru. The implementation reuses the existing
ANEX_B2B_TOKEN loader without changing credentials, auth configuration or scopes.
Whether the deployed token and parser CATCLAIM work for this API still requires
one separately reserved runtime acceptance. Never try another auth carrier.

| Stage | Path | Form fields besides lang |
| --- | --- | --- |
| Begin temporary calculation | /bron/start | cat_claim, client-generated numeric id, currency |
| Read transport choices | /bron/transports | id |
| Select transport pair | /bron/SetTransport | id, recalculate=false, transport[routeIndex]=uid |
| Calculate full package | /bron/calcfull | id |

The public client generates a numeric temporary id, separate from booked claims.
Its transport formatter groups regular flight variants in consecutive pairs;
charter directions are individually selectable. Never combine arbitrary GDS legs.
Returned claimDocument supplies hotel key, room/meal/placement, dates, nights,
adult/child counts, transports and moneys.money. The price formatter uses gross
money.price; money.net is agency money. This adapter accepts only one explicit
RUB gross row. It does not reconstruct FX, rounding, fuel or additional charges.
Missing/ambiguous currency or price stays unverified.

The official bron client also treats the top-level `code: -1` envelope as a
supplier rejection (observed in the first two frontend chunks above). A returned
claimDocument does not override that failure. The adapter rejects numeric or
string `-1` before reading the document, seals the attempted stage and exposes
only `ANEX_QUOTE_SUPPLIER_REJECTED`; supplier message text is not retained.
Offline coverage exercises this envelope at all four stages and proves that a
retained valid-looking document cannot produce choices or a verified price.
This does not identify the cause of the earlier live attempt's unknown failure.

Booking is a DIFFERENT /bron/save call in the official client, taking personal
form values. Save, buyer/tourist writes, draft booking, payment, services and
fare changes are outside this adapter's allowlist and authorization.

The isolated ANEX endpoint adds quote_start and quote_calculate. Existing
generation/search/offer/local-hotel validation runs first; native references stay
server-side. Every supplier stage has a durable UNKNOWN reservation before HTTP
and no retry. Unversioned retained attempts preserve their original four-call,
one-selected-pair contract; they are never upgraded or reopened. Unsupported or
malformed versions fail closed.
Returned package identity and selected transport UIDs must match at every stage.
Only calcfull can produce quote_verified, with expiry no later than the search
context. Personal/raw supplier documents are neither persisted nor projected.

## Bounded repeated pricing — owner authorization 2026-10-07

Only newly created version-2 attempts have repeated-pricing capability. The same
temporary calculation id may allocate at most three distinct retained flight
pairs: two initialization calls, then SetTransport + calcfull for each new pair,
at most eight durably reserved calls. The pair decision, active-pair marker and
call debit are saved together before HTTP. Failed or unknown decisions consume
their slots; the budget, initialization and temporary id are never reset.

The existing PHP session lock serializes mutable work. Its checkpoint releases
and reacquires that lock, then verifies the complete expected retained state.
Concurrent reentry sees the active reservation and cannot mutate it. A checkpoint
failure does not trigger a second stale write; a lost session comparison escapes.
For other failed final checkpoints, in-memory promotion is replaced by the pending
reservation before the endpoint can close its session.

Any supplier/stage failure seals the entire mutable context. Retained active,
UNKNOWN or incomplete stages also block every new pair and cached-price promotion.
No replacement session, replay, auth change or booking call recovers such a seal.
The immutable binding includes the exact offer, search/generation, supplier claim,
selected currency and retained child ages. Foreign bindings cannot spend calls.
The current clock is checked before every HTTP call, after its response and after
the result checkpoint; expiry never grants a renewed budget or a verified result.

An open-context A -> B -> A cache hit returns A's original exact gross total,
pair reference, verified_at and expires_at. It makes no supplier/factory/checkpoint
call, spends no pair slot and leaves the mutable supplier head at B. A healthy
quote_start therefore returns the last completed supplier calculation plus the
retained safe choices inventory. The fourth uncached pair is locked; already
verified pairs remain readable until expiry while the context stays open.

Fresh version-2 responses add only the capability marker
`repricing={enabled,max_pairs:3,used_pairs,remaining_pairs}` and retain the existing
choices projection in verified responses. Counts are derived from durable pair
decisions; disabled contexts report remaining_pairs=0. Legacy responses have no
marker. Receipts project up to eight sanitized stage facts with pair ordinals,
without private supplier ids or opaque choice references.

This bounded source contract uses the existing frontend endpoints and unchanged
gross/identity/selected-UID authority. Offline repeated-pair fixtures do not prove
that the installed token and live supplier session accept repeated SetTransport
and calcfull; that requires separately reserved runtime acceptance. No APD, fuel,
transport markup, FX or price-delta arithmetic is introduced.

Target: NEXT visual-search -> shared prototype-search/data.js -> existing
isolated ANEX endpoint. This source package alone does not publish UI or establish
live acceptance. Installation must use the existing checked INT install-runtime
and install-anex-preview mechanisms, each with its own reservation and receipt.

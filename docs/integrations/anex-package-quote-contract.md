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
server-side. Every supplier stage has a durable UNKNOWN reservation before HTTP,
a finite four-call per-offer budget and no retry. Only one selected flight pair
can consume the calculation. Terminal/cache/history reads cannot spend again.
Returned package identity and selected transport UIDs must match at every stage.
Only calcfull can produce quote_verified, with expiry no later than the search
context. Personal/raw supplier documents are neither persisted nor projected.

Target: NEXT visual-search -> shared prototype-search/data.js -> existing
isolated ANEX endpoint. This source package alone does not publish UI or establish
live acceptance. Installation must use the existing checked INT install-runtime
and install-anex-preview mechanisms, each with its own reservation and receipt.

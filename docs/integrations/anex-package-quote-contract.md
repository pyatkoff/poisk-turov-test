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

## Infant family binding — source contract 2026-10-21

The original request keeps every child in CHILD/AGES, including ages0/1. The
internal/public `children` count continues to mean that entire requested list;
`infants` is a subset, not an extra passenger or a separate calculated charge.
For a requested infant family, this normalizer requires explicit supplier infant
count matching ages below2 and supplier child count equal to the remaining ages.
Missing or contradictory counts stay rejected. This SearchTour count decomposition
is a source-contract inference from the documented separate fields; a nonzero
SearchTour wire specimen and ordinary live acceptance remain separate evidence.

Primary public sources retrieved on2026-10-21 without supplier API/auth calls:
- https://dokuwiki.samo.ru/doku.php?id=onlinest:api:searchtour:prices
  (68139B; SHA256 `9ee4123ae1e2dc42fff16b74e7a2976844f4ede8016f95f79f2f63fea7ad57ed`).
- https://agent.anextour.ru/_next/static/chunks/3j9cb9-tia3ma.js
  (380799B; SHA256 `3e66ab84677135c2d5c33a57a97de85247838ad723e348fe46696e31a8a5d143`):
  module474441 `_formatBookingHotels` independently counts typed claim participants.
- https://agent.anextour.ru/_next/static/chunks/32kpnbkz1bf7b.js
  (SHA256 `15e637a35f19e4eee9484bd0744906956a70431c211c66b1de9c5b943eb0a2ac`):
  module559458 defines ADL, CHD and INF as separate classes.

Every infant quote stage must prove all requested adults, non-infant children and
infants from explicit typed participants with distinct keys. Missing/unknown types,
contradictory age/human classes or disagreeing present summary counters fail closed.
The saved full ages and infant count must agree before client construction. Legacy
non-infant behavior is preserved. Only the existing explicit RUB gross calcfull
total can verify the entire party; separate infant fee remains unknown. No APD,
fuel, currency, booking, personal-data write or additional endpoint is introduced.

The existing own-session `quote_receipt` can additionally report a validated
`retained_first_page` aggregate. It compares the already retained pre-normalization
raw-row digest with SHA256 of `[]`; the digest itself and supplier identifiers
never leave the server. An empty raw page is distinguished from a nonempty page
with no retained offers (rows rejected or hotel-filtered). It cannot identify the
individual rejection, raw party counters, HTTP timing, market availability or a
root cause. Missing, malformed, continued or blocked snapshots omit this field:
unknown is not empty. This diagnostic read does not renew expiry, start requests,
create quote authority or reopen any attempt. No new endpoint or query is added.

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
Retained choices and cached prices also recheck the current clock after the gateway's
hotel identity/metadata work. A request entering before expiry cannot promote a
cached quote if that work crosses the original deadline; an invalid current clock
returns an unverified unknown result. These reads do not mutate retained state,
renew its deadline or perform supplier/checkpoint calls, including legacy attempts.

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


## First-page normalization diagnostics (21 October 2026)

The existing own-session `quote_receipt.retained_first_page` may additionally contain
`normalization`: at most300 processed rows, accepted/rejected row counts, the first
failed existing predicate per rejected row, requested-hotel filtered rows and the
retained unique offer count. Fixed rejection codes distinguish row/reference,
hotel/date/night/package fields, adult/child/infant, grouping, price and currency.
The bounded `party_counts` histogram contains only parsed adult/child/infant counts
(e.g. `2/0/1`); absent and malformed values remain `missing` and `invalid`, never0.
Rows can fail multiple predicates: the first-failure partition does not claim that
all other fields are valid. Duplicate accepted rows may reduce retained offers.

These facts are captured during the existing successful foreground first-page
request, bound to its private search reference and raw-page digest, and kept in the
existing gateway session outside the authoritative snapshot. Neither binding,
raw rows/identifiers, labels, prices, credentials nor response bodies are returned.
The regular public search result is unchanged. Receipt validation rejects unknown
codes, malformed/inconsistent counters or foreign bindings; legacy sessions do
not gain invented facts. Existing first-page-only eligibility still applies.
The read neither requests supplier data nor mutates the session/expiry/authority.

This is observability, not a relaxation of party validation or final-price proof.
Existing family case6325407802 is consumed/no-replay; its missing historical fields
cannot be reconstructed by this addition. Only a future justified new ordinary
case can establish actual supplier counter semantics. Source, CI, independent
review, stock installation and that future live acceptance remain separate stages.

## Observed age-zero boundary — 22 October 2026

PR4802 was independently reviewed, merged as5a703baf, and installed through the
existing runtime and endpoint carriers (39685334326 and39685594872). In the one
new ordinary Moscow→Egypt14–20Feb2027/7n/2A+[0]/AI case, the current-session receipt
reported174 raw rows, all with parsed counters adult2/child0/infant0. Every row
failed the existing `infant` predicate; normalized rows and requested-hotel
filtered rows were both0. Thus the specific local rejection and the returned
counter values are established. This is not an empty supplier page or a proved
transport outage. The historical31Jan case still lacks these exact facts.

The installed request path has no falsy-age filter: the existing real Client
validates and serializes CHILD1 and AGES`0`. The focused offline Search→Client
regression checks that complete query boundary and the observed rejection shape
through an injected transport. It is source/offline evidence, not a capture of the
actual live request URL or proof of the supplier's interpretation of age0.
The public SearchTour contract documents CHILD/AGES and separate response counters;
its zero-infant example supplies no supported nonzero-infant guarantee.

Consequently live infant support remains UNCONFIRMED. Accepting returned infant0
as the requested whole family, changing age0 to1, inventing an INFANT parameter,
or treating a missing infant charge as0 would discard the unresolved binding.
The next provider contract result must establish how the requested infant appears
in the supported search/package claim and typed quote participants. Until then the
current strict checks and unknown infant fee remain in force; no whole-family
flight/final-price acceptance is claimed. The completed14Feb case is consumed and
must not be rerun, repolled or varied as a fallback. Tourvisor remains independent.

Evidence: #4709/6327863689 (single Find/one regular own-session receipt, no expand,
quote, Continue or retry); retained DOM/receipt JSON SHA256
`3b15c5c92b2e7ebc6555b2ab7d5175b0ea551b502b987f7a1c115b24963245ad`.

### Official search-to-quote boundary inspected 22 October 2026

The first two assets are linked by the official [agent search page](https://agent.anextour.ru/search/tour).
The third is the previously retained official booking asset with the same pinned
hash. They provide these narrower, independently checkable source facts:

| Official asset / module | Observed behavior | Limit of this evidence |
| --- | --- | --- |
| [1h772e4nh6ahm.js](https://agent.anextour.ru/_next/static/chunks/1h772e4nh6ahm.js), people field | Adding age 0 increments CHILD and joins the unchanged age into AGES. Parsing excludes empty entries, preserving the string 0. The form distinguishes infants under 2 and restricts their count relative to accompanying adults. | Frontend form behavior, not a live SearchTour response contract. |
| [1aecwlppxy2vn.js](https://agent.anextour.ru/_next/static/chunks/1aecwlppxy2vn.js), modules 64743/373098 | Search parameter preparation includes CHILD and extracts/sorts/joins age digits without discarding 0. The separate seated-tourist count is adults plus requested ages 2 and older. The tour-card mapper uses that count from search inputs. | A seated count is not the full party count or evidence that the returned infant 0 represents the requested infant. The mapper's display behavior does not establish server semantics. |
| [3j9cb9-tia3ma.js](https://agent.anextour.ru/_next/static/chunks/3j9cb9-tia3ma.js), module 693655 | The official start request supplies cat_claim, id, currency and lang. It does not forward CHILD/AGES from the search form. | The inspected start function does not document how an infant absent from a claim is attached. This is not proof that the supplier has no supported mechanism elsewhere. |

Content hashes (SHA256; respectively 771799, 38919, 380799 bytes):
`b8b32aafa59c266b445a57a590ea306c020b6f2fcffc3cdea2d065e1448cc88b`,
`6bbc3efd68ec2971e2656303ad5f8d46c43a7e2cb2a6d215c76dde640ea63ffc`,
`3e66ab84677135c2d5c33a57a97de85247838ad723e348fe46696e31a8a5d143`.
Assets were read as public static text, without executing the page or making a
search, start, quote, receipt, booking or lead call. The bounded asset retrieval
had two static-file timeouts; no claim of a complete frontend audit is made.

Inference: the request age is preserved in both our client and the official
frontend, while our current search-to-quote path still lacks evidence binding the
requested infant to a returned claim. The 174 rejected rows alone cannot distinguish
supplier search convention from an unsupported/omitted infant addition. Calling
this a supplier outage, changing age 0, or merely removing the infant predicate
would not establish that missing relationship.

The minimal next contract evidence must identify the existing supported
**quote-only** mechanism, if any, that binds one requested infant and an
accompanying adult to the selected transient claim. It must establish its exact
request fields, response identity and participant semantics, and whether it has
booking/reservation side effects. Then the same bounded own-tour path can prove
typed ADL/CHD/INF participants with distinct keys and supplier gross for the entire
party across the existing quote stages. No live call is justified merely to guess
a parameter or synthesize a participant. Booking/save or personal-data submission
is outside this contract investigation. Infant fee remains unknown until explicitly
returned; a listing or an adults-only calculation cannot become final_verified.


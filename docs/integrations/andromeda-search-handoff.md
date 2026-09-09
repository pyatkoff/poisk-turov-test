# Andromeda → Search3 provider handler

Issue #1717. `AnyTourAndromedaSearch` connects the existing protocol client,
normalizer and private offer store in a start/resume operation. Default disabled.
It supplies data to the existing SEARCH renderer; it does not create another UI.

## Caller contract

- Keep a dedicated private provider state and use the existing exclusive storage
  lock across start/resume. The save callback must durably and atomically commit
  the supplied state and return true. Do not use a callback that only buffers a
  PHP session until request shutdown: the pending checkpoint must be durable
  **before** the first network operation. Storage sits outside DOCUMENT_ROOT.
- Enforce preview-only routing, owner/session authority, CSRF, a reviewed
  account-wide supplier budget and the feature flag before constructing an
  enabled handler. This module is not itself an HTTP authorization boundary.
- `start(criteria, searchRef, generation, now, client, username, password, resolver?)`
  accepts exactly `AnyTourAndromedaClient::priceProbeParams()` in this first
  integration: Moscow1/Egypt3, 18 September 2026, 8 nights, 2 adults, no children,
  AI5, RUB643, Anex Tour5, package0, page1. Unsupported criteria fail before I/O;
  a generic form must not silently fall back to these values. IDs are Andromeda
  IDs. General criteria translation is not implemented by this package.
- A fresh client/transport performs login + one price request. Existing protocol
  limits are unchanged. No retries, page2, booking or catalog calls. A pending or
  unavailable prior attempt cannot be replayed by changing generation. A
  completed prior result can be replaced by an explicitly new generation only
  after the caller's account budget admits it.
- `resume(searchRef, generation, now)` returns saved data without a client or
  network. Wrong/expired contexts fail. Start a new provider generation together
  with the common search generation; drop late responses at the UI boundary.
- Provide a current accepted-only resolver if available. Proposed hotel pairs
  are never passed as accepted rows. Unresolved hotels remain visible through
  their provider key; catalog metadata is used only after accepted resolution.

## Public response consumed by SEARCH

`provider`, `search_ref`, `generation`, `status`, `date_range`, `page`,
`pages_count`, `offers`, `hotels`, `selection_enabled`, `error`.

The normalized offers keep their own operator, currency, decimal price, meal,
room, placement, dates and tourists. Fees are unknown and final price is false.
The handler never returns sid, credentials or raw opaque supplier offer IDs.

Each hotel group has a `card_key`, nullable `local_hotel_id`, `name`,
`name_source`, `provider`, `mapping_status`, and `offer_refs` into that response.
Accepted local identities use `catalog:<id>`. Unresolved identities use
`andromeda:<supplier_namespace>:<external_hotel_id>`. Thus numerically equal
Tourvisor, Andromeda catalog and operator hotel IDs do not collide. Hotel title
remains the observed Andromeda title and must be rendered as text, not HTML.
No invented stars/photo/address and no minimum-price arithmetic are added.

`pending` and `unavailable` are provider states with no offers. The latter is not
successful empty availability. `partial` means more pages or rejected rows exist;
page1 of2 must not appear as the complete supplier response. Other source results
must remain visible. Selection is false until quote and selection integration.

## Verification and remaining publication work

The CI executes start/resume against the pinned real 50-offer capture using an
offline transport and saves the exact public response artifact. The synthetic
fixture covers namespaces, persistence failures before/after API, interrupted
requests, no replay, stale/expired context and sanitized errors.

SEARCH owns `v2/results-renderer-v5.js` / lifecycle on the fresh release and is
currently modifying that renderer in #1779. The next shared package must consume
this contract with one renderer and exact provider generation, plus a scoped HTTP
route wired to existing private auth/storage and account budget. Claim the shared
paths in #996 before touching them. Do not deploy the old integration base over
release. This source package does not publish a route or enable Andromeda.

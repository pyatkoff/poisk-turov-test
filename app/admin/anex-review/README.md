# P2 protected hotel review — implementation, not a live panel

Issue #1647; own ANEX preview only. This packet does not deploy, apply a migration,
call a supplier, or change a real decision. No production Search3/DS2 modules are used.

## Implemented

- Persistent observed queue, 25 rows/page, frequency/recency order, literal search,
  country/status filters. Current registry projection respects manual hotel blocks.
- Local staging comparison, saved candidate alternatives, explicit missing fields,
  age and capped-set warnings. Current canonical country/name/coordinates are shown
  separately from historic candidate scores/raw evidence. No invented descriptions,
  aliases, photo URLs, or assertions of complete candidate coverage.
- Transactional `accept`, `reject_pair`, `later`, per-hotel mutex, evidence version,
  persistent request-key replay, audit and readback before commit. `accept` writes
  the existing manual registry table and is visible through the existing preview
  resolver. Pair rejection never writes hotel-wide `rejected`. Later never blocks.
- Existing manual decisions AND effective accepted policy rows cannot be replaced
  here. A future explicit replacement/reversal screen must display/audit the prior
  decision; this version fails closed, rather than silently broadening permission.
- Auth/CSRF boundaries, no-store/noindex/CSP, escaped output, POST/redirect/GET.
- Additive schema plus isolated MySQL tests; no application secrets in test CI.

## Deployment gates — NOT yet satisfied

1. Identify the existing real owner authentication/session authority on the server.
   Repository search did not find an admin/login system. `ANYTOUR_ANEX_SEARCH3`
   is an anonymous public rate-limit session, NOT owner authentication.
2. A reviewed server-side adapter, outside `DOCUMENT_ROOT`, must reuse that authority
   and the existing AnyTour DB helper. It must validate authentication, expiration,
   authorization and secure session cookies/rotation before returning. Never derive
   actor/capabilities from query/form/header values; never put credentials in this repo.
   `ANYTOUR_ANEX_REVIEW_AUTH_FILE` names that trusted absolute PHP file. Its return
   value is a callable returning:
   - `authenticated: true`, stable `actor`, integer `expires_at`;
   - `capabilities: ['anex:review']`, optionally `anex:decide`;
   - lazy `pdo_factory` (called only after authorization);
   - `write_enabled: false` until all gates pass;
   - `pair_exclusions_enforced: false` until import/readback protections below pass.
   Adapter must leave its verified session active. Panel rotates its own CSRF token
   when actor changes. No public login scheme/password or new server configuration
   has been created in this packet.
3. Apply `schema.sql` through an approved, bounded CLI migration with registry/manual/
   staging preservation hashes and readback. Web requests never execute DDL.
4. Wire pair exclusions into all automatic acceptance paths, including a fresh
   rejection check inside the importer transaction, then test it. Until then web
   writes are **disabled** even for an authenticated viewer. Existing paired/cached
   pinned importers were intentionally not changed or rerun in this packet.
5. Persist live dossiers not yet represented by staging into a durable source store.
   Observations are already persistent; staging top-five candidates survive artifact
   expiry but may predate live review. Missing live candidates are shown as missing,
   not substituted with fabricated evidence. The separate owner-authorized content
   pilot owns its own storage; this panel does not duplicate it.
6. Add only this page/admin package to the isolated preview deployment manifest with
   exact source/artifact provenance. Intended path:
   `/_preview/search3-anex-candidate/anex-hotel-review.php` (not currently published).
   Check authenticated/unauthenticated GET/POST, secure cookies, stale tabs/replay,
   one authorized decision, preview resolver readback and before/after preservation.
7. Desktop/mobile visual and real authorized session checks remain deferred until
   that integration. CI HTML is synthetic, not live data or a published owner link.

No completed mapping/alias checkpoint is mutated by this work. All prior finalized
delta imports remain finalized. The general release keeps its own DS2-off state.

## Tests

`.github/workflows/anex-review-panel.yml` uses a disposable local MySQL database.
`tests/anex-review-panel-test.php` refuses any other DSN. Tests cover pagination,
queue/manual projection, missing/truncated evidence, CSRF/authorization, escaped
content, stale evidence, idempotence, alternatives after a rejected pair, actual
preview resolver acceptance, transaction rollback on audit failure, and preserved
catalog/policy/candidate/prior-manual rows. No supplier or application DB is used.

# P2 protected hotel review — implementation, not a live panel

Issue #1647; own ANEX preview only. Source UI and persistent storage are implemented;
the panel is not deployed or authorized for real decisions. Pinned CLI storage migration
completed in #1728; no supplier calls or production Search3/DS2 modules are used.

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
- Read-only integration with the separately prepared `anex_hotel_content` and
  `catalog_hotel_details` stores. Missing tables/rows remain explicit; ANEX ID and
  content digest are verified. Descriptions are escaped; photos are rendered as source-labelled HTTPS thumbnails with lazy loading and
  no-referrer requests, opening the original image on click. ANEX and Tourvisor
  remain separate; an empty ANEX gallery is never filled from a candidate. Data is included in the evidence
  version, so an updated card invalidates a stale decision. No content collection.

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
3. DONE: PR #1728 applied both pinned schema files through the existing bounded CLI;
   five additive tables, registry/manual/staging/catalog preservation and readback.
   Run34344152765/artifact10100996931, sourcec9e0bbba. Web requests never execute DDL.
4. Shared importer and effective-resolver pair exclusions are implemented in
   PR #1698. The writer uses the same observation-row mutex as panel decisions,
   then a fresh locking rejection read inside its transaction. Reimports and a
   concurrent committed rejection are covered by real MySQL writer tests.
   Only verified legacy table absence is optional; inaccessible/broken review
   tables fail closed. Before enabling web writes, confirm the deployed acceptance
   paths use these guards; live migration/readback completed in #1728. Historical finalized
   paired/cached batches must not be rerun. Web writes remain **disabled** while
   the remaining real deployment gates are incomplete.
5. Durable dossier source code is implemented in PR #1697 (see dossier-bridge.md):
   offline packer, immutable archive and optional panel read path. Live archive
   migration and import completed in #1728:263 immutable historical dossiers,263/263
   DB readback, original triage/source digests and previous data preserved. Observations
   are already persistent. Missing live evidence and historical hints must never be
   replaced by fabricated candidates. The separate content store remains separate.
6. Add only this page/admin package to the isolated preview deployment manifest with
   exact source/artifact provenance. Intended path:
   `/_preview/search3-anex-candidate/anex-hotel-review.php` (not currently published).
   Check authenticated/unauthenticated GET/POST, secure cookies, stale tabs/replay,
   one authorized decision, preview resolver readback and before/after preservation.
7. Real authorized owner-session checks and visual inspection remain deferred until
   that integration. Automated Chromium HTTP/responsive tests at 1280/820/390/320
   passed on bbeb870c/run34318775116 (synthetic session/local MySQL, no overflow).
   CI HTML/screenshots are synthetic, not live data or a published owner link.

No completed mapping/alias checkpoint is mutated by this work. All prior finalized
delta imports remain finalized. The general release keeps its own DS2-off state.

## Tests

`.github/workflows/anex-review-panel.yml` uses a disposable local MySQL database.
`tests/anex-review-panel-test.php` refuses any other DSN. Tests cover pagination,
queue/manual projection, missing/truncated evidence, CSRF/authorization, escaped
content, stale evidence, idempotence, alternatives after a rejected pair, actual
preview resolver acceptance, transaction rollback on audit failure, and preserved
catalog/policy/candidate/prior-manual rows. No supplier or application DB is used.

## Rich comparison cards (owner request, 9 September 2026)

The ANEX card and saved Tourvisor candidates now appear side by side on desktop
and stack on narrow screens. Descriptions are initially open; saved ANEX location,
transfer, characteristics and room types are expandable. Up to 12 saved thumbnails
per source are shown. CSP permits HTTPS images only; it still denies scripts and
connections. Loading a photo requests its stored CDN URL, never a supplier search
or a hotel-content API call. Existing authentication/deployment gates above remain.

Verified official public-page links are pinned by ANEX ID in `public-cards.json`.
For 16193, the official HTML proves the same ID and twelve explicit room-photo
URLs. Media returned403 during research, so those URLs are evidence only and are
not presented as a working gallery. No cross-source image substitution occurs.

Saved ANEX descriptions now render JSON arrays of `title`/`text` as readable
sections with escaped headings, text and line breaks. Plain descriptions remain
supported; malformed structured values show an explicit reading failure. Parsing
is bounded to 16 KB / 32 sections / depth 8 and does not rewrite content or hashes.
The read-only inspection of all 30 stored records found 29 hotel descriptions
(184 sections including one offer section) and no photos. ANEX 17097 is explicitly
labelled as FORTUNA allocation conditions, not evidence for a specific hotel;
other hotels are not classified by the word Fortuna in their names. This is display
context, not a change to the manual decision policy or an automatic mapping.
Evidence: `reports/anex-saved-descriptions-review-20260909.json`.

## Pair exclusion verification (9 September 2026)

PR #1698 is merged only into the ANEX feature branch. Source and own-preview
resolver: `8c9378315277dbabb9405d8dcde1f98725f2bb21`.
Push panel run34327629394 passed96 MySQL checks and HTTP/Chromium tests at
1280/820/390/320 with synthetic identity; the full ANEX workflow34327629358
also passed, including90 registry checks and isolated preview publication.
The panel itself is still unpublished; no owner decision was made. Subsequent live
schema/dossier migration completed in #1728; see `reports/anex-review-storage-once-20260909.json`.
Its pinned apply plan is protected by a persistent one-shot checkpoint: reservation
artifact before SQL, executing before SSH, completed report/preservation/readback.
Unknown or missing post-bootstrap checkpoints never replay. Completed storage does
not repackage evidence under a newer artifact ID or re-read SQL. The historical263
dossiers are not the whole current pending population.
Next: establish the actual owner authority and reviewed adapter outside DOCUMENT_ROOT,
then isolated publication and real authenticated gates. Existing Bitrix bootstrap and
CSRF helpers alone do not identify an authorized owner; synthetic fixtures never deploy.

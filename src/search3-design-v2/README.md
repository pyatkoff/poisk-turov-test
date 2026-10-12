# AnyTour design-v2 — native final Sites frontend

This is a new frontend, not a stylesheet applied to Search3 NEXT.

The only layout donor is final Sites version 2, source
`00fe6734c08acf234756bb59e5b61dbfecf41941`. The original fragment and desktop
stylesheet are retained byte-for-byte under `reference/`. Its original Aeroport
fonts and AnyTour logo are part of that fragment; do not reconstruct them.

The importer extracts the original product markup and CSS, removes laboratory
navigation and the mock simulator, and mounts `frontend.js`. A separate
`bridge.js` talks to the existing canonical data owner. No legacy
`visual-search/app.js`, `styles.css`, `mobile-controls-v1.css`, filter-panel,
hotel-details or offer-list renderer may be loaded by this frontend.

The reference's generated hotels, date/party price factors, fictional flights,
inclusions and local lead success are never a live data source. Unknown facts
stay unknown. Source preparation and local DOM checks are not publication or
acceptance of the complete booking journey.

The source is isolated until its new entry graph passes exact-source build,
runtime and independent visual gates. The only eventual publication address is
`https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/`.
Sites reference, NEXT, production and sibling previews must remain unchanged.

## Checkpoint — 2026-10-12

The source and bridge fixture checks pass, including exact donor hashes, passive
initialization, local meal filtering without another search, exact offer/tour
references, full flight variant prices and rejection of stale quote responses.
These are DOM/contract checks, not browser visual acceptance or supplier evidence.

Implemented in the same packet: weekday-aligned two-month date editor and
Apply/Cancel; exact/range nights; canonical country/resort/hotel selection;
budget and child ages; URL/Back/Forward and in-memory drafts; pending search and
quote delivery across viewport mounts. The native tour delegates Tourvisor and
ANEX quote/flight/expiry authority, requires acceptance of a changed full total,
and validates contact review through the existing lead payload owner. Actual
lead delivery remains disabled under the preview boundary.

Publication is blocked by the consumed predecessor-specific updater and old entry
graph inventory. The candidate builder deliberately does not rewrite protected
canonical route dependencies and is not a publishable artifact. A reviewed native
build/control successor, exact-head CI and independent rendered checks at
360/390/430/768/1280 and 200% text are required. No existing gate may be weakened.

The native CI workflow builds an isolated review payload, retains its entire
dependency graph/inventory, and renders donor/native fixture pairs. Its artifact
is review evidence and does not grant publication authority. Prepared payload
route derivation remains with the trusted bounded updater. Source or DOM tests
must never be described as browser or live acceptance.

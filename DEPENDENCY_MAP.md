# Dependency and inventory map

Canonical companion to `ARCHITECTURE.md` and `TEST_MATRIX.md` for the technical refactor pass.

Status vocabulary:
- **ACTIVE** — referenced by the current production entrypoint, active bundle manifest, shared standalone shell, or deploy/runtime path.
- **COMPATIBILITY** — retained for an explicit legacy/public compatibility path; do not extend with new behavior.
- **DEPRECATED-CANDIDATE** — an older generation still present in the repository but not selected by the current canonical entrypoint/manifest. This is not permission to delete it; first prove no runtime, deploy, external or test consumer depends on it.
- **DEAD-CANDIDATE** — no required consumer found after repository-wide runtime/CI/deploy/compatibility verification. Still requires a separate deletion PR with regression evidence.
- **PROTECTED** — active external-contract code whose mechanics must not be changed during this refactor without explicit approval.

## Canonical production entrypoints

| Area | Canonical implementation | Status | Notes |
| --- | --- | --- | --- |
| Tour search page | `v2/index.php` | ACTIVE | Canonical search implementation behind `/poisk-turov/`. Currently still owns the legacy `.at-site-header` shell. |
| Search API | `v2/api-v2.php` | ACTIVE / PROTECTED CONTRACT | Selected by `window.V2_CONFIG.api`; preserve Tourvisor-facing behavior. |
| Lead adapter | `v2/lead-adapter-v2.php` | ACTIVE / PROTECTED CONTRACT | Selected by `window.V2_CONFIG.leadApi`; preserve external lead contract and field mapping. |
| Search browser assets | `v2/bundle-manifest-v1.php` + `v2/bundle-v1.php` | ACTIVE | Manifest order is currently behavior-sensitive and must be preserved during migration. |
| Asset URL/version layer | `v2/assets.php` | ACTIVE | Required by `v2/index.php`; current validator intentionally accepts flat basenames only. |
| Shared standalone header | `v2/site-header-v2.php` + `v2/site-header-v2.css` | ACTIVE | Canonical header for homepage/content pages; search migration remains deferred until it is safe. |
| Shared standalone page shell | `v2/site-page-shell-v1.php` + `v2/site-page-v1.css` | ACTIVE | Canonical content-page composition. |
| Shared footer | `v2/site-footer-v1.php` + `v2/site-footer-v1.css` | ACTIVE | Search also renders this footer; keep one physical footer implementation. |
| Design system | `v2/design-system-v1.css` | ACTIVE | Canonical shared tokens/primitives. New page-specific systems should not fork these concepts. |
| Homepage | `v2/home-entry-v1.php` -> `v2/home-v1.php` | ACTIVE | Standalone discovery entrypoint. |
| Country pages | `v2/country-page-v1.php` + `v2/country/**/index.php` | ACTIVE | Shared country renderer plus route wrappers. |
| Search compatibility route | `v2/poisk-turov/index.php` | COMPATIBILITY | Route wrapper only; business logic belongs in the canonical search implementation. |

## Active browser bundle

`v2/bundle-manifest-v1.php` is the current browser-asset source of truth. The following files are therefore **ACTIVE** regardless of whether their names contain historical version suffixes.

### CSS — 27 active files

`design-system-v1.css`, `app.css`, `enhancements.css`, `room-details.css`, `selected-tour-ux.css`, `design-v1.css`, `hotel-details-design.css`, `tour-design-v1.css`, `search-states-design.css`, `anytour-brand.css`, `results-experience-v1.css`, `conversion-confidence-v1.css`, `checkout-experience-v1.css`, `header-current-site.css`, `primary-meal-ux-v1.css`, `mobile-results-filters-v1.css`, `search-progress-ux-v1.css`, `search-dirty-ux-v1.css`, `mobile-search-summary-v1.css`, `search-filters-ux-v1.css`, `product-shell-v1.css`, `br3-control-consistency-v1.css`, `site-footer-v1.css`, `results-layout-guard-v1.css`, `search-header-layout-guard-v1.css`, `search-footer-rhythm-v1.css`, `search-header-shared-shell-v1.css`.

### JavaScript — 32 active files

`header-current-site.js`, `runtime-retry-policy.js`, `runtime-v3.js`, `analytics-v4.js`, `results-renderer-v5.js`, `conversion-confidence-v1.js`, `compare-refresh-guard-v1.js`, `search-continue-v6.js`, `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-return-v1.js`, `tour-controller-v4.js`, `flight-empty-recovery-v1.js`, `selected-tour-description-v1.js`, `checkout-experience-v1.js`, `lead-search-context.js`, `lead-ui-race-guard-v1.js`, `lead-form-guard-v1.js`, `flight-price-sync-v1.js`, `unpriced-flight-price-reset-v1.js`, `price-confidence-v1.js`, `catalogs-v2.js`, `url-primary-catalog-sync-v1.js`, `search-filters-ux-v1.js`, `search-lifecycle-v6.js`, `mobile-results-filters-v1.js`, `primary-meal-ux-v1.js`, `search-progress-ux-v1.js`, `search-complete-recovery-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`, `accessibility.js`.

The 27/32 counts are verified against the current manifest after the mobile shared-shell compatibility layer. Reducing this layering is a refactor goal, but not by deleting modules before behavioral coverage proves equivalence.

## Server-side/support modules currently active

The canonical search entrypoint directly requires `assets.php`, `form-defaults.php`, `analytics-config.php`, `privacy-config.php`, `seo-config.php` and `site-footer-v1.php`. `assets.php` requires `asset-version-v1.php` and `bundle-manifest-v1.php`. `v2/index.php` publishes `api-v2.php` and `lead-adapter-v2.php` through `window.V2_CONFIG`, making those the current canonical endpoint generations.

Lead receiver/bridge/idempotency/price helpers are considered **PROTECTED** until the deploy and external receiver graph is audited end-to-end.

The SEO family (`seo-config.php`, `seo-content-catalog-v1.php`, `seo-internal-links-v1.php`, `seo-page-contract-v1.php`, `seo-page-graph-v1.php`, `seo-page-primitives-v1.php`, `seo-page-registry-v1.php`, `seo-page-types-v1.php`, `seo-publication-manifest-v1.php`, `seo-publishability-v1.php`) is **ACTIVE/FOUNDATION** where referenced by current standalone/SEO routes and CI. Consolidation must preserve the rule that SEO pages hand off to the common search instead of duplicating search business logic.

## Historical-generation candidates requiring proof before removal

These are the first confirmed generation pairs/triples to audit. Their presence is technical debt; their deletion is **not yet approved**.

| Concept | Canonical/current | Older repository generation | Initial classification | Removal gate |
| --- | --- | --- | --- | --- |
| Analytics browser runtime | `analytics-v4.js` | `analytics-v3.js`, `analytics.js` | older files = DEPRECATED-CANDIDATE; analytics contract remains PROTECTED | Prove no runtime, workflow, deploy or compatibility consumer; preserve analytics/Metrika external behavior. |
| Search API | `api-v2.php` | `api.php` | older file = DEPRECATED-CANDIDATE | Prove no route, workflow, live diagnostic or deploy consumer; preserve Tourvisor contract. |
| Lead adapter | `lead-adapter-v2.php` | `lead-adapter.php` | older file = DEPRECATED-CANDIDATE; all lead mechanics PROTECTED | Prove no bridge, workflow, deploy or compatibility consumer; preserve external lead contract and field mapping. |

`.github/workflows/validate-analytics-v4.yml` explicitly owns `analytics-v4.js`, which reinforces v4 as the active generation. That workflow currently validates syntax plus exact source-string contracts and runs on push to `main`; it remains a CI-audit candidate for PR coverage and behavioral diagnostics, not a deletion target.

## Shared-shell duplication currently known

The standalone site uses `site-header-v2.php` / `.at-global-header`, while `v2/index.php` still hardcodes `.at-site-header` and its own desktop/mobile navigation. This is a confirmed duplicate concept, not a reason for an immediate rewrite. The canonical destination is `site-header-v2`; migration must preserve the search page's personal-account/order/contact affordances and leave search/results/tour/lead behavior untouched.

The footer is already materially closer to the target rule because `v2/index.php` calls `v2_render_site_footer()`. New footer implementations are forbidden; consumers should migrate to this shared component.

The current `search-header-shared-shell-v1.css` is **ACTIVE compatibility styling**, not a second canonical header. It exists to keep the legacy search shell visually coherent until a safe component migration is possible.

## Compatibility and external dependencies

| Dependency | Status | Rule |
| --- | --- | --- |
| legacy `/poisk-turov-test/v2/` surface | COMPATIBILITY | Canonical public search remains `/poisk-turov/`; do not build new product logic specifically for the legacy route. |
| `anytour.online/max-search/web-consultant/` scripts loaded by `v2/index.php` | COMPATIBILITY / APPROVED EXTERNAL DEPENDENCY | May be consumed from this repository but neighboring project code must not be modified here. |
| legacy `anytour.online` deploy/lead-bridge sourcing | COMPATIBILITY / MIGRATION DEBT | Decouple only in separately proven slices. Protected lead bridge migration is HIGH risk. |

## Asset-loader constraint blocking safe folder moves

`v2/assets.php::v2_asset()` compares `basename($file)` with the original input and only accepts `[a-zA-Z0-9._-]+`, so browser asset subdirectories are rejected by design. Bundle content versioning likewise resolves manifest members directly below `__DIR__`.

Classification: **ACTIVE architectural constraint / TECH DEBT**.

Required migration sequence before moving browser modules into `shared/search/results/tour/checkout/integrations/...`:

1. introduce a controlled relative-path validator that rejects traversal, absolute paths and non-allowlisted roots;
2. make versioning/bundling resolve exactly the same validated relative path;
3. keep all existing flat manifest entries unchanged initially;
4. add deterministic path-validation and bundle-closure tests;
5. migrate one small asset family at a time after focused and broader coverage is green.

Do not mass-move files simply to match the target tree.

## Target ownership zones

Future moves should be incremental and behavior-neutral:

- `shared/` — header, footer, navigation, UI primitives, design system;
- `search/` — form, catalogs, lifecycle, recovery;
- `results/` — renderer, sorting, comparison, filters;
- `tour/` — selected tour, rooms, flights, hotel detail;
- `checkout/` — lead UI and submission orchestration;
- `integrations/` — Tourvisor and other external adapters;
- `site/` — homepage/content/discovery pages;
- `seo/` — registry, publication, schema, sitemap/internal links;
- `templates/` — base/search/SEO/content composition;
- `tests/` — unit/contracts/e2e/visual/production;
- `scripts/` — build/diagnostics/deploy/CI helpers.

## Repository-wide inventory still required before deletion

Absence from the active browser manifest does **not** mean dead. Complete these audit batches before deleting anything:

1. all non-manifest `v2/*.js` / `v2/*.css` generations and patch layers;
2. PHP endpoint/helper generations, including API/lead compatibility consumers;
3. workflow-only fixtures, diagnostics and source-contract markers;
4. deploy/release scripts that may copy compatibility files without browser references;
5. standalone/site assets outside `v2/` and shared shell implementations;
6. SEO/content migration documents and publication helpers;
7. legacy route aliases/rewrite/deploy dependencies.

For every candidate, record:

`file → classification → canonical replacement → runtime consumers → CI consumers → deploy consumers → compatibility reason → removal evidence required`.

## Refactor safety and removal gates

1. No status may move from DEPRECATED-CANDIDATE to DEAD-CANDIDATE from filename/version alone.
2. No ACTIVE file may be moved until direct references, bundle order, workflow path filters and deploy copy rules are mapped.
3. No DEAD-CANDIDATE may be deleted without a separate narrow PR showing repository-wide consumer proof plus relevant focused/broader regression.
4. Metrika/goals, analytics external contract, Tourvisor external contract and lead external contract remain read-only during this pass.
5. Compatibility routes remain thin wrappers; do not add new business logic to them.
6. One concept -> one implementation: when two active implementations exist, choose the canonical owner, migrate consumers, verify behavior, then remove the duplicate in a separate narrow PR.

Update this map in the same PR whenever a dependency classification materially changes.
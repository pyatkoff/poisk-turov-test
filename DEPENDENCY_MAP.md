# Dependency and inventory map

Canonical companion to `ARCHITECTURE.md` and `TEST_MATRIX.md` for the technical refactor pass.

Status vocabulary:
- **ACTIVE** — referenced by the current production entrypoint, active bundle manifest, shared standalone shell, or deploy/runtime path.
- **COMPATIBILITY** — retained for an explicit legacy/public compatibility path; do not extend with new behavior.
- **DEPRECATED-CANDIDATE** — an older generation still present in the repository but not selected by the current canonical entrypoint/manifest. This is not permission to delete it; first prove no runtime, deploy, external or test consumer depends on it.
- **PROTECTED** — active external-contract code whose mechanics must not be changed during this refactor without explicit approval.

## Canonical production entrypoints

| Area | Canonical implementation | Status | Notes |
| --- | --- | --- | --- |
| Tour search page | `v2/index.php` | ACTIVE | Canonical search implementation behind `/poisk-turov/`. Currently still owns the legacy `.at-site-header` shell. |
| Search API | `v2/api-v2.php` | ACTIVE / PROTECTED CONTRACT | Selected by `window.V2_CONFIG.api`; preserve Tourvisor-facing behavior. |
| Lead adapter | `v2/lead-adapter-v2.php` | ACTIVE / PROTECTED CONTRACT | Selected by `window.V2_CONFIG.leadApi`; preserve external lead contract and field mapping. |
| Search browser assets | `v2/bundle-manifest-v1.php` + `v2/bundle-v1.php` | ACTIVE | Manifest order is currently behavior-sensitive and must be preserved during migration. |
| Shared standalone header | `v2/site-header-v2.php` + `v2/site-header-v2.css` | ACTIVE | Canonical header for homepage/content pages; search migration remains deferred until it is safe. |
| Shared standalone page shell | `v2/site-page-shell-v1.php` + `v2/site-page-v1.css` | ACTIVE | Canonical content-page composition. |
| Shared footer | `v2/site-footer-v1.php` + `v2/site-footer-v1.css` | ACTIVE | Search also renders this footer; keep one physical footer implementation. |
| Design system | `v2/design-system-v1.css` | ACTIVE | Canonical shared tokens/primitives. New page-specific systems should not fork these concepts. |
| Homepage | `v2/home-entry-v1.php` -> `v2/home-v1.php` | ACTIVE | Standalone discovery entrypoint. |
| Country pages | `v2/country-page-v1.php` + `v2/country/**/index.php` | ACTIVE | Shared country renderer plus route wrappers. |
| Search compatibility route | `v2/poisk-turov/index.php` | COMPATIBILITY | Route wrapper only; business logic belongs in the canonical search implementation. |

## Active browser bundle

`v2/bundle-manifest-v1.php` is the current browser-asset source of truth. The following files are therefore **ACTIVE** regardless of whether their names contain historical version suffixes.

### CSS

`design-system-v1.css`, `app.css`, `enhancements.css`, `room-details.css`, `selected-tour-ux.css`, `design-v1.css`, `hotel-details-design.css`, `tour-design-v1.css`, `search-states-design.css`, `anytour-brand.css`, `results-experience-v1.css`, `conversion-confidence-v1.css`, `checkout-experience-v1.css`, `header-current-site.css`, `primary-meal-ux-v1.css`, `mobile-results-filters-v1.css`, `search-progress-ux-v1.css`, `search-dirty-ux-v1.css`, `mobile-search-summary-v1.css`, `search-filters-ux-v1.css`, `product-shell-v1.css`, `br3-control-consistency-v1.css`, `site-footer-v1.css`, `results-layout-guard-v1.css`, `search-header-layout-guard-v1.css`, `search-footer-rhythm-v1.css`.

### JavaScript

`header-current-site.js`, `runtime-retry-policy.js`, `runtime-v3.js`, `analytics-v4.js`, `results-renderer-v5.js`, `conversion-confidence-v1.js`, `compare-refresh-guard-v1.js`, `search-continue-v6.js`, `hotel-actions-v3.js`, `room-details-v3.js`, `selected-tour-return-v1.js`, `tour-controller-v4.js`, `flight-empty-recovery-v1.js`, `selected-tour-description-v1.js`, `checkout-experience-v1.js`, `lead-search-context.js`, `lead-ui-race-guard-v1.js`, `lead-form-guard-v1.js`, `flight-price-sync-v1.js`, `unpriced-flight-price-reset-v1.js`, `price-confidence-v1.js`, `catalogs-v2.js`, `url-primary-catalog-sync-v1.js`, `search-filters-ux-v1.js`, `search-lifecycle-v6.js`, `mobile-results-filters-v1.js`, `primary-meal-ux-v1.js`, `search-progress-ux-v1.js`, `search-complete-recovery-v1.js`, `search-dirty-ux-v1.js`, `mobile-search-summary-v1.js`, `accessibility.js`.

The manifest currently contains 26 CSS files and 32 JavaScript files. Reducing this layering is a refactor goal, but not by deleting modules before behavioral coverage proves equivalence.

## Server-side/support modules currently active

The canonical search entrypoint directly requires `assets.php`, `form-defaults.php`, `analytics-config.php`, `privacy-config.php`, `seo-config.php` and `site-footer-v1.php`. The asset layer in turn owns the active bundle-manifest/bundle path. Lead receiver/bridge/idempotency/price helpers are considered **PROTECTED** until the deploy and external receiver graph is audited end-to-end.

The SEO family (`seo-config.php`, `seo-content-catalog-v1.php`, `seo-internal-links-v1.php`, `seo-page-contract-v1.php`, `seo-page-graph-v1.php`, `seo-page-primitives-v1.php`, `seo-page-registry-v1.php`, `seo-page-types-v1.php`, `seo-publication-manifest-v1.php`, `seo-publishability-v1.php`) is **ACTIVE/FOUNDATION** where referenced by current standalone/SEO routes and CI. Consolidation must preserve the rule that SEO pages hand off to the common search instead of duplicating search business logic.

## Historical-generation candidates requiring proof before removal

These are the first confirmed generation pairs/triples to audit. Their presence is technical debt; their deletion is **not yet approved**.

| Concept | Canonical/current | Older repository generation | Initial classification |
| --- | --- | --- | --- |
| Analytics browser runtime | `analytics-v4.js` | `analytics-v3.js`, `analytics.js` | older files = DEPRECATED-CANDIDATE; analytics contract remains PROTECTED |
| Search API | `api-v2.php` | `api.php` | older file = DEPRECATED-CANDIDATE until route/deploy consumers are proven absent |
| Lead adapter | `lead-adapter-v2.php` | `lead-adapter.php` | older file = DEPRECATED-CANDIDATE; all lead mechanics PROTECTED |

Next audit rule: for each candidate, search repository references, workflow/deploy references and any explicit compatibility route before changing status to DEAD-CANDIDATE. Only a later narrow PR may remove a proven dead file.

## Shared-shell duplication currently known

The standalone site uses `site-header-v2.php` / `.at-global-header`, while `v2/index.php` still hardcodes `.at-site-header` and its own desktop/mobile navigation. This is a confirmed duplicate concept, not a reason for an immediate rewrite. The canonical destination is `site-header-v2`; migration must preserve the search page's personal-account/order/contact affordances and leave search/results/tour/lead behavior untouched.

The footer is already materially closer to the target rule because `v2/index.php` calls `v2_render_site_footer()`. New footer implementations are forbidden; consumers should migrate to this shared component.

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

Do not mass-move the mature `v2/` tree. First make the asset loader safely support controlled subdirectories, then migrate one ownership zone at a time with existing coverage green.

## Refactor safety gates

1. No status may move from DEPRECATED-CANDIDATE to DEAD-CANDIDATE from filename/version alone.
2. No ACTIVE file may be moved until all direct references, bundle order, workflow path filters and deploy copy rules are mapped.
3. Metrika/goals, analytics external contract, Tourvisor external contract and lead external contract remain read-only during this pass.
4. Compatibility routes remain thin wrappers; do not add new business logic to them.
5. One concept -> one implementation: when two active implementations exist, choose the canonical owner, migrate consumers, verify behavior, then remove the duplicate in a separate narrow PR.
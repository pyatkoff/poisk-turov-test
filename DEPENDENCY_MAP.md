# Dependency and inventory map

Canonical companion to `ARCHITECTURE.md` and `TEST_MATRIX.md` for the technical refactor pass.

This document records architectural classification and dependency ownership. It must not duplicate machine-readable inventories that already have a canonical source.

## Status vocabulary

- **ACTIVE** — referenced by the current production entrypoint, active bundle manifest, shared standalone shell, or deploy/runtime path.
- **COMPATIBILITY** — retained for an explicit legacy/public compatibility path; do not extend with new behavior.
- **DEPRECATED-CANDIDATE** — an older generation still present but not selected by the current canonical entrypoint/manifest. This is not permission to delete it; first prove no runtime, deploy, external or test consumer depends on it.
- **DEAD-CANDIDATE** — no required consumer found after repository-wide runtime/CI/deploy/compatibility verification. Deletion still requires a separate narrow PR with regression evidence.
- **PROTECTED** — active external-contract code whose mechanics must not be changed during this refactor without explicit approval.

## Inventory source-of-truth rules

One concept -> one implementation also applies to inventory metadata.

- `v2/bundle-manifest-v1.php` is the **only canonical ordered list** of active V2 browser CSS/JS.
- `scripts/ci/inventory_v2_assets.py` is the canonical deterministic inventory helper for comparing repository browser assets with that manifest.
- This document intentionally does **not** mirror the full manifest list or hard-code active asset counts. Doing so previously drifted from the manifest (the document still said 27 CSS / 32 JS while the manifest had already grown to 31 CSS / 37 JS).
- Absence from the active manifest means only `NON_MANIFEST` until runtime, CI, deploy and compatibility consumers are checked. It does not by itself imply deprecated or dead code.

When the active bundle changes, update the manifest and relevant tests; do not manually maintain a second ordered asset list here.

## Canonical production entrypoints

| Area | Canonical implementation | Status | Notes |
| --- | --- | --- | --- |
| Tour search page | `v2/index.php` | ACTIVE | Canonical search implementation behind `/poisk-turov/`. |
| Search API | `v2/api-v2.php` | ACTIVE / PROTECTED | Preserve Tourvisor-facing behavior and contract. |
| Lead adapter | `v2/lead-adapter-v2.php` | ACTIVE / PROTECTED | Preserve the external lead contract and field mapping. |
| Browser asset manifest | `v2/bundle-manifest-v1.php` | ACTIVE | Ordered browser-asset source of truth. |
| Browser bundle endpoint | `v2/bundle-v1.php` | ACTIVE | Resolves the ordered manifest; current order is behavior-sensitive. |
| Asset URL/version layer | `v2/assets.php` | ACTIVE | Required by the search entrypoint and current bundling/versioning path. |
| Shared standalone header | `v2/site-header-v2.php` + `v2/site-header-v2.css` | ACTIVE | Canonical header direction for public standalone pages. |
| Shared standalone page shell | `v2/site-page-shell-v1.php` + `v2/site-page-v1.css` | ACTIVE | Canonical content-page composition. |
| Shared footer | `v2/site-footer-v1.php` + `v2/site-footer-v1.css` | ACTIVE | Search and standalone consumers should converge here; do not create another footer. |
| Design-system implementation | `v2/design-system-v1.css` | ACTIVE | Implementation filename is historical; canonical product terminology is **AnyTour Design System 2.0**. Do not mass-rename without mapped migration. |
| Homepage | `v2/home-entry-v1.php` -> `v2/home-v1.php` | ACTIVE | Standalone discovery entrypoint. |
| Country pages | `v2/country-page-v1.php` + `v2/country/**/index.php` | ACTIVE | Shared country renderer plus route wrappers. |
| Search compatibility route | `v2/poisk-turov/index.php` | COMPATIBILITY | Route wrapper only; business logic belongs in the canonical search implementation. |

## Active browser bundle

The active browser bundle is whatever `v2_bundle_manifest()` returns from `v2/bundle-manifest-v1.php` on the current commit.

Use:

```bash
python3 scripts/ci/inventory_v2_assets.py
python3 scripts/ci/inventory_v2_assets.py --json
```

The helper verifies manifest shape, duplicate entries, safe relative paths, file existence and repository-vs-manifest membership. `--strict-non-manifest` is intentionally not the default because non-manifest files require consumer proof before classification.

Current active families include the shared header/footer layers, search lifecycle/catalogs, results renderer/filtering/comparison, selected-tour/rooms/flights, checkout/lead UI guards, hotel autocomplete, current-price calendar, passive price observer, country/catalog routing and accessibility. Exact membership and ordering belong only to the manifest.

## Server-side/support modules currently active

The canonical search entrypoint directly requires `assets.php`, `form-defaults.php`, `analytics-config.php`, `privacy-config.php`, `seo-config.php` and `site-footer-v1.php`. `assets.php` requires `asset-version-v1.php` and `bundle-manifest-v1.php`. `v2/index.php` publishes `api-v2.php` and `lead-adapter-v2.php` through `window.V2_CONFIG`, making those the current canonical endpoint generations.

Lead receiver/bridge/idempotency/price helpers are **PROTECTED** until the deploy and external receiver graph is audited end-to-end.

The SEO family (`seo-config.php`, `seo-content-catalog-v1.php`, `seo-internal-links-v1.php`, `seo-page-contract-v1.php`, `seo-page-graph-v1.php`, `seo-page-primitives-v1.php`, `seo-page-registry-v1.php`, `seo-page-types-v1.php`, `seo-publication-manifest-v1.php`, `seo-publishability-v1.php`) is **ACTIVE/FOUNDATION** where referenced by current standalone/SEO routes and CI. SEO pages must hand off to the common search rather than duplicating transactional search logic.

## Historical-generation candidates requiring proof before removal

| Concept | Canonical/current | Older generation | Initial classification | Removal gate |
| --- | --- | --- | --- | --- |
| Analytics browser runtime | `analytics-v4.js` | `analytics-v3.js`, `analytics.js` | DEPRECATED-CANDIDATE | Prove no runtime/workflow/deploy/compatibility consumer; preserve analytics/Metrika behavior. |
| Search API | `api-v2.php` | `api.php` | DEPRECATED-CANDIDATE | Prove no route/workflow/live diagnostic/deploy consumer; preserve Tourvisor contract. |
| Lead adapter | `lead-adapter-v2.php` | `lead-adapter.php` | DEPRECATED-CANDIDATE / PROTECTED | Prove no bridge/workflow/deploy/compatibility consumer; preserve external lead contract and field mapping. |

Similar filenames are evidence for an audit, not evidence for deletion.

## Shared-shell duplication currently known

Standalone pages use `site-header-v2.php` / `.at-global-header`, while the search entrypoint still has a legacy search-header seam. This is a confirmed duplicate concept, but not permission for a broad rewrite. The canonical destination is the shared header/navigation layer; migration must preserve current search affordances and leave search/results/tour/lead behavior unchanged.

The footer already uses `v2_render_site_footer()` in the search path. New footer implementations are forbidden; migrate consumers toward the shared component.

`search-header-shared-shell-v1.css` is ACTIVE compatibility styling, not a second canonical design system/header implementation.

## Compatibility and external dependencies

| Dependency | Status | Rule |
| --- | --- | --- |
| legacy `/poisk-turov-test/v2/` surface | COMPATIBILITY | Canonical public search remains `/poisk-turov/`; do not add product logic specifically for the legacy route. |
| `anytour.online/max-search/web-consultant/` scripts consumed by `v2/index.php` | COMPATIBILITY / APPROVED EXTERNAL DEPENDENCY | May be consumed here; neighboring project code must not be modified. |
| legacy `anytour.online` deploy/lead-bridge sourcing | COMPATIBILITY / MIGRATION DEBT | Decouple only in separately proven slices. Lead bridge migration remains HIGH risk. |

## Asset-loader constraint blocking safe folder moves

`v2/assets.php::v2_asset()` currently accepts flat safe basenames; bundle resolution is likewise rooted directly in `v2/`. Therefore the target `shared/search/results/tour/checkout/integrations/...` browser-asset layout cannot be reached by a blind file move.

Required migration sequence:

1. introduce one controlled relative-path validator rejecting traversal, absolute paths and non-allowlisted roots;
2. make asset URL generation, versioning and bundling use that same validator;
3. leave current flat manifest entries unchanged initially;
4. add deterministic path-validation and bundle-closure tests;
5. migrate one small ownership family at a time after focused and broader coverage is green.

## Target ownership zones

Future moves are incremental and behavior-neutral:

- `shared/` — header, footer, navigation, UI primitives, design system;
- `search/` — form, catalogs, lifecycle, recovery;
- `results/` — renderer, sorting, comparison, filters;
- `tour/` — selected tour, rooms, flights, hotel detail;
- `checkout/` — lead UI and submission orchestration;
- `integrations/` — Tourvisor and other external adapters;
- `site/` — homepage/content/discovery pages;
- `seo/` — registry, publication, schema, sitemap/internal links;
- `templates/` — base/search/SEO/content composition;
- `tests/` — contracts/e2e/visual/production;
- `scripts/` — build/diagnostics/deploy/CI helpers.

## Repository-wide inventory still required before deletion

Audit these batches before deleting anything:

1. all `NON_MANIFEST` V2 JS/CSS generations and patch layers;
2. PHP endpoint/helper generations, including API/lead compatibility consumers;
3. workflow-only fixtures, diagnostics and source-contract markers;
4. deploy/release scripts that may copy compatibility files without browser references;
5. standalone/site assets outside the active V2 browser manifest;
6. SEO/content publication helpers and migration leftovers;
7. legacy route aliases/rewrite/deploy dependencies.

For each candidate record:

`file -> classification -> canonical replacement -> runtime consumers -> CI consumers -> deploy consumers -> compatibility reason -> removal evidence required`.

## Refactor safety and removal gates

1. No status moves from DEPRECATED-CANDIDATE to DEAD-CANDIDATE from filename/version alone.
2. No ACTIVE file moves until direct references, bundle order, workflow path filters and deploy copy rules are mapped.
3. No DEAD-CANDIDATE is deleted without a separate narrow PR showing repository-wide consumer proof plus relevant focused/broader regression.
4. Metrika/goals, analytics external contract, Tourvisor external contract and lead external contract remain read-only during this pass.
5. Compatibility routes remain thin wrappers; do not add new business logic to them.
6. One concept -> one implementation: declare the canonical owner, migrate consumers, verify behavior, then remove the duplicate in a separate narrow PR.
7. Machine-readable inventories must have one canonical source; documentation should reference them rather than copy ordered lists that can drift.

Update this map in the same PR whenever a dependency classification materially changes.

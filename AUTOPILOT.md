# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-01

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

## Ordered work

1. Preserve and improve the mature search flow: full-search filters, results, hotel/tour cards and selected-tour UX.
2. Converge shared header/navigation/footer and broader site visuals using AnyTour Design System 2.0 without replacing the AnyTour logo.
3. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages visually coherent through shared shell primitives rather than route-local overrides.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
6. Keep technical refactor, unresolved legal/payment content and PR #254 deferred until separately authorized/proven safe.

## Material progress / current resume point

PR #606 is verified in production. Its shared site-coherence layer hardened homepage/editorial geometry, min-width/overflow handling, long-copy wrapping, balanced grids, section rhythm, breadcrumbs and narrow-screen CTA behavior. Production deploy checks passed public-page verification, the unchanged lead bridge and live search smoke.

PR #610 converged the remaining shared editorial/country first screen onto AnyTour Design System 2.0. `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages consume the same light, compact visual language as the homepage and tour-search intro. PR validation passed standalone navigation/content, V2 pull-request/startup/branch checks, security and visual suites; production deployment passed public-page verification, unchanged lead-bridge verification and live search smoke.

PR #612 fixed a correctness bug in mobile result filters. Applying/removing mobile filters writes only touched controls into the canonical search form and starts one fresh search through `V2SearchLifecycle`; stars, rating, max price and meal mapping remain tied to canonical values. Production V2 and standalone verification passed.

PR #614 tightened the desktop AnyTour Design System 2.0 advanced-filter rail without changing search semantics. Desktop/tablet hierarchy, spacing, wrapping, control sizing and overflow behavior are stable and production-verified.

PR #617 rebuilt the factual `Лидер продаж` signal on fresh main. It includes all 500 source rows, matches conservatively by Tourvisor result country + normalized hotel name, exposes only the customer-facing badge, and does not reorder results or alter price/search semantics. All PR regressions passed and both production deployment paths completed successfully.

PR #618 converged selected tour → flight choice → lead form on AnyTour Design System 2.0 through a final responsive visual layer rather than changing product behavior. It resolves historical CSS conflicts, long-value wrapping/min-width issues and inconsistent price/facts/flight/lead geometry across 375/430/768/1024/1440. The initial CI run correctly rejected the changed bundle contract; the bundle manifest validator was updated to assert the new final DS2 layer and all 21 PR checks then passed, including selected-tour and whole-V2 visual suites. Both production paths completed successfully: V2 passed Verify V2 and Live search smoke; standalone passed public-page verification, unchanged production lead-bridge verification and Live search smoke.

PR #622 strengthened homepage discovery hierarchy on wide desktop. The primary `Страны и курорты` journey now occupies two columns in a balanced six-column row while the four secondary journeys remain compact; tablet/mobile behavior and the shared DS2 shell are unchanged. Standalone production verification passed public pages, the unchanged lead bridge and live search smoke.

PR #623 improved `/hot/` offer scanning without changing hot-inventory or search semantics. Route/date/nights metadata and factual price are now distinct DS2 hierarchy elements, with the price clearly scannable before the existing verification CTA. PR bundle/security/visual checks passed, and standalone production deployment passed public-page verification, unchanged production lead-bridge verification and live search smoke.

PR #624 corrected the four-office `/contacts/` desktop layout from an awkward generic 3+1 grid to the existing balanced two-column DS2 editorial primitive. Its production deployment completed successfully and passed public-page verification, unchanged production lead-bridge verification and live search smoke.

`/how-to-buy/` was re-audited before changing it. The shared `.sp-step` primitive already renders explicit step numbering, so the proposed extra numbering layer in PR #626 was identified as non-material duplication and closed without merge.

PR #628 fixed a confirmed `/rb/` layout defect: the markup already used `sp-steps--summary`, but there was no layout rule for that primitive, leaving the three summary cards stacked on wide screens. The DS2 editorial owner now renders the summary as three columns from 901px while preserving stacked tablet/mobile behavior. Its standalone deployment was cancelled only because the immediately following main deployment superseded it.

PR #629 fixed a shared country-page DS2 regression. `country-page-v1.php` correctly renders the country intent title as an `h1`, while the old country-intent styling still targeted `h2`; representative country pages therefore missed the intended title scale, margins and wrapping. The later DS2 convergence layer now owns the actual `h1`, including the narrow-screen size. All PR checks passed, including visual checks. The final standalone deployment containing both #628 and #629 passed public-page verification, unchanged production lead-bridge verification and live search smoke.

PR #636 fixed the remaining confirmed tablet breakpoint gap in full-search advanced filters. The mobile sheet owns <=700px and the established desktop DS2 rail owns >=821px, but 701–820px—including the required 768px audit width—was falling back to older generic geometry. A dedicated two-column DS2 tablet layer now provides the same hierarchy, wrapping/min-width discipline, flight toggles, hotel-service section and reset action without changing search lifecycle or filter semantics. The strict startup bundle contract was extended to 35 CSS source inputs while preserving one CSS startup request. All 21 PR checks passed, including the 375/430/768/1024/1440 visual suites. V2 production passed Verify V2 and Live search smoke; standalone/public deployment passed public pages, unchanged lead bridge and Live search smoke. `/hot/` and `/how-to-buy/` were re-audited in the same session and no new confirmed structural defect justified speculative edits.

PR #637 replaced the temporary generated favicon with the owner-supplied AnyTour favicon while preserving the established `/favicon.svg` route used by the homepage and shared standalone shell. It changes no search, analytics or lead behavior. All eight PR checks passed.

PR #638 fixed a confirmed full-search generation defect after `Сбросить дополнительные фильтры`. The existing reset control changed canonical form values programmatically and marked the lifecycle dirty, but desktop autorefresh only listened to input/change events, so the visible hotel results could remain stale after reset. The autorefresh owner now submits one fresh `V2SearchLifecycle` generation after the existing reset handler completes. All eight relevant PR checks passed. The final production release containing #637 and #638 passed V2 Verify V2 and Live search smoke; full anytoour.ru deployment passed public-page verification, unchanged production lead-bridge verification and Live search smoke.

PR #664 removed the last active naming contradiction in the shared design source of truth: the search bundle, homepage and shared editorial shell now consume `design-system-v2.css` as the canonical AnyTour Design System 2.0 token layer. The proven token values were copied unchanged, so this migration is intentionally pixel-neutral. The active bundle validator now fails if `design-system-v1.css` is ever returned to the production manifest. During the same PR, two stale visual gates were corrected rather than weakened: mobile sticky validation now distinguishes the case where the inline search submit is already visible and waits for the actual IntersectionObserver boundary state, while the meal visual gate now validates the current DS2 `Фильтры результатов` rail and visible `food` select instead of the intentionally hidden pre-split quick-meal row. The meal-specific gate also dropped generic bundle triggers and redundant viewport runs; final PR validation passed the full regression set including startup bundles, standalone shell/navigation, header/footer, mobile sticky, meal filter, hotel comparison, selected-tour and whole-V2 visual baseline.

PR #667 fixed a confirmed responsive defect in the shared AnyTour Design System 2.0 header. At 375/430/768 the inline phone was being squeezed between the AnyTour logo and hamburger and became visually tiny, while the same verified contact already remained available as the first item of the mobile menu. The canonical `site-header-v2.css` now hides the duplicate inline action lane at <=768px and lets the hamburger own the right edge; 1024/1440 keep the inline phone. The logo, destinations and all search/Tourvisor/analytics/lead behavior are unchanged. Relevant PR checks passed before merge, including responsive V2 visual, standalone content/navigation, branch bundles and security. Merge SHA: `d5b12e983b59b9c0921151162bb16dd4e7a524f5`.

PR #670 completed the homepage side of shared DS2 shell ownership. `v2/home-v1.css` no longer defines a second route-local global token set or dead `.at-home-header*`/nav/phone implementation; the live homepage consumes the canonical shared `site_header_v1()`/`site-header-v2.css` shell, while route-specific hero/search/content styles remain local. Homepage actions now use canonical `--at-brand`, `--at-accent` and `--at-focus` tokens. The first CI pass exposed a stale standalone-content gate that still required the deprecated homepage token/header implementation, so the gate was corrected to assert canonical DS2 ownership and explicitly reject reintroduction of `.at-home-header`, `--at-orange` or `--at-blue`. All relevant PR checks passed before merge. Production deployment for merge `4026383fad2c5a438b5de803a29cffab26ef8ed9` passed public-page verification, unchanged lead-bridge verification and live search smoke; post-deploy live user journey also passed. The change is intentionally near pixel-neutral, so whole-product score remains 7.1/10 pending larger visual convergence.

Next: resume the results-first visual pass: results toolbar → first hotel card → tour variants → selected-tour handoff at 768/1024/1440, fixing only confirmed hierarchy/spacing/wrapping/overflow defects in canonical DS2 owners. In parallel sample `/country/`, `/country/turkey/` and another representative country page at 375/430/768/1024/1440. Preserve the owner-approved loaded-results local filtering architecture; do not replace it with a new Tourvisor search on every result-filter click. Keep site-wide score at 7.1/10 until a broader visible product slice materially improves.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.

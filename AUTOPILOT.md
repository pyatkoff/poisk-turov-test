# poisk-turov-test — Autopilot State

Updated: 2026-08-30 08:35 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must not be treated as conversion evidence.

The mature tour-search flow remains the strongest product surface at approximately **8.5–9.0/10**, but that is not the score of the whole public site. After the production Design System work through PR #313 and direct five-width production review, the honest whole-site score is now **7.9/10**. The separate legacy implementation of the `/poisk-turov/` header remains a structural seam, but PRs #311–#313 proved that its visible geometry/state can be safely aligned through isolated CSS without touching the mature minified search index.

Current scorecard:
- whole public site / coherent product impression: **7.9/10**
- cross-page visual consistency: **7.8/10**
- header/navigation consistency: **7.1/10** — implementation is still separate, but mobile geometry, phone/menu spacing and active-route state now match the shared shell much more closely
- homepage: **7.25/10**
- country pages: **8.0/10**
- `/hot/`: **7.7/10**
- `/how-to-buy/`: **7.5/10**
- `/contacts/`: **7.55/10**
- `/rb/`: **7.55/10**
- public-site mobile consistency: **8.0/10**
- typography: **7.6/10**
- grid/spacing: **7.7/10**
- brand coherence: **7.8/10**
- tour search reference surface: **8.75/10**

The next milestone remains **site-wide 8.5+ without regressing search**, then 9.0+ after a complete cross-page visual audit.

## Latest material progress

- PR #276 established the first shared shell and fixed migrated navigation that incorrectly jumped back to `anytour.online`.
- PR #278 established `design-system-v1.css` with shared color, typography, spacing, shell, radius, shadow, focus, button/card and responsive tokens/primitives.
- PR #285 completed the first production visual-unification pass for standalone/content pages.
- PR #287 repaired and expanded site-wide visual/navigation guards and explicitly exposed the shell seam: shared `.at-global-header` on homepage/content vs legacy `.at-site-header` on `/poisk-turov/`.
- PR #290 materially strengthened homepage composition and search-card hierarchy without changing search business logic.
- PR #293 added shared breadcrumbs, section headings, resort chips and branded search callouts to country/discovery pages.
- PR #296 applied shared breadcrumb/section/search-callout primitives to `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/`.
- PR #298 removed remaining country-catalog jumps to `anytour.online`; all 14 visible directions now remain inside one AnyTour product before the common search handoff.
- PR #300 added `/hot/` quick starts for 7, 10 and 14 nights through existing common search parameters only.
- PR #303 added the compact “Сравните похожие направления” block to country pages and expanded CI coverage.
- PR #306 fixed the shared heading-specificity defect that flattened card/step hierarchy on `/how-to-buy/`, `/contacts/`, `/rb/` and other standalone content. Production five-width visual/navigation/live-result evidence was green.
- PR #308 fixed homepage visual evidence so screenshots wait for the country selector to leave the transient loading state.
- **PR #311 safely aligned the mobile `/poisk-turov/` legacy header geometry with the shared shell through `header-current-site.css` only.** The hamburger now uses the same right-edge geometry as the shared header; `v2/index.php` and search behavior remained untouched.
- **PR #312 fixed a confirmed mobile spacing collision risk between the search phone and hamburger**, and strengthened the dedicated 375/430/768/1024/1440 search-header layout guard.
- **PR #313 is DONE in production.** The legacy search nav now visibly marks `Поиск туров` with the same soft-blue current-page treatment used by the shared `.at-global-header`, on desktop and inside the mobile menu. This closes another visible continuity seam without touching search/results/recovery/comparison/lead logic.
- PR #313 pre-merge Security, standalone navigation, V2 validation, branch-bundle, selected-tour, meal, trust, visual baseline and dedicated search-header layout checks were green. The dedicated search-header artifact was directly reviewed at desktop 1440 and showed the intended active route with clean one-row geometry.
- PR #313 squash merge is `8989c3a77ac50b7b1be4488ce0b8f978252ea7c5`. V2 deploy run `33296971578` completed successfully including active-V2 validation, copy, V2 verification and live search smoke.
- Production visual baseline run `33297023194` passed. Its fresh desktop 1440 production screenshot was directly reviewed: `Поиск туров` is active, header spacing is clean and the search form/results shell remains intact. No search business contract was changed.

## Current blocker / deferred full search-header migration

The `/poisk-turov/` outer header still uses the legacy `.at-site-header` implementation rather than the shared `.at-global-header`. The mature search page is concentrated in large/minified `v2/index.php`; replacing that whole file just to swap the component remains an unnecessary regression risk.

**Important resume correction:** the whole search-header slice is no longer treated as completely blocked. PRs #311–#313 demonstrated that isolated visual shell fixes in `v2/header-current-site.css` are safe and should continue when a concrete defect is confirmed. What remains deferred is only the **full component migration** onto `site-header-v2` until an atomic patch/extraction path is available.

## Exact next work order — Design System 1.0

1. **Continue the production journey audit** `homepage → country/hot → search → results → selected tour → lead` at 375/430/768/1024/1440. Fix confirmed shell-side spacing, wrapping, hierarchy or continuity defects; do not perturb mature search state/recovery logic.
2. **Prioritize the homepage as the weakest safe standalone surface**, but only for evidence-backed hierarchy/spacing improvements. Reassess `/how-to-buy/`, `/contacts/` and `/rb/` before adding more density.
3. **Continue safe legacy search-shell alignment through isolated CSS when concrete defects are found.** Keep full `.at-global-header` component migration deferred until an atomic edit/extraction path exists.
4. **After shell unification**, deepen reviewed content/SEO inventory and live-price discovery modules.

## Mandatory protections

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX and existing regression guards remain protected. Previous fixes for completed-search recovery, stale lifecycle isolation, room/flight recovery, pending/priced flight confidence, fuel fallback, comparison, return/focus and lead recovery must remain green.

Standalone architecture remains explicit: `https://anytoour.ru/` is the homepage and `https://anytoour.ru/poisk-turov/` is full search. Legacy `/poisk-turov-test/v2/` remains compatibility-only and canonically consolidates to standalone search.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is the allowed V2/standalone scope only.
- Do not redesign/replace the AnyTour logo.
- Do not modify neighboring projects, server config outside allowed scope, Yandex Metrika/goals, analytics contract, Tourvisor contract, or existing lead-sending mechanism.
- Preserve verified social/app destinations.
- Legal/payment migration remains deferred until source content/requisites are reconciled.
- PR #254 remains deferred unless freshly reassessed and proven safe; do not auto-merge its separate DB/platform architecture.
- Priority remains: production broken → lead loss → incorrect data → site-wide visual incoherence/poor UX → responsive stability → content/SEO → cosmetic/refactor.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one task is blocked, record/defer it and continue independent safe work.

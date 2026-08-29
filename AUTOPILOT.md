# poisk-turov-test — Autopilot State

Updated: 2026-08-30 01:07 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The previous 9.0 scores describe the mature **tour-search product flow**, not the visual quality of the entire public anytoour.ru site. Owner review on 2026-08-29 identified the material cross-page defect: the search experience and the rest of the public site use visibly different composition, spacing and visual language; migrated pages still need to read as one coherent AnyTour product.

Production visual evidence after PR #285 now justifies a cautious site-wide re-score:
- whole public site / coherent product impression: **7.0/10**
- cross-page visual consistency: **6.75/10**
- header/navigation consistency: **6.5/10** — content/home are unified, but `/poisk-turov/` still uses the legacy search header
- homepage: **6.75/10**
- country pages: **7.0/10**
- `/hot/`: **7.0/10**
- `/how-to-buy/`: **6.75/10**
- `/contacts/`: **7.0/10**
- public-site mobile consistency: **7.25/10**
- typography: **7.0/10**
- grid/spacing: **7.0/10**
- brand coherence: **7.0/10**
- tour search itself remains the strongest reference surface at approximately **8.5–9.0/10**.

The next milestone is **site-wide 8.5+ without regressing the search flow**, then 9.0+ after a complete cross-page visual audit. Do not claim the whole site is 9/10 merely because search-flow engineering guards are green.

## Latest material progress

- PR #276 shipped the first shared-shell slice: `SITE_QUALITY_SCORECARD.md`, shared `site-header-v2.php` / `site-header-v2.css`, homepage and standalone content pages on the same header, and search-header visual alignment. It also fixed migrated anytoour.ru navigation that incorrectly jumped back to anytour.online. Release `ec00bcde39fed67be4df88371fbfebcd9048ce10` was production-green.
- PR #278 shipped the next Design System 1.0 slice. New `design-system-v1.css` establishes shared brand/text/line/surface colors, 1180px shell, radii, shadows, spacing, section rhythm, focus treatment, type/container/card/button/breadcrumb primitives and responsive defaults.
- The shared token layer loads on the homepage, standalone content shell and first in the V2/search CSS bundle. Search JavaScript and business logic were not changed.
- `site-page-v1.css` no longer carries a duplicate root palette or obsolete `.sp-header/.sp-nav` shell left behind after the shared-header migration. Content hero/type/card/button/grid rhythm uses shared tokens; grids collapse predictably at 1024/768/560 instead of page-specific ad-hoc behavior.
- The shared community/footer styling uses the same shell/tokens and has safer narrow-screen stacking. Existing verified destinations and legal/payment content were not changed.
- PR #285 completed the first meaningful production visual-unification pass for standalone/content pages. It strengthened the shared content hero, normalized page background/spacing/card radius/shadows/type hierarchy and clearer country/office card treatment without changing content routes, Tourvisor, Metrika, lead contract, logo or legal/payment behavior.
- PR #285 squash merge `83a85e358c288f40a8ff2ad8444423d338fa161d` deployed successfully in `Deploy anytoour.ru` run `33279683684`. Public pages, unchanged lead bridge and live search smoke were green.
- Production visual evidence after #285 passed at **375/430/768/1024/1440** across homepage, search, contacts, how-to-buy, early booking, hot tours, country catalog and Turkey. Representative full-page captures showed the content shell materially more coherent, with no confirmed horizontal overflow/wrapping defect in the audited pages.
- The post-deploy navigation workflow exposed a stale test contract and, more importantly, a real remaining architecture seam: homepage/content pages use shared `.at-global-header`, while `/poisk-turov/` still renders the older `.at-site-header` and its separate navigation/mobile-menu implementation.
- PR #287 repaired the stale navigation guard instead of hiding that seam. It now validates both current contracts honestly across 375/430/768/1024/1440 and expands production visual evidence to `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` plus Turkey/Egypt/UAE/Thailand/Russia.
- Latest PR #287 content-live, navigation-live and security checks passed. Squash merge `17656f37a01bc85ce45249d521415ef5046a9e1b` landed on main. #287 is CI/visual-guard work only; it did not alter product code or require a product deploy.
- This production evidence supports moving the honest whole-site score from **6.5 → 7.0**, not higher. The largest visible structural gap is now the search-header split; editorial pages also remain lighter/less developed than the mature search experience.

## Primary product objective

Make `https://anytoour.ru/` feel like one modern AnyTour product across:
`homepage → country/destination → hot tours → search → results → selected tour → lead`, plus `/contacts/`, `/how-to-buy/` and `/rb/`.

The search experience is the visual/interaction reference, but do not blindly copy search-only density into editorial pages. Build a shared AnyTour design system and shared site shell.

## Exact next work order — Visual Unification / Design System 1.0

1. **Migrate the remaining search header safely.** `/poisk-turov/` still has a separate `at-site-header`/`at-site-nav`/mobile-menu implementation. Map required legacy affordances first (including personal/order links), then migrate only the outer header/navigation onto `site-header-v2` where safe. Preserve the mature search form/results/selected-tour behavior and keep all search/flight/price/comparison/lead regressions green.
2. **Re-run the cross-page journey audit.** Verify `homepage → country → hot/search → results → tour → lead` at 375/430/768/1024/1440 after any shell change.
3. **Shared orientation primitives.** Add/normalize breadcrumbs and editorial hierarchy where production evidence shows weak orientation, using existing Design System primitives rather than page-local shell CSS.
4. **Homepage hierarchy/discovery improvement.** Improve composition and travel discovery while routing useful blocks into the existing search instead of duplicating search logic.
5. **Country/hot discovery depth.** Improve `/country/`, representative country pages and `/hot/` on shared primitives; useful discovery modules must hand off to the common search/API.
6. **Polish supporting pages only where evidence warrants it.** `/contacts/`, `/how-to-buy/`, `/rb/` are now structurally coherent; prioritize remaining hierarchy/spacing weaknesses over decorative flourishes.
7. **Only after shell unification:** deepen content, real-price discovery modules and SEO inventory. Do not fork search business logic.

## Design principles

- One product, not a collection of migrated pages.
- Shared primitives before page-specific CSS patches.
- Search remains fast, information-dense and conversion-oriented; editorial pages may be more visual/emotional but must share the same brand system.
- Prefer strong photography/content only where it materially helps travel discovery; do not make core search slower or visually noisy.
- Every responsive visual change must be checked at 375/430/768/1024/1440.
- Fix confirmed crooked spacing, wrapping, overflow, duplicated shell, inconsistent header/footer and hierarchy defects before cosmetic flourishes.
- Avoid framework-only abstraction that produces no visible improvement.

## Existing search-product protections remain mandatory

Search, Waiting/Recovery, Results/Comparison, Selected Tour, Flights/Price, Lead UX and their existing regression guards remain protected. Previous production fixes for completed-search recovery, second-tour isolation, room/flight recovery, pending/priced flight confidence, fuel fallback, comparison, return/focus and lead recovery must remain green.

Standalone architecture remains explicit: `https://anytoour.ru/` is the homepage and `https://anytoour.ru/poisk-turov/` is the full search. Legacy `/poisk-turov-test/v2/` remains compatibility-only, `noindex,follow`, canonically consolidating to the standalone search.

## Guardrails

- Work only inside `pyatkoff/poisk-turov-test`; production deploy scope is the allowed V2/standalone scope only.
- Do not redesign/replace the existing AnyTour logo.
- Do not modify neighboring projects, global site assets or server config outside allowed scope.
- Do not change Yandex Metrika configuration/goals or analytics contract.
- Do not change the existing lead-sending mechanism/external contract.
- Do not change the Tourvisor request/data contract merely for visual work.
- Preserve valid social/app destinations.
- Legal/payment migration remains deferred until source content/requisites are reconciled.
- PR #254 remains deferred unless freshly reassessed; do not auto-merge its separate DB/platform architecture.
- Production breakage → lead loss → incorrect data → site-wide visual incoherence/poor UX → responsive stability → content/SEO → cosmetic/refactor.
- CI green alone is not DONE; require relevant functional/production/visual evidence.
- If one task is blocked, record/defer it and immediately continue independent safe work.

## Explicitly inactive until owner launches traffic

Live conversion optimization; traffic-based product conclusions; browser-session funnel analysis; waiting for `search → tour → lead` samples; A/B-like conclusions from owner/team visits.

Absence of traffic is expected and is never a blocker. Visual/product development continues.

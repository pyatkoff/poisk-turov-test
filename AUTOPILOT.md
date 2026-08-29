# poisk-turov-test — Autopilot State

Updated: 2026-08-30 00:05 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The previous 9.0 scores describe the mature **tour-search product flow**, not the visual quality of the entire public anytoour.ru site. Owner review on 2026-08-29 identified the material cross-page defect: the search experience and the rest of the public site use visibly different composition, spacing and visual language; migrated pages still need to read as one coherent AnyTour product.

Use this honest site-wide baseline until production visual evidence across all required viewports justifies a re-score:
- whole public site / coherent product impression: **6.5/10**
- cross-page visual consistency: **5.5/10**
- header/navigation consistency: **5.5/10** baseline before the first unification releases
- homepage: **6.5/10**
- country pages: **6.0/10**
- `/hot/`: **6.0–6.5/10**
- `/how-to-buy/`: **6.0/10**
- `/contacts/`: **6.0/10**
- public-site mobile consistency: **6.5–7.0/10**
- typography: **6.5/10**
- grid/spacing: **6.0/10**
- brand coherence: **6.5/10**
- tour search itself remains the strongest reference surface at approximately **8.5–9.0/10**.

The next milestone is **site-wide 8.5+ without regressing the search flow**, then 9.0+ after a complete cross-page visual audit. Do not claim the whole site is 9/10 merely because search-flow engineering guards are green.

## Latest material progress

- PR #276 shipped the first shared-shell slice: `SITE_QUALITY_SCORECARD.md`, shared `site-header-v2.php` / `site-header-v2.css`, homepage and standalone content pages on the same header, and search-header visual alignment. It also fixed migrated anytoour.ru navigation that incorrectly jumped back to anytour.online. Release `ec00bcde39fed67be4df88371fbfebcd9048ce10` was production-green.
- PR #278 shipped the next Design System 1.0 slice. New `design-system-v1.css` establishes shared brand/text/line/surface colors, 1180px shell, radii, shadows, spacing, section rhythm, focus treatment, type/container/card/button/breadcrumb primitives and responsive defaults.
- The shared token layer now loads on the homepage, the standalone content shell and first in the V2/search CSS bundle. Search JavaScript and business logic were not changed.
- `site-page-v1.css` no longer carries a duplicate root palette or obsolete `.sp-header/.sp-nav` shell left behind after the shared-header migration. Content hero/type/card/button/grid rhythm now uses shared tokens; grids collapse predictably at 1024/768/560 instead of page-specific ad-hoc behavior.
- The shared community/footer styling now uses the same shell/tokens and has safer narrow-screen stacking: social and app buttons become single-column at phone widths, while legal/payment rows can wrap without forcing horizontal overflow. Existing verified destinations and legal/payment content were not changed.
- Two CI guards still encoded the previous duplicated architecture (legacy standalone header selectors and a hard-coded 23-file CSS bundle). They were updated to validate the new shared Design System contract instead of forcing duplication back in.
- The unpriced-flight guard exposed a separate stale test expectation: production correctly says `цена тура из поиска`, while the test required exact `Цена из поиска`. The guard now accepts the grounded wording semantically. Flight price reset, fuel fallback, pending-price lead context and product code were not changed.
- PR #278 final head `f9ec3e3559b252b231dc57e899e9b7cfc87d6fb3` passed the latest functional and visual suite: V2 visual baseline, PR visual, selected tour, meal visibility, B5 trust, startup/branch bundles, PHP 8.3, security, standalone navigation/content/home handoff, footer, SEO, comparison, flight recovery, unpriced-flight, selected-tour return and lead/race guards.
- Squash merge `26f17595f4b87ee925e4238e89b20bf9696f461f` deployed successfully to `anytoour.ru` in run `33277570431`: standalone validation → copy → public-page verification → unchanged lead bridge → live search smoke all passed.
- **Do not raise site-wide scores yet.** The architecture and responsive guards improved materially, but the scorecard requires production cross-page visual evidence at all required widths before score movement.

## Primary product objective

Make `https://anytoour.ru/` feel like one modern AnyTour product across:
`homepage → country/destination → hot tours → search → results → selected tour → lead`, plus `/contacts/`, `/how-to-buy/` and `/rb/`.

The search experience is the visual/interaction reference, but do not blindly copy search-only density into editorial pages. Build a shared AnyTour design system and shared site shell.

## Exact next work order — Visual Unification / Design System 1.0

1. **Production cross-page audit now.** Inspect deployed `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative Turkey/Egypt/UAE/Thailand/Russia pages at 375/430/768/1024/1440. Record concrete remaining header, grid, typography, spacing, card, footer, overflow and responsive inconsistencies.
2. **Migrate confirmed weak pages onto shared primitives.** Use `design-system-v1.css`, shared header and shared footer rather than adding new page-local palettes/shells. Reuse existing AnyTour brand assets; **do not redesign or replace the logo**.
3. **Finish one shared header/navigation contract.** Close production-visible differences between search and content pages, including active states, mobile menu behavior, vertical rhythm and any remaining legacy-only search-header markup where changing it is safe.
4. **One shared footer.** Keep verified MAX/Telegram/VK/App Store/Google Play destinations and existing safe legal destinations, normalize composition across all pages, and eliminate duplicate footer/pre-footer structures.
5. **Homepage hierarchy rebuild.** Improve composition and travel discovery while routing useful blocks into the existing search instead of duplicating search logic.
6. **Weak-page visual rebuild on shared primitives:** `/hot/`, `/country/` + country pages, `/contacts/`, `/how-to-buy/`, `/rb/`. Preserve valid routes/content while fixing layout, hierarchy, cards, spacing and responsive behavior. Do not migrate unresolved legal/payment content.
7. **Search-shell alignment.** Keep the mature search form/results/selected-tour UX intact while matching public-site shell, typography and rhythm.
8. **Cross-page journey audit.** Verify `homepage → country → hot/search → results → tour → lead` feels continuous at 375/430/768/1024/1440.
9. **Only after visual unification:** deepen homepage/country/hot-tour content, real-price discovery modules and SEO inventory. Content blocks must route to the shared search/API rather than fork business logic.

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

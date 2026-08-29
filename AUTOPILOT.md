# poisk-turov-test — Autopilot State

Updated: 2026-08-29 23:56 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The previous 9.0 scores describe the mature **tour-search product flow**, not the visual quality of the entire public anytoour.ru site. Owner review on 2026-08-29 identified the material cross-page defect: the search experience and the rest of the public site use visibly different headers, composition, spacing and visual language; migrated pages feel uneven and the site does not yet read as one coherent AnyTour product.

Use this honest site-wide baseline until production visual evidence across all required viewports justifies a re-score:
- whole public site / coherent product impression: **6.5/10**
- cross-page visual consistency: **5.5/10**
- header/navigation consistency: **5.5/10** baseline before the first unification release
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

- PR #276 shipped the first Design System 1.0 slice. It added `SITE_QUALITY_SCORECARD.md`, a shared `site-header-v2.php` + `site-header-v2.css`, moved the homepage and all `site-page-shell-v1.php` pages onto the shared header, and visually aligned the search header to the same one-row system without changing search mechanics.
- During the rollout, stale CI contracts still expected duplicated `.at-home-nav` / `.sp-nav` headers and old legacy homepage links. Those guards were updated to validate the new shared header architecture instead of reintroducing old duplicated markup.
- A real cross-page routing defect was also fixed: on `anytoour.ru` the search header still rewrote migrated public sections back to `anytour.online`. Migrated standalone routes now stay local; unresolved legal/payment and undeployed destinations continue to fall back to legacy destinations.
- PR #276 head `a079521389021cb5f6441cbdca99fda1a1eb2e77` passed all 14 PR workflows including standalone navigation/home handoff, PHP 8.3, security, search visual baseline, selected-tour and trust checks.
- Squash merge `ec00bcde39fed67be4df88371fbfebcd9048ce10` is deployed to `anytoour.ru` in run `33277124959`: validate → copy → public page verification → unchanged lead bridge → live search smoke all passed.
- **Do not raise site-wide scores yet.** The next step is a production cross-page responsive visual audit after this first shared-header release.

## Primary product objective

Make `https://anytoour.ru/` feel like one modern AnyTour product across:
`homepage → country/destination → hot tours → search → results → selected tour → lead`, plus `/contacts/`, `/how-to-buy/` and `/rb/`.

The search experience is the visual/interaction reference, but do not blindly copy search-only density into editorial pages. Build a shared AnyTour design system and shared site shell.

## Exact next work order — Visual Unification / Design System 1.0

1. **Production cross-page audit now.** Inspect the newly deployed `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative Turkey/Egypt/UAE/Thailand/Russia pages at 375/430/768/1024/1440. Record concrete remaining header, grid, typography, spacing, card, footer, overflow and responsive inconsistencies.
2. **Shared design tokens/primitives.** Consolidate container widths, spacing scale, type hierarchy, colors, borders/radii, shadows, buttons, links, chips, cards, section spacing, breadcrumbs, focus/hover states and responsive rules. Reuse existing AnyTour brand assets; **do not redesign or replace the logo**.
3. **Finish one shared header/navigation contract.** The first shared header is live; now close production-visible differences between search and content pages, including active states, mobile menu behavior, vertical rhythm and any remaining legacy-only search-header markup where changing it is safe.
4. **One shared footer.** Keep verified MAX/Telegram/VK/App Store/Google Play destinations and existing safe legal destinations, normalize spacing/typography/composition across all pages, and eliminate duplicate footer/pre-footer structures.
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

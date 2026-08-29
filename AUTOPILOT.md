# poisk-turov-test — Autopilot State

Updated: 2026-08-29 23:15 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must **not** be treated as conversion evidence.

The previous 9.0 scores describe the mature **tour-search product flow**, not the visual quality of the entire public anytoour.ru site. Owner review on 2026-08-29 identified the material cross-page defect: the search experience and the rest of the public site use visibly different headers, composition, spacing and visual language; migrated pages feel uneven and the site does not yet read as one coherent AnyTour product.

Use this honest site-wide baseline until visual evidence justifies a re-score:
- whole public site / coherent product impression: **6.5/10**
- cross-page visual consistency: **5.5/10**
- header/navigation consistency: **5.5/10**
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

## Primary product objective

Make `https://anytoour.ru/` feel like one modern AnyTour product across:
`homepage → country/destination → hot tours → search → results → selected tour → lead`, plus `/contacts/`, `/how-to-buy/` and `/rb/`.

The search experience is the visual/interaction reference, but do not blindly copy search-only density into editorial pages. Build a shared AnyTour design system and shared site shell.

## Exact next work order — Visual Unification / Design System 1.0

1. **Audit before redesign.** Capture/inspect the current production pages at 375/430/768/1024/1440: `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative Turkey/Egypt/UAE/Thailand/Russia pages. Record concrete header, grid, typography, spacing, card, footer, overflow and responsive inconsistencies. Do not rely on the old search-only scorecard.
2. **Create a site-wide quality scorecard.** Track every public page plus cross-page visual consistency, header/navigation, typography, grid/spacing, responsive behavior, brand coherence, trust and search handoff. Re-score only from production/visual evidence.
3. **Design System 1.0.** Consolidate reusable tokens/primitives inside this repository: container widths, spacing scale, type hierarchy, colors, borders/radii, shadows, buttons, links, chips, cards, section spacing, breadcrumbs, focus/hover states and responsive rules. Reuse existing AnyTour brand assets; **do not redesign or replace the logo**.
4. **One shared header/navigation.** Build a single coherent header behavior for public standalone pages and search where technically safe. Same logo treatment, container, navigation hierarchy, mobile menu behavior, active states and CTA language. Avoid parallel page-specific header copies.
5. **One shared footer.** Keep the verified MAX/Telegram/VK/App Store/Google Play destinations and existing safe legal destinations, but normalize spacing, typography and composition across all pages. No duplicate footer/pre-footer structures.
6. **Homepage rebuild on the shared shell.** Improve hierarchy and visual composition, then add useful travel discovery blocks that hand off into the existing search rather than duplicate search logic. Favor useful destination/category entry points and trust over decorative filler.
7. **Migrate weak public pages onto the shared shell:** `/hot/`, `/country/` and country pages, `/contacts/`, `/how-to-buy/`, `/rb/`. Preserve valid content/routes while fixing layout, hierarchy, cards, spacing and responsive behavior. Do not migrate unresolved legal/payment content.
8. **Search-shell alignment.** Bring `/poisk-turov/` header/footer/shell into the same public-site system without weakening the mature search form/results/selected-tour UX or changing Tourvisor behavior.
9. **Cross-page journey audit.** Verify `homepage → country → hot/search → results → tour → lead` feels visually continuous at 375/430/768/1024/1440. Check that returning/navigation does not jump between visibly different shells.
10. **Only after visual unification:** deepen homepage/country/hot-tour content, real-price discovery modules and SEO inventory. Content blocks must route to the shared search/API rather than fork business logic.

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

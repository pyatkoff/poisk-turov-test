# poisk-turov-test — Autopilot State

Updated: 2026-08-30 02:05 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must not be treated as conversion evidence.

The mature tour-search flow remains the strongest product surface at approximately **8.5–9.0/10**, but that score is not the score of the whole public site. After the latest country/discovery pass the honest site-wide score is **7.2/10**: materially improved from the original 6.5 baseline, but still held back by the separate legacy header on `/poisk-turov/` and lighter editorial depth outside search.

Current scorecard:
- whole public site / coherent product impression: **7.2/10**
- cross-page visual consistency: **7.0/10**
- header/navigation consistency: **6.5/10** — homepage/content use shared `.at-global-header`; `/poisk-turov/` still uses `.at-site-header`
- homepage: **7.25/10**
- country pages: **7.5/10**
- `/hot/`: **7.0/10**
- `/how-to-buy/`: **6.75/10**
- `/contacts/`: **7.0/10**
- public-site mobile consistency: **7.4/10**
- typography: **7.2/10**
- grid/spacing: **7.2/10**
- brand coherence: **7.25/10**
- tour search reference surface: **8.75/10**

The next milestone remains **site-wide 8.5+ without regressing search**, then 9.0+ after a complete cross-page visual audit.

## Latest material progress

- PR #276 established the first shared shell and fixed migrated navigation that incorrectly jumped back to `anytour.online`.
- PR #278 established `design-system-v1.css` with shared color, typography, spacing, shell, radius, shadow, focus, button/card and responsive tokens/primitives.
- PR #285 completed the first production visual-unification pass for standalone/content pages. Its production deploy was green across public pages, unchanged lead bridge and live search smoke; visual evidence covered 375/430/768/1024/1440.
- PR #287 repaired and expanded site-wide visual/navigation guards and explicitly exposed the real remaining shell seam: shared `.at-global-header` on homepage/content vs legacy `.at-site-header` on `/poisk-turov/`.
- PR #290 materially strengthened homepage composition and search-card hierarchy without changing search business logic.
- PR #293 added a reusable standalone breadcrumb primitive plus responsive section-heading, resort-chip and branded search-callout primitives. `/country/` and representative country pages now have clearer orientation, stronger editorial hierarchy and a more obvious handoff into the common live search instead of duplicating search logic.
- PR #293 squash merge `cb248e603ccd3725f335a1ff11a0c782a33405a0` deployed successfully in `Deploy anytoour.ru` run `33282245828`. Public pages, unchanged lead bridge and live search smoke were all green.
- PR #293 pre-merge visual/functional CI was green, including standalone content visual checks, V2 visual checks, country route handoff, PHP runtime, security and branch/startup guards.
- The post-deploy `Visual standalone content live` run exposed a stale test assumption rather than a new product regression: it incorrectly required `.at-global-header` on `/poisk-turov/` even though the search-header migration is explicitly still deferred. The production search page correctly reported exactly one legacy `.at-site-header`, while homepage reported the shared header correctly.
- Follow-up CI slice now changes that visual guard to remain strict and honest: non-search pages must have exactly one shared header and no legacy header; search must have exactly one legacy header and no shared header until the migration is completed. This prevents the known seam from being hidden while restoring useful post-deploy visual coverage.

## Current blocker / deferred search-header slice

The highest-priority structural gap remains `/poisk-turov/` outer header/navigation. The search page is concentrated in a large/minified `v2/index.php`; with the currently available GitHub connector, changing it requires replacing the whole file rather than applying a small atomic patch. Replacing that mature search file just to alter the shell is an unnecessary regression risk.

Therefore the header migration is **deferred for tooling/safe-patching reasons, not abandoned**. Required migration behavior is already mapped: preserve personal-account and order/contact affordances, migrate only the outer shell onto `site-header-v2`, and leave search form/results/selected-tour/lead logic untouched. Resume this slice only when an atomic patch/edit path is available or after first safely de-minifying/extracting the header without changing behavior.

## Exact next work order — Design System 1.0

1. **Safely resolve the search-header seam** when an atomic patch path is available. Preserve personal/order affordances and all mature search regressions.
2. **Continue shared orientation primitives** on `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` only where visual evidence shows weak hierarchy; do not add decorative complexity for its own sake.
3. **Re-run the whole journey** `homepage → country → hot/search → results → selected tour → lead` at 375/430/768/1024/1440 after shell changes.
4. **Deepen country/hot discovery** with useful travel-oriented modules that hand off to the common search/API; do not fork search business logic.
5. **After shell unification**, deepen content/SEO inventory and real-price discovery modules.

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

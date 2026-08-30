# poisk-turov-test — Autopilot State

Updated: 2026-08-30 03:03 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must not be treated as conversion evidence.

The mature tour-search flow remains the strongest product surface at approximately **8.5–9.0/10**, but that is not the score of the whole public site. After PR #296 production deployment and representative production visual review, the honest whole-site score is now **7.4/10**. The remaining largest structural seam is still the separate legacy header on `/poisk-turov/`.

Current scorecard:
- whole public site / coherent product impression: **7.4/10**
- cross-page visual consistency: **7.3/10**
- header/navigation consistency: **6.5/10** — homepage/content use shared `.at-global-header`; `/poisk-turov/` still uses `.at-site-header`
- homepage: **7.25/10**
- country pages: **7.5/10**
- `/hot/`: **7.5/10**
- `/how-to-buy/`: **7.25/10**
- `/contacts/`: **7.4/10**
- `/rb/`: **7.4/10**
- public-site mobile consistency: **7.6/10**
- typography: **7.35/10**
- grid/spacing: **7.45/10**
- brand coherence: **7.4/10**
- tour search reference surface: **8.75/10**

The next milestone remains **site-wide 8.5+ without regressing search**, then 9.0+ after a complete cross-page visual audit.

## Latest material progress

- PR #276 established the first shared shell and fixed migrated navigation that incorrectly jumped back to `anytour.online`.
- PR #278 established `design-system-v1.css` with shared color, typography, spacing, shell, radius, shadow, focus, button/card and responsive tokens/primitives.
- PR #285 completed the first production visual-unification pass for standalone/content pages.
- PR #287 repaired and expanded site-wide visual/navigation guards and explicitly exposed the real remaining shell seam: shared `.at-global-header` on homepage/content vs legacy `.at-site-header` on `/poisk-turov/`.
- PR #290 materially strengthened homepage composition and search-card hierarchy without changing search business logic.
- PR #293 added shared breadcrumbs, section headings, resort chips and branded search callouts to country/discovery pages.
- PR #294 made the visual guard explicitly track the known legacy search-header seam instead of masking it or failing every deploy.
- PR #295 added architecture/test source-of-truth documentation only; no runtime behavior changed.
- **PR #296 is DONE in production.** It applied the shared breadcrumb, section-heading and branded search-callout primitives to `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/`, removed ad-hoc spacing hacks and made the path into the common search visually consistent without duplicating search logic.
- PR #296 squash merge is `bcd7c4e79790474793c7124861db36dd5ca8d798`. Production deploy run `33284574693` completed successfully: public-page verification, unchanged lead bridge and live search smoke were all green.
- PR visual run `33284486384` passed at 375/430/768/1024/1440. Production visual run `33284643524` also passed at all five widths across homepage, search, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, country catalog and representative countries. Production navigation live run `33284643544` and root/search visual run `33284643513` were green.
- Representative production screenshots were reviewed directly: desktop `/hot/` has coherent breadcrumb → hero → section/cards → branded search handoff → community/footer hierarchy; mobile `/how-to-buy/` keeps all eight steps, payment warning and CTA readable without horizontal overflow.
- A CI blind spot was fixed in #296: direct changes to the standalone page PHP files and country page files now trigger the five-width `Visual standalone content live` workflow. Previously those page-only edits could miss the dedicated visual job.

## Current blocker / deferred search-header slice

The highest-priority structural gap remains `/poisk-turov/` outer header/navigation. The search page is concentrated in a large/minified `v2/index.php`; with the currently available GitHub connector, changing it requires replacing the whole file rather than applying a small atomic patch. Replacing that mature search file just to alter the shell is an unnecessary regression risk.

Therefore the header migration is **deferred for tooling/safe-patching reasons, not abandoned**. Required migration behavior is mapped: preserve personal-account and order/contact affordances, migrate only the outer shell onto `site-header-v2`, and leave search form/results/selected-tour/lead logic untouched. Resume this slice only when an atomic patch/edit path is available or after first safely extracting/de-minifying the header without behavior change.

## Exact next work order — Design System 1.0

1. **Run the whole production journey audit** `homepage → country → hot/search → results → selected tour → lead` at 375/430/768/1024/1440, focusing on visual handoffs between standalone and mature search surfaces rather than search internals already covered by regressions.
2. **Safely resolve the search-header seam** when an atomic patch path is available. Preserve personal/order affordances and all mature search regressions.
3. **Deepen `/hot/` and country discovery** with genuinely useful travel-oriented modules that hand off to the common search/API; do not fork search business logic.
4. **After shell unification**, deepen content/SEO inventory and real-price discovery modules.

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

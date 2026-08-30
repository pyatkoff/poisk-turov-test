# poisk-turov-test — Autopilot State

Updated: 2026-08-30 07:20 +02:00

Operational companion to `AGENTS.md`; `AUTOPILOT_STATE.json` is the machine-readable resume point and `PRODUCT_ROADMAP.md` owns Brand + Product/competitor-gap work.

## Current phase — SITE-WIDE VISUAL UNIFICATION / ANYTOUR DESIGN SYSTEM 1.0

Paid/real-user traffic is intentionally not running. Current visitors are the owner and team, so browser/funnel activity must not be treated as conversion evidence.

The mature tour-search flow remains the strongest product surface at approximately **8.5–9.0/10**, but that is not the score of the whole public site. After PR #306 production deployment and five-width production visual review, the honest whole-site score is now **7.8/10**. The shared standalone shell now has corrected heading hierarchy in addition to the local country discovery layer. PR #308 also corrected the visual evidence pipeline so homepage captures are taken only after the country selector reaches its stable loaded state. The remaining largest structural seam is still the separate legacy header on `/poisk-turov/`.

Current scorecard:
- whole public site / coherent product impression: **7.8/10**
- cross-page visual consistency: **7.7/10**
- header/navigation consistency: **6.5/10** — homepage/content use shared `.at-global-header`; `/poisk-turov/` still uses `.at-site-header`
- homepage: **7.25/10**
- country pages: **8.0/10**
- `/hot/`: **7.7/10**
- `/how-to-buy/`: **7.5/10**
- `/contacts/`: **7.55/10**
- `/rb/`: **7.55/10**
- public-site mobile consistency: **7.85/10**
- typography: **7.6/10**
- grid/spacing: **7.65/10**
- brand coherence: **7.7/10**
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
- PR #296 applied the shared breadcrumb, section-heading and branded search-callout primitives to `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/`, removed ad-hoc spacing hacks and made the path into the common search visually consistent without duplicating search logic.
- PR #298 removed the remaining country-catalog jumps to `anytour.online` and added local shared-shell destination pages for Tunisia, Vietnam, Dominican Republic, Cyprus, Cuba, Maldives, Mexico, Sri Lanka and Tanzania. The visible catalog now keeps all 14 directions inside one AnyTour product before handing off to the common live search. New country pages reuse the canonical renderer and existing Design System primitives; verified Tourvisor IDs remain only on Turkey/Egypt/UAE/Thailand/Russia and no IDs were guessed for the nine editorial routes.
- PR #300 added direct `/hot/` quick starts for 7, 10 and 14 nights inside the existing nearest-two-weeks range, reusing common search parameters and shared cards with no forked search/Tourvisor logic or fake static prices.
- PR #303 added a compact “Сравните похожие направления” discovery section to every country page from the existing `.sp-country` primitive and fixed standalone-content CI coverage for country/hot changes. Production deploy, five-width visuals, navigation and migrated-content guards were green.
- **PR #306 is DONE in production.** Production screenshot review confirmed a shared CSS specificity bug: `.sp-main section>h2` overrode the intended `.sp-card h2` and `.sp-step h2` sizes, making nested cards and the eight `/how-to-buy/` steps look like top-level sections. This flattened hierarchy across `/how-to-buy/`, `/contacts/`, `/rb/` and other standalone cards.
- PR #306 removed the broad descendant heading override. Section scale now comes from `.sp-section-head h2`, card scale from `.sp-card h2`, and step scale from `.sp-step h2`; no page content, search logic, routes or external contracts changed.
- PR #306 squash merge is `1f452e5bcef200d924cebc50d726e59187296bb4`. Production deploy run `33293939846` completed successfully: standalone release validation, public-page checks, unchanged production lead bridge validation and live search smoke were all green.
- Production visual run `33294016417` passed the required 375/430/768/1024/1440 audit. Production navigation run `33294016413`, migrated-content validation `33294016419`, and live-results visual run `33294016407` also passed after deploy.
- Representative **production** screenshots were reviewed directly: `/how-to-buy/` at desktop 1440 and mobile 375 plus `/contacts/` at desktop 1440 and mobile 375. Step/card headings now have the intended hierarchy, CTA prominence is preserved, mobile stacking is clean, and there is no horizontal overflow, clipping, broken wrapping or duplicated shell.
- **PR #308 fixed a confirmed visual-QA gap on the homepage.** The content visual workflow had been taking screenshots directly after DOM/font readiness, repeatedly capturing the transient `Загружаем страны…` state instead of the stable form. Production audits now wait for `[data-home-countries]` to become enabled with a non-loading/non-error selected option before CTA validation and screenshots.
- PR #308 merge `b67b52ae18070db59469526b97cf396ca73ffde6` triggered production visual run `33294316825`, which passed all 375/430/768/1024/1440 routes. The new desktop 1440 homepage screenshot was reviewed directly and correctly shows the populated country `Турция` rather than the loading placeholder. This is evidence-quality hardening only, so the product score was not raised from it.
- The existing production guards still explicitly track the known search-header seam rather than pretending the search page has already migrated.

## Current blocker / deferred search-header slice

The highest-priority structural gap remains `/poisk-turov/` outer header/navigation. The search page is concentrated in a large/minified `v2/index.php`; with the currently available GitHub connector, changing it requires replacing the whole file rather than applying a small atomic patch. Replacing that mature search file just to alter the shell is an unnecessary regression risk.

Therefore the header migration is **deferred for tooling/safe-patching reasons, not abandoned**. Required migration behavior is mapped: preserve personal-account and order/contact affordances, migrate only the outer shell onto `site-header-v2`, and leave search form/results/selected-tour/lead logic untouched. Resume this slice only when an atomic patch/edit path is available or after first safely extracting/de-minifying the header without behavior change.

## Exact next work order — Design System 1.0

1. **Continue the full production journey audit** `homepage → country/hot → search → results → selected tour → lead` at 375/430/768/1024/1440 using the now-stable homepage search-state screenshots, concentrating on shell-side spacing/hierarchy around the visible transition into the mature search surface.
2. **Prioritize the homepage as the weakest safe standalone surface**, then reassess `/how-to-buy/`, `/contacts/` and `/rb/` after the shared hierarchy correction before adding any more density. Use existing Design System primitives and only fix confirmed gaps.
3. **Safely resolve the search-header seam** when an atomic patch path is available. Preserve personal/order affordances and all mature search regressions.
4. **After shell unification**, deepen reviewed content/SEO inventory and real-price discovery modules.

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

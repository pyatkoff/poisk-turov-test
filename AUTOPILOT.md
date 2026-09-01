# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-01

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

AnyTour Design System 2.0 is the only canonical design system. Never introduce, restore or reference Design System 1.0 as current. Preserve the canonical AnyTour logo unchanged.

## Ordered work

1. Preserve and improve the mature search flow: full-search filters, loaded-results local filtering, results, hotel/tour cards and selected-tour UX.
2. Converge shared header/navigation/footer and broader site visuals using AnyTour Design System 2.0.
3. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and country pages visually coherent through shared shell primitives rather than route-local overrides.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
6. Keep technical refactor, unresolved legal/payment content and PR #254 deferred until separately authorized/proven safe.

## Current architecture decisions

### Loaded-results filtering

The owner-approved result-filter architecture is `loaded normalized hotels (target up to 100) → instant local filtering`. Stars, rating, price, meal and other fields already present in the loaded normalized set must filter that set immediately. Do not replace this with a new Tourvisor search on every filter click. A new Tourvisor search is reserved for real primary search-input changes or an explicitly justified optional background expansion capability.

Do not introduce generic client pagination as a substitute for the existing progressive result-loading model.

### Shared DS2 shell

The canonical design source is `design-system-v2.css` plus shared DS2 shell owners. The homepage/editorial/country/search surfaces consume the shared header/navigation/footer and canonical token layer. Do not restore route-local duplicate shell implementations or the old Design System 1.0 source.

## Recent material progress relevant to resume point

PR #664 made `design-system-v2.css` the canonical active design source for search/home/editorial surfaces and added a bundle guard against restoring the old DS1 source. Existing visual gates were updated to test the current DS2 behaviors rather than deprecated UI.

PR #667 fixed the shared header at 375/430/768: the squeezed duplicate inline phone action is hidden at compact widths while the verified phone remains available in the mobile menu; 1024/1440 retain the inline phone.

PR #670 removed the homepage-local duplicate token/header ownership so the homepage consumes the same canonical DS2 shell as the rest of the site. Its stale CI assertion was corrected to reject restoration of the removed local shell.

PR #672 extended the existing production result-card live audit from 1440 to 1440/1024/768 while reusing one populated search state. It validates the canonical grid-to-stacked transition, hotel photo/body geometry, stars-badge orientation and page overflow without adding extra Tourvisor searches.

PR #675 extends that same single-search production evidence into the first rendered tour variant. At 1440/1024/768 the live gate now also measures the results toolbar, tour row/meta/action containment and the visible `Выбрать тур` CTA while retaining the hotel-card/photo/stars/overflow assertions. The change adds no Tourvisor search, runtime CSS/JS, analytics/Metrika, price or lead behavior. Security guard passed before merge; merge SHA `08c6fea505574e0b1bf83921e933a2fa255c6f19`. The live production execution will run after the next successful standalone deployment.

The existing standalone-content visual owner already exercises `/country/`, `/country/turkey/` and a broad representative country set at 375/430/768/1024/1440, including shared header geometry, navigation, footer/community shell, overflow, CTA contrast/focus and selected screenshots. Do not create a duplicate country workflow merely to resample the same surfaces.

## Current resume point

Continue from the selected-tour handoff: validate the actual `Выбрать тур` → selected-tour transition and populated selected-tour geometry/hierarchy at 768/1024/1440. Fix only confirmed DS2 hierarchy/spacing/wrapping/overflow defects in canonical owners. Preserve the loaded-results local-filter architecture and all external contracts.

If the selected-tour handoff is already stable, continue an independent site-wide DS2 slice by reviewing existing five-width evidence for the weakest public route and fixing only a confirmed shared-shell or hierarchy defect.

Keep the whole-product site-wide score at **7.1/10** until a broader visible product slice materially improves; CI/evidence hardening alone is not a reason to raise it.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe. Keep `technical_refactor` deferred until new explicit owner direction.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe DS2/search-UX slice.

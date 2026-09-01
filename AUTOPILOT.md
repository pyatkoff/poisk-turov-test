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

PR #675 extended that same single-search production evidence into the first rendered tour variant. At 1440/1024/768 the live gate measures the results toolbar, tour row/meta/action containment and the visible `Выбрать тур` CTA while retaining hotel-card/photo/stars/overflow assertions.

PR #677 completed the production journey proof through the actual `Выбрать тур` → populated selected-tour → `Вернуться к предложениям` handoff on the same already-populated search state. The live gate passed at 1440/1024/768 with 10 populated facts, selected picture/flights/lead surfaces contained inside the DS2 card, correct desktop/tablet selected-price hierarchy, no horizontal overflow and a valid return to the preserved results state. The measured selected-tour roots were 1180 px at 1440, 984 px at 1024 and 744 px at 768; the lead CTA remained 54 px high at all three widths. This was CI/evidence hardening only: runtime CSS/JS, Tourvisor, analytics/Metrika, pricing and lead transport were unchanged.

PR #680 converged the homepage base benefit-grid owner with the already-canonical downstream DS2 alignment layer. The four `Поиск без сюрпризов` proof cards now declare four balanced desktop columns, two columns through tablet widths and an explicit `04` fallback marker for the fourth card. Review of the loaded CSS order confirmed `home-design-system-alignment-v1.css` remains the final runtime owner and already preserves the intended 4/2/1 geometry at wide/tablet/mobile widths, so this change removes contradictory fallback ownership rather than creating another visual system. All 10 PR checks completed successfully; search/Tourvisor, analytics/Metrika, logo, destinations and lead transport were untouched.

PR #683 continued the public-route DS2 hierarchy pass across `/hot/` and `/contacts/`. On `/hot/`, hotel-category stars are grouped as one non-wrapping semantic label so the category marker cannot fragment star-by-star on narrow cards while the whole marker can still move below a long hotel name. On `/contacts/`, the two child cards inside the `Связаться и перейти к подбору` section now use `h3` beneath the section-level `h2`, restoring the same section/card heading hierarchy used by the rest of the editorial shell. All PR checks passed, including standalone five-width visual validation and search/selected-tour regression gates. Both `Deploy V2 only` and `Deploy anytoour.ru` completed successfully; V2 verification, public-page verification, unchanged lead-bridge validation and live-search smoke passed. Search/Tourvisor, analytics/Metrika, pricing, logo, verified destinations and lead transport were unchanged.

PR #687 fixed the process hierarchy on `/how-to-buy/`. The eight purchase steps already used the shared DS2 numbered-step primitive visually, but the document structure was a generic container of independent sections. They now form a semantic ordered list (`ol`/`li`) while preserving the existing DS2 marker owner, two-column desktop / one-column mobile behavior and all existing wording. During implementation, a duplicate marker layer was detected and removed before merge so `site-page-v1.css` remains the sole visual owner for step numbering. All PR checks passed, including standalone content, five-width visual, V2 visual, selected-tour, bundle and security gates. `Deploy anytoour.ru` completed successfully with public-page verification, unchanged lead-bridge verification and live-search smoke; post-deploy migrated-content validation passed. Search/Tourvisor, analytics/Metrika, legal/payment wording, pricing, logo, verified destinations and lead transport were unchanged.

PR #690 completed the first public-route hierarchy pass on `/rb/`. The three items under `Что даёт раннее планирование` are independent benefits, but they were rendered with the numbered DS2 step primitive and therefore implied a false 1→2→3 process. They now use the canonical responsive `sp-grid` + `sp-card` primitives with unchanged wording and destinations, preserving the shared shell while matching the actual information hierarchy. All PR checks passed, including standalone content, five-width standalone visual, V2 visual, selected-tour, bundle and security gates. Search/Tourvisor, analytics/Metrika, legal/payment wording, pricing, logo, verified destinations and lead transport were unchanged.

The existing standalone-content visual owner already exercises `/country/`, `/country/turkey/` and a broad representative country set at 375/430/768/1024/1440, including shared header geometry, navigation, footer/community shell, overflow, CTA contrast/focus and selected screenshots. Do not create a duplicate country workflow merely to resample the same surfaces.

## Current resume point

The live search journey through results → tour variant → selected-tour → return is production-stable at 768/1024/1440. Homepage DS2 base/final ownership is internally aligned for the proof-card grid, and the first public-route hierarchy pass has now removed confirmed issues on `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/`.

Re-audit the representative country/editorial set at 375/430/768/1024/1440, starting with `/country/` and representative country pages. Inspect existing visual evidence and loaded CSS ownership before editing; fix only confirmed hierarchy, spacing, wrapping, overflow or shared-shell inconsistencies and do not manufacture route-local visual layers. Preserve the short search handoff and do not make editorial surfaces as dense as the search application.

Whole-product site-wide score is now **7.2/10**. The increase is deliberately small: the first public-route hierarchy pass is materially cleaner across four editorial routes and all relevant five-width visual checks are green, but a broader representative country/editorial re-audit is still required before claiming a larger jump.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe. Keep `technical_refactor` deferred until new explicit owner direction.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe DS2/search-UX slice.

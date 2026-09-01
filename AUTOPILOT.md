# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-01

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize the search user journey and user-facing visual layer. Treat the public site as one product across homepage → country/destination → hot/search → results → selected tour → lead; do not use search-only engineering quality as the whole-site score.

AnyTour Design System 2.0 is the only canonical design system. Never introduce, restore or reference Design System 1.0 as current. Preserve the canonical AnyTour logo unchanged.

New design/layout concepts require explicit owner approval before implementation. Already approved DS2 visual layers may be repaired, converged onto shared owners and regression-hardened autonomously when a concrete defect is confirmed.

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

PR #695 fixed the first confirmed defect from the representative country re-audit. The three `Что важно при выборе` fact cards on country detail pages inherited the generic `sp-grid--balanced-three` tablet fallback, creating a crooked 2+1 arrangement with a centered half-width orphan card at 1024/768. The fix remains inside the shared DS2 coherence owner but is scoped to `.sp-country-page`: country facts keep three equal columns at 901–1024 and switch to one calm editorial column at <=900, while `/hot/` and every other generic `balanced-three` consumer retain their existing behavior. All eight PR gates passed. `Deploy V2 only` and `Deploy anytoour.ru` completed successfully; public-page verification, unchanged lead-bridge validation and live-search smoke passed. Post-deploy five-width standalone-content validation, responsive navigation, live user journey, migrated-content, live results/selected-tour and V2 baseline checks are all green. Search/Tourvisor, analytics/Metrika, legal/payment wording, pricing, logo, verified destinations and lead transport were unchanged.

PR #792 repaired a confirmed site-wide DS2 shell regression around the factual footer. The shared markup from #788 already rendered `.ds2-site-footer` on search, homepage, editorial and country routes, but the approved footer visual layer lived only inside search-specific `ds2-search.css`; non-search public routes therefore rendered the shared footer without its intended DS2 layout. The same approved visual layer now lives in shared `site-footer-v1.css`, so the footer is coherent across the public product. Mobile editorial breadcrumbs were also restored from 40 px to the DS2 44 px interaction rhythm. During the fix, the standalone five-width workflow was found to miss shared convergence/footer CSS and still assert stale legacy footer selectors; it now triggers on those shared owners and validates canonical footer styling plus five 44 px social/app targets at 375/430/768/1024/1440. All 11 PR gates passed. `Deploy V2 only` and `Deploy anytoour.ru` completed successfully; V2 verification, public-page verification, unchanged lead-bridge validation and live-search smoke passed. Post-deploy migrated-content and five-width standalone-content live runs are green. Tourvisor, analytics/Metrika, lead transport, logo, verified destinations and unresolved legal/payment content were unchanged.

PR #800 rebuilt the stale/conflicted #797 repair directly on current `main` and fixed a confirmed country-detail DS2 geometry inconsistency. Informational resort chips in the country hero inherited 38 px desktop and 36 px narrow-screen heights from the base editorial layer; a single shared `site-coherence-v1.css` owner now holds those badges at 44 px across the required widths without changing their non-interactive semantics. All nine PR gates passed, including the five-width standalone-content visual workflow plus V2 visual, selected-tour, bundle, standalone and security validation. The stale #797 PR was closed superseded rather than merged. Search/Tourvisor, analytics/Metrika, pricing, lead transport, logo, verified destinations and unresolved legal/payment content were unchanged.

PR #806 fixed the next confirmed cross-route DS2 handoff inconsistency without inventing a new layout. Country detail pages used `Помочь с выбором` for the secondary manager CTA and `/rb/` used `Обсудить с менеджером`, while the existing `/country/` catalog and `/hot/` already used the clearer canonical label `Помощь менеджера`. Country detail and early-booking handoffs now use the same canonical wording. During CI, `Validate standalone content UX` was also found to still assert obsolete pre-DS2-footer selectors; the workflow now validates the current canonical `ds2-site-footer` structure and the manager handoff contract. All 11 PR gates passed, including five-width visual coverage at 375/430/768/1024/1440. `Deploy V2 only` and `Deploy anytoour.ru` completed successfully; the production deploy passed public-page verification, unchanged lead-bridge verification and live-search smoke. Tourvisor, analytics/Metrika, pricing, lead transport, logo, verified destinations and unresolved legal/payment content were unchanged.

The existing standalone-content visual owner exercises `/country/`, `/country/turkey/` and a broad representative country set at 375/430/768/1024/1440, including shared header geometry, navigation, canonical footer styling/touch targets, overflow, CTA contrast/focus and selected screenshots. Do not create a duplicate country workflow merely to resample the same surfaces.

## Current resume point

The live search journey through results → tour variant → selected-tour → return remains production-stable. Homepage, editorial routes and representative country pages share the canonical DS2 header/footer shell. Country hero resort badges follow the same 44 px vertical rhythm at 375/430/768/1024/1440 and remain informational labels. Country detail and `/rb/` now share the same explicit `Помощь менеджера` secondary handoff wording already used by `/country/` and `/hot/`.

Continue the shared DS2 CTA/handoff and wrapping audit at 375/430/768/1024/1440, focusing next on `/contacts/`, `/how-to-buy/`, homepage/search and representative country pages. Prefer shared-owner fixes only when a defect is confirmed. Do not turn resort labels into links/controls or invent new interaction behavior without an approved design/data contract. Do not invent route-local visual layers or implement a new unapproved layout; new design concepts stay in the owner-approval loop.

Whole-product site-wide score remains **7.3/10**. PR #806 improves editorial-to-manager handoff coherence and repairs stale DS2 CI ownership, but it is intentionally narrow and does not yet justify a whole-product score increase.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe. Keep `technical_refactor` deferred until new explicit owner direction.

## Decision rule

Prefer narrow shared-shell or search-UX changes with broad user benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe DS2/search-UX slice.

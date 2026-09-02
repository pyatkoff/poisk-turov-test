# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-02

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the owner-priority source; `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` owns architecture and `TEST_MATRIX.md` owns CI/test mapping.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

AnyTour Design System 2.0 is the only canonical design system. Do not restore, introduce or reference Design System 1.0 as current. Treat the public site as one product across homepage → destination/country → hot/search → results → selected tour → lead.

Priority order after emergency overrides is: search user journey → loaded-results local filtering → hotel/tour cards and selected-tour → desktop/mobile visual consistency → shared DS2 header/navigation/footer → site-wide DS2 convergence. Technical refactor remains deferred.

New design/layout concepts require explicit owner approval. Existing approved DS2 owners may be repaired and converged autonomously when a concrete defect is confirmed.

**Search 2.0 release lock:** draft PR #810 is design-approval work. The owner explicitly approved publishing this branch to the isolated preview route `https://anytoour.ru/_preview/search2/poisk-turov/`; preview-only deploys from `design/search2-form-polish` are therefore allowed and should be used for visual review. Do not merge #810 to `main` and do not deploy its redesign to production `/poisk-turov/` until the owner explicitly approves the visual design for production.

## Architecture and protections

- Loaded normalized result filters remain instant/local, target up to 100 loaded hotels. Do not trigger a new Tourvisor search for every result-filter click.
- Local result facets may only be offered when the loaded payload contains complete data for that facet; otherwise hide/reset the facet rather than silently filtering on partial data.
- Do not introduce generic client pagination as a replacement for the current progressive loading model.
- Preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
- Work only inside `pyatkoff/poisk-turov-test`.
- Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, existing lead transport/field mapping, AnyTour logo or verified destinations.
- Do not migrate unresolved legal/payment content.
- PR #254 remains deferred pending a fresh architecture review. `technical_refactor` remains deferred pending explicit owner direction.

## Current DS2 production state

The shared DS2 shell is established across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages. Existing production gates cover required widths 375/430/768/1024/1440 and preserve search/results/selected-tour regressions.

Recent material convergence:

- PR #792 moved the factual footer styling into the shared footer owner and restored canonical footer behavior/touch targets across search and non-search routes.
- PR #800 normalized country hero resort badges to the shared 44 px DS2 vertical rhythm without changing them into interactions.
- PR #806 normalized country-detail and `/rb/` manager handoffs to the canonical `Помощь менеджера` wording.
- PR #813 closed the remaining `/how-to-buy/` wording mismatch: its `/contacts/` secondary CTA now also says `Помощь менеджера`; destination unchanged.
- PR #814 fixed a confirmed `/contacts/` interaction defect: four office telephone links plus the unified telephone link now use a shared 44 px minimum DS2 touch target. All PR gates were green. `Deploy anytoour.ru` run 33553029719 passed release copy, public-page verification, unchanged lead-bridge verification and live-search smoke; post-deploy standalone visual run 33553359626 passed at 375/430/768/1024/1440.
- PR #817 hardened the canonical five-width visual gate with an explicit live assertion for exactly five `/contacts/` phone targets and minimum 43.5 px measured height.
- The first main-push execution after #817 exposed a CI/deployment race while a concurrent country-content release was being copied, producing a transient `/country/russia/` 500 during the copy window rather than a stable runtime defect.
- PR #820 fixed that race: production `Visual standalone content live` no longer starts directly on main push; it runs authoritatively after successful `Deploy anytoour.ru`, while PRs still use the same local five-width audit. #820 passed Security + local five-width visual checks and merged as `de909ef035b31664851609f8180c0b1de77d969b`.
- The subsequent country-content `Deploy anytoour.ru` run 33553777247 completed successfully, including public-page verification, unchanged lead-bridge verification and live-search smoke. Authoritative post-deploy five-width visual run 33554159108 also passed, confirming the fully copied release at 375/430/768/1024/1440, including all five `/contacts/` phone targets and representative country pages.
- PR #842 hardened local result filtering: meal/category/rating/sea facets are exposed only when the currently loaded normalized payload has complete data for that facet. Production deploy run 33563761068 completed successfully; Tourvisor, pricing, analytics and lead contracts were unchanged.
- PR #843 fixed shared mobile breadcrumb readability on long country/page names. At widths up to 560 px the current breadcrumb can wrap instead of being clipped to an ellipsis; desktop/tablet are unchanged. PR security, standalone, bundle and visual gates passed before merge; merge commit is `ec4f7afb6eaab4af18cd951018ea0647fae73a3e`.
- PR #862 fixed a shared DS2 compact-header touch regression: at <=520 px the hamburger control had fallen to 38×38 px while navigation targets remained 44 px. The canonical shared header now keeps the compact control at 44×44 px. Merge `a8047188bc1b4d31294456a6f8d0cbdfdbbae501`; production deploy 33571150972 passed release validation, copy, public-page verification, unchanged lead bridge and live-search smoke. A concurrent subsequent SEO release also deployed successfully, and authoritative current post-deploy standalone visual run 33571329043 passed the requested 375/430/768/1024/1440 route audit, confirming the fix in the current live release.
- Search 2.0 preview now has a green five-width initial-form baseline on head `efdd19f4fe9799e3e36332d00729f675b1e94530`. Preview run #51 (`33573157764`) passed 375/430/768/1024/1440 with exactly five primary fields, truthful `All Inclusive` bound to canonical `food=7`, blue CTA, populated `7–10 ночей` summary, `Все фильтры`, correct mobile CTA-before-quick order, no page JS errors and `overflow=false` at every width. The 375 evidence was visually inspected: quick choices scroll internally while the document remains viewport-width, and the nights summary stays on one line. No new CSS layer was added; production remains release-locked.
- PR #870 closed another shared DS2 interaction inconsistency in selected-tour: `Вернуться к предложениям` used a 40 px minimum target and the hotel-description toggle used 38 px. Both now use the canonical 44 px DS2 touch rhythm in the existing `ds2-selected-tour-convergence-v1.css`. All eight PR gates passed, including five-width selected-tour visual evidence and the deterministic baseline. Merge `4ddf997599d69fadf96fd0842cc4ff9e561e187b`; production V2 deploy `33573370464` passed copy, Verify V2 and Live search smoke, and selected-tour post-deploy visual run `33573588616` passed.
- That release also exposed a stale generic V2 post-deploy assertion, not a runtime defect: every five-width search/result/header/phone/overflow check was green, but the workflow still required removed legacy `.v2-site-community`. PR #872 changed the gate to require one canonical `.ds2-site-footer` and zero legacy community blocks while retaining all other assertions. It merged as `2ae43e3dffce44a607fb14254056438ca8de554b`; direct production five-width run `33573805145` then passed.

Search/Tourvisor, analytics/Metrika, pricing, lead transport, logo, verified destinations and unresolved legal/payment content were untouched by these DS2 fixes.

## Resume point

Whole-product score remains **7.3/10**. Search 2.0 now has a fully green five-width preview baseline and the prior mobile document-overflow defect is closed. Selected-tour interaction sizing is live on the shared 44 px DS2 rhythm, and the production visual gate now validates the canonical DS2 footer rather than a removed legacy block. The score is intentionally held because Search 2.0 is still preview-only.

Continue the Search 2.0 initial form only through confirmed visual gaps against the approved design, using run #51 as the green 375/430/768/1024/1440 baseline. Preserve the target composition `Откуда → Куда → Когда → Ночи → Туристы → Найти туры`, blue CTA, truthful compact secondary filters and unchanged underlying search values/contracts. Do not add another CSS layer. PR #810 remains draft/preview-only and must not reach production until explicit owner visual approval. In parallel, continue only confirmed independent shared DS2 wrapping/touch/hierarchy fixes on production-safe pages, with relevant green PR/deploy/live evidence. PR #254, `technical_refactor` and unresolved legal/payment work remain deferred.

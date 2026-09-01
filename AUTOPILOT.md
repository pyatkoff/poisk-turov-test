# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-01

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the owner-priority source; `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` owns architecture and `TEST_MATRIX.md` owns CI/test mapping.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

AnyTour Design System 2.0 is the only canonical design system. Do not restore, introduce or reference Design System 1.0 as current. Treat the public site as one product across homepage → destination/country → hot/search → results → selected tour → lead.

Priority order after emergency overrides is: search user journey → loaded-results local filtering → hotel/tour cards and selected-tour → desktop/mobile visual consistency → shared DS2 header/navigation/footer → site-wide DS2 convergence. Technical refactor remains deferred.

New design/layout concepts require explicit owner approval. Existing approved DS2 owners may be repaired and converged autonomously when a concrete defect is confirmed.

**Search 2.0 release lock:** draft PR #810 is preview/design-approval work only. Do not merge it to `main` and do not deploy its redesign to production until the owner explicitly approves the visual design.

## Architecture and protections

- Loaded normalized result filters remain instant/local, target up to 100 loaded hotels. Do not trigger a new Tourvisor search for every result-filter click.
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

Search/Tourvisor, analytics/Metrika, pricing, lead transport, logo, verified destinations and unresolved legal/payment content were untouched by these DS2 fixes.

## Resume point

Whole-product score remains **7.3/10**. The recent changes improve cross-route handoff wording, touch-target consistency and production visual-gate reliability, but are intentionally narrow and do not yet justify a broader score increase.

Next priority: continue the shared DS2 CTA/wrapping audit on homepage/search and representative country pages at 375/430/768/1024/1440. Fix only confirmed spacing, wrapping, overflow, hierarchy or shared-shell inconsistencies in canonical owners. Do not invent route-local visual systems or new interaction behavior. Search 2.0 preview PR #810 remains release-locked until explicit owner approval.

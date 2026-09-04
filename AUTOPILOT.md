# poisk-turov-test — Autopilot Roadmap

Updated: 2026-09-04

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the owner-priority source; `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md` owns architecture and `TEST_MATRIX.md` owns CI/test mapping.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE

AnyTour Design System 2.0 is the only canonical design system. Do not restore, introduce or reference Design System 1.0 as current. Treat the public site as one product across homepage → destination/country → hot/search → results → selected tour → lead.

Active owner-directed continuation is [Search3 site completion, issue #996](https://github.com/pyatkoff/poisk-turov-test/issues/996). Finish the new design across the site before expanding SEO or refactoring. Shared button/header/footer convergence is prepared and checked in draft #1334. The next task is a whole-site preview, using a separately reviewed artifact because the existing preview deploy accepts only nine search files. The stable DS2 mode/phase/stage identifiers remain unchanged; Search3 is the active substage.

Priority order after emergency overrides is: confirmed search/mobile defects → common site presentation → whole-site preview and handoff → truthful content/working links and offer freshness → end-to-end acceptance → owner-approved migration. Technical refactor and mass SEO expansion remain deferred.

New design/layout concepts require explicit owner approval. Existing approved DS2 owners may be repaired and converged autonomously when a concrete defect is confirmed.

**Search3 release lock:** isolated preview publication is authorized; production draft [#1334](https://github.com/pyatkoff/poisk-turov-test/pull/1334) must not merge to `main` or replace production `/poisk-turov/` until the owner explicitly approves the concrete visual release. Current search preview: `https://anytoour.ru/_preview/search3-candidate/poisk-turov/`. Preparing code, tasks, evidence and rollback is authorized; the remaining production approval does not block independent safe work.

**Historical Search2 lock:** #810 remains subject to its previous preview-only restriction. It is not the active development target; do not resume Search2 merely because old evidence below refers to it.

## Architecture and protections

- Loaded normalized result filters remain instant/local, target up to 100 loaded hotels. Do not trigger a new Tourvisor search for every result-filter click.
- Local result facets may only be offered when the loaded payload contains complete data for that facet; otherwise hide/reset the facet rather than silently filtering on partial data.
- Do not introduce generic client pagination as a replacement for the current progressive loading model.
- Preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
- Work only inside `pyatkoff/poisk-turov-test`.
- Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, existing lead transport/field mapping, AnyTour logo or verified destinations.
- Do not migrate unresolved legal/payment content.
- PR #254 remains deferred pending a fresh architecture review. `technical_refactor` remains deferred pending explicit owner direction.

## Historical DS2 production evidence through 2026-09-02

The following records are retained for provenance, not as the current Search3 queue or latest release status.

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

Search3 candidate `470474414a3930f1f6c095a8dbbc187075b253c0` is published in the isolated search preview. CI `33925976956` and deployment `33926206799` passed; deployment PR #1339. Evidence covers 375/430/768/1024/1440 and wide 1920/2048. Owner confirmed the iPhone calendar opens; system night-picker appearance and Safari alignment still require device confirmation. This evidence is not whole-site or real lead-delivery acceptance.

Draft #1334 contains the production import, old-search route, client-facing copy, independent home night ranges/child ages, and date/night/party-preserving links from snapshot/month pages. These changes are prepared and checked, not published on the main site. The previous 7.3/10 score is historical; no new whole-product score has been assigned.

Continue from `AUTOPILOT_STATE.json` current task/queue and [issue #996](https://github.com/pyatkoff/poisk-turov-test/issues/996):

1. Converge shared buttons, header, footer and spacing across page families using the approved design and verified facts.
2. Publish a whole-site preview including the prepared homepage/content handoff changes; check navigation and search values.
3. Resolve truthful copy/map actions and confirmed broken legal/payment links using existing approved content; record missing content rather than invent terms. Fix offer freshness using an explicit timezone and verified availability rules.
4. Verify phone/desktop home → search → hotel → tour → flights or clarification → summary → lead form, including long values, families and recovery states. Preview lead delivery remains disabled.
5. Obtain owner visual approval on a specific release, retain previous deployment artifact/configuration, verify rollback and `/poisk-turov-old/`, then execute the approved migration and post-deploy verification.

Detailed status and legacy-search limits: `docs/project/search3-site-finish-and-legacy.md`. Issue #2 is a machine-maintained CI signal, not the product task list. An hourly scheduled development task is enabled. It must continue beyond one PR whenever time remains for another safe step; it is not a nonstop process. PR #254, technical refactor and unresolved legal-content authoring remain deferred.

Owner continuation rule: **do not stop after one PR if the current run has time for the next safe step**. Update the handoff after each checked change and continue. A final production visual-approval gate blocks production publication only, not other authorized safe preparation.

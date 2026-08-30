# poisk-turov-test — Autopilot State

Updated: 2026-08-30 10:38 +03:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` is the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, and `PRODUCT_ROADMAP.md` owns product/brand roadmap.

## Current phase — TECHNICAL REFACTOR PASS WITH P0/P1 PRODUCTION PREEMPTION

The technical refactor pass remains active before broad UX/visual expansion. Production, lead-loss, incorrect-data and broken-user-journey issues override refactor work immediately.

That override was exercised after real iPhone production evidence exposed two mobile search-shell defects. They were fixed in narrow compatibility slices instead of replacing the mature search shell.

The goal of the technical phase remains unchanged: make the repository easier and safer for autonomous development while preserving mature search/product behavior.

Current refactor objectives:

1. finish canonical source-of-truth alignment across architecture, tests and operational state;
2. complete workflow-by-workflow CI inventory and classify `PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE` ownership;
3. extract repeated deterministic checks into `scripts/ci/` and reduce brittle inline workflow logic;
4. replace `grep` / `src.includes()` implementation-string guards only after equivalent behavioral diagnostics exist;
5. map versioned assets/APIs/adapters as `ACTIVE / COMPATIBILITY / DEPRECATED / DEAD-CANDIDATE` before removing anything;
6. refactor the asset helper so controlled subdirectories are possible, then migrate modules incrementally toward shared/search/results/tour/checkout/integrations/site/seo ownership zones;
7. decouple legacy deployment/test dependencies only in proven-safe slices; protected lead bridge migration remains HIGH risk;
8. continue confirmed P0/P1 production UX fixes immediately, while deferring broad visual expansion until the technical checkpoint.

## Latest material progress

- `ARCHITECTURE.md` remains the canonical architecture source of truth and codifies **one concept → one implementation**.
- `TEST_MATRIX.md` defines the four CI tiers and protected behavioral ownership.
- `CI_WORKFLOW_AUDIT.md` plus family audit companions record verified workflow evidence rather than filename assumptions.
- Lead CI ownership was audited in PR #310; PR #316 extracted lead idempotency and lead price assertions into reusable `scripts/ci/lead/` diagnostics without changing runtime PHP, Metrika, Tourvisor or the external lead contract.
- **PR #320** fixed a confirmed mobile search-header inconsistency from real iPhone evidence. A final CSS compatibility layer now makes the legacy `/poisk-turov/` hamburger match the shared site header without replacing `v2/index.php`. The large blue square regression is no longer the intended production state.
- **PR #321** fixed the second confirmed iPhone defect: the floating consultant could cover the fixed primary search CTA. The sticky search bar now reserves a dedicated bottom-right support-widget lane while remaining compact and safe-area aware; its existing inline-submit boundary behavior is preserved.
- PR #321 also strengthened `Visual mobile search sticky`: 375 and 430 px runs now fail if the sticky CTA enters the reserved support-widget lane. The existing height, overflow and inline-boundary assertions remain in force.
- During #321, CI exposed a stale startup-bundle contract left behind after #320 increased the CSS manifest from 26 to 27 active assets. The guard was corrected to the actual ordered manifest, and its local HTTP readiness check was hardened rather than weakening coverage.
- All final PR #321 checks were green, including V2 PR, Security, startup/branch bundles, mobile sticky boundary, dedicated sticky visual, selected-tour, meal, trust and general visual baselines.
- Production deploy **33299532124** is green. Public-page verification, the unchanged lead bridge and live search smoke all passed. Post-deploy live user journey **33299603432** and migrated-content check **33299603436** are green. Remaining post-deploy visual-family runs are observation only unless they expose a real regression.

## Current product baseline

Whole-site score is now approximately **8.1/10** after production evidence, not from CI alone. Search remains the stronger product reference at about **8.75/10**. Mobile cross-page consistency is approximately **8.2/10** and header/navigation consistency approximately **7.4/10** after the search-header compatibility fix. These are cautious scores; full shared-header component migration is still not complete.

## Exact next work order

1. **Complete post-deploy observation of PR #321.** Investigate any failed live/visual guard before moving on; successful post-deploy checks require no cosmetic follow-up by themselves.
2. **Resume exhaustive CI inventory.** Complete remaining room/flight/price, lead, mobile/UI, SEO/content, production/live, runtime/deploy and measurement workflow families.
3. **Consolidate deterministic CI infrastructure.** Move repeated syntax/asset/render/contract checks into reusable `scripts/ci/` helpers while preserving all existing protected verdicts.
4. **Reduce refactor-hostile source-text guards.** Start only where a deterministic behavioral diagnostic already exists or can be added first.
5. **Build the dependency/deprecation map.** Classify historical generations such as versioned analytics/API/lead adapters and active asset layers before any deletion.
6. **Prepare structural ownership migration.** Update the asset loader to support allowlisted subdirectories, then move one small module family at a time with focused regression evidence.
7. **Continue mobile user-journey audit when production evidence warrants it.** The next safe UX pass is header → form → sticky CTA → results → selected tour → lead at 375/430, while the broader 375/430/768/1024/1440 Design System baseline remains protected.

## Production baseline that must not regress

- public AnyTour product is one site on `anytoour.ru`;
- `/poisk-turov/` remains the transactional search application;
- legacy `/poisk-turov-test/v2/` is compatibility-only;
- the established visual baseline remains protected at 375 / 430 / 768 / 1024 / 1440;
- mobile search header now visually follows the shared shell through a final compatibility layer;
- mobile sticky search CTA must preserve at least 72 px of right-side support-widget clearance at 375/430 and must still yield to the inline submit at its boundary;
- mature search/results/tour/lead flow remains protected during structural work.

## Mandatory protections

Search lifecycle/recovery, results/comparison, selected tour, rooms/flights/price and lead UX regression guards remain authoritative until equivalent replacement coverage is proven.

Do not modify without explicit approval:

- Yandex Metrika configuration or goals/events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

The AnyTour logo must not be redesigned/replaced. Legal/payment migration remains deferred. PR #254 remains deferred unless freshly reassessed and proven safe.

Full replacement of the `/poisk-turov/` legacy header component also remains deferred. Isolated compatibility fixes are allowed when real production evidence confirms a defect and focused browser coverage exists.

## Execution policy

Priority order during this phase:

`production broken → lead loss → incorrect data → broken user journey → technical refactor/source-of-truth/CI/dependency cleanup → UX/responsive/visual → content/SEO → cosmetic refactor`

Work in narrow slices. For each slice: inspect current implementation and tests, make one material change, run the narrowest relevant checks first, then broader regression when needed. Do not create a new implementation when a canonical one can be extended. Do not delete a guard until replacement coverage is green. If blocked, record the blocker and continue the next independent safe task.

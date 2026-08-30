# poisk-turov-test — Autopilot State

Updated: 2026-08-30 09:16 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` is the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, and `PRODUCT_ROADMAP.md` owns product/brand roadmap.

## Current phase — TECHNICAL REFACTOR PASS

The owner explicitly moved the project into a technical refactor pass before further UX/visual expansion. Production, lead-loss, incorrect-data and broken-user-journey issues still override refactor work immediately.

The goal of this phase is not a rewrite. It is to make the repository easier and safer for autonomous development while preserving the mature search/product behavior.

Current refactor objectives:

1. finish canonical source-of-truth alignment across architecture, tests and operational state;
2. complete workflow-by-workflow CI inventory and classify `PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE` ownership;
3. extract repeated deterministic checks into `scripts/ci/` and reduce brittle inline workflow logic;
4. replace `grep` / `src.includes()` implementation-string guards only after equivalent behavioral diagnostics exist;
5. map versioned assets/APIs/adapters as `ACTIVE / COMPATIBILITY / DEPRECATED / DEAD-CANDIDATE` before removing anything;
6. refactor the asset helper so controlled subdirectories are possible, then migrate modules incrementally toward shared/search/results/tour/checkout/integrations/site/seo ownership zones;
7. decouple legacy deployment/test dependencies only in proven-safe slices; protected lead bridge migration remains HIGH risk;
8. resume broad UX/visual work after the technical checkpoint, while keeping all established visual/search regression coverage green.

## Latest material progress

- `ARCHITECTURE.md` is now the canonical architecture source of truth and codifies **one concept → one implementation**.
- `TEST_MATRIX.md` defines the four CI tiers and protected behavioral ownership.
- `CI_WORKFLOW_AUDIT.md` plus family audit companions record verified workflow evidence rather than filename assumptions.
- Lead CI ownership was audited in PR #310.
- PR #316 extracted lead idempotency and lead price assertions into reusable `scripts/ci/lead/` diagnostics and added PR FAST execution without changing runtime PHP, Metrika, Tourvisor or the external lead contract. The focused lead checks and Security guard were green before merge.
- Existing production visual/search baseline remains valid: standalone/public pages are production-green through PR #317 and the search shell baseline remains production-green through the latest header alignment work. These baselines are guardrails during refactor, not the current development priority.

## Exact next work order

1. **Finish exhaustive CI inventory.** Complete remaining room/flight/price, lead, mobile/UI, SEO/content, production/live, runtime/deploy and measurement workflow families.
2. **Consolidate deterministic CI infrastructure.** Move repeated syntax/asset/render/contract checks into reusable `scripts/ci/` helpers while preserving all existing protected verdicts.
3. **Reduce refactor-hostile source-text guards.** Start only where a deterministic behavioral diagnostic already exists or can be added first.
4. **Build the dependency/deprecation map.** Classify historical generations such as versioned analytics/API/lead adapters and active asset layers before any deletion.
5. **Prepare structural ownership migration.** Update the asset loader to support allowlisted subdirectories, then move one small module family at a time with focused regression evidence.
6. **Reconcile legacy host/deploy coupling carefully.** Remove harmless legacy dependencies first; do not change lead bridge/external contract without explicit HIGH-risk review.
7. **Then resume shared-shell/UX/visual unification** with required 375/430/768/1024/1440 evidence.

## Production baseline that must not regress

- public AnyTour product is one site on `anytoour.ru`;
- `/poisk-turov/` remains the transactional search application;
- legacy `/poisk-turov-test/v2/` is compatibility-only;
- the existing visual baseline is green at 375 / 430 / 768 / 1024 / 1440;
- the mature search/results/tour/lead flow remains protected during structural work.

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

## Execution policy

Priority order during this phase:

`production broken → lead loss → incorrect data → broken user journey → technical refactor/source-of-truth/CI/dependency cleanup → UX/responsive/visual → content/SEO → cosmetic refactor`

Work in narrow slices. For each slice: inspect current implementation and tests, make one material change, run the narrowest relevant checks first, then broader regression when needed. Do not create a new implementation when a canonical one can be extended. Do not delete a guard until replacement coverage is green. If blocked, record the blocker and continue the next independent safe task.

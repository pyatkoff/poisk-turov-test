# poisk-turov-test — Autopilot State

Updated: 2026-08-30 12:08 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md`, `TEST_MATRIX.md` and `DEPENDENCY_MAP.md` remain the canonical technical references and continue to protect the mature search flow while the active product phase is visual unification.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's newest explicit direction supersedes the previous technical-refactor priority lock.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → Design System 1.0 / site-wide visual unification → UX/responsive visual fixes → supporting technical consolidation → content/SEO → cosmetic flourishes`

Do not confuse the strong tour-search implementation with whole-site product coherence. Whole-site baseline is approximately **6.5/10**; current estimate is approximately **6.6/10** after the first shared-shell slices. This is a site-wide coherence score, not a search-only score.

## Design System 1.0 goals

1. One shared visual language across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
2. Shared design tokens/primitives for shell width, responsive gutters, typography, spacing, buttons, cards, breadcrumbs and responsive behavior.
3. One coherent header/navigation and footer without duplicated shell implementations.
4. Migrate weak/editorial pages onto the mature shared shell without making them as dense as the search experience.
5. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell and inconsistent hierarchy before cosmetic additions.
6. Preserve search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Current evidence and resume point

- PR #334 remains the production-green shared header geometry/navigation baseline.
- PR #337 added `--at-page-gutter`, aligned standalone main content and breadcrumbs, and is fully production green: deploy, public-page verification, unchanged lead bridge, live search smoke, V2 post-deploy visual audit and search-recovery audit all passed.
- PR #339 adds semantic `--at-page-edge` and aligns standalone hero horizontal padding with breadcrumbs/main at 375/430/768/1024/1440. Full PR suite passed and the PR is merged; post-merge deploy/live verification is the current release gate.
- PR #340 is an independent `/contacts/` slice that changes the four-office wide layout from visually unbalanced 3+1 to 2×2 while retaining one-column mobile behavior. It was rebased onto the #339 main baseline and is in fresh CI/visual verification.

## Exact next work order

1. Finish PR #339 post-merge deploy/live verification.
2. Finish PR #340 fresh PR visual/browser checks; merge/deploy only if green and verify `/contacts/` live.
3. Audit remaining hero ↔ breadcrumbs ↔ main ↔ footer alignment and navigation wrapping at 375/430/768/1024/1440.
4. Unify remaining duplicated button/card/breadcrumb geometry behind Design System tokens/primitives where safe.
5. Continue weak editorial pages: `/how-to-buy/`, `/rb/`, `/hot/`, `/country/` and representative country pages.
6. Reassess homepage → country → hot/search hierarchy and spacing after the shared shell is stable.
7. Keep `/poisk-turov/` search regressions green; any deeper shared-header replacement must be atomic with equivalent browser coverage.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve verified social/app destinations and the existing AnyTour logo. Do not migrate unresolved legal/payment content. PR #254 remains deferred unless a fresh scope-specific review proves its separate DB/platform architecture safe. PRs #248/#249 also remain excluded from automatic merge without fresh review.

## Execution policy

Work in narrow, independent PR-sized slices. Read current `main`, open PRs and fresh CI before choosing work. For user-facing changes, validate 375/430/768/1024/1440 and inspect visual evidence. Deploy only after relevant checks are green, then verify live behavior. If one page/task is blocked, record/defer it and continue another safe independent visual slice.

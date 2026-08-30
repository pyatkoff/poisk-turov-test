# poisk-turov-test — Autopilot State

Updated: 2026-08-30 13:52 +03:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md`, `TEST_MATRIX.md` and `DEPENDENCY_MAP.md` remain the canonical technical references and continue to protect the mature search flow while the active product phase is visual unification.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's newest explicit direction supersedes the previous technical-refactor priority lock.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → Design System 1.0 / site-wide visual unification → UX/responsive visual fixes → supporting technical consolidation → content/SEO → cosmetic flourishes`

Do not confuse the strong tour-search implementation with whole-site product coherence. Whole-site baseline is approximately **6.5/10**; current estimate is approximately **6.7/10** after the shared-shell, contacts and first editorial-hierarchy slices. This is a site-wide coherence score, not a search-only score.

## Design System 1.0 goals

1. One shared visual language across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
2. Shared design tokens/primitives for shell width, responsive gutters, typography, spacing, buttons, cards, breadcrumbs and responsive behavior.
3. One coherent header/navigation and footer without duplicated shell implementations.
4. Migrate weak/editorial pages onto the mature shared shell without making them as dense as the search experience.
5. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell and inconsistent hierarchy before cosmetic additions.
6. Preserve search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Current evidence and resume point

- PR #334 remains the production-green shared header geometry/navigation baseline.
- PR #337 added shared responsive page gutters and aligned standalone main content/breadcrumbs; production deploy, public pages, unchanged lead bridge, live search, post-deploy visual and search-recovery checks are green.
- PR #339 added semantic page-edge geometry and aligned standalone heroes with breadcrumbs/main across 375/430/768/1024/1440; merged release checks are complete.
- PR #340 rebalanced the four-office `/contacts/` layout from 3+1 to 2×2 on wider screens while retaining one-column mobile behavior; merged production/live validation is complete.
- PR #343 improves `/how-to-buy/` hierarchy by rendering the eight-step purchase journey as a balanced two-column flow on wider screens and a single sequential column at `<=768px`. The complete PR suite passed with zero failures. Production deploy is green, including public-page verification, unchanged lead bridge and live search smoke; post-deploy workflow fanout completed with zero failures for the release SHA.
- `/hot/` was re-audited after these shared-shell changes. Its current hierarchy (explanation → duration shortcuts → live-search CTA) has no confirmed material visual defect, so no cosmetic-only change was made.

## Exact next work order

1. Audit and improve `/rb/` only where hierarchy/spacing evidence warrants a material change.
2. Consolidate remaining duplicated button/card/breadcrumb spacing and geometry behind Design System tokens/primitives, with five-width visual coverage.
3. Audit `/country/` and representative country pages for hierarchy, card density, wrapping and responsive rhythm without over-densifying editorial content.
4. Reassess homepage → country → hot/search continuity after the editorial migrations.
5. Continue checking hero ↔ breadcrumbs ↔ main ↔ footer alignment and navigation wrapping at 375/430/768/1024/1440 as pages migrate.
6. Keep `/poisk-turov/` search/recovery/results/comparison/flight/price/fuel/lead regressions green; any deeper shared-header replacement must be atomic with equivalent browser coverage.

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
# poisk-turov-test — Autopilot State

Updated: 2026-08-30 12:00 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. `ARCHITECTURE.md`, `TEST_MATRIX.md` and `DEPENDENCY_MAP.md` remain the canonical technical references and continue to protect the mature search flow while the active product phase is visual unification.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's newest explicit direction supersedes the previous technical-refactor priority lock.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → Design System 1.0 / site-wide visual unification → UX/responsive visual fixes → supporting technical consolidation → content/SEO → cosmetic flourishes`

Do not confuse the strong tour-search implementation with whole-site product coherence. Current whole-site baseline is approximately **6.5/10**; Design System 1.0 is intended to raise the homepage → country/destination → hot/search → results → selected tour → lead journey into one coherent product.

## Design System 1.0 goals

1. One shared visual language across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
2. Shared design tokens/primitives for shell width, responsive gutters, typography, spacing, buttons, cards, breadcrumbs and responsive behavior.
3. One coherent header/navigation and footer without duplicated shell implementations.
4. Migrate weak/editorial pages onto the mature shared shell without making them as dense as the search experience.
5. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell and inconsistent hierarchy before cosmetic additions.
6. Preserve search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Current evidence and resume point

- PR #334 is the production-green shared header geometry/navigation baseline.
- PR #337 introduced `--at-page-gutter` as the responsive shell gutter token and aligned standalone `.sp-main` plus breadcrumbs to one shell at desktop/tablet/mobile breakpoints.
- PR #337 PR visual/browser checks passed across 375/430/768/1024/1440 before merge.
- Post-merge CI/deploy/live verification for PR #337 is the immediate release gate; do not mark the slice fully DONE until those checks are green.
- Whole-site visual score moves only modestly from the owner's 6.5/10 baseline after this slice; treat it as approximately **6.6/10 pending production verification**, not as a search-only score.

## Exact next work order

1. Finish PR #337 post-merge deploy/live verification and record production evidence.
2. Audit remaining shared shell geometry at 375/430/768/1024/1440, especially hero ↔ breadcrumbs ↔ main ↔ footer alignment and navigation wrapping.
3. Unify remaining duplicated button/card/breadcrumb spacing values behind Design System tokens/primitives where safe.
4. Improve weak editorial pages in small independent slices, prioritizing `/contacts/`, `/how-to-buy/`, `/rb/`, `/hot/`, `/country/` and representative country pages.
5. Reassess homepage → country → hot/search handoff hierarchy and spacing after standalone shell primitives are stable.
6. Keep `/poisk-turov/` search regressions green; perform any deeper shared-header replacement only atomically with equivalent browser coverage.

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

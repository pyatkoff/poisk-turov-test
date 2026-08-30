# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is site-wide visual unification. Search engineering remains mature and protected, but whole-site product coherence is the active product priority.

Canonical priority after emergency overrides:

`design_system_site_wide_visual_unification → supporting_technical_consolidation → content_seo → cosmetic_cleanup`

Emergency/product safety still overrides this phase in the order defined by `AGENTS.md`: production broken → lead loss → incorrect data → broken visual/user journey. Successful unrelated releases or autonomous state refreshes must not replace the owner phase. Only a new explicit owner direction may change `OWNER_PRIORITY.json`.

## Design System 1.0 objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Keep one coherent header/navigation, one footer, shared typography, spacing/grid, buttons, cards, breadcrumbs and responsive rules.
3. Use the mature search experience as the quality reference without making editorial pages unnecessarily dense.
4. Fix confirmed overflow, wrapping, crooked spacing, duplicated shell and hierarchy problems before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 where relevant.
6. Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior and all protected external contracts.

## Current resume point

Material Design System progress in the latest pass:

- #363 is fully production/live green: homepage, editorial pages and search now use one PHP footer markup owner; duplicate JS footer markup is gone while verified social/app/legal destinations remain unchanged.
- #365 aligns the mature search header's top-level desktop/mobile navigation with the shared public header — Search, Countries, Hot, Early booking, Contacts — and passed the dedicated 375/430/768/1024/1440 search-header gate before merge.
- Direct review of full-page visual artifacts exposed a severe common primitive regression on `/hot/` and `/rb/`: the late generic `.sp-card` background painted blue CTA callouts white while their copy stayed white. #367 restores the blue callout variant in the final shared primitive layer. The complete PR visual/regression suite is green, and new desktop/mobile screenshots confirm the callouts are readable and visually coherent again. Final standalone production deploy is still running.
- #366 was refreshed on top of current `main` after the shared-CSS conflict so it preserves the callout repair while balancing the third related-destination card at 561–768px.

Whole-site coherence remains conservatively **6.8/10** until the newest production gate closes. This is a site-wide product score, not the stronger search-only engineering score.

Next work order:

1. Finish #367 production/live verification, including public pages, unchanged lead bridge and live search smoke.
2. Take refreshed #366 through fresh CI/visual evidence and merge only if the callout repair remains intact.
3. Continue screenshot-driven audit of `/how-to-buy/`, `/rb/`, `/hot/`, `/country/`, `/contacts/` and representative country pages at 375/430/768/1024/1440; fix only confirmed defects.
4. After visible defects are cleared, choose the next smallest compatibility-preserving step toward one shared header implementation rather than blindly replacing the mature search shell.
5. Keep mature search/recovery/results/comparison/flight/price/fuel/lead regressions green throughout.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred. PRs #248/#249/#254 are not automatic merge candidates without fresh scope-specific review.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs, fresh CI/deploy and live production evidence. Use visual artifacts instead of inferring appearance from CSS when available. Do not invent visual defects merely to produce commits. If one visual item is blocked, record/defer it and continue another safe page/component. Deploy only after relevant checks are green, then verify production/live behavior.

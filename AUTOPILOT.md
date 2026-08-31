# poisk-turov-test — Autopilot State

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether the former refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction makes AnyTour Design System 1.0 and site-wide visual unification priority #1 after emergency production, lead-loss, incorrect-data and broken-user-journey overrides. Do not confuse the stronger search-flow engineering score with whole-site visual quality.

Canonical priority after emergency overrides:

`ux_visual → technical_refactor → content_seo → cosmetic_cleanup`

## Design System objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Keep one physical shared header/navigation and one footer; remove duplicated or stale shell expectations when confirmed.
3. Use shared design tokens/primitives for typography, grid, spacing, buttons, cards, breadcrumbs, surfaces and responsive behavior.
4. Preserve the mature search/recovery/results/comparison/flight/price/fuel/lead journey while improving outer-shell continuity.
5. Validate user-facing changes at 375/430/768/1024/1440 and fix confirmed overflow, wrapping, hierarchy and spacing issues before cosmetic flourishes.
6. Keep editorial pages clear and lighter than search/results while maintaining the same visual language.

## Latest material progress

- Homepage discovery now follows the quick search directly: country/destination, hot tours, early booking and full search are reachable before explanatory benefit content.
- Homepage route-specific surfaces now consume shared Design System 1.0 brand/surface/radius/spacing/focus tokens through a narrow alignment layer instead of drifting further from the shared shell.
- The five-width standalone content sweep is green at 375/430/768/1024/1440 across home, search, contacts, how-to-buy, hot, early booking, country catalog and representative country pages, with no horizontal overflow and stable shared header/footer geometry.
- A stale navigation guard that still required the intentionally removed top-level `Как купить` link was corrected to match the canonical shared header.
- Search form parameters, Tourvisor, Metrika/analytics and lead contracts were not changed.

## Exact next work order

1. Confirm the merged homepage slice on production after deploy, including live search smoke and production five-width standalone evidence.
2. Audit the full production journey home → country/destination → hot/search → results → selected tour → lead at 375/430/768/1024/1440.
3. Fix only confirmed shell/hierarchy/spacing/wrapping/overflow inconsistencies, starting with representative country/destination pages and then other weak editorial pages.
4. Continue shared primitive adoption where legacy page-specific surfaces still visibly diverge.
5. Recalculate the whole-site visual score only after production evidence justifies movement; do not infer it from the stronger search-only score.
6. Continue independent technical consolidation only where it directly enables safe Design System migration or removes proven duplication.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- unresolved legal/payment content.

Preserve verified social/app destinations and the AnyTour logo. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe. Ignore stale/conflicting priority restoration work such as PR #433 unless the owner explicitly changes direction.

## Execution policy

At the start of each run inspect current `main`, open PRs, fresh CI/deploy results, production behavior and this resume point. Work in narrow independent safe slices. If one task is blocked, record/defer it and continue other safe Design System work. SAFE changes may merge after narrow relevant checks; MEDIUM user-facing changes require focused regression evidence plus relevant broader CI before release.

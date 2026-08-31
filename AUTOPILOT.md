# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether the old refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TOUR DATA PLATFORM

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the primary roadmap is the first-party AnyTour Tour Data Platform. It must power active departure cities, hot tours, SEO offer pages, search ranking, the low-price calendar and price recommendations while Tourvisor remains the source of truth for live availability and final bookable price.

Design System 2.0 remains important product packaging, but cosmetic work does not outrank the shared data layer. Technical refactor is supporting work only when it directly helps correctness, reliability or delivery.

## Ordered work

1. Use free Tourvisor catalog data to expose only departure cities that currently have destinations.
2. Persist passive tour/price observations from successful live user searches without blocking the user flow.
3. Build reliable current hot-tour snapshots in the local DB and render `/hot/` from real inventory.
4. Derive a current low-price calendar from comparable live results before making historical claims.
5. Aggregate price history and add guarded price recommendations only with sufficient comparable data.
6. Build SEO offer snapshots for country/resort/month pages from the same first-party data layer.
7. Improve search ranking/recommendations using accumulated availability and price knowledge, while refreshing critical live prices through Tourvisor.
8. Continue Design System 2.0 where needed to present these products coherently.
9. Do technical consolidation only where it unlocks or protects the roadmap.

## Current resume point

Active-departure filtering has been implemented through the local departure catalog: the complete Tourvisor departure directory is stored, while `is_active` is based on free `/countries?departureId=...` availability checks. Verify the production catalog sync and visible selector count. Then implement passive tour/price observation persistence from successful search results as the next major data slice.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika or goals, the external lead contract, Tourvisor live-search/final-price contract or neighboring projects. Cached/history data must never be presented as a guaranteed current bookable price without live refresh where required.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy and production evidence. Pick the highest-value independent Tour Data Platform slice and finish it. If blocked, record/defer the blocker and continue another safe data/product task. Keep PR #254 deferred unless a fresh explicit review proves its separate DB/platform architecture safe.

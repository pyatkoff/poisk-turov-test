# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records that the old refactor-first lock is inactive, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TOUR DATA PLATFORM

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is accumulating trustworthy tour data. Technical refactor is supporting work only when it is required to deliver this path safely.

## Ordered work

1. Keep the Tourvisor catalog and real departure→country availability matrix fresh.
2. Accumulate tour observations across real markets with bounded broad searches, never one paid/search request per hotel.
3. Preserve normalization, deduplication, observation timestamps, attempt history and independent search/day evidence.
4. Build reliable daily minima, hotel minima, price history, confidence and percentile/baseline aggregates.
5. Power hot tours, the price calendar and price recommendations from those aggregates while verifying current/final bookable price through live Tourvisor.
6. Use accumulated catalog/offer data for SEO country/resort/hotel pages and search ranking/recommendations.
7. Finish UX/Design System 2.0 packaging after the underlying data products are real enough to justify the final surfaces.

## Current resume point

The production departure-country matrix contains real market coverage and the bounded scheduled-monitor collector is the active stage. Verify its first production attempts, use the attempt ledger and coverage reports to improve target selection, then grow independent search/day history safely. Do not spend autonomous cycles on spacing/alignment/cosmetic cleanup unless it fixes a correctness or conversion regression.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, the external lead contract or field mapping, Tourvisor contract, or neighboring projects. The accumulated database is a knowledge layer; current availability and lead/purchase-critical price remain live Tourvisor truth.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI, production coverage/collector evidence and these source-of-truth files. Choose the highest-value independent Tour Data Platform slice and carry it through a narrow PR and relevant CI. SAFE/MEDIUM changes may be merged autonomously after green evidence. If blocked, record/defer the blocker and continue another independent data-platform task.

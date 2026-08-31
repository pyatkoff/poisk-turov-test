# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` independently records the active technical phase lock, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current order is:

`technical_refactor → ux_visual → content_seo → cosmetic_cleanup`

The technical pass is not style cleanup. It exists to remove architectural ambiguity and duplicated ownership before further UX/visual expansion.

## Required sequence

1. Freeze a single architecture/source of truth in `ARCHITECTURE.md` and `TEST_MATRIX.md`; enforce **one concept → one implementation** unless a documented compatibility boundary requires otherwise.
2. Complete the repository inventory/dependency map and classify active, compatibility and dead files from actual consumers rather than names alone.
3. Complete the GitHub Actions audit. Classify checks into **PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE**. Remove or merge checks only after equivalent coverage is demonstrated.
4. Prepare clear ownership for `shared/`, `search/`, `results/`, `tour/`, `checkout/`, `integrations/`, `site/`, `seo/`, `tests/`, `scripts/`, `templates/` without changing user-visible behavior or external contracts.
5. Consolidate the shared template layer only after dependency evidence is sufficient: one header, one footer, one navigation and one design system source of truth.
6. Resume UX and visual work after technical consolidation.

## Current resume point

Architecture/source-of-truth and workflow inventory work already exists in the repository, but the exhaustive CI audit is not yet complete. Resume with remaining workflow families, then continue non-manifest/PHP/deploy consumers in the dependency map. Do not delete duplicate-looking workflows until coverage equivalence is proven.

Recent production hotel-layout fixes, shared shell work and their regression guards remain valid evidence and must be preserved; they do not supersede the current technical-refactor-first owner direction.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not change without explicit owner approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects.

## Execution policy

At the start of each run inspect fresh `main`, open PRs and recent CI. Read `AGENTS.md`, this file and `AUTOPILOT_STATE.json` before editing. Choose one highest-value independent slice and take it to completion. Use narrow PRs. SAFE/MEDIUM technical changes may merge autonomously after relevant green checks. If blocked, record the blocker and continue another independent technical slice.

Do not refactor for style and do not invent defects. Preserve behavior unless a confirmed defect or separately approved product task requires a change.

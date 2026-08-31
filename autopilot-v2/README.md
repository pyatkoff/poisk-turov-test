# AnyTour Autopilot 2.0 MVP

Autopilot 2.0 is an orchestration layer on top of the existing project, not a replacement for production runtime code.

## MVP goals

- keep one machine-readable project contract;
- keep one explicit task state machine;
- separate Orchestrator, Developer, QA and Visual QA responsibilities;
- preserve the lean CI policy introduced by the CI-diet pass;
- import current priorities from `AUTOPILOT_STATE.json` without deleting Autopilot 1.x;
- start in `dry_run` mode so the orchestrator can prove task selection and gate selection before autonomous product mutations.

## State flow

`discovered -> triaged -> ready -> coding -> qa -> visual_qa -> deploy -> production_qa -> done`

A task may move to `blocked` from any state. Visual QA may be skipped when QA records that no user-facing/shared-UI risk exists.

## Gate policy

Normal coding loop: `Fast CI` only.

Production release: existing deploy workflow plus `Production smoke`.

Deep live/visual/SEO regression suites are on-demand evidence tools, not automatic gates. New permanent workflows require a demonstrated coverage gap.

## Compatibility with Autopilot 1.x

`AGENTS.md` remains the authority for autonomy, hard boundaries and priority order. `AUTOPILOT_STATE.json` remains readable as the current product/roadmap source while migration is in progress. `autopilot-v2/state.json` is the v2 execution state.

The first pilot task is deliberately non-mutating: re-audit the current standalone results/selected-tour transitions and let the Orchestrator choose the smallest evidence set. Only after the dry run behaves correctly should `mode` become `active`.

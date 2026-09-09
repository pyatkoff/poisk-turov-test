# Rooms / Flights / Price CI Audit

Status: verified batch 3 companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`. This slice changes no workflow behavior.

| Workflow | Trigger / scope | Tier | Protected behavior | Disposition |
| --- | --- | --- | --- | --- |
| `validate-room-recovery.yml` | PR to `main` for `v2/room-details-v3.js`; manual | PR BROWSER | room-details error state, retry, empty-data fallback, stale async response isolation and successful render at 375/768/1440 | KEEP; isolated local behavioral guard, share Playwright setup later |
| `validate-flight-autoload-race.yml` | PR to `main` for `v2/tour-controller-v4.js`; manual | PR BROWSER | automatic flight loading, stale flight response isolation between selected tours, error state and manual retry at 375/768/1440 | KEEP; request lifecycle/race ownership is distinct |
| `validate-flight-empty-recovery.yml` | PR to `main` for `v2/flight-empty-recovery-v1.js`, bundle manifest; manual | PR BROWSER | completed empty-flight state becomes one retry affordance, preserves lead form, and does not interfere once real flight variants exist at 375/1440 | KEEP; decorator/empty-state behavior is distinct from autoload race |
| `validate-flight-keyboard.yml` | PR to `main` for `v2/tour-controller-v4.js`; manual | PR BROWSER | native radio keyboard selection, selected-card state, `v2:flight-selected` event and lead payload stay on the same chosen flight | KEEP; accessibility + lead-boundary behavior is distinct |
| `validate-v2-flight-tradeoffs.yml` | PR to `main` for `v2/flight-price-sync-v1.js`; manual | PR BROWSER | cheapest/direct/connection facts, pending-price confidence, ranking recovery, fuel/lead copy and overflow at 375/768/1440 | KEEP; share browser harness later |
| `validate-flight-fuel-fallback.yml` | PR to `main` for `v2/flight-price-sync-v1.js`; manual | PR BROWSER | flight-specific fuel, tour fallback fuel and explicit zero remain distinct and emit coherent update state | KEEP; narrow deterministic edge-case contract |
| `validate-flights-live.yml` | push to `main` for `v2/tour-controller-v4.js`, `v2/api-v2.php`; manual | POST DEPLOY / LIVE CONTRACT | fresh search → flight variants, segment/default cardinality, price/fuel/baggage/timezone edge profiles and latency | KEEP; endpoint contract is unique, bootstrap is duplicated |
| `validate-rooms-live.yml` | push to `main` for `v2/room-details-runtime.js`, `v2/api-v2.php`; manual | POST DEPLOY / LIVE CONTRACT | fresh search → tour room IDs → rooms endpoint returns at least one requested room | KEEP; endpoint contract is unique, bootstrap is duplicated |
| `validate-flight-price-live.yml` | push to `main` only when its own workflow changes; manual | SCHEDULED / LIVE (effectively manual) | result/tour/default-flight identity, date/hotel and price consistency | KEEP coverage; `TRIGGER-GAP` because runtime/API changes do not invoke it |

## Confirmed findings

The local room/flight browser guards are strong refactor anchors: `validate-room-recovery.yml`, `validate-flight-autoload-race.yml` and `validate-flight-empty-recovery.yml` all load branch-local modules into isolated DOM fixtures and assert observable behavior. Unlike several older V2 browser workflows, these three do not depend on the legacy `anytour.online/poisk-turov-test/v2/` shell.

The flight PR guards overlap by asset/domain, not by protected behavior. Autoload/race owns selected-tour request lifecycle and stale/error/retry safety; empty recovery owns the later empty-state decorator; keyboard selection protects accessibility and lead payload synchronization; tradeoffs protect decision-support and pending-price confidence; fuel fallback protects missing-vs-zero semantics. They are not deletion candidates. Shared Playwright installation/bootstrap and fixture helpers are the safe consolidation target.

`validate-room-recovery.yml` follows the same isolated local Playwright pattern as the strongest flight guards but protects room-details API state. Its setup is a duplication candidate; its room error/empty/stale/success assertions are not.

`validate-flights-live.yml` and `validate-rooms-live.yml` independently start the same Moscow→Turkey fresh search, poll it to completion and sample tours. The duplicated bootstrap is infrastructure overlap; the endpoint verdicts are independent. The safe future direction is one reusable fresh-live-search sampler under `scripts/ci/`, consumed by both workflows while preserving both verdicts.

`validate-flight-price-live.yml` has a confirmed trigger gap: its push path filter includes only its own workflow file. Changes to `api-v2.php`, `tour-controller-v4.js` or flight price synchronization therefore do not automatically invoke this cross-endpoint consistency check. This is CI debt, not evidence of a production defect. Do not broaden the trigger in isolation until the remaining live-family inventory is complete; otherwise one API change may fan out into many expensive overlapping live searches.

## Next safe steps

1. Finish remaining flight pending/unpriced/price-sync workflow inventory.
2. Audit any remaining lead/mobile/UI families before touching their workflows.
3. Extract a reusable fresh-search sampler only after all live consumers are mapped.
4. Extract shared Playwright setup/fixture helpers without combining behavioral assertions.
5. Change triggers or remove workflows only after equivalent coverage is green.

After exhaustive workflow inventory, fold this companion into `CI_WORKFLOW_AUDIT.md` in one documentation-only consolidation so there is again one canonical audit document.

Protected boundaries remain unchanged: no Metrika/goals, external lead contract, Tourvisor external contract, neighboring project or production runtime behavior changes are part of this audit.

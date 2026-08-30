# Rooms / Flights / Price CI Audit

Status: verified batch 3 companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

This slice changes no workflow behavior. It records trigger, tier, protected behavior and consolidation implications from the workflow definitions on `main`.

| Workflow | Trigger / scope | Tier | Protected behavior | Disposition |
| --- | --- | --- | --- | --- |
| `validate-flight-keyboard.yml` | PR to `main` for `v2/tour-controller-v4.js`; manual | PR BROWSER | native radio keyboard selection, selected-card state, `v2:flight-selected` event and lead payload stay on the same chosen flight | KEEP; accessibility + lead-boundary behavior is distinct |
| `validate-v2-flight-tradeoffs.yml` | PR to `main` for `v2/flight-price-sync-v1.js`; manual | PR BROWSER | cheapest/direct/connection facts, pending-price confidence, ranking recovery, fuel/lead copy and overflow at 375/768/1440 | KEEP; share browser harness later |
| `validate-flight-fuel-fallback.yml` | PR to `main` for `v2/flight-price-sync-v1.js`; manual | PR BROWSER | flight-specific fuel, tour fallback fuel and explicit zero remain distinct and emit coherent update state | KEEP; narrow deterministic edge-case contract |
| `validate-flights-live.yml` | push to `main` for `v2/tour-controller-v4.js`, `v2/api-v2.php`; manual | POST DEPLOY / LIVE CONTRACT | fresh search → flight variants, segment/default cardinality, price/fuel/baggage/timezone edge profiles and latency | KEEP; endpoint contract is unique, bootstrap is duplicated |
| `validate-rooms-live.yml` | push to `main` for `v2/room-details-runtime.js`, `v2/api-v2.php`; manual | POST DEPLOY / LIVE CONTRACT | fresh search → tour room IDs → rooms endpoint returns at least one requested room | KEEP; endpoint contract is unique, bootstrap is duplicated |
| `validate-flight-price-live.yml` | push to `main` only when its own workflow changes; manual | SCHEDULED / LIVE (effectively manual) | result/tour/default-flight identity, date/hotel and price consistency | KEEP coverage; `TRIGGER-GAP` because runtime/API changes do not invoke it |

## Confirmed findings

### PR flight guards overlap by asset, not by behavior

Keyboard selection, tradeoff confidence and fuel fallback exercise the same selected-tour/flight area but protect different contracts. They are not deletion candidates. Shared Playwright installation/bootstrap and fixture helpers are the safe consolidation target.

### Rooms and flights live checks duplicate the expensive bootstrap, not the verdict

`validate-flights-live.yml` and `validate-rooms-live.yml` independently start the same Moscow→Turkey fresh search, poll it to completion and sample tours. Only after that do they diverge into flight and room endpoint assertions.

Safe future direction: extract one reusable fresh-live-search sampler under `scripts/ci/`, then call it from both workflows. Preserve both endpoint verdicts until equivalent coverage is proven.

### Flight-price live has a trigger coverage gap

`validate-flight-price-live.yml` proves a valuable cross-endpoint invariant but its push path filter includes only `.github/workflows/validate-flight-price-live.yml`. A change to `api-v2.php`, `tour-controller-v4.js` or flight price synchronization can therefore land without automatically invoking this check.

This is a CI trigger gap, not evidence of a production defect. Do not broaden it in isolation yet: finish the remaining live-family inventory first so a single API change does not accidentally fan out into many expensive overlapping fresh searches. The preferred fix is a shared post-deploy/live sampler with explicit contract suites.

## Next safe steps

1. Finish the remaining room-recovery and flight autoload/empty/pending/unpriced workflow inventory.
2. Audit the lead family before touching any lead-related workflow.
3. Extract a reusable fresh-search sampling helper only after all live consumers are mapped.
4. Extract shared Playwright setup/fixture helpers without combining behavioral assertions.
5. Change triggers or remove workflows only after equivalent coverage is green.

Protected boundaries remain unchanged: no Metrika/goals, external lead contract, Tourvisor external contract, neighboring project or production runtime behavior changes are part of this audit.

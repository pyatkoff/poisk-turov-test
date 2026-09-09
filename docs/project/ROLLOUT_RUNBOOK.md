# AnyTour Search3 rollout and rollback runbook

Status: target operational procedure; production execution not authorized
Updated: 2026-09-04

This runbook describes the release control required before Search3 receives
production traffic. The current repository must first expose exact deployed
and last-known-good identity. Percentage canary capability is a prerequisite
for percentage stages; do not pretend a percentage rollout occurred if routing
cannot enforce and observe it.

## Roles

| Role | Responsibility |
| --- | --- |
| Release owner | selects exact SHA, records evidence, starts/stops each stage |
| Product owner | approves exact visual candidate and residual product risk |
| Engineering verifier | validates contracts, live journey and runtime signal |
| Operations/lead verifier | confirms existing lead handoff when a safe method is available |

One person may hold several roles, but every responsibility must be explicitly
recorded for the release.

## Preconditions

- all gates in `RELEASE_GATES.md` pass on one immutable candidate SHA;
- production baseline and exact LKG SHA are known;
- candidate and LKG are independently deployable without rebuilding from a
  moving branch;
- routing/flag behavior and cache implications are documented;
- current `main` consultant integration and protected lead chain are verified;
- synthetic checks cannot pollute Metrica or create uncontrolled CRM leads;
- rollback has been rehearsed and post-rollback verification passes;
- no concurrent release can expose a partially copied filesystem state.

## Release record template

```text
candidate_sha:
artifact_or_profile_id:
base_main_sha:
production_before_sha:
lkg_sha:
owner_approval:
gate_evidence:
operator:
started_at:
current_stage:
known_risks:
rollback_reason:
rollback_completed_at:
production_after_sha:
```

## Preflight

1. Freeze the candidate SHA; do not release a branch name.
2. Confirm no later deployment or concurrent copy is in progress.
3. Read production and LKG identity from the release mechanism.
4. Re-run fast contract and artifact-integrity checks.
5. Verify isolated candidate at all required widths.
6. Exercise search, first/cheapest tour, flight success and flight
   empty/error/timeout → review → lead fallback.
7. Verify header/footer/logo, privacy/consent links and consultant widget.
8. Record baseline 5xx, JS errors, search failure/latency and safe lead signal.
9. Confirm rollback target, operator access and communication channel.

If exact identity, LKG, protected live checks or rollback are unavailable, stop.

## Staged rollout

| Stage | Exposure | Minimum observation | Advance only when |
| --- | ---: | --- | --- |
| 0 | internal/allowlist | complete scripted + manual journey | every live check passes |
| 1 | 10% | enough real sessions to observe all critical transitions, with a time floor agreed from traffic | no stop trigger; signals comparable to baseline |
| 2 | 25% | same, including a business-hours operational sample | no stop trigger; lead handoff confirmed |
| 3 | 50% | full peak/off-peak window appropriate to traffic | no stop trigger; error/performance stable |
| 4 | 100% | continuous enhanced observation through the agreed stabilization window | release declared stable |

Numerical duration and minimum sample sizes must come from the measured traffic
baseline. Never advance solely because a clock expired or no alert fired.
If percentage routing is not implemented and verified, remain on the isolated
candidate until the owner approves a separately reviewed switch plan.

## Checks at every stage

- deployed identity equals the approved candidate SHA;
- public route and assets return consistent release identity;
- render/search/API/lead-bridge/widget checks pass;
- no increase in page 5xx, critical request failures or JS exceptions;
- search starts and finishes; progressive results and stale isolation work;
- selected tour reaches review with flight and without flight;
- accepted, failed and retry lead states are truthful;
- input/context is retained; no duplicate lead attempt is created;
- accessibility, overflow and responsive smoke remain green;
- compare commercial signals only after synthetic traffic is excluded.

## Immediate rollback triggers

- unknown/mixed deployed version or partially copied release;
- public route outage, sustained 5xx or critical asset failure;
- Tourvisor request/response or pricing contract regression;
- lead path unreachable, silent lead loss, payload/mapping corruption or
  uncontrolled duplicates;
- stale tour/flight data can overwrite the current selection;
- flight empty/error creates a conversion dead end;
- consultant/shared shell/consent critical integration is missing;
- material security/privacy regression;
- inability to observe the release safely.

Pause and investigate before advancing for material performance, accessibility,
content-trust or conversion guardrail regression even if it does not yet meet
an immediate technical rollback trigger.

## Rollback procedure

1. Stop traffic progression and record the trigger/time.
2. Route traffic away from the candidate if an independent flag/profile exists.
3. Redeploy the exact immutable LKG artifact/SHA; never rebuild a moving branch.
4. Purge only the release-scoped cache entries required by the documented
   deployment mechanism.
5. Confirm public asset/page identity is uniformly LKG.
6. Re-run render, live search, selected-tour, no-flight lead fallback, protected
   lead-bridge and widget smoke.
7. Confirm error signals return to baseline and record the result.
8. Keep the failed candidate and evidence intact for diagnosis. Do not combine
   rollback with cleanup or a speculative fix.
9. Open a narrow corrective PR and repeat all gates on a new exact SHA.

## Known platform gaps blocking release readiness

- the current public site does not expose a verified exact production SHA/LKG
  contract for this program;
- the current deploy workflow is triggered by relevant `v2/**` pushes to
  `main` and has no independent Search3 activation/owner-approval gate;
- the deploy workflow copies into the live directory rather than switching an
  already complete immutable release atomically;
- current workflow concurrency reduces overlap but does not itself prove atomic
  visibility to visitors;
- percentage routing/canary capability has not been demonstrated;
- real downstream lead delivery evidence and its safe synthetic method need an
  operations decision.

These gaps are first mapped by `baseline/release-audit`, then corrected only in
separately approved `release/exact-sha-boundary` and cohort-routing slices. They
are not reasons to weaken the gates.

# AnyTour CI Workflow Audit — Runtime / Operations Batch

Status: verified companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

Scope: operational/runtime workflows inspected directly on `main`. This batch changes documentation only; no workflow triggers, runtime behavior, deployment, analytics, lead, or Tourvisor contracts are modified.

## Verified workflows

| Workflow | Trigger / scope | Tier | What it protects | Assertion style | Audit disposition |
| --- | --- | --- | --- | --- | --- |
| `security-guard.yml` | every PR plus every push to `main` | PR FAST | tracked secret files and private-key material must not enter the repository | deterministic `git ls-files` / `git grep` repository assertions | KEEP; canonical repository-wide security gate, not a duplication candidate |
| `autopilot-runtime-state.yml` | successful/failed completion signal from `Deploy V2 only`; manual | POST DEPLOY | persistent CI handoff issue recording deploy result, SHA, run URL and recommended continuation action | GitHub API state update; does not validate product behavior itself | KEEP as operational handoff; legacy `Deploy V2 only` workflow-name coupling must be reconciled with canonical deploy ownership before any trigger change |
| `audit-anytoour-runtime.yml` | manual; push to `main` only when this workflow or `deploy-anytoour.yml` changes | SCHEDULED / LIVE (manual operational audit) | production PHP/FPM/nginx runtime, required PHP extensions, writable/probeable web runtime and `api-v2.php` syntax | direct SSH + temporary HTTPS probe + runtime assertions | KEEP; operational production diagnostic, not a PR gate and not equivalent to deployment smoke |
| `check-anytoour-target.yml` | manual; push to `main` only when this workflow changes | SCHEDULED / LIVE (manual preflight) | deploy credentials, target document root, write access, PHP extensions and presence of expected server files | direct SSH preflight assertions | KEEP; environment-readiness diagnostic distinct from runtime audit because it proves deploy target accessibility/writability rather than serving runtime behavior |
| `measure-v2-results-dom.yml` | manual; push to `main` only when this workflow changes | SCHEDULED / LIVE (manual measurement) | empirical live search result/tour-row counts before and after continue-search | live API calls and printed measurements; only minimal pass/fail around search completion | KEEP as measurement-only diagnostic; it is not a correctness gate and currently targets the legacy `anytour.online/.../v2/api-v2.php` compatibility endpoint |

## Findings

### Security guard is already correctly placed in PR FAST

`security-guard.yml` is cheap, repository-wide and deterministic. It protects a different contract from syntax, browser and release workflows. There is no evidence-based duplication candidate here.

Disposition: retain on every PR and main push.

### Runtime audit and target preflight are complementary, not duplicates

`check-anytoour-target.yml` proves that deploy credentials work, the document root exists/is writable, required PHP extensions are installed and expected server files can be observed. `audit-anytoour-runtime.yml` goes further: it inspects PHP/FPM/nginx characteristics, creates a temporary web probe, verifies the HTTPS-served PHP runtime and lints production `api-v2.php`.

Disposition: KEEP both. If shared SSH/bootstrap is extracted later, preserve the two separate verdicts: deployment readiness vs served runtime health.

### Autopilot runtime state is lifecycle plumbing, not product validation

`autopilot-runtime-state.yml` writes a persistent issue after `Deploy V2 only` completes. It records CI status and recommends the next action, but does not assert the site, search, lead path or release content.

Disposition: classify as POST DEPLOY operational handoff. Do not count it as equivalent coverage for production smoke or live journey checks. Its workflow-name dependency is part of the deploy ownership map that still needs explicit reconciliation.

### Results DOM measurement must not be promoted to a gate

`measure-v2-results-dom.yml` performs a real search against the legacy V2 API, measures hotel/tour-row counts at two result limits and prints the totals. It does not define a stable business threshold for those values, so a green run only proves the measurement completed.

Disposition: keep under SCHEDULED / LIVE manual measurement. Do not use it as evidence that canonical `anytoour.ru/poisk-turov/` result correctness is green. Before changing its endpoint, first establish the canonical-route/live-measurement contract shared with the rest of the legacy-host browser family.

## Consolidation candidates from this batch

No workflow in this batch is safe to delete.

Potential infrastructure-only consolidation after dependency mapping:

1. shared SSH key preparation for `check-anytoour-target.yml` and `audit-anytoour-runtime.yml`;
2. shared target-host connection options and secret validation;
3. explicit deploy-workflow ownership constant/helper so POST DEPLOY handoff does not silently depend on stale workflow display names.

These are implementation deduplication candidates only. The behavioral/operational verdicts must remain separate.

## Protected boundaries

No changes in this batch may alter Yandex Metrika/goals, analytics contract, external lead contract or field mapping, Tourvisor contract, neighboring projects, or production user behavior.

# Codex Execution Queue

Purpose: keep a small, self-contained queue of implementation slices that can be pasted/launched in Codex without reconstructing project context manually.

Codex is an execution lane. `AGENTS.md`, `ARCHITECTURE.md`, `AUTOPILOT.md`, `AUTOPILOT_STATE.json`, `TEST_MATRIX.md` and the explicit task below remain authoritative.

## Operating rules

- Work only in `pyatkoff/poisk-turov-test`.
- Before editing, read `AGENTS.md`, `ARCHITECTURE.md`, `AUTOPILOT.md`, `AUTOPILOT_STATE.json`, `TEST_MATRIX.md` and relevant implementation/tests.
- One Codex task = one narrow branch/worktree and one coherent PR-sized slice.
- Parallel lanes should not edit the same files unless explicitly coordinated.
- P0 production, lead-loss or incorrect-data findings interrupt this queue.
- Never autonomously change Yandex Metrika/goals, analytics external contract, external lead-sending contract/field mapping, Tourvisor external contract, server/platform architecture or neighboring projects.
- Do not invent defects or refactor for style alone.
- Run narrow checks first, then the broader regression required by `AGENTS.md`.
- Report root cause/audit conclusion, files changed, checks/results and remaining risk.

## READY — C1 / QA-INFRA — complete CI inventory

**Risk:** SAFE

**Ownership:** `.github/workflows/**`, `scripts/ci/**`, CI audit/test documentation only. Do not change production runtime.

**Goal:** finish the exhaustive workflow inventory before changing triggers or deleting/consolidating checks.

**Task:**

1. Read the existing CI audit documents and `TEST_MATRIX.md`.
2. Inventory the remaining room-recovery, flight autoload/empty/pending/unpriced, lead, search/results, visual and live workflow families not yet fully classified.
3. For every workflow record trigger/path scope, tier (`PR FAST`, `PR BROWSER`, `POST DEPLOY`, `SCHEDULED/LIVE`), protected behavior, overlap, trigger gaps and disposition (`KEEP`, `CONSOLIDATE INFRA`, `REPLACE AFTER COVERAGE`, `CANDIDATE DEAD`).
4. Distinguish duplicated bootstrap/setup from duplicated behavioral assertions. Do not propose deleting a workflow merely because it touches the same asset.
5. Do not change workflow triggers or runtime behavior in this slice unless a documentation-only correction is necessary.
6. Consolidate companion audit notes into one canonical audit only when the inventory is complete and no information is lost.

**Done when:** all workflows are classified and the next extraction/consolidation targets are explicit, with protected lead/search/price/flight/visual coverage preserved.

## READY — C2 / ARCHITECTURE — dependency generations map

**Risk:** SAFE

**Ownership:** architecture/dependency documentation plus read-only source inspection. No production runtime changes.

**Goal:** establish a verified usage graph for historical generations before any deletion/move.

**Task:**

1. Map current consumers of versioned/historical implementations, especially analytics generations, API generations, lead adapters, search lifecycle/results/tour generations and compatibility entrypoints.
2. Label each candidate `ACTIVE`, `COMPATIBILITY`, `DEPRECATED`, or `DEAD-CANDIDATE` with concrete references.
3. Record protected external contracts separately from implementation generations.
4. Identify cases where load order is effectively an API and where a consumer migration must precede deletion.
5. Do not delete or move files in this slice.

**Done when:** the dependency map is evidence-backed enough that a later Codex task can remove/migrate one proven generation without guessing.

## READY — C3 / QA-INFRA — reusable live-search bootstrap design

**Risk:** SAFE

**Depends on:** C1 complete.

**Ownership:** `scripts/ci/**` and workflow test infrastructure. No production runtime.

**Goal:** remove repeated fresh-search bootstrap from live room/flight checks without combining their behavioral verdicts.

**Task:**

1. Compare all mapped live workflows that start the same fresh Tourvisor search and poll to completion.
2. Design/extract the smallest reusable sampler/helper under `scripts/ci/` only if C1 proves consumers and inputs are compatible.
3. Preserve independent rooms/flights/price assertions and failure messages.
4. Keep external Tourvisor contract unchanged.
5. Run each affected workflow/diagnostic path or equivalent local deterministic checks.

**Done when:** duplicated infrastructure is reduced while independent contract coverage remains equivalent or stronger.

## BLOCKED UNTIL INVENTORY — C4 / STRUCTURE — nested asset ownership

**Risk:** MEDIUM

Do not start until C1/C2 establish current consumers. Refactor `v2_asset()` to permit controlled allowlisted subdirectories, then migrate only one low-risk asset family as proof. Preserve asset URLs/order/cache behavior and run bundle/startup/visual checks.

## DEFERRED — C5 / SHARED-UI — full search header component migration

**Risk:** MEDIUM

Full `/poisk-turov/` migration from legacy `.at-site-header` to shared `site-header-v2` remains deferred until an atomic extraction/edit path exists. Isolated evidence-backed CSS alignment remains allowed, but this queue must not use header migration as a refactor placeholder.

## Lane model

- `QA-INFRA`: tests, CI, diagnostics, release evidence.
- `ARCHITECTURE`: dependency/source-of-truth/ownership mapping.
- `SEARCH-CORE`: form, dictionaries, lifecycle, recovery.
- `RESULTS-TOUR`: results, filters, comparison, hotel/tour, rooms/flights/prices.
- `CONVERSION`: selected tour and lead UX around the protected external lead contract.
- `SHARED-UI`: header/footer/navigation/design system/templates.
- `SITE-SEO`: public pages, SEO registry/sitemap/internal links and search handoff.

Only promote a task to `READY` when its ownership does not conflict with another active Codex lane and required predecessor evidence exists.

# AnyTour parallel delivery system

Status: execution governance only. It does not authorize merge, preview deployment, or production deployment.

## Operating decision

Use one accountable day-to-day orchestrator, up to three concurrent write-heavy worktrees, and independent read-only reviewers. Keep no more than four active change lanes plus one parked HIGH-review lane. More writers would increase collisions in the historically flat `v2/` tree faster than it would increase delivery speed.

The current optimal lanes are:

| Lane | Model | Write scope | Depends on | Output |
| --- | --- | --- | --- | --- |
| Program orchestration | GPT-5.6 Sol / High | integration queue only | current main + all active drafts | dependency and integration decisions |
| Release architecture | GPT-5.6 Sol / Ultra | read-only, milestone use only | exact candidate + all release evidence | cross-program decision or final go/no-go |
| Search contract boundary | GPT-5.6 Sol / High | contract fixtures/tests and one CI owner | fresh `main` | protected behavioral baseline |
| SEO foundation | GPT-5.6 Terra / High | SEO inventory/docs/tests and one SEO CI owner | fresh `main` | acquisition foundation and prioritized backlog |
| Search UI slice | GPT-5.6 Sol / High | exactly one state/view owner | accepted reference + frozen contracts | tested release-candidate increment |
| Visual evidence | GPT-5.6 Terra / High | read-only | frozen reference + candidate SHA | five-width/state evidence and P0/P1 list |
| Integration review | GPT-5.6 Sol / High | read-only | candidate branch + dependency queue | ready/blocked verdict |

Use Luna/Medium only after a task becomes mechanical and has an exact expected output, for example inventory extraction or formatting an already-defined fixture.

Ultra is deliberately not the permanent execution mode. Use it for initial architecture, a material dependency conflict, a HIGH-risk platform decision, and final release go/no-go. Routine orchestration and implementation stay on High so feedback arrives faster.

## Active dependency map

| Work item | Relationship | Readiness condition |
| --- | --- | --- |
| #1295 project definition | governance base | owner accepts canonical program sources |
| #1296 release baseline | stacked on #1295 | #1295 accepted first |
| #1297 exact-SHA containment | parked standalone HIGH draft | split into narrow platform slices; LKG/rollback/writer blockers and explicit HIGH review closed |
| #1298 Search3 reference dossier | standalone SAFE draft | reference identity/evidence reviewed; reconcile its security-workflow overlap with #1295 |
| `contracts/search3-boundaries` | independent implementation lane | frozen behavioral tests green; no runtime diff |
| `seo/anytour-foundation` | independent implementation lane | factual baseline green; no route/indexability behavior change |
| first Search3 UI candidate | queued, not yet an active change lane | durable reference evidence + contract boundaries accepted and one narrow state/view owner chosen |

Search contracts and SEO can run concurrently because their owned files and product responsibilities are different. UI implementation must consume the frozen contracts; it must not race ahead by copying the Search3 branch.

## File ownership and collision rules

| Zone | Single active writer |
| --- | --- |
| release workflows and `scripts/release/**` | release containment lane |
| contract fixtures/tests and their CI owner | search contract lane |
| SEO inventory/docs/tests and their SEO CI owner | SEO foundation lane |
| `v2/**` Search3 candidate slice | one UI lane only |
| project source-of-truth documents | governance lane |
| reference screenshots/manifests | evidence lane after reference source is durable |

An agent encountering an owned file must stop and return a collision. Cross-lane cleanup is deferred. Every branch starts from a declared full main SHA, has one outcome, and is rebased or rebuilt from current main before readiness review.

## Integration sequence

1. Freeze reference and protected contracts.
2. Continue SEO foundation independently without duplicating transactional search logic.
3. Select one Search3 state owner, then migrate one narrow UI slice from fresh main.
4. Run focused contracts and the relevant broader browser matrix.
5. Obtain an independent integration verdict.
6. Publish an exact-SHA isolated preview only when preview deployment is explicitly authorized.
7. Obtain visual owner approval.
8. Prepare an exact-SHA production release only after HIGH platform gates and explicit production authorization.

## Preview and production are different permissions

`push`, `draft PR`, `merge`, `preview deploy`, and `production deploy` are five separate authority levels.

The currently recorded user authority is `push + draft PR`, with no merge or deploy. Therefore branches and CI may progress, but no workflow may be dispatched and no branch that auto-deploys may be pushed.

Recommended preview policy when authorized:

- manual-only exact-SHA deployment to an isolated `noindex` route;
- production lead submission disabled without changing the production lead contract;
- immutable candidate identity visible in evidence;
- five-width screenshots and deterministic empty/error/timeout fixtures;
- no route, Metrika, Tourvisor, pricing, or production mutation.

Recommended production policy:

- current protected main SHA only;
- successful retained exact-SHA artifact;
- explicit per-release owner approval;
- predeclared rollback/LKG target;
- protected-contract and five-width evidence;
- post-deploy route, search, lead-invocation, identity, and visual verification.

## Branch definition of done

Each lane reports:

`base SHA → branch/tree SHA → owned files → protected areas → focused CI → broader CI → visual/live evidence → rollback condition → remaining blocker`.

Green CI without the applicable visual, live, or production evidence is `DONE / awaiting evidence`, not released.

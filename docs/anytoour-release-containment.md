# AnyTour release containment and exact-SHA boundary

Status: **HIGH / draft only / do not merge or deploy**. This slice builds an exact, reviewable release candidate and makes the canonical deploy manual and fail-closed. Production activation remains blocked on repository settings, pinned host keys, an isolated rehearsal and the independent-writer decision below.

## Scope invariants

- Existing public application URLs and route behavior are unchanged. The release control files are private files under the SSH account `$HOME`; this change adds no HTTP endpoint.
- Lead payload, field mapping and delivery behavior are unchanged.
- Yandex Metrika configuration and goals are unchanged.
- Tourvisor requests and response handling are unchanged.
- No tracked `v2/**` source is changed by this slice.
- A pull request, merge or ordinary branch push cannot invoke `Deploy anytoour.ru`. A merge of the tooling builds and retains a candidate only.

## Exact candidate and deploy flow

1. `Build anytoour.ru release` runs for an eligible `main` push or explicit dispatch on `main`.
2. It lints every packaged PHP file, validates all 44 active manifest JavaScript files and the combined bundle, and renders the homepage/search entrypoints with their existing URL contracts.
3. It builds twice from Git objects at the full commit SHA and requires byte-identical same-run outputs.
4. It retains one target-specific GitHub Actions artifact for 30 days. The artifact binds the build run/attempt, commit SHA, repository tree, `v2` tree, target mapping, archive digests and per-file bytes/modes/digests.
5. `Deploy anytoour.ru` is `workflow_dispatch` only. The requested release must equal the workflow run's full `github.sha`, originate from `main`, have the exact successful build run/attempt and remain an ancestor of `origin/main` immediately before mutation.
6. The ancestor rule is intentional: re-running the same failed workflow at SHA `Y` can resume `Y` even if `main` later advances to `Z`. A new dispatch at `Z` cannot impersonate `Y` because `release_sha == github.sha` is still mandatory.
7. Deploy semantically reconstructs the expected files, modes and control documents from Git objects at that SHA. It does not require compressed bytes to reproduce across a later Python/zlib runner version. The archive digests are captured across semantic verification and compared with freshly computed digests in the preparation step before either archive is sent.
8. Both servers are prepared and fully validated before the first application mutation. Preparation uses random private `$HOME` directories (`0700`), archive/secret files (`0600`) and a receipt binding target, SHA, tree, run, attempt and digests.
9. Only after both preparations succeed does activation install legacy lead helpers/entrypoint and then public files. Each file is installed through a same-directory temporary file and atomic `mv`; the legacy receiver and public bridge entrypoints are installed last.
10. Remote activation accepts only the exact transition `active X -> Y`, `activating Y / previous X -> resume`, or an already exact `active Y`. Every other state fails before a new mutation.
11. Canonical deploy SSH uses pre-provisioned pinned `known_hosts`, `StrictHostKeyChecking=yes` and no runtime `ssh-keyscan`.
12. Core Actions in build, deploy, validation and the downstream production verifiers are pinned to reviewed full commits: `actions/checkout` v6 at `d23441a48e516b6c34aea4fa41551a30e30af803`, `actions/upload-artifact` v4 at `ea165f8d65b6e75b540449e92b4886f43607fa02`, and `actions/download-artifact` v4 at `d3f86a106a0bac45b974a628896c90dbdf5c8093`. Version comments are informational only.

## Target-specific lead and layout mapping

The public artifact starts from all tracked `v2/**` blobs at the exact source commit and applies the existing final-server layout before upload:

| Public target | Exact Git source |
|---|---|
| `lead-adapter-v2.php` | `v2/lead-bridge-v1.php` |
| `search-page-v2.php` | `v2/index.php` |
| `index.php` | `v2/home-entry-v1.php` |

The direct Bitrix adapter is never the active public `lead-adapter-v2.php` in the public archive. The physically separate legacy artifact contains exactly:

- `lead-receiver-v1.php`
- direct `lead-adapter-v2.php`
- `lead-price-v1.php`
- `lead-idempotency-v1.php`

Neither artifact contains `config.php`, `site_conf.php`, `.anytoour-bridge-secret` or ambient working-tree files. The shared HMAC secret remains persistent server state and is transferred only through pinned SSH inside private staging.

## Private identity, bootstrap and retry

The public component records `$HOME/.anytoour-public-release.json` and a content-addressed private manifest. The legacy component records `$HOME/.anytoour-legacy-lead-release.json`. These files and the legacy bridge secret must be regular, non-symlink files owned by the current SSH deploy UID with mode `0600`; canonical deploy rechecks that boundary after live smoke. They identify the exact activation snapshot; they are not public routes.

For the first activation only:

- `release_sha`: exact full SHA used to dispatch the workflow on `main`;
- `build_run_id` and `build_run_attempt`: the exact retained successful build;
- `expected_current_sha`: `absent`;
- `allow_identity_bootstrap`: `true`;
- `confirmation`: `DEPLOY_ANYTOUR_EXACT_SHA`.

Both hidden identities must be absent. If the legacy bridge secret is absent, preparation creates it privately and activation installs it atomically. After bootstrap, use `allow_identity_bootstrap: false` and the previous active full SHA as `expected_current_sha`.

If activation is interrupted after an identity becomes `activating`, do not start a new release. Re-run **the same workflow run at the same SHA with the same inputs**. Preparation revalidates both hosts; `activating Y` is reapplied and finalized, while an already exact `active Y` is hash-verified and treated idempotently. Cleanup takes the same component lock and never deletes staging owned by a surviving activation.

The SSH deploy account is a trust boundary. The protocol trusts that account's UID and `$HOME`: another process or actor running as the same UID can alter locks, handles, identities, manifests or the bridge secret. Owner/mode checks prevent cross-UID ownership and accidental disclosure; they do not defend against compromise of the trusted deploy UID.

Cleanup waits briefly for the same component lock used by activation. If the lock is busy, cleanup fails closed and leaves the prepared directory in place. A runner, SSH session or host crash can therefore leave an orphan. Remove an orphan only through a separately reviewed operator procedure that acquires the same component lock, confirms that no activation survives and validates the exact private handle before deletion; do not use blind age-based deletion.

An identity that already says `active Y` while a managed byte, private manifest, bridge-secret value/ownership/mode or identity metadata has drifted is also a deliberate fail-closed state. Re-running the same `Y` does not repair it. Before production, approve a separate recovery procedure for either force-reapplying exact `Y` from its verified candidate or advancing to a newly built `Z`, including evidence, lock acquisition and reviewer requirements. Do not hand-edit an identity to bypass the mismatch.

## Required settings before any activation

Configure the `anytoour-production` GitHub Environment:

- required reviewer(s), with self-review prevented where supported;
- deployment branch restricted to `main`;
- existing deployment secrets deliberately assigned to the environment;
- `ANYTOOUR_DEPLOY_KNOWN_HOSTS` and `DEPLOY_KNOWN_HOSTS`, populated with host-key lines verified through an independent trusted channel.

Configure a `main` branch ruleset so the source boundary is actually trusted:

- pull-request-only changes and restricted direct pushes;
- force-push and deletion protection;
- required release/core/security validation checks;
- restricted ruleset bypass.

Provision both document roots and every managed parent directory as trusted infrastructure: no symlinked managed path components and no write access for untrusted UIDs. If the web/PHP runtime shares the deploy UID, compromise of that runtime is compromise of the release boundary and must be removed or explicitly accepted before activation.

Do not obtain host keys with `ssh-keyscan` inside deploy: that would trust the same network path being authenticated.

## Residual HIGH blockers

The hidden identity means “these managed bytes matched this Git SHA when canonical activation completed.” It is **not** a continuous exact-docroot attestation yet.

- Existing scheduled/push production writers (`bootstrap-anytour-data`, catalog/hot/price collection and rollup workflows, favicon deploy and related data jobs) can still copy or generate files outside this release lock.
- The existing post-deploy resort materializer intentionally rewrites tracked `sitemap.xml`, and the hotel lock/materializer can change generated routes. Their triggers are left unchanged to preserve the current public URL/indexation behavior.
- The overlay does not remove a previously managed path that disappears from a later Git SHA, so a stale endpoint may survive.
- File replacement is atomic per file, not an atomic whole-docroot `current` switch.
- There is no promoted permanent LKG pointer, exact automatic rollback after artifact expiry, or automatic rollback after a failed live smoke.
- Two hosts are not one transaction. The transition is resumable and observable, but not atomically committed across both servers.
- Other legacy production workflows still use their inherited SSH policy; pinned-host enforcement in this slice applies to the canonical deploy only.
- Same-UID compromise is outside the owner/mode boundary, and an `active Y` byte/secret/mode mismatch has no reviewed force-reapply-`Y` or new-`Z` recovery procedure yet.

Do not mark this PR ready or dispatch production until owners choose one explicit ownership model for independent mutable paths: bring every writer under the release lock/identity protocol, or exclude and document runtime-owned paths in a separately reviewed manifest contract.

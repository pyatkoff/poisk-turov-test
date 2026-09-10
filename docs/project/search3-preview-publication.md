# Permanent publication of the shared Search3 preview

Owner authorization: 2026-09-10, coordinator #996. This is delivery infrastructure
only. It does not approve production #1334/#1493 or copy release product code to main.

## One publisher, explicit exact request

`.github/workflows/deploy-search3-whole-site-preview.yml` lives on main. It has no
push, schedule or automatic build-completion publication trigger. The same publisher
accepts **Run workflow** on main, or a new single-line comment by repository owner
`pyatkoff` (numeric account ID 226193297), only on issue #996:

```text
/publish-search3-preview SOURCE_SHA RELEASE_SHA BUILD_RUN_ID ARTIFACT_ID
```

SHA values must be full lowercase 40-character hashes. RELEASE_SHA is the fresh
head of `release/search3-production-ready-v1`; SOURCE_SHA is the successful artifact
source, which may differ only by documentation/state from that release. A source
from the ANEX branch cannot replace the shared site. Edited comments, PR comments,
other actors, arbitrary branches and rerun attempts are rejected. Never place
credentials, a path, shell code or server address in the command.

The initial retained package is source `bcd32c86c7fea0a4d05ea9d796ffea115c87ee79`,
release `d99a3ec6266985cc0fdbf62114e29557b36d2d4d`, build `34488963710`, artifact
`10156978735`. These are historical inputs, not automatic defaults. Recheck the
current release, completed runs, artifact expiration and #996 writer ownership.

## Gates and evidence

The trusted control code comes only from main, not the source/artifact branch.
It checks current main/release identity, runtime-tree equivalence, successful exact
build and Security guard, all returned current checks, artifact run/name/SHA/digest,
ZIP inventory, safe tar paths/types/size limits, manifest, every file hash and the
preview invariants. It reuses the artifact; it never rebuilds or executes its code
on the runner. The only credentials used are the existing AnyTour deployment
secrets, with read-only GitHub permissions and no persisted checkout credentials.

Server writes are restricted to `/_preview/search3-site-candidate/` and its private
staging/backup/receipt siblings, plus temporary upload/HTTPS-binding files. The
production document root, protected endpoints/configuration, SEO directives and
Andromeda own-preview API/module are fingerprinted, not changed. Strict SSH host
checking is combined with a random HTTPS nonce binding before publication.

Publication is serialized with the existing delivery concurrency group and a
server-side lock. A changed predecessor or unknown earlier outcome stops mutation;
locks are not stolen. Previous preview and ownership receipt remain available.
On a failed live check, rollback requires ownership by this exact deployment and
an unchanged candidate inventory; it never overwrites a later writer. The previous
inventory and protected fingerprints must match after rollback. An identical,
completed source/artifact with an identical installed inventory is a read-only
publication no-op (apart from the temporary origin-binding probe).

Live acceptance checks nine existing routes, noindex, counter 0, physically disabled
lead endpoint (403 synthetic probe only), denied internal PHP, served CSS/JS hashes,
production health and unchanged production/INT fingerprints. No supplier search or
real lead is sent. A receipt is retained as `search3-site-deployment-RUN-ATTEMPT`.
Read the receipt, not just the workflow's green status. Visual/physical Safari
acceptance and production approval remain separate.

## Failure and continuation

Do not rerun an unknown or partially activated operation. Read the receipt and
private owner/backup state first; rollback cannot cross ownership. A fresh owner
command is required after the cause is resolved. Missing source/CI/hash/permission
is a blocker, not a reason to weaken a gate or add a one-shot launcher. Source UI
work and independent SITE/SEO preparation can continue without redoing a published
package. The earlier blocked generated-CSS proposal is not part of this publisher.

The product queue remains the fresh release `AUTOPILOT_STATE.json.current_task`
and its existing profile audit; this document is an operational contract, not a
second task queue. Offline checks: `python3 tests/search3_preview_publish_test.py`.

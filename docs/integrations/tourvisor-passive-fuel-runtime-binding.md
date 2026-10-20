# Passive fuel: ROOT gateway → existing INT runtime

20.10.2026. **Non-executable handoff; no installation authority or command.**
Execution remains in [INT #2506](https://github.com/pyatkoff/poisk-turov-test/issues/2506)
and coordination [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709).
This document records one concrete delivery boundary, not a second queue.

## Current checked boundary — 20.10.2026, 15:01 UTC

Gateway binding is corrected in [PR4745](https://github.com/pyatkoff/poisk-turov-test/pull/4745),
source65a802303afc3bedd6a8b674b9f5a9579ab4a38a/release266848221cc78b1299c675171d7fe53fbb7e0cce.
All five exact-source checks succeeded, including native39442829138/job118696895774
(8bootstrap/4include/2actual-action DBfailure cases); independent review accepted.
The corrected API is **source only, NOT INSTALLED**.

The distinct private-runtime metadata operation is also completed/consumed/no-replay:
command6293929408, run39444434407/job118702478080/attempt1SUCCESS, control196ffab0,
source26684822; [terminal6294014947](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6294014947).
Its carrier source PR4746/fa59ea27 passed native39443850191/job118700431508
(76core tests plus existing suites), Security and independent review; merged196ffab0.
Neither this nor the old ROOT operation may be renamed/refreshed/replayed.

| Actual private boundary | Checked result |
| --- | --- |
| Evidence/store/retained helpers |All three match the accepted hashes below, 0644 |
| Passive helper |13823B/0644, SHA256 `be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22`; byte-exact historical feature488fe40 helper |
| Only passive-helper delta needed |Remove the eager `require_once dirname(__DIR__, 2) . '/v2/data/db-v1.php';`; accepted13766B/da89 helper below already does this |
| Private config |84B/0600, SHA256 `52e05b625b63a611f34cb87b58bd0e554c024df1e277bede469416330812d5aa`; exact stock proxy, not missing/bad configuration |
| ROOT DB helper |3282B/0644, SHA256 `c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13`; exact canonical main blob4ac8258, four declarations, NOT_EXECUTED |
| Remaining metadata |Literal-only reader returned config_binding=unrecognized for the proxy; backing configuration/store identity, mode and ownership remain NOT_MEASURED |

All14 protected before/after records matched, including ROOT API01ac, DB and
NEXT/native apps. Provider/DB reads/DB writes/runtime writes/booking/lead0;
only the existing carrier reservation/staging/receipt was written. No PHP/config
execution, supplier request, observations/log read or application/config/store change.
Actual inventory completed **not_ready**, not installation or live fuel acceptance.

Artifact12275770285,1744B: ZIP SHA256
`8aa8bcd61dd463f1aeee58300e62a3a8892492904eb2c0147c9d0c4009070d26`;
sole result.json SHA256 `87c9874e8c8f0468676b1813fc8fafbe1f16d1f2ed79679e3d4165dfc55b0487`.
Downloaded ZIP/CRC/job JSON equality verified; independent runtime/static review
confirmed the counters, exact helper/DB/proxy provenance separately.

## Verified delivery gap

[PR4737](https://github.com/pyatkoff/poisk-turov-test/pull/4737) prepared the existing
passive engine on the current gateway, preserving TV diagnostics, limit5000,
LOCAL discovery, autosave and supplier responses. Source990eb585/releasee496e63b
passed five checks and independent review. It was **not installed**.

The first fixed read-only readiness operation is completed/consumed/no-replay:

- Command6293299811; run39440047127/job118687171134, attempt1 SUCCESS.
- Control568373792ec59b81391366113c244c0c3734f75d; source670245c83b8fd47429a51a5d079e5106125476f9.
- [Terminal6293384001](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6293384001).
- Artifact12274245020,1479B; ZIP SHA256 `7cf5035400a4931ef1752a92d9669caee4e6c72525e7b3da5deb82c6f961fa82`.
- Sole result.json SHA256 `7149641193bc91cad65bd28e502b396fde025e7a877994ae9ca487c15745365c`; downloaded ZIP/CRC/job JSON equality verified.

| Boundary | Actual result |
| --- | --- |
| ROOT api-v2.php |16366B, SHA256 `01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61`; expected installed predecessor matches |
| ROOT data/db-v1.php |3861B, SHA256 `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4`; integrity true, four declarations; PHP/DB not executed |
| Four fuel helpers in ROOT app/integrations |All absent |
| Same four names in the explicitly bound parent app layout |All absent |
| ROOT .andromeda-private.php |Absent; config binding missing, store not verified |
| Protected public files |All14 before/after records equal, including seven ROOT helpers, config, index, NEXT and native app |
| Application side effects |Supplier/DB reads/DB writes/runtime writes/booking/lead0; existing carrier created only its private reservation/staging/receipt |

Result: inventory completed, **readiness not_ready**. This is not an ANEX failure
cause, global helper absence, supplier recovery or installed fuel acceptance.
Installing only the prepared API would leave capture inactive. Do not create a
second ROOT config, helper copy or evidence store to conceal that gap.

## Existing binding and remaining preflight

The existing permanent carrier defines the INT runtime as
`project/_preview/search3-anex-candidate` and already refers to its
`.andromeda-private.php`. Those paths come from the existing executor, not a new
URL parameter, guessed filesystem fallback or new transport.

| Existing component | Candidate binding | Evidence still required |
| --- | --- | --- |
| ROOT passive bootstrap |PR4745 uses the existing runtime's app/integrations, same four classes/files |All four inspected; replace only the stale passive helper, reuse the three exact helpers |
| ROOT passive configuration |PR4745 uses that runtime's existing .andromeda-private.php |Proxy identity verified; backing config/store binding still requires exact scoped preflight |
| Evidence directory |The existing configured catalog parent/searches |Directory identity/ownership, no symlinks, same before/after snapshot; no listing or observation reads |
| DB helpers |Reuse the already loaded complete gateway helper set; partial → no capture |Keep PR4732/4737 include protections; do not require a second physical DB helper |

The absent root/parent-only binding from PR4737 is superseded in source by PR4745.
The ROOT inventory below remains historical proof, not a new operation. Private
helper/config metadata is now measured, but the backing store is not ready-accepted.

The exact84B proxy comes from the existing
`.github/workflows/int-andromeda-private-proxy-restore-v3.yml` literal:
`return require dirname(__DIR__,4).'/.anytoour-andromeda/search3-preview.php';`.
That source binds the account-owned private directory/config and catalog; do not
run its consumed restore operation or execute PHP to discover the path. Do not
rewrite this valid proxy or copy its backing credentials into ROOT.

Next narrowly reviewed **installer preflight**, not another generic reader:
verify this exact proxy hash; inspect only the source-owned account-relative
`.anytoour-andromeda/search3-preview.php` regular-file metadata and literal
top-level enabled/catalog_path binding to that same directory's catalog.json;
verify its existing searches directory identity/owner/write+search modes before
and after. No arbitrary include, directory fallback, catalog/observation listing,
PHP/config execution, DB/log/provider access, new directories or storage creation.
The current consumed reader deliberately cannot follow this proxy or leave its
project scope; do not weaken/reissue it. The additional fixed private closure
needs its own exact code/review/admission in the same carrier before any action.
Unsupported syntax, missing binding or drift remains not_ready; never invent a path.

The passive helper is shared with the existing private gateway. Before removing
its eager include, the installer package must also prove that gateway's DB-helper
ownership remains compatible: exact existing runtime api-v2.php identity and
bounded static passive-caller/include facts only, no execution or public output
of source/config/URLs. Do not assume the private gateway was upgraded with PR4732.
If its caller depends on the removed side effect or remains unproven, hold the
two-file activation; an extra API change needs a separately reviewed bounded
package, not an automatic third write or a broad feature installation.

The old DB pin ca7 mismatch is not corruption: canonical main4ac8258 is exactly
the observedc1c helper. Releaseca7 adds only a config fallback when ROOTconfig is
absent; observed ROOTconfig exists (0600/hash121839ce). Keep DB/config bytes
unchanged, explicitly pin this actual predecessor and prove compatibility in the
activation package. No blanket DB allowlist or unreviewed overwrite is authorized.

## Closed runtime-file package

The four helpers remain the existing engine, byte-for-byte from feature37df6227:

| File under app/integrations | SHA256 |
| --- | --- |
|tourvisor-operator-fuel-passive-intake.php|`da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d`|
|tourvisor-operator-fuel-retained-intake.php|`a086f4d109b8d5e521311fd23a4047b5bf548cc41469e9b64370d740518adc0b`|
|operator-fuel-rule-evidence.php|`cecd23217cc5e74b81f6c2ecd22db5813770edde3e861be888026393693ae1f2`|
|operator-fuel-rule-store.php|`774c5bc01e213c614644b269b111593967bd82dc2ea120a15175df846bcd386d`|

Historical PR4737 API19663B/SHA256 `b87733d4f604f404c25f8d127c6b5949f6e0d42dcb3120c0e3c02df1793aa468`
has the absent binding and must not be installed. Corrected PR4745 API20233B,
SHA256 `04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15`,
has its own exact source, five CI successes and review. Store preflight is still open.

The minimal candidate runtime delta is **two files only**: ROOT API01ac→04c plus the
existing private passive helper be4380→da89. Reuse all three identical helpers;
DB/config/proxy/store stay unchanged. Do not install the whole feature runtime, import mappings/LOCAL
profiles, alter arithmetic/ANEX/APD, create directories/config/storage, or copy
private credentials. A missing approved runtime directory/config is a separate
blocker, not implicit creation authority.

## Admission and acceptance

1. ROOT and private-helper inventories above are closed/no-replay. Prepare only
   the exact stock-proxy/backing-store preflight defined above inside the existing
   carrier/installer, with code/review/admission before execution. This document
   provides no executable command or new server permission.
2. Gateway binding correction and its narrow PHP CI/review are accepted in PR4745;
   do not redo them or add a UI/general QA cycle. Keep existing diagnostics/limit/
   LOCAL/autosave/response contracts and complete-DB-reuse/partial rejection.
3. After actual metadata acceptance, pin exact predecessors/candidate bytes for a
   two-file transaction on the existing installer/carrier. Update the stale passive
   dependency before API activation, retain backups and rollback, recheck unchanged ROOT
   helpers/config/NEXT/native/protected fingerprints and publish sanitized
   readback. Current-main/source/actor/owner/attempt, concurrency, reservation and
   no-replay guards remain. No command is defined or authorized here.
4. Installed bytes/config binding, natural own tour→flights capture, two distinct
   compatible observations, rule availability and LOCAL consumption are separate
   evidence stages. Do not manufacture a paid search merely to fill this report.

The earlier own FUN&SUN quote139078RUB/fuel43648 included is one consumed
offer-specific observation, not a reusable rule. Internal pending900s/evidence
86400s retention is not supplier price validity. Infant stays separate_unknown;
missing is not0 and an estimated price is not final_verified. BG fuel0 remains
owner_policy; ANEX/APD are not redone; SAMO/Andromeda supplier work stays paused.

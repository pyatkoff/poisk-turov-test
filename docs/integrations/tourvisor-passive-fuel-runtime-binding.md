# Passive fuel: ROOT gateway → existing INT runtime

20.10.2026. **Non-executable handoff; no installation authority or command.**
Execution remains in [INT #2506](https://github.com/pyatkoff/poisk-turov-test/issues/2506)
and coordination [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709).
This document records one concrete delivery boundary, not a second queue.

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

## Concrete binding to prepare

The existing permanent carrier defines the INT runtime as
`project/_preview/search3-anex-candidate` and already refers to its
`.andromeda-private.php`. Those paths come from the existing executor, not a new
URL parameter, guessed filesystem fallback or new transport.

| Existing component | Candidate binding | Evidence still required |
| --- | --- | --- |
| ROOT passive bootstrap |The existing runtime's app/integrations, using the same four classes/files |Actual hashes/modes/regular-file and complete-dependency checks at these exact previously uninspected paths |
| ROOT passive configuration |That runtime's existing .andromeda-private.php |Safe local config identity and unambiguous catalog_path binding; publish only hash/booleans |
| Evidence directory |The existing configured catalog parent/searches |Directory identity/ownership, no symlinks, same before/after snapshot; no listing or observation reads |
| DB helpers |Reuse the already loaded complete gateway helper set; partial → no capture |Keep PR4732/4737 include protections; do not require a second physical DB helper |

The current source's root/parent-only bootstrap and ROOT-only config lookup need
a narrowly reviewed correction before activation. This document does not make
that code change or claim the private runtime is ready. Its four helper versions,
config and store binding remain **NOT_MEASURED**.

## Closed runtime-file package

The four helpers remain the existing engine, byte-for-byte from feature37df6227:

| File under app/integrations | SHA256 |
| --- | --- |
|tourvisor-operator-fuel-passive-intake.php|`da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d`|
|tourvisor-operator-fuel-retained-intake.php|`a086f4d109b8d5e521311fd23a4047b5bf548cc41469e9b64370d740518adc0b`|
|operator-fuel-rule-evidence.php|`cecd23217cc5e74b81f6c2ecd22db5813770edde3e861be888026393693ae1f2`|
|operator-fuel-rule-store.php|`774c5bc01e213c614644b269b111593967bd82dc2ea120a15175df846bcd386d`|

Prepared API19663B/SHA256 `b87733d4f604f404c25f8d127c6b5949f6e0d42dcb3120c0e3c02df1793aa468`
still has the absent binding. It is **not** an activation candidate until the
binding correction has its own exact source, CI and review.

The maximum runtime delta is the corrected ROOT API plus only necessary files
from this four-helper closure in the existing INT runtime. Reuse identical
helpers. Do not install the whole feature runtime, import mappings/LOCAL
profiles, alter arithmetic/ANEX/APD, create directories/config/storage, or copy
private credentials. A missing approved runtime directory/config is a separate
blocker, not implicit creation authority.

## Admission and acceptance

1. Prepare one narrow, separately reviewed metadata scope inside the same
   permanent carrier for the exact existing runtime's four helpers/config/store.
   This is distinct from the consumed ROOT-layout inventory above. Do not replay
   its operation, issue another command for it, or widen it into a generic reader.
   No PHP/config execution, DB/client/supplier/log/observation access. Unsupported
   config syntax, ambiguous binding, path escape or drift yields not_ready.
2. Correct only the current gateway bootstrap/config binding; retain every
   existing diagnostics/limit/LOCAL/autosave/response contract. Exercise the actual
   configured runtime layout, absent/partial dependencies, duplicate DB globals
   and persistence failures with the existing narrow PHP tests. Exact required
   CI and independent review precede merge; no UI/general QA cycle is added.
3. After actual metadata acceptance, pin exact predecessors/candidate bytes for a
   bounded transaction on the existing installer/carrier. Complete dependencies
   before API activation, retain backups and rollback, recheck unchanged ROOT
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

# LOCAL #4191: independent mass census and retained-content planning

This is the narrowly requested extension after #4191/comment5932858291. Owner
continuation: «Давай дальше». Coordination #4217, claim5941276295. It extends the
existing permanent LOCAL **read-only** registration; it is not an alternative
executor, another workflow, a supplier collector, or a profile writer.

## Exact operation and limits

- Existing mode: `local-profile-plan-4191`.
- New operation: `int-andromeda-local-profile-mass-plan-4191-20261002-v1`.
- New batch: `local4191-mass-retained-20261002`.
- The pair is immutable. Caller-supplied IDs/limits, arbitrary suffixes, old/new
  cross-pairs and apply flags are rejected.
- Catalogue census: all active canonical profiles in one repeatable-read,
  read-only snapshot, at most 30,000 profiles. A larger catalogue fails closed
  and is NOT reported complete. SQL profile reads use keyset pages of 250.
- Source planning: at most 2,000 independent eligible profiles in demand order,
  through the existing `AnyTourProfileEnrichmentV1::plan`, in scopes <=250.
  This is at most eight owner batches, not 2,000 writes. Remaining candidates
  are `PLAN_BOUND_DEFERRED`; their existence remains visible in the receipt.

## Protection and provenance

The historical 366-ID roster is read solely as an exclusion. Both its own IDs
and CURRENT accepted local IDs are excluded before any owner source plan. An
unverifiable historical alias holds the new work instead of guessing identity.
The original sealed HC-1 D1 manifest is read by the existing helper; both D1 own
and legacy identities are excluded. Unknown D1/history manifests permit no
source plans. The old planner file, completed36/320 registrations, D1 intake,
source acquisition, apply routines and denied own-alias actions are unchanged.

Only an untouched revision-one profile with an intact existing accepted alias
and no prior content-operation provenance can become a candidate. Already
edited/prior-operation profiles remain held for separate reviewed work. Missing
fields alone enter the exact scope: nonempty existing fields cannot be included
in a patch by this planner. The existing owner independently proves full import
origin, retained full-card provenance/freshness and current profile/alias hashes.
No names are used to infer identity. Room/meal **descriptive** fields retain the
existing owner contract; no offer dictionary, trait, matching, schema, price,
fuel, lead, Metrika, website or preview change is introduced.

Every returned owner plan is verified against its exact scope and stable hash;
selected before-image/revision/profile/alias hashes are compared to the census.
Unknown/failed/drifting batches are held without retry. A successful owner plan
is stored intact in a separate private `mass-batch-NNN.json` with a digest.
The private index binds all batch digests. Files are exclusive-create, mode0600,
flushed/fsynced by the existing helper. A terminal or UNKNOWN operation is not
renamed, resumed or replayed.

## Honest counters and next stage

`core_fields_present` means a presence screen of description/primary/gallery
on integrity-checked, alias-validated profiles. It is NOT a fully verified hotel
card, photo download, source match, or catalogue completeness score. Missing
field counts apply to that screened subset, not to held unreadable profiles.
Optional fields can legitimately be absent. `NO_DELTA_NOT_PROVEN_COMPLETE`
never means complete. `SOURCE_MISSING`, `SOURCE_PROVENANCE_HELD` and structural
holds remain distinct. Raw profiles, aliases, descriptions, URLs and source
material stay private; public output contains only counts, pins and digests.

`safe_to_apply=false`, supplier HTTP0, profile/DB/mapping/schema writes0 on every
successful receipt. There is deliberately no mass-apply registration here.
After checked/merged current code and exact operation admission, the read-only
receipt establishes the real next cohort. A subsequent separately reviewed
existing-writer extension must bind the selected cohort and private owner-plan
hash, recheck CURRENT revisions/provenance/D1/history and read back every actual
commit. Preparing this package or its plans is not catalogue filling.

## Verification

Focused tests run inside the existing permanent-executor contract job; no new
workflow/event/transport/permission/secret/concurrency mechanism is introduced.
Tests cover exact admission, zero authority, private bundles/seals, 501 profiles
as250+250+1, 2,001 candidates with an explicit2,000 cap and demand priority,
D1/history exclusions before owner invocation, unknown manifests, no-delta vs
missing/provenance, nonempty/editorial protection and plan/revision/scope drift.
Existing executor, transport and fixed-plan/apply/acquire tests remain in place.
Offline callback/PDO tests and CI are not production DB evidence. No operational
command is implied by this document. No auto-schedule or supplier acquisition.


## Exact retained71 write admission after the CURRENT receipt

Planning run36932599624 is terminal/no-replay:15999 active cards; first2000
retained source plans produced71 selected profiles,735 field changes and4 private
batches. Private index SHA256:
`0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426`.
Source5e6797373c61f5b1ad4cb365a18cf15d66526b99;
control58af4702bbee0ec584812ce5bf643f60b59df96a. Claim #4217/comment5941607126.

The existing `local-profile-apply-4191` registration receives one immutable pair:
`int-andromeda-local-profile-mass-apply71-4191-20261002-v1` /
`local4191-mass-retained71-20261002`. It is NOT a generic mass writer. There are
no caller IDs, counts, digest overrides or supplier permissions. The historical
36/320 operations and original PHP implementations remain untouched/no-replay.

The new runner checks the exact producer/index/batch digests, CURRENT accepted
historical aliases and original sealed D1 identities, all71 before-images,
revision1, unique targets and missing-only fields. All4 complete owner scopes
are replanned and their exact hashes checked BEFORE any write. A current release
may differ from the plan's release due to unrelated changes: both SHAs remain
reported, and the owner must reproduce the identical sealed plan. This does not
waive current-source admission or permit source-data drift.

Only the existing canonical owner performs database writes: at most71 profile
updates plus71 provenance records,735 fields, in the4 independently bounded
transactions. Each committed batch is read back by that owner and checkpointed
privately. The input is exclusive-consumed immediately before the first apply;
there is no retry/resume/rename, including on partial or UNKNOWN outcomes.
Known verified counts are preserved separately; a later uncertain commit is
reported as UNKNOWN, never as zero writes or an all-batch rollback. An input
hold before any apply reports zero writes. The old planner receipt deliberately
remains `safe_to_apply=false`; only this separately bounded registration admits
its exact reviewed71 cohort, not the1920 source-missing profiles or11142 deferred
candidates. Supplier/lead/booking/mapping/schema/public-site changes remain0.

Focused tests add exact71 admission, missing-only protection, private integrity,
all-preflights-before-write, current drift, consumed input, D1/history exclusion,
invalid readback, partial/UNKNOWN and checkpoint-failure coverage inside the same
existing workflow. Offline tests are not a live write receipt. Completion is only
claimed after the operation's committed_verified receipt and artifact readback.

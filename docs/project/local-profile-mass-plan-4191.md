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

# Shared hotel prices for feed and messenger-post consumers

The existing `collect-anytour-tours.yml` monitor remains the only range-search
producer. Its cadence, budget, unfinished-search reuse, 12-month defaults and
reviewed LOCAL hook are unchanged. This change adds a DB-only adapter, no collector.

`GET /data/hotel-monitor-prices-read-v1.php?tourvisorHotelIds[]=212&departureId=1&dateFrom=2026-11-12&dateTo=2027-10-31`

The four explicit fields are required; 1–100 unique Tourvisor IDs, one departure,
future dates within 18 calendar months. Scope is the monitor's 5–14 nights, two
adults, no children, RUB. It reads only `scheduled_monitor` observations within
24 database-relative hours, returning up to three cheapest retained products per
hotel. Each latest exact room/meal/operator/tour segment participates in ranking;
no catalogue join or ANEX/legacy/name-based link is invented. Names come from the
consumer's already accepted identity, not numeric equality in another table.

Each candidate retains observation/search/tour/hotel/operator IDs, conditions,
raw observation timestamp, age at export, listing price and separate nullable
fuel charge. `recordId` identifies those source facts; the same record must be
used by the feed and corresponding post draft. Do not add fuel to this amount or
reuse the old SEO snapshot as a complete minimum: neither proves a final package.
Stored naive observation timezone is explicitly unasserted. Export UTC time is
not a new price check; source-age semantics remain database-relative.

This endpoint does **not** prove a current final total, flights, mandatory extras,
availability or global market minimum. All such booleans remain false/unknown.
Under Pavel's later explicit approval, the existing publisher uses the cheapest
retained monitor observation directly, with its exact party, date, nights, room,
meal and observation time. A second quote is not required. The displayed amount
is a found "from" price; fuel is not added separately and availability is not asserted.

Coverage counts completed requested date windows from latest attempts for each
stable monthly key; success and empty are distinct from failure/timeout/unknown.
It is **not** full supplier-rate coverage, returned by `marketCoverageComplete=false`.
Missing fresh observations mean unknown, not no hotel/no tours. Incomplete or
truncated coverage cannot be promoted to a complete monthly/daily sweep.

Consumer snapshots are separate from accepted/published content. Read/update by
exact existing Tourvisor binding; never create hotels or remap IDs. Retain old
snapshots, original content, accepted versions, receipts, holds and queue. HTTP
failure must leave stored data intact and make expiry visible, not extend freshness.

Validation: `php tests/hotel-monitor-prices-smoke.php`. Existing collector workflow
validates these files on PR and copies them only in its existing production job
after an authorised merge. PR execution cannot run the production job. No workflow
dispatch, server execution, MAIN/DEPLOY reservation or merge is part of source prep.

## Compatible execution within the existing budget

The retained 10 November run42922035872 planned2,958 weekly logical targets,
with64requests per pass. Many publication cohorts contain only1–4hotels. Their
immutable keys preserve history but need not each consume a separate search.
Even24nominal passes cannot refresh2,958separate searches within24hours. Actual
scheduled-run gaps are an additional limitation; cron configuration is not proof
of daily execution or completed coverage.

`v2_top500_execution_targets` packs due hotel cohorts with identical departure,
country, dates and search conditions into the existing maximum30hotel request.
It changes execution only: every original target key, hotel membership and
attempt remains separate. Already fresh/deferred targets stay excluded by the
original queue. Resumed searches can share work only with the same retained
search ID and scope; they never join a new search or another known search.
Seasonal page searches keep their existing reserve inside the same request cap.

Each original attempt records the actual shared search ID and its own filtered
results. A sibling finding cannot make an empty hotel cohort successful. Partial
and failed searches remain incomplete. Disjoint results reach the unchanged
observer once, with the total50,000tour envelope preserved. A completion-write
failure stops execution without retrying an uncertain update. Original logical
coverage counts are retained; logs separately report physical requests and
logical targets processed. No cadence, budget, hotel whitelist, namespace,
supplier API contract, price/fuel policy, schema, publisher or schedule changes.

The native monthly smoke executes the actual collector loop against deterministic
fake storage/provider boundaries and checks coalescing, exact attribution,
resumption, request/observer caps, partial/error/empty states and uncertain writes.
This test is not proof that production has completed its daily sweep. Actual
runtime logs/coverage and final package-price proof remain separate requirements.


## Accepted/published Site roster, 11 November 2026

The fresh owner-private Site12 export (registry217) contains605 unique retained
Tourvisor bindings for509 already published and96 accepted/queued hotel pairs.
The last original monitor run reported579 searchable source hotels; these totals
alone do not identify missing hotels or prove a current MATCH projection.

The collector now loads the union of its historical inputs and this fixed605-ID
consumer roster, then appends only IDs not already assigned to TOP500 or a prior
publication cohort. Each candidate still requires an active factual catalogue
row and departure route. Names, countries and numeric equality across namespaces
are not used to create a binding. No accepted post, price snapshot, hold or Site
record is changed. This is an append-only roster snapshot, not recurring Site sync.

The historical182-ID exclusion from the TOP500 base stays unchanged. Putting the
new roster into that exclusion would move hotels out of existing batches and
invalidate their target keys. Only catalogue loading includes the new union;
legacy base/cohort target membership and unfinished search IDs remain intact.
New inputs use the installed compatible-request packing,30-hotel cap and64-request
pass budget. No new workflow, cadence, provider request type or quote is introduced.
The next original scheduled run must report the exact new roster cohort's
requested/searchable counts; source tests do not prove production coverage.

Source projection SHA256 (UUID, typed TV ID, status, queued, sorted by TV ID):
`83e81812a15ce16a1a792ab58c2d58d5c5a7de23928cee98344be8646cc3746a`.
Sorted comma-separated Tourvisor ID SHA256:
`436b553ce4c9a7776d771ffbdfd2097e5ed37d7274cc569b78b8719750aa02c9`.

## Existing collector completion callback, 11 November 2026

After a successful original scheduled pass, the same SSH session invokes the
reviewed callback from `pyatkoff/max-search-bot` at the immutable SHA pinned in
the workflow. Its existing editor changes both existing channel messages, then
its existing feed writer patches the three existing XML files from those actual
post receipts. No new task, cron, worker, publication, supplier request, quote,
Site mutation or credential is introduced. Manual runs and workflow retries do
not invoke this callback. Collection cadence, request caps and LOCAL pins remain unchanged.

The initial callback roster contained 481 pairs, including the original six. A read-only
runtime pass on 11 November verified all 481 original post receipts, typed native
IDs, retained buttons/photos and feed destinations. The remaining 28 of 509
published hotels still need unambiguous original receipt/template bindings; they
are not treated as missing from the hotel database. The existing monitor search
roster and physical request budget are unchanged.

Since the owner correction of 13 November, each new successful original scheduled
collector admits maintenance at any Moscow hour, including a later collector on
the same day. The existing `--nightly` argument and `nightly_cycle` journal field
remain compatible names; neither the hour nor the calendar date limits admission.
Repeated collector IDs are rejected using the same append-only journal, including
an older ID after a later run. An unfinished cycle or pending/unknown operation
still blocks a new pass regardless of its date; the collection clock cannot move
backwards. No separate replay registry is introduced.

One original interrupted cycle (`43336135923`) has an explicit forward-only
metadata closeout: its accepted recovery `43352649982` already completed the
original post/feed operation but deliberately retained the cycle marker. Under
the same lock, the callback must match both complete original journal hashes,
byte/line counts, the completed child receipt and the exact global completion.
Only then it appends `interrupted_reconciled` with the saved recovery binding.
It never labels the partial sweep completed, replays the recovery or edits posts
for that closeout. Changed evidence and unrelated unfinished cycles stay blocked.
This is forward-only: a source predating this terminal safely stops on it; any
rollback must retain the terminal reader instead of re-enabling the old gate.

The scheduled callback can process the full pinned roster within the existing
1,800-second budget, reserving the final 300 seconds to finish the current pair.
It prioritizes never-updated pairs, then the oldest completed pairs. The existing
non-scheduled CLI limit remains 30 pairs / 480 seconds. All paths use retained
price observations only; the callback does not collect or quote prices again.
An exact tour with a later saved operator rejection or room conflict stays held
until a newer observation; other hotels continue. Empty or stale observations
remain unpriced. These bounds do not establish daily coverage of every hotel and
date range: actual collector coverage and callback receipts remain authoritative.

Completed record IDs are skipped. A subsequent automated caption is trusted only
when the actual completed journal matches current publication/version/text/URL;
manual drift is held. Unknown post outcomes stop, and unknown feed outcomes are
read back by exact hashes without replay. Runtime descendants are allowed only
with all22 reviewed publishing surface blobs unchanged and a clean checkout.
Private journals retain each stage before the next mutation. Public feed hashes
are checked before marking completion. The Site's older publication snapshots
are preserved and are not falsely reported as current channel versions.

Source tests execute the real workflow shell with inert boundaries for scheduled
success, supplier failure, manual invocation, rerun and callback failure. Runtime
inspection verifies all 481 bindings without edits. A merged callback is enabled
for the next natural pass; only its actual receipt proves automatic execution.


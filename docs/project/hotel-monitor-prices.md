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
The existing authorised final-quote/publisher boundary may consume these retained
tour candidates without a second date-range search. No new quote operation is
introduced or executed by this change. A consumer cannot release a sales price
until that boundary supplies a current final-price proof for the same conditions.

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

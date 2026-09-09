# Search3: size, performance and sales-readiness plan

Updated: 2026-09-07

## Outcome

Finish the structural reduction without deleting active owners, then spend the
saved complexity budget on a faster and clearer path from search to a qualified
lead. Production remains locked until the owner approves a concrete visual
release.

## Phase 1 — finish structural reduction

1. Retire whole unreachable owners and markup families first.
2. Consolidate duplicated behavior only when one loaded canonical owner already
   exists.
3. Keep the eight public Search3 paths stable and rebuild generated assets from
   `src/search3`.
4. Stop byte-only work when the next removal would require rebuilding active
   result, detail, review or lead geometry. Those owners move to the product
   phase instead of being deleted blindly.

Exit criteria:

- no second desktop or mobile filter implementation;
- no live CSS for retired runtime markup;
- no standalone behavior owner that repeats a current lifecycle owner;
- Security and exact artifact CI green on the checked release;
- Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping,
  Metrika/goals and the canonical logo unchanged.

## Phase 2 — conversion-critical search journey

Priority is based on lost-search or lost-lead risk, not visual novelty.

1. Touch and reachability: every primary mobile action is at least 44 px; fixed
   actions respect safe areas and never cover required content.
2. Search clarity: destination, dates, nights and tourists remain obvious,
   reversible and preserved when the user edits a result search.
3. Result decisions: price scope, party, dates, meals, flight status and hotel
   facts are readable without duplicated or fabricated supplier data.
4. Filters: instant local filters expose only complete loaded facets, have a
   recoverable zero-result state and never start a Tourvisor request per click.
5. Selection and review: one clear next action per stage, truthful price context,
   visible fallback/retry paths and no premature lead submission.
6. Lead entry: concise contact form, visible consent/trust context, stable error,
   sending and success states; transport and field mapping remain unchanged.

## Phase 3 — sales-ready site

1. Apply one coherent DS2 hierarchy to search, destination, hot-tour, contacts
   and informational routes.
2. Keep trust claims factual and remove internal or placeholder copy.
3. Repair only legal/payment links backed by approved existing content.
4. Improve performance with measured asset, render and layout-shift budgets.
5. Preserve indexable server-rendered routes, internal links and scalable
   destination/hotel architecture for the future SEO expansion.
6. Use existing analytics evidence to assess search completion, result-to-tour,
   tour-to-review and review-to-lead progression; do not change Metrika goals
   without explicit approval.

## Release evidence

Each product batch needs the narrow behavior regression plus the relevant
responsive browser states. Accumulate checked batches in the release branch;
publish one justified isolated preview instead of a deploy per micro-change.
Before production migration: owner visual approval, physical Safari/iPhone pass,
legacy-search rollback, protected production fingerprints and post-release search
and lead-path verification.

## Completed product batches

1. Fixed the collapsed mobile selected-tour CTA with a 48 px minimum target.
2. Made the mobile search form readable and touch-safe: 12–16 px text, 48 px
   primary controls and 44 px quick filters, protected by Chromium geometry at
   375/760/761 px.
3. Added immediate accessible desktop-filter feedback and a clear recoverable
   zero-results state in the single DS2 owner, without network requests.
4. Removed the unimplemented “Ближе к морю” sort and the map action with no
   consumer; retained price/rating/stars sorting and list/grid views.
5. Consolidated the hotel card to one count-aware primary CTA while preserving
   expanded collapse behavior and each concrete tour-selection action.
6. Removed the ineffective Search3 list/grid switch while preserving both
   working view controls on the maintained legacy search route.

## Next product batch

Build the lean Search3 base-bundle boundary. Classify every shared-manifest
module as Search3-required or legacy-only, retain the complete bundle on
`/poisk-turov-old/`, and switch Search3 only after measured loaded raw/gzip
savings plus source-closure and browser evidence.

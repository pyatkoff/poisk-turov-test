# Search3 visual review loop

Status: execution contract for preview-only Search3 work. This document does
not authorize merge, production deployment or changes to protected contracts.

## Operating decision

Use the built-in browser for fast human design judgment and GitHub CI for
deterministic evidence. Neither replaces the other:

| Layer | Trigger | Purpose | Output |
| --- | --- | --- | --- |
| PR FAST | every relevant PR | syntax, route isolation, protected contracts, reference integrity | logs, immediate fail/pass |
| BUILT-IN/LOCAL BROWSER | each UI edit | inspect only the changed slice at mobile and desktop | immediate human judgment, no retained archive |
| LANE SMOKE | manual exact-SHA run after a lane is complete | lifecycle at 375/1440 plus focused presentation interactions | 12 PNG files, manifest, review index |
| CANDIDATE EVIDENCE | one manual exact-SHA run after integration | full five-width lifecycle and regression evidence before preview publication | 21 PNG files, manifest, review index, release artifact |
| LIVE BUILT-IN BROWSER | after an approved exact-SHA isolated publication | subjective parity, real content, interaction quality | owner review decision and prioritized gaps |

The `smoke` tier captures complete lifecycle states at 375 and 1440 plus the
six focused presentation interactions. It does not run the responsive-boundary,
race or seven failure-state suites.

The candidate tier captures initial, progressive-25 and final-100 at all five
canonical widths, plus the same six interaction captures. It also runs the
responsive-boundary, race and seven failure-state suites. It is the retained
evidence set used to bind a reviewed integration SHA to a later manual preview.
Only a successful `candidate` dispatch builds the release artifact; a `smoke`
dispatch cannot do so.

## Fast iteration protocol

1. A worker changes one owned UI slice without changing Tourvisor, pricing,
   lead delivery/payload, Metrika or production routes.
2. After each edit, the built-in or local browser checks only that changed
   slice at 375 and 1440. Add an edge width only when the slice needs it.
3. Local/static checks run first. Pull requests run only the FAST job; neither
   PR updates nor `main` pushes start Chromium or upload visual evidence.
4. When the lane is complete, manually dispatch the default `smoke` tier for
   its exact SHA and inspect the generated `review.html`.
5. After the completed lanes are integrated, that integration head receives
   one manual `candidate` evidence run.
6. Only that exact evidence-bound SHA may be considered for an isolated
   preview publication.
7. The built-in browser then checks the live candidate at 375, 430 and 1440,
   with 768/1024 and layout-edge widths when a gap is suspected.

Browser review covers visual hierarchy, density, actual text and images,
sticky behavior, dialogs, focus movement and comparison against the eight
approved reference layouts. Automated geometry and contract assertions remain
authoritative for overflow, touch sizes, exact state counts and isolation.

## Public preview security boundary

Do not automatically deploy arbitrary PR PHP or JavaScript to the production
`anytoour.ru` origin. `noindex`, a hidden route, Metrika `0` and a disabled
configured lead endpoint are functional safeguards, not a hostile-code
sandbox. Same-origin PR code could otherwise access production APIs, cookies
or server-readable configuration.

Until a separate preview origin/container exists, public browser review uses
only a manually approved, same-repository, exact-SHA integration candidate
that has passed retained evidence and protected-contract checks. Fork PRs and
unreviewed worker heads receive CI artifacts only.

A future per-PR interactive host must provide all of the following before use:

- separate origin with no production-domain cookies;
- no production configuration or lead credentials;
- mocked/read-only API and denied egress to Tourvisor, lead and analytics;
- protected manual deployment credentials, immutable SHA routes and TTL
  cleanup;
- strict archive/path/symlink validation and atomic activation;
- noindex/no-store/CSP headers and adversarial isolation tests.

## Evidence identity

`manifest.json` is the machine-readable source. It records schema version,
visual tier, source and tested SHAs, workflow run identity, browser environment,
candidate asset hashes, screenshot hashes, geometry, interactions and behavior
states. `review.html` and `review.md` are derived indexes and must fail if a PNG
is missing, renamed through path traversal or has the wrong digest.

The archive digest belongs in a separate post-upload binding record because an
archive cannot truthfully contain its own final digest.

## Definition of done

A UI branch is `DONE / awaiting integration` when FAST and its manually
dispatched smoke are green and the review index has been inspected. It becomes
`DONE / awaiting visual approval` only after integration, one full candidate
run and owner comparison. It is not a production release until a separately
authorized exact-SHA release passes the production gates and rollback
requirements.

# Search3 visual review loop

Status: execution contract for preview-only Search3 work. This document does
not authorize merge, production deployment or changes to protected contracts.

## Operating decision

Use the built-in browser for fast human design judgment and GitHub CI for
deterministic evidence. Neither replaces the other:

| Layer | Trigger | Purpose | Output |
| --- | --- | --- | --- |
| PR FAST | every relevant PR | syntax, route isolation, protected contracts, reference integrity | logs, immediate fail/pass |
| PR BROWSER | every relevant PR after FAST | responsive behavior with deterministic fixture data | 12 PNG files, manifest, review index |
| CANDIDATE EVIDENCE | manual exact-SHA run | full five-width lifecycle evidence before preview publication | 18 PNG files, manifest, review index |
| BUILT-IN BROWSER | after an approved exact-SHA isolated publication | subjective parity, real content, interaction quality | owner review decision and prioritized gaps |

The PR tier captures complete lifecycle states at 375 and 1440, final results
at 430, 768 and 1024, and the desktop disclosure plus both mobile filter
interactions. It still runs the race and seven failure-state assertions.

The candidate tier captures initial, progressive-25 and final-100 at all five
canonical widths, plus the same three interaction captures. It is the retained
evidence set used to bind a reviewed source SHA to a later manual preview.

## Fast iteration protocol

1. A worker changes one owned UI slice without changing Tourvisor, pricing,
   lead delivery/payload, Metrika or production routes.
2. Local/static checks run first.
3. Draft PR CI produces deterministic fixture evidence at the `pr` tier.
4. The generated `review.html` is inspected before another full CI cycle.
5. Only an integration head selected for owner review receives a manual
   `candidate` evidence run.
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

A UI branch is `DONE / awaiting visual approval` when FAST and PR BROWSER are
green and the review index has been inspected. It becomes a candidate only
after integration, full candidate evidence and owner comparison. It is not a
production release until a separately authorized exact-SHA release passes the
production gates and rollback requirements.

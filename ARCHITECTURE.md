# AnyTour Web Architecture

Status: canonical architecture source of truth for `pyatkoff/poisk-turov-test`.

This document describes how the product is intended to be structured. Operational progress belongs in `AUTOPILOT.md` / `AUTOPILOT_STATE.json`; product roadmap belongs in `PRODUCT_ROADMAP.md`; test ownership belongs in `TEST_MATRIX.md`. When those documents disagree with this file about architecture, this file wins unless a deliberate architecture change updates it in the same PR.

## Core rule: one concept → one implementation

A product concept must have one canonical implementation whenever practical. Do not create a second header, footer, navigation system, design system, search lifecycle, results renderer, lead flow, analytics contract or Tourvisor adapter merely because a new page needs similar behavior.

When historical generations coexist, classify them as `ACTIVE`, `COMPATIBILITY`, `DEPRECATED` or `DEAD-CANDIDATE`; migrate consumers toward the canonical implementation before removal. Never delete compatibility/contract code based only on naming or age.

### Canonical implementation registry

Every architectural concept must resolve to exactly one of these states during the refactor pass:

| State | Meaning | Allowed action |
| --- | --- | --- |
| `CANONICAL` | implementation new work must extend | add consumers here; do not fork |
| `COMPATIBILITY` | still required by a proven legacy route/contract | preserve until consumers are migrated and replacement evidence exists |
| `DEPRECATED` | superseded and intentionally not used for new work | migrate remaining proven consumers; no feature additions |
| `DEAD-CANDIDATE` | no required consumer has been proven | do not delete until repository/runtime/CI/deploy evidence confirms absence of required use |

For each concept under consolidation, `DEPENDENCY_MAP.md` or the relevant inventory evidence must identify: canonical implementation, known consumers, compatibility consumers, protected contract boundary, and tests/CI that own the behavior. Two `CANONICAL` implementations for the same concept are an architecture defect unless this document explicitly records a temporary seam and migration owner.

A file name, version suffix, age, or absence from the browser bundle is not sufficient evidence that an implementation is deprecated or dead. Removal requires dependency evidence plus equivalent behavioral coverage where behavior is protected.

## Public product model

The public product is one AnyTour website on the canonical `anytoour.ru` domain.

- `/` is the public homepage and discovery entry point.
- `/poisk-turov/` is the transactional tour-search application.
- `/country/`, country pages, `/hot/`, `/early-booking/`, `/rb/`, `/contacts/`, `/how-to-buy/` and similar routes are public content/discovery surfaces.
- legacy `/poisk-turov-test/v2/` is compatibility-only and must not become a second product architecture.

SEO/discovery pages may prepare search parameters and hand users into `/poisk-turov/`, but they must not duplicate Tourvisor/search/filter/results/lead business logic.

## Logical ownership zones

The repository is still historically flat in `v2/`. Refactoring must be incremental and behavior-preserving toward these ownership zones:

```text
app/
  shared/         header, footer, navigation, UI primitives, design system
  search/         form, catalogs, lifecycle, recovery
  results/        renderer, sorting, filters, comparison
  tour/           hotel, rooms, flights, selected tour
  checkout/       lead UX and local integration boundary
  integrations/   Tourvisor and other external adapters
site/
  templates/      base, content, SEO landing templates
  pages/          homepage and public content routes
  seo/            registry, sitemap, schema, internal links
tests/
  contracts/      protected contracts and lifecycle invariants
  e2e/            browser flows
  visual/         responsive/visual regression
  production/     post-deploy/live verification
scripts/
  build/          deterministic asset/release build
  ci/             reusable CI helpers
  diagnostics/    local/production diagnostics
```

This is a target ownership map, not permission for a mass file move. Move modules only in small PRs after dependency mapping and relevant regression coverage.

## Shared shell

Canonical public shell target:

`base template → shared header/navigation → page content slot → shared footer → consultant/integrations`

The reusable `.at-global-header` / `site-header-v2` family is the canonical shared-header direction for public standalone pages. `/poisk-turov/` currently remains on legacy `.at-site-header`; that is an explicit temporary seam, not permission to build further variants.

Footer, navigation, mobile navigation, containers, typography, buttons, cards, forms, breadcrumbs and spacing tokens should converge on one shared implementation/design system.

## Search application boundaries

The search application owns transactional search behavior only:

- search form/defaults/catalog synchronization;
- Tourvisor search lifecycle and recovery;
- results rendering/filtering/sorting/comparison;
- hotel/room/flight/selected-tour presentation;
- lead UI invocation around the protected lead contract.

Do not fork these behaviors into SEO or content pages.

The mature search flow is protected. Structural extraction/de-minification is allowed only when behavior can be proven unchanged with focused and broader regression evidence.

## External contracts — protected

Without explicit user approval, architecture work must preserve:

- Yandex Metrika configuration and goals/events;
- analytics external contract;
- external lead-sending contract and field mapping;
- Tourvisor external contract;
- neighboring project boundaries;
- server/platform architecture outside this repository's allowed deploy scope.

Tests and diagnostics may inspect these contracts; refactoring may wrap them internally only if observable behavior and external interfaces remain unchanged.

## Asset architecture

Current `v2` assets are manifest-driven but heavily order-dependent and historically layered. The migration direction is:

1. keep source modules small and explicit;
2. allow controlled subdirectories rather than forcing a flat `v2/` namespace;
3. map each active asset to an owning zone;
4. eliminate duplicate generations only after usage/dependency proof;
5. eventually produce deterministic prebuilt release bundles rather than relying indefinitely on runtime concatenation/order as an implicit API.

Do not introduce another `*-vN` layer when an existing canonical module can be safely extended.

## SEO architecture

SEO pages are acquisition/discovery surfaces. Search remains the transactional surface.

A future canonical publication registry should own which routes are indexable and drive canonical URLs, sitemap membership, internal-link eligibility and structured-data eligibility. Arbitrary search/filter query combinations must not become uncontrolled indexable faceted pages.

`robots.txt`, sitemap and canonical behavior must agree before broad SEO publication is enabled.

## Deployment boundary

GitHub `main` is the source of truth. Deploy workflows release the allowed AnyTour scope and then verify production behavior.

Current deployment still contains historical coupling to legacy `anytour.online` resources/lead bridge. Treat that coupling as a migration dependency, not desired architecture. Decouple incrementally, starting only with proven-safe dependencies; protected lead bridge behavior requires explicit high-risk review before architectural migration.

## Refactor policy

Refactor in narrow slices:

1. map current consumers/dependencies;
2. declare the canonical owner/implementation;
3. add or confirm behavioral coverage;
4. migrate one consumer/group at a time;
5. run focused checks, then broader relevant regression;
6. remove legacy code only when no required consumer/compatibility path remains.

Do not perform a repository-wide rename/move/rewrite solely to match this target tree.

## Source-of-truth ownership

- `ARCHITECTURE.md` — architecture, boundaries, canonical implementations and target ownership.
- `TEST_MATRIX.md` — CI/test tiers, protected behavior and coverage ownership.
- `AGENTS.md` — autonomy, risk and execution rules.
- `AUTOPILOT.md` — current operational phase and human-readable resume context.
- `AUTOPILOT_STATE.json` — machine-readable current state.
- `PRODUCT_ROADMAP.md` — product/brand roadmap.
- historical audit/migration docs — evidence/history only; they must not override current architecture.

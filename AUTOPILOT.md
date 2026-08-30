# poisk-turov-test — Autopilot State

Updated: 2026-08-30 10:12 +02:00

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point, `ARCHITECTURE.md` is the canonical architecture source of truth, `TEST_MATRIX.md` owns CI/test policy, and `PRODUCT_ROADMAP.md` owns product/brand roadmap.

## Current phase — DESIGN SYSTEM 1.0 / SITE-WIDE UNIFICATION

Design System 1.0 and whole-site visual coherence are now priority #1. The technical refactor pass remains useful, but it no longer outranks visible product consistency. Production breakage, lead loss, incorrect data and broken user journeys still override planned Design System work immediately.

The objective is to make the public AnyTour site feel like one product across:

`homepage → country/destination → hot/search → results → selected tour → lead`

Search remains the stronger product reference. It must not regress while weaker editorial pages inherit shared design tokens, shell geometry, typography, spacing, cards, buttons, breadcrumbs and responsive behavior.

## Latest material progress

- **PR #325** added one shared editorial rhythm layer through the canonical public-page shell instead of accumulating page-specific spacing patches.
- The new layer increases section separation, gives cards/steps/actions a consistent rhythm, and constrains long editorial copy to a readable measure across `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
- All PR #325 checks were green. Production deploy **33300567702** passed public-page verification, the unchanged lead bridge and live search smoke.
- Production visual run **33300650073** passed the full public route family at **375 / 430 / 768 / 1024 / 1440**, including homepage, search, contacts, how-to-buy, early booking, hot tours, country catalog and representative destination pages. It also checks overflow, single-shell ownership, logo rendering, search handoff and CTA contrast/focus.
- Representative production screenshots were manually reviewed after deploy: mobile `/contacts/` and `/hot/`, plus desktop `/how-to-buy/` and `/country/`. Hierarchy and spacing are materially cleaner, with no visible overflow or duplicated shell.
- **PR #326** promoted the responsive public-content gap and readable copy measure into canonical `design-system-v1.css` tokens. The editorial layer now consumes those shared tokens rather than owning duplicate values.
- All PR #326 checks were green. V2 deploy **33300796925** and standalone deploy **33300796910** are green; V2 verification and live search smoke passed after the token release. Post-deploy V2 visual **33300851477** and migrated-content check **33300902384** are green.
- Metrika, goals/events, Tourvisor, the external lead contract, the AnyTour logo and unresolved legal/payment content were not changed.

## Current product baseline

Whole-site coherence is now approximately **8.2/10**, moved cautiously from 8.1 only after production deployment plus representative five-width visual evidence and manual screenshot review. Search remains the stronger product reference at about **8.75/10**.

Current weaker areas are no longer basic shell breakage. The next gaps are subtler cross-page differences: homepage-specific primitives versus the shared editorial system, header/navigation geometry, footer/community density, and continuity between editorial discovery and the transactional search/results journey.

## Exact next work order

1. **Audit homepage vs shared public-page primitives** at 375/430/768/1024/1440. Remove repeated visual concepts only where the shared primitive can preserve or improve the current homepage result.
2. **Converge header/navigation geometry and active states.** Keep the mature `/poisk-turov/` header component in place until a full replacement is proven atomic and safe; isolated compatibility alignment remains acceptable.
3. **Audit footer/community density and wrapping.** Fix confirmed spacing, hierarchy or wrapping problems through the shared footer rather than per-page overrides.
4. **Continue page-family migration onto shared primitives.** Prefer one shared typography/spacing/card/button/breadcrumb implementation over page-specific copies.
5. **Audit the full discovery-to-lead continuity:** homepage → country/hot → search → results → selected tour → lead. Fix real hierarchy or handoff seams while keeping the mature search interaction density appropriate only where needed.
6. **Keep technical refactor work parallel and subordinate** where it directly enables safer Design System iteration, removes verified duplication, or improves regression coverage.

## Production baseline that must not regress

- public AnyTour product is one site on `anytoour.ru`;
- `/poisk-turov/` remains the transactional search application;
- legacy `/poisk-turov-test/v2/` is compatibility-only;
- the required visual baseline is 375 / 430 / 768 / 1024 / 1440;
- public editorial pages use the canonical shared shell and shared editorial rhythm;
- mature search/recovery/results/comparison/selected-tour/flight/price/fuel/lead flows remain protected;
- mobile search header and sticky CTA fixes from PRs #320/#321 remain protected.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals or events;
- analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

The AnyTour logo must not be redesigned or replaced. Verified social/app destinations must be preserved. Legal/payment migration remains deferred. PR #254 remains deferred unless freshly reassessed and proven safe.

Full replacement of the `/poisk-turov/` legacy header component remains deferred until an atomic migration path and equivalent browser coverage exist.

## Execution policy

Priority order:

`production broken → lead loss → incorrect data → broken user journey → Design System/site-wide visual unification → technical refactor → content/SEO → cosmetic refactor`

Work in narrow but material slices. Inspect current implementation and production evidence, fix one shared concept or weak page family, run the narrowest relevant checks first, then the broader five-width visual/search regressions. Do not create a second implementation when the canonical shared one can be extended. Do not delete a guard until replacement coverage is green. If blocked, record the blocker and continue the next independent safe Design System task.

# SEO2 Bootstrap — DONE

A dedicated `seo-autopilot-v2/` control plane was created on top of `feature/seo-autopilot-2`.

## What is now separate
- SEO2 state and active pilot
- SEO2 project/protected-boundary contract
- SEO2 task queue and risk policies
- SEO2 outcomes namespace
- SEO2 roadmap/milestones
- SEO2 agent-role namespace

The existing `autopilot-v2/` product/Search UX task set remains untouched and is not treated as the SEO2 backlog.

## Key operating decisions
- production host intent: `anytoour.ru`, with live verification required before host migrations
- AnyTour Design System 2.0 only for new user-visible SEO work
- technical foundation and existing-page quality before mass landing generation
- country → resort → hotel is a candidate core entity hierarchy, validated by inventory/data/intent evidence
- factual tour/hotel/price snapshots may enrich pages, but transient offers do not define durable page identity
- Metrika, Tourvisor/search behavior, pricing and lead transport are protected boundaries
- targeted/lightweight PR checks by default
- autonomous continuation across independent SAFE slices

## Runtime impact
None. Bootstrap added control-plane files only.

## Resume point
`SEO_INVENTORY` — repository + production-facing SEO contract inventory, followed by the first evidence-backed narrow SAFE repair.

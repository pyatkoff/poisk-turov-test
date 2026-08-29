# AnyTour Site Platform v1

## Goal
Build a maintainable page platform for tens of thousands of URLs without creating one PHP file per page.

## Ownership
`feature/site-product` owns:
- domain entities and their relationships;
- content blocks and page composition;
- page templates and rendering;
- admin/editor overrides;
- reusable UI components;
- responsive rendering and site visual QA.

It does not own search internals or SEO indexability policy.

## Core principle
Pages are rendered from data + templates + content blocks. Physical PHP files are entry points/templates only, not one file per destination.

## Domain entities
Initial entity types:
- country
- resort
- hotel
- departure_city
- tour_operator
- holiday_type
- season
- article

Every entity has an internal AnyTour ID. External provider identifiers (Tourvisor etc.) are mappings, never primary IDs.

## Content model
A page is composed from typed blocks, for example:
- hero
- search
- live_tours
- resort_grid
- hotel_grid
- price_calendar
- weather
- faq
- rich_text
- related_pages
- breadcrumbs

Blocks store structured JSON data and optional manual overrides. Rendering belongs to reusable block templates.

## Templates
Initial template families:
- country
- resort
- hotel
- departure
- departure_country
- departure_resort
- holiday_type
- article

SEO metadata is provided through a contract from the SEO layer. Site templates must expose hooks for title, description, H1, canonical, breadcrumbs, schema and indexability.

## Search handoff
SEO/content pages do not duplicate Tourvisor search logic. They create a normalized SearchIntent payload (country/resort/hotel/departure/dates/nights/etc.) and hand it to `/poisk-turov/` using the shared search parameter contract.

## Editing model
Generated values are defaults. Important pages can override:
- hero copy/image;
- intro text;
- individual blocks;
- FAQ;
- template variant;
- visibility/order of blocks.

The final value is always `manual override ?? generated default`.

## Database
Production target: MariaDB/MySQL via PDO. No database credentials in repository. Connection comes from server-side environment/secrets.

## Rollout
1. Introduce DB adapter + migrations.
2. Seed a small entity graph for Turkey as the reference vertical slice.
3. Render country/resort/hotel from database-backed templates while old URLs remain intact.
4. Add editor/admin after read-only rendering is stable.
5. Scale only after live/visual/SEO quality gates pass.

## Non-goals for v1
- replacing `/poisk-turov/` search implementation;
- importing every Tourvisor object immediately;
- mass-indexing generated combinations;
- building a generic CMS for arbitrary websites.

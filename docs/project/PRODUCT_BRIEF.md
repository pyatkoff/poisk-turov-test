# AnyTour product brief

Status: working product direction; implementation approval remains per slice
Updated: 2026-09-04
Scope: public AnyTour site, tour search and the handoff to the existing manager
lead flow

## Product vision

AnyTour should be the clearest and most dependable way to choose a package tour
with human support:

> Quickly find relevant options, understand what the price includes, compare
> real tours and hand the selected context to a manager even when supplier data
> is temporarily incomplete.

A commercially effective site is not defined by the number of promotional
blocks. It reduces uncertainty and loss at every step:

- the visitor understands the offer quickly;
- useful results appear before the entire search finishes where possible;
- hotels and concrete tours are easy to compare;
- price, flight and availability uncertainty are explained honestly;
- integration failures have a real recovery action;
- the user's search and selection context are not lost;
- an accepted lead reaches the existing operational channel without duplicates;
- the manager can continue without asking the user to repeat the search.

## Primary product objective

Increase confirmed delivered qualified leads and subsequent bookings per valid
search session without degrading production reliability.

The commercial north-star proposal is:

`delivered qualified leads / valid search sessions`

The exact definition and instrumentation remain **proposed** until the owner
approves a separate measurement scope. Existing Metrica goals and lead transport
must not be changed by this document.

## Target users and jobs

### The decided traveler

When I know the approximate dates, departure city, direction and budget, I want
to see relevant options quickly so I can choose without reading dozens of
irrelevant offers.

### The flexible traveler

When I can move dates or have not selected a resort, I want to understand where
my budget creates the best trade-off so I can make a confident choice.

### The trust-sensitive buyer

When I buy an expensive trip online, I want to know who handles the payment,
what is included and what happens after my request so I remain in control.

### The traveler facing incomplete data

When flights, price or supplier data fail to load, I want to keep the option I
found and send it to a manager instead of restarting the search.

### The group decision maker

When a family or group chooses together, I want to keep and share a short list.
This is a post-release growth opportunity, not a first-release blocker.

## Value proposition

AnyTour helps a traveler find a suitable package tour, compare concrete options
and continue with a real manager without dead ends. The manager verifies the
current price, flight and booking conditions before payment.

Proof should use only verified facts:

- clear price and availability verification language;
- contract and purchase process before payment;
- real offices, legal entity and contact details;
- real supplier data with explicit uncertainty where applicable;
- a recoverable lead path and confirmed operational receipt.

Unsupported best-price guarantees, invented review counts, fake urgency,
unverified 24/7 support, inferred instant confirmation and unsupported payment
options are outside the product.

## Core user journey

1. Enter through the homepage, search or a useful destination page.
2. Understand the offer and provide essential trip parameters.
3. See the first interactive results as early as possible.
4. Refine the loaded set with supported filters.
5. Open a hotel and compare concrete tours.
6. Select a tour.
7. Load real atomic flight variants or show an honest empty/error state.
8. Select a flight, or use the manager fallback without a flight.
9. Review the exact context that will be sent.
10. Submit a contact through the existing lead contract.
11. Receive a truthful accepted/error/retry state.
12. Continue with a manager without repeating known parameters.

## Required recovery journeys

The product must retain the user's context and a useful next action when:

- the supplier is slow or times out;
- results are partial or empty;
- flight lookup is empty, errors or fails again after retry;
- the price changes or the tour becomes unavailable;
- the browser loses the network;
- the user goes back or selects a second tour;
- validation fails;
- a repeated action could create a duplicate lead;
- downstream lead delivery cannot yet be confirmed.

Flight selection is not a prerequisite for asking a manager to verify a
selected tour.

## Product principles

1. Reliability before visual novelty.
2. No dead end on the route to human help.
3. Every visible action reflects a real available action.
4. Data is never shown as more certain than the supplier evidence.
5. One clear primary action per state.
6. Essential parameters first; deeper refinement later.
7. Preserve context across transitions, retries and back navigation.
8. Mobile is a complete journey, not a reduced desktop page.
9. Trust comes from verifiable facts.
10. Commercial changes are judged by end-to-end evidence, not clicks alone.
11. Production evolves through small reversible PRs.

## First release scope

Required:

- semantic and readable search form;
- progressive results and compact hotel/tour comparison;
- only data-backed filters;
- selected-tour, flight success/empty/error/retry states;
- lead entry with and without a selected flight;
- unchanged external lead transport and payload;
- keyboard-accessible critical path;
- responsive desktop/tablet/mobile layouts;
- state recovery and stale-request isolation;
- real failure-path tests;
- exact-SHA evidence, controlled rollout and rollback.

Later:

- shareable shortlist and durable resume;
- flexible-budget and flexible-date discovery;
- price-change notification with explicit consent;
- stronger destination decision support;
- approved high-value SEO landing pages;
- controlled experiments after reliable baseline data exists.

## Protected contracts

The following cannot change incidentally inside Search3 or visual work:

- current Yandex Metrica configuration, goals and event semantics;
- external lead transport, payload, field mapping, deduplication and delivery;
- Tourvisor request/response semantics and pricing behavior;
- manager shifts, routing, bonuses and neighboring projects;
- public routes and backwards-compatible links;
- the canonical AnyTour logo and Design System 2.0;
- verified social, app and contact destinations.

Any change to a protected contract requires a confirmed defect or separately
approved need, a dedicated PR, compatibility and rollback design, explicit
tests and owner approval.

## Non-goals for the first release

- a one-shot rewrite;
- wholesale merge of `feature/search3-preview`;
- pixel copying at the cost of reliability or architecture;
- simultaneous redesign of all content pages;
- mass publication of generated SEO pages;
- unsupported filters or fabricated certainty;
- replacement of the existing analytics or lead mechanism;
- loyalty, account or autonomous booking systems without a separate product
  decision.

## First-release acceptance

The candidate can enter controlled rollout only when:

- the complete search-to-lead journey works at required viewport widths;
- flight empty/error/timeout cannot block lead entry;
- stepper and CTA state are truthful;
- hidden elements do not receive keyboard focus;
- supported controls have accessible names and visible focus;
- input and selection state survive valid recovery paths;
- duplicate lead attempts are guarded by the existing contract;
- current Metrica and lead contracts are unchanged;
- critical paths have deterministic and real-browser evidence;
- one exact candidate SHA passes all release gates;
- the rollback path is proven before traffic is increased;
- the owner approves the exact visual and release candidate.

## Decisions needed later

These questions do not block foundation work, but block measurement or growth
implementation:

- What minimum data makes a lead qualified for sales?
- What existing signal proves downstream lead delivery?
- Which existing system owns manager response and booking status?
- What support hours and commercial claims are legally approved?
- Which directions and customer segments have the highest business priority?
- Which current pages should be intentionally indexable first?
- What baseline and minimum experiment sample are practical for current traffic?

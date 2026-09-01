# AnyTour Search 3 preview target

Preview: `/_preview/search3/poisk-turov/`

This branch is an isolated design/implementation workspace based on current `main`. It must not change production until the owner explicitly approves the preview.

## Search workspace

The dedicated `/poisk-turov/` page uses an expanded search form. Homepage and country/resort pages keep the compact search entry.

Primary search criteria: departure city, country/destination, resort/region, departure date range, nights range, travelers/child ages, hotel stars, hotel rating, meal plan, budget, concrete hotel, and supported quick constraints.

Tour operator is not a primary search field. It belongs to deeper result refinement.

`⚡ Моментальное подтверждение` is a first-class search criterion, but it must only become functional when backed by reliable Tourvisor/current-offer data. Do not fake or infer availability.

## Results

After search the expanded form collapses to a compact summary with `Изменить поиск`. Desktop gives the main area to a rich sticky filter rail plus wide hotel cards; mobile uses a dedicated filter drawer/panel.

The result filter rail should deepen the choice rather than duplicate the primary form. Preferred groups when reliably supported: actual tour price, location/sea line, beach type/ownership, flight/directness, baggage, airline, departure airport, hotel facilities, family/adults-only signals, availability/confirmation and tour operator.

Hotel cards are hotel-level. They show a price-from and `Показать туры`. They may show the count/presence of concrete instant-confirmation offers when that evidence exists.

Never show `Купить сейчас` on a hotel price-from card.

## Concrete tour

Only a concrete tour with known dates, nights, travelers, room, meal, flight/operator and current price may expose purchase actions. An eligible instant-confirmation offer can show:

- primary: `Купить сейчас`
- secondary: `Оставить заявку`

Before purchase, current price/availability/instant-confirmation eligibility must be revalidated.

## Protected contracts

Preserve Tourvisor search semantics, progressive 25→100 loading, stale-state guards, Metrika goals/configuration, lead transport, pricing semantics and the existing AnyTour logo.

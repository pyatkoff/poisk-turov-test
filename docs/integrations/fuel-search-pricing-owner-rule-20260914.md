# Search fuel surcharge owner rule — 2026-09-14

Owner clarification for INT pricing:

- Search result price must include the applicable fuel/air surcharge for every passenger in the package.
- The surcharge is selected by the flight program/date, not by hotel.
- Adults and children are counted separately; child surcharge may differ from adult surcharge.
- Direct ANEX source: `AdditionalPricesDaily` (`price_adult` / `price_chd`, or the matching converted fields when the search-price currency requires them).
- Andromeda source: transport directory / transport surcharge for the offer's flight program/date, with adult/child amounts when supplied.
- Search display estimate: `base search package price + adult surcharge * adults + child surcharge * children`.
- Missing surcharge stays `unknown`; never substitute zero.
- This is the search-result estimate. Final price is still replaced by supplier actualization / selected-flight calculation later.
- Do not block search-result surcharge arithmetic on final quote/calc availability.
- Preserve base search price, surcharge facts, and final actualized/quote price as separate evidence fields even when the search display amount is summed.

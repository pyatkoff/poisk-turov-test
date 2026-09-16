# Direct ANEX exact-offer APD price continuity

P0 defect: `additional_prices_batch` can expose a retained direct-ANEX concrete offer with customer-ready `search + AdditionalPricesDaily`, while a later exact saved `action=offer` read currently returns the original offer without that APD projection.

This package first retains the already-completed sanitized APD evidence on the exact saved offer so the subsequent exact-offer consumer can reuse it with zero supplier replay. Unknown APD remains no-replay/fail-closed. Search price, APD evidence and later quote/final price remain separate facts.

The remaining runtime projection must consume only this retained evidence for the same current offer/context; it must not fall back to base price, set `final_price_verified`, or issue a new supplier request.

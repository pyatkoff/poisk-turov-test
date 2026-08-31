# Local catalog routing

`catalog-local-routing-v1.js` only exposes `window.V2LocalCatalogApi.load()`.

It must not replace or wrap `window.V2Runtime.api`. Search catalog code (`catalogs-v2.js`) decides when local DB data is safe to use and falls back to the canonical Tourvisor gateway on any local error.

Local-first actions: regions without `arrivalId`, subregions, hotels with country+region. Dynamic availability actions such as countries, arrivals and operators remain on the Tourvisor gateway.

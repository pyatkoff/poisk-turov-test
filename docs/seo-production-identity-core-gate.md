# SEO production identity core gate

This gate validates the existing launched Turkey country/resort identity scope together with one protected hotel-tour reference.

It is deterministic in CI and does not perform live network calls. Live production collection remains a separate explicit command through `v2/data/report-seo-production-identity-v1.php`.

Safety boundary:
- launched Turkey country/resort pages are expected in sitemap with `index,follow` identity;
- the hotel-tour reference is expected `noindex,follow` and out of sitemap;
- the gate cannot publish, index, add sitemap URLs, change canonicals, mount routes, or modify Search/Tourvisor/pricing/lead/Metrika contracts.

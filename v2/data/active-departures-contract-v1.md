# Active departure catalog contract

The public departure selector must expose only Tourvisor departure cities that currently have at least one available destination country.

Source rules:

- `/departures?departureCountryId=1` defines the complete Russian departure directory.
- `/countries?departureId=<id>` determines whether a directory city currently has destinations.
- These calls are Tourvisor catalog calls and must not start `/tours/search`.
- `catalog_departures.is_active = 1` only when the country list contains at least one valid country id.
- `departures-v1.php` serves only active rows.
- A failed partial refresh must preserve the previous catalog rather than publish a partially evaluated set.

The complete directory remains persisted so future SEO/data tooling can still refer to known Tourvisor departure ids even while they are inactive for package-tour search.

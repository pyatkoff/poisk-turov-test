# ANEX access check for anytoour.ru

ANEX is an external supplier of tours and reference data for AnyTour. This
diagnostic belongs only to `pyatkoff/poisk-turov-test` and uses the existing
AnyTour SSH connection. The owner supplied two tokens and confirmed that ANEX
has allowed the AnyTour server IP (2026-09-07).

## Configuration

Repository Actions secrets:

- `ANEX_API_TOKEN`: Online SAMO API credential.
- `ANEX_REFERENCE_TOKEN`: XML reference credential.
- Existing `ANYTOOUR_DEPLOY_HOST`, `ANYTOOUR_DEPLOY_USER`, and
  `ANYTOOUR_DEPLOY_SSH_KEY`: AnyTour SSH transport.

Supplier base: `https://parser.anextour.ru/`; documented request path:
`/export/default.php`.

## Bounded read-only check

The isolated `diagnostics/anex-access-20260907` branch has a push-triggered
workflow, `.github/workflows/anex-access-probe.yml`. It first runs offline tests,
then enters the existing `$HOME/www/anytoour.ru` directory and executes the
diagnostic in memory over SSH on the existing AnyTour host.
There is no application bootstrap, database connection, package installation,
site deployment, booking request, or persistent server file.

At most four requests are made:

1. Online API `SearchTour_TOWNFROMS`: departure-city count (documented GET).
2. XML `currentstamp`: validate synchronization stamp.
3. XML `state`: first country batch only, using the stamp (no pagination).
4. XML `townstate`: available departure/destination route count.

Each request has a 20-second socket timeout and a 2 MiB response limit. The SSH
process has an overall 110-second timeout. HTTPS certificate verification stays
enabled and redirects are refused. Tokens travel via encrypted SSH stdin;
both supplier interfaces receive tokens in the documented HTTPS query format.
No response bodies, token-bearing URLs, exception messages, or secret values
are printed. Only fixed status names, HTTP codes, elapsed time, and counts leave
the process. The SSH key is held in a temporary mode-0600 runner file, removed
when the process completes; the SSH child environment excludes these secrets.

SSH uses `accept-new` with a temporary known-hosts file. This is trust on first
use for each runner, matching the unpinned existing transport; it is not an
independently verified, persistent server fingerprint.

Passing confirms only these read methods. Price search, live availability,
booking permissions, full reference import, Andromeda, and Tourvisor are outside
this diagnostic. No change to the site's live search is made.

## Sources

Initial run [34140006120](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34140006120)
reached the AnyTour host successfully. XML returned HTTP 200 for `currentstamp`,
278 `state` records, and 850 `townstate` records. The API POST request returned
HTTP 500. The follow-up uses the exact documented GET format to distinguish
POST dispatch behaviour from a general API-access failure.

- [Online SAMO API](https://dokuwiki.samo.ru/doku.php?id=onlinest:api)
- [XML gateway and reference synchronization](https://dokuwiki.samo.ru/doku.php?id=samotour:xml_gate)

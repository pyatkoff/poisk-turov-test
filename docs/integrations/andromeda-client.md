# Andromeda: offline authorization and dictionaries

Issue #1717. This first client is disabled by default and requires an injected
transport. A separate explicit cURL transport and CLI probe are prepared. There is no
runtime consumer, price request, booking, database write or deployment.

Official wiki read on 2026-09-09 through its normal index navigation:
- [Protocol](https://dokuwiki.samo.ru/doku.php?id=andromeda): JSON, version 1.01.
- [Authorization](https://dokuwiki.samo.ru/doku.php?id=andromeda:base): GET login,
  unique nonce, UTC created, password digest, sid with approximately one-hour life.
- [Dictionaries/search](https://dokuwiki.samo.ru/doku.php?id=andromeda:search):
  townfrom, state and all supply departure, country and search dictionaries.

The former PDF requirement was incorrect. Wiki documentation is readable.
Gateway supplied by the owner is HTTPS gateway.samo.ru/api/, not the internal
search.samo.lan hostname in documentation examples.

Digest implementation uses raw nonce bytes + created + lowercase hexadecimal
MD5(password), SHA1 binary output then Base64. Nonce wire encoding is Base64.
Synthetic fixtures verify this interpretation; they are not a supplier-certified
test vector. Unicode password encoding and real authentication remain unverified.
No password is sent to the public digest helper.

The client permits only login/townfrom/state/all. Four total transport attempts,
2 MiB response and 50-minute local session life are application safety choices,
not claims about provider rate limits. No automatic retry, re-login or pagination.
Supplier errors invalidate the session. Invalid responses are errors, not empty
availability. Output dictionary keys are allowlisted; session echoes are rejected.

The future transport must enforce HTTPS/TLS, no redirects, streaming body bounds,
timeout and a reviewed account-wide request budget. It must never log full URLs,
credentials, response bodies or exception argument traces. Debugging/serialization
of this client cannot expose sid. Auth secrets and sid must stay server-side.

Next: provision credentials through an approved private mechanism and validate
the digest with the prepared bounded read-only transport and probe. Do not merge this preparation blindly: the existing ANEX branch push
workflow can deploy integrations. Price normalizer and shared SEARCH handoff follow
actual sanitized samples, preserving provider/operator/hotel/offer identity.

Check: `php tests/andromeda-client-smoke.php`; CI uses fixtures only, no secrets/API.

## Prepared live probe (not executed)

`andromeda-transport.php` fixes the HTTPS origin, verifies TLS, refuses redirects,
streams at most 2 MiB, and has no retries. Its 1.05s spacing is only a conservative
pilot budget inside this process, not account-wide rate enforcement. Do not attach
it to concurrent search workers before the account limits are established.

The CLI accepts only `--execute <new-reservation-path>` and reads
`ANDROMEDA_USERNAME` / `ANDROMEDA_PASSWORD` from the private environment. Never
put their values in a command, issue, artifact or tracked file. Default invocation
or absent credentials exits before any API call. An exclusive 0600 reservation
precedes login + townfrom (two maximum calls), with summary-only readback.
Reusing a reservation is refused, including unknown interrupted results. Do not
delete it or switch paths to replay an unknown attempt. Keep it outside web roots.

This turn has not provisioned secrets or executed the probe. Transport guards and
CLI disabled/missing-secret paths are offline-tested; real TLS/service compatibility
and streaming behavior against a server remain deferred.

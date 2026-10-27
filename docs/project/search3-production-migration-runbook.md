# Search3 production migration and rollback runbook

## Current preparation — 28 October 2026, Europe/Kaliningrad

**Status: local production candidate preparation only; NOT ACTIVATED.**
No current live rollback snapshot or restore drill is asserted. No production
publisher has been approved for this candidate. Source preparation, final-source
CI, a staged entry, server backup, activation approval and production acceptance
are separate results. This section is current; the complete September runbook
below is retained verbatim as historical evidence, not executable instructions.

### Current basis and first release

The accepted release preparation baseline is 234f079a; the final candidate must
record its full immutable source SHA/tree and the hashes of the actual inputs.
Current installed ORIGINAL NEXT UI is source
62a7f3d08fcc9a12151ceb97a40de7aba0d41218 / runtime release
48563aadfee26af0c975b78dfd00952c2c66ee46
([publication](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451707739),
[installed copy readback](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6451787711)).
A locally modified checkout is a preparation input, not proof that those new
bytes were reviewed, committed, checked or installed.

Earlier own-account Telegram and MAX selected-tour inquiries were received in
the correct existing Manager dialogs with their displayed terms and responsible
manager preserved. Their private links, tokens, message bodies and identities
stay in the existing private evidence. This is accepted coverage of those exact
cases, not a current price, a new bot1202 card receipt or a production inquiry.
The newer post1202 case
[6452180476](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6452180476)
is consumed with its check not confirmed. It establishes neither a current
backend root cause nor natural delivery of a new bot1202 tour card. Do not
repeat that case, renew its price or reconstruct a link to complete this plan.

The first production release is **messenger-only**: select a tour, review the
whole-party price and its qualification, open Telegram or MAX, start the bot,
then receive the selected-tour context and reach the correct Manager dialog.
The site does not book or take payment. A checked TV observation remains
finalPriceVerified=false / browser-observation-only; sending it does not
guarantee final cost or availability. Missing fees, expiry and unavailable
confirmation remain explicit.

When the messenger path is unavailable, offer the reviewed ordinary contact
links and return to tour selection. Do not collect a phone number or consent,
expose the disabled preview phone form as a working fallback, submit to the
preview phone endpoint, or show successful lead delivery without its receipt.
The existing lead transport, price calculation, Metrika/goals and CRM contracts
are outside this frontend migration.

### Offline artifact and route contract

Use only [the offline candidate builder](../../scripts/build/search3_production_candidate.py).
It prepares the new public entry at /poisk-turov/ plus its explicit shared
/search3-runtime/ graph, an exact file inventory, content hashes and an archive.
The manifest, payload checksums and archive hash are the reviewable output;
PREPARED_NOT_ACTIVATED must remain explicit. Building or verifying it performs
no upload, runtime write, supplier request, inquiry or activation.

The production entry must use the reviewed production marker in the source
configuration. It must preserve the existing live script order, data/lead
owners, cold-loaded modules, styles and immutable brand assets. The production
query helper belongs explicitly in that graph; do not copy the offline demo
graph, enable new providers or silently fall back to a second renderer.

The first payload does not replace the existing home page or its scripts.
It must deliberately accept the existing home/old-search query through
production-query-v1.js before the app consumes its parameters. Review valid,
empty and rejected mappings for departure/country, dates, nights, adult/ordered
child ages and the supported filters. Preserve literal age0 and repeated ages.
Record unsupported legacy fields instead of silently broadening the search.
Canonical LOCAL hotel/region identifiers and Tourvisor supplier identifiers
are separate namespaces: translate only through the existing accepted canonical
mapping; never reuse a legacy TV hotel ID as a LOCAL ID. No helper may invent an
offer, confirmed price or supplier reference from a URL. Entry verification
must use an actual legacy/home-style query, not just the empty new route.

The backend dependency map is deliberate:

| Dependency | Production candidate decision |
| --- | --- |
| /api-v2.php and existing catalogue/calendar/content reads | Preserve the reviewed exact same-origin URLs and their existing contracts; inventory them as external dependencies |
| /_preview/search3-anex-candidate/api-anex-search3-preview.php | Keep this exact API URL; no relocation, alias or copied backend in /poisk-turov/ |
| ANYTOUR_ANEX_SEARCH3 cookie and CSRF | Keep the existing cookie name/path, same-origin credentials, Host/Origin/fetch-site checks and generation/expiry guards |
| NEXT and production session | They deliberately share the existing backend session. Concurrent tabs may invalidate a stale generation; do not claim separate production/preview isolation |
| Dedicated signer and app.anytoour.ru receiver | Keep the existing server-local custody, signed fixed receiver and authentic native-entry contract; no keys or runtime config enter the payload |
| Phone delivery | Disabled for the first production release; only reviewed contact links are the fallback |

The API cookie is sent for the existing API request path even though the UI is
at /poisk-turov/. Use a root-relative API on the same canonical HTTPS host.
Changing host, API location, cookie namespace/path, backend flags, private
configuration or production/preview session isolation requires its own INT
review and accepted installation; an artifact rewrite is not that approval.

For local preparation, the builder's supported build and verify commands are:

~~~bash
python3 scripts/build/search3_production_candidate.py build \
  --source-root . --output "$candidate_dir" \
  --source-sha "$candidate_source_sha" --source-tree-sha "$candidate_source_tree"
python3 scripts/build/search3_production_candidate.py verify \
  --output "$candidate_dir" --expected-manifest-sha256 "$candidate_manifest_sha256"
~~~

Choose a new empty output path and record the actual source/inputs. Replace
preparation labels with the final immutable reviewed commit/tree before the
release build; a caller-supplied SHA alone does not attest modified input bytes.
These are local commands only, not deployment instructions.

### Gates before requesting activation

1. **Final source and staged entry.** Review the complete delta and exact graph;
   pass applicable final-source CI and meaningful production-mode/contact/query
   regressions. Render the built /poisk-turov/ entry at430 and1280, including
   short desktop720, and inspect affected form, calendar, results, selected
   conditions, truthful price/expiry and messenger/fallback states. Prove that
   the production marker works, old home queries map correctly and no disabled
   phone collection or false success remains. Synthetic/staged proof is separate
   from supplier, native delivery and physical-phone evidence.
2. **Reviewed inventory and procedure.** Pin full source/tree, manifest and
   archive hashes; enumerate every overwritten, created and removed path and
   all retained external dependencies. Inspect the real production entry/router,
   existing home query and legacy-search exit before selecting a publisher.
   The existing broad deploy workflow and preview publisher are not approved
   production target publishers. Current main does not yet contain the required
   rollback tool; its reviewed delivery and invocation must be part of the
   concrete procedure before activation. Do not invent a new worker or treat a
   prepared source tool as an installed server capability.
3. **Current predecessor and rollback drill.** Immediately before any approved
   switch, verify the actual live predecessor and hash the exact paths this
   candidate will affect. Capture the corresponding private server-local
   snapshot outside the served root, with modes and the reviewed candidate-only
   delta recorded. Secrets/configuration remain server-local; do not upload
   snapshot contents to chat, GitHub or Actions artifacts. Restore that exact
   snapshot into a new isolated empty directory and verify hashes/modes and
   entry/PHP behavior. Neither a source archive nor a September receipt is a
   current live backup or a completed restore drill.
4. **Concrete owner approval.** Present the exact artifact, staged430/1280
   evidence, scope/limitations, predecessor snapshot/drill results and reviewed
   deployment/rollback procedure. Obtain approval before production activation.
   A changed artifact, target, predecessor or procedure requires reconciliation;
   old preview permissions do not activate it.

Physical phone/Safari, SAMO and Compare remain explicitly deferred for this
bounded first release. They are not resurrected from the September checklist as
general activation prerequisites. Existing mandatory final-source checks and
the price/identity/delivery safeguards still apply. No guaranteed calendar
deadline is inferred from a prepared artifact or an expected CI duration.

### Approved activation and one postactivation inquiry

After the gates above and the exact approval, the sole approved publisher applies
only the reviewed inventory. Verify installed hashes, /poisk-turov/ and the
existing home-to-search query, the preserved fallback/legacy route, unchanged
backend paths and the working ordinary entry. Do not enable advertising or
claim commercial success from HTTP200 alone.

Then run **one new, separately bounded own-account inquiry** through the activated
entry: current permitted tour/check with its honest price qualification, signed
messenger link, authentic native bot start, visible new bot1202 tour card, the
same selected conditions in Manager and the correct responsible manager.
Choose the permitted test channel and exact case before its live calls; retain
one receipt and the actual outcome. Do not replay6452180476 or either previously
accepted TG/MAX case, extend a stale price, switch channel to evade an UNKNOWN,
or substitute a plain channel note for the selected-tour/native-card proof.
Additional channels or scenarios need their own justified scope, not automatic
repetition of this launch check. Until this receipt exists, mark the candidate
activated/awaiting its exact production inquiry evidence rather than DONE.

Rollback immediately if the canonical route is unavailable, the inquiry carries
the wrong tour/party/price qualification or manager, or the interface falsely
claims a lead was sent. Stop the affected candidate entry, restore the exact
verified predecessor through the approved server-local procedure, verify its
hashes/routes, and remove only candidate-only paths in the reviewed delta.
Preserve inquiry/UNKNOWN history and runtime configuration; rollback is not
permission to resend. Record the terminal result and actual remaining condition.

## Historical — September 2026, preserved verbatim

The following document is an archival snapshot. Its statuses, pins, example
commands, broad provider/phone gates and publisher assumptions are not current
instructions and do not prove an installed rollback tool or a live backup.

<details>
<summary>September production-migration runbook — exact original text</summary>

# Search3 production migration and rollback runbook

Status: **rollback tooling fixture-tested; current visual-search transfer is NOT ready for activation. No live backup captured and no production switch authorized.**

This runbook applies to the eventual whole-site Search3 migration. It does not widen the nine-file search-only deploy allowlist and it does not authorize the existing production workflow. Owner approval of the exact preview version is still required.

## Current transfer review — 2026-09-28 UTC

The only accepted UI target is `https://anytoour.ru/_preview/search3-next-candidate/visual-search/`.
The old prototype and the old whole-site search are not substitutes for that UI.
This section updates this runbook; it does not create another product specification.

Reviewed release: `2e16ae86186547dabc976aa164ea2480595886f4`.
Installed NEXT source: `98af3df27950b4519b32ee146a1353cfbf39e956`;
installed INT at the acceptance checks: `dd64e683abf021717e010dd94073f1d49bd127aa`.
Reconcile newer source heads and actual installs separately before using this review.

| Evidence | Result and limit |
| --- | --- |
| NEXT install run `36493980488`, artifact `11001897996` | Isolated preview installed; not production activation |
| ANEX charter PARADOR, v40, [receipt](https://github.com/pyatkoff/poisk-turov-test/issues/3419#issuecomment-5880146007) | Explicit TK1232/TK3002, supplier final 164529 RUB, preview application and return passed |
| SAMO FUN&SUN KLEOPATRA, v41, [receipt](https://github.com/pyatkoff/poisk-turov-test/issues/3419#issuecomment-5880191093) | Final 101069 RUB, ZF3003/U63556, preview application and return passed; unambiguous pair, NOT manual alternative selection |
| ANEX regular SOKULLU, v42, [receipt](https://github.com/pyatkoff/poisk-turov-test/issues/3419#issuecomment-5880266008) | Fresh search and 40 flight pairs passed; explicit S7 pair calculation returned `quote_unconfirmed`, no final price. Cause remains unclassified |
| Fresh SAMO manual-selection attempt v43, [receipt](https://github.com/pyatkoff/poisk-turov-test/issues/3419#issuecomment-5880354067) | Search returned partial results; no eligible SAMO sample established, no quote or selection request made. Manual-selection acceptance remains open |

All cited attempts are terminal/no-replay. Historical empty APD and code1108 do
not describe these new outcomes. None of these checks sent a real application.

### Proven entry and lead ownership

| Current owner | Actual behavior | Transfer work still required |
| --- | --- | --- |
| `v2/visual-search/index.php` | Rejects paths outside the NEXT preview with 403; injects shared runtime/data/lead modules into `index.html` | Reviewed production entry and route mapping for this exact UI; copying its directory cannot activate it |
| `v2/prototype-search/config.js` | Uses `preview-lead-disabled.php` and INT preview supplier endpoints | Explicit production endpoint map and server dependencies; do not silently keep preview dependencies |
| `v2/prototype-search/lead.js` | Actual live target form owner. TV uses `leadSession`; SAMO/ANEX use `bindProviderApplication`, delegating to dry-run on preview | Provider mapping implemented under owner approval; real delivery remains disabled in preview |
| `v2/prototype-search/data.js` and `v2/tour-controller-v4.js` | TV session preserves numeric TV identity; separate provider session shares the same sender | Provider offer identity stays namespaced; no synthetic TV search ID |
| `v2/lead-bridge-v1.php` → `v2/lead-receiver-v1.php` → `v2/lead-adapter-v2.php` | Existing HMAC bridge and Bitrix delivery. Deployment installs the bridge under public `/lead-adapter-v2.php` | Reuse existing transport/auth, preserve receiver and CRM contract unless separately approved |
| `v2/lead-adapter-v2.php`, `v2/lead-idempotency-v1.php` | Provider-aware manager text and exact offer/flight/party dedup; legacy TV mapping preserved | Install this receiver mapping before enabling provider delivery |

`v2/visual-search/preview-lead.js` is used for offline scenarios; it is not the
live target form owner. The source graph above is selected by `index.php`.

### Approved provider handoff — implemented in source, delivery not activated

Owner approved this mapping explicitly on 2026-09-28 at 23:13 UTC (coordination
#3419, comment5880427102). The implementation keeps the same HMAC/receiver/Bitrix
transport and applies the following provider handoff contract:

| Value | Required mapping / rejection behavior |
| --- | --- |
| Provider identity | Preserve `andromeda` or `anex` plus exact opaque offer reference; never represent it as a TV ID. Use `provider`, `providerOfferRef`, and `tourId = provider + ":" + offerRef`; manager text says SAMO/Andromeda or ANEX |
| Confirmation | Bind to exact search generation, offer, room, meal, party, dates and selected outbound/return pair; reject expired, failed, pending and mismatched receipts |
| Price | Transfer supplier-confirmed RUB total only for the confirmed flow; no invented supplement or fuel formula. Keep estimate distinguishable and outside final-confirmed handoff |
| Flights | Preserve both directions, dates, airport codes and selected pair identity. Existing manager field is limited to 2500 characters; reject/resolve overflow explicitly rather than silently losing itinerary |
| Contacts | Reuse phone validation, explicit consent and existing CRM field limits; no contact data in diagnostics or browser persistence |
| Retries | Preserve in-flight guard, receipt-bound deduplication, successful `leadId` and explicit failure recovery; no success on HTTP200 alone |
| Return | Returning from application restores the same confirmed selection; changing offer/search/flight invalidates the old submission context |

The approval covers these provider fields/mapping only. It does not authorize
production activation, real CRM submissions, authentication or analytics changes.
`tests/search3-provider-lead-delivery.cjs` exercises the actual binder/controller
with an intercepted transport; `tests/search3-provider-lead-mapping.php` executes
the actual pure receiver mapping and dedup without bootstrapping Bitrix. The
existing lead-source workflow runs both. Backend deployment and real delivery
acceptance are still separate gates, not implied by passing fixtures.

### Release gates and rollout order

1. Close fresh SAMO manual alternative-flight acceptance and ANEX regular final
   calculation; record exact installed versions and terminal results. A successful
   charter or unambiguous pair does not close either branch.
2. Merge the approved provider identity/lead mapping only after exact-head CI
   proves exact fields, expired/mismatched rejection, double submit, failure and
   retry. Install its receiver before enabling delivery. Real sends remain a
   separately agreed check.
3. Prepare exact production route/configuration delta, dependency inventory,
   artifact hashes and immutable source tree. Review actual target at mobile and
   desktop sizes. Do not activate the old prototype, saved tours or comparison.
4. Obtain owner approval of that exact release and separately reviewed production
   procedure. Keep existing preview isolation until this approval.
5. Capture the CURRENT live predecessor and private rollback snapshot immediately
   before switching. The historical `fa58a0c…` example below is not proof of the
   current live version. Hash all overwritten paths and enumerate candidate-only
   paths; configuration/secrets remain server-local.
6. Restore the snapshot into an isolated empty directory and verify bytes/modes.
   Then use only the approved publisher and verify installed source, routes,
   search-to-selection-to-application and unchanged existing delivery behavior.
7. If canonical search is unavailable, verified identity/price/flight changes in
   handoff, contacts are lost, or delivery falsely reports success: stop the
   rollout and restore the exact predecessor. HTTP200/CI alone cannot waive this.

The existing offline checks are `tests/search3-visual-live-bridge.cjs`,
`tests/search3-prototype-provider-application-preview.cjs`, and
`tests/search3_production_snapshot_test.py`. Preview tests intentionally prove
NO delivery and must not be reported as production lead acceptance.

## Safety boundary

- The prior source release is `fa58a0cba6dcfc8624d98c20d64fa06330eae309` (`main` and `archive/search-before-search3-2026-09-04` at preparation time).
- The source archive is not a server, configuration or database backup.
- The live snapshot must be made on the server, outside the served document root, immediately before the approved switch.
- `config.php`, `.anytoour-bridge-secret` and any other runtime configuration remain on the server. Never upload the snapshot or its payload as a GitHub Actions artifact and never print file contents.
- The snapshot tool refuses absolute/traversal paths, symlinks, in-document-root backup locations, duplicate inventory entries and non-empty restore targets.
- Production Metrika/goals, Tourvisor/API contracts, lead transport/mapping and price calculation are not migration variables.

## Exact inventory before the approved switch

Build the inventory from the exact previous source revision in a trusted checkout. The `v2/` prefix maps to the production document root:

```bash
git ls-tree -r --name-only fa58a0cba6dcfc8624d98c20d64fa06330eae309 -- v2/ \
  | sed 's#^v2/##' > /tmp/anytoour-previous-managed-paths.txt
printf '%s\n' config.php .anytoour-bridge-secret images/logo.svg search-page-v2.php \
  >> /tmp/anytoour-previous-managed-paths.txt
sort -u /tmp/anytoour-previous-managed-paths.txt -o /tmp/anytoour-previous-managed-paths.txt
```

Review the list against the live deployment procedure and append any additional live-only file that the approved migration will touch. Do not include databases, cache trees, logs, `_preview/` or unrelated projects.

## Capture and verify the live rollback snapshot

Run from a trusted temporary location on the production host. `BACKUP_DIR` must be private and outside `$HOME/www/anytoour.ru`.

```bash
umask 077
python3 search3_production_snapshot.py snapshot \
  --root "$HOME/www/anytoour.ru" \
  --backup-dir "$HOME/private-release-backups/anytoour" \
  --inventory /tmp/anytoour-previous-managed-paths.txt \
  --snapshot-id "before-search3-$(date -u +%Y%m%dT%H%M%SZ)" \
  --source-sha fa58a0cba6dcfc8624d98c20d64fa06330eae309
```

Save only the command's JSON summary and the SHA of the reviewed inventory as non-secret evidence. The snapshot payload and manifest stay on the host with mode `0700/0600` protection.

## Isolated restore drill

Before switching production, restore the exact snapshot to a new empty directory outside the served root:

```bash
python3 search3_production_snapshot.py restore \
  --snapshot-dir "$SNAPSHOT_DIR" \
  --target-root "$HOME/private-release-backups/restore-drill-$GITHUB_RUN_ID"
```

The command re-verifies every stored hash and mode before and after extraction. Check PHP syntax in the restored tree and render its home/search entry with an empty document-root fixture. Record `restore_drill_verified`; do not call this a live rollback.

## Approved migration and rollback decision

Only after owner approval of the exact preview:

1. Record the approved source SHA, tree SHA, artifact ID and hashes.
2. Capture and verify the live snapshot above.
3. Verify the legacy route `/poisk-turov-old/`, the new artifact with preview isolation removed only where reviewed, and the unchanged API/lead contracts.
4. Deploy through a separately reviewed whole-site production procedure. Do not repurpose the search-only nine-file deployment.
5. Check home, search, hotel, tour, flight/clarification, summary and lead-form routes. A controlled real lead receipt is a separate explicitly agreed check.
6. If a release-critical check fails, stop traffic to the failed candidate, restore the reviewed snapshot through a server-local staging directory, verify all manifest hashes, then reactivate the previous entry points. Remove only candidate-only paths from a separately reviewed delta list.

The final live backup and activation steps remain intentionally unexecuted until approval.

</details>

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

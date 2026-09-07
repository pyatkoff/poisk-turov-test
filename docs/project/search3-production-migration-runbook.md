# Search3 production migration and rollback runbook

Status: **prepared and fixture-drill verified; no live backup captured and no production switch authorized.**

This runbook applies to the eventual whole-site Search3 migration. It does not widen the nine-file search-only deploy allowlist and it does not authorize the existing production workflow. Owner approval of the exact preview version is still required.

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

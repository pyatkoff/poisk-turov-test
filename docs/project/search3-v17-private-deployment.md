# Search3 v17: private candidate provisioning

Status: prepared for review; not installed. The owner has approved a separate
private migration candidate and has not approved a public/production launch.

## Concrete target

- New URL after successful installation: `https://anytoour.ru/_preview/search3-v17-candidate/poisk-turov/`.
- Public directory: `$HOME/www/anytoour.ru/_preview/search3-v17-candidate/`.
  It contains only `index.php` and `.htaccess`, never the UI payload.
- Private code and payload: `$HOME/.anytoour-search3-v17/`, mode 0700.
- Source is the exact successful whole-site artifact from open PR #3214, whose
  current head must descend from the current release and differ only in the ten
  declared entry presentation/test/generated paths. This new fixed publisher
  does not modify the existing public publishers or their release-equality gate.

## Login proposal

Use the already-enrolled AnyTour owner password, validated by the existing
`AnexReviewOwnerAuth` implementation. The deployment verifies its exact source
hash and the presence of an enrolled private owner account. It does not copy,
display, reset, re-enroll or export the account/password hash.

The new candidate uses its own `ANYTOUR_SEARCH3_PRIVATE` cookie, scoped to the new
path, Secure, HttpOnly and SameSite=Strict, and a separate private session directory.
Login requires HTTPS, same-origin POST and CSRF; successful login rotates the
session ID. Existing 30-minute idle / 8-hour absolute expiry, account revocation
and credential version checks remain effective. The existing credential failure
limit is shared with owner login (five failures per 15 minutes).

This is a new server authentication entrypoint. `AGENTS.md` classifies server/
platform architecture as HIGH and requires explicit review of the exact change.
The implementation is prepared before that review; no main merge or installation
has occurred as part of this draft.

## Publication and recovery

After review and green control CI, merge only this provisioning PR to main.
This does not merge the UI draft, replace production or publish the candidate.
One fresh owner command on #2530 authorizes the exact one-shot installation:

```
/create-search3-private-preview <PR3214-head-SHA> <current-release-SHA> <successful-build-run> <whole-site-artifact-ID>
```

The prepare job has no SSH secrets. It verifies the PR, build, checks, artifact
identity and ZIP/tar hashes; changes only the payload route helper; adds the
trusted-main private gateway; renders the derived PHP and retains exact bytes.
The publisher independently repeats provenance/derivation and compares the
retained bytes before using existing AnyTour deployment credentials.

The remote transaction refuses an existing target, validates the existing owner
auth module/account, snapshots protected production and sibling-preview files,
and puts the full payload outside document root before atomically activating the
two-file public gateway. If rewrite is unavailable, no payload becomes public.
The acceptance probes require HTTP 401 for pages, bundles, CSS/JS and internal
entry paths without login. Failure removes only this exact newly created copy;
unknown ownership or changed files stop recovery rather than replacing them.

## Scope and remaining acceptance

- Original logo and production Tourvisor/lead/price/analytics contracts remain unchanged.
- The checked preview artifact keeps leads disabled and Metrika counter zero.
- LOCAL-only DB endpoints remain denied; their existing path restrictions are not widened.
- The first UI packet transfers the form/category choices, not all v17 features.
  Real price/flight selection and exact selected-variant lead handoff remain later
  migration work, with real-data acceptance before production approval.
- Automated fixtures verify anonymous denial, successful owner login with a
  disposable account, cookie isolation, CSRF, private assets, credential revocation,
  deterministic bytes, source scope, create-only activation and rollback.
- After installation, the owner signs in with their existing password to accept
  the live candidate. Automated deployment does not obtain or log that password.
- Public rollout and production lead activation require separate owner acceptance.

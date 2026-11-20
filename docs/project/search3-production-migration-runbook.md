# Search3 production migration and rollback runbook

Status: **production preparation in progress; current visual-search transfer is NOT ready for activation. No live backup captured and no production switch authorized.**

This runbook applies to the eventual whole-site Search3 migration. It does not widen the nine-file search-only deploy allowlist and it does not authorize the existing production workflow. The shown public contact interface is approved; the installation still needs the concrete route, configuration, installed predecessor and rollback evidence described below.

## Current preparation — 2026-11-20

Pavel authorized preparing the concrete migration package after reviewing the installed
ORIGINAL NEXT. He subsequently approved the shown public contact interface from
`8d5fc37fdc7baca67f0a26444a4548f8c7ded38c` with «дальше». The following package work
keeps those runtime and UI owners unchanged. This preparation makes no supplier
requests, sends no applications, does not change secrets or protected contracts,
and does not authorize a server run.

Pavel clarified the primary contact path on the same day: **selected tour →
Telegram/MAX → the existing manager conversation**. The legacy phone/Bitrix
receiver is not a dependency of this messenger path. Its SSH failure must not
be treated as a prerequisite for continuing Search3. See the
[scope correction](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7043236583)
and [public contact review claim](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7043406640).
The phone transport and its historical acceptance below remain separate.

### Exact starting points

- Accepted NEXT source `d6e0563d598b32d59d6219e68eed97340f2e68a1`, release
  `63844d4093e82dbe2810294514fe2a881df981ed` / PR5117;
  [installation receipt](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7031874040).
- Initial REVIEW Git `main` input: `e68fe088764521c7ff6b605403ca7f2ebb7e4dd6`.
  The explicit LIVE package uses inspected `c5513aaee18af701f1253678e79f41e5725b71a2`;
  both have the same `v2` tree, `3e3d9bc8315daa13f5e052ddbd5678597999a49b`.
  These are source references, **not verified installed production revisions**.
- [Public integration preparation claim](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7040614703),
  continuing [PR5118](https://github.com/pyatkoff/poisk-turov-test/pull/5118) on
  `work/search3-production-prep-20261120`. Its first source preparation was
  `189d1eac8a83842d7eaf6a6acb648cf26a773c8b`; this is not the final integration head.
  Final candidate and tree identities
  must come from the final reviewed commit and the generated inventory, not this base pin.
- Draft PR1334 is divergent and not mergeable. Its September description and the old
  eight-asset production-import manifest do not describe the current visual-search UI.
  Do not merge all release history or copy the whole release `v2/` over current main.

Prepare the eventual integration on a fresh main branch using only the reviewed
runtime inventory. Preserve unrelated main files, especially the current price
monitoring, data observers, homepage, site shell and SEO owners. The preparation
patch itself targets the release branch for review and CI; it does not activate
either `/poisk-turov/` or the NEXT publisher.

### Concrete corrections in this preparation

1. `visual-search/app.js` retains the six existing `yclid`/`utm_*` fields when the
   search owner rewrites its URL. The unchanged canonical lead reader obtains these
   fields from `location.search`; previously they were erased by the first rewrite.
   No new attribution field, counter, goal or transport is introduced. The regression
   uses actual source owners and intercepted DOM collaborators, not a live supplier.
2. `/poisk-turov-old/` resolves the maintained `search-page-v2.php` before falling
   back to the source `index.php`. The standalone publisher puts the homepage at
   `index.php`; directly including it would therefore lose the fallback search.
   Existing noindex, canonical path, legacy flags and request parameters are retained.
3. `visual-search/public-url-v1.js` provides an explicit, pure translator for the
   supported legacy form/country links. It uses the existing native-to-canonical
   country crosswalk and preserves dates, nights, adults, child ages and attribution.
   Unknown nonempty conditions, unresolved filters and mixed NEXT/legacy links return
   an error with no partial query. The new public bootstrap selects the namespace
   before applying any conditions, resolves the native departure before loading its
   country catalog, and preserves the original query/hash in an explicit old-search
   fallback when migration is unsupported. Converted country-only links are stamped
   with the canonical origin/scenario so reload cannot reinterpret their country ID.
4. `visual-search/entry-v1.php` owns the single ordered LIVE graph and the renderer.
   The existing preview wrapper retains its original guard and offline semantics.
   A separate `public-entry-v1.php` is a source capability for the exact public
   route; the source `v2/poisk-turov/index.php` is not switched by this PR.
   Public HTML uses absolute resource URLs, hides laboratory controls, and retains
   the existing form/card/room/price owners and layout.
5. The public LIVE configuration explicitly selects the existing API paths and
   messenger contact mode. Both public profiles keep the phone lead endpoint
   disabled. The actual messenger transport remains the existing INT producer
   and its fixed bot receiver; no replacement delivery mechanism is introduced.
   Narrow public-page admission is added to the canonical profile and LOCAL content
   clients. Existing endpoint, session, identity, price and lead contracts remain
   unchanged. This source capability is neither backend admission nor installation.
6. Public analytics calls the existing analytics owner with its existing goal names
   from actual transitions. It does not dispatch legacy search events that would
   reset the canonical catalog, guess native hotel IDs, or count a messenger handoff
   as delivered lead. The review graph does not load analytics at all.

### Approved public messenger contact

The existing contact component now has a public-only presentation mode. Its tour
recap, room, meal, family, flights and whole-party amount keep their existing
owners. Telegram/MAX availability determines whether the handoff can proceed;
it does not switch the public visitor into the legacy phone form. An unavailable
transition keeps the selected tour and offers the existing return action. An
uncertain CREATE keeps the original operation and explicit status check; it never
asserts non-delivery or creates a replacement operation.

The public REVIEW fixture shows the same component with a conspicuous demonstration
label. Its buttons only show a local example of an unavailable transition. They
do not enable the LIVE flag, change `data.live`, create provider receipts or call
token/freeze/create/status, a bot, a supplier, or the phone sender. The existing
family/snapshot profile remains fixed by the build. The candidate is for visual
and interaction approval; it is not evidence of manager delivery.

Reference: the PR5118 `dd216ed705bc79cb2eb8c17968fb114cd5ca4cd7` review artifact and
the retained PR5117 NEXT source above. Candidate identity is the final commit/tree
in the generated manifest. The affected review covers contact, unavailable state
and return at 430/1280, including the retained 3-adult, children aged 0/12,
Standard/AI, 186400-RUB fictional example. This is a scope description, not a
completed-test or 9.5/10 claim. The later shown `8d5fc37...` interface is approved;
its [exact-source receipt](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7044198437)
does not prove that the new mixed-main LIVE artifact has passed CI or is installed.
The NEXT-only profile keeps its existing phone option and behavior until a
separately reviewed change is approved.

### Review artifact and local rollback rehearsal

`scripts/build/search3_public_candidate.py` defaults to an isolated **review**
artifact. Its explicit offline LIVE profile is described below; neither profile
has a server executor. Inputs are immutable Git objects and already installed,
version-checked compiler dependencies:

```sh
python3 scripts/build/search3_public_candidate.py \
  --repo . \
  --candidate-sha <40-character-reviewed-commit> \
  --main-sha e68fe088764521c7ff6b605403ca7f2ebb7e4dd6 \
  --output <new-directory-outside-any-checkout> \
  --node-modules <existing-pinned-compiler-node_modules>
```

The default scenario is the explicitly labelled fictional family example. The only
alternative is `--scenario snapshot`, using the existing saved offer data. A URL
such as `?scenario=live` cannot change the server-selected profile or scenario.
The public wrapper rejects a missing/invalid local build profile. The existing
`/_preview/search3-site-candidate/poisk-turov/` prefix accepts review only.

The artifact contains `site/` for local review, `overlay/` for inspecting the bounded
file delta, and `manifest.json` with the **final served bytes and modes**. The compiler
runs against candidate inputs in a disposable stage; shared main files remain the
main originals, including runtime, analytics, SEO, homepage, price/data and lead
owners. `compiler-output.json` is separate and must not be mistaken for served hashes.
Only the established homepage/search-page aliases are staged; the installed public
lead bridge is not inferred from main source or replaced by a source adapter.

At the pinned main revision, the public search entry is the sole replaced route and
the corrected old-search route is an addition. A known older old-search wrapper may
be corrected only if its exact predecessor blob matches; any other collision fails
the build. No whole-release overwrite is permitted.

The existing snapshot/restore helper captures the replaced versioned route(s) outside
the served root. A disposable rehearsal removes only hash-matching listed additions,
restores those routes, and compares every main file's bytes and mode to the baseline.
`rollback-rehearsal.json` records that result. This is an actual **local source-based
restore**, not a backup or rollback test of the running site. The artifact always has
`deployable: false`; current installed hashes and a live rollback snapshot are still
required before an approved installation.

### Explicit offline LIVE installation candidate

The [bounded packaging claim](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7045452012)
continues PR5118. It adds a build-time choice without changing the approved UI,
transport, API, session, signing, lead or analytics owners:

```sh
python3 scripts/build/search3_public_candidate.py \
  --repo . \
  --candidate-sha <40-character-reviewed-commit> \
  --main-sha c5513aaee18af701f1253678e79f41e5725b71a2 \
  --profile live \
  --output <new-directory-outside-any-checkout> \
  --node-modules <existing-pinned-compiler-node_modules>
```

The manifest purpose is `local_public_live_install_candidate`; `deployable`,
`published` and `network_used` remain false. The exact graph contains 22 initial
scripts, three existing on-demand modules and two stylesheets. `site/` contains
the mixed candidate/main runtime; `overlay/` contains only the bounded route
replacements and additions. Every final file has its served hash, size and mode.
Compiler output is recorded separately. The existing source-based rollback
rehearsal removes only matching additions and restores every main byte and mode.

LIVE requires existing main `runtime-v3.js`, `analytics-v4.js`,
`lead-search-context.js`, `analytics-config.php` and `seo-config.php`. It retains
them exactly. It does not expand PHP, DB, MATCH or supplier code from the candidate.
Optional `data/hotel-popularity-v1.php` stays absent when absent from main; if
present, it stays main-owned and its transitive dependencies remain explicitly
unverified. Existing installed configuration and backend admission cannot be
inferred from these source files.

The LIVE closure excludes family/snapshot adapters, saved offer JSON and demo
photos. The build-profile schema retains its existing `scenario: family` field,
which is only a REVIEW selector; LIVE initializes the existing live bridge and
reports `AnyTourPrototypeData.scenario === 'live'`. `--profile live --scenario
snapshot` is refused. URL parameters cannot select a different profile or activate
the phone sender, and the isolated whole-site review prefix rejects LIVE.

The existing `search3-visual-migration.yml` job builds and retains the exact LIVE
artifact, then runs `tests/search3-public-live-browser.cjs`. That bounded check
renders the actual PHP entry and serves only inventoried compiled assets. At
430/1280 it exercises the initial graph and all three on-demand modules, the
fictional 3-adult/children 0/12 selection, a concrete flight pair, messenger-only
contact, unknown CREATE, explicit status on the same operation, unavailable state
and return to the same tour. All data and handoff responses are intercepted;
unlisted routes, supplier/bot requests and phone submissions fail the check.
Unexpected executable local configuration or optional DB bootstrap also fails
closed. Screenshots and receipts join the existing visual evidence artifact.
Read the actual final-source CI result before claiming this compiled check passed.

This package is inspectable installation input, not a production installer. It
does not provide fresh installed INT/LOCAL/configuration hashes, a server-local
predecessor snapshot, a production rollback drill or a write procedure. Those
remaining dependencies must be resolved for the exact installation; the local
source restore cannot stand in for them.

### Source integration and publication boundaries

Merging the accepted source into the inspected `release` revision is source
integration only. For the PR5118 path set inspected at `8d5fc37...`, the only
matching release-push workflow performs the temporary-files rollback fixture
drill. It does not publish NEXT, write a server or contact a provider. Recheck this
conclusion if the workflow/path set changes. The existing NEXT publisher still
requires its explicit command and targets only `/_preview/search3-next-candidate/`;
the whole-site preview publisher is a separate fixed-prefix review operation.

The historical broad `deploy-anytoour.yml` runs on matching pushes to `main`
(`v2/**` or its own workflow path). It copies the whole site, prepares the legacy
receiver and contains lead/provider smoke requests. It is not an admitted path for
this bounded public package. A source merge must not silently trigger that path
or be described as an installation. No paid provider request, application, booking
or bot message is authorized by this package work.

### Exact offline runtime inventory

`scripts/build/search3_production_inventory.py` reads two immutable, already available
Git revisions without checkout changes, network access or server access. For example,
after the preparation commit is reviewed:

```sh
python3 scripts/build/search3_production_inventory.py \
  --repo . \
  --candidate-sha <40-character-reviewed-commit> \
  --main-sha <40-character-current-main-commit> \
  --output <new-directory-outside-any-checkout>
```

The output records source Git objects, paths and SHA256 values for the retained NEXT
live/on-demand script and stylesheet closure, static assets and its direct optional
PHP dependency. It also exposes the derived public LIVE and REVIEW declarations for
the separate public builder; these source declarations alone are not a complete public installation
inventory. The initial preparation
at `189d1eac` counted 38 source files; that historical count must not be reused for
the expanded public entry. Use the manifest generated from the reviewed head.

The manifest always says `deployable: false`. Its separate source-level snapshot
candidate list is neither a deploy allowlist nor a live-server inventory. Unresolved
server/transitive PHP dependencies, current production hashes and production/rollback
admission must be resolved before selecting an install set.
The tool rejects changed graph grammar and unsafe output paths rather than inventing
an incomplete graph. Pairwise source differences are not labelled merge conflicts.

### Required production bindings

The exact client action groups and their current guards are recorded in
[`search3-production-endpoints.json`](search3-production-endpoints.json).
That file is a source inventory, not executable configuration or an installation receipt.

| Boundary | Current fact | Required result before activation |
| --- | --- | --- |
| Public entry | Shown messenger-only interface approved; fresh read-only inventory confirms the existing public route still matches its main predecessor | Verify the exact mixed-main LIVE artifact, actual site configuration and the concrete installation/rollback procedure |
| Private TV, direct ANEX, messenger | Same preview INT URL, distinct action/session/identity guards; fresh read-only inventory matches all 14 retained INT code pins | Verify private enable flags, configuration, signer/bot dependencies and public-page admission; preserve the existing session and identity rules, with no root fallback or guessed API paths |
| LOCAL catalog/calendar/content | Prepared public clients explicitly reuse existing preview endpoints; their filesystem admission and DTO checks are unchanged | Verify actual installed dependencies with the existing read-only and identity rules |
| Messenger delivery | Existing INT producer uses `https://app.anytoour.ru/search3/tour-handoff.php` and returns Telegram/MAX links | Preserve the saved exact tour, signing/session admission and existing bot consumer; distinguish ready/opened links from arrival in the manager conversation |
| Separate phone transport | Existing `/lead-adapter-v2.php` is the public HMAC bridge to the legacy receiver; the proposed public messenger contact does not invoke it | Preserve existing site owners. Its access and field mapping are prerequisites only if phone delivery is separately activated, not for Telegram/MAX |
| Analytics and inbound links | Source public integration preserves existing goals and resolves supported old links before hydration; review sends no analytics | Verify the installed public counter/configuration and exact public flow before activation |
| Production predecessor | Two identical read-only inventories record the existing public route and known root owners; no private live snapshot exists | Bind the final install delta to current predecessor hashes and use the existing snapshot/restore tooling immediately before the approved switch |

Missing bindings are not satisfied by removing `nativeEndpoint`, path or filesystem
guards. SAMO/Andromeda remains paused. The historical supplier attempts and September
acceptance list below are provenance, not instructions to restart paid checks.

The existing absolute INT URL is a possible explicit binding for the public page on
the same origin. Source `3207266e17ed4e8244aaf28d05b962747fd4cd3c` checks
host/origin/session/CSRF and action identity, not the referring page's route. Its
session cookie path applies to the requested API URL. Moving that API is therefore
not an established prerequisite. This is source analysis, not proof that this INT
revision is installed or that public use has been admitted. The prepared public
frontend selects that existing path; actual installed dependencies still require
verification.

The latest retained INT installation is
[PR4960/CONTROL4961](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6528951578),
source `6dcfb5053793caba4f353facfa7a899a30df2d17`,
[run41097499149](https://github.com/pyatkoff/poisk-turov-test/actions/runs/41097499149),
artifact `12996328865`. The 19 November
[coordination entry](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6995931455)
still uses that installed pin map. This was maintenance with 12 selected / 14 pinned
files and one changed private quote client, not a whole-runtime reinstall from one
commit. The API, messenger authorization and TV gateway source hashes inspected for
the URL/session analysis match this retained installed map; the newer private TV
client changes in `3207266...` are not installed. The earlier readiness attempt
[44759079166](https://github.com/pyatkoff/poisk-turov-test/actions/runs/44759079166)
stopped before file hashes and remains consumed/no-replay. It does not establish
an installed-file mismatch. After the separately reviewed read-only control fix,
[command 7045609219](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7045609219)
ran once in [44779751229](https://github.com/pyatkoff/poisk-turov-test/actions/runs/44779751229),
job `136438969050`, attempt 1, and succeeded. The control source is PR5120
`b88ce8e58b41cbd2f3977e07bae71c461fea2167`, merged at main
`f58b6bb2a2873b4386321fab2a678d3113aa44c3` with the same `v2` tree used above.
Artifact `14651686519` has ZIP SHA256
`1ec03d04cfff7e65cdf186d49c3b58cf2fe3e43dd78ce4c759352f4bd0dcfb5f`.
The [verified receipt and packaging scope amendment](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7045700047)
records evidence JSON SHA256
`3484f7597d68321365084115752dcbc0dba651888b8fb0164cc5f288cd4d78fb`.
That readiness command is terminal/consumed; the package does not authorize replay.

Both passes returned the same 23 rows: 22 present files and the absent old-search
route. All 14 INT pins match the retained `6dcfb505...` installation map; both NEXT
pins match PR5117; the public search route matches its expected main predecessor.
This is fresh **installed code identity** evidence. It does not read private flags,
prove LOCAL/configuration/signer readiness, exercise PHP or API calls, create a
session or capture a rollback snapshot. `installation_ready` remains false.
The public `anex-preview-manifest.json` is not updated by the maintenance executor
and cannot replace these private installation/readback receipts.

The actual root `index.php` hash is
`80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f`, matching
the source main search page, not the builder's staged homepage alias. Root
`api-v2.php` is `90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2`,
the known `CONTEXT_ROOT_HASH` of the existing root gateway installation control.
These are known installed owners, not an inferred drift defect. Both remain
outside the public overlay. Full root/admin routing acceptance is still unknown;
the staged source aliases must not be used to replace either installed owner.

### Historical phone installer: access failure and separate recovery

This installer includes legacy phone preparation. Its failure does not establish
a dependency of the primary messenger path and is not a reason to run it for
this contact review. No existing production workflow is authorized here.

[Run44286139929](https://github.com/pyatkoff/poisk-turov-test/actions/runs/44286139929/job/134739185043)
stopped at the first SCP in `Prepare authenticated legacy lead receiver` with
`Permission denied (publickey,password)` / exit255. Both private keys parsed
successfully first. Remote PHP/chmod/secret preparation and the subsequent site copy
were skipped. This establishes failure of the legacy host/account/key combination;
it does not establish failure of already installed customer lead delivery.

The affected existing secret names are `DEPLOY_HOST`, `DEPLOY_USER` and
`DEPLOY_SSH_KEY`, targeting `~/www/anytour.online/poisk-turov-test/v2`.
`ANYTOOUR_DEPLOY_*` is a different tuple; successful NEXT publication cannot verify it.
This failure blocks the existing broad installer and any required receiver update.
It does not by itself prove that a separately reviewed, additive public-site install
needs legacy-host access. Such an install must preserve the actual installed lead
chain and prove that its selected phone/provider flows work with that predecessor.
In particular, release `lead-adapter-v2.php` and `lead-idempotency-v1.php` differ from
main; copying them is a receiver change, not preservation of the old contract.

If the chosen install requires that host, the repository/legacy-host access custodian
must confirm the intended account and matching authorized public key, then correct
only the proven stale element. Never copy private key values into an issue, artifact,
log or this document.

After access is repaired, perform one separately admitted read-only preflight using
that same tuple and the existing trust policy: batch authentication succeeds and the
fixed receiver directory, PHP interpreter and existing receiver dependencies are
accessible. It must not copy files, change modes/secrets, POST to the receiver or
invoke a supplier. No legacy-only executable server probe is introduced by this PR.

Do not run `Deploy anytoour.ru` merely to test a key: after authentication it writes
the receiver and site, verifies the bridge and starts a real supplier smoke search.
The current user request forbids paid queries and applications. A production
procedure compatible with that request still requires its own review and approval;
existing gates are not silently skipped or relabelled as passed.

### Validation and remaining acceptance boundary

The preparation uses intercepted source-owner/DOM contracts, local Git fixtures and
the publisher's alias-copy layout. The attribution regression fails on the original
URL owner and passes with the correction; the public-link test distinguishes its
pinned main form contract from the current NEXT restore owner. These are offline
compatibility checks, not supplier latency, live lead delivery or a new mobile UX
score. The public-entry test executes real PHP, including byte-for-byte comparison
with the retained preview entry, invalid profile/path cases and traps proving review
does not read production configuration. Missing PHP exits77 and fails the CI gate.
The public bindings test exercises actual catalog/bootstrap/app/analytics owners with
intercepted transports. The builder tests compile real sources and rehearse restoration.
The compiled public browser check uses the exact mixed-main review artifact at
430×932 and 1280×900: form category/stars, additional mobile budget, whole-party
fixture total, room/contact/return, reload, unsupported-link fallback and both
permitted review paths. It records screenshots and network evidence and submits no
contact form. This is browser emulation, not physical iPhone acceptance.

Before activation, separately close all three owner priorities:

1. Confirm the actual installed main/INT and messenger-consumer predecessors and
   reviewed public configuration. The separate phone receiver is not a dependency
   of this Telegram/MAX path; source main is not proof of any installed revision.
2. Compare retained evidence for a real selected room, meal, flight and total for
   **all** travellers with the same tour in Telegram/MAX and the manager dialogue.
   Keep the exact historical source/party/price limits and physical-device scope.
   A fresh connected case needs its own permitted conditions; current no-paid-search
   and no-application instructions remain in force. Local fixtures do not close it.
3. Capture and verify the live rollback snapshot and obtain approval for the exact
   source/artifact/version before changing the running interface.

The existing attribution/legacy-route checks remain in their earlier source gates;
public preparation checks are added to the existing visual/build lanes. All previous
required visual commands remain present.
Main's CI differs from release's CI; the eventual main integration must explicitly
retain these gates instead of assuming that the release workflow exists there.

## Historical transfer review — 2026-09-28 UTC

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

### Separate phone-form ownership and historical mapping

The following form/Bitrix chain describes the optional historical phone branch.
It is not the messenger chain and is not a launch prerequisite for the public
messenger-only proposal. The prepared public configuration leaves phone delivery
disabled; the existing NEXT profile remains unchanged.

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

### Historical approved provider phone handoff

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

### Current messenger release gates

1. Review the exact public entry, contact/fallback behavior and artifact on the
   agreed mobile/desktop widths. Preserve hotel category/stars, budget placement,
   selected conditions, price qualification and whole-party amount.
2. Confirm the actual existing INT/session/signer and bot consumer dependencies.
   Reuse retained delivery receipts only within their exact source and scenario;
   a fixture or ready link does not prove a new Telegram/MAX manager delivery.
   The current prohibition on paid supplier requests and real applications remains.
3. Obtain approval of the concrete interface and exact release, capture and verify
   the current predecessor/rollback through the existing permitted procedure,
   then use only the separately approved publisher. No live switch is authorized
   by this source or review artifact.

### Historical provider-phone rollout order — not the current messenger gate

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

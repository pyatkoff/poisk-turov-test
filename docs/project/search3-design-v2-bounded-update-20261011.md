# Bounded update of the separate Search3 design preview

This is an UPDATE control only for the existing new target
`/_preview/search3-design-v2-candidate/`. It does not recreate or overwrite NEXT,
the original Sites project, any other preview or production.

The exact required installed predecessor is source
`950c9b5c0a00430c5b5a711d1b95bc04449d069d`, published by run `38180461319`,
with canonical inventory SHA256
`0f489470b5621f4660b800c377f37dbbfe1ad5fbd65deea1890e053f751f8a33`
and a matching published owner. An absent, modified, activated, foreign or
unknown target aborts. After one successful update this control cannot publish
another source over the new predecessor.

This successor prepares the owner's 12.10.2026 correction against the final Sites
reference. The previous creation command/run38173086001 and polish update
command/run38180461319 remain terminal/no-replay. Their sources cannot be used as
a new update. Rollback during this transaction restores the verified published
polish predecessor, never the original creation.

The only command form is a fresh OWNER-created #4217 issue comment on trusted
main, run attempt1:

```text
/update-search3-design-v2-preview <source40SHA> <release40SHA> <buildRun> <artifactId>
```

These placeholders are not a publication command. The receiving ref is always
`release/search3-design-v2`; neither target nor branch nor predecessor is an
argument. The shared fixed-ref provenance verifier, immutable source artifact
digest and all five exact-source success checks are required in both prepare and
publish: guard, build-preview-artifact, focused-presentation, visual-migration,
lead-source. Publication also rechecks the command, fresh main and receiving ref.

The original accepted artifact11710372908 is downloaded and verified against its
fixed SHA256 `5267c2eec2549d3117a87a71faedf410d699baa0bdbf364e5f371e9410f14e5f`.
Its immutable source remains `01baa3a91e1d497aa928b9866ee97de5f0cc29e2`; changing the
installed predecessor does not change this independent source-delta baseline.
Both new source and baseline must have the same845-file inventory. Only these
eight source payload paths can differ; every other payload byte must match:

- visual-search/app.js
- visual-search/hotel-details-v1.js
- visual-search/offer-list-v1.js
- visual-search/index.html
- visual-search/styles.css
- visual-search/mobile-controls-v1.css
- visual-search/migration-manifest.json
- visual-search/asset-size.json

The two added module paths cover only the confirmed final-reference hotel and
flat-offer presentation correction. Their final exact-source diff requires
independent presentation and protected-behavior review: file authorization alone
does not prove unchanged identity, offer selection, lazy loading, filters,
pagination, price or provider protocols. The five existing exact-source checks
still apply. `prototype-search/lead.js`, `visual-search/preview-lead.js`, the tour
controller, supplier/data owners, original logo and fonts remain excluded.

The creator's seven fixed route/ledger boundary adaptations are then applied.
Canonical supplier/price/lead owners, catalogue readers, original brand artwork
and all fonts are not source-delta candidates. The no-secret prepare job performs
PHP renders, negative route checks and retains the exact derived archive and
request. The publisher independently rederives both source and baseline and
compares every retained byte before server access. Payload PHP is never executed
with SSH secrets.

The updater inherits the creator's fixed-root/target inventory, production and
older-preview fingerprints, binding check, lock and shared production concurrency
group. Before activation it writes a durable request and retains the full exact
predecessor directory and owner. Interrupted moves can restore only a verified
predecessor and an unchanged new target owned by this request. Modified backup,
modified target, different owner or unknown state stops without takeover or replay.
A terminal published/rollback/not-activated receipt cannot be replayed. Complete
keeps the predecessor backup for evidence; it does not offer later rollback of a
completed operation.

Existing live readback verifies the new route, served assets, noindex/counter0,
disabled leads, internal PHP and LOCAL-only boundaries and unchanged production
pages. It makes zero supplier searches and sends zero real leads.

Independent HIGH review, green control CI and source visual review precede main
merge and the single publisher command. Preparing this controller grants no
server action or publication. Fixed control paths add no v2 runtime change and
do not modify an existing creator or publisher.

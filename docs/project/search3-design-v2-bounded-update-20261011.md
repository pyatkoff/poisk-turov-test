# Bounded update of the separate Search3 design preview

This is an UPDATE control only for the existing new target
`/_preview/search3-design-v2-candidate/`. It does not recreate or overwrite NEXT,
the original Sites project, any other preview or production.

The exact required installed predecessor is source
`01baa3a91e1d497aa928b9866ee97de5f0cc29e2`, published by run `38173086001`,
with canonical inventory SHA256
`f8e4742f6964dd1237dc86ba27c0d3379a53b57ca4f45d6c16e713f392b43aef`
and a matching published owner. An absent, modified, activated, foreign or
unknown target aborts. After one successful update this control cannot publish
another source over the new predecessor.

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
Both new source and baseline must have the same845-file inventory. Only these
six source payload paths can differ; every other payload byte must match:

- visual-search/app.js
- visual-search/index.html
- visual-search/styles.css
- visual-search/mobile-controls-v1.css
- visual-search/migration-manifest.json
- visual-search/asset-size.json

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

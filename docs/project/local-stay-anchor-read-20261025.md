# LOCAL: retained FIRST CLASS anchor → current hotel stay review

This fixed read implements the owner's continuation of LOCAL #2690/6303191398.
Canonical claim: #4709/6400560144. The source and control package are separate;
preparation and CI are not an actual database read or catalogue population.

## One independent data scope

The only anchor is the already observed Tourvisor tour `25282424201500`.
The actual autosave contract hashes `tourvisor:tour:<tour ID>`, while its hotel
reference hashes `tourvisor:hotel:<legacy ID>`. No hotel ID is inferred from the
tour number, hotel name, older FIRST CLASS snapshot, or Rixos observations.

The retained anchor is historical identity evidence. Its payload bytes, persisted
identity digest, unique hotel tuple and current accepted AnyTour bridge must all
validate. It grants no current price or selection authority. Within the same
read-only transaction the collector reads at most 15,001 rows for that exact
hotel/provider/digest; more than 15,000 is a typed stop. Only complete current
refreshes, active/unexpired offers and existing hotel-scoped concepts contribute.
The output has at most 50 room/meal pair cohorts and 100 review references; it
records the total cohort count so an incomplete selection is explicit.

The original September global frontier run35358490084 failed without retaining
a packet. It is not rerun. The Rixos read6336338880 and #4191 STOP6047119931,
phase3 UNKNOWN and D1 operations remain sealed. This new scope reads no old
operation files and invokes no collector, recovery, matching or writer.

## Exact permanent-carrier admission

Use the existing `/run-int-server-v1` carrier in #4709, with its OWNER-created
comment, trusted main, current release SHA, SSH host verification, operation
reservation and no-replay guards. The fixed mode is `local-stay-anchor-read-v1`;
the sole operation is `int-andromeda-local-stay-firstclass-read-20261025-v1`.
No caller hotel, path, URL, SQL, budget or script arguments are accepted.
Only attempt1 is admitted. The accepted merged release SOURCE and six source
file hashes are literal pins in the control profile and its native CI fixture.
They are not set to a provisional source branch.

No production file is installed. Source files run only in the existing private
operation directory. Network/process functions in PHP are disabled apart from
the existing PDO database connection. The existing database helper loads the
existing site configuration; neither credentials nor configuration contents
appear in the result. Protected config/DB-helper fingerprints and the stock four
public fingerprints must remain unchanged. DB/profile/mapping/schema/supplier,
booking and lead writes/calls are integer zero.

## Result and acceptance

One exclusively created0600/fsynced `local-stay-anchor-read.json` must equal the
single PHP stdout JSON. The controller validates exact schema, source/control/
operation/anchor pins, counts, canonical hashes and the existing packet digest,
then retains the sanitized data in the same stock `result.json` artifact.
The maximum payload is2MiB; full concepts and choices are never silently cut.

`completed_read_only` means the scoped inventory was read; `no_current_cohorts`
means the anchor was proved but no current eligible pair cohort exists. Missing
schema reports the exact allowlisted missing tables. Missing, ambiguous or
invalid anchor/current data and size/row limits retain distinct terminal states.
They are missing-input results, not accepted normalization or a zero dictionary.
An unclassified `read_failed`, malformed receipt, drift or transport failure is
not acceptance and never grants a retry/renamed operation.

The operation creates no concepts or mappings and grants no import/apply
authority. A future reviewed import still requires exact current source evidence,
existing hotel-specific targets, an explicit reviewer dossier and its own guarded
plan/apply admission. Do not fabricate a translation from a raw label.

## Checkpoint

SOURCE4904 is accepted and merged at release516ab32e7ea3f23e0d071343143e207e3d9bbefe.
Original native MySQL57 anchor checks plus29 existing collector checks and Security
passed. The control profile and its existing CI fixture pin this actual merge.
Control CI/review/merge and one actual operation/strict original receipt are still
required before claiming the requested data intake.

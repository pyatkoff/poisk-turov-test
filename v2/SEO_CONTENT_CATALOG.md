# V2 SEO editorial content catalog

This catalog is a controlled editorial source for future public AnyTour destination pages. It is deliberately separate from request/search state and does not publish any URL by itself.

## Record lifecycle

Each editorial record has a stable lowercase `id`, explicit `status` (`draft`, `review`, or `approved`), a clean registered first-party `path`, a supported page `type`, and editorial `data` that must satisfy the existing page contract.

- `draft` — work in progress; never a publication candidate.
- `review` — may be structurally complete, but still requires editorial approval.
- `approved` — eligible only if the same record also passes the structural publishability gate.

Approval never overrides validation. An approved record with thin content, transient search state, an invalid breadcrumb chain, an invalid registry path, or an invalid relationship graph remains blocked.

## Source boundary

Do not generate catalog identity from `$_GET`, current search filters, Tourvisor request parameters, dates, nights, hotel IDs, operator IDs, or arbitrary user input. Search state may only appear inside the existing allowlisted handoff state of an explicitly curated editorial record.

Relationships are also curated: parent and related paths must already exist in the same registry, and graph cycles/unknown references are rejected.

## Publication boundary

`v2_seo_content_candidate_paths()` returns records that are both `approved` and structurally publishable. This output is only a reviewable candidate set. It does not create a route, canonical, sitemap entry, structured data, or indexing permission.

The current `/poisk-turov-test/v2/` search route remains `noindex,follow` with no canonical until the final public mount/URL and publication policy are explicitly chosen.

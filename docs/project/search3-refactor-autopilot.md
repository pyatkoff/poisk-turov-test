Warning: truncated output (original token count: 85135)
Total output lines: 3531

# Search3 technical refactor autopilot

## Current owner direction — product/site work without duplicate layers (2026-09-08)

The active plan is [Search3 product development](search3-product-development-plan.md)
and `AUTOPILOT_STATE.json.current_task`. Useful CSS/JS growth for better visuals,
usability and functionality is explicitly authorized. Historical 12KB/zero-growth
targets or mandatory per-packet savings do not define the current product goal.
Replace superseded implementations in the same packet; no stacked override layers.

**Do not stop after one PR if this invocation has time for the next safe step.**
Finish applicable CI/integration, record evidence, then continue the next product task.
A blocked item or production visual-approval wait does not block independent safe work.
Keep lean preview checks and all protected business/production boundaries.
Older roadmap/checkpoint sections below are historical where superseded here.

## Current product checkpoint — homepage handoff and shared CTA owner #1693/#1694 — 2026-09-09

#1693 keeps both homepage GET handoffs unavailable until country data is complete, preserves country after readiness, and ignores stale country responses after rapid departure changes. #1694 removes567B of generic primary/secondary CTA duplicates from `site-page-v1.css`; `shared-content-primitives-v1.css` is the single later owner and contextual variants remain.

Sources `276fca32150cb5f4d0dff28736293e6bae98670a` / `514d7fa92def05c65a1945266367dc8623c0d142`; checked release `865ee7b6b0f6e7b2c663393fcec08e24f1b6d94a`. Eight Search3 assets delta0; homepage CSS +166B, inline controller +785B, shared page CSS −567B. Security, exact artifact, navigation and standalone content CI passed. Preview remains on source `8382d1bd8543fd3f0efb623673d1b70fa2be823d`; main/production unchanged, real leads0.

Next: `S3_PRODUCT_PAGES_HANDOFF`, bounded audit of remaining country/resort/hot/rb offer transitions for destination/date/night/party loss. Do not restart completed homepage/shared-owner work or separate ANEX work. Audit: `docs/project/search3-shared-control-owner.json`.

## Текущий checkpoint — правдивый фильтр питания и фото #1686/#1687 — 2026-09-09

Два source-пакета завершены последовательно и опубликованы вместе. #1686 переносит ровно два файла owner main#1679: CDN URL вида `//…` нормализуются в HTTPS, опасные URL отклоняются. Source `d75d21d31520142b864d965997f310840de80280`, release `f814829e2c7e397a83191e24836cb599d9db3ef8`. CSS/JS delta0; это подготовка нормализации, не обещание восстановления старых записей БД. Collector/DB не запускались.

#1687 добавляет питание в существующий local-hotel-filter. Карточка, сортировка и раскрытие используют только подходящие исходные туры. Исходные state/event items, continuation count, tour references и payload сохранены; name/category/meal имеют одного владельца `card.hidden`. Неполные данные скрывают и сбрасывают фасет, dirty/reset проверены. Source `8382d1bd8543fd3f0efb623673d1b70fa2be823d`, tree `89830004db93c05bf1edfa19a86a970f61fc2a2b`, checked code release `598269e241391aae34112d835596f56d089fd6d7`. Основа оформления по-прежнему DS2 donor17b674fc; старые слои не возвращены.

Восемь assets **26579→28103 B (+1524)**: CSS18604→18677 (+73), JS7975→9426 (+1451). Shared emitted JS +80, inline delta0. Точный начальный и полный внешний CSS/JS **162859→164463 B (+1604)**; source/exact artifact совпадают. Учтены общие подключения и разделители ответа; HTML/fonts/images/third-party и inline totals не входят. Дополнительный selected-request0, перенос или lazy-loading экономией не называются.

Security34321149428/34321878627 и exact34321149460/34321878643 зелёные. Оба owner validators локально passed; ready-source Security проверил owner direction, его draft-only validator step штатно skipped. Docs checkpoint запускает существующий draft gate. Цена A RO90000/AI120000 против B AI100000, сортировка/раскрытие/сброс/неполные данные/escaping проверены raw+served375/1440; существующие16 result states на8 ширинах, native entry375/1440, source/PHP/path/presentation/isolation guards passed. Первый exact34321465874 был красным на устаревшем `version:2`; теперь assertion требует ровно current apply/clear/project/version3 owner и прежние guards сохранены. Test-only исправление без rebuild. Photo PHP smoke прошёл на точном donor34318224463, release exact выполнил PHP lint; локального PHP нет, повторный local smoke не заявлен.

**Published preview:** source `8382d1bd8543fd3f0efb623673d1b70fa2be823d`, artifact10092207048, digest `sha256:53355759364989ffa18505d07176cb831e45955dcc09f82243cdd4d35bfec9a8`, deploy34322213328. Control#1689 закрыт без merge. Evidence10092314613 подтверждает9 routes, noindex, disabled production leads/counter0, rollback и13 неизменных production fingerprints. Main `41ec8876c5c92a4a9d1b71d7387dcae6b5efee66` не менялся этой работой.

Live1363:79 отелей; AI оставляет25, вместе с SUN VERA —1. У SUN VERA исходные21 тура/BB87235 ₽ заменяются5 подходящими AI от94956 ₽; все5 раскрытых строк с AI, сортировка сохраняет питание. Сброс питания возвращает21 тур/87235 ₽, очистка названия клавишей —79 карточек. Overflow0, заявок0. Осмотрены live toolbar/card и CI375/1440. Live mobile/Safari/полный site+SEO/selected lead path deferred. Известный исторический flight-presentation test с удалённым helper не повторялся и не называется зелёным.

**Следующий S3_PRODUCT_SHARED_SITE:** продолжить после уже завершённого #1658 — ограниченно проверить header/nav/footer/container/secondary-button/link состояния поиска, главной и страны на mobile/desktop. Исправить подтверждённое расхождение в одном текущем DS2-владельце с удалением заменённого правила. Если расхождений нет — S3_PRODUCT_PAGES_HANDOFF, сохранение параметров country/hot→search. Завершённые форма/выдача/рейсы/питание не перезапускать; ANEX отдельно.

Audit: `docs/project/search3-local-meal-facet-product.json`. После двух source PR, исправления CI и общей публикации ресурс текущего прохода отведён на checkpoint; дальнейшая разработка остаётся активной.

## Previous product checkpoint — flight choices and meal labels #1680/#1681 — 2026-09-09

Two source packets completed sequentially and published together. #1680 keeps all original flight radios/indices/prices, showing only the selected choice until expanded in a bounded panel; small sets are unchanged. #1681 shares the canonical meal display label while retaining the exact original lead payload. DS2 donor17b674fc remains the design base; no retired owner/include returned.

Flight source `cdf760bb610b43fdb2805584bd7d96abe20623bb`, release `5f2e12a8358062477f42d8c2dcbccbd882704474`; meal/final source `0754f3dbb52d715c82a1f7212c85ada65fb659d6`, tree `46664ef73ef1ac805693c20d9a0572975af86f6c`, checked code release `1569e01a721d16374c6eb87b70c00a3c1123ebc7`. Docs checkpoint is separate.

Eight assets **25210→26579 B (+1369)**; CSS+462, JS+907; shared emitted JS+183, inline delta0. Correct current initial/full owned external CSS/JS **161307→162859 B (+1552)**, including both shared bundles and response separators. Current route eagerly loads JS `all`; historical phase42418 and old absolute eager/full totals below are superseded. Extra selected request0; exact source/artifact totals agree. No deletion or lazy-loading saving claimed.

Security34318264527/34318776520 and exact34318264521/34318776481 passed.89 options at375/1440 preserve last choice, keyboard arrows, price/summary, phone focus and no extra requests; existing source/PHP/path/presentation/isolation gates pass. Meal shapes11, escaping and complete original leadPayload equality pass. Initial meal exact34318622993 was red on whole-controller hash. The guard now requires one reviewed display expression, reverses only it, and compares the entire controller against the unchanged protected baseline; all other bytes stay locked. No asset rebuild for this test-only repair.

Published exact source0754f3db via artifact10091093868 (`sha256:47039ae87856dd47242b71da1f597ed1e2724ce8d89febf883f59bb4904852c4`), deploy34319053098, control#1682 closed without merge. Evidence10091160692 confirms9 routes, noindex, disabled production leads, counter0, rollback and13 unchanged production fingerprints. Live1363:100 cards, matching meal labels, actual4/1-flight short lists, selected80032 ₽/TK3025+TK3024 matches contact summary and phone focus; overflow0, real leads0. Live contact screenshot and CI375/1440 inspected. Large live supplier list was unavailable: mark live collapse/expand deferred, not passed. Live mobile/physical Safari/full site+SEO/production acceptance deferred. Historical flight-presentation test still targets a retired helper and is red outside the current required source gates; no weakening or false green.

Main independently advanced through owner#1679 hotel-photo fix to41ec8876c5c92a4a9d1b71d7387dcae6b5efee66; this continuation made no main/production write. Before next packet audit whether that fix needs a release port. Next active S3_PRODUCT_USEFUL_FEATURES is a truthful meal facet: preserve original result state/events/continuation count, project matching original tours only for renderer view/price/sort/expand; clear/hide incomplete sets; one local filter owner; protect dirty/reset and lead/API. Concrete implementation and tests: `docs/project/search3-flight-meal-product.json` and the active product plan. Recurring development remains active.

## Previous product checkpoint — DS2 restoration #1675/#1676 — 2026-09-09

Owner requested faster reuse of previous polished components. Donor `17b674fc54fa6b49aaa73379bcbdc35bfccfda28` was adapted into the single current `results-layout.css` owner, replacing its result/toolbar and selected/flight/contact blocks. No retired includes, JS decorators, price, lead or analytics logic returned. Renderer-only `meal.fullName` fallback was repaired.

Results source `c9d1ddd6add646616498ae501dd91be1db96837b`, merge `dc0abe36a9b9844f7078f666a67150b35eb3692f`; next selected source `3e33d4addb9317b78434e92fcbc56851416a0abb`, tree `e5aba6a88f8edd1fe30b8e59dfe450c7742cf3e7`, checked code release `417bf7d2d3024a6c59c3367dc1f1a43a82431e04`. Both packages were completed sequentially in this execution.

Eight assets **15986→19578→25210 raw B (+9224)**. Eager route87339→96599 and full scenario129757→139017 (+9260 including36 B shared JS); selected phase42418, inline unchanged. Intentional functional/visual growth, no transfer-saving claim. The historical owner CSS cap follows this concrete approved replacement (16408 measured,17000 cap); security/path/isolation guards are intact.

Security34315997361/34316326525 and exact34315997382/34316326507 passed. Result16 states/eight widths keep raw/served parity; selected8 states/four widths keep facts, all flight radios, price and phone handoff. Earlier #1675 runs34315595792/34315802644 failed on asynchronous offline catalog-recovery insertion; the fixture now awaits that canonical partial state before unchanged full geometry comparison.

One accumulated publication: exact source3e33d4ad, artifact10090230450 (digest `sha256:85a6a7a9219446c930863307dbef867ed9991bd3476c29519ef999624e112e30`), deploy34316577672, closed-without-merge control#1677. Evidence10090282179;9 routes/noindex/disabled production leads/counter0/rollback/13 unchanged production fingerprints passed. Live1363:100 hotels, real photo220px, CTA50px;89 flight choices; option2 changes67314→84519 ₽, contact summary and phone focus match, return restores100 cards, overflow0, real leads0. Live result/contact screenshots and CI375/1440 captures inspected. Main remains86fc165277a13ae9bef1369659e9b150399f0e35.

Next `S3_PRODUCT_SELECTED_LEAD`: make the observed89-flight set practical without removing alternatives or modifying price/lead contracts; selected view is about12509px tall. Include the current retry CSS selector correction in that coherent packet. Meal-label/facet audit follows; physical Safari/live mobile/full site+SEO+production acceptance remain deferred. Do not restart completed layers or stop recurring development. Audit: `docs/project/search3-ds2-restoration-product.json`.

## Previous product checkpoint — exact local category facet #1672 — 2026-09-09

Source `d69bb12907cf6bb890f9f86565b16a173825d96d`, tree
`c6ab0497bb375ed5effb10ef79908c055a981c6a`; checked release
`b6c1837a817562100355586205366d5bad6c208a`. The single loaded-results filter
owner now combines normalized hotel-name matching with exact category matching
before one `card.hidden` assignment. Category appears only when every current
normalized hotel has a positive category and at least two distinct categories
exist. It survives sort, resets on new search, and hides/resets as soon as a
progressive result set becomes incomplete. No supplier/API/lead call was added and
retired filter owners remain excluded.

The fresh Turkey/Egypt/UAE audit covered234 hotels /843 tours. Category was complete
for220 hotels (94.0%), rating212 (90.6%), sea distance101 (43.2%), and currently
displayed representative meal210 (89.7%). Raw `meal.fullName` was present for all843
tours, but the canonical renderer does not yet normalize that field, so rating,
sea-distance and meal facets remain hidden rather than misleading.

Eight assets are **14677→15986 raw B (+1309)**; eager route
**86030→87339 B**, full scenario **128448→129757 B**, selected phase unchanged at
42418 B. Security `34312454899` and exact runs `34312455035` / `34312459604`
passed. Reused artifact `10088877773`, digest
`sha256:6f905758b370e581b618b16bd80cb9c3b9c01f094c82e5e38cab9ea16fd5fe6b`.
Focused CI covered16 result states at375/760/761/999/1000/1024/1025/1440,
raw/served parity, exact match, sort persistence, incomplete reset/hide, native
entry375/1440, no overflow, external calls0 and leads0.

Deploy `34312777229` through closed-without-merge control #1673 published exact
source only to the isolated preview. Evidence `10088967073`, digest
`sha256:272ec5c425952db66cef1cc7d0d05ef7b35d650488b3ff79c2113c3ab3da8c15`.
All9 routes, noindex, disabled production leads, counter0, rollback and unchanged
production fingerprints passed. Live1363 showed the exact 2/3/4-star facet at25
complete hotels, then its fail-closed disappearance after100 hotels introduced
incomplete category data; overflow0 and real leads0. Main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main/production unchanged.

Next: add `meal.fullName` to the canonical renderer text normalization with focused
source/fixture coverage and no price/API/lead/lifecycle change; then re-audit a
truthful meal facet. Physical Safari/safe-area, full site/SEO/lead journey and owner
production acceptance remain deferred. Audit:
`docs/project/search3-local-category-facet-product.json`.

## Previous product checkpoint — local loaded-hotel filter #1668 — 2026-09-09

Source `0fedbcb5ffb426af4069305d06f446a571dc5254`, tree
`f9f462c682b328786e4b4e5393d3c52f00f8041f`; checked release
`fd0d0cf69b2b3928575c7c68d6afe5f62f1eab56`. One responsive source owner now
filters only already rendered hotel cards by normalized title. It preserves the
query after renderer sort, owns a truthful shown/loaded count and clears on new
search/reset. It does not call the canonical lifecycle, supplier APIs or lead code;
the excluded legacy desktop and mobile filter owners remain excluded.

Eight Search3 assets are **12865→14677 raw B (+1812)**. Because the changed public
asset is eager, initial CSS/JS is **84218→86030 B** and full scenario CSS/JS is
**126636→128448 B**; selected phase remains42418 B. This is disclosed useful
product growth, not a removal or lazy-loading saving.

Security `34308196296` and exact artifact runs `34308196297` / `34308433190`
passed. Artifact `10087423395`, digest
`sha256:07c21112bdbc6107086d4b93a765257314f2cc37c74dc8a0e6c03c58983c476a`.
Browser assertions cover 375/760/761/999/1000/1024/1025/1440, normalized match,
truthful count, sort persistence, clear/reset, raw/served parity and no overflow.

The first connector-only controls did not emit the needed push event, but a later
push-control retry `96d18e15a9fd3afbbd5acb09408756c3ee2736b2` did publish this exact
source through successful deploy `34309036334`. Evidence artifact `10087692143`,
digest `sha256:159d9f76bdf1ae19b78702c9633a314ed2a53efc0e824364500e73fe4e7b3de5`.
This corrects the earlier not-published note. Main/production unchanged, real leads0.
Audit: `docs/project/search3-local-hotel-filter-product.json`.

The next step from this checkpoint was completed by #1672 above.

## Current product checkpoint — truthful current-price calendar #1665 — 2026-09-09

Source `049ceba47ee170699cc7792b050c7af47224c7ee`, tree
`8e80442911a4fc328d870e8fbca4341e7f460f5a`; checked release
`946ee3b7c2c035dd391a66731d2315de740f4bbb`. Search3 now reuses the existing
shared `current-price-calendar-v1` owner. It shows per-day minima only from the
already received result set and states that the data is current search output, not
price history. A reproduced stale-data boundary was fixed: `v2:search-continued`
now refreshes the dates and highlighted minimum.

Eight Search3 assets remain 12865 B. Shared calendar CSS/JS adds 6686 served bytes:
eager route **77532→84218 B**, full scenario **119950→126636 B**; selected phase
remains 42418 B. This is intentional product growth, not a claimed reduction.

Security `34306214649` and exact artifact `34306214593` passed. Artifact
`10086760175`, digest
`sha256:573a3fccd7ed3bb9e5e5618129195d35384fb2d10fa1c635680284c7457a132d`.
Browser coverage at 375/760/761/999/1000/1024/1025/1440 verifies daily minima,
zero-price exclusion, continued refresh, one submit, non-date parameter preservation,
44 px targets and no overflow.

Deploy `34306579270` through non-merged control #1666 published only the isolated
preview. Nine routes returned 200; noindex, disabled production leads, counter0,
rollback and unchanged production fingerprint
`c6f30c6980bb91963601c9f88036ebfb47734c293f05cc3ad2975bd9335134ce`
are recorded by evidence artifact `10086859273`. Live desktop 1363 px loaded
100 hotels and 14 calendar dates; the inspected calendar had no overflow and a
77.39 px minimum date target. Main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; main/production were not changed
by this package, real leads0. Audit:
`docs/project/search3-current-price-calendar-product.json`.

Next: continue `S3_PRODUCT_USEFUL_FEATURES` with a bounded audit of local result
filters. Require a demonstrated user-facing gap; do not restore retired filter layers
wholesale or duplicate the separate ANEX work. Physical Safari and owner production
acceptance remain deferred.

## Previous product checkpoint — canonical site-to-Search3 handoff #1663 — 2026-09-09

Source `e010aa325d110091f244b3abed789038e1f3288c`, tree
`ca14dc6045bf7e1bcf9f004a7517fc2d157be54e`; checked release
`f3b3ef3ed1dac758efeb6f3c56d6d5c456f72134`. The remaining manual country and
hot-tour Search3 URLs now use `v2_seo_search_handoff_url`, the existing allowlisted
and preview-aware owner. Exact query order/values and generic fallback for destinations
without verified IDs are unchanged; no IDs or ANEX behavior were added.

Security `34305077898`, standalone content `34305077886`, preview boundary
`34305077951` and exact artifact `34305077962` passed. Artifact `10086355909`, digest
`sha256:0b328cc7fc5e48a486f3d5cc5cfa726f01d41a8810fc1b7a9f79e0c3d28302fe`.
Search3 CSS/JS and assets are unchanged: eight assets 12865 B, eager route 77532 B,
full scenario 119950 B.

The package was not republished because visible output and URLs are intentionally
identical. Published preview remains exact source `0c87e1ff07b17723b819ae83a15fc3449a693b54`.
Main observed `86fc165277a13ae9bef1369659e9b150399f0e35`; main/production unchanged,
real leads0. Audit: `docs/project/search3-site-handoff-owner.json`.

Next: start `S3_PRODUCT_USEFUL_FEATURES` with a bounded audit of the existing price
calendar and filters; do not duplicate the separate ANEX branch.

## Previous product checkpoint — canonical Search3 site header #1660 — 2026-09-09

Source `0c87e1ff07b17723b819ae83a15fc3449a693b54`, tree
`b56839cd16c3dda7de4dc3bb6c1e0b6939d556d6`; checked release
`984c63891fd786044264071d5dbe5cb71eed4029`. Search3 now loads the canonical
`design-system-v2.css`, `site-header-v2.css` and `site-footer-v1.css` owners once.
Its private header selectors were removed and generic resets were scoped to `.v2-shell`.
Navigation now follows the shared 1024 px breakpoint.

Eight public assets are **13574→12865 raw B (-709)**. The canonical DS2/header add
10494 B to the route closure, so eager CSS/JS is **67747→77532 B** and the full
scenario **110165→119950 B**: an honest **+9785 B** product/ownership improvement;
JS is unchanged.

Security `34303744326` and exact artifact `34303744328` passed. Artifact
`10085907944`, digest
`sha256:ace19d57bd7d16c616dc901333958b6ef7982ffb08f2b54f6dd750616e384c59`.
Browser checks covered mobile/tablet/desktop and both sides of 1024/1025; no overflow
or protected-contract regression. The fixed historical entry baseline remains a
lifecycle/value reference while current-candidate overflow remains strictly rejected.

Deploy `34304080062` published only the isolated preview. Nine routes returned 200
with noindex; production leads are disabled, preview counter0, rollback retained and
production fingerprints unchanged. Live desktop 1363 px showed one visible canonical
header, logo/navigation/actions, no mobile duplicate and no overflow. Main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main/production unchanged, real leads0.
Audit: `docs/project/search3-canonical-header-owner.json`.

Next: `S3_PRODUCT_PAGES_HANDOFF`, beginning with a bounded audit of parameter-preserving
transitions from shared content pages into Search3; do not duplicate the separate ANEX
work. Physical Safari/safe-area and owner production acceptance remain deferred.

## Previous product checkpoint — shared content control ownership #1658 — 2026-09-09

Source `d68c0989e949afde8fcb1051c3fc9ab9716a2907`, tree
`872c096e7eb8d0baee9b6dd478747b2914910e65`; checked release
`6304d128429e999e97ba1dbdc9fc4c3933f7574c`. The current blue `.sp-primary`,
blue hover and 44 px `.sp-office-phone`/`.sp-contact-phone` target geometry now
live once in `shared-content-primitives-v1.css`. The later matching corrections were
removed from `site-coherence-v1.css`; the standalone guard requires the canonical
rules and rejects their return to the late layer. Computed visuals are intentionally
unchanged. Homepage search, Search3 and protected price/API/lead/analytics contracts
are untouched.

The two shared CSS files are **17699→17530 raw B (-169)**; content pages load both.
Homepage loads only the reduced coherence file and removes 332 B; it does not use the
moved content selectors. Search3 eight assets remain 13574 B and JS is unchanged.

Security `34301707589`, standalone route validation `34301712871` and exact artifact
`34301712792` passed. Artifact `10085158687`, digest
`sha256:5fda4c63be5b5f2192ceddb928c8022a73b8fed0d9596948acbf2543d6a65f8b`.
The exact job correctly skipped Search3 browser geometry because no Search3 source
changed; CI PHP lint/rendering covered contacts, how-to-buy, early-booking, hot,
country catalog and representative country pages. Local PHP was unavailable.

This behavior-preserving ownership package was not republished: the isolated preview
remains exact source `ef8ce1d8e967ffc6945adbc75b4a726906503593`. Main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main and production unchanged, real
leads0. Rollback: revert #1658 independently. Audit:
`docs/project/search3-shared-control-owner.json`.

Next: continue `S3_PRODUCT_SHARED_SITE` with a bounded audit of shared header,
navigation, footer, container, secondary-button and link states. Consolidate only a
confirmed duplicate owner or mismatch; otherwise advance to `S3_PRODUCT_PAGES_HANDOFF`.

## Previous product checkpoint — selected flight and lead handoff #1655 — 2026-09-09

Exact source `ef8ce1d8e967ffc6945adbc75b4a726906503593`, tree
`6e0eb20a8424c4be507c2ef484cde619bd6e2884`; checked release
`d6b71962c2a1fa47de2ffeab9158e2f3133f2d28`. All six flight variants remain
selectable while only the current choice expands route, baggage and fuel details.
Search3 has one visible handoff CTA; the existing lead summary receives the selected
flight number/time and the form is a distinct next step. The authoritative selected
total, retry, return focus, Tourvisor/API and lead transport are unchanged. No
parallel selected controller or override layer was added.

Eight public assets are **12007→13574 raw B (+1567)**: CSS +905 B, JS +662 B.
The eager initial external CSS/JS set is **66180→67747 B** and the full scenario is
**108598→110165 B**; selected phase remains 42418 B. This is intentional product
growth, not claimed as deletion, movement or lazy-loading savings.

Security `34299852388` and exact artifact `34299852380` passed. Reuse artifact
`10084543925`, digest
`sha256:1d8694ae03af183dca64b4d29208c7c2fd1f47f02e0010507cb259b29fb22873`,
archive `ec2e700492af02b90c8bac1a288f65fe3068c788070012c4525cd829ee743bd8`,
manifest `403658e82190d0e173ebe9558050f0d264cd1faa0b5de7582e3f3aea45c39937`,
payload `a0006595882e3f37ac55e42d6b65fe4a79fe1918c35d1659f4898c0386d9cb5d`,
717 files. Exact Chromium covered detail/lead at375/760/1000/1440, six visible
variants, variant switching, one visible Search3 handoff, lead summary `AB123 09:30`,
retry/return and no horizontal overflow; external calls0 and real leads0. Initial
exact runs correctly rejected protected-owner edits; both files were restored to
their allowlisted hashes and no validator was weakened.

**Published preview now equals this exact source.** One-shot control #1656,
SHA `2e3b8d980b2da029d05ace1533cdd39da08cf74b`, deploy `34300274732` reused
the artifact and passed provenance, isolation, noindex, disabled production lead,
preview counter0, rollback and unchanged production-fingerprint guards. Four-width
screenshots were inspected: the selected option, one handoff CTA, summary and form
are readable without clipping or overlap. Synthetic missing airline/fuel/route data
and dense but unclipped375 detail fields remain fixture/device caveats. Physical
Safari, real-provider long-flight copy, full lead/site/SEO journey and owner visual
acceptance remain deferred. Main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main and production unchanged.
Rollback: revert #1655 for code or restore the retained preview backup from deploy
run34300274732. Audit: `docs/project/search3-selected-lead-product.json`.

Next: `S3_PRODUCT_SHARED_SITE`. Fold the approved blue `.sp-primary` and 44 px
`.sp-office-phone` rules into `shared-content-primitives-v1.css`, remove the
superseded `site-coherence-v1.css` overrides in the same package and verify
representative shared routes at mobile and desktop widths. Do not touch Search3
business contracts or duplicate the separate ANEX work.

## Previous product checkpoint — clear results and truthful states #1652 — 2026-09-09

Exact source `867bf3e899a09fbe48c09774dcd1a7568a258e65`, checked release
`4554e34b4cfed32d290a82b0ae05f3adaa828c80`, publish run `34297374511`.
The current renderer and lifecycle own one card hierarchy, one whole-tour total and
truthful loading/progress/empty/error/retry states. Eight assets 10616→12007 B;
details and rollback remain in `docs/project/search3-results-clarity-product.json`.

## Previous product checkpoint — grouped form and readable summary #1645 — 2026-09-09

Exact source `d81f1dc53fe2a4fe1a05b9c32434858114924164`, tree
`fcb6beb329c39afbbc013efe5e5eada90a674a1d`; checked release
`b7f1500f261c2757c0bb5ea7c01e5f3080174bc2`. Eight canonical fields are now
four labelled native groups (direction, dates, duration and tourists). Search3
loads the canonical shared footer as its sole footer owner; four local footer
overrides were removed. Current results layout owns selected/lead spacing.
Lead summary reads direct price/flight values only, and the existing summary
controller clears selected state before canonical return focus.

Eight public assets are **9073→10616 raw B (+1543)**: CSS 5448→6822 B and JS
3625→3794 B. The complete external CSS/JS file set is **113239→125065 B
(+11826)**: +1543 B in the eight Search3 paths, the existing 10176 B shared
footer now used on this route, and +107 B shared runtime. `v2/index.php` adds
565 B separately. This is an intentional product improvement, not claimed as
deletion or lazy-loading savings; no duplicate presentation/controller owner
was added.

Security `34294044806` and exact artifact `34294044809` passed. Reuse artifact
`10082470390`, digest
`sha256:c7bc451e305b99257d0a6230e40867dcd739540438dccb14b09c087c198be3fc`,
archive `247263406acbcbcc7c5baa02b7b2043e8e7a00bac49ccaac128f4488fb04d276`,
manifest `56640d152fd6bf15394bb02401c3ce966991c179ab58261816fe66b6b18a4de5`,
payload `98e6f87ad122d0b8848a34a739808074f4312ab4a9acdaa2cb748cb68cad7c48`,
717 files. Exact evidence covered entry and selected states at 375/1440 and 12
results/selected/return states; real leads0.

**Published preview now equals this exact source.** One-shot control #1650,
SHA `58b98c2b3da0dee79512669055a03b3630727050`, deploy `34294751418` succeeded
without rebuilding. Evidence `10082695312`, digest
`sha256:b741e7d143cf9eb06135539f8b808f4f23bd27cc0a8d80ce62cfcf8a1965ddd9`.
Live 1348 px smoke confirmed four grouped fieldsets, canonical footer, no
horizontal overflow, noindex and disabled production lead action; no page
console error and no lead submission. Physical Safari/safe-area, owner visual
acceptance and full lead/site/SEO journey remain deferred. Main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; main and production unchanged.
Rollback: revert #1645 for code or restore the retained preview backup from the
deploy run. Audit: `docs/project/search3-form-readability-product.json`.

Next: `S3_PRODUCT_RESULTS_CLARITY`. Use the current renderer/layout owners to
make the hierarchy photo → name/location → essential facts → authoritative
total price → action, remove repeated facts, and clarify progress/empty/error/
retry states. Preserve API, URL, price, lead and analytics contracts; do not add
a parallel results renderer or override layer.

## Last checked code checkpoint — results-top bridge retirement #1643 — 2026-09-08

Exact source `937c343d00b379414074ee5632298543054a5935`, tree
`dd53455b1a6e5d4e394bdf9a0ad7d3ba48a00cf7`; checked release
`3003788b8096d7eae161ed8ad98769c7e0da3a42`. The whole
`src/search3/behavior/results-top.js` state bridge is retired. Canonical renderer
and lifecycle still own results/tools; the native form owner keeps only
`aria-busy`, edit/focus and editing reset. Existing `search3-selected-open` plus
native `:has()` replace duplicate result/selected/return state listeners.

Eight public assets are **10097→9073 raw B (−1024, −10.14%)**. Results JS is
4720→3625 B and results CSS4573→4644 B. The retired bridge contributed1556 built
bytes; compact JS salvage adds461 B and CSS salvage71 B, so the net reduction is
real and no code moved to shared assets. All eight public URLs remain.

Security `34290099786` and exact artifact `34290099807` passed. Reuse artifact
`10081029313`, digest
`sha256:54425f1aaeee6158d43ee35b13f42c08251499af9f554154eb7f4f92c6bfc964`,
archive `b9f8b43e29a0e7acbef5ab125566475ed82155733544586c0b8eaaf6ec788ac4`,
717 files. Results evidence `10081028950`, digest
`sha256:aaa6fe3e506b9f069d27a142fd9fec7fcddf7cf82c747cf4f6435a8033a2e396`;
entry evidence `10081028561`, digest
`sha256:69650889f2527bb8b4ce73996b162b2b467b90b08df94ed449a3853be8987abf`.

Exact Chromium passed12 results states at375/760/761/999/1000/1440 with
raw/served parity, native header, edit and empty state, plus native entry at
375/1440. External calls0 and real leads0. The workflow path guard did not run
selected geometry, so current-source selected/return browser and live/manual
screenshots are deferred, not called green. Physical Safari/safe-area, owner
visual acceptance and the full lead/site/SEO journey also remain deferred.

**Checked release is not published preview.** Preview remains source
`89a5a8c37b3c5e5473f8f0d99b9ce19c4b51cbf1`; main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; main and production are unchanged.
Rollback: revert #1643. Audit:
`docs/project/search3-results-top-bridge-retirement.json`.

Next: resume the owner roadmap's coherent native-form grouping/footer/lead-summary
readability package. Useful current-owner growth is allowed; remove superseded
rules in the same package, do not restore `results-top.js` or stack another state
bridge, and keep all protected business contracts.

## Historical checkpoint — eager selected runtime retirement #1640 — 2026-09-08

Exact source `d123a1d8b7cae29c39d7c2678445238d3763ae96`, tree
`8d76145840c17c6cb9ffbf2051fe80719423ee45`; checked release
`a28d62772bcb89ffc7368de18002b524a961329e`. #1640 removes the whole
`src/search3/behavior/selected-runtime.js` lazy proxy. Search3 now requests the
existing compact shared runtime once; canonical tour, flight recovery, price and
lead owners remain byte-identical and appear once.

Eight public assets are **11984→10097 raw B (−1887, −15.75%)**. Results JS is
6607→4720 B; the other seven public assets are unchanged and all eight URLs remain.
Complete loaded files are **116150→114263 B (−1887)**, so this is deletion rather
than movement. Record the latency tradeoff explicitly: the former42310 B selected
phase is eager, making initial files **73840→114263 B (+40423)** while selection no
longer makes a second runtime request or replays the click.

Security `34287755060`, navigation `34287754978` and exact artifact
`34287754975` passed on the final source. Reuse artifact `10080169508`, digest
`sha256:4eef8d02507356d150edc6146455bcd0450b75b24687b9c3f75c6b1bb8e26de2`,
archive `8395ecc1a2ba5639ced1a3ad790c6e91be80e2fb359207f8b61e0390d572b922`,
717 files. Selected evidence `10080169022`, digest
`sha256:a987a3cb2e4522f26870dbc2e16b4a557ec968d8f07d7cd0ec07797b338572d9`.

Exact Chromium passed8 selected states at375/760/1000/1440 and direct/reset/
tour-retry scenarios with zero selected-phase requests, native lead handoff,
decimal prices, phone validation, stale-lead blocking and return focus. Empty-flight
recovery and localized tradeoffs passed. External calls were blocked; real leads0.

**Checked release is not published preview.** Preview remains exact source
`89a5a8c37b3c5e5473f8f0d99b9ce19c4b51cbf1`; #1640 was not published because it
does not introduce a visual package. Main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; main and production are unchanged.
Current-source live/manual screenshots, physical Safari/safe-area, owner visual
acceptance and full lead/site/SEO journey remain deferred. Rollback: revert #1640.
Audit: `docs/project/search3-eager-selected-runtime-retirement.json`.

Next: audit a new coarse results-state boundary. Proceed only for removal of a
complete owner with material eight-asset savings; do not restore retired layers,
micro-trim statements or remove controller, price, URL/payload, Tourvisor/API,
lead transport/mapping, analytics, logo or browser contracts.

## Historical checkpoint — compact native UI #1635 and selected entry repair #1637 — 2026-09-08

Exact final source `89a5a8c37b3c5e5473f8f0d99b9ce19c4b51cbf1`, tree `833623851f9411e188dfe6915dd04f2b4211c675`;
checked code release `abc6e4f255709572b731b0db1ffb0d94a4b0909e`. Initial checkpoint
`a728832d300a01f61d92bb194c243117bc63a423`; #1633 retirement is preserved.

#1635 source `66cb0164b2523be69b1eee821dba4efb29d69265`, release `66c77795ea80c786ab89ec7a7275381e50f1822a`:
eight **8867→11975 B (+3108 CSS)**. The existing header, native form, cards,
selected facts/flights and lead fields get readable compact presentation.
#1637 fixes entry hero/form reappearing above selected content using the existing
selected-state class: **11975→11984 B (+9 CSS)**. Whole-pass growth **+3117 B**
is product repair, not deletion. JavaScript remains **6607 B**. Only current
results-layout/entry owners change; retired card CSS stays1B, selected CSS stays
exact50B and selected JS stays0B. All8 paths and original business owners remain.

Primary Security `34285887617`/exact `34285887635`
passed. Final Security `34286700375`/exact `34286700356` passed.
Intermediate primary runs34284706152/34284889596/34285260425/34285553146 were RED:
CSS-boundary/native marker requirements, fixture catalog/toggle timing and old
all-width mobile-menu assumptions. Source/test corrections are recorded; existing
source/PHP/path/presentation/isolation guards were not weakened. Entry values and
lifecycle still compare exactly; intentional dimensions are recorded with no-overflow,
16px/44px checks. Desktop navigation and native mobile open/close are both tested.
Final focused checks: Selected8 at375/760/1000/1440 including both duplicate entry surfaces hidden; lazy3 with native handoff/return focus; entry30 exact values/lifecycle; current results12 raw/served parity at375/760/761/999/1000/1440; native entry375/1440; empty-flight retry/recovery, phone/stale-lead guards and decimal tradeoffs. Real leads0..

Reuse exact artifact `10079777903`, digest `sha256:881720d851ead0efaee151cf3b428985c56949dc9c4cf36fe4b8623494eaf494`;
archive `e0a2b42cb58b37cc79ec2f1d7c6b65be9916e5d2fda9233069e3f5a3493b0f35`, manifest `41c8fae6568a30363d6198a9ecdd426a172710ff2c94fca260631ba429117df3`,
payload `921769f1be583978e4bbffb7013de7cafbf24124283ff3b4f62445154b3c68cc`, 717 files.
**Published preview** source `89a5a8c37b3c5e5473f8f0d99b9ce19c4b51cbf1` via control
PR#1638, deploy `34286947471`. Earlier #1636 deployed the
primary package and the short live check found the selected-entry duplication;
it is fixed by the final source above. Both control PRs closed without merge.
No rebuild for publication or docs. Noindex, counter0, disabled production leads,
rollback and13 identical production fingerprints retained. Main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; this lane did not modify main or production.

Actual primary checks: {"width": 1363, "entry": "Readable native form; Moscow/Turkey catalogs loaded; both original /images/logo.svg images complete. Screenshot inspected.", "results": "One live search,100 hotels; first ARES CITY61883RUB, photo/facts/CTA bounded and readable. Screenshot inspected.", "selected": "ARES CITY selected,61883RUB, flight loaded; phone CTA focuses phone; returning restores100 hotels/focus. No horizontal overflow.", "issue": "Entry hero/form reappear above selected tour due existing results-state transition; confirmed and corrected next in#1637.", "lead_submitted": false}.
Actual final checks: {"width": 1363, "entry": "Native form visible on fresh reload; Turkey catalog loaded. Exact versioned CSS path58eadb7eba76eaa7 served.", "results": "One live search completed with96 hotels; first ANAHTAR APART65487RUB. No real leads.", "selected": "ANAHTAR APART selected; search3-selected-open set, both hero and search form hidden; no horizontal overflow. Canonical selected price65701RUB,214RUB flight adjustment; all offered flight options remain available. Live screenshot inspected.", "return_to_results": "96 hotels restored, selected hidden; edit-search restores form and focuses from. All8 primary values retained; no overflow.", "lead_submitted": false, "scope": "Short targeted live correction check only. Previous package already inspected original logo, form/cards and phone focus. No extra lead/site/SEO traversal."}.
Deferred: physical Safari/safe-area, owner visual/design acceptance for production, full lead/site/SEO/responsive journey, Group long native flight lists and space footer/lead-summary text in a future coherent UI package; not a new correctness regression.. Audit: `docs/project/search3-compact-native-presentation.json`.
Rollback: revert #1637/#1635 independently or restore retained preview backup.

Next: The compact native presentation and selected-entry duplication repair are published. Preserve the current11984B eight-asset total and all retired boundaries. Next coherent UI package: group the eight primary search fields into destination/date/nights/party pairs, and fix existing footer/lead-summary text spacing within the same12KB budget. Batch these visible refinements; do not create printer/micro-trim PRs or restore legacy skins. Keep canonical values, price arithmetic, API/lead transport/mapping/analytics and original logo. Production remains gated on explicit owner visual acceptance.

## Historical checkpoint — selected-flow adapter retirement #1633 — 2026-09-08

Exact source `c1990a4cda2bb3ab6623e18b4fd2c5a17be6884b`, tree
`8f8cfa872dc552cc6ecb17850b2c58a410daccb1`; checked release
`df923cf2035c02d75c819c22fbeb15c5b803dcb6`. The whole selected-flow adapter and
its private flight-fallback source are retired. The public compatibility JS path
remains and is now an empty 0 B asset. Canonical flight-empty recovery is loaded
once in the selected phase; canonical price and lead transport owners are unchanged.

Eight public assets are **11814→8867 raw B (−2947, −24.94%)**. Selected JS is
4483→0 B; the current results JS owner is 5120→6607 B because it now contains the
small remaining selected state/native-lead glue. Selected CSS is 1→50 B for the
only necessary bounded-image rule. This is a real net reduction, not a source move.
All eight URLs remain.

Security `34281178946` and exact artifact `34281178998` passed. Reuse artifact
`10077692756`, digest
`sha256:dbf96e19360253fc1b43ddcc9be3076c7958b4feb595a4e366c09c26d13a2af5`,
archive `883d649d017577c67a1bfe2e4cfb8b7d4a0b2ee485c9e9fc8763f8ea4aa56fe7`;
717 files, 769513 B. Selected evidence `10077690903`, digest
`sha256:2601c6f380f079e9a03e05101cf5f7f1cd976213af876ef49f1cf31d19302ec9`.

Actual isolated Chromium passed 8 selected states at 375/760/1000/1440, three
lazy retry/reset/tour-retry scenarios, 30 entry states at
375/700/701/760/761/1440, native lead handoff, empty-flight recovery, return focus,
phone validation and decimal price/tradeoff behavior. External requests were
blocked and real leads were 0. Exact CI found and the source repaired a 375 px
remote-image overflow, a missing CTA after empty-flight subtree replacement and
a repeated same-value mutation loop. No failed state is called green.

**Checked release is not published preview.** The isolated preview still contains
source `a960efd5ed65143bc111554689a5deb9e52a3f69`; this package was not published.
No current-source live or manual screenshot claim is made. Main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main and production were unchanged.
Physical Safari/safe-area, owner visual/design acceptance and the full
lead/site/SEO/responsive journey remain deferred. Rollback: revert #1633
independently. Audit: `docs/project/search3-selected-flow-adapter-retirement.json`.

Next: no optional whole public owner larger than 2 KB remains. Do not return to
micro-trims. A successor must remove a real current architectural boundary, likely
the selected lazy proxy or a results-state owner, and show an actual reduction in
the eight built assets. Preserve price arithmetic, URL/payload, Tourvisor/API,
lead transport/mapping, analytics and browser contracts; source movement does not count.

## Historical checkpoint — native results repair #1629 and exact preview — 2026-09-08

Exact combined source `a960efd5ed65143bc111554689a5deb9e52a3f69`, tree `9ef663d955ad9750f17ac53c13fd9f832480c142`;
checked code release `b03edfbb9ab50f27e7591a7ffe6af0f81485c0b6`. This includes independently integrated
#1625 late lead owner, #1626 donor-skin retirement and #1629 result repair.

#1629: eight **15426→11814 B (−3612)**; CSS **5490→1878 B**;
initial file payload **76733→73121 B**; complete **116963→113351 B**.
Whole orphan result-summary, stale-banner, desktop-tour decoration and old sidebar
offset families are removed. Native edit-search remains, legacy summary is retained.
Confirmed 375 px overflow is fixed with bounded images and full-width results.
JS/shared originals are unchanged. HTTP bodies including shared boundaries:
initial 73670 B, selected 40408 B, complete 114078 B. All eight URLs remain.

Earlier in this pass #1625 removed 769 B overall and deferred 14626 B of the exact
lead guard: initial 95058→79663 B. Protected phone/bootstrap/race behavior passed.
Total new deletion in these two packages is 4381 B; parallel #1626's 2930 B is separate.

Security `34273162448`, exact artifact `34273162444` and navigation
`34273162381` passed on this final source. Actual Chromium results:
12 states at 375/760/761/999/1000/1440, raw/served parity, preserved form parameters,
edit-search, native header, sorting, tour actions, decimal prices and no overflow.
Native entry 375/1440 also passed. First exact 34272896628 was RED for actual 375 px
overflow, fixed in source; no guard was weakened. Only the necessary corrective
build was added. Reuse artifact `10074666815`, digest `sha256:36e0d1bf58eb20648dcfcfdd1db177d6198b30531fed760f7e6eb9b5aecd5102`,
archive `27af8cd4e647a91da3916b02166ebeab0f1feb99efe8ca3d5a8f696b8d3654b2`. No release/docs rebuild.

**Published preview source:** `a960efd5ed65143bc111554689a5deb9e52a3f69`.
Control #1630, deploy `34273519277` (success);
exact source/artifact pins, noindex, lead delivery disabled, Metrika 0,
production fingerprints unchanged and atomic rollback backup retained.
Previous publication was #1628 / run 34272077289 / source fda61fbd; its exact evidence
is retained in `search3-late-lead-review-retirement.json`.
Actual live checks at 1348 px: one Moscow/Turkey search returned 100 hotels;
the placeholder summary is absent, one native edit action remains, horizontal
overflow is absent, and editing restores the form with all values preserved and
focus on departure. Actual results screenshot inspected. Selection, flights and
phone focus passed on the preceding #1625 publication in this pass; that unchanged
JS journey was not repeated after the CSS repair. No real lead was submitted.
This is isolated experimental presentation; design acceptance is not green.
Deferred: physical Safari/safe-area, owner visual/design acceptance, full lead/site/SEO/responsive journey. Main observed `86fc165277a13ae9bef1369659e9b150399f0e35`; this lane did not
change main or production. Rollback: revert #1629 independently; preview uses its
owned atomic backup. Audit: `docs/project/search3-results-native-retirement.json`.

Next: The confirmed empty-summary/mobile-overflow repair is complete. Do not repeat the retired layers or create a 31 B printer micro-PR. Read-only audit of all 17 shared owners found only 31 B AST-identical further compaction (101537→101506); keep existing build contract and protected originals. Next useful work is a coherent native results/selected-form presentation pass guided by the published preview, preserving the 11814 B eight-asset budget where practical, all selection/edit/phone/retry behavior and protected price/API/lead/analytics contracts. Separate initial loading from actual deletion. Main/production still need explicit owner visual approval.

## Historical checkpoint — donor base skin retirement #1626 — 2026-09-08

Exact source `98aed98b0ac90575be43bc22f58e8c2fcf4071b5`, tree
`9fa329f9a886c6bade96284e6b908029ecefb701`; checked release
`69fa28aa1224026cf05e6c2b4b5993236481396e`. Independent #1625 merged first as
`c423da23deafb6782eb0d1994c42d6e68d5b7941`; that is the final integration base.

The whole preview-era `base.css` donor skin was deleted with no rule movement.
Eight public assets are **18356→15426 raw B (−2930)**; results-filter CSS
**8420→5490 B**; initial route **79663→76733 B**; complete route
**119893→116963 B**. The independent #1625 savings are not counted here. All
eight paths and current entry/results/selected owners remain.

Security `34271552421` and exact artifact `34271552328` passed. Reuse artifact
`10074024154`, digest
`sha256:2fe82654ae24ade1baf27b395e2f7e68189e7d0c08de7d3d2c509ad97611e003`,
archive `d5a27e56c8a4e4ad650930cbf48c0dd7a428f4950dd764e6593ff1e90fdee5bf`.
Source build, protected closures, PHP/path/presentation/isolation and 9 local preview
routes passed; real leads0. The first exact `34271267151` was red because its source
assertion required the deleted filename. The final guard requires it to remain absent,
pins the smaller output and retains native/results owner assertions.

No actual browser geometry ran for this design reset because the workflow's focused
geometry path selectors do not include the retired donor owner. This is deferred,
not green. Manual screenshots, physical Safari/safe-area, live current-source preview,
owner acceptance and full lead/responsive/site/SEO journey are also deferred.

**Checked release is not published preview.** Preview stays
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; main and production unchanged.
Revert `69fa28aa` for rollback. Audit: `docs/project/search3-base-skin-retirement.json`.

The exact artifact is for #1626 on its original source base; the clean final merge
also contains independently green #1625 and was not rebuilt.

Next: do not micro-trim the remaining 15426 B. Remaining public owners are results
layout5490 B, selected flow4483 B, result-state/lazy/native-lead JS5120 B and native
entry331 B. Retire another owner only as a coarse product/design boundary with real
eight-asset savings; do not count source movement or deferred loading.

## Historical checkpoint — native booking handoff #1623 — 2026-09-08

Exact source `989e4a90048f76812021b221706fa1635a4884a2`, tree
`c1642263d5fee92371b14d1a9c94d6692ce1e87e`; checked release
`d5ff8203f47f7c3ccb48d9c5465f6549b08bc861`. Fresh base was
`4b92586a8126b6b93f56b2c0463c8347a867867f`.

The eight public assets are **25391→19125 raw B (−6266)**. Initial route file
payload is **101324→95058 B (−6266)** and complete route file payload is
**126928→120662 B (−6266)**. This is deletion, not deferred loading or a source
move: the whole duplicate booking-summary card, duplicate fact formatter and
intermediate review→summary→lead presentation stage are gone. The selected-tour
CTA now hands off directly to the canonical native lead form.

All eight paths, protected TourController, pending/confirmed/decimal price,
selected lazy retry/reset, return focus, canonical facts/FormData/lead transport,
URL/payload/Tourvisor/API and analytics remain. Security `34269579797` and exact
artifact `34269579936` passed. Reuse artifact `10073289828`, digest
`sha256:2def434e159e246459b6da0ff5761b8cfb99ec80a768a66ce9becd4b8303fb33`,
archive `4391a159f75ad413926498186cab6ac02619090ed72dcaf2f7e8fbc381d75016`.
Selected evidence `10073289344`, digest
`sha256:9963f0d4fb963ac1cf08a5fb8e6ef563cc04e8d89d373e75f04ed24de4088cbb`.

Actual isolated Chromium passed 8 selected states at 375/760/1000/1440, direct
native lead handoff, lazy download retry, reset cancellation, one tour and one
flight request on the successful selection, decimal price `150001.2`, fallback
with zero lead requests and return focus. Real leads0. The earlier old-base #1621
and duplicate red runs on `e9d9ad35` are retained as red fixture history, not
called green. The final exact head fixed only the stale booking-summary fixture.

**Checked release is not published preview.** Preview remains
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`86fc165277a13ae9bef1369659e9b150399f0e35`; neither main nor production changed.
Manual screenshots, live current-source preview, physical Safari/safe-area, owner
acceptance and the full lead/responsive/site/SEO journey are deferred. Revert
`d5ff8203` for rollback. Audit: `docs/project/search3-native-booking-handoff.json`.

Next: audit only coarse remaining public owners. Count a successor only if it
removes an entire optional surface or saves at least 2KB in the eight real assets
while preserving protected lead, price, URL, payload, Tourvisor/API and analytics.
Do not count late loading or source movement as deletion; do not start a micro-PR.

## Historical checkpoint — on-demand selected runtime #1618 — 2026-09-08

Exact combined source `b25ae3e8c72e300bbaf877a9df8010d7f3f108fe`, tree `9ee238b4e9745cb95311d0d09add462c6bdee476`;
checked code release `5bb8595d870906c4eb67b07de3d86a26ff71ec1b`. Concurrent #1619 merged first as
`fa9917bf35b88f548bbea44baf26785dc5a1aa93`; retain its native-entry retirement
and shared compaction. Our overlapping core compaction was superseded, not counted twice.

Relative to that fresh base: **initial CSS/JS file payload 125043→101324 B (−23719)**.
The selected-tour/lead transport and flight-price closure is 25604 B and loads only
after selection through the existing same-origin bundle endpoint. It is deferred,
not deleted. Complete file payload **125043→126928 B (+1885)**; eight public assets
**23506→25391 B (+1885)**; shared payload 101537 B unchanged. Added 1550 B loader,
208 B confirmed return-visibility/focus repair and 127 B native-details close-on-search
repair after the concurrent entry retirement. HTTP shared boundaries add 727 B
overall, 590 B initially and 137 B to the selected request; keep this accounting separate.

All eight public paths remain. Initial search/catalog/URL/runtime/analytics and
lead race/context/fetch wrappers stay eager. First real action replays once after
the canonical controller and both price owners are ready. Failed download retries
only on another action; search reset cancels a pending selection. Current results
owner reveals results before the unchanged controller chooses its return focus.
No canonical protected source, price arithmetic, payload or lead transport changed.

Final Security 34268129179, exact 34268129387, boundary
34268129237 and navigation 34268129190 passed.
Reuse artifact 10072720854, digest `sha256:1936f778595c200da8251133ee38f2b10771fb9ffa8c59f4507ef04b9d7c4adf`;
archive `831dc290534fab55e69fa49f7d8c3ccd67594b93af9299c0b26c7517ec35003e`. No release/docs rebuild.
Actual isolated Chromium passed first-click/retry/reset/decimal/return-focus,
12 selected detail/review/lead states, 30 native entry lifecycle states, 12 raw/served
results states, native FormData 375/1440 and retry/fallback/decimal checks. Real leads 0,
external requests blocked. Earlier red harness/closure checks and confirmed UI
failures remain red in the audit; the earlier green pre-integration artifact was superseded.

**Checked release is not published preview.** Preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`;
main observed `86fc165277a13ae9bef1369659e9b150399f0e35`; neither main nor production
was changed by this work. No new live fingerprint capture or manual visual acceptance
is claimed. Safari/safe-area, manual screenshots, live current-source preview and
owner acceptance are deferred. Revert #1618 for rollback while preserving #1619.
Audit: `docs/project/search3-compact-lazy-runtime.json`.

Next: First finish sequential integration with the active booking retirement #1621. Then assess a coarse late-load boundary for lead-form-guard-v1.js (14626 B at this source). Booking/review retirement is already owned by open #1621; integrate it sequentially and adapt lazy fixture references to its native lead handoff, without duplicating its edits. Keep lead-ui-race-guard and lead-search-context eager: stale-event protection and fetch-wrapper order must precede presentation. The form guard needs a readyState-aware bootstrap and all listeners installed before first-click replay; verify decimal/pending/confirmed ordering before integrating. Do not count deferral as deletion or repeat #1619 compaction.

## Historical checkpoint — native entry and shared-owner compaction #1619 — 2026-09-08

Exact source `efd0368b99f91533cb5538d433244a71fb303c25`, checked release
`fa9917bf35b88f548bbea44baf26785dc5a1aa93`; base was
`e4fae092af9fa9d1607cdc141b52cc270ab00ca2`. Eight public assets are
**33355 → 23506 raw B (−9849)**. Search3 shared JavaScript is
**106735 → 101537 B (−5198)**, so complete loaded CSS/JS is
**140090 → 125043 B (−15047)**.

The entire client entry-control projection is retired. Canonical server markup,
catalogs and lifecycle now directly own the visible form, meal loading, child
ages, URL hydration and `FormData`. Only the compatibility ready marker and compact
44px/Safari-safe native control rules remain. Exact SHA-locked build normalization
serves compact Search3-only representations of `tour-controller-v4.js` and
`catalogs-v2.js`; their canonical protected sources and the complete legacy route
remain byte-identical.

Security `34265965821` and exact artifact `34265965943` passed on the first head.
Reuse artifact `10071852547`, digest
`sha256:5553468af7d54aa90ea04b50a8187e2af009d14bec27f0fdd8c8f453de59aa94`.
Actual isolated Chromium checked native entry at 375/1440 and 12 current results
states at 375/760/761/999/1000/1440 with raw/served parity, external calls0 and
leads0. Entry evidence `10071851661`, digest
`sha256:837d9d430c2fbf81d7a50446ec83e80cb912f1e5fa7829f481211848f174d12a`;
results evidence `10071852147`, digest
`sha256:10d6c05a7a5a641ac81c6fffb6fdda82a37c7a75e56c76284739cdc872e30ca2`.
Manual screenshot inspection was not performed.

**Checked release is not published preview.** Preview remains
`c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main observed
`47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production unchanged. Owner visual
acceptance, physical Safari/safe-area, live current-source preview and the full
lead/responsive/site/SEO journey remain deferred. Revert merge `fa9917bf` for
rollback. Audit: `docs/project/search3-native-entry-shared-compaction.json`.

Next: assess one coarse retirement of booking-summary/booking-format/summary-CTA
presentation. Retain TourController lead form, pending/confirmed price,
review/back/lead transitions and original tour/flight facts; do not make a
standalone micro-trim.

## Historical checkpoint — booking/accessibility/rail reset #1614 — 2026-09-08

Exact source `70cc8105a6920472fafccabc172158ed15f48bca`, checked release
`61df0ca55c4791d04…55135 tokens truncated…corrects the preceding stored gzip absolute by +4 bytes; the package delta is measured on both exact trees with one method.

Security `34184579113` and exact artifacts `34184579101` / `34184702799` passed. Reusable final artifact `10040123280`, digest `sha256:6b40ec5faf8e19e1f5cfe20f38e149fbb7c4f8cabffdeab4c06f8ad0b7da3542`; selected evidence `10040122833`, digest `sha256:5e44b0c51a1d5bd57514ece700ebac4b85409df2b4a42030a073f5f56d8bb23e`; results evidence `10040123068`, digest `sha256:2ceb6a6c4438c720955be9ded8cf47c8bae00cfccda7ca412a0a796d1bd2cb30`.

Focused deterministic coverage passed original source, rerendered same-tour source, results fallback, lead-success return and temporary tabindex cleanup. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area, owner visual acceptance and the pre-existing browser-default result-only retry styling remain deferred. Audit: `docs/project/search3-lean-selected-return-runtime.json`.

Next: audit the complete 1855-byte `flight-empty-recovery-v1.js` owner against the current `selected/flight-fallback.js` owner. Retain the friendly no-flight message and exactly one delegated retry button, preserve fallback review/lead and price behavior, and require empty→retry→recovery plus exact selected browser CI. Do not take the larger `price-confidence-v1.js` before this lower-risk presentation-only owner.

## S3_LEAN_FLIGHT_EMPTY_RUNTIME — checked release, 2026-09-08

Source PR #1542 / `0301f3bf9c2ab77d7f837e86f6915c344f9f87fe`; checked code release `dac375ad4c0f4d366cab3c8a1be4855f3ad6dfcd`. Search3 no longer loads the complete 1855-byte `flight-empty-recovery-v1.js` runtime. The full legacy route retains the unchanged file and `V2FlightEmptyRecoveryV1` compatibility global.

The current selected-flow fallback owner now upgrades the empty-flight copy and creates exactly one delegated `.load-flights.secondary[data-tid]` action from the current tour id. Existing controller delegation performs the retry. The current owner does not decorate `.flight-error`, does not alter flight variants, and preserves the no-flight review/lead handoff. No Tourvisor/API, price, payload or lead transport code moved.

Loaded raw is **551591 → 550542 bytes (−1049)**: scoped shared JavaScript 193914→192059, shared CSS remains 186118, and the eight generated Search3 assets are 171559→172365 (+806 retained behavior). Exact emitted endpoints plus eight independent files gzip is **122454 → 122288 (−166)**.

Security `34186013087` and exact artifacts `34186013106` / `34186184019` passed. Reusable final artifact `10040600906`, digest `sha256:fa390f4dc397d04cf7a9c940d8051d3d416e309c2892c91bab14c3d6c43cc73c`; selected evidence `10040599919`, digest `sha256:3cf61cca1b18f87ae2ef4a1a980c5d8c4aa2e5900406160137ed15f21a5a49cb`; results evidence `10040600381`, digest `sha256:b7619849afd13b93d6913481889e1007dd73cd27f510dd7b19644c2a02c3fabf`.

The required source suite verifies friendly copy, current-tour id, one retry across repeated sync, error exclusion and fallback navigation. Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states without external API or lead requests. The dedicated empty→retry→recovered browser file was updated but is only wired to the main-target flight workflow, so its execution remains deferred rather than claimed green.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Physical Safari/safe-area, live current-source verification, manual screenshot review, dedicated empty→recovery Chromium and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-flight-empty-runtime.json`.

Next: audit the complete `price-confidence-v1.js` runtime. Preserve truthful totals, pending-price states, selected-flight arithmetic and all protected payload/lead contracts; exclude it only if focused price snapshots demonstrate that the legacy layer is presentation-only.

## S3_LEAN_PRICE_CONFIDENCE_RUNTIME — checked release, 2026-09-08

Source PR #1544 / `8a5c9c895b23cd6a068dfcf5b40dcbf8141c3759`; checked code release `90124c5440eecbc46b8aeb104614800e3cc54c80`. Search3 no longer loads the complete 2399-byte `price-confidence-v1.js` runtime. The full legacy route retains the unchanged runtime and `V2PriceConfidenceV1` compatibility global.

The runtime only created a second explanatory note. Current owners already preserve the protected truth: `pricePending` uses the base tour price, a confirmed flight replaces it with one normalized total across the selected header, mobile bar and booking summary, and the booking summary states that a manager confirms final price and flight details before payment. `flight-price-sync-v1.js`, `unpriced-flight-price-reset-v1.js`, price arithmetic and lead payload/transport were not changed. The 99-byte dead review selector for the retired node was removed.

Loaded raw is **550542 → 548044 bytes (−2498)**: scoped shared JavaScript 192059→189660 and eight Search3 assets 172365→172266; shared CSS remains 186118. Exact emitted endpoints plus eight independent files gzip is **122288 → 121807 (−481)**.

Security `34188350663` and exact artifacts `34188350666` / `34188528176` passed. Reusable final artifact `10041387959`, digest `sha256:99faf7dd6e12c92697fab64c2729721075991a08c69637580b0b43e32caf41be`; selected evidence `10041387331`, digest `sha256:da267f7de034dfa0b67b89a5ea854cfe5898a4c5943012010c53a62e7cb78ae8`; results evidence `10041387639`, digest `sha256:bd88065902147240b66a13c5c720c0c4e5f8b2a6377c297122477be8b41fa654`.

Focused source checks cover base, pending and confirmed totals plus the retained booking confirmation copy. Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states without external API or lead requests. The first exact run correctly detected the intentional two-node note removal; the runtime baseline was advanced to the checked source while keeping every compared property, state and width.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-price-confidence-runtime.json`.

Next: audit the complete `results-filter-autorefresh-v1.js` owner against current instant/local DS2 result filters. Preserve loaded-result completeness guards, zero-result recovery and explicit search submission; do not keep an automatic Tourvisor refresh path in Search3 unless a focused contract proves it is required.

## S3_LEAN_FILTER_AUTOREFRESH_RUNTIME — checked release, 2026-09-08

Source PR #1546 / `c7118489fe450ae7850f058206bb20031e6e3004`; checked code release `401c095df33a9cfd440534d48d72ff5a5e8a4c89`. Search3 no longer loads the complete 2714-byte `results-filter-autorefresh-v1.js` runtime. The full legacy route retains the unchanged file and `V2ResultsFilterAutorefreshV1` compatibility global.

Current Search3 result facets remain instant and local, with completeness guards, zero-result recovery and reset intact. Changes to primary search parameters still mark results stale and are submitted explicitly through the current stale-results action and `V2SearchLifecycle.submit()`. The retired legacy owner only scheduled a second supplier search 650 ms after old-form filter changes; no replacement network path was added.

Loaded raw is **548044 → 545330 bytes (−2714)**: scoped shared JavaScript 189660→186946; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact emitted endpoints plus eight independent files gzip is **121807 → 121305 (−502)**.

Security `34189315892` and exact artifacts `34189315891` / `34189452118` passed. Reusable final artifact `10041716679`, digest `sha256:bc39e469877ee9b24721262975490a1d9c6dca8c2ea23d592d558512c92eb384`; selected evidence `10041715945`, digest `sha256:1715d7b6a6ed15922830f774e5e1736899ff6d6fc632fafb4781d849cfb86f2f`; results evidence `10041716298`, digest `sha256:be83f6de2980095d3a23026ff785d0c341e4239e97e5279a9abeef25f85a1e95`.

Focused source checks cover instant/local facets, completeness guards, zero-result recovery, explicit stale-results submission and unchanged legacy retention. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-filter-autorefresh-runtime.json`.

Next: audit `results-depth-v1.js` against `search-lifecycle-v6.js`. The lifecycle already renders 100 results before emitting `v2:search-complete`; preserve progressive 25-result refreshes and the final 100-result render, and remove the old post-completion owner only if focused coverage proves its second request is redundant.

## S3_LEAN_RESULTS_DEPTH_RUNTIME — checked release, 2026-09-08

Source PR #1548 / `e3c6fe0615e64c9117faa0d1895c35a09fe9d7c4`; checked code release `98599ae41594d809af24ef767e7586a9706f8ac6`. Search3 no longer loads the complete 1346-byte `results-depth-v1.js` runtime. The full legacy route retains the unchanged file and `V2ResultsDepthV1` compatibility global.

The current `search-lifecycle-v6.js` remains the single network owner: it fetches progressive 25-result batches while search runs, then fetches and renders 100 results before emitting `v2:search-complete`. The retired owner listened to that completion event and issued the same 100-result request again. No replacement request or behavior code was added.

Loaded raw is **545330 → 543984 bytes (−1346)**: scoped shared JavaScript 186946→185600; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact emitted endpoints plus eight independent files gzip is **121305 → 120947 (−358)**.

Security `34190365275` and exact artifacts `34190365259` / `34190514822` passed. Reusable final artifact `10042067449`, digest `sha256:3afd81112c9f06803248747e27edb48d8d5de6e44256b15db06f08ce0fdb1bc4`; selected evidence `10042066429`, digest `sha256:c4bee1faaea696858c5bce1f1378dab1ae319bf773749f7a8e78cbbf394cd209`; results evidence `10042066943`, digest `sha256:9bebbde6699e8935680a29eaa3f34e0d0d350c47fe1114856082bff03eaad206`.

Focused source checks assert one final 100-result request, completion only after its render, retained progressive limit 25 and absent Search3 compatibility global. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-results-depth-runtime.json`.

Next: audit `results-local-filters-v1.js` against `ds2-results-filters.js` and `search-lifecycle-v6.js` as one substantial owner-consolidation package. Preserve every unique form-narrowing, result-facet, completeness, catalog-refresh and explicit supplier-search contract; do not remove working filter behavior for size alone.

## S3_LEAN_LOCAL_FILTER_OWNER — checked release, 2026-09-08

Source PR #1550 / `4cd7961bb85b3654a1c2cfae32d78747deb5c888`; checked code release `1ed8fec4b87792b5274d7a61ba4f5a1930cace89`. Search3 no longer loads the complete 5765-byte `results-local-filters-v1.js` runtime. The unchanged runtime and `V2ResultsLocalFiltersV1` global remain in the full manifest used by `/poisk-turov-old/`.

The unique form contract moved into the current `ds2-results-filters.js` owner: stars, rating, price bounds, scalar/object region and subregion IDs, region-dependent catalog refresh, tour pruning and minimum-price recomputation. Changes that would broaden the supplier snapshot still reach the current explicit lifecycle submit path. Both form narrowing and DS2 facets now filter one canonical source list, preventing a locally rendered subset from being recaptured as a second owner’s source and preserving the Search3 zero-result bridge.

Loaded raw is **543984 → 542124 bytes (−1860)**: scoped shared JavaScript 185600→183740; shared CSS and the eight Search3 assets remain 186118 and 172266. Exact same-method endpoint gzip delta is **−438 bytes**, giving **120947 → 120509** from the preceding exact checkpoint. The removed legacy runtime is 5765 bytes and the retained behavior adds 3905 bytes to the current shared owner; only the net loaded saving is reported.

Security `34192366116` and exact artifacts `34192366178` / `34192605097` passed. Reusable final artifact `10042768273`, digest `sha256:6b1e966e07714ccff226269fd0ee8099288b34e3ffb87e1f93c9ca764cf588df`; selected evidence `10042767008`, digest `sha256:ede37e35a92a7ca573371a9d28776702f7fc1d35191ac07842d092e4061ca583`; results evidence `10042767642`, digest `sha256:6a1cb432427f5f28337440ed6b3eaac68c153ae70a701253cc7bf4550f2ec39f`.

Focused source checks cover stars narrow/clear, rating and price bounds, region clearing subregion, one catalog refresh, tour-price recomputation, unsafe broadening handoff, DS2 facet zero/recovery and old-route retention. Exact Chromium retained 12 selected states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-local-filter-owner.json`.

Next: audit the complete `design-v1.css` and `enhancements.css` legacy presentation layers against current Search3 private/DS2 owners. Remove a whole layer only if focused geometry proves it has no unique Search3 contract; keep both layers on the old route and avoid micro-removals.

## S3_LEAN_DESIGN_V1_LAYER — checked release, 2026-09-08

Source PR #1552 / `802e1adff7395fb817563512f080ef2d798b0ef6`; checked code release `f0f39e91f037343fa3aa30a4d5953f01ca42cf9f`. Search3 no longer loads the complete 7527-byte `design-v1.css` presentation layer. The unchanged layer and full manifest order remain available to `/poisk-turov-old/`.

Exact Chromium deliberately drove the repair. Five red runs (`34193483597`, `34193997477`, `34194429152`, `34195148577`, `34195705377`) exposed only the live selected-tour and mobile-entry slice: Back spacing, flight route/arrow geometry, lead consent/CTA dimensions, the 10px mobile form grid rhythm and 49px native fields. These rules now live in current Search3 owners; the rest of the legacy layer remains excluded. The red runs are recorded as red, not described as successful.

Loaded raw is **542124 → 535608 bytes (−6516)**: scoped shared CSS 186118→178591, shared JavaScript remains 183740, and the eight generated Search3 assets are 172266→173277 after retaining 1011 bytes of current-owner geometry. Carried same-method gzip is **120509 → 119180 (−1329)**.

Security `34196012323` and exact artifacts `34196012398` / `34196175190` passed. Reusable final artifact `10044015653`, digest `sha256:ee6824caa70a9aaabf48720bc517c475e28d83e0f7fb8b1159f116718d4b4514`; selected evidence `10044014763`, digest `sha256:95c9ce657e50f82d9477d22987c77f25061b38297ee47f4245e5eb83f69f8143`; results evidence `10044015196`, digest `sha256:685945e258b13936a05989c0f58dd806b23297a553ba8a75811bc328e7985266`.

Exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 header states. No supplier request or lead was sent. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-design-v1-layer.json`.

Next: audit the complete `enhancements.css` presentation layer. Keep it unchanged on the old route and attempt only a whole-layer, reversible Search3 exclusion with focused geometry-driven repair; do not create micro-removal PRs.
## S3_LEAN_TOUR_DESIGN_LAYER — checked release, 2026-09-08

Source PR #1556 / `87190b028bb628c7b831c65e11d5ea8d54d8639c`; checked code release `74bb11cca818e111503f888a99b3a61e306c1186`. Search3 no longer loads the complete 5269-byte `tour-design-v1.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

Exact Chromium drove the repair. Three red runs (`34198929535`, `34199068139`, `34199526115`) exposed malformed literal line breaks and then the genuinely live selected-tour/mobile-flight geometry. Only a 670-byte slice was retained in the current `selected-tour-ux.css` owner: selected overflow and price alignment, selected/lead positioning and rhythm, and mobile flight segment/title/route/baggage presentation. The red runs remain recorded as red.

Loaded raw is **523917 → 519318 bytes (−4599)**: scoped shared CSS 166900→162301, shared JavaScript remains 183740, and the eight generated Search3 assets are 173330→174000 after retaining current-owner geometry. Carried same-method gzip is **117126 → 116431 (−695)**.

Security `34199833477` and exact artifacts `34199833496` / `34200268121` passed. Reusable final artifact `10045565727`, digest `sha256:738136f729747089b59cb65d9e61a018b53f6c46737959d62990baf18ed21a23`; selected evidence `10045564964`, digest `sha256:903a62215194e12a3bb5bf7394d0552d0aca62aea855cd74f791c8b1815a1e48`; results evidence `10045565347`, digest `sha256:95af06173ab0262b92f0d25bc54eed81be45f52ac45a931adf0156e7cf97a821`.

Exact Chromium retained selected detail/review/lead, result and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-tour-design-layer.json`.

Next: audit the complete `hotel-details-design.css` presentation layer. Keep it unchanged on the old route and exclude it from Search3 only if focused result/selected coverage proves the inline-detail owner is obsolete.

## S3_LEAN_HOTEL_DETAILS_LAYER — checked release, 2026-09-08

Source PR #1558 / `60223b579d05456d782722ceead4e755a22167d9`; checked code release `4dc61c0154d4129a9d514121120e48692c82cc97`. Search3 no longer loads the complete 4640-byte `hotel-details-design.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

The layer belongs to the old `.hotel-actions`, `.hotel-info-toggle`, `.hotel-inline-detail` and inline gallery/facts surface. Current Search3 already hides the retired actions and inline detail, while its hotel CTA, expanded tour rows, selected tour and active room details remain owned by current modules. No replacement CSS or JavaScript was added.

Loaded raw is **519318 → 514678 bytes (−4640)**: scoped shared CSS 162301→157661; shared JavaScript and the eight generated Search3 assets remain 183740 and 174000. Carried same-method gzip is **116431 → 115816 (−615)**.

Security `34202647856` and exact artifacts `34202647875` / `34202904621` passed. Reusable final artifact `10046592943`, digest `sha256:d81ea21c4ec39cda85b576bc745108376c6c9bdf695e1db8709ed5194bb2fd24`; selected evidence `10046591698`, digest `sha256:2d028c44e64423351193a4986726b143c9844d298b37ed343bd4d76c1cc10f8f`; results evidence `10046592310`, digest `sha256:2acf4dd85f72f4a039ceef69c521264c30fc743f2843f781edb0e70cf9c70d42`.

Both exact Chromium runs retained selected detail/review/lead, result and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-hotel-details-layer.json`.

Next: audit the complete `results-experience-v1.css` presentation layer as one reversible package. Preserve active Search3 result geometry in current owners, keep the layer unchanged on the old route, and do not remove active `room-details.css` for size alone.

## S3_LEAN_RESULTS_EXPERIENCE_LAYER — checked release, 2026-09-08

Source PR #1561 / `886ae5f7b1cbe886ad2bc047c2785edebd343d88`; checked code release `b9d4240871c1194187022ea0b3f80a16ae0f4435`. Search3 no longer loads the complete 8656-byte `results-experience-v1.css` presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

Current Search3 owners already preserve result tools, hotel card/photo, hotel facts, package disclosure, expanded tour rows, price/CTA and actionable empty state. Both exact Chromium runs passed without adding any replacement CSS or JavaScript.

Loaded raw is **514678 → 506022 bytes (−8656)**: scoped shared CSS 157661→149005; shared JavaScript and the eight generated Search3 assets remain 183740 and 174000. Carried same-method gzip is **115816 → 114104 (−1712)**.

Security `34204676344` and exact artifacts `34204676400` / `34204924813` passed. Reusable final artifact `10047392842`, digest `sha256:bb40958fb09d03030f6dccd52cb8609579fba06a69e99908d3c2ee69a33d7a2b`; results evidence `10047392372`, digest `sha256:4d1aed11ba7554daa3bebc58204d5f1338d73f35fb7450fb3adb39388d2c8eb3`; selected evidence `10047392000`, digest `sha256:21998738db112adc43866985216ec8ff7a573550a3205658a9c9d9d481460612`.

Exact Chromium retained selected detail/review/lead, result, expanded-package and entry geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-results-experience-layer.json`.

Next: audit the complete `anytour-brand.css` presentation layer against current Search3 owners as one reversible package. Preserve brand tokens and active geometry, keep the layer unchanged on the old route, and do not remove active `room-details.css` or `search-states-design.css` merely for size.

## S3_LEAN_ANYTOUR_BRAND_LAYER — checked release, 2026-09-08

Source PR #1563 / `775e7279f1f6cd2d923136451a1ea5594b69e282`; checked code release `d42691a6797f58abb5da7aca730d2125d57b425f`. Search3 no longer loads the complete 9722-byte `anytour-brand.css` legacy V2 presentation layer. The unchanged layer remains in the full manifest used by `/poisk-turov-old/`.

The retired file owned its own hero/brand/form/card skin and private `--anytour-*` variables; focused source inspection found no consumers of those variables outside that same file. Current Search3 owners preserve its form, result cards, selected/lead states and shared site header/logo. Active `room-details.css` and `search-states-design.css` remain loaded. No replacement CSS or JavaScript was added.

Loaded raw is **506022 → 496300 bytes (−9722)**: scoped shared CSS 149005→139283; shared JavaScript remains 183740 and the exact artifact-proven eight generated Search3 assets remain 173277. This corrects the previously transcribed 174000 subtotal without changing the 496300 loaded total. Carried same-method gzip is **114104 → 112163 (−1941)**.

Security `34205833337` and exact artifacts `34205833396` / `34206067387` passed. Reusable final artifact `10047845216`, digest `sha256:7111070f1ff14b5c9be8f20292219c3a752cb1dd8aef9b8c9dbdadb9d7014da6`; results evidence `10047844646`, digest `sha256:28dc1e34ebe754433ecb98ac9565dd2901db8d09fef698e83126b6d2b69edb82`; selected evidence `10047844074`, digest `sha256:0dd0c4cc01227efb3693bda83a791956ba230793a7a4a1b27fa797cce1e17306`.

Exact Chromium retained selected detail/review/lead, result, expanded-package, entry and shared header/logo geometry without external API or lead requests. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, logo/native/nesting contracts are unchanged.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-anytour-brand-layer.json`.

Next: audit the complete `product-shell-v1.css` presentation layer against the current Search3 page intro and shared site shell. Preserve header/footer/logo and active route geometry, retain the full old-route layer, and do not retire active `room-details.css` or `search-states-design.css` merely for size.

## S3_LEAN_PRODUCT_SHELL_LAYER — checked release, 2026-09-08

Source PR #1568 / `9bcaaae15cc287f929a9fb8c4a42a03307d81b47`; checked code release `c46d6fab0f7d8ecfd43d6fc46acdf446a742b1db`. Search3 no longer loads the complete 10197-byte `product-shell-v1.css` legacy presentation layer. The unchanged file remains in the full manifest used by `/poisk-turov-old/`.

The retired layer owned the obsolete `.at-site-header*`, nav/mobile-menu, product hero and old `.primary-search-flow` grid. Current Search3 uses `.at-global-header`, hides and replaces the old hero, and owns its shell, entry, results, selected and lead surfaces. Exact Chromium identified the retained slice: hidden legacy hero before initialization, 52px mobile/tablet shell bottom spacing, Aeroport font and the inherited ink/soft values. Those contracts add 232 compiled bytes to the current Search3 base; no other legacy rule moved.

Loaded raw is **496300 → 486335 bytes (−9965)**: scoped shared CSS 139283→129086; shared JavaScript remains 183740; the eight public assets are 173277→173509. Exact artifact `10047845216` proves the prior eight-asset subtotal was 173277 rather than the transcribed 174000; its 496300 loaded total is unchanged. Same-method endpoint/eight-file gzip delta is **−1978 bytes**, carrying **112163 → 110185**.

Security `34208217683` and exact artifacts `34208217590` / `34208410446` passed. The first exact run `34207759220` is retained as red: node counts and geometry were unchanged, but it exposed the font, ink and soft-token visual drift that was then repaired. Reusable final artifact `10048797611`, digest `sha256:aad6972854eb311400c17cc831f17135717bf610838f0e205bc3de9888141f5f`; selected evidence `10048796241`, digest `sha256:0db90db602f31f76f115fc2e789a0a447fb01c05765812562c0fd06dce2500b1`; results evidence `10048796853`, digest `sha256:35e247ede326b51f4192cd6f3d8b078b200d50f6dca7252a8acc936f5f28e1ae`.

Final exact Chromium retained 12 selected detail/review/lead states, 30 entry states, 12 result states and 10 shared-header states without external API or lead requests. Header/footer/navigation and canonical logo are unchanged. Eight public paths and Tourvisor/API, URL/payload, price arithmetic, lead transport/mapping, analytics/goals, native/nesting contracts are preserved.

Preview was not published and remains exact source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains observed at `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`. No production deployment occurred. Live current-source interaction, manual screenshots, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-product-shell-layer.json`.

Next: audit the complete `search-header-shared-shell-v1.css` layer against the current `.at-global-header` and Search3 base/entry owners. Preserve header/footer/logo/navigation and active route geometry, and keep the full layer on the old route.

### S3_LEAN_SHARED_HEADER_SHELL_LAYER — CHECKED RELEASE, NOT PUBLISHED, NOT PRODUCTION (2026-09-08)

Source PR #1573 / `2b6afe8602587ff6e45e13127129daec4187e22d`; checked release
`e887eef45e8a23560d5f5138c0b38908f0be7ca3`. Search3 no longer loads the complete
`search-header-shared-shell-v1.css` layer. All of its selectors belong to the legacy
`.at-site-header`, `.at-site-*` and `.at-mobile-menu-*` families. Current Search3
renders `.at-global-header`, whose logo, navigation, mobile menu and geometry remain
owned by `site-header-v2.php/css`. The full old-search manifest retains the 4,455-byte
file; no compensating Search3 CSS was required.

Loaded raw is **486335 → 481880 bytes (−4455)**: scoped shared CSS
**129086 → 124631**, shared JavaScript remains **183740**, and the eight generated
Search3 public assets remain exactly **173509**. Exact same-method endpoint plus
eight-independent-file gzip is **110185 → 109432 (−753)**.

Local source build/check, 13 source-build tests, 45 presentation tests (one local
PHP-dependent skip) and the owner-priority validator passed. Security
`34209958561`, initial exact artifact `34209962002` and ready repeat
`34210172714` succeeded. Reusable artifact `10049486332`, digest
`sha256:879ab7e79da7b10782b64d55273028dce5efbddec0f77a0fd26b6107502c3d5c`.
Results/header evidence `10049485574`, digest
`sha256:1da978383f64e0401a269ca7cd312033610c6c2743a3bbe66999a1349d2f49e5`;
selected evidence `10049484807`, digest
`sha256:1d4a362e8ed0255888f343830f540337abb913e5380cbcb88aca2d231cbf24f7`.
Exact source/PHP/path/presentation/isolation guards and Chromium header, entry,
results and selected-tour geometry passed without external API or lead requests.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not
published. Main and production were not changed. Live current-source visuals,
representative screenshot inspection, physical Safari/safe-area and owner acceptance
remain deferred. Audit: `docs/project/search3-lean-shared-header-shell-layer.json`.

Next: audit complete `ds2-search-intro-v1.css` and
`ds2-selected-tour-convergence-v1.css` layers against current Search3 owners.
Retire only a proven whole layer or bounded family; preserve active entry, results,
selected-tour, price, URL/payload, Tourvisor/API, lead, analytics, logo and browser
contracts.

### S3_LEAN_SELECTED_CONVERGENCE_LAYER — CHECKED RELEASE, NOT PUBLISHED, NOT PRODUCTION (2026-09-08)

Source PR #1575 / `b86a5eeb7396134b08b2eb2ced2eddd717a0f882`; checked release
`13df507f22e6e56c8320b944cb3c531ad8a796b9`. Search3 no longer loads the complete
10,798-byte `ds2-selected-tour-convergence-v1.css` layer. The full old-search
manifest retains the unchanged file.

The first exact run correctly exposed the donor's remaining live presentation slice.
Current Search3 owners now explicitly preserve selected shell sizing and box sizing,
price-label tracking, facts and disclosure heights, section typography, flight-choice
grid placement, route radius and mobile lead-input radius. The final desktop mismatch
was the inherited `.045em` price-label tracking: without it the auto price column
grew 12.6px. No business logic moved.

Loaded raw is **481880 → 472663 bytes (−9217 net)**: scoped shared CSS
**124631 → 113833**, shared JavaScript remains **183740**, and the eight generated
public assets are **173509 → 175090** (+1581 retained current-owner CSS).
Carried same-method endpoint plus eight-file gzip is **109432 → 107843 (−1589)**.

Security `34215439259`, exact source run `34215439223` and ready repeat
`34215627824` passed. Reusable artifact `10051687021`, digest
`sha256:6b813f3bc74c55ec3e9a4ff3acfab85edd1560b4f697a0400f09bc4dd7be6f65`.
Results/entry evidence `10051686606`, digest
`sha256:178b841171f92931285942b5968c722a71e5690edf4b7bab8f02b43a105ca351`;
selected evidence `10051686211`, digest
`sha256:9c16de58147951a5a0a4291bc22b1aca3aded16df1a722d0aea49896a87812ef`.

Exact source/PHP/path/presentation/isolation guards passed. Chromium retained all
12 selected detail/review/lead states at 375/760/1000/1440 and the guarded
results/entry states, with no external API or lead request. Eight public paths,
price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping,
Metrika/analytics/goals, logo and native-browser/nesting contracts are preserved.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not
published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not
changed. Live current-source visuals, representative manual screenshot inspection,
physical Safari/safe-area and owner acceptance remain deferred. Audit:
`docs/project/search3-lean-selected-convergence-layer.json`.

Next: retire the complete `selected-tour-layout-guard-v1.css` and audit
`br3-control-consistency-v1.css` in one substantial reversible package. Preserve
current progress/error controls, focus/touch targets, selected return/description
geometry, and keep both legacy files unchanged on the old route.

## S3_LEAN_CONTROL_GUARDS_LAYER — checked release, 2026-09-08

Source PR #1580 / `5b07409fdacb9b5cd8912f0c117eabd9e5378172`; checked code release `4a8b279deda40075817013653bf03dcd51f927eb`. Search3 no longer loads the complete 4357-byte `br3-control-consistency-v1.css` and 916-byte `selected-tour-layout-guard-v1.css` layers. Both unchanged files remain in the full manifest used by `/poisk-turov-old/`.

Current owners retain only active secondary/progress/retry/filter controls, focus and reduced-motion behavior, direct-tour CTA state, hotel-description disclosure, and the tablet/phone selected-head and price geometry. The first exact run `34218075137` correctly exposed an over-specific retained selector that moved review content at 760px and left the phone review grid template wrong. The selector was reduced to donor specificity and the phone one-column head restored; no guard or compared property was removed.

Loaded raw is **472663 → 470476 bytes (−2187 net)**: scoped shared CSS **113833 → 108560**, shared JavaScript remains **183740**, and the eight generated public assets are **175090 → 178176** (+3086 retained current-owner CSS). Carried same-method gzip is **107843 → 107522 (−321)**.

Security `34220520877`, exact source run `34220520872` and ready repeat `34220737644` passed. Reusable artifact `10053671415`, digest `sha256:101937e4872190abb5126f9280f6103596918ceb1d20def4d2de5afaf2516d08`. Results/entry evidence `10053670660`, digest `sha256:c6caf779e233c7b3220d9d21a7b077947a5b6c91e50addae0ee8e405141c8bc8`; selected evidence `10053669978`, digest `sha256:35b64a1363a68353f446656709ea1f4436e60d86e67f28a50d8109804ab30931`.

Exact source/PHP/path/presentation/isolation guards passed. Chromium retained all 12 selected detail/review/lead states at 375/760/1000/1440 and the guarded results/entry states, with no external API or lead request. Eight public paths, price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping, Metrika/analytics/goals, logo and native-browser/nesting contracts are preserved.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, representative manual screenshot inspection, physical Safari/safe-area and owner acceptance remain deferred. Audit: `docs/project/search3-lean-control-guards-layer.json`.

Next: audit `search-filters-ux-v1.css` and `selected-tour-ux.css` as whole-owner candidates. Retire only the stronger substantial candidate after compactly preserving active Search3 lifecycle/geometry in current owners; keep the old route unchanged.


### S3_LEAN_SEARCH_FILTER_SKIN — checked release, not published

[#1584](https://github.com/pyatkoff/poisk-turov-test/pull/1584) removes the complete legacy `search-filters-ux-v1.css` layer from the Search3 scope while leaving the file unchanged in the full manifest for `/poisk-turov-old/`. The current Search3 form retains native date/night controls, quality controls, mobile advanced filters and the tourist popover. The only retained dependencies are a scoped pre-init hidden-wrapper rule and child-age spacing; the two tourist selects now explicitly shed the legacy visually-hidden class, `aria-hidden` and negative tab order.

Loaded shared CSS + shared JS + eight public Search3 assets: **470,476 → 458,693 raw bytes (−11,783 B)**. Shared CSS: 108,560 → 96,535 B; shared JS: 183,740 B unchanged; eight public assets: 178,176 → 178,418 B (+242 B retained current ownership).

Implementation source `a201f645bc614bdab65ee3008ebfd152a70b78af`; checked release `f1a7fe122d878918157cb27f4c83165569f0638e`. Security run [34222910866](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34222910866) and exact artifact/browser run [34222910847](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34222910847) passed. Artifact `10054529522`, digest `sha256:84a7c55e4612fbd9395f5b315477945ccee8f9f0d1ee44ebbb9baf3031dbcccd`; results/entry geometry `10054528862`; selected geometry `10054528235`. No external API or lead request was made.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; this source was not published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, physical Safari/safe-area and manual screenshot inspection remain deferred.

Next: retire `selected-tour-ux.css` as one reversible Search3-only package, retaining only confirmed detail, flight, room-disclosure and lead-state contracts in current owners. Following the owner’s test-branch guidance, use one Security + exact artifact/smoke pass per substantial package and do not repeat the same CI after ready transition.


### S3_LEAN_SELECTED_TOUR_SKIN — experimental test release, not published

[#1588](https://github.com/pyatkoff/poisk-turov-test/pull/1588) removes the complete 14,190-byte `selected-tour-ux.css` layer from Search3 only. The unchanged full manifest still serves it to `/poisk-turov-old/`. Confirmed description/room disclosure, flight recovery/mobile route and lead-state rules remain in current Search3 owners; old decision summaries, repeated checkout skin and legacy lead-success/trust presentation were not copied.

Loaded shared CSS + shared JS + eight public assets: **458,693 → 447,863 raw bytes (−10,830 B)**. Shared CSS: 96,535 → 82,345 B; shared JS: 183,740 B unchanged; eight public assets: 178,418 → 181,778 B (+3,360 B retained current rules).

Implementation `33395ab79eb4148c4c440edb0ea1bcb2fddd48e1`; experimental release `20df2019ea2948ef725526bc896ca290fdd154ab`. Security [34223970798](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34223970798) passed. Exact artifact/browser [34223970803](https://github.com/pyatkoff/poisk-turov-test/actions/runs/34223970803) is **red** at selected-tour pixel equality: removal changed legacy spacing/skin in all 12 detail/review/lead snapshots. It did not produce a reusable release artifact. Evidence `10054929764`, digest `sha256:71cd2ce050e3d5a444a981905ff88464241888d2023dfd44794a5c17bc495d03`, records equal DOM node counts for every state and no horizontal overflow. This is recorded as an owner-authorized visual experiment on the test release, not as green CI or a checked release. Last fully checked release remains `f1a7fe122d878918157cb27f4c83165569f0638e`.

Preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual inspection of changed selected-tour visuals, live current-source interaction and physical Safari remain deferred.

Next: continue an independent whole-layer audit of `app.css` / `search-states-design.css`. Do not restore retired selected-tour pixels unless an actual functional regression is confirmed. Keep the reduced owner-requested policy: one Security plus one build/smoke run per substantial source package, no automatic ready-transition repeat.

### S3_LEAN_SELECTED_TOUR_AND_RUNTIME_OWNERS — checked release, not published

The experimental #1588 result was not left red. Its retained DOM was intact, but exact run `34223970803` exposed omitted live flight/detail/lead geometry. #1590 restores only that active slice in current owners; `selected-tour-ux.css` stays completely excluded from Search3 and unchanged for `/poisk-turov-old/`. Security `34225808267` and exact artifact/browser `34225808272` passed all 12 detail/review/lead states at 375/760/1000/1440. Reusable artifact `10055697968`, digest `sha256:374ddaf86efdfad49a82d46c31d17bdb37d20428ae58e67f1d2586462d6da4f6`; selected evidence `10055697041`, digest `sha256:53de20c17c1ef2f1b025ae225a7c867069484d999b00b9c48b160cc0312a8415`.

In parallel, #1587 retires the standalone `flight-price-presentation.js` and `filter-rail.js` runtimes. Their live display-only decimal correction and bounded zero-result bridge now run inside the existing selected/results schedulers; authoritative `v2/flight-price-sync-v1.js`, price events and protected contracts are unchanged. Its eight generated assets are **178,418 → 177,847 raw bytes (−571 B)**. Security `34224138160` and exact artifact `34224138265` passed; artifact `10055011556`, digest `sha256:5fb3eba01d21851bea83b2701907a2f7ab134105234b3f5b16eed9707d32807a`.

Final checked release `c155faa2cfcbb2e04dbafa841022d491ee863abe` is **458,693 → 448,524 loaded raw bytes (−10,169 B)** across the retired selected-tour layer, retained current-owner repair and JS consolidation. The eight generated assets are 182,439 B at that combined release; only #1587 contributes a real eight-asset reduction, while the selected CSS layer saves bytes in the scoped shared endpoint. The original red run remains recorded and is not called green.

Preview was not published and remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction and physical Safari remain deferred. Audits: `docs/project/search3-lean-selected-tour-skin.json` and `docs/project/search3-current-presentation-runtime-consolidation.json`.

Next: audit the next complete shared presentation layer against current Search3 owners. Keep the old route intact and take only a substantial package with a positive final loaded-byte result.

### S3_LEAN_APP_PRESENTATION_LAYER — checked release, not published

[#1591](https://github.com/pyatkoff/poisk-turov-test/pull/1591) removes the complete 12,911-byte `app.css` layer from Search3 only. The unchanged full manifest continues to serve it to `/poisk-turov-old/`. Exact artifact failures were used as a repair list: current owners retain only scoped box sizing/body baseline, entry grid and native-control dimensions, selected/flight/lead primitives, and the result-card container/image/typography geometry that is still live.

Final checked Search3 payload is **448,524 → 438,274 raw bytes (−10,250 B)**. Shared CSS is **82,345 → 69,434 B** and shared JS remains **183,740 B**. The eight generated public assets are **182,439 → 185,100 B (+2,661 B)** because the live replacement slice now belongs to current modules; this package is therefore recorded as a real route payload reduction, not as an eight-asset reduction. Across the preceding selected/runtime package and this layer, the checked route moved **458,693 → 438,274 B (−20,419 B)**.

Final source `7fe04821c961c48243a69d6edee0e345bcc43d8a`; checked release `de49b39989891945b8e47434e72d5e79d9f4080b`. Security `34230516546` and exact artifact/browser `34230516579` passed. Reusable whole-site artifact `10057632192`, digest `sha256:8248e14765ed95dce5f84ff16799e893a16a1dd479aebb07910e7282a7bdbb4f`; selected evidence `10057629961`, digest `sha256:d854e0350839288dd2da6635f5dc6ed6bca8fcf7876be24df0e32cebb700cc22`; results/entry evidence `10057631078`, digest `sha256:dfb7988688678d2a4907dcda54c6bc612ce427f2620ddb9c0c3285a8364a653f`.

Exact Chromium retained selected detail/review/lead at 375/760/1000/1440, entry lifecycle and breakpoint resize, and result-card/drawer collapsed/expanded geometry at 375/760/761/999/1000/1440. No external API or lead request was made. Eight public paths, price arithmetic, URL/payload, Tourvisor/API, lead transport/mapping, Metrika/analytics/goals, logo and native-browser/nesting contracts remain protected.

Preview was not published and remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection, physical Safari/safe-area and owner acceptance remain deferred. Audit: `docs/project/search3-lean-app-layer.json`.

Next: re-audit the complete `search-states-design.css` layer after `app.css` retirement. Its previous fallback assumptions are obsolete; preserve explicit current status, skeleton and empty-state ownership and proceed only with a positive final loaded-byte result.

### S3_LEAN_APP_STATE_MOBILE_LAYERS — checked release, not published (2026-09-08)

Three consecutive Search3-only packages retired complete legacy presentation layers while preserving every file in the full manifest for `/poisk-turov-old/`:

- [#1591](https://github.com/pyatkoff/poisk-turov-test/pull/1591), source `7fe04821c961c48243a69d6edee0e345bcc43d8a`, release `de49b39989891945b8e47434e72d5e79d9f4080b`: removed 12,911-byte `app.css`; 2,661 bytes of active base/entry/result/selected primitives moved to current Search3 owners, net **448,524 → 438,274 raw (−10,250 B)** and same-method gzip **100,143 → 98,083 (−2,060 B)**.
- [#1592](https://github.com/pyatkoff/poisk-turov-test/pull/1592), source `1607948872372a5d1426076ccd1158846258be68`, release `49f898823d30b6f3e60cb682e9d474767a03f812`: removed complete 2,199-byte `search-states-design.css` with no compensation; skeleton, empty and tour-loading structure remains in current `search-progress.css`.
- [#1594](https://github.com/pyatkoff/poisk-turov-test/pull/1594), source `220667185984d1e3c05ede301fa222038b0637b4`, checked release `58b5cf9dc1c13388c6ebc9706cbdc2a293f7bbfc`: removed complete 4,247-byte `mobile-results-filters-v1.css` with no compensation. `mobile-results-filters-v1.js` remains loaded and the current Search3 toolbar owner already supplies the bar, drawer, option and action presentation.

Combined loaded shared CSS + shared JS + eight generated public assets are **448,524 → 431,828 raw bytes (−16,696 B)**. Shared CSS is **82,345 → 62,988 B**, shared JS remains **183,740 B**, and the eight generated assets are **182,439 → 185,100 B** after the retained current-owner primitives from #1591. All eight public paths are unchanged.

Required Security runs `34230516546`, `34231461485`, `34231823029` and exact artifact runs `34230516579`, `34231461481`, `34231823219` passed. Final reusable artifact `10058165680`, digest `sha256:feee4efdd6bdef701221c28a0d72f35e0ecacca08f7ff5005d0da8c3888dedf3`; selected evidence `10058163708`, digest `sha256:84b34d9689a3b14c14a527ad2031eb2c5827a59f039665075fbd0515c277870c`; results/entry evidence `10058164665`, digest `sha256:d8f80b8afdc76c6ef1c5276409f1ec8eb1d7e7663190482d30d264e6a0d93ee9`. Final exact source/PHP/path/presentation/isolation guards and Chromium 12 selected detail/review/lead, 30 entry and 12 result/drawer states passed without external API or lead requests.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; none of these sources was published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection, physical Safari/safe-area and owner visual acceptance remain deferred. Audit: `docs/project/search3-lean-app-state-mobile-layers.json`.

Next: retire `current-price-calendar-v1.css` from Search3 after moving only its active day-grid/button primitives into `entry-calendar.css`. Preserve `current-price-calendar-v1.js`, all price arithmetic, the eight public paths and the complete old-route layer.

### S3_HALF_BUNDLE_AND_DEAD_FOOTER — checked release, not published (2026-09-08)

The owner requested one coarse reduction with minimal repeated checks. [#1602](https://github.com/pyatkoff/poisk-turov-test/pull/1602), source `ba53409864d25d44f6900e83d0095d03fe155d33`, removed three complete CSS layers and nine JavaScript owners from the Search3 route. The full manifest and files remain available to `/poisk-turov-old/`. The optional price calendar, room gallery/details, hotel autocomplete and secondary loaded-result filters were intentionally not retained on the test release; core search, Tourvisor/API, selection, price arithmetic, URL/payload, analytics and lead transport remain.

The first exact run `34237093531` was correctly red: the supposedly entry/results-only `ds2-search-intro-v1.css` contained an undocumented selected-tour focus block. Evidence `10060370921`, digest `sha256:a4ac4facee5b6ac84646c02d54afa3df42e0ee1e8c8d6fd197a58492124a25bd`. Only that live slice was moved into the current `tour-detail` owner; the complete legacy layer stayed excluded. Final Security `34238161870` and exact artifact `34238161854` passed, including selected detail/review/lead at 375/760/1000/1440 and guarded entry/results geometry. Reusable artifact `10060846914`, digest `sha256:8b111ac4af05d0a459022f956189950c472dbeb7439e08e3be703aabfd2e34af`; selected evidence `10060844850`, digest `sha256:2219dfa938529d8a38c8ad25af4821ead910f9d4ad1c65a88b7d933d77a7ec0c`; results evidence `10060845900`, digest `sha256:91c60d11e8235d8907b26baabf3852327cc96bc9479ebefafecde1420fba4248`. Checked release after #1602: `e8c50167b32a08bc2e21aab084f61ff64ba969c9`.

The same invocation continued with [#1603](https://github.com/pyatkoff/poisk-turov-test/pull/1603), source `7c509cdd9f8c8073c30383445029f5b7a6273731`: two unused generations (`v2-site-community` and `at-site-footer`) were removed from `site-footer-v1.css`; the active DS2 footer, logo, contacts, navigation, social/app links and responsive rules remain. Security `34239017232` and exact artifact `34239017211` passed. Artifact `10061148819`, digest `sha256:06b40cdd95b9e384819884efdd608984c3fb4f0de0751a315c0aa28d6e6d92ca`. Final checked release: `789f4cdb200ca19bab9967aad5f64edbe60014da`.

Across these two PRs, loaded Search3 CSS/JS is **419167 → 324836 raw bytes (−94331 B)**. Shared CSS is **57078 → 20670 B**, shared JavaScript **183740 → 126853 B**, and the eight generated public assets **178349 → 177313 B**. Against the original lean-bundle baseline, the route is **665247 → 324836 B (−340411 B / 51.17%)**. This is the first checked release below half of the original loaded raw size.

Published preview remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`; neither source was published. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Live current-source interaction, manual screenshot inspection and physical Safari/safe-area are deferred. Audit: `docs/project/search3-half-bundle-reduction.json`.

Next: audit remaining required JavaScript only as a coarse dependency package. Do not micro-trim or repeat CI on ready transition; preserve the core search, Tourvisor/API, price, routing, analytics and lead contracts.

## 2026-09-08 — whole-layer half-size reset (#1604)

[#1604](https://github.com/pyatkoff/poisk-turov-test/pull/1604), exact source `a25fb7d4492ffa3c7308bc5ac92fadd8b91f9017`, removed 42 obsolete/duplicate Search3 CSS modules, the standalone progress presentation module, all shared CSS and seven optional shared runtime owners. The eight public paths remain stable. Base-aware eight-asset raw size is **178349 → 78454 B (−99895 B / 56.01%)**; complete loaded Search3 CSS/JS is **412027 → 197067 B (−214960 B / 52.17%)**.

Security `34241546821` and exact artifact build `34241546778` passed. Reusable artifact `10062216567`, digest `sha256:4f694ae0a1b0bba75193f5be0cccb1b7e230e5c99dfe5d6af9480dd674cd6eeb`; focused reset evidence `10062216044`, digest `sha256:e114b9d49421d63f1c9ea1e2c8d9850a1b9d0914cf8b7c9954eccf32c2984ad8`. The one focused browser pass covered entry/detail/review/lead at 375/1440, blocked all external requests and sent zero leads. It caught and repaired a sub-44px search submit and mobile selected-tour overflow before merge. Release is `9d0d29f21033414fe86ee8179a9027f922d98896`.

Preview was not published: published source remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main remains `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual screenshot inspection, live current-source interaction, physical Safari/safe-area and the broad responsive/site/SEO matrix are deferred, not passed.

Next: audit retained results/selected JavaScript as one coarse owner package. Do not return to micro-trims or per-layer browser/deploy cycles; preserve API/Tourvisor, URL/payload, price, lead and analytics contracts.

## 2026-09-08 — results presentation reset (#1607)

The same active reduction pass continued after #1604 with [#1607](https://github.com/pyatkoff/poisk-turov-test/pull/1607), exact source `e15d9e67feb0e08d97dfd0255a75a8e89b245980`. The duplicate Search3 results presentation, card decoration and label owners were removed as whole modules; `results-top.js` is now a small route-visibility bridge. The canonical `v2/results-renderer-v5.js` remains responsible for result rendering, sorting, empty recovery and the real `.direct-tour` action.

Eight public assets are **78454 → 60811 raw bytes (−17643 B)** and same-method gzip is **22594 → 18093 (−4501 B)**. Complete loaded Search3 CSS/JS is **197067 → 179424 B**. Against the original 665247-byte route, the checked release has removed **485823 B (−73.03%)** and is **3.71× smaller**.

Security `34243761242` and exact artifact/core-browser run `34243761739` passed. Reusable artifact `10063127413`, digest `sha256:11adb12faeebc6402c15758e45696ba0e9c286e79d309f4da734287796f961fa`; browser evidence `10063126829`, digest `sha256:b1c8e44ab397f253fc41fd37938683111aa2e373376088a843613ee5c52db14a`. The deliberately narrow smoke rendered a result through the retained renderer, selected its actual action and reached detail/review/lead at 375 and 1440 with external requests blocked and zero leads sent. It first caught and then repaired an empty mobile rail overflow and desktop results remaining expanded under selected detail. Checked code release: `686cd04e26a7d07bed559d637301cf30b4aed7b2`.

Preview was not published and remains source `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main observed `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production was not changed. Manual visual acceptance, live current-source interaction and physical Safari/safe-area remain deferred.

Next: treat retained selected-flow JavaScript as one coarse package. Preserve price and pending arithmetic, URL/payload, Tourvisor/API, lead transport/mapping and analytics; do not spend a separate cycle on micro-reductions.

## 2026-09-08 — native entry owner retirement (#1610)

[#1610](https://github.com/pyatkoff/poisk-turov-test/pull/1610), exact source `ca42aa75d7558b902399b72f5135c3d52a4a0055`, removes the complete `entry-presentation.js` calendar/summary/timer-layout owner, the custom guest popup and obsolete mobile filter/trust wrappers. The original adult/child/child-age controls are directly editable; their nodes, values, names and catalog handlers remain. Native date/night controls, URL/payload, meals, price, API/Tourvisor, lead, analytics and all eight public paths are unchanged.

Fresh base `686cd04e26a7d07bed559d637301cf30b4aed7b2`: eight assets **60811 → 54605 raw B (new saving 6206 B)**; main JS **28806 → 22600 B**; loaded route **179424 → 173218 B**. Concurrent #1607 had already retired results while #1608 was being prepared; #1608 was closed unmerged and its candidate bytes are not counted. Checked code release after #1610 is `def1cf844aa1b9d15bb59219d8633ad4e1409314`.

One Security `34244914013` and exact artifact `34244914086` passed on the first source. Reusable artifact `10063598031`, digest `sha256:c5bbb4fa23c854381fc585fee8b06bbfa77135545d455a694ed06606b7768f38`; native-entry evidence `10063597221`, digest `sha256:90572d15f4f4a850fd73393fc5af6aeaa6e5a66e355b2a72582866d328bffd24`. Source build drift/malformed-input fixtures remain executable in the retained primary-controls module. Existing source/PHP/path/presentation/isolation and geometry conditions were not weakened. The exact presentation suite reports 7 active tests and 53 pre-existing skipped historical tests; this is not a claim that historical pixel assertions passed.

Actual Chromium checks: 375/1440 URL guest hydration, original adult/child/age nodes, changed FormData values, night range, native >=44px tap targets and no horizontal overflow. Both screenshots were inspected: controls and labels are visible; the intentionally stripped shell remains from the earlier reset. Catalog/image requests were blocked, no search/lead request was sent. This does not establish live data availability or owner visual acceptance.

Preview not published: source remains `c9ba79528ebc80c6803c9d7c5be6118f087b1eb3`. Main observed `47d6ccd0c324aceb4d6090fe53ffac03b9c41816`; production unchanged. Physical Safari, live current-source preview, owner acceptance and the full lead/responsive/site/SEO matrix are deferred. Audit: `docs/project/search3-entry-owner-retirement.json`.

Next coarse owner audit is complete: selected-flow is 18705 raw B and still mixes optional disclosures/mobile bars/trust with required no-flight retry/review, selected-open state and decimal-safe price labels. Retire its presentation only with that salvage; recheck parallel PRs before editing. No speculative selected-byte saving has been counted.

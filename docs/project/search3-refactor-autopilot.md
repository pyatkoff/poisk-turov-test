# Search3 technical refactor autopilot

Owner request recorded on 2026-09-06: «давай на автопилот поставь».

## Execution and activation are separate

This is the persistent task prompt for autonomous Search3 presentation refactoring. It does not create a scheduler, start a background coding process or authorize production publication.

The existing project records name a ChatGPT task «Продолжать разработку AnyTour», with hourly continuation in Europe/Amsterdam. Its current enabled state, task ID and next run have not been verified in this session: scheduler-management tools were unavailable. Reuse/update that existing task rather than create a duplicate. Keep its model/settings unless the owner changes them. Desired cadence remains hourly; do not describe it as continuous execution or claim activation without a scheduler confirmation.

GitHub issue #2 and autopilot-runtime-state.yml persist CI signals only. A successful CI run or this document is not evidence that a new coding session will start.

## Copyable task prompt

Продолжай автономный технический refactor-pass Search3 в репозитории pyatkoff/poisk-turov-test. Выполняй разработку, а не только мониторинг и отчёт. Работай по активной ветке release/search3-production-ready-v1 либо явно согласованной в #996 ветке-преемнику; текущий release draft — #1334.

В начале каждого запуска прочитай #996 с последними комментариями и #1334; затем AGENTS.md, OWNER_PRIORITY.json, AUTOPILOT.md, AUTOPILOT_STATE.json, src/search3/AGENTS.md, src/search3/README.md и этот документ ИЗ АКТИВНОЙ ВЕТКИ. Общий roadmap #1 — контекст, не повод перезапускать завершённые этапы. Search2/#810 и старые записи в main не являются текущей очередью. Устаревшие упоминания общего отложенного refactor не отменяют последующее разрешение владельца на ограниченный Search3 presentation refactor; оно не распространяется на защищённые контракты.

Последняя проверенная точка кода на момент записи: 0dc0efe6ab47508a2d99d09f02daffc195a3ad37. Перед изменениями заново проверь фактические head ветки/main, PR, CI и параллельную работу. Не откатывай ветку к этому SHA, если она уже продвинулась. Не перетирай чужие изменения, не force-push и не дублируй выполняющийся шаг/выкладку. При параллельном выполнении проверь его результат или выбери независимый безопасный пункт.

Уже завершены: source split девяти cascade-модулей на 03e7422e; удаление 127 cascade-деклараций и 60 пустых блоков на 91a7ff5d; удаление ещё 75 compatibility-деклараций и 17 опустевших правил на 2d4cd972; единый владелец мобильного фильтра и удаление orphan lifecycle/cascade на 53f482eb/35e2ae36; frame-coalescing price slider, одна final-count нотификация, отмена ожидающего price render при reset/sea/charter/fresh-source, повторное применение активных фильтров к свежим progressive results, подавление sort/same-reference filter events/DOM writes и сохранение смонтированных контролов при progressive refresh на 51ebb674/4679200a/35345fb1/35207333/b1b5791c/b72f7494/43856063/56099e49/f0198905. Устаревший silent-mode полной отрисовки удалён на a8ae0202; выбранный ценовой предел переживает временное сужение source bounds на 21bf320c; парные budget/flight поля очищаются на d66183a4; charter result/form state синхронизирован на 41c27412; пустой источник корректно объявляет смену фильтров на 6978435a; повторная пустая отрисовка не уничтожает исходный набор на 243d0515; Search3 price input отделён от legacy DS2 listener на 134611b5; неполный seaDistance facet скрывается и сбрасывается на 0dc0efe6. Пустой @media iteration1b уже удалён. Сохранены предыдущие JS event-coalescing, latest-price-wins, idempotent DOM/aria/dataset updates и кеш календаря. Не повторяй эти проходы. Прежний неудачный split filter-rail.js полностью отменён; не считать его выполненным.

Выбирай следующий шаг по реальному уменьшению сложности и риска, а не по числу коммитов. Следующий кандидат для анализа — оставшаяся синхронизация состояния filter-rail.js после смены/сброса источника; сначала подтвердить конкретную проблему и воспроизвести её focused regression, затем изменить один участок. Не повторять уже завершённые ownership, price-frame и fresh-announcement исправления, не разрезать IIFE вслепую и не добавлять глобальный scheduler или дублирующие observers. Альтернативный независимый шаг — доказуемые оставшиеся CSS-дубли в существующих owners. Разные media/specificity, shorthand, переменные и fallback не объявлять дублями без отдельного доказательства. Не добавлять код или тесты только ради активности.

Редактируй src/search3, затем используй python3 scripts/build/search3_assets.py --write и --check. Source, generated assets, нужные section contracts и production-import hashes коммить вместе. Сохраняй восемь публичных asset paths, порядок подключения, действующий дизайн, поисковое поведение и cascade. Сначала узкие локальные тесты, затем существующие применимые CI; для CSS/UI — релевантные responsive checks и осмотр визуальных материалов. Не отключай проверки и не создавай дублирующую CI-инфраструктуру. Не запускай вручную полный visual suite ради одной документации.

Не останавливаться после одного PR или коммита, если в текущем запуске есть возможность следующего безопасного шага. Сначала доводи блокирующие ошибки своего изменения до исправления либо безопасного отката, затем продолжай. При внешнем блокере запиши его и продолжи независимую безопасную работу. Требуемая физическая проверка Safari, юридические материалы и production approval не блокируют независимый разрешённый presentation refactor, но не могут быть объявлены выполненными автоматически.

Запрещено менять Tourvisor/API, расчёт цены, lead transport/маппинг, Метрику/цели, логотип и соседние проекты. Не отправляй реальные заявки. Разрешены ветки, коммиты, draft PR и CI. Не merge в main и не deploy production. Не запускай автоматический deploy как побочный эффект refactor-pass; отдельно разрешённый preview-процесс остаётся отдельной exact-SHA операцией с его существующими ограничениями. Не изменяй серверы через SentinelX в рамках этого задания.

После каждого существенного проверенного шага обновляй presentation_refactor_checkpoint в AUTOPILOT_STATE.json и handoff в #1334/#996, не заменяя историю публикаций результатами локальной сборки. Зафиксируй следующий конкретный шаг и границы. В итоговом отчёте укажи точный SHA, фактически выполненные изменения, результаты применимых проверок и общий размер/число строк четырёх CSS и четырёх JS из manifest без двойного учёта src/v2. Отличай «подготовлено», «CI пройден», «опубликовано в preview» и «проверено в production». Не обещай продолжение между запусками без реально включённого планировщика.

## Checkpoint metrics, not a live measurement

At code SHA 2d4cd972: Search3 CSS is 375,207 bytes / 4,268 physical lines; JS is 162,360 bytes / 1,928 lines. Total: 537,567 bytes / 6,196 lines across eight public assets, uncompressed, excluding shared runtime/legacy. Recompute after material code changes. These source metrics do not establish deployed size or measured page-load performance.

The code checkpoint passed 23 PR workflows, with one expected skipped workflow, as recorded in PR #1334. This handoff-only document makes no new runtime/visual/production verification claim.

## Verified filter-rail checkpoint — 2026-09-06

Exact code head: `0dc0efe6ab47508a2d99d09f02daffc195a3ad37`.

- `53f482eb` removed the unused mobile drawer lifecycle so the active mobile controls have one owner.
- `51ebb674` keeps slider labels immediate but coalesces rapid price filtering to one render per animation frame with the latest values.
- `4679200a` removed the second filter-change announcement after a fresh result source; `renderRail()` remains the single announcement owner.
- `35345fb1` invalidates a queued price render on both local reset and `v2:search-reset`.
- `35e2ae36` removed the corresponding orphan drawer cascade after the ownership regression proved those selectors had no owner.
- `35207333` cancels a superseded queued price render when a sea-distance or charter change immediately applies the same latest price state.
- `b1b5791c` reapplies active local filters when a fresh progressive result source arrives, so new unfiltered cards cannot leak into the visible set.
- `b72f7494` cancels a pending price frame when that fresh source already consumed the latest price state.
- `43856063` keeps sort/same-reference rerenders outside the public filter-change event contract.
- `56099e49` skips the redundant count/word DOM writes for that same-reference path.
- `f0198905` keeps the existing filter controls mounted during progressive source updates while synchronizing their price bounds and preserving the selected price.
- `a8ae0202` removes the now-obsolete silent full-render mode, leaving one explicit initialization/reset contract.
- `21bf320c` keeps the user's price limit separate from temporary source bounds, so a narrower intermediate result only clamps the displayed slider and the chosen limit returns with wider results.
- `d66183a4` makes “reset all” clear both form budget bounds and both flight constraints before the existing single desktop submit.
- `41c27412` restores the result-rail charter state from the form on search reset and mirrors local charter changes back to that form without adding another search request.
- `6978435a` keeps the active-filter count and public event synchronized when price, sea or charter changes while the current source is empty.
- `243d0515` treats an empty filtered output as a valid previous render, preserving the original hotels when that output is rendered again and the filter is later cleared.
- `134611b5` removes the legacy `data-ds2-price` opt-in from the Search3 slider, so the retained base listener cannot perform a second immediate filter pass before Search3's scheduled pass.
- `0dc0efe6` exposes the sea-distance facet only for a complete positive `seaDistance` payload and resets it before filtering when a progressive source becomes incomplete.

Focused price-input and filter-ownership regressions pass, the generated assets match their sources, and all 23 applicable PR workflows passed with one expected migration-only skip. Core run: `34017150380`; responsive visual run: `34017150461`, artifact `9984266598`, digest `sha256:667a923d59855e0cd524822f03c8c83c17699dc08a90fc15063f85788b16ae0c`; whole-site artifact run: `34017150371`, artifact `9984260200`, digest `sha256:0aad44f8d5b8c70fe2fec96795848b2b1cd9c644aeae2b817944f6d8af00b2a2`.

At this code head, the four public CSS assets are 366,267 bytes / 4,196 physical lines; the four public JS assets are 158,857 bytes / 1,921 lines. Total: 525,124 bytes / 6,117 lines, uncompressed, excluding shared runtime and legacy and without double-counting `src` and generated `v2` files. Compared with the `2d4cd972` checkpoint, the active eight-asset set is 12,443 bytes and 79 lines smaller; this aggregate includes both the JS work and the independently completed orphan CSS/lifecycle cleanup, so it is not attributed to one commit or presented as a measured page-load gain.

Status boundary: prepared and CI-verified only. This refactor pass did not publish a new preview, merge `main`, deploy production, alter Tourvisor/API, price arithmetic, lead transport/mapping, Metrika/goals, logo or neighboring projects. The next run must refresh the release head and prove a new independent problem before changing code.

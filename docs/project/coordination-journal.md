# AnyTour — текущий журнал координации

С20.10.2026 новый журнал — [#4709](https://github.com/pyatkoff/poisk-turov-test/issues/4709).
GitHub перестал принимать новые комментарии в #4217 после2500; старый журнал
сохраняет все сообщения, решения, evidence, reservations и no-replay. Исторические
#3419/#2530/#996 также остаются источниками прежних ограничений. Все незавершённые
claims и зависимости наследуются без истечения по времени и без переавторизации.

## Рабочие очереди

| Направление | Текущая очередь | Рабочая основа |
| --- | --- | --- |
| SEARCH / Search3 | #1646 | release/search3-production-ready-v1; текущий product plan |
| INT / ANEX / Andromeda | #2506, umbrella #1493 | feature/anex-search-adapter-20260907; scoped ANEX autopilot |
| MATCH | #1971 | тот же INT source; MATCH-план на свежей release |
| LOCAL | #2690, #4191 | release; актуальное задание и его safety/review ограничения |
| SITE | #1719 | release |
| SEO | #1720 | release |

Это смена адреса общей координации, а не новая очередь. Перед каждым пакетом:
свежие head/PR/CI, #4709, применимые прежние #4217 claims/receipts и своя очередь.
Один writer на точные общие paths; возраст claim не освобождает файл или publisher.
При блокере записать причину/следующий шаг и продолжать независимую разрешённую работу.
Source prepared, CI checked, merged, preview published и live verified — разные статусы.
Датированные snapshots/next_action и закрытые операции не перезапускаются.

## Cutover исполнителей

Авторитетный control code — только свежий main. План или новый журнал сами
не меняют event gate. Для каждой новой команды проверить фактически слитый
cutover именно её существующего workflow/controller и свежие claims/receipts.

| Команда / компонент | Новый журнал | Проверка допуска |
| --- | --- | --- |
| /run-int-server-v1; постоянный INT/MATCH/LOCAL carrier | #4709 после main cutover | int_server_executor.py, int_funsun_direction_store_server_readback.py и оба существующих workflow filters; int_server_executor_test.py отвергает новый вызов из4217/3419/2530/996 |
| SEARCH publishers / inspect / restore / preview creators | Проверить собственный актуальный gate | Не считать автоматически перенесёнными: их прежний4217 gate остаётся закрытым к4709 до отдельного штатного review/CI/merge/readback соответствующего владельца |

Сохраняются owner ID226193297, OWNER association, created-comment-only/noPR,
main/ref/actor guards, точные source/build/artifact/predecessor pins, locks,
reservations, no-replay, allowlists и защищённые пути. Существующий workflow_dispatch,
где он был, не расширяется. Перенос не создаёт нового carrier, секретного канала,
provider/auth/transport/DB/money/lead/production разрешения или supplier запроса.
Старый4217 для новых постоянных INT-команд отклоняется; исторические receipts
и comment IDs остаются читаемыми по исходным URLs и не переписываются.

Старые одноразовые ANEX preview/root-gateway/install/fixed probes/Andromeda
publish/readback/restore/canary контуры остаются привязаны к своим завершённым или
UNKNOWN операциям и историческим журналам. Их IDs, source/artifacts, timestamps,
команды и результаты сохраняются без нового допуска. Переименование, новое issue
или час запуска не обходит отказ/consumed marker. Если готовое действие упёрлось
только в технический comment cap ДО создания event/серверной reservation, сначала
доказать эту границу по фактам и затем выполнить обычный допуск на фактически
мигрированном carrier; не перезапускать старый workflow attempt и не обходить guard.

## Автоматизации

Новые запуски читают актуальный #4709 и унаследованные #4217 claims/receipts.
Сам перенос документа не переключает prompt/enabled/paused/schedule/timezone;
изменения своего задания фиксируются отдельно. Приостановленные задания не
возобновляются автоматически; другие проекты и расписания не меняются.
До новой серверной команды подтвердить cutover, свежие ownership/pins/receipts.
Решения о поставщиках,5000account/day HTTP, UNKNOWN, БД, цене/топливе,
лидах/аналитике, production и визуальной приёмке остаются в исходных допусках.


## Dedicated Search3 signing custody (23 October 2026)

The owner's explicit narrow producer/signing authorization continues constructor1095;
producer4839 SOURCE is merged at FEATURE5c83c4134f8f1c3aeec24be1713736cd058fc6d3.
The existing main-only INT executor registers `search3-signing-provision` and
`search3-signing-readback`, using its same owner/ref/source/SSH/reservation/no-replay guards.
Command shape remains `/run-int-server-v1 <current-FEATURE-SHA> <mode> <operation>`;
operation must start `int-anex-search3-signing-` and satisfy the existing versioned ID grammar.
No caller path, key, URL, lifetime, provider budget or execution code is accepted.

Provision creates one dedicated Ed25519 key only inside existing protected server custody,
with0700 directory/0600 key file,90-day fixed validity and no silent rotation. It appends only
an absolute key-file pointer to the existing protected ANEX config, after lint and exact
before-byte checks; unrelated credentials and mode are retained. Backup/staged config remain
outside the web root. Dynamic/conflicting/unreachable pointer configurations fail closed.
Readback never creates/rotates a key or patches config. Repeated/UNKNOWN operation IDs retain
stock no-replay; use a separately admitted readback to reconcile, not another create blindly.
Only bounded public verification material appears in the receipt; private bytes never cross
SSH stdout, artifact or repository. No supplier, model, DB, booking, bot or customer call occurs.

Source/control CI does not provision a key or activate1095. After reviewed main cutover, perform
one admitted custody operation, inspect the public key, pin it in receiver1095, and install the
source dependencies/API through stock gates before connected owner-only native acceptance.
This is an addition to the current4709/same existing package, not a new executor or roadmap.

## Bounded Search3 producer installation continuation (23 October 2026)

Custody operation `int-anex-search3-signing-20261023-v1` is CONSUMED/RELEASED
(#4709/6375455463); do not provision, rotate or replay it. Receiver1095 pins only
its public material. INT4841 expiry correction is SOURCE/CI/MERGE complete at
FEATURE `b1a69c5721697a8f8c1867a445e7f2292dbe1b93` (#4709/6375482102).

Same constructor dependency, claims6375540628/6375591789, exact three CONTROL paths:
`int_server_executor_search3_signing.py`, its existing native test, this journal.
The registered `install-search3-producer` mode reuses `install_anex_preview` staging,
backup, lint, dependency-first writes, hash readback and stock rollback. Fixed reviewed
SHA256 pins admit only quote-runtime, canonical4838 freezer, producer4839+4841 and API;
the initial-week gate must already match its reviewed source and is never rewritten.
The API is last. Unrelated integrations, UI, key/config, DB, supplier and lead paths
are excluded. No caller path/hash list or second installer/transport is accepted.
Use existing command shape with current FEATURE SHA and an operation starting
`int-anex-search3-producer-install-`; all main/owner/ref/source/SSH/concurrency and
reservation/no-replay guards remain. A source/main merge does not prove installation.

The independent conditional-pointer finding6375414688 is resolved in the same
custody admission: an existing pointer counts only as the canonical generated
top-level guarded tail; conditional/function/plain definitions fail before writes.
The new native negative fails against original main, then passes with the fix.
Actual provision receipt created=true/pointer_configured=true followed the append
path; no production incident is inferred and no real custody operation is repeated.
Native synthetic stock tests cover narrow install, existing dependency/source drift
and postwrite failure restoring old files/removing new files. Current receiver CI,
controlled runtime install and connected SEARCH/native acceptance remain separate gates.

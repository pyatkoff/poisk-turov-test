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

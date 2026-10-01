# AnyTour — текущий журнал координации

С 2026-10-01 канонический журнал — [#4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217).
Предыдущие #3419 и #2530 (достигли 2500 комментариев) и #996 остаются историей и источником
решений, evidence, reservations и no-replay. Новые сообщения туда не направляются.

## Рабочие очереди

| Направление | Текущая очередь | Рабочая основа |
| --- | --- | --- |
| SEARCH / Search3 | #1646 | release/search3-production-ready-v1; текущий product plan |
| INT / ANEX / Andromeda | #2506, umbrella #1493 | feature/anex-search-adapter-20260907; scoped ANEX autopilot |
| MATCH | #1971 | тот же INT source; MATCH-план на свежей release |
| LOCAL | #2690, #4191 | release; актуальное задание и его safety/review ограничения проверять отдельно |
| SITE | #1719 | release |
| SEO | #1720 | release |

Это смена адреса общей координации, а не новая очередь. Перед каждым пакетом:
свежие head/PR/CI, сообщения #4217 и своей очереди, унаследованные claims и receipts.
Один writer на точные общие paths. Возраст claim не освобождает файл или publisher.
При блокере записать причину/следующий шаг и продолжать независимую доступную задачу.
Состояния source prepared, CI checked, merged, preview published и live verified
фиксируются отдельно. Датированные snapshots/next_action не перезапускаются.

## Действующие повторно используемые исполнители

Авторитетный control code находится только в свежем main. Cutover считается
выполненным после merge и readback main; запись в плане сама не переключает gate.

| Команда | Workflow | Gate / существующий тест |
| --- | --- | --- |
| /publish-search3-preview | deploy-search3-whole-site-preview.yml | search3_preview_publish.py / search3_preview_publish_test.py |
| /restore-search3-lead-route | тот же workflow | search3_lead_route_repair.py использует COORDINATION_ISSUE из общего publisher |
| /create-search3-local-preview | create-search3-local-preview.yml | search3_local_preview.py / search3_local_preview_test.py |
| /create-search3-v18-preview | create-search3-v17-preview.yml | search3_v17_preview.py / search3_v17_preview_test.py |
| /update-search3-local-preview | update-search3-local-preview.yml | search3_local_preview_update.py / search3_local_preview_update_test.py |
| /inspect-search3-local-preview | inspect-search3-local-preview.yml | search3_local_preview_inspect.py / search3_local_preview_inspect_test.py |
| /run-int-server-v1 | int-server-executor.yml | int_server_executor.py / int_server_executor_test.py |

Issue-comment gates принимают только #4217 и отклоняют #3419/#2530/#996. Сохраняются
owner ID226193297, main-only/ref/actor/event guards, точные source/build/artifact/
predecessor pins, locks, reservations, no-replay, allowlists и защищённые пути.
Существующий workflow_dispatch, где он был, не расширяется и не является обходом.
Перенос не запускает ни публикацию, ни серверную операцию, ни supplier запрос.

Старые одноразовые ANEX preview, root-gateway install, fixed SEARCH probes и Andromeda publish/readback/restore/canary
контуры привязаны к своим завершённым или unknown операциям и историческим журналам.
Их команды, operation IDs, evidence URLs, comment IDs, exact pins и checkpoints
сохраняются без переавторизации в #4217. Это не исполнители новой очереди.
Для следующей обоснованной операции сначала проверить текущий разрешённый контур,
предыдущий результат и no-replay; новое имя или перенос журнала не обходят отказ.

## Автоматизации

Существующие задания этого репозитория читают #4217 вместо прежнего текущего
журнала. Расписания и enabled/paused состояния сохраняются; перенос сам не
возобновляет приостановленные задания. Другие проекты и расписания не меняются. Для новых серверных команд
сначала подтвердить cutover штатного executor и свежие ownership/pins/receipts.

Решения о поставщиках, расходах/лимитах, БД, ценах/топливе, лидах, аналитике,
production и визуальной приёмке остаются в исходных разрешениях. Их перенос не меняет.

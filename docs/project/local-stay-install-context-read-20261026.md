# LOCAL: CURRENT context после отказа protected-file guard

Павел26.10.2026 одобрил создание трёх пустых hotel-stay V2 таблиц и включение
LOCAL-автопилота. PR4910 прошёл native CI/review и слит в MAIN
`d02f258b4cfe854f4c69715758d0966d38d46197`. Оригинальное применение остановлено
до PHP/DB/DDL: [terminal6404352448](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6404352448).
Эта READ-only диагностика устанавливает реальные новые факты о CURRENT контексте;
она не повторяет старую команду и не разрешает новое применение миграции.

## Исходный отказ

Command6404234353, run40240095415/attempt1, execute121309848173, операция
`int-andromeda-local-stay-schema-install-20261026-v1`. Literal status
`unknown_no_replay`, reason `local_stay_install_private_drift` сохраняются.
Точный исполнявшийся source вызывает этот guard до проверки parent и запуска PHP.
Typed schema ACK0/outcomeUnknownfalse и остальные call/write0 это подтверждают.
Таблицы этой операцией не создавались; прежняя диагностика не доказывает CURRENT
отсутствие таблиц. Production-after и текущие file hashes не были получены.

| Неизменяемый источник | Байт / SHA256 |
| --- | --- |
| Исходный failed private result | 624 / `fbf6bd8808816daa6b40c7ac1647c0ccf6ecb0f9157d8feaae36ce31647b8921` |
| Тот же host artifact JSON | 717 / `1b8d720e58e6ee7d5d7fa33854a69d9232e5a2111805ea0c4d5344249628a73e` |
| Предыдущий accepted READ private result | 7079 / `96e41780998f973f109674d40e6aec9f6d175a769d1a1ff21e8d6b536a706f86` |

Сначала проверяется эта точная lineage, исходные operation/source/control pins,
причина отказа и zero-DDL boundary. Старые schema-read, anchor и install программы,
их hashes, completion predicates и consumed markers сохраняются побайтово.

## Новый фиксированный READ

Mode `local-stay-install-context-read-v1`, операция
`int-andromeda-local-stay-install-context-read-20261026-v1`, тот же существующий
`/run-int-server-v1` в #4709. Ни SQL, пути, credential, список таблиц, лимит,
apply или replay не принимаются от caller. Workflow и transport не расширяются.

Новый результат содержит CURRENT hashes фиксированных `config.php` и
`data/db-v1.php`, различия относительно принятого READ и новые context facts.
Изначально известно лишь расхождение одного или обоих protected files.
Не делается вывод о JWT rotation, причине изменения или виновном владельце.

Если root DB helper отличается от принятого SHA
`ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4`, результат
остаётся hash-only HOLD до PHP. Неизвестный helper не исполняется и не заменяется.
Если helper совпадает, исполняется принятый staged helper с CURRENT root config
обычным `v2_data_db()`: только metadata пяти таблиц через неизменённый read runner
SHA `d0dc691633975ae7e0c48af13aa9ffaf5c5a5014c766c50826c4dff393808a83` и новый
hash configured DB identity (selected database/server UUID). Это новый факт,
исторический READ не сохранял такой identity hash.

CURRENT hashes и connection identity проверяются до/после чтения. Config content,
credential, DSN, account, grant text, raw SQL и application rows не экспортируются.
Supplier/search/quote, DDL/DML, profile/mapping/lead/booking и automatic accepts — 0.
Config, grants, schema, public файлы и старые receipts не изменяются.

MAIN содержит новый context runner и принятый read runner; RELEASE — pinned
SQL/helper. До extraction допускаются ровно четыре файла и manifest. Private
exclusive0600 receipt включён в штатный result.json; при принятом helper он
совпадает с canonical PHP stdout. Hash-only HOLD формирует Python без PHP/stdout.
Unknown/held/completed context не становится автоматическим install admission.

## Дальнейшая граница

Новый context отвечает, какой protected file изменился и каков реально configured
CURRENT target/schema, если accepted helper позволяет безопасное чтение. Причина
изменения остаётся неизвестной без отдельного доказательства.
После этих фактов требуется точный HIGH/apply disposition для исходных трёх CREATE;
старый отказ не обходится удалением pin, переименованием операции или другим carrier.
Никаких дополнительных таблиц, переустановки, ALTER/DROP или импорта здесь нет.

Обязательный existing native CI проверяет lineage/typed0/archive closure,
helper-drift HOLD до PHP и фактическое read-only MySQL context при принятом helper.
Старые30cases сохраняются; добавлены4 Admission и4 native context cases.
Все20 локальных Admission cases прошли. Локального PHP нет; native PASS только
из actual CI. Все3 прежних activated REMOTE программы побайтово равны MAINd02f.
Source/CI/merge/runtime context и установка/наполнение — разные результаты.

LOCAL-автопилот `6ade7d3053308191b73bb488b3552387` остаётся enabled/HOURLY/
Europe-Kaliningrad. Его checkpoint следует этому read-only claim6404352655 и
реальным terminals; чужие задачи и расписания не меняются.

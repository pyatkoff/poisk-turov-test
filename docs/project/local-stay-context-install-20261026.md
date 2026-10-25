# LOCAL: три пустые таблицы по новой CURRENT-context admission

Павел 26.10.2026 одобрил создание ровно трёх пустых hotel-stay V2 таблиц.
Независимый HIGH planning review подтвердил, что новое применение может опираться
на фактический CURRENT context без дополнительного вопроса пользователю.
Claim: [6404594383](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6404594383).
Подготовка кода не резервирует MAIN/release/server/NEXT; runtime требует отдельного
короткого окна после exact-source HIGH review и обязательного native CI.

## Неизменяемая история и новые факты

Исходная операция `int-andromeda-local-stay-schema-install-20261026-v1`,
run40240095415, завершилась `unknown_no_replay` / `local_stay_install_private_drift`
до PHP/DB/DDL. Её код, pins, receipts и consumed operation не меняются и не
повторяются. Причина и конкретный файл прежнего расхождения не установлены.

Отдельный CURRENT READ run40242306311/attempt1/execute121316521883 завершён и принят:
[terminal6404576964](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6404576964).
MAIN `af256ad98636480ee1e62c59808bad2a612d6420`, observedAt1792968926.
Тогда оба protected files совпали до/после и с исходным READ, `changedFiles=[]`;
это не доказывает причину прежнего отказа или историю восстановления файлов.
Схема содержит два совместимых InnoDB parent и физически не содержит три target.
Впервые измерен hash CURRENT configured database/server UUID; identity старого
READ остаётся неизвестной. CURRENT context имеет `applyAuthorized=false`.

| Фиксированный вход новой admission | Байт / SHA256 |
| --- | --- |
| CURRENT context private result | 8528 / `21ce19017ad4211a67d8f9e053d2ed033cfa3f5350493d0797084eb31867f1b0` |
| CURRENT context inner receipt | `4f60fd91f95774b08e5b44611a20c6202d989eb96fda9a573a011101bb0ef043` |
| CURRENT DB identity | `4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d` |
| Исходный failed private result | 624 / `fbf6bd8808816daa6b40c7ac1647c0ccf6ecb0f9157d8feaae36ce31647b8921` |
| Исходный READ private result | 7079 / `96e41780998f973f109674d40e6aec9f6d175a769d1a1ff21e8d6b536a706f86` |
| Root config.php | `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def` |
| Root data/db-v1.php и staged helper | `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4` |
| Миграция | `1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06` |
| CURRENT inventory / plan | `8a37c3c6a3cd4894afff942268d6c669f1ae81f7403253bc58f0b8ae5f38c32e` / `6f1c556fe1a6bf3182f3a49686850f6fcb479cf94e03210881a9faf216b603f6` |

## Новая операция

Mode `local-stay-context-install-v1`, operation
`int-andromeda-local-stay-context-install-20261026-v1`, fixed SOURCE
`30a7e7013edd7fe70a4700db0aafb2bf22ee05f8`. Прежний `/run-int-server-v1` в #4709;
owner/main/source/ref/SSH/first-attempt/reservation/no-replay/production guards
сохранены. Ни SQL, target, expected identity, credential или apply flag не
принимаются от caller. Workflow и transport не меняются.

Новая admission проверяет целиком immutable CURRENT context и его completion,
исходный отказ с доказанным нулём DDL и исходный READ. Она непосредственно
привязана к новой DB identity, а не переименовывает прежний installer или заменяет
его pins. Все четыре прежних режима и generated REMOTE программы неизменны.
Закрытый bundle: новый runner и прежний READ из MAIN; SQL/helper из RELEASE;
ровно четыре файла и manifest до extraction. Старого installer в bundle нет.

Одна PDO и advisory lock охватывают fresh preflight и три CREATE. До первой DDL
проверяются CURRENT identity, protected hashes, точные определения parent,
физическое отсутствие всех трёх target, необходимые privileges и visibility,
constraint-name collisions, DYNAMIC/page capacity, CHECK enforcement и session
guards, migration/source hashes. Несовпадение завершает операцию до CREATE.
Raw config, credentials, DSN, account, grant text и application rows не выводятся.

Порядок literal CREATE из неизменной миграции:

1. `anytour_hotel_room_concepts_v2`
2. `anytour_hotel_meal_concepts_v2`
3. `anytour_hotel_stay_mappings_v2`

Новый private exclusive0600 fsynced journal сохраняет intent до каждого CREATE,
ACK после возврата и terminal. Success требует три подтверждённых CREATE,
точную итоговую схему, ноль строк в каждой новой таблице, неизменные parent,
identity/protected/public hashes и совпадающий private receipt/stdout/result.
Schema ACK считаются отдельно от application/database writes.
Mismatch, partial или потерянный ACK сохраняет фактические ACK/unknown и
останавливает дальнейшие CREATE. Никаких resume/retry/DROP/repair/rollback,
IF NOT EXISTS, ALTER, импорта, DML, supplier/profile/mapping/lead/booking действий.

Native CI проверяет новый target-bound gate, реальные три пустые CREATE и
потерянный ACK, а также сохранность прежних режимов и штатных guards.
Старые38 Python cases сохраняются; добавлены4 Admission и4 native cases.
Все24 локальных Admission checks прошли. Native stock cases проверяют pre-PHP
lineage/protected/archive/no-replay; реальные DB/DDL semantics проверяет отдельная
pure-PHP native suite с явной fixture identity. Staged production constants не
подменяются; runtime CLI не имеет такого override.
Локального PHP нет; native PASS существует только из фактического CI.
Source/CI/merge не означают создания таблиц или новых concepts/mappings.

LOCAL-автопилот включён HOURLY / Europe-Kaliningrad; его checkpoint следует
foreground claim и терминалам, не дублирует текущий пакет. Чужие задачи и
расписания не меняются.

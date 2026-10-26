# LOCAL: permission-bound создание трёх пустых hotel-stay V2 таблиц

Claim: [6406727041](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6406727041).
Основа MAIN `b4fc7c3c00c56ba205ade7dabfc214490d8de279`, SOURCE
`1d0e529e3fc0666783db1454c0765d3c16d63843`. Независимый HIGH planning
принят до writer. Павел 26.10.2026 уже явно разрешил создание ровно трёх
пустых V2 таблиц; новый вопрос для того же scope не требуется.

## Новая материальная admission

Старые installer operations PR4910 и PR4912 завершены и sealed. Их нельзя
повторять, переименовывать, перепривязывать или использовать как runtime entry
point. PR4912 не выполнил DDL: ACK=0, partial=false, outcomeUnknown=false.
Его прежний privilege predicate также не становится новой authority.

PR4916 впервые завершил отдельный context-bound permission READ и фактически
связал текущие DB/account/role с полным положительным доказательством четырёх
прав. Это новый immutable input, а не изменение истории прежнего отказа.

| Фиксированный вход | Байт / SHA256 |
| --- | --- |
| PR4916 private result | 8400 / `ab218f78c62e1d57e9d6be54203134adedb07c382ad949719b130e0e9d4b031e` |
| PR4916 wrapper receipt | 5995 / `33d60c6a1f580b965547982f49d10c3a956bcb0c24fa19c7b94d128fdb76777b` |
| PR4916 engine result | 3626 / `c15febf3673ebefc1169e704b3b2e8eba3e1faf4962fb07c4ec637d6cc0ab068` |
| Database identity | `4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d` |
| Account identity | `7ef350d330c1c4e04d1232ba7649b559d85a4930a512746585cba4aadb9d4ef8` |
| Role binding | `08f51cfad4b88bfff85676aac5da3d61ddacc7612f6106db7759ee5fff1411d0` |
| Permission coverage | `f4fea51dd60b2e3944e4ab9bde80e4d5ba2fd8d8d12dda907f9b45a613a24a5b` |
| Permission metadata | `fc2221f539a7fa83c66ad2fda24922e0bbf34b87bb97cddc2bd10e83ec17c59c` |
| Protected CURRENT context | 2930 / `49656487d2db323519ce915584001a948408765e72cfa73bfabdb16c81fdfeea` |
| Refused permission parent | 573 / `356a4d731d4a9d6f38045ea9c27ed71dcd07e87a525f7af942a1abccfcb2d005` |
| RAW source-facts parent | 3429 / `0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817` |
| Root config / helper | `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def` / `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4` |
| SQL migration | `1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06` |

В принятом PR4916 receipt CREATE, REFERENCES, SELECT и TRIGGER имеют
`status=proven`, `coverage=complete`, `basis=single_literal_schema`.
Database/account/role/protected/metadata стабильны до и после чтения.
`ddlAdmission=false` в READ остаётся неизменным: запись допускает только новая
операция, точный owner scope и все свежие preflight guards вместе.

## Новая операция

Mode `local-stay-permission-install-v1`, operation
`int-andromeda-local-stay-permission-install-20261026-v1`, тот же постоянный
`/run-int-server-v1` в #4709. Workflow, transport, auth, owner/main/source/ref,
SSH, reservation, quota, no-replay и production guards не меняются.

Закрытый bundle содержит новый writer, неизменные MAIN
`AnyTourHotelStaySchemaReadV1`,
`AnyTourHotelStayContextPermissionReadV1` и
`AnyTourHotelStayPermissionProofReadV1`, а также неизменные SOURCE migration
и helper плюс manifest. Старые installer CLI/operations/REMOTE не входят и не
вызываются. Read-классы используются только через чистые `::read()`.

Одна PDO и фиксированный advisory lock охватывают fresh permission proof,
fresh schema read и все DDL. До первой DDL и перед каждым CREATE заново
проверяются:

- точные DB/account/role/protected bindings и полный положительный proof всех
  четырёх прав с принятыми coverage/metadata hashes;
- два совместимых InnoDB parent и физическое отсутствие всех трёх target;
- CHECK/trigger visibility, migration/source pins, отсутствие 14
  case-insensitive constraint-name collisions;
- DYNAMIC row format, достаточный page size, включённые foreign-key и unique
  session guards, отсутствие caller transaction.

Порядок единственных разрешённых DDL:

1. `anytour_hotel_room_concepts_v2`
2. `anytour_hotel_meal_concepts_v2`
3. `anytour_hotel_stay_mappings_v2`

Exclusive 0600 fsynced journal фиксирует immutable start, intent до попытки,
возврат exec, ACK и terminal. Потерянный ACK, mismatch или partial сохраняет
точные attempted/created/unknown списки и останавливает дальнейшие CREATE.
Нет resume/retry, DROP, repair, rollback, ALTER, IF NOT EXISTS, импорта или DML.

Success требует ровно три ACK, точную итоговую схему, ноль строк во всех трёх
новых таблицах, неизменные parent/protected/DB/account/role и повторно полный
permission proof. Все supplier/profile/mapping/application/lead/booking
вызовы и записи равны нулю. Schema ACK считаются отдельно от application writes.

### Неизменный raw SchemaRead и qualified postflight

Неизменный SchemaRead использует более узкий historical predicate прямой
`TABLE_SCHEMA=DATABASE()` для visibility TRIGGER. Принятый текущий proof
доказывает database-wide право через корректно разобранный
`single_literal_schema`. Поэтому после правильных CREATE raw SchemaRead может
честно вернуть `blocked_server_capability`, где единственная issue каждой
target — `trigger_visibility_not_proven`.

Raw inventory и plan сохраняются в receipt без изменений и никогда не
переписываются в `ready_no_op`. Новый writer отдельно ставит qualified
postflight success только когда fresh proof на той же PDO до и после raw
наблюдения подтверждает те же DB/account/role/protected/coverage/metadata,
все четыре права остаются complete+proven с database-wide TRIGGER, родители
неизменны, CHECK supported, все три target имеют точную структуру,
`triggerCount=0`, ровно singleton issue
`trigger_visibility_not_proven` и integer0 rows. Обычный raw
`ready_no_op` путь также сохраняется.

Дополнительная issue, фактический trigger, incomplete/table-only право,
binding/protected drift, missing/nonempty target или потерянный ACK запрещают
qualification и оставляют `verification_failed` либо `ddl_unknown` с
фактическими ACK/unknown списками.

## Проверка и предел результата

Native fixtures обязаны проверить реальный escaped-schema four-right proof,
успешные три пустые CREATE с raw blocked + отдельным qualified success,
обычный raw-ready путь, неправильные DB/account/role/proof/protected
bindings, drift parent/target/collision/session/capacity, existing journal
no-replay, дополнительную structural issue/реальный trigger,
incomplete/table-only TRIGGER, nonempty target, lost ACK и post-DDL
proof/protected failure без ложного обнуления известных ACK. Старые programs,
pins, operations, receipts и stock cases
сохраняются побайтово. Перед runtime обязательны independent exact-source
review и exact-head native CI.

Код, тесты, CI и merge не равны фактически созданным таблицам. Только новый
terminal runtime receipt с тремя ACK и строгим post-readback может дать
`installed_empty`. Даже после schema success новые profiles/concepts/mappings
остаются нулём, пока существующий producer не передаст настоящую hotel/operator
scoped retained tuple с accepted bridge, raw room/meal facts, payload SHA и
временем. Identity и qualifiers не угадываются.

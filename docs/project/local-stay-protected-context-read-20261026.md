# LOCAL: текущее protected-context наблюдение

Claim: [6405841682](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6405841682).
Основа MAIN `12e29deac85ad6d9cce32734797716bf751ef7ca`, SOURCE
`1d0e529e3fc0666783db1454c0765d3c16d63843`.

PR4914 прошёл source review и exact-head native CI: 62 LOCAL cases и163 новых
PHP/MySQL checks. Однако его единственная runtime-команда6405770466/run40251257850
остановилась с `unknown_no_replay / local_stay_permission_private_drift`.
Проверка protected hashes предшествует PHP/PDO/DB: эти стадии не исполнялись.
Какой файл отличался и почему, receipt не сохраняет. После этого отказа текущие
DB/account identity и права не измерены. Операция consumed/no-replay, все leases
освобождены: [terminal6405809211](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6405809211).

Новый пакет отвечает только на отдельный вопрос о **текущих** двух файлах.
Независимый HIGH planning принял этот scope до начала writer. Он не вызывает
permission-reader, не меняет его guard и не подставляет новые expected hashes.

## Неизменные входы

| Вход | Связь |
| --- | --- |
| Последний отказ | private573B / `356a4d731d4a9d6f38045ea9c27ed71dcd07e87a525f7af942a1abccfcb2d005` |
| Отказавшая операция | `int-andromeda-local-stay-permission-proof-read-20261026-v1` |
| Literal terminal | `unknown_no_replay / local_stay_permission_private_drift`, все typed calls/writes0, без permission payload |
| Полный RAW parent | private3429B / `0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817` |
| RAW inner/control | `1b26f11a7fbca04f7ce9ffcaeb9c41a849948a98301284a6c4ca3ccf9e79dea4` / `436cf6f1bd85f062dd7b8ff42292f80f8232a4b5` |
| Исторический config.php | `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def` |
| Исторический data/db-v1.php | `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4` |

До наблюдения проверяются точные bytes/hash/shape отказа и полный принятый
исторический completion predicate RAW. Историческая DB4cd остаётся историческим
полем parent, не свежим доказательством configured target.

## Конечный scope

Mode `local-stay-protected-context-read-v1`, operation
`int-andromeda-local-stay-protected-context-read-20261026-v1`, тот же permanent
`/run-int-server-v1` в #4709. Caller не задаёт пути, hashes, SQL, account или apply.
Bundle закрыт до extraction: только неизменный SOURCE helper и manifest.
Helper нужен для stock carrier bundle и никогда не исполняется.

Python читает и хеширует ровно ROOT `config.php` и `data/db-v1.php`, каждый не более
2MiB, с проверками regular file, symlink и разрешённого пути. Два независимых
снимка сравниваются друг с другом и с immutable accepted baseline. Экспортируются
только производные hashes/statuses и имена из фиксированной пары. Никаких raw
contents, credentials, config values, DSN или SQL. Никаких subprocess, PHP,
helper execution, PDO, DB, account/role/grant queries или application rows.

Стабильные hashes могут совпасть с baseline или отличаться от него. Это только
наблюдение CURRENT: `changedFiles` не устанавливает, какой файл вызвал прежний
отказ и когда/почему он менялся. Даже `changedFiles=[]` не объясняет старый отказ.
Между двумя снимками drift либо недоступность файла дают terminal HOLD, не authority.

Receipt связывает оба immutable inputs, current control/source/op, baseline,
before/after hashes/statuses и различия. Exclusive private0600/fsynced bounded JSON
связан с outer result. PHP/helper execution и DB connection явно false;
current DB/account identity и permission proof — NOT_MEASURED. Price/apply/DDL
admission всегда false. Нет repin, GRANT/SET ROLE, test CREATE, DDL/DML, supplier,
profile/mapping/lead/booking/import/publisher/UI действий.

Все прежние семь activated programs/pins/operations/receipts и62 test cases
сохраняются. Owner/main/source/ref/SSH/actor/first-attempt/reservation/no-replay
и public-file guards прежние. Новый source пакет требует final exact-delta review
и existing mandatory native CI с file fixtures перед коротким merge→ONE READ окном.
MAIN/release/private-server freeze не удерживается при подготовке.

Этот результат не разрешает повтор permission operation или создание таблиц.
Будущие permission/apply действия требуют своей материально новой admission и
фактических доказательств; прежнее owner approval ровно трёх пустых таблиц сохраняется.
Содержательные room/meal concepts отдельно ждут retained tuple существующего
producer с hotel/bridge/provider/operator/rawroom/meal/payloadSHA/time evidence.
Код/CI/merge не означают installed schema или новые профили/concepts/mappings.

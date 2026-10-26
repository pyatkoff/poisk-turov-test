# LOCAL: CURRENT-context-bound permission READ

Claim: [6406139418](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6406139418).
Основа MAIN `fbb814c3460dad4a72eb8ea235801d1b8cc950eb`, SOURCE
`1d0e529e3fc0666783db1454c0765d3c16d63843`.
Независимый HIGH planning принят до writer. Новая admission требует фактически
полученного CURRENT protected-context receipt; прежняя операция4914 закрыта.

## Основание нового scope

PR4914 завершился `unknown_no_replay / local_stay_permission_private_drift`
до PHP/PDO/DB. Его причиной не объявлены ни конкретный файл, ни восстановление
config, ни права. Затем отдельный PR4915 действительно измерил текущие hashes
двух ROOT-файлов: до/после они стабильны и совпадают с accepted baseline,
`changedFiles=[]`. Он не подключался к DB и не измерял account/permissions.
Оба результата consumed/no-replay, все их leases освобождены.

Этот пакет потребляет **все три полных immutable результата** до наблюдения:

| Результат | Private bytes / SHA256 |
| --- | --- |
| CURRENT protected-context, controlfbb814 | 2930 / `49656487d2db323519ce915584001a948408765e72cfa73bfabdb16c81fdfeea` |
| Прежний отказ permission, без PHP/DB | 573 / `356a4d731d4a9d6f38045ea9c27ed71dcd07e87a525f7af942a1abccfcb2d005` |
| RAW source-facts parent, historical control436 | 3429 / `0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817` |

CURRENT inner1906B имеет SHA
`a65936a8731590baf63a4fa0657a860a5f583e6e84c2d43d186bbf9e29900315`.
Проверяется полный принятый completion predicate CURRENT и RAW, точный shape
отказа, их SHA и lineage. Это не caller-supplied expected hashes и не
перепривязка старого действия.

## Новый producer и честные фазы

Mode `local-stay-context-permission-read-v1`, operation
`int-andromeda-local-stay-context-permission-read-20261026-v1` через тот же
permanent `/run-int-server-v1` в #4709. Закрытый bundle: новый MAIN wrapper,
неизменный MAIN `AnyTourHotelStayPermissionProofReadV1` и SOURCE helper; плюс
manifest. Caller не задаёт SQL, DB/account, credentials, hashes или apply.

Старый PHP-файл подключается только как библиотека чистого `::read()` с SHA
`1e9b8a15f0418e467380bcfe63368e8e4734b76a1deb9d95d6b16245b2cc6e4b`.
Его `SCRIPT_FILENAME===__FILE__` guard остаётся false. Старые CLI, operation,
REMOTE и receipt не вызываются и не адаптируются под новое исполнение.
Новый wrapper владеет собственным receipt; вложенный engine сохраняется
побайтово в его каноническом представлении без добавления выдуманных старых
operationId/sourceSha/controlSha. Имя версии engine — происхождение алгоритма.

Host сохраняет derived observations ровно ROOT `config.php` и `data/db-v1.php`
**до отказа**. Безопасные regular/nonsymlink/resolved файлы ограничены2MiB каждый.
Их hashes должны совпасть и с новым CURRENT context, и с immutable baseline:
config `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def`,
helper `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4`.
Mismatch/unavailable означает terminal HOLD, без repin. Hashes/statuses остаются
в результате; голый reason больше не теряет наблюдённый контекст.

Wrapper повторяет fresh guard до helper/PDO и после engine. Фазы отражают
фактически достигнутую границу: после запуска PHP нельзя писать «PHP не был
запущен»; helper/PDO attempts и engine invocation также не превращаются в false
после отказа. До engine вывод engine=null. Нет raw config/DSN/account/roles/grant
patterns/SQL или текста исключений.

Неизменный engine проверяет фактический target hash
`4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d`
на том же PDO **до grant metadata**. Несовпадение не переназначает expected DB.
Account/role hashes — первые новые факты, oldAccountIdentityKnown=false.
Два стабильных metadata snapshots, READ ONLY, максимум512 rows/128KiB,
фиксированные schema/table/column scopes и прежние conservative semantics.
Неполные роли/patterns/revokes/неподдержанные данные дают not_proven,
а не утверждение effective denial. Нет нового auth engine или grant parser.

Новый результат связывает CURRENT/failure/RAW, текущие observations,
новую operation/control/source и engine hash. PHP wrapper body ограничен256KiB
с запасом под контекст; stock outer result ограничен512KiB для admission envelope.
Exclusive private0600/fsynced receipt связан с stdout и stock result.
Без usable sameDB/account/role/protected binding результат удерживается.
Для каждого CREATE/REFERENCES/SELECT/TRIGGER сохраняются basis и полнота proof.
Даже все четыре proven не дают DDL admission: effectiveDenialClaimed=false и
ddlAdmission=false всегда. Нет GRANT/SET ROLE/test CREATE/DDL/DML/supplier,
application/source rows, profile/mapping/lead/booking/import или publication.

## Проверка и предел результата

Новые native fixtures проверяют именно context/phase/lineage/target boundaries;
принятые163 engine checks остаются в штатном CI. Pure fixture identity допускается
только внутри тестового вызова, без production CLI/env overrides или подмены
staged constants. Все старые восемь programs/pins/operations/receipts и71 case
сохраняются; workflow/transport/owner/main/source/ref/SSH/reservation/no-replay
и public guards прежние. Нужны независимый exact-source HIGH review и mandatory
exact-head native CI прежде короткого MAIN merge→ONE READ окна.

Код/CI/merge не доказывают текущие права, созданные таблицы или наполнение.
Следующие три CREATE требуют отдельной материальной admission, фактического
полного proof, fresh guards и strict post-readback под прежним owner approval.
Фактические room/meal concepts отдельно ждут настоящую retained tuple
существующего producer; этот metadata READ не создаёт её и не угадывает identity.

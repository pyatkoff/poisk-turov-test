# LOCAL: ограниченный permission-authority READ

Claim: [6405309113](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6405309113).
Основа: MAIN `436cf6f1bd85f062dd7b8ff42292f80f8232a4b5`, CURRENT SOURCE
`1d0e529e3fc0666783db1454c0765d3c16d63843`.
Пакет исполняет принятый контракт
[6405217250](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6405217250),
не повторяет прежний READ или CREATE и не меняет их guards/pins/receipts.

## Известное и отсутствующее доказательство

PR4912 фактически остановился до DDL на `preflight_refused / privileges`, ACK0.
PR4913 затем успешно выполнил source SELECT, но завершил чтение как
`anchor_missing`: выбранный digest не найден, source facts/drafts=NULL.
Его четыре direct GLOBAL/SCHEMA flags=false не означают отсутствия эффективного
SELECT. Нулевые выходные счётчики не описывают весь каталог.
Обе операции consumed/no-replay, все leases освобождены.

Новый producer связывается с полным принятым private результатом4913:

| Фиксированный вход | Значение |
| --- | --- |
| Parent private result | 3429B / `0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817` |
| Parent inner receipt | `1b26f11a7fbca04f7ce9ffcaeb9c41a849948a98301284a6c4ca3ccf9e79dea4` |
| Parent control / source | `436cf6f1bd85f062dd7b8ff42292f80f8232a4b5` / `1d0e529e3fc0666783db1454c0765d3c16d63843` |
| Configured DB identity | `4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d` |
| Root config.php | `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def` |
| Root/staged db-v1.php | `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4` |

Полный прежний completion predicate проверяет parent с его историческим control;
его содержимое и SHA связывают предыдущие context/failure inputs. Прежние операции
и source guards не расширяются и не вызываются повторно.

## Конечный scope

Mode `local-stay-permission-proof-read-v1`, operation
`int-andromeda-local-stay-permission-proof-read-20261026-v1`, тот же
`/run-int-server-v1` в #4709. Caller не задаёт SQL, account, role, expectedDB,
таблицы, grants или apply. Bundle закрыт до extraction: ровно два файла и manifest
— новый MAIN runner и прежний SOURCE helper. Других runners/classes/SQL нет.
Owner/main/source/ref/actor/SSH/first-attempt/reservation/no-replay/public guards
сохраняются; workflow и transport не меняются.

Одна exception-PDO и READ ONLY transaction. Проверяются selected DB/server hash,
account binding hash, protected files и устойчивость metadata до/после.
Читаются только необходимые server/grant metadata для четырёх привилегий
CREATE/REFERENCES/SELECT/TRIGGER, выбранной schema и фиксированного набора семи
связанных таблиц/allowlisted key/source columns. COLUMN_NAME связывает наблюдённый
column grant с этим фиксированным столбцом. Это observation прямой grant metadata,
а не новый census существования таблиц/столбцов и не полнота всех effective scopes.
Ноль matching grant rows не означает отсутствие объекта или эффективный запрет.
Application rows не читаются.
Все metadata row/byte bounds фиксированы; превышение или ошибка не дают разрешения.

Producer явно описывает account/case binding, schema patterns и escaping,
active-role coverage, table/column scopes и partial revokes. Поддержанный полный
положительный случай — известная семантика и однозначные direct global/schema
основания. Неоднозначные patterns, неподдержанное encoding/case, неразвёрнутые
активные роли, revokes или недоступная metadata дают partial/unavailable и
`not_proven`, а не эффективный запрет. Metadata одной таблицы не становится
разрешением на всю БД. Произвольного unescape/объединения patterns нет.

Для каждой из четырёх привилегий результат содержит наблюдённое основание и
полноту доказательства. Неполнота не превращается в `effective=false`.
DDL admission остаётся false даже при полном положительном proof. Дальнейшие
три CREATE требуют своей отдельной admission/HIGH review/native CI/fresh
target/schema preflight и strict post-readback; уже данное owner approval
ровно трёх пустых таблиц сохраняется.

Экспортируются только производные поля и hashes. Raw account/roles/grant
patterns, config, credentials, DSN и SQL не выводятся. Нет GRANT/SET ROLE,
test CREATE, DDL/DML, supplier, profile/mapping/lead/booking/import действий.
Новый private exclusive0600 canonical receipt связывается со stdout и штатным
result; старые receipts не меняются.

## Проверки и предел результата

Обязательный existing native PHP/MySQL CI проверяет escaped-schema положительный
случай и консервативные table-only/role/ambiguous/revoke/unsupported cases,
read-only sentinel и границы disclosure. Отдельный simulated scalar guardcase
для partial_revokes не называется фактическим server-revoke доказательством;
он проверяет только консервативный отказ при таком входе.
Disposable fixture accounts/grants/roles
создаются только в loopback CI setup, не runtime. Production CLI не имеет
fixture override; staged constants/source для проверки не подменяются.
Старые шесть activated profiles/programs/pins и54 controller cases сохраняются.
Добавлены4 Admission и4 native cases, итого62. Локальные32 Admission checks прошли;
исторические37 defs/63 constants и все6 generated REMOTE побайтово равны MAIN436.
Локального PHP нет; native PASS берётся только из фактического CI.

Подготовка не резервирует MAIN/release/server/NEXT. После независимого review и
exact-head CI допускается только короткое merge→ONE READ окно с terminal RELEASE.
Код/CI/merge не являются доказательством текущих прав или созданных таблиц.
Наполнение room/meal отдельно ждёт реально сохранённую source tuple существующего
producer; этот metadata READ не подменяет такой input и не публикует consumer/UI.

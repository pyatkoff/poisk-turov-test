# LOCAL: исходные room/meal факты при недоступном concept schema

Schema/import остаются HELD после фактического отказа проверки прав:
[terminal6404805791](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6404805791).
Операция4912/run40243823110 завершилась `terminal_nonzero_no_replay`,
`preflight_refused / privileges`, ACK0, unknown=false, attempted/created/unknown=[];
journal не создан, after=null. Ни одного CREATE она не выполнила.
Сохранённый результат не определяет конкретное неподтверждённое право, роль или
причину; последующие collision/capacity/session gates не достигнуты.
Её source/CI/merge приняты, но таблицы не созданы. Повтора или изменения grants,
account/config, transport, DROP/repair здесь нет.

Независимый следующий результат — фактические исходные названия с provenance и
черновики hotel-scoped concepts. Claim
[6404832075](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6404832075),
branch `chore/local-stay-source-facts-20261026`, base MAIN
`ecc2e42af388a81de965949e1e9e2e9ee9011698`. Подготовка не резервирует MAIN,
release/server/NEXT. Обязательны exact-delta review и existing native CI, затем
короткое окно одного READ через прежний carrier.

## Почему нужен отдельный source-only вход

Принятый SOURCE `AnyTourHotelStayOfferCandidatesV2::collectForTourvisorAnchor()`
проверяет все семь таблиц до anchorScope(). Три отсутствующие V2 таблицы блокируют
получение исходных фактов, хотя чтение этих фактов не требует concept lookup.

В текущем MAIN candidates-файла раньше не было. Новый пакет добавляет туда точный
принятый SOURCE owner body SHA
`7a555c9665d3917160eab1f01ac60a42530f854294fb36b2daf08f89fce7d40b`
с additive source-only API. Все прежние методы сохраняются побайтно. Новый API
переиспользует anchor/current/bridge/raw validation, отдельно проверяет четыре
InnoDB source tables и завершает работу до Catalog resolve/concept SQL:

- `anytour_hotels`
- `anytour_hotel_sources`
- `anytour_offers`
- `anytour_offer_scope_state`

Исторический SOURCE `30a7e7013edd7fe70a4700db0aafb2bf22ee05f8`, его candidates-файл и все
прежние pinned modes/programs/receipts не меняются. Текущий RELEASE обновлён
поставкой SEARCH4909 до `1d0e529e3fc0666783db1454c0765d3c16d63843`;
Catalog/bridge/helper и исходный candidates body в нём побайтово прежние.
Новый ещё не исполнявшийся режим привязан к этому CURRENT source, а его
исторические зависимости продолжают проверяться по source30a7. Только новый private CLI
получает MAIN candidates copy. RELEASE port, consumer publication и новый
дублирующий reader не нужны. Старый AnchorRead CLI/op не вызывается и не
переименовывается; его отказ до чтения anchor остаётся consumed.

## Новая фиксированная READ admission

Mode `local-stay-source-facts-read-v1`, operation
`int-andromeda-local-stay-source-facts-read-20261026-v1`, source
`1d0e529e3fc0666783db1454c0765d3c16d63843`,
tour `25282424201500`, максимум50 cohorts с фиксированными row/payload bounds.
Caller не задаёт SQL, credentials, expectedDB, список таблиц, import или apply.
Пять файлов и manifest проверяются до extraction: MAIN новый CLI+candidates;
SOURCE неизменные CatalogV2, IdentityBridgeV1 и db-v1 helper. Никаких old Anchor,
ReviewPacket, migration или installer файлов в bundle.

Проверяются полные immutable зависимости:

| Вход | Байт / SHA256 |
| --- | --- |
| Последний failed apply private result | 8644 / `0da0b944d7a09b010fbd449fbc6004916c2f1e4d9c7698df850eb5eb65308596` |
| Его inner receipt | `a56bd8c4b0a7f5d126c626151687c5c9b5c2d37006c16be4c67494b67f29c73a` |
| CURRENT context private result | 8528 / `21ce19017ad4211a67d8f9e053d2ed033cfa3f5350493d0797084eb31867f1b0` |
| CURRENT context inner / control | `4f60fd91f95774b08e5b44611a20c6202d989eb96fda9a573a011101bb0ef043` / `af256ad98636480ee1e62c59808bad2a612d6420` |
| Configured CURRENT DB identity | `4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d` |
| Root config.php | `121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def` |
| Root/staged helper | `ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4` |

Одна exception-PDO, READ ONLY / repeatable-read transaction; configured identity
и protected hashes проверяются до/после. SQL metadata/source SELECT ограничены
этой целью. Private exclusive0600 canonical receipt совпадает со stdout и
вложенным stock result. Owner/main/source/ref/SSH/first-attempt/reservation/
no-replay/public guards и существующий transport сохраняются.

## Значение результата

Source facts сохраняют точные hotel/legacy/provider/digest/operator scope,
raw room/meal labels, payload/identity hashes, timestamps и evidence state.
Historical retained anchor и CURRENT validated cohorts различаются; ни один
не становится актуальной ценой или selection authority. Не экспортируются
full payload, цены, profile JSON, credentials/account/grants или данные туристов.

Draft concepts содержат исходные labels/provenance и требуют ручного review;
canonical target/nameRu/canonicalFacts остаются null. Concept lookup явно
не выполняется этим source-only READ, его значения null, import held. Это не
новое доказательство отсутствия V2 таблиц: предыдущая операция измерила их
отсутствие только в своём preflight. Похожие названия и разные регионы не объединяются;
FAMILY/SEA VIEW/DELUXE/SUITE/корпуса и operator qualifiers сохраняются.

В том же READ допустимы только derived direct GLOBAL/SCHEMA booleans для
CREATE/REFERENCES/SELECT/TRIGGER. Это не effective-role proof. Ошибка получения
metadata означает unavailable/null, а не четыре false; source SELECT от этой
проверки не зависит. Никакого GRANT/account изменения или отдельного аудита.

Supplier/DDL/DML/profile/mapping/lead/booking/import/automatic accepts —0.
Обязательный native CI доказывает чтение фактов при наличии только четырёх
source tables и отсутствии трёх V2, identity/namespace/qualifier/provenance
guards, отдельные historical/CURRENT states, bounds и неизменность sentinel.
Старые46 controller cases сохранены; добавлены4 Admission и4 native cases,
итого54. Все28 локальных Admission checks прошли. Пять прежних activated REMOTE
программ побайтово равны MAINecc2; accepted Candidate body восстанавливается до
7a55 после удаления только additive source-only region.
Локального PHP нет; native PASS только из фактического CI. Source/CI/merge не
являются actual source-facts или записанными concepts/mappings.

# LOCAL: создание трёх пустых hotel-stay V2 таблиц

Павел26.10.2026 ответил «давай дальше и включи автопилот» на конкретный вопрос
о создании трёх пустых таблиц по существующей миграции. Этот пакет исполняет
именно это разрешение; заполнение concepts/mappings и остальные схемы сюда не входят.

## Основа

Предыдущее штатное чтение завершено: [terminal6403516877](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6403516877),
command6403439413, run40234960991/attempt1, MAIN
`a4faad3ba37937a44249df68e9a1530b3f5f258f`, RELEASE
`30a7e7013edd7fe70a4700db0aafb2bf22ee05f8`.
Тогда обе родительские таблицы InnoDB были совместимы, три целевые таблицы
физически отсутствовали, MySQL8.0.46 поддерживал CHECK. Старые schema-read и
anchor-read остаются завершёнными/consumed/no-replay; их команды не повторяются.

| Создаётся | Содержимое после успеха |
| --- | --- |
| `anytour_hotel_room_concepts_v2` | 0 строк |
| `anytour_hotel_meal_concepts_v2` | 0 строк |
| `anytour_hotel_stay_mappings_v2` | 0 строк |

SQL — неизменённый `v2/data/migrations/20260918-anytour-hotel-stay-v2.sql`,
SHA256 `1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06`.
Ровно три CREATE в порядке room→meal→mapping; 3 PK, 5 UNIQUE, 6 FK, 8 CHECK.
Без IF NOT EXISTS, ALTER, DROP, DML, импорта, решений по соответствиям или supplier.

## Новый допуск в существующем carrier

Mode `local-stay-schema-install-v1`, фиксированная операция
`int-andromeda-local-stay-schema-install-20261026-v1`, тот же
`/run-int-server-v1` в #4709. Caller не задаёт SQL, пути, список таблиц,
лимиты записей, grant или повтор. MAIN содержит installer и неизменённый read
runner; RELEASE поставляет pinned SQL/helper. Bundle закрыт до extraction:
ровно четыре файла и manifest. Лишний staged config отклоняется.

Source/control/owner/main/ref/first-attempt/SSH/reservation/no-replay и protected
fingerprints сохраняются. Программы старого anchor и schema-read не меняются.
Workflow и transport не расширяются; новая native suite исполняется внутри
существующего LOCAL test step с прежним immutable source fixture516.

Сохранённый родитель — private `result.json` исходной schema-read операции.
Его compact sort_keys serialization без newline: 7079 байт,
SHA256 `96e41780998f973f109674d40e6aec9f6d175a769d1a1ff21e8d6b536a706f86`.
Host artifact имеет другой формат (indent2+newline): 13261 байт,
SHA256 `90c73ad9d390cd09a9b133ad7b13884dd67deee313b2e1846361e36aaed2a265`.
Различие доказано исходным stock serializer и тем же принятым JSON, без нового
серверного чтения. Проверяются private bytes, полный старый completion predicate,
inner inventory/plan hashes и source/control/op pins.

## Перед первым CREATE

При фиксированном advisory lock без ожидания installer заново проверяет CURRENT
метаданные: те же родители, все три цели отсутствуют, CHECK/видимость поддержаны,
исходная миграция и helper точны. Config SHA
`121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def`, helper SHA
`ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4`.
Проверяются прямые достаточные CREATE/REFERENCES/TRIGGER/SELECT права и коллизии
имён FK/CHECK. Неизвестные roles-only права не трактуются как разрешённые.
Для UNIQUE ключа mapping на834 байта проверяются DYNAMIC row format и страницы
InnoDB не менее8192 байт; FK/unique checks должны быть уже включены в сессии.
Grant text, account, credentials и имя БД не экспортируются.

Старый read не сохранял database identity hash. Его configured target связывают
точные неизменные config/helper и штатный PDO helper. Новый hash CURRENT DB identity
— новый факт installer; он не называется ранее измеренным fingerprint родителя.
Перед DDL сравнивается свежая структура с принятым inventory родителя; обычное
изменение AUTO_INCREMENT от вставок не считается schema drift.

## Частичный результат

До первой DDL-команды создаётся private exclusive0600 journal с fsync; намерение
и результат каждого CREATE фиксируются отдельно. DDL не откатывается одной
транзакцией. Первый отказ/потерянный ответ прекращает последовательность,
фиксирует ACKed/attempted/unknown таблицы и сохраняет consumed terminal.
После исключения выполненность текущего CREATE остаётся UNKNOWN, даже если
последующие метаданные видимы; чужое создание не приписывается installer.
Ни автоматического DROP, ни повторного CREATE, ни resume этой операции нет.

Успех означает три подтверждённых CREATE, точное совпадение всей новой схемы,
0 строк только в новых трёх таблицах, неизменные родительские метаданные и
config/helper/public fingerprints. `schema_writes` отдельно учитывает ACKed DDL;
application `database_writes`, profile/mapping writes, supplier/lead/booking — 0.
При UNKNOWN число ACKed DDL не выдаётся за полное число возможных изменений.

## Проверка и состояние

Нативные PHP/MySQL fixtures: три пустые таблицы и exact-schema readback;
preexisting/drift/permissions/collision отказ до DDL; реальный persistent partial
CREATE с журналом и отсутствием DROP/повтора. Штатные Python/native remote tests:
parent custody, закрытый bundle, typed counters, protected hashes и сохранение
исходного terminal при replay. Локального PHP нет; native PASS — только actual CI.

Implemented, checked, merged и runtime installed различаются. Конечные SHA,
independent HIGH/apply review, CI и оригинальный install receipt — в #4709/#2690.
Этот документ сам по себе не утверждает установку или заполнение справочников.

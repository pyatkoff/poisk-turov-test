# LOCAL: точная проверка схемы hotel-stay V2

## Причина и граница

Завершённая операция `int-andromeda-local-stay-firstclass-read-20261025-v1`
вернула `schema_missing` для трёх V2-таблиц. Этот статус означает отсутствие
таблицы **или** движок, отличный от InnoDB. Физическое отсутствие не доказано;
anchor/данные конкретного отеля в той операции не исследовались.
Оригинальный запуск40217038514 завершён и не повторяется.
Авторитетный terminal: [#4709/6400937803](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6400937803).

Новый пакет читает только метаданные фиксированных пяти таблиц:

| Таблица | Роль |
| --- | --- |
| `anytour_hotels` | Родитель hotel ID |
| `anytour_hotel_sources` | Родитель provider namespace/key |
| `anytour_hotel_room_concepts_v2` | Номера внутри конкретного отеля |
| `anytour_hotel_meal_concepts_v2` | Питание внутри конкретного отеля |
| `anytour_hotel_stay_mappings_v2` | Проверенные соответствия с provenance |

Строки отелей, офферов, профилей и соответствий не читаются. Supplier/search,
quote, lead, DML, DDL и автоматические решения отсутствуют. Результат различает
отсутствие, неподходящий движок, совместимую структуру и существующий drift.
Поддержка CHECK устанавливается по фактической версии и metadata; неизвестная
возможность сервера блокирует установочный вывод, а не считается успехом.
Пустой information_schema не считается доказательством отсутствия: фиксированный
SHOW CREATE должен подтвердить ошибку1146. Видимость triggers проверяется по
прямым metadata-привилегиям текущего подключения; наружу выходит только bool.
Неизвестные права/roles-only не дают ложного `ready_no_op`.

## Штатный carrier и custody

Используется только существующий `/run-int-server-v1` в #4709. Новый фиксированный
mode — `local-stay-schema-read-v1`, операция —
`int-andromeda-local-stay-schema-read-20261025-v1`. Caller не задаёт SQL, путь,
таблицу, гостиницу, лимит или действие apply.

RELEASE source на подготовке — `30a7e7013edd7fe70a4700db0aafb2bf22ee05f8`.
MAIN содержит runner и control. Пакет строго состоит из runner, принятого
`v2/data/db-v1.php`, существующей миграции и manifest; лишний staged config
отклоняется до extraction/PHP. Source SHA связывает SQL/helper, control SHA —
исполняемый diagnostic. Такая split-custody уже используется старым
`local_tv_schema`; его исторические операции не открываются заново.

SHA256 миграции `v2/data/migrations/20260918-anytour-hotel-stay-v2.sql`:
`1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06`.
SHA256 helper:
`ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4`.
Оба файла в существующем immutable CI fixture516 совпадают с RELEASE30a7;
изменять shared workflow или владение INT4903 не требуется.

Сохраняются owner/main/ref/source, SSH, first-attempt, reservation/no-replay,
проверка до/после config/helper и public fingerprints. Результат остаётся в том
же private operation receipt и штатном `result.json`; второй транспорт не создан.
Все поля supplier/database/schema/profile/mapping/lead writes равны целому0.
Оригинальная anchor-команда, bundle, validator и закрытый operation ID сохраняются.

## Решение после чтения

При совместимых родителях и отсутствующих целевых таблицах вывод
`create_only_after_review` означает только подготовленный вариант установки.
При существующем drift — отдельное решение по фактической структуре, без
`IF NOT EXISTS`, конвертации движка, перезаписи или удаления.
Совместимые существующие таблицы дают `ready_no_op`.

Этот пакет не разрешает установку. CREATE TABLE — постоянный DDL: последовательные
три CREATE не откатываются одной транзакцией и могут завершиться частично.
До отдельного apply потребуются точный CURRENT preflight, права/коллизии имён,
независимый review, фиксированный план частичного результата и обычный допуск
из AGENTS HIGH. Не удалять таблицы в качестве автоматического rollback.

## Проверка и статус

Нативные PHP/MySQL fixtures проверяют реальные отсутствующие таблицы,
неподходящий движок, несовместимые родители/схему и точную миграцию. Штатные
Python tests проверяют фиксированную команду, closed bundle, typed zeros,
source/control pins, фактический remote PHP read и сохранение исходного terminal
при повторе. Старые anchor-тесты сохраняются. Локального PHP нет; native PASS
допустим только из существующего exact-head CI.

На момент подготовки: implemented/CI/review/MAIN merge/runtime read — отдельные
статусы. Конечные SHA, CI и оригинальный runtime receipt фиксируются в #4709;
этот документ не является разрешением команды или устаревшей очередью на replay.

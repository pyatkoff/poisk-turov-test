# ТОП500: пространства идентификаторов и регрессия ANEX 8401

Аудит 6 ноября 2026 года. Подготовка исходников и локальные проверки; рабочая БД
и установленный runtime в этот проход не читались и не изменялись.

## Вывод

В старом Search3 подтверждён дефект использования списка: число из ANEX ТОП500
могло выбрать постороннюю строку Tourvisor с тем же номером. Воспроизведение
с исходным `top-hotels-v1.php` из `64fa44201d77d8b6ea2abd4f9580deb7cf2b9d4d`
и синтетической строкой каталога `id=8401` действительно выбрало TV8401.

Это доказательство ошибки в коде, а не доказательство повреждения реальной БД.
Исправление уже выполнено существующим владельцем:
[PR4998](https://github.com/pyatkoff/poisk-turov-test/pull/4998) →
main `d245a6c8d9ff34c7ba517212ba704b290451ff8e`;
[PR4999](https://github.com/pyatkoff/poisk-turov-test/pull/4999) →
release `ec6799c7074331b2545562a83812123ab4e15e83`.
[Исходный scope закрыт](https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-6678597989).
Этот follow-up добавляет адресные тесты, одну команду в существующий CI
и данный отчёт; производственная логика и принятые связи не переписываются.

## Разделение пространств

| Пространство | Значение | Допустимый переход |
| --- | --- | --- |
| Исходный ТОП500 | 492 уникальных ANEX ID в порядке владельца | Существующий принятый MATCH resolver; совпадение числа не связь |
| `anex_hotel_search_mappings.anex_hotel_id` | Принятый ANEX ключ существующего resolver | Политика/class, enabled/scope, существующий target, manual decisions и pair exclusions |
| `catalog_hotels.id` | Tourvisor ID по контракту старого каталога и `api-v2.hotelIds` | Явная принятая source-связь в канонический AnyTour |
| `anytour_hotels.id` | Самостоятельный локальный AUTO_INCREMENT ID | `anytour_hotel_sources(namespace='legacy_catalog', external_key)` |
| `local-tv` | TV-keyed каталог гостиничных фактов | Для старого AnyTour ID — сохранённый `old_local_id → tv_id` crosswalk |
| Исследовательский hotel key | Стабильный строковый ключ сохранённых материалов | Полная принятая цепочка источников и доказательств |

Основание: [старый каталог](https://github.com/pyatkoff/poisk-turov-test/blob/ec6799c7074331b2545562a83812123ab4e15e83/ANYTOUR_DATA_FOUNDATION.md),
[канонический контракт](https://github.com/pyatkoff/poisk-turov-test/blob/ec6799c7074331b2545562a83812123ab4e15e83/docs/project/anytour-canonical-catalog-contract.md),
[существующий resolver](https://github.com/pyatkoff/poisk-turov-test/blob/ec6799c7074331b2545562a83812123ab4e15e83/app/integrations/anex-search-mapping-registry.php).

Исходные 492 числа и порядок сохранены. SHA256 строки чисел через запятую:
`cac5f3cc765390702974cdb063333881fbf6bd70cab1296b02ef17118facbfe4`.
Это checksum списка, а не хеш файла или доказательство реальных соответствий.

## Проверенные места риска

| Потребитель | Риск до исправления / текущая граница |
| --- | --- |
| `top-hotels-v1.php` | Ранее возвращал сырые ANEX числа как Tourvisor; теперь возвращает принятые target IDs |
| `top500-daily-plan-v1.php` | Сравнивает priority ID с `hotelRows[].id`; вызывающий collector теперь передаёт разрешённые TV IDs |
| Daily/matrix collector, priority sync, coverage | Получают проекцию через MATCH; отсутствие реестра не возвращает сырой список |
| `hotel-popularity-v1.php`, оба PHP входа поиска | Передают проекцию в существующий модуль популярности |
| `hotel-popularity-v1.js` | Проверяет явно переданные `legacyIds`; собственный local `hotel.id` не считается supplier ID |
| `sales-leaders-v1.php` | ID-путь использует проекцию; прежний fallback по имени+стране сохранён и не является доказательством MATCH |
| HOTEL `ManagerHotelKnowledge.php` | Требует типизированную source identity и принятую цепочку, без ANEX→TV подстановки числа |
| HOTEL `HotelServerInputs.php` / projector | Сохраняют research keys и receipt-bound документы, не выполняют числовой JOIN каталогов |

В текущих HOTEL читателе и projector нового числового дефекта не найдено.
Произвольные research keys во входной подготовке не заменяют отдельную проверку
принадлежности ТОП500; автоматический TOP500 admission guard этим не подтверждён.

## Что установлено для 8401

[Сохранённое гостиничное подтверждение](https://github.com/pyatkoff/max-search-bot/pull/1274#issuecomment-6573814738):
ANEX владельца `8401`, позиция `76`, Rubi Platinum Spa Resort & Suites,
research key `top500_dca4e4a0c9ae44ca`; источник — сохранённая ANEX web-card
`/hotel/8401/rubi-platinum-spa-resort-suites/`.
Текущие Tourvisor ID и AnyTour ID не подтверждены.
Материалы сохраняют `admission=false`, `consumer_ready=false`; нового credit нет.

В историческом [XML CSV](https://github.com/pyatkoff/poisk-turov-test/blob/6dcfb5053793caba4f353facfa7a899a30df2d17/app/integrations/data/anex-xml-exact-identities.csv)
есть `8401,27426`. Это сохранённый кандидат, не текущая приёмка MATCH и не разрешение
связать Rubi с TV27426. Он не используется как принятое соответствие.

Существующий контракт унифицирует принятые `anex_online/anex_xml` ID, но не
доказывает для всего исходного CSV тождество website-card и Online/XML ID.
Этот аудит не объявляет website8401 принятой Online8401-связью.
Отклонение неизвестного label `anex_web` в тесте не закрывает пробел происхождения.
Для допуска Rubi нужен существующий авторитетный native/MATCH proof, а не повтор
сбора, платный поиск или использование старой XML строки как fallback.

## Сохранённые принятые TV → AnyTour связи

Источник: [HOTEL manifest на 40b5b232](https://github.com/pyatkoff/max-search-bot/blob/40b5b2325776930c2b45eee829a1abb86da3fa1f/knowledge/hotels/manifest.json).
Для каждой записи принят типизированный `legacy_catalog → anytour → local-tv`
путь. Таблица не добавляет ANEX связи и не доказывает принадлежность ТОП500.

| Отель | Tourvisor / local-tv | AnyTour |
| --- | ---: | ---: |
| SUNRISE ARABIAN BEACH RESORT | 9374 | 1554 |
| ALBATROS MAKADI RESORT | 241 | 81 |
| HAPPY LIFE VILLAGE | 240 | 80 |
| JAZ MAKADI SARAYA RESORT (EX. IBEROTEL MAKADI SARAYA RESORT) | 268 | 99 |
| JAZ NEO NAAMA BAY (EX. SOLYMAR NAAMA BAY) | 1981 | 877 |
| MOVENPICK RESORT SHARM EL SHEIKH NAAMA BAY | 12111 | 1654 |
| TITANIC BEACH SPA & AQUA PARK | 498 | 228 |
| AVIA RESORT & SPA | 157359 | 4101 |
| CLUB AQUA PLAZA (EX. SUNEOCLUB AQUA PLAZA) | 1077 | 477 |
| DREAM FUN WORLD | 109900 | 3726 |
| PRIMASOL HANE FAMILY RESORT (EX. FAMILY HANE RESORT) | 28666 | 2077 |
| JADORE DELUXE HOTEL & SPA | 46672 | 2432 |
| SIDE MOON PALACE HOTEL | 111129 | 3742 |
| VOX MARIS RESORT ADULTS ONLY 16+ | 111045 | 3737 |
| AKRA ANTALYA | 1146 | 516 |
| PICKALBATROS BLU SPA RESORT HURGHADA ADULTS ONLY 16+ | 78898 | 3417 |

Все 16 bindings, bundle hashes, research keys, принятые факты и receipts сохранены.
Вывод о `0/16` принадлежности ANEX ТОП500 отозван в гостиничной координации;
это не причина удалять принятые данные.

## Новая адресная регрессия

```sh
node tests/search3-top500-anex-8401.cjs
# Для другого установленного PHP:
PHP_BINARY=/path/to/php node tests/search3-top500-anex-8401.cjs
```

Выполнено локально на PHP 8.2.32 и Node: **17 PHP + 7 JS assertions PASS**.
Тесты используют реальные pure PHP resolver/planner и модуль популярности;
рабочая БД, HTML rendering и установленный runtime в них не участвуют.

Покрыты отсутствие связи, синтетическая принятая `8401 → 900076`, manual rejection,
pair exclusion, пропавший и неактивный target, неизвестное пространство, закрытый
production scope, отсутствие реестра, сохранность всего списка и позиции76.
Синтетическая `8401 → 8401` разрешается только при явной принятой паре.
Собственный local ID `8401` или `900076` без `legacyIds` не даёт популярности.
В синтетической карточке порядок наследуется через явно заданный `legacyIds`.

Исходная ошибка отдельно воспроизведена на старом helper: выбран TV8401 без
принятого ANEX→TV перехода. Завершённые suites и HOTEL проверки не повторялись.
Независимый review новых тестов выполнен чтением, без второго запуска.

Существующий workflow дополнен двумя paths и одной командой; новый workflow,
расписание или worker не создаётся. Пакет подготовлен в ветке без запуска
полного PR CI, merge, deploy или повторной приёмки старых suites.

## Граница готовности

Исправления4998/4999 merged; их завершённые исходные CI используются как
существующее доказательство. Это не подтверждение установки в NEXT/production.
Исходный closeout фиксирует отдельный отказ автоматического deployment по SSH
и skipped release copy; здесь он не повторяется и не исправляется.

Настоящий follow-up подготовлен и локально проверен. Актуальная доля принятых
ANEX связей и соответствие Rubi каталогу остаются неизмеренными.
Перезапись accepted data, пересоздание ID и replay закрытых/UNKNOWN операций
не выполнялись.

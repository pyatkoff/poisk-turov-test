# Search3 data.js: итог структурного анализа

Статус: **анализ завершён; изменение runtime не выполнялось**. Это результат F2
ограниченного структурного прохода от 02.10.2026, а не новая очередь мелких оптимизаций.
Принимающий владелец — SEARCH [#1646](https://github.com/pyatkoff/poisk-turov-test/issues/1646);
общая координация — [#4217](https://github.com/pyatkoff/poisk-turov-test/issues/4217).

## Проверенная основа

- Release: `e764a07719a98e45911cad3582bf3fe048e1b91d`.
- Файл: `v2/prototype-search/data.js`, неизменённый Git blob
  `b3d5ae68f2246e3f1f6e22a216970ab628b4942e`.
- Размер: **126 244 байта исходника**, 107 объявлений функций.
  Опубликованная сборка `1525640b410ab602960f9e6cf2e427b8ecc5293e`:
  87 876 байт JS, 24 372 байта gzip.
- Scoped INT-планы прочитаны на свежем
  `feature/anex-search-adapter-20260907` SHA
  `24699a22b7d4123b4e6ec387d7193c0c360e2d03`:
  `docs/integrations/anex-search3-autopilot.md` и
  `docs/integrations/multi-provider-search-plan.md`.
  Их исторические capture/install задачи не запускались.

## Полная карта ответственности

Ниже непрерывные участки файла, от начала соответствующей функции до следующей
границы. Байты включают переменные, комментарии и пробелы; сумма покрывает весь файл.
Это карта текущего кода, а не предложение создать восемь новых модулей.

| Участок | Исходные байты | Функции |
| --- | ---: | ---: |
| Общие значения, питание и оператор | 11 056 | 8 |
| Направления, scope и локальное чтение | 10 658 | 13 |
| Общая проекция и состояние выдачи | 7 802 | 13 |
| Прямой ANEX search/continuation | 14 054 | 10 |
| Прямая Andromeda search/continuation | 18 362 | 14 |
| Координация общей поисковой сессии | 8 410 | 8 |
| Календарь, справочники и поиск отеля | 14 178 | 13 |
| Актуализация ANEX/Andromeda/TV и lead guard | 41 724 | 28 |
| **Всего** | **126 244** | **107** |

Главная структурная проблема — совместное владение состоянием справочников,
поисковой сессии, supplier continuation и подтверждением конкретного предложения.
Самый большой участок — актуализация и guards, а не форматирование интерфейса.
Его механический перенос без контракта затронул бы действующие границы INT/SEARCH.

`generation`, `searchId`, `activeSearch`, `activeVerification` и supplier scope
инвалидируют старые ответы. Quote receipts, ANEX package/flight receipts и
Andromeda attempts связывают действие с удержанным предложением. Календарь имеет
собственный cache/version/budget. Они остаются в текущем владельце до отдельной
согласованной передачи; глобальный cache или второй session owner здесь не предлагается.

## Один ограниченный кандидат для INT → SEARCH

Первый кандидат — чистая проекция питания:
`mealAliases`, `mealIndexes`, `indexedMeal`, `meal`, `supplierMealNativeId`, `mealPlan`.
Функции `mealDetails` в проверенном файле нет; предварительное имя в исходном claim
уточнено по фактическому коду.

`installMealPlans` остаётся в host: он проверяет источник, provider/scope,
дубликаты/native IDs, revision и устанавливает authoritative catalog.
Transport, endpoints, auth, supplier requests, money/fuel, quote/lead authority
не входят в этот кандидат.

| Часть передачи | Ограничение |
| --- | --- |
| Source / target | Проверенный blob выше; перед реализацией заново сверить INT head и свежую release |
| Точные runtime paths | Предлагаются только `v2/prototype-search/data.js` и один согласованный pure meal owner; пути подключения и тестов объявить до правки |
| Владелец | INT + SEARCH согласуют одного исполнителя в #4217; этот проход runtime claim на data.js не берёт |
| Вход | Живой `catalog.meals`, `catalog.mealPlans`, `mealPlanAvailable`, исходное `t.meal`, provider и явный `searchMealPlan` |
| Выход | Прежние display meal, native ID и nullable `{id,code,nameRu}`; публичные `meal`/`mealPlan` и существующие аргументы не меняются |
| Consumers | `requestPlan`, `offer`/`project`, `observationMealPlans`, публичные `meal`/`mealPlan`; один индекс на текущую операцию сохраняется |
| Fixtures | TV/native ID и alias; explicit canonical plan; branded/unknown label; ambiguous native/name mapping; non-TV provider; available=false; смена catalog; null/sparse/invalid row и прежний порядок ошибки |
| Проверки | Meal owner/client tests, prototype calendar/filter scope, projection/reference/mutation checks и общий compiled visual browser gate |
| Supplier / lead budget | 0 / 0; никаких live поисков, новых package capture или отправки заявки |
| Состояние | Analysis checked; implementation deferred; publication не требуется для неизменённого data.js |

Индекс нельзя удерживать дольше текущей операции: catalog arrays заменяются при
инициализации. Дубликаты сохраняют приоритет первой записи; неоднозначное native
сопоставление не превращается в подтверждённый plan. Sparse/null/invalid rows и
ошибки должны совпасть с существующими reference-тестами, включая порядок чтений.
Публичное питание, `mealRaw`, canonical meal plan и supplier native ID сохраняют
свои отдельные значения.

## Конечный остаток

1. Согласовать и выполнить только этот pure meal handoff, если INT подтвердит пользу
   и актуальные consumers. Эффект по времени/памяти пока не измерен.
2. Supplier search/continuation и quote/selection выделять только будущими INT-пакетами
   со своими receipts и guards, когда появится конкретная потребность.

Оба пункта — последующий backlog. В текущем проходе data.js побайтно сохранён;
проведённый анализ не выдаётся за реализованное разделение supplier-кода.
Физический Safari/iPhone и реальные supplier/lead сценарии этим анализом не проверены.

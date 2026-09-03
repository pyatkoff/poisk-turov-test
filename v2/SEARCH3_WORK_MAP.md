# Search3 — карта работ

Последнее обновление: 2026-09-03  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation head: `6cdbf58f14c814e25d1a57173fb13cb4920bcf5e`  
Последний полный QA: **run 416 — SUCCESS** (`deploy-preview`, `visual-qa desktop`, `visual-qa tablet`, `visual-qa mobile`).

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в проверку не входят.

| № | Утверждённый макет | Покрываемые состояния | Статус | Что уже сделано / что проверить |
|---:|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | REVIEW | Верх выдачи и hotel cards уплотнены; desktop card/photo 166px; нужен финальный визуальный diff свежих artifacts |
| 2 | Макет фильтров поиска туров AnyTour | filters, sorting, sub-screens, reset/apply/cancel | REVIEW | Реальные mobile/tablet sub-screens, полный reset, apply/cancel staging; контракт проходит QA |
| 3 | Выбор рейсов AnyTour | outbound/inbound, baggage, delta, total, continue | REVIEW | Responsive booking-board convergence внесён; flight variants остаются обязательными в QA; нужен визуальный diff |
| 4 | Итог тура AnyTour | hotel, room, flights, services, tourists, total, submit | REVIEW | Исправлена tablet-геометрия итогового hotel heading; цена/итог проходят полный QA; нужен визуальный diff |
| 5 | Страница бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | REVIEW | Mobile selected-tour booking view изолирован от лишних блоков; порядок booking sections уточнён; run 416 зелёный |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram | REVIEW | Mobile lead lifecycle приведён к утверждённому kit; все 4 состояния обязательны в desktop/tablet/mobile QA |
| 7 | Спецификация футера AnyTour | navigation, support, social, apps, trust, legal | REVIEW | Grid/readability и canonical `anytoour.ru` links исправлены; нужен финальный screenshot diff |
| 8 | Цельный mobile flow AnyTour | search → results → hotel → tour → flight → total → lead → messenger | REVIEW | Полный flow проходит; filter subflows и реальные MAX/Telegram handoff включены; нужен финальный визуальный diff |

## Матрица обязательного QA

| QA state | Макет | Desktop | Tablet | Mobile |
|---|---:|---:|---:|---:|
| Initial search | 1 | required | required | required |
| Results | 1 | required | required | required |
| Filters open / subflows | 2 | rail | required | required |
| Hotel / selected tour details | 1, 5 | required | required | required |
| Flights | 3 | required | required | required |
| Final review | 4 | required | required | required |
| Lead entry | 6 | required | required | required |
| Lead sending | 6 | required | required | required |
| Lead success | 6, 8 | required | required | required |
| Lead error / retry | 6 | required | required | required |
| Footer | 7 | required | required | required |
| End-to-end mobile flow | 8 | n/a | n/a | required |

## Текущий фактический статус

- Изолированный preview, noindex и production-lock: **DONE**.
- Функциональный путь search → tour → flights → final → lead success/error: **DONE на текущем head**.
- Run 416: deploy + desktop + tablet + mobile = **SUCCESS**.
- Последний head `6cdbf58f` закрывает mobile selected-tour booking isolation.
- Перед ним закрыты: booking-board responsive convergence, tablet final hotel heading geometry и mobile lead lifecycle.
- Визуальное одобрение владельца: **LOCKED**, пока не завершён финальный screenshot-by-screenshot diff всех 8 макетов.
- Production rollout: **LOCKED** до отдельного явного подтверждения владельца.

## Что делаем дальше

1. Забрать свежие artifacts run 416 и сравнить `03 Tour Details / Flights`, `04 Final`, `05 Booking` с утверждёнными макетами на desktop/tablet/mobile.
2. Одним пакетом исправить оставшиеся визуальные P1 без изменения данных, Tourvisor/pricing/Metrika/lead contracts.
3. Затем сделать финальный diff макетов 1, 2, 6, 7, 8 и закрыть только реально совпавшие состояния.
4. Один финальный QA одного implementation head на всех трёх viewport.
5. Отдать preview владельцу на визуальную приёмку.
6. Production — только после явного одобрения.

## Правила реализации

- Зелёный функциональный тест не равен визуальному одобрению.
- Не добавлять неподтверждённые отзывы, удобства, моментальное подтверждение или иные данные, которых нет в Tourvisor/current-offer.
- Карточка отеля показывает цену «от» и `Показать туры`; покупка доступна только для конкретного проверенного тура.
- Сохранять progressive loading, stale-state guards, Metrika, lead transport и pricing.
- Production запрещён до явного визуального одобрения владельца.

## Последние изменения

- run 407 — полностью зелёный baseline после card-density/filter/handoff работ.
- `74b731a0` — подключён booking board convergence layer.
- `69c1bfef` — закреплена tablet-геометрия итогового hotel heading.
- `13840419` — mobile lead lifecycle приведён к approved kit.
- `6cdbf58f` — изолирован selected-tour mobile booking view.
- run 416 — полностью зелёный на `6cdbf58f`: deploy + desktop + tablet + mobile.

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- все 8 утверждённых макетов имеют закрытые обязательные состояния;
- desktop, tablet и mobile проходят один и тот же implementation head;
- все обязательные screenshots созданы;
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.
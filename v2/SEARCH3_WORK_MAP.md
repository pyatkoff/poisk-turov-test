# Search3 — карта работ

Последнее обновление: 2026-09-03  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation head: `21d857e0ee6311c72cf692d87eb47ace0c8d2c1d`  
Последний полный QA: **run 430 — SUCCESS** (`deploy-preview`, `visual-qa desktop`, `visual-qa tablet`, `visual-qa mobile`).

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в проверку не входят.

| № | Утверждённый макет | Покрываемые состояния | Статус | Что уже сделано / что проверить |
|---:|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | REVIEW | Верх выдачи и hotel cards уплотнены; desktop card/photo 166px; нужен финальный visual diff свежего head |
| 2 | Макет фильтров поиска туров AnyTour | filters, sorting, sub-screens, reset/apply/cancel | REVIEW | Реальные mobile/tablet sub-screens, полный reset, apply/cancel staging; контракт проходит QA |
| 3 | Выбор рейсов AnyTour | outbound/inbound, baggage, delta, total, continue | REVIEW | Рейсы уплотнены на desktop/mobile, реальные paired-variant semantics сохранены; run 430 проходит все viewport |
| 4 | Итог тура AnyTour | hotel, room, flights, services, tourists, total, submit | REVIEW | Mobile hotel+room+meal собраны в единый review-card; flight block/CTA stacking исправлен; run 430 зелёный |
| 5 | Страница бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | REVIEW | Booking view изолирован и уплотнён; tablet flight-choice стабилизирован; весь путь проходит на run 430 |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram | REVIEW | Все 4 lead-state обязательны в desktop/tablet/mobile QA; нужен финальный screenshot diff |
| 7 | Спецификация футера AnyTour | navigation, support, social, apps, trust, legal | REVIEW | Grid/readability и canonical `anytoour.ru` links исправлены; нужен финальный screenshot diff |
| 8 | Цельный mobile flow AnyTour | search → results → hotel → tour → flight → total → lead → messenger | REVIEW | Полный flow, filter subflows, final review и реальные MAX/Telegram handoff проходят на одном head; нужен финальный visual diff |

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
- Run 430: deploy + desktop + tablet + mobile = **SUCCESS** на `21d857e0`.
- Макеты 3–5 прошли отдельный responsive convergence: compact flights, joined mobile final hotel card, CTA no-overlap, tablet flight-choice stabilization.
- Визуальное одобрение владельца: **LOCKED**, пока не завершён финальный screenshot-by-screenshot diff всех 8 макетов.
- Production rollout: **LOCKED** до отдельного явного подтверждения владельца.

## Что делаем дальше

1. Финальный visual diff макетов 1 и 2: search/results/cards + filters/sorting/sub-screens.
2. Финальный visual diff макетов 6 и 7: lead kit + footer.
3. Цельная проверка макета 8 по свежему mobile artifact от search до messenger handoff.
4. Одним пакетом закрыть оставшиеся визуальные P1 без изменения данных, Tourvisor/pricing/Metrika/lead contracts.
5. Один финальный QA одного implementation head на desktop/tablet/mobile.
6. Отдать preview владельцу на визуальную приёмку.
7. Production — только после явного одобрения.

## Правила реализации

- Зелёный функциональный тест не равен визуальному одобрению.
- Не добавлять неподтверждённые отзывы, удобства, моментальное подтверждение или иные данные, которых нет в Tourvisor/current-offer.
- Карточка отеля показывает цену «от» и `Показать туры`; покупка доступна только для конкретного проверенного тура.
- Сохранять progressive loading, stale-state guards, Metrika, lead transport и pricing.
- Production запрещён до явного визуального одобрения владельца.

## Последние изменения

- run 416 — полностью зелёный baseline на `6cdbf58f`.
- `9ac9690e` — уплотнены flight variants и final review без изменения flight semantics.
- `e5aa58a4` — mobile final hotel/room/meal собраны в единый booking-card и добавлен самый поздний preview visual layer.
- `0e08c1da` — сохранён параллельный tablet flight-choice stabilization.
- `21d857e0` — устранено перекрытие mobile final CTA блоком рейсов.
- run 430 — полностью зелёный: deploy + desktop + tablet + mobile на `21d857e0`.

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- все 8 утверждённых макетов имеют закрытые обязательные состояния;
- desktop, tablet и mobile проходят один и тот же implementation head;
- все обязательные screenshots созданы;
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.
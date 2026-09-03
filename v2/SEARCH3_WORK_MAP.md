# Search3 — карта работ

Последнее обновление: 2026-09-04  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation head: `12e72d4c83c5dbc2089b976774d184672f5eda27`  
Текущий implementation candidate: совпадает с проверенным head.  
Последний полный QA: **run 465 — SUCCESS** (`deploy-preview`, `visual-qa desktop`, `visual-qa tablet`, `visual-qa mobile`).

> История transient: run 463 на `c801f286` в первой попытке получил одинаковый search/Tourvisor timeout на всех viewport; rerun failed jobs на том же commit прошёл зелёным. Runs 464 и 465 затем прошли полностью зелёными.

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в приёмку не входят.

| № | Утверждённый макет | Основные состояния | Статус | Текущее состояние |
|---:|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | REVIEW | Mobile CTA синий, filters orange-outline, trust hints low-chrome; run 465 зелёный. Нужен финальный прямой visual diff. |
| 2 | Макет фильтров поиска туров AnyTour | filters, sorting, sub-screens, reset/apply/cancel | REVIEW | Mobile/tablet drawer и sub-screens работают; back/reset/apply сохранены. Нужен финальный прямой visual diff. |
| 3 | Выбор рейсов AnyTour | outbound/inbound, baggage, delta, total, continue | REVIEW / P1 CANDIDATE | Функционально реальные варианты и pricing сохранены, run 465 зелёный. Прямой diff выявил заметное расхождение mobile-композиции: текущий экран группирует `Вариант 1…N`, тогда как утверждённый макет показывает отдельные секции «Туда»/«Обратно» с flight cards. Исправлять только без подмены Tourvisor-данных и semantics. |
| 4 | Итог тура AnyTour | hotel, room, flights, services, tourists, total, submit | REVIEW | Порядок блоков и mobile CTA geometry защищены QA; run 465 зелёный. |
| 5 | Страница бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | REVIEW | Selected-tour изолирован, compact back-control без дубля; run 465 зелёный. |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram | REVIEW | Entry low-chrome, optional comment secondary, sending cue, success actions full-width. Error preserve-note теперь не обрезается и отображается тихой строкой без лишней карточки; fresh `m09` run 465 проверен. |
| 7 | Спецификация футера AnyTour | navigation, support, social, apps, trust, legal | REVIEW | Maket7 dark-card structure, mobile accordions/apps/legal и canonical `anytoour.ru` links собраны; run 465 зелёный. |
| 8 | Цельный mobile flow AnyTour | search → results → hotel → tour → flight → total → lead → messenger | REVIEW | Цельный flow проходит на одном head. Главный оставшийся structural visual gap связан с flight-stage из макета 3; после него нужен финальный flow diff. |

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

- Изолированный preview, `noindex` и production-lock: **DONE**.
- Функциональный путь search → tour → flights → final → lead sending/success/error: **DONE на текущем head**.
- Run 465 на `12e72d4c`: deploy + desktop + tablet + mobile = **SUCCESS**.
- Mobile error lifecycle P1: clipping и лишняя card-chrome preserve-note устранены — **CLOSED**.
- Прямой diff всех 8 канонических макетов начат; следующий честно выделенный крупный visual gap — **mobile flight selection composition (mockup 3)**.
- Визуальное одобрение владельца: **LOCKED**, пока финальный screenshot-by-screenshot diff всех 8 макетов не закрыт.
- Production rollout: **LOCKED** до отдельного явного подтверждения владельца.

## Последний convergence-пакет

- PR #1274 — mobile search palette.
- PR #1275 — compact mobile back-controls.
- PR #1276 — mobile lead lifecycle layout.
- PR #1277 — lighter mobile lead entry chrome + back/action fixes.
- PR #1278 — optional comment demotion + robust success/error/back fixes; run 463 rerun fully green.
- PR #1282 — mobile error preserved-data note width/clipping fix; run 464 fully green.
- PR #1286 — preserve-note low-chrome convergence to approved lead UI kit; merge head `12e72d4c`, run 465 fully green.

## Что делаем дальше

1. Разобрать текущий renderer flight-stage и привести mobile-композицию к макету 3, сохранив реальные Tourvisor variants/pricing и выбор рейса.
2. Повторить run desktop/tablet/mobile на одном implementation head и проверить fresh `m04-flights` + `m05-final-review`.
3. Закрыть оставшийся screenshot-by-screenshot diff макетов 1, 2, 4, 5, 7 и цельного flow 8.
4. Когда visual P1 = 0 — отдать preview владельцу на визуальную приёмку.
5. Production — только после явного одобрения владельца.

## Правила реализации

- Зелёный функциональный тест не равен визуальному одобрению.
- Не добавлять неподтверждённые отзывы, удобства, моментальное подтверждение или иные данные, которых нет в Tourvisor/current-offer.
- Карточка отеля показывает цену «от» и `Показать туры`; покупка доступна только для конкретного проверенного тура.
- Сохранять progressive loading, stale-state guards, Metrika, lead transport и pricing.
- Production запрещён до явного визуального одобрения владельца.

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- все 8 утверждённых макетов имеют закрытые обязательные состояния;
- desktop, tablet и mobile проходят один и тот же implementation head;
- все обязательные screenshots созданы и визуально просмотрены;
- нет horizontal overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.

# Search3 — карта работ

Последнее обновление: 2026-09-04  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation head: `da9fb79bceef303a297079a73228d3ab3cc94aed`  
Текущий implementation candidate: совпадает с проверенным head.  
Последний полный QA: **run 464 — SUCCESS** (`deploy-preview`, `visual-qa desktop`, `visual-qa tablet`, `visual-qa mobile`).

> Историческая оговорка: run 463 на `c801f286` в первой попытке получил одинаковый transient search/Tourvisor timeout на всех трёх viewport; rerun failed jobs на том же commit прошёл полностью зелёным. Run 464 затем прошёл зелёным с первой попытки.

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в приёмку не входят.

| № | Утверждённый макет | Основные состояния | Статус | Текущее состояние |
|---:|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | REVIEW | Mobile primary CTA синий, filters — orange outline, trust hints low-chrome; search/results проходят run 464. Нужен финальный прямой visual diff. |
| 2 | Макет фильтров поиска туров AnyTour | filters, sorting, sub-screens, reset/apply/cancel | REVIEW | Mobile/tablet drawer и sub-screen, reset/apply/back работают; свежий mobile artifact проверен. Нужен финальный прямой visual diff. |
| 3 | Выбор рейсов AnyTour | outbound/inbound, baggage, delta, total, continue | REVIEW | Responsive convergence и реальные варианты сохранены; desktop/tablet/mobile проходят run 464. |
| 4 | Итог тура AnyTour | hotel, room, flights, services, tourists, total, submit | REVIEW | Порядок блоков и mobile CTA geometry закреплены QA; run 464 зелёный. |
| 5 | Страница бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | REVIEW | Selected-tour изолирован, compact back-control без дубля; desktop/tablet/mobile run 464 зелёный. |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram | REVIEW | Entry low-chrome, optional comment secondary, sending progress cue, success actions full-width, error preserve-note больше не обрезается. Все 4 lead-state проходят run 464; свежие mobile screenshots проверены. |
| 7 | Спецификация футера AnyTour | navigation, support, social, apps, trust, legal | REVIEW | Maket7 dark-card structure, mobile accordions/apps/legal и canonical `anytoour.ru` links собраны; run 464 зелёный. Нужен финальный прямой visual diff. |
| 8 | Цельный mobile flow AnyTour | search → results → hotel → tour → flight → total → lead → messenger | REVIEW | Цельный flow, compact back, lead lifecycle и messenger handoff проходят на одном head; свежий run 464 зелёный. Нужна финальная визуальная приёмка владельца. |

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
- Run 464 на `da9fb79b`: deploy + desktop + tablet + mobile = **SUCCESS**.
- Свежий mobile `m09-lead-error`: сохранённые данные отображаются на полной ширине карточки, без clipping — **P1 CLOSED**.
- Свежие mobile search, filters и footer также проверены после run 464; критических регрессий не обнаружено.
- Визуальное одобрение владельца: **LOCKED**, пока не завершён финальный screenshot-by-screenshot diff всех 8 утверждённых макетов.
- Production rollout: **LOCKED** до отдельного явного подтверждения владельца.

## Последний convergence-пакет

- PR #1274 — mobile search palette.
- PR #1275 — compact mobile back-controls.
- PR #1276 — mobile lead lifecycle layout.
- PR #1277 — lighter mobile lead entry chrome + back/action fixes.
- PR #1278 — optional comment demotion + robust success/error/back fixes; run 463 rerun fully green.
- PR #1282 — mobile error preserved-data note width/clipping fix; merge head `da9fb79b`, run 464 fully green.

## Что делаем дальше

1. Финальный screenshot-by-screenshot visual diff всех 8 макетов на artifact run 464.
2. Закрывать только конкретные оставшиеся визуальные P1; не менять бизнес-логику и данные ради косметики.
3. После каждого изменения сохранять один общий implementation head для desktop/tablet/mobile QA.
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
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.

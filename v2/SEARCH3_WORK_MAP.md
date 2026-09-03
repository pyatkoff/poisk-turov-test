# Search3 — карта работ

Последнее обновление: 2026-09-03  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation baseline: `ecedaeb1396b553c7150e37d7c4031a5915dcd51`  
Текущий visual candidate: `c1ef321610a647af99515ec15b1bfd67dd1b4603`; макет №1 уплотнён, filter sub-screens и реальный MAX/Telegram handoff включены в единый QA

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в проверку не входят.

| № | Утверждённый макет | Покрываемые состояния | Viewport | Статус | Следующее действие |
|---:|---|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | desktop + mobile | REVIEW | Карточки уплотнены до hotel-level иерархии; проверить свежий desktop/tablet/mobile artifact |
| 2 | Макет фильтров поиска туров AnyTour | filter entry, popular, hotel, amenities, meal, room, tourists, flight, price, sorting | mobile; desktop rail как адаптация | REVIEW | Реализованы реальные sub-screens category/rating/meal/direct, full reset и apply/cancel staging; проверить tablet/mobile artifacts |
| 3 | Выбор рейсов AnyTour: десктоп и мобильная версия | outbound/inbound variants, baggage, price delta, total, continue | desktop + mobile | REVIEW | Сверить структуру вариантов и sticky/summary на всех viewport |
| 4 | Итог тура AnyTour: десктоп и мобильная версии | hotel, room, flights, services, tourists, total, submit | desktop + mobile | REVIEW | Проверить секции и цену после выбора рейса; убрать визуальные склейки |
| 5 | Дизайн страницы бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | desktop + mobile | REVIEW | Дожать геометрию Tour Details без изменения booking-семантики |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram handoff | desktop + mobile | REVIEW | Все 4 lead-состояния теперь обязательны в desktop/tablet/mobile QA; проверить свежие artifacts и retry |
| 7 | Спецификация футера AnyTour: десктоп и мобильная версия | navigation, support, social, apps, trust, legal | desktop + mobile | REVIEW | Desktop readability/grid и canonical links исправлены; подтвердить tablet/mobile screenshots |
| 8 | Цельный mobile flow AnyTour: бронирование тура и общение | search → results → hotel → tour → flight → total → lead → messenger | mobile | REVIEW | Полный путь и реальный MAX/Telegram handoff покрыты QA; проверить свежие mobile screenshots |

## Матрица QA

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

- Изолированный preview, noindex и запрет production: **DONE**.
- Функциональный путь search → success/error: **DONE на прежнем baseline**, повторный единый прогон нужен после визуальных изменений.
- Живой desktop preview проверен 2026-09-03:
  - реальные результаты загружаются;
  - toolbar не перекрывает первую карточку;
  - горизонтального overflow нет;
  - hotel-level CTA остаётся `Показать туры`.
- Визуальное одобрение: **LOCKED**.
- Production rollout: **LOCKED** до отдельного явного подтверждения владельца.

## Приоритет оставшейся реализации

1. **P1 — Footer (макет 7):** восстановить полную структуру desktop/mobile на фактических ссылках и данных.
2. **P1 — Results/cards (макет 1):** приблизить иерархию, высоту, факты и CTA к эталону без fake-data.
3. **P1 — Filters (макет 2):** подтвердить свежие tablet/mobile screenshots, apply/cancel и chips.
4. **P1 — Tour details + flights + total (макеты 3–5):** единая геометрия и цена после выбора рейса.
5. **P1 — Lead kit (макет 6):** entry/sending/success/error/MAX/TG, включая retry.
6. **P1 — Mobile E2E (макет 8):** проверить свежие screenshots и переходы success → MAX/TG.
7. **Final:** единый QA одного head на desktop/tablet/mobile и owner visual approval.

## Правила реализации

- Зелёный функциональный тест не означает визуальное одобрение.
- Не добавлять неподтверждённые отзывы, удобства, моментальное подтверждение или иные данные, которых нет в Tourvisor/current-offer.
- Карточка отеля показывает цену «от» и `Показать туры`; покупка доступна только для конкретного проверенного тура.
- Сохранять progressive loading, stale-state guards, Metrika, lead transport и pricing.
- Production запрещён до явного визуального одобрения владельца.

## Журнал

- 2026-09-03 — создана каноническая карта; обновления карты исключены из deploy-trigger.
- 2026-09-03 — workflow расширен до desktop / tablet / mobile.
- 2026-09-03 — добавлен формальный контракт начального состояния во все три viewport.
- 2026-09-03 — mobile results order и tablet card density приведены к прежнему visual-contract.
- 2026-09-03 — source-of-truth исправлен: вместо одного `maket7` зафиксированы все 8 файлов `00_CURRENT_FULL_CYCLE`.
- 2026-09-03 — выполнена живая проверка desktop preview: results загружаются, overlap/overflow верхней выдачи не обнаружены.
- 2026-09-03 — footer приведён к 1180px Search3-grid, увеличена читаемость desktop/mobile, legacy links заменены на canonical `anytoour.ru`.
- 2026-09-03 — QA макета №6 усилен: entry/sending/success/error обязательны на desktop/tablet/mobile.
- 2026-09-03 — для макета №2 добавлены mobile/tablet filter sub-screens на фактических form controls; reset очищает весь filter set, apply и cancel разделены.
- 2026-09-03 — QA макета №2 теперь обязан снять вложенный filter-screen; live DOM подтверждает 4 panel rows и commit CTA.
- 2026-09-03 — вручную пройден desktop Search3: 97 отелей → выбранный тур → 57 flight variants → final total → lead entry; overflow не обнаружен.
- 2026-09-03 — success-state получает реальные MAX/Telegram URL из конфигурации либо factual footer; общий канал не называется чатом менеджера.
- 2026-09-03 — QA всех трёх viewport требует активные HTTPS handoff-ссылки на `max.ru` и `t.me`/`telegram.me`.

- 2026-09-03 — макет №1: desktop-карточка уплотнена до hotel-level решения; повторяющиеся room/operator/flight tiles скрыты из сводки, фактические данные сохранены в facts/tour details.

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- все 8 утверждённых макетов имеют закрытые обязательные состояния;
- desktop, tablet и mobile проходят один и тот же head;
- все обязательные screenshots созданы;
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.

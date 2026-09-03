# Search3 — карта работ

Последнее обновление: 2026-09-03  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation baseline: `ecedaeb1396b553c7150e37d7c4031a5915dcd51`  
Текущий кандидат: `dd39045eb0bc9c7ae7d6d433623a4025a31a6d12` — требуется единый подтверждённый QA на desktop / tablet / mobile

## Источник истины: 8 утверждённых макетов

Единственный канонический набор — `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`.
Архив `99_ARCHIVE_ITERATIONS` и одиночные ранние макеты в проверку не входят.

| № | Утверждённый макет | Покрываемые состояния | Viewport | Статус | Следующее действие |
|---:|---|---|---|---|---|
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | desktop + mobile | REVIEW | Дожать плотность верхней выдачи и карточек; не подменять реальные данные демонстрационными |
| 2 | Макет фильтров поиска туров AnyTour | filter entry, popular, hotel, amenities, meal, room, tourists, flight, price, sorting | mobile; desktop rail как адаптация | IN PROGRESS | Проверить все подэкраны, выбранные chips, reset/apply и сохранение между поисками |
| 3 | Выбор рейсов AnyTour: десктоп и мобильная версия | outbound/inbound variants, baggage, price delta, total, continue | desktop + mobile | REVIEW | Сверить структуру вариантов и sticky/summary на всех viewport |
| 4 | Итог тура AnyTour: десктоп и мобильная версии | hotel, room, flights, services, tourists, total, submit | desktop + mobile | REVIEW | Проверить секции и цену после выбора рейса; убрать визуальные склейки |
| 5 | Дизайн страницы бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | desktop + mobile | REVIEW | Дожать геометрию Tour Details без изменения booking-семантики |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram handoff | desktop + mobile | REVIEW | Добавить отдельные обязательные screenshots sending/error и проверить возврат/повтор |
| 7 | Спецификация футера AnyTour: десктоп и мобильная версия | navigation, support, social, apps, trust, legal | desktop + mobile | IN PROGRESS | Привести текущую неполную структуру к спецификации на реальных ссылках |
| 8 | Цельный mobile flow AnyTour: бронирование тура и общение | search → results → hotel → tour → flight → total → lead → messenger | mobile | IN PROGRESS | Прогнать как один сценарий и сопоставить каждый шаг с макетами 1–7 |

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
3. **P1 — Filters (макет 2):** проверить все мобильные подэкраны, apply/reset/chips/persistence.
4. **P1 — Tour details + flights + total (макеты 3–5):** единая геометрия и цена после выбора рейса.
5. **P1 — Lead kit (макет 6):** entry/sending/success/error/MAX/TG, включая retry.
6. **P1 — Mobile E2E (макет 8):** финальная последовательная сверка.
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

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- все 8 утверждённых макетов имеют закрытые обязательные состояния;
- desktop, tablet и mobile проходят один и тот же head;
- все обязательные screenshots созданы;
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до sending/success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.

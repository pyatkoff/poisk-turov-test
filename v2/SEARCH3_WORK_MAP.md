# Search3 — карта работ

Последнее обновление: 2026-09-03  
Ветка: `feature/search3-preview`  
Preview: `https://anytoour.ru/_preview/search3/poisk-turov/`  
Последний проверенный implementation baseline: `ecedaeb1396b553c7150e37d7c4031a5915dcd51`

## Правила

- Эта карта — единый источник статуса по Search3.
- Сравниваются только одинаковые состояния preview и утверждённого `maket7`.
- Зелёный функциональный тест не означает визуальное одобрение.
- Не добавлять неподтверждённые отзывы, удобства, моментальное подтверждение или иные данные, которых нет в Tourvisor/current-offer.
- Карточка отеля показывает цену «от» и `Показать туры`; покупка доступна только для конкретного проверенного тура.
- Production запрещён до явного визуального одобрения владельца.

## Статусы

| Зона | Статус | Критерий завершения | Следующее действие |
|---|---|---|---|
| Изолированный preview | DONE | Preview отделён от production, noindex, lead submission выключен | Сохранять lock |
| Полный функциональный путь | DONE (baseline) | Search → results → hotel tours → tour → flights → final → lead states | Повторить после финального visual pass |
| Desktop: верх выдачи | REVIEW | Toolbar не перекрывает карточки и совпадает с шириной results | Сверить свежий screenshot с maket7 |
| Desktop: карточки отелей | REVIEW | Реальные rating/sea/room/operator/flight, ровные media/content, без overflow | Проверить свежий artifact |
| Mobile: верх выдачи | IN PROGRESS | `Найдено → изменить поиск → Фильтры/Сортировка → карточки`; одна строка равных кнопок | Подтвердить новым QA artifact |
| Mobile: карточки | IN PROGRESS | Читаемая иерархия, без горизонтального overflow, CTA виден | Подтвердить новым QA artifact |
| Tablet 834×1112 | IN PROGRESS | Отдельный полный QA-flow и визуально чистые карточки/toolbar/filter drawer | Получить первый tablet artifact |
| Filter rail / drawer | REVIEW | Desktop sticky rail; mobile/tablet drawer; controls не дублируют primary form | Визуальная сверка плотности |
| Expanded hotel tours | REVIEW | Реальные варианты, фильтры питания, корректный CTA | Сверить desktop/tablet/mobile |
| Concrete tour | REVIEW | Известные даты, ночи, туристы, номер, питание, оператор, цена | Сверить геометрию |
| Flights | REVIEW | Варианты, багаж, выбор и Continue не ломают цену | Финальный regression |
| Final review | REVIEW | Итоговые секции и total не перекрываются | Финальный regression |
| Lead states | REVIEW | Entry/sending/success/error проходят QA | Финальный regression |
| Footer | REVIEW | Desktop и адаптивы без дублей и overflow | Сверить свежие screenshots |
| Semantic audit | TODO | Нет fake-data; CTA и pricing соответствуют контракту | Пройти после visual fixes |
| Owner visual approval | LOCKED | Владелец явно одобрил preview | Только после P0=0/P1=0 |
| Production rollout | LOCKED | Есть owner approval и зелёный финальный regression | Отдельное подтверждение |

## Активная итерация

1. Получить фактический результат нового тройного QA: desktop / tablet / mobile.
2. Сверить начальный экран поиска и верх выдачи с `maket7`.
3. Исправить только визуальные/семантические расхождения без редизайна.
4. Повторить полный regression.
5. Обновить эту карту с доказательствами и новым baseline.

## Definition of done

Search3 готов к визуальному одобрению только если:

- P0 = 0 и визуальный P1 = 0;
- desktop, tablet и mobile проходят один и тот же head;
- все обязательные screenshots созданы;
- нет горизонтального overflow, перекрытий и скачков layout;
- поиск и booking-flow проходят до success/error;
- соблюдены Tourvisor, pricing, Metrika, lead и production-lock контракты.

# ANEX → тестовый Search3: точка интеграции

Дата аудита: 2026-09-07. Проект: только `pyatkoff/poisk-turov-test`.

## Основа

- ANEX-адаптер: `feature/anex-search-adapter-20260907` на
  `b06bc6949c7119a3c45e6d17f309b5e1c18815d4`.
- Актуальный Search3: `release/search3-production-ready-v1` на
  `45492347f760fdc6e568996c0319f7fef78fef68`.
- Этот внедренческий пакет ответвляется от ANEX-адаптера и не изменяет release,
  main, production или опубликованный preview.

## Владельцы частей

| Часть | Действующий владелец | Решение первого этапа |
| --- | --- | --- |
| Нормализация ответа ANEX | `app/integrations/anex-normalizer.php` | Сохраняется. Он уже передаёт резолверу `anex_online` и `PRICES.hotelKey`. |
| Поиск и раскрытие ANEX | `app/integrations/anex-search.php` | Сохраняется как внутренний серверный слой; HTTP-сессия появится в S3-ANEX-02. |
| Соответствие отелей | `app/integrations/anex-xml-exact-registry.php` | Один принятый ANEX hotel ID разрешается для меток `anex_xml` и `anex_online`, только в preview. |
| Текущий HTTP API поиска сайта | `v2/api-v2.php` | Пока не изменяется. Здесь находится Tourvisor-контракт, который нельзя смешивать с первым безопасным срезом ANEX. |
| Представление Search3 | `src/search3/` → восемь собранных `v2/search3-*` assets | Пока не изменяется: текущий слой владеет представлением, а данные ему даёт существующий runtime. |
| Маршрут Search3 | `v2/poisk-turov/index.php` и `v2/search3-presentation-v1.php` | Пока не изменяется. Флаг и подключение источника относятся к S3-ANEX-02/04. |

## Реализованный контракт S3-ANEX-01

Полный audited-реестр по-прежнему хранит происхождение `anex_xml` и работает
только при явном scope `preview`. Его новый `previewResolver()` подходит к
существующей сигнатуре нормализатора `(provider, externalId)`.

При ответе Online API цепочка теперь выглядит так:

`PRICES.hotelKey` → `anex_online` → единый ANEX hotel ID → принятая запись
реестра → `catalog_hotels.id`.

Tourvisor, Andromeda, неизвестные provider labels, production scope и
неподтверждённые ANEX ID возвращают `null`. Supplier ID не используется как
резервный AnyTour ID.

## Следующая точка изменения

S3-ANEX-02 должен добавить отдельный серверный HTTP-контроллер/композиционный
слой рядом с существующим API, а не менять Tourvisor-запросы внутри
`v2/api-v2.php`. Контроллеру нужны ограниченная сессия с TTL, непрозрачные
ключи предложений, перевод критериев через ANEX-справочники и выключенный по
умолчанию preview-флаг. После стабилизации этого контракта S3-ANEX-04 сможет
подключить источник в presentation/runtime Search3 без дублирования карточек.

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
| Поиск и раскрытие ANEX | `app/integrations/anex-search.php` | Серверный snapshot сохраняет только критерии и минимальные supplier-ссылки, без клиента и токена. |
| Соответствие отелей | `app/integrations/anex-xml-exact-registry.php` | Один принятый ANEX hotel ID разрешается для меток `anex_xml` и `anex_online`, только в preview. |
| Текущий HTTP API поиска сайта | `v2/api-v2.php` | Пока не изменяется. Здесь находится Tourvisor-контракт, который нельзя смешивать с первым безопасным срезом ANEX. |
| Представление Search3 | `src/search3/` → восемь собранных `v2/search3-*` assets | Пока не изменяется: текущий слой владеет представлением, а данные ему даёт существующий runtime. |
| Preview HTTP ANEX | `v2/api-anex-preview.php` и `app/integrations/anex-preview-gateway.php` | Новый POST/JSON-маршрут выключен по умолчанию. Сессия имеет TTL и лимит, supplier ID остаются на сервере. |
| Маршрут Search3 | `v2/poisk-turov/index.php` и `v2/search3-presentation-v1.php` | Пока не изменяется. Подключение источника к интерфейсу относится к S3-ANEX-04. |

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

## Реализованная основа S3-ANEX-02

Отдельный контроллер находится рядом с существующим API и не меняет
Tourvisor-запросы внутри `v2/api-v2.php`. Он принимает только POST/JSON,
проверяет размер и форму запроса, работает при явном серверном флаге
`ANYTOUR_ANEX_PREVIEW_ENABLED=1`, использует secure/HttpOnly/SameSite cookie,
15-минутный TTL и ограничение числа обращений.

Между запросами search → expand → flights хранится минимальный серверный
snapshot. Клиент и токен не сериализуются. `supplier_offer_id` удаляется из
публичных ответов; браузер возвращает только непрозрачный `offer_key`.
Повреждённое/устаревшее состояние отвергается до обращения к ANEX, а новый
неудачный поиск удаляет старые selectable supplier-ссылки.

Оставшаяся часть S3-ANEX-02 — перевести значения действующей формы Search3 в
ID справочников ANEX и определить способ включения маршрута только в isolated
preview. После этого S3-ANEX-04 сможет подключить источник в runtime Search3
без дублирования карточек.

# Andromeda → актуальный SEARCH: ограниченная передача

Issue #1717 → #996 / S3_OTA_PROVIDER_PARITY. 9 сентября 2026.

Источник: `365e30fa11dc86d0e7750d16ca52c9d72661120c`, PR #1781.
Принимающая release: `f3a4cf3b471bc44cff13021096d1013586e5c4c5`.
Машиночитаемый состав и Git blob SHA: [manifest](andromeda-release-handoff-20260909.json).

Это конкретный вход для реализации **read-only потребителя** в актуальном SEARCH,
не разрешение на merge старой ANEX-ветки или публикацию whole-site.
Публикацией и продолжением live-пагинации по-прежнему владеет исполнитель #1781.

## Минимальный пакет

Шесть файлов `app/integrations/andromeda-*.php` и семь автономных PHP fixtures
перечислены в manifest. Их нет в указанной release. Они образуют замкнутое ядро:
client/transport → normalizer/resolver → private offer store → search handler.
Fixtures используют внедрённый транспорт; каждый тест запускается отдельным PHP-процессом.
Требуется PHP с mbstring. Выполнять старые capture/import workflows не нужно.

`v2/api-andromeda-search3-preview.php` — **reference only**:
сейчас зависит от ANEX route/helpers и собственного private config/preview path.
Не копировать его вместе с ANEX helper chain в release. Первый потребитель работает
офлайн на проекции handler; отдельный согласованный HTTP пакет затем подключит
существующие private persistence, same-origin защиту и account budget.
`v2/anex-search3-preview-v1.js` также только источник примеров, не переносимый renderer.

SEARCH использует существующий `V2Results.render` в
`v2/results-renderer-v5.js`, локальные фильтры и lifecycle.
В release пока **нет** универсального provider consumer API; не считать его готовым.
`v2/tour-controller-v4.js` в первом пакете не менять:
его `.direct-tour` и `selectTour(tid)` относятся к Tourvisor.
Нельзя подставить хеш Андромеды в Tourvisor tid, flight или lead.

## Контракт проекции

- Envelope handler: provider, search_ref, generation, status, page, pages_count,
  date_range, hotels, offers, selection_enabled=false.
- Hotel key: `catalog:<accepted local id>`; без связи —
  `andromeda:<supplier_namespace>:<external_hotel_id>`.
  Namespace `andromeda_catalog` и `operator_<operator_ref>` различаются.
- Offer identity: provider + search_ref + generation + offer_ref.
  Оператор — отдельные operator_ref/operator; не источник API.
- Сохранять check_in, nights, adults, children, room **и placement**,
  meal.label/operator_id/andromeda_id. Не восстанавливать identity по цене/названию.
  Для потребителя предпочитать исходный offer tuple handler:
  текущая addon-проекция преобразует поля через ANEX helper и не является общим контрактом.
- Цена: decimal string amount, currency/currency_id, kind=offer, fees=unknown, final=false.
  Одинаковые минимумы не доказывают одинаковые пакеты; quote не реализован.
- hotel_content: source=andromeda, nullable image_url/hotel_url, region/category.
  Текстового description здесь нет. Экранирование, безопасный HTTPS,
  подпись происхождения и fallback фото обязательны; нет серверной загрузки hotel_url.

## Полнота и сохранность

Каждая страница — отдельный private checkpoint до сети. Неизвестная попытка не
повторяется. Следующая страница разрешается по последней успешно полученной
PAGES_COUNT, а не только по первой. Supplier page count может изменяться.
Состояние отдельной нормализованной страницы partial не равно завершению коллектора:
завершение требует всех последовательных страниц и учёта отклонённых строк.
Ошибка сохраняет уже полученные предложения; новое поколение отбрасывает старые ответы.

Нынешний own-preview ограничен загруженным Египтом и поддержанными условиями.
5 000 000/месяц — сообщённый владельцем лимит, не доказанная мгновенная квота.
Не выводить из него произвольный RPS и не переносить ANEX лимиты.
Private sid, credentials, raw supplier offer IDs не попадают в браузер/fixtures.

## Проверено и что ещё требуется

На source прошли protocol34380948828 и Security34380948812.
Scoped deploy34381046363 сохранил exact hashes; artifact10115848594,
digest `sha256:076e926f57282b6ba63ec0cd0af91c0afc9afd991ed422a70829ec74915f6857`. Логи/metadata прочитаны,
независимое скачивание ZIP не выполнялось.

Whole-site build34380948653 **красный**: trailing blank line в API route на строке212,
до самой сборки. Владельцу #1781 передано; этот документ gate не снимает.
Никакой whole-site artifact из этой старой основы не следует публиковать.

Read-only browser проверка уже открытого поиска:107 отелей Андромеды,167 всего,
карточки сохранены после ошибки продолжения. Installer отдельно прочитал13 страниц,
первая PAGES_COUNT46, последняя39. Это не доказательство завершения и не browser
приёмка исправленной версии. Новый поиск/повтор supplier/SQL не запускались.

Принимающий SEARCH должен: освежить release SHA, claim paths в #996, подключить
fixtures в один текущий renderer, проверить namespaces/late generations/partial failure,
same-offer фильтры/локальное раскрытие/сохранность и mobile/desktop.
Включение сети, quote/selection, физический Safari и production остаются отдельными gates.

Это заменяет устаревшее предположение, что #1782 single-page pilot —
текущий live source. Не создаёт новую очередь сопоставления отелей.

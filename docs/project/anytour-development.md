# AnyTour — единая организация разработки

Поручение владельца от 2026-09-09: улучшать интеграции ANEX/Андромеды,
сопоставление отелей, поиск, сайт и SEO параллельно, с одной общей координацией.
Репозиторий: только `pyatkoff/poisk-turov-test`.

## Старт и источники текущего состояния

1. Прочитать `AGENTS.md`, этот документ и scoped AGENTS/README затронутого компонента.
2. Выбрать направление в таблице ниже; прочитать его актуальный state/план и issue.
3. Освежить head ветки, открытые PR, последние сообщения владельца/координатора и
   активные CI/deploy. Исторический SHA или старый автоматический prompt не заменяет свежую точку.
4. Объявить задачу и точные общие файлы в [#996](https://github.com/pyatkoff/poisk-turov-test/issues/996).
   Если их уже меняет другой исполнитель — передать ему требование либо взять независимый файл.

Этот документ владеет распределением работы. `AGENTS.md` и `OWNER_PRIORITY.json`
сохраняют правила допуска/приоритеты; `ARCHITECTURE.md` — архитектурные инварианты;
`TEST_MATRIX.md` и применимые workflows — проверки. Этот пакет не меняет их защиты.
Состояние исполнения не дублируется в этом документе или в расписаниях:

| Что нужно узнать | Авторитетная точка |
| --- | --- |
| Общие приоритеты, владение общими файлами, передача между направлениями | Этот документ и #996 |
| Текущий исполняемый продуктовый пакет общего Search3/сайта | Свежая release: `AUTOPILOT_STATE.json.current_task`; конкретные evidence — профильный audit |
| Порядок продуктовых этапов | `docs/project/search3-product-development-plan.md` на свежей release |
| Текущие supplier/API задачи и доказательства | `docs/integrations/anex-search3-autopilot.md` на свежей ANEX-ветке |
| Текущее массовое сопоставление отелей и evidence ladder | `docs/project/hotel-matching-autopilot.md` и MATCH #1971 |
| Конкретная задача и завершённые/незавершённые условия | Issue направления, linked PR и его артефакт |
| Что действительно опубликовано | Exact source/artifact и успешный deploy/readback; не один head ветки |
| Предыдущие действия/неудачи/измерения | Датированные audit/history; не повторная очередь исполнения |

`AUTOPILOT_STATE.json` из старой ANEX-основы не является состоянием общего сайта.
В release `current_stage.next_action` — указатель на `current_task`, а не вторая очередь.
Новая запись состояния должна исправлять активный указатель; не добавлять ещё один
конкурирующий «Текущий checkpoint» над историей. Историю сохранять с датой и ссылкой.

## Пять направлений

| Код / направление | Ответственность и результат | Очередь | Рабочая основа |
| --- | --- | --- | --- |
| INT — интеграции и provider data | Supplier transport/auth/search/package/price, provider response normalization, сохранённые supplier observations и контракты границы с SEARCH | umbrella draft #1493; Андромеда #1717; состав цены #1685 отдельным пакетом | Сейчас `feature/anex-search-adapter-20260907`; перенос в release отдельным согласованным пакетом |
| MATCH — hotel identity | Массовое Tourvisor ↔ ANEX ↔ Andromeda сопоставление, evidence, accepted/manual/conflict/exclusion registry delta и остаточный review | #1971; история выполненных пакетов #1759 | Matching source на свежей `feature/anex-search-adapter-20260907`; организационный план в release |
| SEARCH — поиск и выдача | Форма, lifecycle, карточки/фильтры/сортировка, общая выдача, выбор/возврат и контекст конкретного предложения | #1646; текущий release-пакет в `current_task`, координация #996 | `release/search3-production-ready-v1`; существующий ANEX addon временно остаётся в своей ветке |
| SITE — сайт и путь к покупке | Общий shell, главная и страницы, навигация, правдивый контент, переходы в поиск | #1719; существующий продуктовый план | Свежая release |
| SEO — органический поиск | Типы посадочных, URL/metadata, ссылки, содержательность и правила будущей индексации | #1720; существующий #395 — зависимость данных/история | Свежая release; сначала read-only аудит и требования |

Координатор в #996 выбирает ближайшие пакеты по пользе, устраняет пересечения,
собирает совместимые результаты и ведёт единое опубликованное состояние.
Рабочие чаты/агенты — исполнители этих задач; переписка не создаёт новую ветку продукта.
INT может иметь независимые задачи ANEX и Андромеды, но не владеет очередью hotel identity.
MATCH не меняет supplier transport/search/price/package и получает provider evidence через явный контракт.
Полное сопоставление каталога не является условием улучшения поиска или начала SITE/SEO.

## Владельцы компонентов — текущие пути, без массового переноса файлов

| Компонент | Основной владелец | Реальные точки изменения / взаимодействия |
| --- | --- | --- |
| Native search form | SEARCH | `v2/index.php`; `src/search3/behavior/search-form.js` только shell/busy/edit |
| Локальные Search3 фильтры / selected handoff | SEARCH | `src/search3/behavior/results/local-hotel-filter.js`, `behavior/summary-cta.js` |
| Оформление формы, карточек и selected | SEARCH | `src/search3/styles/entry-native-controls.css`, `styles/results-layout.css` |
| Общие results/selected/lifecycle | SEARCH | `v2/results-renderer-v5.js`, `tour-controller-v4.js`, `runtime-v3.js`, `search-lifecycle-v6.js`, `search-continue-v6.js`, `catalogs-v2.js` |
| Supplier transport и response normalization | INT | `app/integrations/anex-client.php`, `anex-search.php`, `anex-preview-gateway.php`, `anex-normalizer.php` |
| Принятые ANEX/Andromeda hotel identities / manual / pair exclusions | MATCH | действующие mapping/identity registries; сохранять текущую БД, manual решения и exclusions; shared path только после claim #996 |
| Matching diagnostics / bridge / review datasets | MATCH | существующие `scripts/diagnostics/*matching*`, Andromeda/ANEX bridge diagnostics и новый bounded MATCH tooling; не создавать дублирующий resolver без необходимости |
| Граница provider → Search3 | INT + SEARCH, один исполнитель на пакет | `v2/api-anex-search3-preview.php` и соответствующая Andromeda projection; input/projection меняются по согласованному контракту |
| Временный own-preview addon | SEARCH в ANEX-ветке | `v2/anex-search3-preview-v1.js`; не копировать поверх актуального UI и не добавлять третий renderer |
| Панель matching-review и сохранённые досье | MATCH; auth/runtime contract с INT при необходимости | `app/admin/anex-review/*`, `v2/anex-hotel-review.php`, существующие bounded diagnostics/importer |
| Общая оболочка и страницы | SITE | `v2/site-page-shell-v1.php`, `site-header-v2.*`, `site-footer-v1.*`, `home-v1.php`, `home-entry-v1.php`, общие стили и route templates |
| Переходы / свежесть | SITE; контракт с SEARCH/INT | `v2/site-path-v1.php`, `offer-freshness-v1.php`, country/resort/month renderers |
| SEO policy / metadata / ссылки | SEO; mixed renderer через SITE/SEARCH | `v2/seo-page-*-v1.php`, `seo-internal-links-v1.php`, `seo-structured-data-v1.php`, `seo-content-catalog-v1.php` |
| Индексация и публикация SEO | Координатор + отдельный допуск владельца | `v2/seo-config.php`, `seo-launch-slice-v1.php`, `seo-sitemap-candidates-v1.php`, `sitemap.xml`, `robots.txt` |

`v2/index.php`, `home-v1.php`, `site-page-shell-v1.php`, `country-page-v1.php`,
`seo-*-page-v1.php`, `site-path-v1.php`, `offer-freshness-v1.php` и catalog readers
смешивают несколько направлений: их меняет один объявленный исполнитель.
Префикс `v2/seo-*` не даёт владения всеми runtime/данными/launch guards этого семейства.
Соседнее направление передаёт контракт/пример и получает результат в том же пакете.

Источники/восемь generated assets определяет `src/search3/manifest.json` и существующая
сборка; точная актуальная карта — `src/search3/README.md`. Пустые provenance-слоты
не считаются живыми владельцами. Целевая папочная схема `ARCHITECTURE.md` не даёт
повода переносить рабочие файлы или создавать новый слой ради структуры.

## Одна выдача для нескольких поставщиков

Tourvisor, ANEX и будущая Андромеда предоставляют отдельные source/offer/search
identity и нормализованные данные. Карточки/фильтры/выбор принадлежат общему SEARCH.
Оператор тура и источник API — разные поля; совпадающие числа ID разных API не связывают отели.
Принятые связи, manual priority, pair exclusions и evidence provenance принадлежат MATCH и сохраняются.

Общий provider contract описан в `docs/integrations/multi-provider-search-plan.md`
на ANEX-ветке. Это целевой контракт, не заявление о готовом универсальном registry:
`hotel-identities.php` пока pilot JSON registry, действующие provider resolvers проверяются по свежему source.
Текущей public ANEX projection ещё не хватает полного offer/search context для выбора.

Передача INT → SEARCH включает: source SHA, целевую свежую release SHA, ограниченный
список файлов, поля/fixtures и неизвестные сведения, supplier budget, проверки,
checked/published/deferred, issue принимающего владельца. MATCH передаёт только проверенную
hotel identity/provenance и не подменяет offer/search/package identity. Затем:
1. SEARCH/INT фиксируют один provider contract и владельца общих файлов в #996; MATCH отдельно claim-ит shared registry paths, если они нужны.
2. Короткая ветка от свежей release получает только необходимый bounded пакет.
3. Актуальные UI-владельцы подключают данные; календарь/фильтры/выбор проходят узкий
   совместный сценарий. Новый поиск инвалидирует старые ответы каждого источника.
4. После паритета перенесённая функция прекращает отдельное развитие в addon.
   Legacy код удаляется только после подтверждения отсутствия нужных consumers.

Не merge всю старую ANEX-основу поверх release и не включать DS2/старые layers из неё.
Draft #1493 остаётся изолированным umbrella до конкретного допуска интеграции.
Андромеда #1717: использовать официальный контракт/доступ и проверенные ограниченные ответы;
ANEX HOTELS30/7 дней/лимиты не переносить на неё по предположению.

## Рабочий пакет и параллельность

Одна задача описывает:

```text
Направление / issue / ответственный исполнитель:
Польза и наблюдаемый дефект либо согласованный сценарий:
База branch + SHA; точные owned paths; общие файлы/зависимости:
Входные сохранённые данные/контракт; отсутствующие сведения:
Критерий готовности; узкие проверки; применимые CI:
Исходный PR/SHA → checked → published artifact/route:
Блокер/deferred; следующий доступный шаг:
```

Статус задачи: ready / in_progress / blocked / done. Отдельно записывать
implemented, checked, preview-published и production-approved; они не взаимозаменяемы.
Для документации done означает проверенные ссылки/согласованность, без build/deploy.

Рабочие ветки короткие: `feat/<направление>-<задача>`, `fix/...`, `docs/...` от
нужной свежей базы; постоянные ANEX/release остаются только существующими integration bases.
Перед каждым интегрированием освежать head и проверять чужие изменения; без force-push.
На один общий файл — один активный writer, на одну задачу — один source PR.
Чтение/подготовка независимых компонентов параллельны; shared edits и deploy последовательны.
В #996 claim включает issue, branch/PR, paths и последний подтверждённый шаг.
Зависший claim не снимается по таймеру: сначала проверить PR/CI и текущего исполнителя.

Автопилотные очереди разделены по пяти направлениям. MATCH #1971 — самостоятельная очередь,
а не подзадача INT: она читает `docs/project/hotel-matching-autopilot.md`, current DB/receipts
и историю #1759. INT не продолжает matching `next_action` из старых checkpoints. Общая продуктовая
работа ведёт SEARCH/SITE и независимую SEO-подготовку; supplier/API задачи идут через INT.
Не создавать дублирующие одинаковые очереди. В начале каждого запуска читать текущие документы,
не хранить многократно переписанные старые checkpoints в prompt. Если блокируется одна операция —
сохранить причину и взять независимую задачу. Не останавливаться после одного PR, пока в запуске
есть следующий безопасный шаг.

## Приоритет, проверки и публикация

Авария/потеря заявок/искажение данных выше плановой работы. Затем пользовательский
поиск/выбор и интеграционный шов, сайт/переходы; matching развивается независимо и
не блокирует SEARCH/SITE, SEO-архитектура идёт независимо, массовое расширение страниц
ждёт проверенных шаблонов и допуска публикации.

Один связный source-пакет → узкие необходимые проверки → применимые обязательные CI →
интеграция. Сборку выполнять один раз для пакета; successful exact artifact переиспользовать.
Визуально смотреть затронутое поведение, не повторять весь путь ради документов.
Непроверенное deferred, красный обязательный gate не passed. Защиты/лимиты не ослаблять.
Полезный рост CSS/JS допустим; удалять заменённые owners/правила/handlers в том же пакете,
показывать прирост initial/full/inline отдельно, без новых stacked override skins.

Публикация только штатным точным артефактом: own ANEX-preview для его текущего пакета,
whole-site preview для актуальной release. Один исполнитель публикации по маршруту;
перед повтором проверить реальные runs и артефакт. Production #1334/main — отдельное
визуальное одобрение конкретной версии; старый preview source не является новым release.
Метрика/goals, lead transport/field mapping, protected API URL/payload, price arithmetic,
catalog/manual решения и соседние проекты сохраняются. Production indexation/robots/
canonical/redirects/sitemap не менять без отдельного явного согласования владельца.

## Подробные планы и модели

По поручению владельца от 2026-09-09 подготовлен [подробный план реализации](anytour-roadmap/README.md):
[INT](anytour-roadmap/integrations.md), [SEARCH](anytour-roadmap/search.md),
[SITE](anytour-roadmap/site.md), [SEO](anytour-roadmap/seo.md),
[порядок интеграции и выпуска](anytour-roadmap/delivery.md), [модельные роли](anytour-roadmap/models.md).
Отдельный план массового hotel identity — [MATCH](hotel-matching-autopilot.md), issue #1971.
Перед назначением нового пакета читать его зависимости и приёмку; ARCH/BUILD/REVIEW/ASSIST
подбирать по модельной матрице. Это backlog с проверяемыми результатами, не новая
копия current_task. Готовые стадии и занятые source-пакеты не перезапускаются.
Две рабочие основы сохраняются; provider INT→SEARCH и identity MATCH — ограниченные handoff
с одним owner на каждый общий файл. Настройка моделей фоновых задач не считается изменённой
от записи рекомендации в документах.

## Ближайшие задачи — ссылки, без второй копии очереди

- MATCH: #1971; продолжать только от CURRENT DB после `hotel-full-catalog-delta-1759-20260911-v2`.
  Сначала cross-provider bridge и сохранённое evidence, затем Tourvisor→ANEX operator link→
  `HOTELLIST`/`hotelCode`, manual только последним. Completed/unknown operation не replay.
- SEARCH: продолжить актуальный `current_task` release и #1646; конкретный point-тур
  должен сохранить свой search context до выбора/возврата. Готовые point/reveal/filter
  пакеты не повторять; минимумы источников не объявлять одинаковыми пакетами.
- INT: #1685 — отдельный владелец состава цены; #1717 — supplier/API/package контракт Андромеды.
  Matching #1971 не является INT-задачей. Блокер API не блокирует остальные задачи.
  Старые supplier unknown/completed не переигрывать.
- SITE: #1719, оставшиеся реальные сценарии приёмки и один подтверждённый дефект.
- SEO: #1720, матрица существующих типов страниц/данных/URL/indexability; старые
  main-based SEO PR сначала сверить с актуальной release, не запускать автоматически.

Уточнение владельца 2026-09-09: развивать продукт до уровня ведущих ОТА.
Новая продуктовая последовательность, критерии сравнения и границы будущего
онлайн-бронирования находятся в [действующем product plan](search3-product-development-plan.md),
подробности — в [SEARCH](anytour-roadmap/search.md). `current_task`/`active_queue_ids`
переключаются на первый полезный пакет выдачи; завершённая доступная приёмка
TV-only остаётся evidence, physical Safari/production approval — внешними gates.
Это не новый координатор/план исполнения и не разрешение на production или SEO-indexation.

Отчёт владельцу: новое улучшение и ссылка, что фактически проверено/опубликовано,
существенный блокер и следующий шаг. Число PR, алиасов или неизменный snapshot не
подменяют результат; нулевой прогресс без новых фактов не требует повторного уведомления.
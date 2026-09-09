# SEO — подробный план органического развития AnyTour

Направление [#1720](https://github.com/pyatkoff/poisk-turov-test/issues/1720), координация #996. Основа — свежая `release/search3-production-ready-v1`; проверенный при подготовке source `cf3f4b6fc0a1b82b7e6b4f3e3ae6818c5d198c90`, 9 сентября 2026. Общие правила — [anytour-development.md](../anytour-development.md), AGENTS и OWNER_PRIORITY. Это исполнимый roadmap; current issue/PR хранит статус и точный следующий шаг. Исторический #395 — зависимость данных и старые evidence, а не текущая очередь или основание вернуть DS1.

## Цель и подтверждённая основа

Создавать полезные страницы, которые отвечают на конкретный запрос путешественника и ведут в работающий поиск с правильными параметрами. Рост числа URL сам по себе не является целью. Нельзя обещать рост трафика, позиции или сроки без измерений.

В release уже существуют contract/registry/types/primitives, internal links, structured data, country/resort/month/hotel templates, launch/readiness helpers и многочисленные исторические тесты. `v2/seo-page-registry-v1.php` описывает editorial entries, не публикует routes. `v2/seo-page-contract-v1.php` нормализует содержимое, не включает индексацию. `v2/seo-config.php` требует реальный host, global flag и per-path allowlist, отдельно исключает preview. Эти механизмы не строятся заново. Наличие кода, каталога или старого зелёного test не доказывает текущую публикацию, полноту контента, спрос или фактическую индексацию.

Актуальная production карта/покрытие поисковиками ещё не сняты в этом плане. Массовое SEO расширение остаётся отложенным. **Production robots/noindex/canonical/redirects/sitemap/launch allowlist и исключение существующих страниц из индекса не менять без отдельного явного согласования конкретного изменения.** Preview всегда noindex; UI approval #1334 не является SEO approval. Read-only аудит и подготовка полностью reviewable пакетов разрешены сейчас.

## Владение и роли

Дополнение владельца 2026-09-09 «догнать ведущие ОТА»: использовать уже существующую
[карту типов страниц](../anytour-seo-page-inventory.json) как исходное evidence
для SEO-02/03, не запускать повторную полную инвентаризацию. Дата/полнота реального
production crawl и данных поисковых систем оцениваются отдельно.
Ближайший независимый результат для `S3_OTA_SITE_CONFIDENCE` — brief одного
существующего country/resort/month/hotel типа с подтверждёнными данными:
intent → полезные факты → источник/свежесть → empty/stale → точный Search3 handoff.
Рейтинг, отзывы, координаты и фотографии не создавать из названия отеля или AI.
SEO передаёт требования SITE/INT; mixed renderer меняет один объявленный writer.
Фактическая польза страницы и пригодность данных важнее числа новых URL.
Production-indexation и масштабирование остаются за отдельным допуском SEO-07;
само расширение продуктового плана не включает их.

- SEO владеет intent/content/URL/metadata policy и требованиями перелинковки. SITE реализует mixed PHP renderers и shell; SEARCH — параметры поиска/состояние; INT — данные/происхождение/свежесть. На совместный файл один заранее объявленный writer в #996.
- Адресные владельцы: `seo-page-contract-v1.php`, `seo-page-registry-v1.php`, `seo-page-types-v1.php`, `seo-page-primitives-v1.php`, `seo-internal-links-v1.php`, `seo-structured-data-v1.php`, `seo-content-catalog-v1.php`. Префикс `v2/seo-*` не даёт разрешение менять все data/runtime/publication guards.
- `seo-config.php`, `seo-launch-slice-v1.php`, `seo-sitemap-candidates-v1.php`, `sitemap.xml`, `robots.txt` — отдельный protected publication пакет. `seo-publication-manifest-v1.php` и readiness reports не заменяют решение владельца.
- ARCH выбирает архитектуру/границы, BUILD внедряет bounded пакет, REVIEW независимо проверяет факты и unintended indexation, ASSIST собирает инвентарь/brief/документацию. Конкретные модели назначаются [общей матрицей](models.md). Генерация текста моделью не подтверждает его истинность и не даёт допуск публикации.

## Пакеты исполнения

### SEO-01 — read-only карта действующих страниц

- **Статус/зависимость:** ready сейчас; независим от supplier API, панели совпадений и SITE runtime изменений.
- **Польза и объём:** понять, какие существующие страницы действительно работают, какие только подготовлены и где может теряться органический путь. Один bounded проход текущего route registry/allowlist/templates и сохранённых deployment evidence; адресное HTTP чтение только для неизвестного существенного поля.
- **Вход/выход:** fresh release/main route sources и существующие reports → таблица `route/type/source owner/rendered/public/review only/canonical/robots/sitemap/internal links/data source/last verified/evidence`. Repo-config, отрендеренный HTTP и реальная индексация — отдельные колонки.
- **Владение/роли:** read-only всех нужных sources; ASSIST собирает, ARCH классифицирует, REVIEW проверяет противоречия. Никаких автоматических schema/data writes, crawls всех query combinations или публикаций.
- **Приёмка/узкие проверки:** каждая строка имеет источник и известную дату/состояние; неизвестное явно `unknown`; preview не смешан с production; redirect chains/HTTP ошибки обозначены без придуманной причины. Проверить уникальность route и ссылки отчёта, без asset build/browser/deploy для docs.
- **Откат:** исправить/версионировать отчёт; runtime ничего не меняет. Старые snapshots сохраняются, не выдаются за свежий crawl.

### SEO-02 — матрица запросов, страниц и данных

- **Статус/зависимость:** после SEO-01; может продолжаться при недоступных Webmaster/аналитике.
- **Польза и объём:** для каждой полезной задачи посетителя назначить один canonical page type, не генерировать страну × курорт × месяц × питание × звёзды.
- **Вход/выход:** existing country/resort/month/hotel/hot families и доступные фактические источники → матрица `intent → user answer → page type → required data/freshness → URL identity → canonical candidate → handoff → gaps`. Спрос из существующих разрешённых отчётов добавляется с периодом/источником; без него приоритет помечается product hypothesis.
- **Владение/роли:** ARCH + ASSIST, REVIEW; SITE/SEARCH согласуют route/handoff, INT — пригодность данных. Protected canonical меняется только поздним отдельным пакетом.
- **Приёмка/проверки:** у каждого типа есть отличие от соседнего, пригодный источник, полезный путь в поиск и поведение без данных. Search filters не становятся landing pages только из-за доступного URL. Проверить конфликтующие intent/дубли по representative примерам, без внешних supplier search.
- **Откат:** обновить матрицу и зависимые draft briefs; опубликованные URL не изменяются.

### SEO-03 — один эталонный тип и контракт качества

- **Статус/зависимость:** SEO-02; совместно с SITE-05. Выбрать один существующий тип и 1–3 фактически наполненных примера по данным, не назначать заранее победителя по названию страны.
- **Польза и объём:** страница отвечает на запрос до перехода в поиск, не состоит из подстановки ключевых слов и случайного прайса.
- **Вход/выход:** verified content/snapshot/catalog и agreed brief → заполненный existing page contract, перечень facts→source→updated_at, отсутствующие блоки и критерии quality. При необходимости один bounded fix текущего template через SITE.
- **Владение/роли:** SEO ARCH/ASSIST brief, SITE BUILD renderer, REVIEW фактов/доступности/противоречий. `country-page-v1.php`, `seo-resort-page-v1.php`, `seo-seasonal-page-v1.php`, `seo-hotel-tour-page-v1.php` не редактировать параллельно с SITE.
- **Приёмка/проверки:** уникальная полезность подтверждена содержимым, не word count. Заголовок/описание правдивы; не придумываются цена, скидка, рейсы, правила виз/въезда/платежа, сезонные обещания или рейтинг. Медицинские/правовые/актуальные travel facts при необходимости проверяются отдельно первичными источниками перед текстом. Один complete/one missing-data fixture, escaping/URL validation и визуальная область через SITE. Существующая production индексация не меняется.
- **Откат:** revert конкретного контента/template, raw source/evidence сохранить; нет массового удаления страниц.

### SEO-04 — перелинковка и переходы по смыслу

- **Статус/зависимость:** SEO-01/02 и выбранный template; один кластер существующих разрешённых страниц.
- **Польза и объём:** посетитель движется страна → подходящий курорт/месяц/отель → поиск, понимая различия. Не строить генератор всех комбинаций или глобальное меню тысяч ссылок.
- **Вход/выход:** existing registry и `seo-internal-links-v1.php` → bounded список рёбер с причиной `parent/child/related`, источником и целевым handoff. Mixed renderer реализует SITE по договорённости.
- **Владение/роли:** SEO policy + SITE renderer, BUILD/REVIEW; SEARCH проверяет критерии CTA. `site-path-v1.php` один writer.
- **Приёмка/проверки:** все новые ссылки идут в существующие public routes либо явно изолированный review по принятому сценарию; нет orphan в выбранном кластере, query session URLs/preview не попадают в public links. Breadcrumb отражает структуру. Fixtures на missing target, duplicate route, preview prefix, escaping; один визуальный участок. Не менять robots/canonical/sitemap молча вместе со ссылками.
- **Откат:** убрать только добавленные рёбра/revert link model; URL и его текущая indexability сохраняются.

### SEO-05 — техническая корректность выбранного шаблона

- **Статус/зависимость:** SEO-03; выполнять только подтверждённые defects, без blanket переписывания SEO helpers.
- **Польза и объём:** пользователь и робот видят согласованные title/H1/content/источники, а структурированные данные соответствуют показанному. Одна page family и её current/empty/error случаи.
- **Вход/выход:** rendered evidence и existing tests → bounded исправление display metadata/JSON-LD или reviewable diff защищённого policy, если оно оказалось необходимым. Canonical/noindex проблема фиксируется как отдельный approval request после подготовки конкретного diff; без автоматической публикации.
- **Владение/роли:** SEO `seo-structured-data-v1.php`/page contract; SITE mixed template; ARCH при общей архитектурной коллизии, BUILD + независимый REVIEW.
- **Приёмка/проверки:** JSON-LD валиден и не обещает неподтверждённые Offer/availability/review score; вывод соответствует видимому тексту; title/H1 не пусты и не перепутаны между route. `tests/seo-structured-data-smoke.php`, applicable narrow family smoke и hostile text fixtures. Parser check не выдавать за гарантию rich results или индексации. Cache failure не ломает поиск.
- **Откат:** revert отдельного helper/template; публикационные guards сохраняются, previous exact preview artifact доступен.

### SEO-06 — свежесть, готовность данных и предотвращение пустых страниц

- **Статус/зависимость:** SEO-02/03; INT предоставляет существующие snapshot/provenance semantics, не запускает новый массовый сбор.
- **Польза и объём:** на странице нет устаревших обещаний; пригодность новой landing page подтверждается фактическими данными. Один выбранный тип и его готовность к ограниченной будущей публикации.
- **Вход/выход:** raw saved data, timestamps, current readiness/launch helpers → report `content available/stale/missing + reason + action`, с сохранением отдельно display fallback и будущей indexation recommendation. Не считать 133k catalog rows доказательством полных карточек; supplier фото и Tourvisor фото не смешивать.
- **Владение/роли:** INT raw/data contract, SEO readiness policy, SITE empty/stale display. ARCH + BUILD + REVIEW по одному scoped пакету.
- **Приёмка/проверки:** past departure не выдаётся за свежую возможность, missing price ≠ 0, same-day timestamp не гарантирует bookability, month/year явные. Использовать `tests/offer-freshness-smoke.php` и точный релевантный data-readiness/family smoke; один stale fixture. Existing indexable page не исключается автоматически из индекса при пустом feed. Full catalog/price history не объявляется подтверждённым без сверки.
- **Откат:** вернуть предыдущую display/readiness реализацию; source данные и существующие URL сохранить, SEO policy approval отдельно.

### SEO-07 — ограниченный выпуск после отдельного допуска

- **Статус/зависимость:** сначала prepare-only; apply blocked до явного решения владельца по конкретным URL/robots/canonical/redirect/sitemap/allowlist изменениям. Зависит от SEO-03–06 и готового общего SITE preview, не от полного matching всех отелей.
- **Польза и объём:** запускается небольшой проверенный набор полезных страниц с известными рисками, а не весь математический каталог.
- **Вход/выход:** pinned source/artifact, exact route manifest, before/after HTTP/meta/sitemap diff, facts/readiness, expected behavior и rollback → reviewable approval packet. После отдельного разрешения — штатный bounded deployment и readback. Старые main-based SEO PR1319/1320/1322 не replay; сначала свежая совместимость.
- **Владение/роли:** координатор release и SEO ARCH; один BUILD writer protected files; независимый REVIEW. SITE/SEARCH проверяют, что release не нарушает существующий путь.
- **Приёмка/проверки:** manifest совпадает с реально выпущенным, preview остаётся noindex, approved production пути ведут себя согласно точному diff; никакого непрошенного исключения остальных страниц из индекса. Применимые production/SEO guards, HTTP/canonical/robots/sitemap readback только для impacted set и representative unaffected route. Открытие для индексации не объявляется фактическим включением в поисковый индекс.
- **Откат:** восстановить зафиксированный предыдущий deployment/policy штатной процедурой; rollback route не придумывается после сбоя. Само изменение indexation при откате тоже входит в согласованный план.

### SEO-08 — обратная связь и следующая ограниченная партия

- **Статус/зависимость:** после SEO-07 и достаточного реально доступного периода наблюдений; до публикации можно подготовить report schema без новых counters.
- **Польза и объём:** расширять то, что помогает посетителю, исправлять конкретные проблемы discovery/content/handoff. Один опубликованный cohort и одно обоснованное follow-up действие.
- **Вход/выход:** уже разрешённые Webmaster/Search Console/аналитика/серверные данные и route manifest → отчёт с периодом, источником, denominator и unknowns; перечень confirmed issues и следующая bounded cohort или content fix. Если доступа нет — точный missing source, независимая работа продолжается.
- **Владение/роли:** ASSIST сбор/сведение, ARCH выводы, REVIEW проверяет причинность; BUILD только подтверждённый следующий issue. Метрика/goals не меняются ради отчёта.
- **Приёмка/проверки:** отдельно discovery/crawl/indexed/impressions/clicks/органические входы/search handoff; одно не подменяет другое. Изменение между периодами не объявляется эффектом релиза без учёта сезонности, состава страниц и источника. Нельзя делать вывод по минимальным данным, обещать позиции или массово удалять low-traffic страницы. Проверить mapping route→cohort и полноту данных; supplier/API searches не нужны.
- **Откат:** исправление отчёта/приоритета; при техническом регрессе — approved rollback предыдущего пакета. Новая cohort проходит тот же content/publish gate, автоматического разрешения всем типам нет.

## Последовательность и реальные показатели

SEO-01 и SEO-02 стартуют сейчас параллельно SITE/INT, с read-only источниками и без ожидания доступа API Андромеды. SEO-03/04 идут на одном выбранном шаблоне с SITE; SEO-05/06 закрывают конкретную корректность и readiness. SEO-07 готовится до approval максимально предметно; само разрешение требуется только на конкретное защищённое действие. При блокере этой публикации продолжаются подготовка следующего evidence brief или независимые SEARCH/SITE задачи, а не бесконечные одинаковые checks.

Фиксировать фактические метрики: маршруты inventory по status/evidence; доля записей с обязательными фактами при известном denominator; число confirmed broken internal links/duplicate intents; fresh/stale/missing snapshots; observed HTTP/meta discrepancies; после допуска — реальные indexed/impression/click показатели из конкретного источника и периода. Baseline отсутствует — `not_measured`, а не произвольная цель «+30%». Word count, число сгенерированных URL и число PR не заменяют содержательность или органический результат.

Не плодить новые registry, readiness CLI и отчёты, если существующий владелец покрывает задачу. В каждом issue: baseline, files/owner, dependence, факт/источник, критерии, narrow checks, rollback, exact source/artifact, implemented/checked/preview-published/production-approved, deferred и следующий шаг. Задачи planning/fixture не требуют deploy; изменения кода используют применимые обязательные CI без расширения проверки ради количества.

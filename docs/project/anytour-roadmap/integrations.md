# INT — подробный план интеграций и данных AnyTour

Версия плана: 2026-09-09. Репозиторий `pyatkoff/poisk-turov-test`.
Рабочая основа: `feature/anex-search-adapter-20260907`; umbrella draft #1493.
Проверенный исходный head: `4fa42a3907623a0673a42148981d3e5a0f9c9233`.
Целевая общая release на момент планирования: `cf3f4b6fc0a1b82b7e6b4f3e3ae6818c5d198c90`.
Перед исполнением каждого пакета эти SHA освежаются; они не разрешают запуск старого кода.

Этот документ разбивает согласованную работу на пакеты и зависимости. Активный
пакет назначается в #996 и текущей таблице `docs/integrations/anex-search3-autopilot.md`.
Здесь нет второй очереди автоматического исполнения. Роли моделей ARCH, BUILD,
REVIEW и ASSIST определены в [модельной политике](models.md); они не заменяют владельца
компонента, допуска на запись или одобрение production.

## Результат направления

Владелец получает рабочую защищённую панель, где решения сохраняются вместе с
доказательствами. Поиск получает проверяемые данные ANEX и будущей Андромеды через
один согласованный контракт, со своим контекстом предложения и понятной полнотой.
Принятые связи отелей, источники контента и источники цен остаются различимыми.

Главные показатели: доступность рабочего сценария ручного решения; сохранность
решений при импорте; доля наблюдавшихся supplier ID с принятыми связями; число
предложений с достаточным контекстом выбора; подтверждённые поля цены; фактические
ошибки/таймауты источника. Число новых PR, алиасов и повторных проверок не является
показателем результата. Исторические знаменатели не смешиваются со свежими.

## Что уже сделано и не возвращается в backlog

| Готовая основа | Фактическая граница |
| --- | --- |
| ANEX-клиент, нормализация, принятый registry, наблюдения | Существующий preview работает; это ещё не универсальный адаптер всех поставщиков |
| Общие карточки/источники, локальные фильтры, point-TV, раскрытие сохранённых туров после первых 20 | P1 опубликован на `f690ff035964a1debf3d03ed7e63c33f715f2aa9`; point-TV пока сравнение без законченного контекста выбора |
| Panel renderer, JSON formatter описаний, transactional writer, pair exclusions | Source реализован и проверен; реальная панель не опубликована |
| Immutable dossier packer/archive и canonical bounded schema runner | Readiness выполнена; live migration/import ещё не выполнены |
| 29 сохранённых ANEX-описаний и условия FORTUNA; каталог-карточки по принятым local ID | ANEX-фото отсутствуют в сохранённом наборе; повторный content API и обход media403 не планируются |
| Завершённые каталожные/observed/alias/cached этапы и ручные решения | Исторические checkpoints и unknown не переигрываются и не переписываются |
| Ограниченные TV-эксперименты #1696, AMC1959 и BEACH99284 | Не повторять ради нового отчёта; массовая догрузка не включается |

Последний опубликованный срез: 706 observed, 347 mapped, 359 pending; policy12890,
manual9, staging8362. Это исходная измеренная точка, не обещание текущих чисел.
Последний переносимый triage содержит263 досье и относится к другому моменту.
Readiness34333956876 / artifact10096966061: пять таблиц отсутствовали,
`created=[]`, `import0`, `preservation=true`, supplier0. `CLI auth env=false`
не доказывает отсутствия действующей web-авторизации владельца.

## Порядок и возможность параллельной работы

| Пакет | Результат | Зависимость | Когда начинать |
| --- | --- | --- | --- |
| INT-01 | Закреплённый manifest хранения и конкретная процедура применения | Готовый runner #1709, исходный artifact | Готов к назначению |
| INT-02 | Live durable dossiers с проверенным readback | INT-01, действующий допуск на точную миграцию | После закрепления manifest |
| INT-03 | Trusted owner adapter | Реальная authority/сеанс AnyTour | Исследование доступа параллельно INT-01 |
| INT-04 | Защищённая опубликованная панель чтения | INT-02 и INT-03 | После обоих gates |
| INT-05 | Сохраняемые решения владельца в живой панели | INT-04 и deployed pair guards | После проверки чтения/доступа |
| INT-06 | Полный ANEX offer/search context для SEARCH | Сохранённые реальные ответы, один владелец границы | Независимо от панели |
| INT-07 | Подтверждённая семантика авиадоплат и цены | Отдельный владелец #1685 и реальный контракт | Не дублировать его активную работу |
| INT-08 | Контракт нескольких поставщиков и identity boundary | INT-06; инвентаризация действующих потребителей | До реализации Андромеды, без массового рефакторинга |
| INT-09 | Официальный контракт и ограниченный доступ Андромеды | Документация/канал владельца | Подготовка вопросов доступна сейчас |
| INT-10 | Выключенный адаптер Андромеды с fixtures | INT-08 и INT-09 | После подтверждения контракта |
| INT-11 | Контрольные принятые связи и отдельная очередь Андромеды | INT-10, проверенный pilot artifact | До показа связанного pilot в общем поиске |
| INT-12 | Проверенный ограниченный INT→SEARCH handoff | ANEX: INT-06/08; Андромеда: INT-10/11 | По одному готовому источнику |

Панель, ANEX identity и получение контракта Андромеды могут продвигаться независимо.
На один файл или общий DB writer — один активный исполнитель. Миграции, записи и
публикация последовательны. Пакеты здесь — ограниченные PR/проверяемые операции,
а не обещание завершить живые доступы за фиксированное число часов.

## INT-01 — закрепить применение существующего хранилища

**Польза:** следующий live шаг будет восстанавливаемой конкретной операцией с
заранее известными файлами и ожидаемыми изменениями.

**Владелец:** INT storage, #1647. ARCH определяет состав manifest, BUILD готовит
пакет, REVIEW независимо проверяет границы. Owned paths:
`scripts/diagnostics/anex_review_storage_plan.json`, `anex_review_storage.py`,
`anex_review_storage_runner.php`, `app/admin/anex-review/schema-manager.php`,
`schema.sql`, `dossier-schema.sql`. Готовые механизмы не переписывать.

**Вход:** source SHA canonical #1709; последний проверенный triage artifact и его
реальный SHA256; existing AnyTour DB-helper/SSH route; свежий отчёт отсутствующих
таблиц. Если чтение artifact отказало, использовать только существующий разрешённый
runner restore; не придумывать digest и не обходить локальный403.

**Выход:** manifest с exact source/schema/triage SHA, artifact lineage, action,
фиксированным списком пяти additive таблиц и ожидаемыми counts/bytes; отдельный
preflight/apply/readback порядок; запись применимого допуска и точного rollback.
Read-only остаётся default. Пакет не включает web writes или supplier-запросы.

**Приёмка:** manifest воспроизводится из сохранённых файлов; существующий runner
отвергает несовпадающий digest/source, неожиданную таблицу/схему и частичный конфликт.
Список защиты включает catalog, staging, policy/manual, старые archive versions.

**Проверки:** проверка JSON/schema manifest и один необходимый offline тест изменённой
границы. Не повторять уже зелёный readiness/общие MySQL тесты без изменения риска.

**Риск/откат:** недостаточное происхождение не допускает apply. Возврат manifest к
inspect не удаляет данные. DDL не объявляется транзакционной операцией.
**Передача:** INT-02 получает точный применяемый manifest; вопрос доступа INT-03
решается независимо. Не спрашивать повторного разрешения, если точная операция уже
разрешена; отсутствие допуска не подменять общим одобрением структурного плана.

## INT-02 — live migration и перенос сохранённых досье

**Польза:** панель не потеряет кандидатов при истечении Actions artifact и будет
читать реальную постоянную очередь.

**Владелец:** INT storage. BUILD выполняет существующий bounded runner; REVIEW
проверяет provenance, readback и сохранность. Paths те же, плюс
`scripts/diagnostics/anex-review-dossier-pack.py`, `anex-review-dossier-import.php`,
`app/admin/anex-review/dossier-store.php`. Один live процесс.

**Вход:** INT-01 и точный допуск на additive schema/import; перед действием свежий
head/run, отсутствие активной конкурирующей операции. Процесс: restore→manifest
verification→reservation artifact→schema validation/apply→pack/import→readback.

**Выход:** только пять ожидаемых review/archive таблиц; immutable batch и все raw
rows проверенного triage, включая ошибки/исторические hints без переклассификации;
новый report/artifact с source/schema/triage digest, counts, byte/hash readback,
before/after hashes существующих таблиц.

**Приёмка:** каждая ожидаемая строка восстанавливается byte-for-byte; catalog,
staging, принятые policy/manual и исторические версии сохранены. Fresh observation
country не противоречит dossier. Повтор finalized import возвращает0 inserts без
нового supplier/контентного запроса. Новые досье не превращают hints в кандидатов.

**Проверки:** те проверки точного manifest, которые ещё не выполнены, затем live
DB readback штатного runner. Одного зелёного статуса CI недостаточно. Повторный
apply ради демонстрации идемпотентности не нужен при достаточной disposable-DB проверке.

**Риск/откат:** MySQL DDL может оставить часть новых таблиц. При обрыве сохранить
точный результат, inspect частичной схемы, не DROP/RESET и не повтор неизвестной
команды. Импорт данных транзакционный; публичный reader выключается без удаления
архива. **Передача:** INT-03/04 получают реальные installed schema/source hash и
archive version; при блокере apply продолжается независимый ANEX contract пакет.

## INT-03 — подключить существующую авторизацию владельца

**Польза:** доступ к досье и решениям получает подтверждённый владелец, а не
посетитель публичного preview.

**Владелец:** INT access, #1647; ARCH и REVIEW обязательны, BUILD реализует узкий
adapter. Owned repository path: `app/admin/anex-review/access.php` и его README;
реальный trusted adapter устанавливается в согласованном AnyTour месте **вне
DOCUMENT_ROOT**, не в соседнем проекте. Глобальная конфигурация сервера не входит.

**Вход:** read-only адресная инвентаризация реальной AnyTour owner authority,
login/session lifecycle и DB helper. `ANYTOUR_ANEX_SEARCH3` — публичная сессия
лимитирования и не может стать owner login. Не придумывать пароль, actor или права.

**Выход:** reviewed adapter контракта `ANYTOUR_ANEX_REVIEW_AUTH_FILE` с проверенным
actor, expires_at, capabilities, lazy pdo_factory и активной защищённой сессией;
`write_enabled=false`. Решённые expiry, session rotation, secure cookie, выход
и CSRF смена при смене actor. Секреты только существующим secrets-механизмом.

**Приёмка:** без права нет ни dossier payload, ни обращения к lazy DB; чужой/истёкший
сеанс отклонён; actor не берётся из формы/URL/headers; owner read capability работает.
Если существующей authority действительно нет, зафиксировать конкретно что
отсутствует и подготовить отдельный выбор механизма; новая схема login не внедряется
молчаливо под видом reuse. Остальные пакеты продолжаются.

**Проверки:** узкие auth/expiry/capability/session/CSRF tests и фактическая проверка
owner-сессии только штатным механизмом входа; synthetic CI не выдавать за live auth.
**Риск/откат:** при сомнении доступ fail closed; отключить только adapter/panel route,
не менять existing sessions других страниц. **Передача:** INT-04 получает проверенный
путь authority, capability contract и evidence без credentials.

## INT-04 — опубликовать защищённое чтение панели

**Польза:** владелец получает ссылку и может сравнивать реальные отели ещё до
включения записывающих действий.

**Владелец:** INT panel; BUILD плюс независимый REVIEW auth/manifest. Owned paths:
`app/admin/anex-review/service.php`, `view.php`, `access.php`,
`v2/anex-hotel-review.php`; только необходимый isolated deployment manifest и
существующий механизм публикации. Общая поисковая UI-ветка не затрагивается.

**Вход:** INT-02, INT-03; exact source/artifact provenance и текущий route owner
в #996. Готовые formatter/gallery/queue pagination не строить повторно.

**Выход:** собственная защищённая страница
`/_preview/search3-anex-candidate/anex-hotel-review.php`, noindex/no-store/CSP,
без production leads/analytics и supplier endpoints; очередь по частоте/свежести,
25 строк, search/country/status, реальные архивные версии. Write mode пока выключен.

**Приёмка:** owner видит реальный набор и происхождение полей, альтернативы,
ограниченность/давность evidence, отсутствующие сведения и FORTUNA-контекст;
неавторизованные GET/POST закрыты. Старые263 не подписаны как свежие359 pending.
Фото Tourvisor не обозначаются как ANEX. Запрос страницы не обращается к supplier.

**Проверки:** manifest isolation, login/logout/expiry, одна страница+поиск+пагинация,
desktop и mobile визуальный осмотр затронутого пути. Реальное устройство/Safari,
если недоступно, явно deferred; прежние synthetic screenshots не live доказательство.

**Риск/откат:** publication rollback удаляет доступ к собственному route/возвращает
прежний exact artifact; данные и решения не удаляются. Если server/auth gate не
пройден, URL остаётся intended, а не рабочей пользовательской ссылкой.
**Передача:** INT-05 получает live read-only URL и проверенные session/evidence hashes.

## INT-05 — включить сохранение решений владельца

**Польза:** «Одобрить», «Отклонить пару», «Позже» становятся реальными сохраняемыми
действиями, которые не отменяются будущими импортами.

**Владелец:** INT decisions, #1647. ARCH/REVIEW контролируют транзакционные границы;
BUILD соединяет уже реализованный writer. Paths: `app/admin/anex-review/service.php`,
`access.php`, `app/integrations/anex-search-mapping-registry.php` и действующий
append-only writer только при выявленном пробеле. Не использовать pinned owner9
CLI как web endpoint.

**Вход:** INT-04, deployed pair-exclusion guards всех актуальных acceptance paths,
fresh schema/registry/manual hashes, проверенная capability `anex:decide`.

**Выход:** scoped `write_enabled=true` только после фактических gates; action audit
с actor/date/evidence version/request key; отдельное pair exclusion, не hotel-wide
rejected. «Позже» не блокирует mapping. История исходных evidence неизменна.

**Приёмка:** владелец выбирает конкретное решение; его live readback совпадает с
вводом и используется preview resolver. Двойной клик идемпотентен, устаревшая вкладка
отклоняется, конкурентный reimport не возвращает rejected pair и не выбирает сам
альтернативу. Старые accepted/manual нельзя молчаливо заменить; отдельная будущая
reversal операция требует явного отображения старого решения. Не делать произвольное
реальное approval/rejection ради smoke: проверять решение, действительно выбранное
владельцем; остальные сценарии — disposable fixtures.

**Проверки:** узкий writer/auth/CSRF/replay/stale/concurrent scenario, актуальный
DB preservation и owner decision readback. До реального решения статус честно
`published / awaiting owner decision evidence`, дальнейшая работа не блокируется.

**Риск/откат:** отключение web writes сохраняет все принятые решения и audit;
автоматический reverse/delete не является rollback UI. **Передача:** операторский
runbook и действующая ссылка владельцу; SEARCH получает подтверждённое изменение
registry без supplier поисков для проверки каждого approval.

## INT-06 — передать SEARCH полный контекст предложения ANEX

**Польза:** конкретный тур можно выбрать и открыть повторно, сохранив источник,
поставщиковый контекст и верные условия. Минимум карточки не становится «выбранным туром».

**Владелец:** INT contract + SEARCH consumer, один согласованный writer границы в
#996/#1646. Owned INT paths: `app/integrations/anex-normalizer.php`, `anex-search.php`,
`anex-preview-gateway.php`, `v2/api-anex-search3-preview.php`. UI consumers и
`v2/anex-search3-preview-v1.js` принадлежат SEARCH, не редактируются параллельно.

**Вход:** сохранённые реальные ANEX ответы, точный текущий projection и потребители.
Сначала определить, какие непрозрачные offer/search идентификаторы реально присутствуют,
какие живут только на сервере и какова их действительность. Недостающие поля нельзя
восстановить выдуманным ID. Новый supplier запрос только при конкретном неразрешённом
контрактном вопросе и назначенном bounded probe, не для повторения старых экспериментов.

**Выход:** versioned внутренний DTO/fixture contract: provider, accepted localID,
supplier hotelID, opaque offer reference, source search context, generation,
условия/валюта/price status/expiry/completeness. Секреты/служебные токены не выходят
в public projection. Если полного контекста нет, сравнение доступно, выбор недоступен.

**Приёмка:** разные searchId/одинаковые числовые IDs не смешиваются; старое поколение
не выдаёт selectable offer; return path сохраняет tuple, а не подставляет broad TV
searchId. Граница отдаёт truthful availability/unknown без изменения price arithmetic,
Tourvisor payload и lead transport/field mapping. Прямое бронирование не появляется.

**Проверки:** focused normalizer/projection fixtures: два контекста, collisions,
expiry, unknown fields, отсутствие credentials; один consumer contract test совместно
с SEARCH. **Риск/откат:** отключить только новый selectable path, сохранить сравнение.
**Передача:** exact schema/source+fixtures+unknowns+required consumer behavior в SEARCH
план; не дублировать реализацию выбора/возврата из его отдельного пакета.

## INT-07 — установить состав ANEX-цены без догадок

**Польза:** пользователь понимает, какая сумма конкретна, что ещё неизвестно и
к чему относится минимальная авиадоплата.

**Владелец:** существующий самостоятельный владелец #1685, ARCH/REVIEW; данный
исполнитель координирует результат и не повторяет его запросы/отказы. Owned path:
`scripts/diagnostics/anex_fuel_quote_probe.py` в его draft; применение к price DTO
передаётся отдельным согласованным изменением INT-06/SEARCH.

**Вход:** свежий #1685 и действующий допуск. Владелец дал ANEX B2B
`AdditionalPricesDaily`; replacement локальный, запись в PR ранее заблокирована.
Remote head `28e4283...` всё ещё старый claimless probe: его не запускать для этого
вопроса. Не обходить отказ инструмента и не повторять DNS/запрос автоматически.

**Выход:** проверенная структура/пагинация, семантика tour/currency, единица начисления,
возраст/рейс/направление, связь с исходной ценой и граница минимального значения;
разделение search minimum, concrete offer и final quote. Приватный ответ без
credentials не превращается в публичный artifact с секретами.

**Приёмка:** сумма применяется только к доказанно подходящим условиям; minimum daily
не называется точным топливом выбранного рейса. Error/empty/unknown не равны0;
`currency=3` не называется RUB и tour ID не считается hotel ID без доказательств.
Полный пересчёт пакета — отдельный этап, не эффект read-only авиадоплат.

**Проверки:** fixture tests единиц/валют/округления без изменения защищённой арифметики,
один разрешённый ограниченный контрактный probe у владельца задачи. При заблокированном
канале продолжить INT-03/06/09. **Откат:** сохранять прежнюю цену с честным unknown;
не пересчитывать сохранённые предложения задним числом. **Передача:** подтверждённые
price-component поля и отрицательные примеры SEARCH, не предполагаемая final price.

## INT-08 — закрепить границу нескольких поставщиков

**Польза:** третий источник добавляется адаптером и принятыми IDs, сохраняя одну
выдачу, одну панель общих решений и различимые поля происхождения.

**Владелец:** INT architecture + принимающий SEARCH; ARCH ведёт contract, BUILD
делает только нужный compatibility seam, REVIEW проверяет утечку supplier-specific
предположений. Paths: `docs/integrations/multi-provider-search-plan.md`, действующие
normalizer/registry/projection из INT-06. Новые runtime paths согласуются только
после inventory; не создавать второй registry под видом общей архитектуры.

**Вход:** INT-06 и полный список текущих consumers. `hotel-identities.php` остаётся
pilot JSON; ANEX registry/manual/pair guards — действующая система данных.

**Выход:** contract version и capability matrix по источнику: dates, search async/
pagination, supported criteria/facets, offer/search identity, price/quote, mapping,
content provenance, limits. Раздельные provider hotel IDs, accepted catalog ID,
оператор и источник. Правило для provider-qualified pair exclusion без миграции
ANEX таблиц «на всякий случай».

**Приёмка:** одна и та же цифра hotelID разных API не склеивается; manual precedence
и pair exclusion сохраняются. Неподключённый источник не отображается как failing.
Неполный facet не становится общим фильтром. Общий DTO не требует выдуманных полей
от поставщика. Нет третьего renderer, broad merge или переезда всех файлов.

**Проверки:** контрактные fixtures двух действующих источников+синтетический третий
только для проверки namespace/capability; синтетический пример не доказательство
API Андромеды. **Откат:** совместимое отключение seam без переноса существующих данных.
**Передача:** обязательные/опциональные поля, capabilities и interface tests INT-10
и SEARCH. Если нужен schema change — отдельный pinned пакет с допуском.

## INT-09 — получить официальный контракт Андромеды

**Польза:** разработка начинается с реальных методов/лимитов и понимания того,
что поставщик позволяет пользователю выбрать.

**Владелец:** INT provider, #1717; ARCH анализирует, ASSIST собирает таблицу подтверждений,
REVIEW проверяет неопределённости. Owned docs: `multi-provider-search-plan.md` и
новый scoped контракт Андромеды, путь согласуется в задаче. Runtime пока не меняется.

**Вход:** официальный документ/API-канал и разрешённый read-only доступ от владельца.
Готовить список вопросов можно немедленно. Не угадывать поставщика по похожему
названию, URL, валюту, доступ или auth. Доступ — через существующие secrets, не чат/Git.

**Выход:** таблица confirmed/unknown со ссылками/версией: auth и жизненный цикл,
справочники стран/курортов/отелей/операторов, критерии поиска и окна дат, hotel batches,
async/cancel/pagination, rate windows/Retry-After, валюта/decimal/доплаты/quote,
offer-ID expiry, content rights/freshness и разрешённые методы без бронирования.
Отдельный bounded pilot manifest: конкретные методы/условия/максимум запросов,
stop conditions и сохраняемое evidence. Числа бюджета устанавливаются по контракту,
не копированием ANEX HOTELS30/7дней/60r/min.

**Приёмка:** можно однозначно реализовать один read-only search cycle и понять его
окончание/полноту; все unknown явно перечислены. При отсутствии договора/канала
готовность называется `contract blocked`, а не «Андромеда подключена».

**Проверки:** сверка первичных документов и таблицы открытых вопросов; без build,
supplier call и новой auth схемы. **Откат:** не применим к runtime; неверные гипотезы
удаляются из active contract, история сохраняется. **Передача:** INT-10 получает
версию контракта и разрешённый pilot; панель/ANEX продолжаются независимо.

## INT-10 — выключенный адаптер Андромеды и ограниченные fixtures

**Польза:** появляется проверенный источник данных для общего поиска, который
не включается публично до проверки нормализации и зависимостей.

**Владелец:** INT provider, #1717. BUILD реализует adapter, REVIEW проверяет контракт,
лимиты/секреты/ошибки. Planned new paths в `app/integrations/` для ровно одного
andromeda client/adapter; точные имена назначаются после INT-09. Общий UI не его владение.

**Вход:** INT-08, INT-09, существующий разрешённый server/secrets route; verified
pilot manifest. Сначала offline fixtures, затем только разрешённый ограниченный
read-only live набор. Не запускать bookings/payment/массовые каталоги.

**Выход:** off-by-default адаптер criteria→request→response, provider-scoped timeout,
cancel/pagination/Retry-After, нормализованный DTO и редактированные fixtures
с provenance. Raw secrets и bearer/search credentials не идут в Git/public artifact.
Новый adapter не дублирует транспорт другого проекта.

**Приёмка:** лимиты и actual request params подтверждены; unknown/lossy поля явно
обозначены; ошибка источника не считается пустой выдачей; поздний ответ не попадёт
в новое поколение. Runtime feature выключен, пока не пройдены INT-11/12.

**Проверки:** auth-error/429/timeout/partial/pagination/decimal/identity fixtures;
ровно один согласованный live pilot, сохранённый checkpoint перед возможным повтором.
Неподтверждённый результат не переигрывается автоматически.
**Риск/откат:** оставить adapter выключенным; schema/registry не меняются этим пакетом.
**Передача:** normalized pilot, source/search/offer identity и coverage limits INT-11/12.

## INT-11 — связать контрольную выдачу Андромеды с нашим каталогом

**Польза:** pilot попадает в правильные общие карточки и отдельную очередь решений,
сохраняя возможность увидеть нерешённые отели.

**Владелец:** INT data, #1717/#1647; ARCH проектирует минимальную provider-scoped
identity запись только при необходимости, BUILD выполняет bounded pack/import,
REVIEW независимо проверяет evidence и сохранность ANEX/manual. Existing ANEX
registry/panel используются через согласованный INT-08 seam, не переписываются.

**Вход:** immutable pilot artifact INT-10, fresh accepted registry, реальные supplier
страны/names/coords/sections и доступный локальный каталог. Наличие похожей цены
или общего курорта не является доказательством связи.

**Выход:** отдельный provider-qualified checkpoint до новых чтений, результаты
accepted/review/source_error/unmatched с происхождением. Только strict воспроизводимые
связи либо конкретное решение владельца принимаются. Неизвестные coords не дополняются
догадкой; при отсутствии данных — review. Полный local candidate set и ambiguity
обязательны для автоматического strict решения.

**Приёмка:** sourceID collision с ANEX/TV не меняет существующих связей; rejected pair
не возвращается при reimport; raw evidence/история/version сохраняются. Покрытие
считается по этому pilot observed набору, без утверждения полноты всего каталога.

**Проверки:** один bounded deterministic matcher/import test и фактический readback
только принятого delta. Existing ANEX completed/unknown не сканируются. Если новые
таблицы нужны, сначала отдельный exact schema manifest/gates, не скрытая DDL веб-панели.
**Откат:** отключить отображение pilot/импорт; принятые решения не удалять автоматически.
**Передача:** approved local IDs и непокрытые IDs с truthful status для SEARCH; panel
расширяется на Андромеду отдельным scoped UI пакетом, без ломки ANEX writer.

## INT-12 — передать готовый источник в актуальную общую тестовую выдачу

Это тот же пакет, что SEARCH-05 для ANEX или SEARCH-09 для Андромеды: один
принимающий owner, один PR и одна совместная приёмка. INT-06 и SEARCH-02/04
описывают producer/consumer одного контракта, а не независимые реализации DTO.

**Польза:** владелец проверяет один сайт и один поиск, а добавление источника не
создаёт самостоятельную версию интерфейса.

**Владелец:** принимающий SEARCH owner в #996, INT поставляет адаптер/контракт и
проверяет его границу. ARCH согласует source/target manifest; BUILD одного владельца
редактирует shared files; REVIEW проверяет отсутствие старых layers. Branch — короткая
от **свежей** `release/search3-production-ready-v1`, не broad merge #1493.

**Вход:** ANEX INT-06/08 или Андромеда INT-10/11; exact source SHA, актуальный target
SHA, необходимый список файлов, fixtures, supplier budget, unknown fields, deployed
data/decision compatibility. Каждый источник переносится отдельным ограниченным шагом;
Андромеда не задерживает готовый ANEX handoff.

**Выход:** вход источника в существующий shared lifecycle/store/renderer/filters;
один owner актуальных `results-renderer-v5.js`, `tour-controller-v4.js`, lifecycle
и `src/search3` consumers. ANEX addon не копируется как второй интерфейс. Полнота,
даты, источник/оператор/контент и конкретный offer context сохранены. Production
остаётся выключенным до одобрения конкретного общего release.

**Приёмка:** общий accepted localID создаёт одну карточку; разные provider IDs не
склеиваются. Один late/failing source не уничтожает другие; новый поиск не смешан
со старым. Локальные фильтры оценивают одно предложение и не вызывают API; source
set поддерживает три источника без буквального предположения «оба». Выбор/возврат
сохраняет source-qualified context; неготовый quote явно недоступен. Feature rollback
возвращает прежний источник/renderer без потери decisions/evidence.

**Проверки:** contract fixtures, узкий mixed-source сценарий, применимые isolation/
Security/owner CI, одна сборка связного пакета, mobile/desktop changed-path visual.
Live search только для конкретного поведения в согласованном бюджете. Единственный
whole-site preview publisher фиксирует exact artifact/readback. Физический Safari
не объявляется проверенным без устройства. Повтор completed probe ради статуса запрещён.

**Передача:** source→checked→preview-published/deferred manifest и ссылка владельцу;
после паритета прекратить самостоятельное развитие перенесённой функции в addon.
Удаление legacy только после подтверждения отсутствия consumers. Production migration
готовится отдельным конкретным release/rollback планом и ждёт действующего одобрения
владельца; публикация старой ANEX-основы поверх общего сайта запрещена.

## Общие stop conditions и доказательства результата

- Потерян pinned checkpoint, нарушен source/hash, неизвестна судьба записи/запроса,
  schema несовместима или отказано в инструменте — остановить именно эту операцию,
  сохранить причину и перейти к независимому пакету. Не reset, force-push или обход.
- API остаются последовательными в подтверждённых лимитах каждого токена. Для ANEX:
  pacing1.05с, максимум10r/s и60r/min, Retry-After; HOTELS до30 ANEX IDs, первые7дней
  и один PRICES обычного preview. Фильтры/досье/раскрытие не инициируют supplier.
- Auth/host key/started command/partial response/mux failures не повторять. Existing
  SSH исключение — ровно один2сretry только direct TCP closed доauth/command без
  stdout/mux, ControlMaster/Persist45 только RUNNER_TEMP. Server config не менять.
- Catalog_hotels, прежние accepted/manual, protected API/price/lead/analytics,
  main/production/другие проекты неизменны в этом допуске. Новая additive review
  схема не означает разрешение на переписывание рабочего каталога.
- Каждый выполненный пакет оставляет ссылку issue/PR, source SHA, конкретно проверенное,
  exact published artifact при наличии, DB/file readback и deferred. Готовность source,
  CI, preview, owner decision и production — разные состояния.
- Следующий task выбирается в действующей таблице координатора; документы не запускают
  все12 пакетов одновременно. Краткое сообщение владельцу содержит полезный новый
  результат/ссылку либо новый существенный блокер, а не повтор исторических цифр.

## Прочитанные исходники и задачи

- `AGENTS.md`, `AUTOPILOT.md`, `docs/integrations/anex-search3-autopilot.md` и
  `multi-provider-search-plan.md` на ANEX4fa42a3.
- `docs/project/anytour-development.md` на releasecf3f4b6.
- `app/admin/anex-review/README.md`, `dossier-bridge.md`, `schema-manager.php`,
  `scripts/diagnostics/anex_review_storage_plan.json`.
- [#1647 — панель](https://github.com/pyatkoff/poisk-turov-test/issues/1647),
  [#1717 — Андромеда](https://github.com/pyatkoff/poisk-turov-test/issues/1717),
  [#1685 — авиадоплаты](https://github.com/pyatkoff/poisk-turov-test/pull/1685),
  [#996 — координация](https://github.com/pyatkoff/poisk-turov-test/issues/996).

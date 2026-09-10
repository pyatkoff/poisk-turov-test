# ANEX / AnyTour — текущая работа интеграций и общей выдачи

Дата организации: 2026-09-09. Только `pyatkoff/poisk-turov-test`,
`feature/anex-search-adapter-20260907`, umbrella draft #1493.

## Начало работы и владение

Сначала [единый план AnyTour](https://github.com/pyatkoff/poisk-turov-test/blob/release/search3-production-ready-v1/docs/project/anytour-development.md) на свежей release,
затем AGENTS/scoped README и этот документ на свежей ANEX-ветке. Координатор #996:
INT — данные/адаптеры/панель; SEARCH — общая выдача и временный own-preview addon;
SITE #1719 и SEO #1720 развиваются независимо в актуальной release.
Inherited root `AUTOPILOT_STATE.json` этой ветки содержит старую Search3 очередь:
не использовать её для общего сайта и не переписывать старую основу поверх release.
Действующий product current_task читается только из свежей release.

Перед пакетом сверить branch head, #1493/#1646/#1647 и соответствующий open PR/CI.
На общий файл — один объявленный исполнитель. Эта структура не даёт нового допуска
на production, широкий merge #1493, изменение supplier/lead/price/analytics контрактов.
Датированная история ниже вынесена в [архив](anex-search3-autopilot-history-20260909.md) без изменения содержимого;
она сохраняет checkpoint/digest/ограничения, но её next_action не являются текущими.

## Текущие задачи — обновлять строки, не добавлять вторую очередь

| Задача / владелец | Готово | Следующий доступный шаг / граница |
| --- | --- | --- |
| SEARCH #1646 | Опубликованы All Inclusive/retention/catalog cards/point-TV/reveal #1716. Immutable point searchId/offer context подготовлен в draft1731,50 Node и CI passed; НЕ опубликован | Не повторять подготовку. Согласованный handoff в свежий SEARCH controller/selected context и lead race guard; draft1731 не включает выбор. Внешний lead-контракт прежний, удержанный snapshot не authority: getter перепроверяется при действии. Проекция ANEX тоже требует полного offer/search context; поля не выдумывать |
| INT #1647 | PR1728: 263 persistent досье. Owner login исправлен #1752; аккаунт уже активирован. #1756: опубликованы внешние ссылки Tourvisor для текущих local кандидатов, редактируемые условия из примера владельца; source1d2c3592/run34355057656/artifact10105375379. Account/config preserved, SQL0/supplier0, write=false; 42 link/publisher9 и panel/browser CI passed | В Tourvisor PRO через безопасный вход проверена контрольная пара local21477→ANEX5200 по видимой ссылке оператора HOTELLIST=5200. Далее ограниченный разбор спорных кандидатов с сохранением прямых ссылок как доказательств, без автопринятия. Реальный браузер панели/физический Safari deferred; прежний отказ URL не обходить. Не повторять setup/storage/repair/links publication. Audit: reports/anex-review-tourvisor-links-20260909.json |
| INT #1717 | #1914 явно одобрен владельцем и merged main-control ea190a98; #1911 установил неизменный #1901 sourcefdd099e3/artifact10159104848 в backend общего превью. Run34510605994/job102983493955 SUCCESS; actual receipt10165617032 ZIP4d67cf89 independently verified: published,6 hashes match,5 files activated, backups retained, supplier0/DBwrites0. #1917 selected-only CLI checked06f93033/merged e0143777,113 caller+10 legacy-refusal checks; НЕ invoked. #1905 inspector и #1897 containment готовы, не повторять. Audit reports/andromeda-package-install-20260910.json; прежние audits/checkpoints сохранены; [A1–A6](multi-provider-search-plan.md) | next_action=A3.1: bounded совместная saved-details приёмка, затем разрешённый приватный вызов готового #1917 CLI для одного явно выбранного действующего retained offer; existing bridge/current mapping/transport, durable reservation, max1broninit, private response. #1914 approval/installation CLOSED: не переустанавливать и не просить тот же допуск. Два post-install GET из текущей среды не подключились (ConnectionError/no HTTP); HTTP/browser acceptance deferred, не доказан сбой сайта. Реальный package/PRICES.id→claiminc/tourist price/quote/selection не подтверждены. SEARCH сохраняет A2/UI/publication; A4 ждёт пакета/цены. No new login/PRICE/first-row/unknown replay; mobile/Safari и production gate сохранены |
| INT #1685 | Отдельная работа по составу цены/топливному сбору, собственный diagnostic draft | Прочитать свежий отдельный checkpoint/PR перед действиями. Этот исполнитель не дублирует его запросы или изменения. Не утверждать final price/fuel по минимуму поиска |
| Координация #996/#1493 | Четыре направления, один UI-владелец; общая модель в multi-provider-search-plan.md | Отдельный bounded handoff в свежую release; не merge старую ANEX-основу и не создавать третий renderer |

Полное сопоставление всех отелей не блокирует P1/P2/SITE/SEO. Новые массовые alias/
catalog/price/TV/курортные эксперименты не являются продолжением этой очереди.
Pending — нерешённый статус, не доказанное различие отелей или отсутствие в TV-каталоге.

## Последняя опубликованная P1 точка

Подготовленный, НЕ опубликованный SEARCH source: draft1731,
`e5627a5c5127fd05aca8d01817bd2a1a30132eeb`,50/50 Node, Security34344748010,
artifact build34344748007 success. Исходный broad searchId/lead/UI/requests не менялись.
CI artifact10101226654 (sha256 `d87f7267e08ca8b82338dfbafc70973cfc3e92e6f9ba6be212452fe1288b89cf`)
собран со старой ANEX-основы: НЕ публиковать как whole-site. Публикация и включение
выбора ждут согласованного SEARCH context; повторные live поиски не запускались.

- Source #1716 `f7f4b546aa9883353cea2469605a753a93df9d95`;
  merged/published `f690ff035964a1debf3d03ed7e63c33f715f2aa9`.
- Own preview: https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/
- Run34335715680/job102414501174 success; artifact10097668551,
  sha256 `770d183c60ccff1537f1ce33879825f8fae6d40c68e882010d9bde8c49001021`;
  восстановлен10096966061. Node45/45, Security34335494744/build34335579068 passed.
- Reveal20→40→… использует сохранённые предложения без API; состояние по отелю
  переживает фильтры/рендер; новый поиск очищает его. Response cap/lead context прежние.
  Addon+1339B; CSS/shared assets0. Физическая mobile/новая визуальная проверка deferred:
  локальный file fixture отклонён browser URL policy, отказ не обходился.
- Штатный probe9:38:48UTC сделал8ANEX calls, обновил ранее observed158/297 ID;
  новых manual TV/point/content calls0, новых связей0.
- Свежий snapshot706 observed /347 mapped /359 pending. Policy12890+manual9=12899,
  unique local11067, staging8362. Policy hash
  `4d9d83071a44bea7a5cc2b1faf1d979eb28798f0de77cb32c8dd1de1039f92fc`,
  manual `ac656a750d05050b824891ad9b09f5174db761a8a5862f22de0188d4863fd1c1`.
- Отчёт `reports/anex-point-offer-reveal-20260909.json`.
  Docs checkpoint2eb804e содержит эту точку; после docs-only executable не устаревает.

## Точка сохранности P2 и исторических данных

Storage PR1728 наc9e0bbba89251c92254537fdbc35870e7536c91e завершён:
run34344152765/job102441647242, artifact10100996931,
sha256 `64a2a46f30abd1a976059a6d79f21499c6bc288c75468b1caee492d8f26fe099`.
Восстановлен10097668551; резерв10100986635 сохранён до SQL. Созданы ровно пять
review/archive таблиц, импорт263 и readback263/263; supplier0, SSH1, preservation=true.
Live checkpoint/evidence не переписаны. Storage checkpoint completed; последующие
запуски восстанавливают его report без SQL. Потеря/unknown блокируют replay.
Report canonical sha256 `c93da26e0792d1af94712ae0bd3f016e680fe3907fd9e94256dee52880950cb9`.
Файлы проверены обратно на runner; независимое скачивание ZIP не заявляется.
Readiness34333956876/10096966061 остаётся историей до переноса, не текущим отсутствием таблиц.
Историческая CLI-инвентаризация не определяла web authority; установлен отдельный owner auth.
Приватный runtime панели опубликован #1741/#1747/#1752/#1756, account activated=true
подтверждён readback публикации #1756. Это не браузерная проверка панели; write=false.
Сохранность account/config и всех прежних данных проверена, реальные решения ещё не включены.

Исторический triage263 не равен свежим359 pending: это разные наборы/даты.
25 пересечений его кандидатов с69 сохранёнными TV ID — приоритет, не25 новых связей.
Реестр `anex-search-mapping-registry.php` владеет принятыми связями, manual precedence
и pair exclusions. Pilot `hotel-identities.php` не является универсальным live registry.

Сохранять все live checkpoints/completed/unknown, catalogue8362, owner9, complete2,
saved4, cached14 и alias12 с pinned SHA/lineage из архива. Finalized imports/SQL reads/
неизвестные supplier calls автоматически не повторять. Потеря checkpoint — блокер, не reset.
Catalog_hotels, прежние mappings/manual decisions и raw evidence не переписывать.
Новый review import применяет существующий append-only importer и строгие name/country/
coords/margin/qualifier/competitor критерии; новая панель не означает автоматическое принятие.

Сохранённые29 описаний отелей + условия FORTUNA17097/184раздела уже используются,
ANEX-фото0. TV catalog cards/read-only content pilot/repair готовы. ContentAPI batches,
media403 и ранее выполненные TV эксперименты1696/AMC1959/BEACH99284 не повторять.
Цены разных поисков/операторов не объявлять идентичными пакетами.

## Выполнение, лимиты и проверка результата

- Сначала соответствующий свежий code/head/run/артефакт. Если нужный процесс активен —
  продолжить его; второй не запускать. Не rerun старый алгоритм после runtime-изменения.
- Workflow `anex-access-probe.yml` и его существующие modes; completed mode не повторять
  ради отчёта. Новые trigger-only commits/workflow обходы запрещены.
- Existing secrets, AnyTour SSH/DB helper и client. Token-wide pacing1.05с,
  максимум10r/s и60r/min, Retry-After. HOTELS до30 единых ANEX ID; обычный preview
  первые7дней/одинPRICES. UI reveal/фильтры не вызывают сеть.
- Point-TV: явное действие для принятого localID, отсутствующего в завершённом rawTV;
  исходные критерии + hotelIds, 1 in-flight, максимум3 новых отеля/generation,
  8status по7.5с/deadline60с. Unknown не переигрывать; исходный broad/lead searchId сохранять.
- Старые observed batches не включены как новая цель; если отдельная актуальная задача
  явно назначает новые eligible ID — максимум30 за запуск, restore→reservation artifact→
  последовательный details/candidates. Completed/unknown исключаются, новый artifact/readback обязателен.
- SSH ControlMaster/ControlPersist45 внутри RUNNER_TEMP. Ровно1retry через2с только
  direct TCP closed доauth/command безstdout/mux; auth/hostkey/started/partial/mux не повторять.
  Server config и число попыток не менять.
- Результат подтверждать содержимым checkpoint/JSON/CSV, runner readback/DB preservation
  и artifact digest. Независимый ZIP download не заявлять, если его не было; download403/error1010
  не обходить. Статус run сам по себе не доказывает импорт/публикацию.
- Ошибка одной операции сохраняется с точной причиной; не повторять одинаковый отказ,
  продолжать независимый разрешённый P1/P2 шаг. Живая очередь/ошибки не принимаются по предложению.
- Применимые Security/owner/изоляция и узкие проверки сохраняются. Docs-only не требует
  build/browser/deploy; выполненные source/CI fixes не повторять. Непроверенное deferred.
- После проверенного пакета продолжать следующий безопасный шаг. Результат и next_action
  обновлять в таблице/issue, evidence в профильном audit; не дублировать исторические prompts.

Публикация только own ANEX-preview с exact provenance/noindex/disabled leads/analytics.
Main/production/общий сайт из старой ANEX-основы, catalog_hotels, другие проекты,
Tourvisor URL/payload, Метрика/goals, lead transport/field mapping, price arithmetic,
server config и production SEO indexation не меняются этим допуском.
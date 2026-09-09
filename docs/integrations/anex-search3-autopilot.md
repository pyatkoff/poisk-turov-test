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
| SEARCH #1646 | All Inclusive, сохранность offers/фильтров, контент по принятому localID, явный point-TV #1703/#1708, reveal после20 #1716 опубликованы | Сохранить собственный point searchId/offer identity по всему пути выбора и возврата. До этого point только сравнение. Проекция ANEX тоже требует полного offer/search context; поля не выдумывать |
| INT #1647 | Panel source, formatter, pair-exclusion guards, immutable dossier packer/archive, bounded schema readiness #1704/#1706/#1709 | Пять additive таблиц отсутствовали при последней readiness; explicit bounded migration/import/readback по закреплённому schema/triage manifest, trusted owner auth, isolated panel manifest. Не повторять readiness без конкретного риска. Live owner authority ещё не установлена; write_enabled=false |
| INT #1717 | План будущей Андромеды | Получить официальный API-контракт/доступ; затем выключенный adapter и ограниченные fixtures. Не копировать ANEX лимиты/параметры |
| INT #1685 | Отдельная работа по составу цены/топливному сбору, собственный diagnostic draft | Прочитать свежий отдельный checkpoint/PR перед действиями. Этот исполнитель не дублирует его запросы или изменения. Не утверждать final price/fuel по минимуму поиска |
| Координация #996/#1493 | Четыре направления, один UI-владелец; общая модель в multi-provider-search-plan.md | Отдельный bounded handoff в свежую release; не merge старую ANEX-основу и не создавать третий renderer |

Полное сопоставление всех отелей не блокирует P1/P2/SITE/SEO. Новые массовые alias/
catalog/price/TV/курортные эксперименты не являются продолжением этой очереди.
Pending — нерешённый статус, не доказанное различие отелей или отсутствие в TV-каталоге.

## Последняя опубликованная P1 точка

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

Readiness run34333956876/job102409045342 наe9083cbfe8d337e218e2ef3b2490e8ff98c40d70,
artifact10096966061:263 досье, formatted10132204/semantic5456105 bytes; digest сохранены,
created=[], import0, preservation=true, supplier0. Панель не опубликована.
CLI auth env=false не доказывает отсутствие web-auth. Canonical code #1709;
дублирующий #1712 закрыт без merge. Не вводить альтернативный schema CLI.

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

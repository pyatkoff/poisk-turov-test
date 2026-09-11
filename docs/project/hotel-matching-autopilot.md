# AnyTour MATCH — автономное сопоставление отелей

Дата выделения направления: 2026-09-11. Issue: #1971. Координация: #996.

MATCH — отдельная очередь автопилота. Это **не INT** и не продолжение INT #1717.

## Миссия

Максимально быстро повышать покрытие hotel identity между Tourvisor, ANEX и Andromeda для продаваемых AnyTour направлений, записывая в реестры только доказанные связи и сводя manual review к последнему хвосту.

Работа идёт массовыми delta-пакетами. Если безопасно разобрать сотни или тысячи строк, не дробить их искусственно на пакеты по несколько отелей. После одного PR не останавливаться, если в текущем запуске есть следующий безопасный независимый шаг.

## Старт каждого запуска

1. Прочитать свежие `AGENTS.md`, `docs/project/anytour-development.md`, эту страницу и #1971.
2. Прочитать свежие сообщения #996, heads, открытые MATCH PR, CI и завершённые receipts.
3. Не исполнять historical `next_action`, если после него изменились данные, алгоритм или owner decision.
4. Не replay завершённые/unknown operation IDs. Потеря receipt — блокер, а не повод повторить запись.
5. Claim в #996: issue, branch/PR, exact owned paths, входной snapshot и следующий write boundary.

## Текущая live-база

Последний подтверждённый DB readback после шестистранового Andromeda batch:

- ANEX accepted links: 12,903; unique local: 11,076.
- Andromeda accepted: 6,002; pending: 3,982; conflict: 12; unique local: 5,899.
- Triple ANEX + Tourvisor + Andromeda: 2,609.
- ANEX + Tourvisor only: 8,467.
- Andromeda + Tourvisor only: 3,290.
- `andromeda-1759-country6-20260910-v1` завершён и никогда не replay.

Эти числа — registry/mapping coverage, а не наличие туров в публичной выдаче.

## Приоритет очереди

### M1 — bridge в triple без новых catalogue calls

Использовать текущие accepted связи и сохранённые evidence, чтобы превращать `Andromeda+TV only` и `ANEX+TV only` в triple. Сначала работать с существующими snapshot/DB registry; не скачивать заново завершённые каталоги ради отчёта.

### M2 — pending шести стран

Разобрать 2,764 новых pending из UAE, Thailand, Vietnam, Sri Lanka, Maldives, Cuba. Приоритет — evidence, которое уже есть на сервере. Maldives не принимать blanket-правилом: 208 unique-name rows имеют geography_unknown и требуют дополнительного bridge/geography evidence.

### M3 — Tourvisor → ANEX operator page → hotelCode

Для unresolved ANEX автоматически пройти цепочку доказательств до manual:

1. Найти точный local/Tourvisor hotel.
2. Использовать сохранённую ссылку Tourvisor/оператора, если она есть.
3. Перейти к странице ANEX оператора.
4. Извлечь `hotelCode` из URL, параметров страницы или прямой media/photo URL, если он присутствует как наблюдаемое доказательство.
5. Сопоставить этот ANEX ID с текущим ANEX catalog/Online evidence.
6. Проверить country + однозначность имени/alias + geography/coordinates, если доступны.
7. Сохранить provenance и только после этого готовить delta acceptance.

Manual review разрешён только после прохождения этого автоматического ladder.

### M4 — остаточный manual

На manual отправляются только ambiguous/conflict/недостаточно доказанные строки с уже собранным досье: обе стороны, причины отказа автоматики, ссылки, координаты/география и предлагаемые кандидаты.

## Правила нормализации

- `EX.` / former-name markers разрешено не учитывать при сравнении, если текущая часть имени совпадает.
- Generic слово `HOTEL` можно исключать из identity key.
- Значимые qualifiers (`ANNEX`, `BEACH`, `GARDEN`, корпус/бренд и т.п.) не удалять blanket-правилом.
- Совпадение числовых ID разных API ничего не доказывает.
- `pending`, geography conflict, ambiguous name и pair exclusion не являются accepted.
- При нескольких возможных local targets автоматическое принятие запрещено.

## Write protocol

Каждая операция, изменяющая registry/DB:

1. Новый уникальный operation id.
2. Durable reservation до supplier/DB access.
3. Capture + canonical hash.
4. Plan + reason counters + immutable target set.
5. Перед write — повторная проверка current rows и preservation existing accepted/manual/conflict/exclusions.
6. Transactional apply; ошибка откатывает весь пакет.
7. Post-COMMIT readback каждой вставленной/изменённой строки.
8. Result + receipt + artifact digest + exact source SHA/run.
9. `unknown` не replay; сначала отдельная readback/reconciliation.

Mass acceptance не должен обновлять существующие identities «для удобства»: только доказанный append/delta, если отдельный owner decision явно не разрешил update.

## Владение и границы

MATCH владеет:
- matching/evidence diagnostics;
- cross-provider bridge tooling;
- review datasets и reason taxonomy;
- bounded registry-delta importer/operation manifests;
- отчётами coverage и остаточного хвоста.

MATCH не владеет:
- supplier transport/auth/search/package/price — INT;
- Search3 UI/results/selection — SEARCH;
- site shell/pages — SITE;
- SEO/indexation — SEO.

Если для delta нужен общий registry runtime-файл, MATCH сначала claim-ит exact path в #996. Один активный writer на shared path.

Не менять Metrika/goals, lead delivery, manager routing, price arithmetic, protected public API payloads, production/main или соседние проекты.

Россия и Абхазия не входят в приоритет массового сопоставления: AnyTour их не продаёт.

## Критерий результата итерации

В #1971 после каждого законченного пакета фиксировать:
- examined / accepted / pending / conflict;
- новые unique local targets;
- прирост triple;
- остаток по reason codes;
- supplier/API call count;
- exact source SHA, PR, CI/run, operation id, artifact/receipt;
- отдельно: source prepared / CI checked / live DB written / post-COMMIT verified.

CI fixture никогда не называть live DB readback.

## Следующий безопасный пакет

Сначала построить **read-only bridge review** по текущему live-unresolved без повторных supplier catalogue calls:

- все 2,764 pending шестистранового batch;
- текущие accepted Andromeda→local и ANEX→local;
- current local/Tourvisor identities и aliases;
- сохранённые ANEX Online/TV links/evidence;
- отдельный срез Maldives geography_unknown;
- кандидаты Tourvisor→ANEX operator link→`hotelCode`.

Выход — массовый delta-plan с группами `auto_accept`, `needs_extra_evidence`, `conflict`, `manual_last`, без DB write. После проверки plan — отдельная новая acceptance operation только для однозначного `auto_accept`.
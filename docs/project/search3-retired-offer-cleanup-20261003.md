# Search3 NEXT: удаление остаточных inline offers и CSS

**COMPLETE / STOP — 2026-10-03, Europe/Kaliningrad.** Новый конечный пакет по просьбе владельца продолжить уменьшение и оптимизацию. Избранное, его текущий выключенный флаг и storage сохранены.

Удалены неиспользуемый inline renderer/dispatcher, write-only `openHotel`, пустая verification-обвязка и 145 целых CSS-правил (146 селекторов, 24 семейства). Текущий холодный список предложений, выбранный тур, провайдеры, цена, топливо, рейсы и заявка сохранены. Prototype entry не менялся. Все 55 защищённых функций и 243 сохранённые function declarations AST-идентичны; оставшиеся CSS-условия, декларации и порядок точны после разрешённых удалений.

| JS+CSS | До, собранные байты | После | Уменьшение | Сумма локального gzip до → после |
| --- | ---: | ---: | ---: | ---: |
| Initial LIVE | 617108 | 605992 | 11116 | 163454 → 161480 |
| Complete LIVE | 646903 | 635787 | 11116 | 174822 → 172848 |

Source initial: 761145 → 749671; complete: 798507 → 787033. Gzip — сумма отдельных локальных level-9 потоков, а не измерение HTTP. Ускорение страницы не заявляется.

[PR #4346](https://github.com/pyatkoff/poisk-turov-test/pull/4346): source `2bc09af35eb706178f58dd984e0cd2bb2af7cebb` → release `481d04a2effd381baf9f108f169ba60b534d3a4e`, точное дерево `347ea2981b65b5cc91352489a5e5c2c22b0ebcf3`. Семь blobs совпадают с одобренным локальным commit `3264a076`; поменялись только commit metadata. Все пять exact-head checks SUCCESS: security 37071617561, lead 37071617549, build 37071617541, focused/full visual 37071617493. Reviews/threads нет.

153 event, 67 modal-history и 134 rendering observations характеризованы до/после. Нормализованы только удалённое `openHotel` и пустой cancellation callback. Проверены холодные loaders, favorites/application recorded journey, live bridge, content/facets, compiler/hash/source/owner guards. Visual artifact 11255100142 проверен по ZIP digest: 146 PNG + 12 JSON; все 11 детерминированных receipts побайтно равны предыдущему artifact 11229074817. Просмотрены 24 целевых экрана на mobile/tablet/desktop, регрессий не выявлено. 72/146 PNG побайтно равны; полного pixel parity нет.

Source artifact 11254364476: ZIP SHA256 `a72d7d763ee06eb017273c8708f10097bed1f067f565533a7b3bd19995ecbb05`; все 838 payload hashes/sizes и 27 compiled owners проверены. [Одна команда 5962424985](https://github.com/pyatkoff/poisk-turov-test/issues/4217#issuecomment-5962424985) → [publisher 37072366374](https://github.com/pyatkoff/poisk-turov-test/actions/runs/37072366374), attempt 1 SUCCESS. READY 11255014671 восстановлен побайтно; только `site-path-v1.php` преобразован в NEXT. Deployment 11255795116 ZIP SHA256 `7121fa1421dab3b9c1ec0a2d2d918e987b7b412ee5991b9b3ae465fb653a0e2f`: published_update/published, 838 files, 41 visual assets, 9 routes 200, noindex, lead/LOCAL-only 403, counter/supplier/real-lead 0. Production/sibling не изменены; predecessor `0f4563d5` и rollback сохранены.

Hosted NEXT загрузил app `3e7db210acf8`, styles `ca3fc1a6434d`. Snapshot: 1047 hotels/1453 tours, 24 cards/97 checkboxes, тексты и все прямоугольники первых трёх карточек точны, overflow нет. Фильтр 4★: 395/522; reset: 1047/1453; Oct 5 calendar: 974/1122. RIA: четыре supplied photos, highlights, Standard/breakfast, два предложения 96953/99938. Выбранный snapshot честно блокирует заявку без quote/flights; Back возвращает список. Demo: 24 пары/6 видимых, вариант 2 даёт +1300 → 187700, SVO 06:05/08:05, baggage 20kg/carry-on 5kg, explicit zero fuel; учебная заявка и Back сохраняют условия. Контакты/submit не выполнялись. Pristine LIVE: 17 scripts, form enabled, 0 cards, no search; page-origin errors 0.

Physical Safari/iPhone, реальные supplier search/quote/flights и доставка реальной заявки отдельно **UNVERIFIED**. Source/integration/publisher claims закрыты терминалами 5962545572/5962545770. Финальный checkpoint меняет только этот MD, JSON и `AUTOPILOT_STATE.json.current_task`; вне текущей задачи все bytes/history сохранены, mask SHA256 `0aff0719478881a6b7238cf8b398b45222cb2113908bdb96c845af00b60e90f9`. Main остаётся `d4ac6ee735d37bc2d24286f742abc9ce9be42e08`. Второй публикации и новых microqueues нет. Предыдущий audit: [comparison removal](search3-comparison-removal-20261002.md).

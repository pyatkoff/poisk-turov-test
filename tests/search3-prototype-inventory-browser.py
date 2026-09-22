"""Actual prototype inventory/budget/continuation acceptance, fictional HTTP only.

Reuse the existing prototype fixture server. No native supplier search, live DB,
lead, price authority or physical-device acceptance is claimed by this test.
"""
import copy
import hashlib
import importlib.util
import json
import threading
from datetime import datetime, timedelta, timezone
from http.server import ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlparse

from playwright.sync_api import sync_playwright

SPEC = importlib.util.spec_from_file_location("prototype_fixture", Path(__file__).with_name("search3-prototype-contact-recovery.py"))
FIXTURE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(FIXTURE)
BASE, DATE, ROOT = FIXTURE.BASE, FIXTURE.DATE, FIXTURE.ROOT
EVIDENCE = Path("local-db-price-evidence/inventory")
EVIDENCE.mkdir(parents=True, exist_ok=True)
FIXTURE_DAY = datetime.fromisoformat(DATE).date()
CALENDAR_MONTH = (FIXTURE_DAY.replace(day=28) + timedelta(days=4)).replace(day=1)
CALENDAR_FIRST = CALENDAR_MONTH.isoformat()
CALENDAR_DAY = (CALENDAR_MONTH + timedelta(days=4)).isoformat()
CALENDAR_SECOND = (CALENDAR_MONTH + timedelta(days=22)).isoformat()


def profile(old):
    item = copy.deepcopy(FIXTURE.PROFILE)
    item.update(id=old + 400, name=f"Вымышленный отель {old}")
    item['description'] = 'Техническое описание: здание и количество номеров.'
    item['place'] = 'Рядом с набережной.'
    tags = []
    if old in (101, 102):
        tags.append({'id': 5, 'name': 'Услуги и территория', 'items': [{'id': 23, 'name': 'Бассейн'}]})
    if old == 102:
        tags.append({'id': 3, 'name': 'Пляж и расположение', 'items': [{'id': 15, 'name': 'Первая линия'}]})
    item['hotelInformation'] = {'services': {'tags': tags, 'child': '<p>Мини-клуб</p>'},
                                'infrastructure': {'beach': '<p>Песчаный пляж</p>'}}
    return item


def tour(old, price):
    item = copy.deepcopy(FIXTURE.TOUR)
    item.update(id=f"inventory-{old}", price=price, operator={"name": "ANEX"})
    return {"id": old, "provider": "tourvisor", "tours": [item]}


def stored(old, provider, price, checkin=DATE):
    return {"anytourHotelId": old + 400, "hotel": profile(old), "offers": [{
        "provider": provider, "legacyHotelId": str(old), "currency": "RUB", "price": price,
        "expiresAt": (datetime.now(timezone.utc) + timedelta(minutes=5)).isoformat().replace("+00:00", "Z"),
        "listing": {"schema_version": 1, "provider": provider, "currency": "RUB",
                    "selection_state": "refresh_required", "booking_enabled": False,
                    "listingPrice": price, "listingPriceState": "search_price_confirmation_required",
                    "listingPriceReady": False, "priceConfirmationRequired": True,
                    "quoteState": "unknown", "finalPriceVerified": False, "quoteEvidenceDigest": None,
                    "identity": {key: hashlib.sha256((key + provider).encode()).hexdigest()
                                 for key in ("offer_ref_digest", "search_ref_digest", "provider_hotel_ref_digest")},
                    "tour": {"checkin": checkin, "nights": 7, "meal": {"raw": "AI"},
                             "room": {"raw": "STANDARD"}, "placement": {"raw": "DBL"},
                             "party": {"adults": 2, "children": 0}},
                    "operator": {"raw": "ANEX" if provider == "anex" else "Библио-Глобус"}}
    }]}


def check_width(browser, origin, width):
    context = browser.new_context(viewport={"width": width, "height": 900})
    page = context.new_page()
    page.set_default_timeout(15000)
    calls, native_calls, calendar_calls, observation_calls, forbidden, errors, held = [], [], [], [], [], [], []
    state = {"native": False, "continued": False, "hold": False, "calendar_partial": False,
             "native_failure": False, "database_failure": False}
    page.on("pageerror", lambda error: errors.append(str(error)))

    def intercept(route):
        request = route.request
        url = urlparse(request.url)
        query = parse_qs(url.query, keep_blank_values=True)

        def reply(value, status=200):
            route.fulfill(status=status, content_type="application/json", body=json.dumps(value, ensure_ascii=False))

        if url.path == "/test-photo.svg":
            route.fulfill(content_type="image/svg+xml", body=FIXTURE.PHOTO)
        elif url.path == "/data/departures-v1.php":
            reply({"ok": True, "items": [{"id": 1, "name": "Москва"}]})
        elif url.path == "/data/price-calendar-read-v1.php":
            observation_calls.append(query)
            first, last = query['dateFrom'][0], query['dateTo'][0]
            series = [{"date": DATE, "observed": True, "minPrice": 97500}] if first <= DATE <= last else []
            reply({"ok": True, "source": "latest-known-exact-segments-from-anytour-first-party-observations",
                   "cachedPriceIsFinal": False, "currency": "RUB", "adults": 2, "childrenCount": 0,
                   "departureId": int(query["departureId"][0]), "countryId": int(query["countryId"][0]),
                   "regionId": int(query["regionId"][0]) if query.get("regionId") else None,
                   "dateFrom": first, "dateTo": last, "nightsFrom": int(query["nightsFrom"][0]),
                   "nightsTo": int(query["nightsTo"][0]), "series": series})
        elif url.path.endswith("/hotel-details-read-v1.php"):
            ids = query.get("legacyHotelIds[]", [])
            assert ids, query
            reply({"ok": True, "source": "anytour-canonical-catalog", "catalog": "anytour",
                   "requestedLegacyIds": ids, "missingLegacyIds": [], "items": [profile(int(i)) for i in ids],
                   "links": [{"legacyHotelId": i, "anytourHotelId": int(i) + 400} for i in ids]})
        elif url.path.endswith("/search3-local-results-read-v1.php"):
            params = request.post_data_json["params"]
            if state['database_failure']:
                reply({'ok': False}, 503)
                return
            if state["calendar_partial"]:
                calendar_calls.append(params)
                if params["dateFrom"] == CALENDAR_SECOND:
                    reply({"ok": False, "error": "fictional later calendar window unavailable"}, 503)
                    return
                rows = [stored(120, "anex", 275000, CALENDAR_DAY)] if params["dateFrom"] == CALENDAR_FIRST else []
            else:
                rows = [stored(104, "anex", 250000), stored(105, "andromeda", 300000)] if state["native"] else []
            reply({"ok": True, "data": {"source": "anytour-db-first-results-v1", "scopeVersion": 1,
                   "scope": {"scopeVersion": 1, **params}, "scopeDigest": "c" * 64,
                   "selectionAuthority": False, "hotels": rows}})
        elif url.path == "/_preview/search3-anex-candidate/api-andromeda-search3-preview.php" and request.method == "POST":
            body = request.post_data_json
            native_calls.append(body)
            assert body["generation"] >= 1
            assert body["params"]["countryId"] == "4"
            if state['native_failure']:
                reply({'ok': False}, 503)
                return
            state["native"] = True
            reply({"ok": True, "data": {"provider": "andromeda", "generation": body["generation"], "hotels": []}})
        elif url.path == "/api-v2.php" and request.method == "GET":
            action = query.get("action", [""])[0]
            calls.append({"action": action, "params": query})
            if action == "countries":
                reply([{"id": 4, "name": "Турция"}])
            elif action == "regions":
                reply([{"id": 20, "name": "Анталья", "countryId": 4}, {"id": 23, "name": "Сиде", "countryId": 4}])
            elif action == "meals":
                reply([{"id": 7, "name": "AI"}])
            elif action == "search_start":
                reply({"searchId": 123})
            elif action == "search_status":
                reply({"progress": 100, "status": "complete", "searchId": 123})
            elif action == "search_continue":
                assert query.get("searchId") == ["123"]
                state["continued"] = True
                if state["hold"]:
                    held.append(route)
                else:
                    reply({"requestCount": 1})
            elif action == "search_results":
                assert query.get("limit") == ["5000"], query
                rows = [tour(101, 185451), tour(102, 508504), tour(103, 1506295)]
                if state["continued"]:
                    rows.append(tour(110, 2100000))
                reply(rows)
            else:
                forbidden.append(request.url)
                route.abort()
        elif request.method == "GET" and request.url.startswith(origin + BASE):
            route.continue_()
        else:
            forbidden.append(request.url)
            route.abort()

    context.route("**/*", intercept)

    def count(n):
        page.wait_for_function("n => document.querySelectorAll('.hotel-card').length === n", arg=n)

    def open_filters():
        if width <= 1100:
            page.locator('.results-toolbar [data-action="filters"]').click()

    def apply_filters():
        if width <= 1100:
            page.locator('[data-action="apply-filters"]').click()

    try:
        params = urlencode({"origin": "Москва", "country": 4, "from": DATE, "to": DATE,
                            "minNights": 7, "maxNights": 7, "adults": 2, "ages": ""})
        page.goto(origin + BASE + "prototype-search/?" + params)
        page.locator(".search-submit:not([disabled])").wait_for()
        page.locator('#country').click()
        assert page.locator('[data-action="destination-resort"]').count() == 2
        page.locator('[data-action="destination-resort"][data-value="Анталья"]').click()
        page.locator('[data-action="apply-destination"]').click()
        assert not any(row['action'] == 'search_start' for row in calls)
        page.locator('[data-action="dates"]').first.click()
        page.wait_for_function("day => document.querySelector('[data-action=day-pick][data-date=\"' + day + '\"]')?.getAttribute('aria-label').includes('97')", arg=DATE)
        assert page.locator(f'[data-action="day-pick"][data-date="{DATE}"]').get_attribute('aria-label').replace('\u00a0', ' ').endswith('97 500 ₽')
        assert page.locator('.hotel-card').count() == 0, 'Observation minimum never creates a selectable tour'
        page.screenshot(path=str(EVIDENCE / f"stored-calendar-before-search-{width}.png"))
        page.locator('[data-action="close-modal"]').click()
        for amount in ("1500000.5", "25000000"):
            page.locator('#quick-budget').click()
            page.locator('#budget-max').fill(amount)
            assert page.locator('#budget-max').get_attribute('max') is None
            page.locator('[data-action="apply-budget"]').click()
            assert parse_qs(urlparse(page.url).query)["max"] == [amount]
        page.locator('#quick-budget').click()
        page.locator('[data-action="budget-preset"][data-value=""]').click()
        page.locator('[data-action="apply-budget"]').click()
        assert "max" not in parse_qs(urlparse(page.url).query)
        page.locator('.search-submit').click()
        count(5)
        assert next(row['params'] for row in calls if row['action'] == 'search_start')['regionIds[]'] == ['20']
        assert native_calls[0]['params']['regionIds'] == ['20']
        page.wait_for_function("day => document.querySelector('[data-action=select-date][data-date=\"' + day + '\"]')?.getAttribute('aria-label').includes('97')", arg=DATE)
        page.locator('[data-action="continue-search"]').wait_for()
        assert page.locator('#search-status').is_hidden(), 'No false completion banner above results'
        assert page.locator('#search-more [data-action="continue-search"]').count() == 1
        assert page.locator('#search-more').evaluate("e => !!(document.querySelector('#cards').compareDocumentPosition(e) & Node.DOCUMENT_POSITION_FOLLOWING)")
        assert page.locator('#search-more').bounding_box()['y'] >= page.locator('#cards').bounding_box()['y'] + page.locator('#cards').bounding_box()['height']
        assert page.get_by_text('Быстро сравнить 3 тура', exact=True).count() == 0
        assert page.locator('#hotel-501 .hotel-facts').inner_text() == 'Бассейн'
        assert 'количество номеров' not in page.locator('#hotel-501 .hotel-facts').inner_text()
        open_filters()
        page.locator('[data-filter="amenities"][value="5:23"]').check()
        apply_filters()
        count(2)
        assert '5:23' in parse_qs(urlparse(page.url).query)['amenities'][0]
        assert '185' in page.locator('#price-strip').inner_text()
        open_filters()
        page.locator('[data-filter="amenities"][value="3:15"]').check()
        apply_filters()
        count(1)
        assert page.locator('.hotel-card').get_attribute('id') == 'hotel-502'
        assert '508' in page.locator('#price-strip').inner_text()
        page.screenshot(path=str(EVIDENCE / f"amenity-filters-{width}.png"))
        page.locator('#active-filters [data-key="amenities"][data-value="3:15"]').click()
        page.locator('#active-filters [data-key="amenities"][data-value="5:23"]').click()
        count(5)
        assert len([c for c in calls if c['action'] == 'search_start']) == 1
        assert len(native_calls) == 1
        assert any("103" in name for name in page.locator('.hotel-card').all_inner_texts()), "Premium tour hidden without budget"
        first_start = next(c for c in calls if c["action"] == "search_start")
        assert first_start["params"].get("priceTo", [""]) == [""]
        providers = page.evaluate("""() => {
            const owner = Search3CanonicalProfilesV1.current();
            return [...new Set(owner.read(owner.source(), {}).flatMap(h => h.providers || []))].sort();
        }""")
        assert providers == ["andromeda", "anex", "tourvisor"], providers
        assert len(native_calls) == 1
        state["hold"] = True
        page.locator('[data-action="continue-search"]').click()
        page.wait_for_timeout(100)
        assert len(held) == 1
        # A second same-tick action must not send another continuation.
        assert page.evaluate("() => AnyTourPrototypeData.continueSearch()") is False
        open_filters()
        field = page.locator('#max-price')
        field.fill('200000')
        field.evaluate("e => { window.fixtureBudgetInput = e; }")
        held.pop().fulfill(content_type="application/json", body='{"requestCount":1}')
        page.wait_for_function("document.querySelector('#search-more [data-action=continue-search]') !== null")
        page.wait_for_timeout(150)
        assert field.input_value() == '200000', "An asynchronous result discarded unfinished budget"
        assert field.evaluate("e => e === window.fixtureBudgetInput && document.activeElement === e"), "Focused editor was replaced"
        field.press('Tab')
        apply_filters()
        count(1)
        assert parse_qs(urlparse(page.url).query)["max"] == ["200000"]
        assert "185" in page.locator('#price-strip').inner_text()
        page.locator('#active-filters [data-key="price"]').click()
        count(6)
        assert "max" not in parse_qs(urlparse(page.url).query)
        open_filters()
        assert int(page.locator('#price-range').get_attribute('max')) >= 2100000
        field.fill('600000')
        field.press('Tab')
        apply_filters()
        count(4)
        assert parse_qs(urlparse(page.url).query)["max"] == ["600000"]
        assert len([c for c in calls if c["action"] == "search_start"]) == 1
        assert len([c for c in calls if c["action"] == "search_continue"]) == 1
        page.screenshot(path=str(EVIDENCE / f"budget-continue-{width}.png"))
        page.reload()
        page.locator(".search-submit:not([disabled])").wait_for()
        assert "600" in page.locator('#budget-label').inner_text()
        page.locator('.search-submit').click()
        count(4)
        last_start = [c for c in calls if c["action"] == "search_start"][-1]
        assert last_start["params"].get("priceTo") == ["600000"]
        assert len(native_calls) == 2
        state["calendar_partial"] = True
        page.locator('[data-action="edit-search"]').first.click()
        page.locator('[data-action="dates"]').click()
        calendar_price = page.locator(f'[data-action="day-pick"][data-date="{CALENDAR_DAY}"] small')
        calendar_price.wait_for()
        page.wait_for_function(
            "day => document.querySelector(`[data-action=day-pick][data-date='${day}'] small`)?.textContent.includes('275')",
            arg=CALENDAR_DAY,
        )
        page.wait_for_function(
            "() => document.querySelector('.calendar-legend span')?.textContent.includes('Не все цены загрузились')"
        )
        assert page.locator('.calendar-legend span').first.inner_text() == 'Не все цены загрузились. Даты можно выбрать без цены.'
        assert any(item["dateFrom"] == CALENDAR_FIRST for item in calendar_calls), calendar_calls
        assert any(item["dateFrom"] == CALENDAR_SECOND for item in calendar_calls), calendar_calls
        assert not page.evaluate("document.documentElement.scrollWidth > innerWidth"), "Calendar partial state overflows"
        page.screenshot(path=str(EVIDENCE / f"calendar-partial-{width}.png"))
        assert not page.evaluate("document.documentElement.scrollWidth > innerWidth"), "Document overflow"
        assert not forbidden, forbidden
        assert not errors, errors
        page.screenshot(path=str(EVIDENCE / f"restored-budget-{width}.png"))
        page.locator('[data-action="close-modal"]').click()
        state['calendar_partial'] = False
        state['native_failure'] = True
        state['database_failure'] = True
        page.locator('.search-submit').click()
        page.wait_for_function("document.querySelector('#search-status').textContent.includes('Получены не все предложения')")
        assert page.locator('#search-status').is_visible()
        assert page.locator('#search-more [data-action="continue-search"]').is_visible()
        assert page.locator('.hotel-card').count() > 0
        assert 'Поиск завершён' not in page.locator('#search-status').inner_text()
        page.screenshot(path=str(EVIDENCE / f"partial-source-error-{width}.png"))
        return {"width": width, "status": "passed", "providers": providers,
                "calls": calls, "mocked_andromeda_requests": native_calls,
                "calendar_requests": calendar_calls, "calendar_partial_day": CALENDAR_DAY,
                "forbidden": forbidden, "browser_errors": errors}
    except Exception:
        page.screenshot(path=str(EVIDENCE / f"failure-{width}.png"))
        print(json.dumps({"width": width, "calls": calls, "forbidden": forbidden,
                          "errors": errors, "body": page.locator('body').inner_text()[:5000]}, ensure_ascii=False))
        raise
    finally:
        for route in held:
            route.abort()
        context.close()


def main():
    server = ThreadingHTTPServer(("127.0.0.1", 0), FIXTURE.StaticFiles)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = "http://127.0.0.1:" + str(server.server_port)
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try:
                results = [check_width(browser, origin, width) for width in (390, 1440)]
            finally:
                browser.close()
        receipt = {"test": "search3-prototype-inventory", "live_inventory": False, "published": False,
                   "supplier_requests": 0, "lead_requests": 0, "results": results,
                   "source_sha256": {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest()
                                     for name in ("prototype-search/app.js", "prototype-search/data.js")}}
        (EVIDENCE / "receipt.json").write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n")
        print("SEARCH3_PROTOTYPE_INVENTORY_BROWSER_OK", json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

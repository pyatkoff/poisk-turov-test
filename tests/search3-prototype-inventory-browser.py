"""Actual prototype inventory/budget/continuation acceptance, fictional HTTP only.

Reuse the existing prototype fixture server. No native supplier search, live DB,
lead, price authority or physical-device acceptance is claimed by this test.
"""
import copy
import hashlib
import importlib.util
import json
import threading
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


def profile(old):
    item = copy.deepcopy(FIXTURE.PROFILE)
    item.update(id=old + 400, name=f"Вымышленный отель {old}")
    return item


def tour(old, price):
    item = copy.deepcopy(FIXTURE.TOUR)
    item.update(id=f"inventory-{old}", price=price, operator={"name": "ANEX"})
    return {"id": old, "provider": "tourvisor", "tours": [item]}


def stored(old, provider, price):
    return {"anytourHotelId": old + 400, "hotel": profile(old), "offers": [{
        "provider": provider, "legacyHotelId": str(old), "currency": "RUB", "price": price,
        "listing": {"schema_version": 1, "provider": provider, "currency": "RUB",
                    "selection_state": "refresh_required", "booking_enabled": False,
                    "listingPrice": price, "listingPriceState": "search_price_confirmation_required",
                    "listingPriceReady": False, "priceConfirmationRequired": True,
                    "quoteState": "unknown", "finalPriceVerified": False, "quoteEvidenceDigest": None,
                    "identity": {key: hashlib.sha256((key + provider).encode()).hexdigest()
                                 for key in ("offer_ref_digest", "search_ref_digest", "provider_hotel_ref_digest")},
                    "tour": {"checkin": DATE, "nights": 7, "meal": {"raw": "AI"},
                             "room": {"raw": "STANDARD"}, "placement": {"raw": "DBL"},
                             "party": {"adults": 2, "children": 0}},
                    "operator": {"raw": "ANEX" if provider == "anex" else "Библио-Глобус"}}
    }]}


def check_width(browser, origin, width):
    context = browser.new_context(viewport={"width": width, "height": 900})
    page = context.new_page()
    page.set_default_timeout(15000)
    calls, forbidden, errors, held = [], [], [], []
    state = {"native": False, "continued": False, "hold": False}
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
        elif url.path.endswith("/hotel-details-read-v1.php"):
            ids = query.get("legacyHotelIds[]", [])
            assert ids, query
            reply({"ok": True, "source": "anytour-canonical-catalog", "catalog": "anytour",
                   "requestedLegacyIds": ids, "missingLegacyIds": [], "items": [profile(int(i)) for i in ids],
                   "links": [{"legacyHotelId": i, "anytourHotelId": int(i) + 400} for i in ids]})
        elif url.path.endswith("/search3-local-results-read-v1.php"):
            params = request.post_data_json["params"]
            rows = [stored(104, "anex", 250000), stored(105, "andromeda", 300000)] if state["native"] else []
            reply({"ok": True, "data": {"source": "anytour-db-first-results-v1", "scopeVersion": 1,
                   "scope": {"scopeVersion": 1, **params}, "scopeDigest": "c" * 64,
                   "selectionAuthority": False, "hotels": rows}})
        elif url.path == "/api-v2.php" and request.method == "GET":
            action = query.get("action", [""])[0]
            calls.append({"action": action, "params": query})
            if action == "countries":
                reply([{"id": 4, "name": "Турция"}])
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
                state["native"] = True
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
        page.add_init_script("window.AbortController = class { constructor(){ this.signal = undefined; } abort(){} };")
        page.goto(origin + BASE + "prototype-search/?" + params)
        page.locator(".search-submit:not([disabled])").wait_for()
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
        page.locator('[data-action="continue-search"]').wait_for()
        assert any("103" in name for name in page.locator('.hotel-card').all_inner_texts()), "Premium tour hidden without budget"
        first_start = next(c for c in calls if c["action"] == "search_start")
        assert first_start["params"].get("priceTo", [""]) == [""]
        providers = page.evaluate("""() => {
            const owner = Search3CanonicalProfilesV1.current();
            return [...new Set(owner.read(owner.source(), {}).flatMap(h => h.tours.map(t => t.provider)))].sort();
        }""")
        assert providers == ["andromeda", "anex", "tourvisor"], providers
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
        page.wait_for_function("document.querySelector('#search-status [data-action=continue-search]') !== null")
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
        assert not page.evaluate("document.documentElement.scrollWidth > innerWidth"), "Document overflow"
        assert not forbidden, forbidden
        assert not errors, errors
        page.screenshot(path=str(EVIDENCE / f"restored-budget-{width}.png"))
        return {"width": width, "status": "passed", "providers": providers,
                "calls": calls, "forbidden": forbidden, "browser_errors": errors}
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

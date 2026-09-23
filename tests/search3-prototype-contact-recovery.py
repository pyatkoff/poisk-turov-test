"""Focused #3350 browser acceptance of the real prototype; inventory is fictional.

Run in the existing pinned Search3 browser CI. No supplier, DB or lead request
leaves the fixture router. A successful run is not a live publication receipt.
"""
import hashlib
import json
import mimetypes
import re
import threading
from datetime import datetime, timedelta, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlencode, urlparse

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1] / "v2"
BASE = "/_preview/search3-local-candidate/"
EVIDENCE = Path("local-db-price-evidence/contact-recovery")
EVIDENCE.mkdir(parents=True, exist_ok=True)
DATE = (datetime.now(timezone.utc) + timedelta(days=8)).date().isoformat()
PHOTO = '<svg xmlns="http://www.w3.org/2000/svg" width="700" height="500"><rect fill="#bacad5" width="700" height="500"/></svg>'
PROFILE = {
    "id": 1, "catalog": "anytour", "revision": 1,
    "name": "Тестовый отель — проверка контактов", "category": 5, "rating": 4.7,
    "country": {"name": "Турция"}, "region": {"name": "Анталья"},
    "description": "Вымышленный отель для проверки интерфейса.",
    "images": ["http://127.0.0.1/test-photo.svg"], "hotelInformation": {},
}
TOUR = {
    "id": "exact-contact", "price": 120000, "date": DATE, "nights": 7,
    "adults": 2, "childs": 0, "meal": {"name": "AI"},
    "roomType": "FAMILY SEA VIEW", "placement": "DBL",
    "operator": {"name": "ANEX"}, "fuelCharge": 0,
}


def segment(number, time):
    return {"company": {"name": "Тестовая авиакомпания"}, "number": number,
            "departure": {"date": DATE, "time": time, "port": {"name": "Москва", "id": "SVO"}},
            "arrival": {"date": DATE, "time": "14:00", "port": {"name": "Анталья", "id": "AYT"}},
            "baggage": 20, "carryOn": "5 кг"}


VARIANTS = [
    {"price": {"value": 120000}, "fuelCharge": 0,
     "forward": [segment("TT 111", "10:00")], "backward": [segment("TT 112", "12:00")]},
    {"price": {"value": 133500.5}, "fuelCharge": 0,
     "forward": [segment("TT 211", "14:00")], "backward": [segment("TT 212", "16:00")]},
    {"isDefault": True, "price": None, "fuelCharge": None,
     "forward": [segment("TT 311", "18:00")], "backward": [segment("TT 312", "20:00")]},
]


class StaticFiles(BaseHTTPRequestHandler):
    def do_GET(self):
        pathname = unquote(urlparse(self.path).path)
        target = (ROOT / pathname[len(BASE):]).resolve()
        if not pathname.startswith(BASE) or not target.is_relative_to(ROOT):
            self.send_error(404)
            return
        if target.is_dir():
            target /= "index.html"
        if not target.is_file():
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header("Content-Type", mimetypes.guess_type(target.name)[0] or "application/octet-stream")
        self.end_headers()
        self.wfile.write(target.read_bytes())

    def log_message(self, *_):
        pass


def check_width(browser, origin, width):
    context = browser.new_context(viewport={"width": width, "height": 900})
    page = context.new_page()
    page.set_default_timeout(10000)
    calls, forbidden, errors, checked = [], [], [], []
    state = {"flights": "error"}
    page.on("pageerror", lambda error: errors.append(str(error)))

    def intercept(route):
        request = route.request
        url = urlparse(request.url)
        query = parse_qs(url.query)

        def reply(value, status=200):
            route.fulfill(status=status, content_type="application/json", body=json.dumps(value, ensure_ascii=False))

        if url.path == "/test-photo.svg":
            route.fulfill(content_type="image/svg+xml", body=PHOTO)
        elif url.path == "/data/departures-v1.php":
            reply({"ok": True, "items": [{"id": 1, "name": "Москва"}]})
        elif url.path == "/data/price-calendar-read-v1.php":
            reply({"ok": True, "source": "latest-known-exact-segments-from-anytour-first-party-observations",
                   "cachedPriceIsFinal": False, "currency": "RUB", "adults": 2, "childrenCount": 0,
                   "departureId": int(query["departureId"][0]), "countryId": int(query["countryId"][0]),
                   "regionId": int(query["regionId"][0]) if query.get("regionId") else None,
                   "dateFrom": query["dateFrom"][0], "dateTo": query["dateTo"][0],
                   "nightsFrom": int(query["nightsFrom"][0]), "nightsTo": int(query["nightsTo"][0]), "series": []})
        elif url.path.endswith("/hotel-details-read-v1.php"):
            if "anytourHotelId" in query:
                assert query["anytourHotelId"] == ["1"]
                reply({"ok": True, "source": "anytour-canonical-catalog", "catalog": "anytour", "item": PROFILE})
            else:
                ids = query.get("legacyHotelIds[]", [])
                assert ids == ["101"], ids
                reply({"ok": True, "source": "anytour-canonical-catalog", "catalog": "anytour",
                       "requestedLegacyIds": ids, "missingLegacyIds": [], "items": [PROFILE],
                       "links": [{"legacyHotelId": 101, "anytourHotelId": 1}]})
        elif url.path.endswith("/search3-local-results-read-v1.php"):
            body = request.post_data_json
            if body.get("action") == "meal_catalog":
                assert body == {"action": "meal_catalog", "provider": "tourvisor", "scopeKey": "global"}, body
                assert request.headers.get("x-requested-with") == "AnyTourSearch3"
                reply({"ok": True, "data": {"source": "anytour-search-meal-v1", "provider": "tourvisor", "scopeKey": "global",
                       "available": True, "revision": "a" * 64, "plans": [
                           {"id": 2, "code": "breakfast", "nameRu": "Завтраки", "nativeIds": ["3"]},
                           {"id": 3, "code": "half-board", "nameRu": "Полупансион", "nativeIds": ["4"]},
                           {"id": 7, "code": "all-inclusive", "nameRu": "Всё включено", "nativeIds": ["7"]},
                           {"id": 8, "code": "ultra-all-inclusive", "nameRu": "Ультра всё включено", "nativeIds": ["9"]},
                       ]}})
                return
            if body.get("action") == "price_calendar":
                child_ages = sorted(int(age) for age in body.get("childs", []))
                child_signature = ",".join(str(age) for age in child_ages)
                reply({"ok": True, "data": {"ok": True,
                       "source": "latest-known-exact-segments-from-anytour-first-party-observations",
                       "cachedPriceIsFinal": False, "currency": "RUB", "adults": int(body["adults"]),
                       "childrenCount": len(child_ages), "childAges": child_ages, "childAgesSignature": child_signature,
                       "departureId": int(body["departureId"]), "countryId": int(body["countryId"]),
                       "regionId": int(body["regionId"]) if int(body.get("regionId", 0)) > 0 else None,
                       "dateFrom": body["dateFrom"], "dateTo": body["dateTo"],
                       "nightsFrom": int(body["nightsFrom"]), "nightsTo": int(body["nightsTo"]), "series": []}})
                return
            params = body["params"]
            reply({"ok": True, "data": {"source": "anytour-db-first-results-v1", "scopeVersion": 1,
                   "scope": {"scopeVersion": 1, **params}, "scopeDigest": "c" * 64,
                   "selectionAuthority": False, "hotels": []}})
        elif url.path == "/_preview/search3-anex-candidate/api-andromeda-search3-preview.php" and request.method == "POST":
            body = request.post_data_json
            assert body["generation"] >= 1
            reply({"ok": True, "data": {"provider": "andromeda", "generation": body["generation"], "hotels": []}})
        elif url.path == "/_preview/search3-anex-candidate/api-anex-search3-preview.php" and request.method == "POST":
            body = request.post_data_json
            assert body["action"] == "search" and body["generation"] >= 1
            reply({"ok": True, "data": {"provider": "anex", "generation": body["generation"],
                   "date_range": {"from": body["params"]["dateFrom"], "to": body["params"]["dateTo"]},
                   "search_ref": "d" * 32, "external_search_pending": False,
                   "pages_read": 1, "first_page_only": True, "hotels": []}})
        elif url.path == "/api-v2.php" and request.method == "GET":
            action = query.get("action", [""])[0]
            calls.append(action)
            if action == "countries":
                reply([{"id": 4, "name": "Турция"}])
            elif action == "regions":
                reply([{"id": 20, "name": "Анталья", "countryId": 4}])
            elif action == "meals":
                reply([{"id": 7, "name": "AI"}, {"id": 5, "name": "HB"}])
            elif action == "search_start":
                reply({"searchId": 123})
            elif action == "search_status":
                reply({"progress": 100, "status": "complete"})
            elif action == "search_results":
                reply([{"id": 101, "provider": "tourvisor", "tours": [TOUR, {**TOUR, "id": "other-contact", "price": 140000}]}])
            elif action == "tour":
                assert query.get("tourId") == [TOUR["id"]]
                reply({**TOUR, "hotel": {"name": PROFILE["name"]}})
            elif action == "flights":
                mode = state["flights"]
                if mode == "error":
                    reply({"error": "Временная ошибка проверки тура"}, 503)
                else:
                    reply({} if mode == "malformed" else [] if mode == "empty" else VARIANTS)
            else:
                forbidden.append(request.url)
                route.abort()
        elif request.method == "GET" and request.url.startswith(origin + BASE):
            route.continue_()
        else:
            forbidden.append(request.url)
            route.abort()

    context.route("**/*", intercept)
    try:
        params = urlencode({"origin": "Москва", "country": 4, "from": DATE, "to": DATE,
                            "minNights": 7, "maxNights": 7, "adults": 2, "ages": ""})
        page.goto(origin + BASE + "prototype-search/?" + params)
        page.locator(".search-submit:not([disabled])").wait_for()
        page.locator(".search-submit").click()
        page.wait_for_function("document.querySelectorAll('.hotel-card').length === 1")
        for case in ("error", "malformed", "empty"):
            state["flights"] = case
            page.locator('.hotel-price [data-action="all-offers"]').first.click()
            page.locator('#modal [data-action="offer"][data-key="tourvisor%3Aexact-contact"]').first.click()
            page.locator('[data-action="retry-flights"]:not([disabled])').wait_for()
            before_contact = len(calls)
            page.locator('[data-action="confirm-tour"]').click()
            form = page.locator("#prototype-lead-form")
            submit = page.locator('#modal-footer [type="submit"]')
            alert = form.locator('.lead-message[role="alert"]')
            draft = {"name": f"Тест контактов {case} {width}", "phone": "+7 999 000-00-00",
                     "comment": f"Сохранить пожелания после ошибки: {case}."}
            if case != "empty":
                assert submit.is_disabled(), case
                assert "Не удалось загрузить рейсы" in alert.inner_text()
                visible_alert = alert.evaluate("""element => {
                    const box = element.getBoundingClientRect();
                    const body = document.querySelector('#modal-body').getBoundingClientRect();
                    const footer = document.querySelector('#modal-footer').getBoundingClientRect();
                    return box.top >= body.top - 1 && box.bottom <= Math.min(body.bottom, footer.top) + 1;
                }""")
                assert visible_alert, "The disabled form's reason must be visible without guessing to scroll"
                page.screenshot(path=str(EVIDENCE / f"visible-error-{case}-{width}.png"))
                for key, value in draft.items():
                    form.locator(f'[name="{key}"]').fill(value)
                assert "Не удалось загрузить рейсы" in alert.inner_text(), "Editing erased verification error"
                form.locator('[name="consent"]').check()
                native = form.evaluate("""form => {
                    const observed = {valid: form.checkValidity(), seen: false, prevented: false};
                    form.addEventListener('submit', event => {
                        observed.seen = true; observed.prevented = event.defaultPrevented;
                    }, {once: true});
                    form.requestSubmit(); return observed;
                }""")
                assert native == {"valid": True, "seen": True, "prevented": True}, native
                assert len(calls) == before_contact, "Rejected form made an API request"
                page.screenshot(path=str(EVIDENCE / f"blocked-{case}-{width}.png"))
                page.locator('[data-action="selected-tour-details"]').click()
                state["flights"] = "variants"
                before_retry = len(calls)
                page.locator('[data-action="retry-flights"]').click()
                page.locator('[data-action="choose-flight"]').wait_for()
                assert calls[before_retry:] == ["flights"], calls[before_retry:]
                page.locator('[data-action="choose-flight"]').click()
                page.locator('input[name="flight-pair"][value="1"]').check()
                page.locator('[data-action="apply-flight"]').click()
                assert re.search(r"133\s?500,5", page.locator("#detail-total").inner_text())
                page.locator('[data-action="confirm-tour"]').click()
                for key, value in draft.items():
                    assert form.locator(f'[name="{key}"]').input_value() == value, f"Lost {key} after {case}"
                assert not form.locator('[name="consent"]').is_checked(), "Consent must not be restored"
                summary = page.locator(".verification-tour").inner_text()
                assert "TT 211" in summary and "TT 212" in summary
                assert re.search(r"133\s?500,5", summary), summary
            else:
                assert alert.count() == 0, "A genuine empty flight list is not a transport error"
                for key, value in draft.items():
                    form.locator(f'[name="{key}"]').fill(value)
            assert submit.is_enabled(), case
            before_validation = len(calls)
            form.locator('[name="consent"]').check()
            submit.click()
            page.wait_for_function("document.querySelector('#prototype-lead-form')?.dataset.checked === '1'")
            assert "не отправлена" in form.locator(".lead-message").inner_text()
            assert len(calls) == before_validation, "Preview validation sent an API request"
            assert not forbidden, forbidden
            page.screenshot(path=str(EVIDENCE / f"recovered-{case}-{width}.png"))
            checked.append(case)
            page.locator('[data-action="close-modal"]').click()
            page.wait_for_timeout(100)
        assert calls.count("search_start") == 1, calls
        assert not page.evaluate("document.documentElement.scrollWidth > innerWidth"), "Document overflow"
        assert not errors, errors
        return {"width": width, "cases": checked, "api_actions_are_fixtures": calls,
                "forbidden_requests": forbidden, "browser_errors": errors, "status": "passed"}
    except Exception:
        page.screenshot(path=str(EVIDENCE / f"failure-{width}.png"))
        print(json.dumps({"width": width, "calls": calls, "forbidden": forbidden,
                          "errors": errors, "modal": page.locator("#modal-body").inner_text()[:4000]}, ensure_ascii=False))
        raise
    finally:
        context.close()


def main():
    server = ThreadingHTTPServer(("127.0.0.1", 0), StaticFiles)
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
        source_hashes = {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest()
                         for name in ("prototype-search/index.html", "prototype-search/app.js", "prototype-search/data.js",
                                      "prototype-search/lead.js", "tour-controller-v4.js", "lead-search-context.js")}
        receipt = {"schema_version": 1, "test": "search3-prototype-contact-recovery", "live_inventory": False,
                   "published": False, "supplier_requests": 0, "lead_requests": 0,
                   "source_sha256": source_hashes, "results": results}
        (EVIDENCE / "receipt.json").write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n")
        print("SEARCH3_CONTACT_RECOVERY_BROWSER_OK " + json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

"""Focused browser evidence for the Search3 prototype mobile native controls layer.

This test serves only repository CSS plus a fictional control fixture. It performs no
supplier, database or lead request and does not claim live-preview publication.
"""
import json
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1] / "v2" / "prototype-search"
EVIDENCE = Path("mobile-controls-evidence")
EVIDENCE.mkdir(parents=True, exist_ok=True)

FIXTURE = """<!doctype html><html lang=\"ru\"><head>
<meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">
<link rel=\"stylesheet\" href=\"/styles.css\"><link rel=\"stylesheet\" href=\"/mobile-controls-v1.css\">
</head><body><main style=\"padding:16px\">
<label class=\"sort-label\">Сортировка отелей <select><option>Рекомендуемые</option></select></label>
<div class=\"offers-section\" style=\"margin-top:24px\"><div class=\"offer-list-toolbar\">Сортировка предложений <select><option>Сначала дешевле</option></select></div>
<div class=\"offer-controls\" style=\"margin-top:16px\"><select><option>Номер FAMILY SEA VIEW · AI · 7 ночей</option></select></div></div>
</main></body></html>"""


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/fixture.html":
            body = FIXTURE.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        name = path.lstrip("/")
        if name not in {"styles.css", "mobile-controls-v1.css"}:
            self.send_error(404)
            return
        target = ROOT / name
        body = target.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", "text/css; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *_):
        pass


def inspect_width(browser, origin, width, screenshot=False):
    page = browser.new_page(viewport={"width": width, "height": 520})
    errors = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    try:
        page.goto(origin + "/fixture.html", wait_until="load")
        mobile = page.evaluate("matchMedia('(max-width:760px)').matches")
        values = page.evaluate("""() => {
          const style = selector => {
            const value = getComputedStyle(document.querySelector(selector));
            return {fontSize:value.fontSize, minHeight:value.minHeight, height:value.height};
          };
          return {
            hotelSort: style('.sort-label select'),
            offerSort: style('.offer-list-toolbar select'),
            offerCondition: style('.offer-controls select'),
            toolbarFont: getComputedStyle(document.querySelector('.offer-list-toolbar')).fontSize,
            overflow: document.documentElement.scrollWidth > innerWidth
          };
        }""")
        if width <= 760:
            assert mobile is True, width
            for key in ("hotelSort", "offerSort", "offerCondition"):
                assert values[key]["fontSize"] == "16px", (width, key, values[key])
                assert float(values[key]["height"].removesuffix("px")) >= 44, (width, key, values[key])
            assert values["toolbarFont"] == "16px", (width, values)
        else:
            assert mobile is False, width
        assert values["overflow"] is False, (width, values)
        assert not errors, errors
        if screenshot:
            page.screenshot(path=str(EVIDENCE / f"controls-{width}.png"), full_page=True)
        return {"width": width, "mobileMedia": mobile, **values, "browserErrors": errors}
    finally:
        page.close()


def main():
    server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f"http://127.0.0.1:{server.server_port}"
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try:
                results = [inspect_width(browser, origin, width, width in (390, 1440))
                           for width in (390, 760, 761, 1440)]
            finally:
                browser.close()
        receipt = {
            "schema_version": 1,
            "test": "search3-prototype-mobile-controls-browser",
            "live_preview": False,
            "supplier_requests": 0,
            "db_requests": 0,
            "lead_requests": 0,
            "results": results,
            "status": "passed",
        }
        (EVIDENCE / "receipt.json").write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print("SEARCH3_MOBILE_CONTROLS_BROWSER_OK " + json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

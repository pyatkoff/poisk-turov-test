"""Browser regression for the real Search3 prototype mobile header geometry.

The fixture reuses the repository styles and real header DOM shape. It performs no
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
<meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1,viewport-fit=cover\">
<link rel=\"stylesheet\" href=\"/styles.css\"><link rel=\"stylesheet\" href=\"/mobile-controls-v1.css\">
</head><body>
<header class=\"header\"><div class=\"wrap header-inner\">
  <a class=\"brand\" href=\"#\" aria-label=\"AnyTour — начало\"><img class=\"brand-logo\" src=\"/logo.svg\" alt=\"AnyTour\" width=\"224\" height=\"61\"></a>
  <nav aria-label=\"Основная навигация\"><a class=\"nav-current\" href=\"#\">Подбор тура</a>
    <button class=\"nav-btn\" aria-label=\"Избранное: 2\"><svg class=\"icon\" viewBox=\"0 0 24 24\"></svg><span class=\"nav-label\">Избранное</span><span class=\"saved-dot\">2</span></button>
    <button class=\"nav-btn\" aria-label=\"Сравнение: 2\"><svg class=\"icon\" viewBox=\"0 0 24 24\"></svg><span class=\"nav-label\">Сравнение</span><span class=\"saved-dot\">2</span></button>
    <button class=\"nav-btn\" aria-label=\"Мой тур: 1\"><svg class=\"icon\" viewBox=\"0 0 24 24\"></svg><span class=\"nav-label\">Мой тур</span><span class=\"saved-dot\">1</span></button>
  </nav>
  <span class=\"private-badge\"><span class=\"status-dot\"></span> Версия для проверки</span>
</div></header>
</body></html>"""

LOGO = b"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 224 61'><rect width='224' height='61' fill='#2743cb'/></svg>"


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/fixture.html":
            body = FIXTURE.encode("utf-8")
            content_type = "text/html; charset=utf-8"
        elif path == "/logo.svg":
            body = LOGO
            content_type = "image/svg+xml"
        elif path in {"/styles.css", "/mobile-controls-v1.css"}:
            body = (ROOT / path.lstrip("/")).read_bytes()
            content_type = "text/css; charset=utf-8"
        else:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *_):
        pass


def inspect(browser, origin, width):
    page = browser.new_page(viewport={"width": width, "height": 260})
    errors = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    try:
        page.goto(origin + "/fixture.html", wait_until="load")
        values = page.evaluate("""() => {
          const rect = selector => {
            const r = document.querySelector(selector).getBoundingClientRect();
            return {width:r.width,height:r.height,left:r.left,right:r.right};
          };
          return {
            innerWidth,
            scrollWidth: document.documentElement.scrollWidth,
            logo: rect('.header .brand-logo'),
            header: rect('.header-inner'),
            nav: rect('.header nav'),
            buttons: [...document.querySelectorAll('.header .nav-btn')].map(node => {
              const r=node.getBoundingClientRect();
              const style=getComputedStyle(node);
              return {width:r.width,height:r.height,minWidth:style.minWidth};
            }),
            navGap: getComputedStyle(document.querySelector('.header nav')).gap,
            headerGap: getComputedStyle(document.querySelector('.header-inner')).gap,
            mobile: matchMedia('(max-width:760px)').matches
          };
        }""")
        assert values["scrollWidth"] <= width, (width, values)
        assert not errors, errors
        if width <= 760:
            assert values["mobile"] is True, (width, values)
            assert len(values["buttons"]) == 3, values
            for button in values["buttons"]:
                assert button["width"] >= 44, (width, button, values)
                assert button["height"] >= 44, (width, button, values)
                assert button["minWidth"] == "44px", (width, button)
            if width == 320:
                assert values["logo"]["width"] <= 136.1, values
            if width == 390:
                assert 147.9 <= values["logo"]["width"] <= 148.1, values
        else:
            assert values["mobile"] is False, (width, values)
            assert all(button["minWidth"] != "44px" for button in values["buttons"]), values
        if width in (320, 390, 1440):
            page.screenshot(path=str(EVIDENCE / f"header-{width}.png"), full_page=True)
        return {"width": width, **values, "browserErrors": errors}
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
                results = [inspect(browser, origin, width) for width in (320, 390, 760, 761, 1440)]
            finally:
                browser.close()
        receipt = {
            "schema_version": 1,
            "test": "search3-prototype-mobile-header-browser",
            "live_preview": False,
            "supplier_requests": 0,
            "db_requests": 0,
            "lead_requests": 0,
            "results": results,
            "status": "passed",
        }
        (EVIDENCE / "header-receipt.json").write_text(
            json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        print("SEARCH3_MOBILE_HEADER_BROWSER_OK " + json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

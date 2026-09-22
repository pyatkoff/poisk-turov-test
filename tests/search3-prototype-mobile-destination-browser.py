"""Browser regression for compact mobile country selection in Search3.

The fixture uses the real prototype destination class names and repository CSS. It
performs no supplier, database or lead request and does not claim live publication.
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

COUNTRIES = [
    "Турция", "Египет", "ОАЭ", "Таиланд", "Вьетнам", "Куба", "Мальдивы",
    "Шри-Ланка", "Индонезия", "Китай", "Индия", "Тунис", "Кипр", "Греция",
    "Испания", "Италия", "Черногория", "Грузия", "Армения", "Азербайджан",
]
BUTTONS = "".join(
    f'<button aria-pressed="{str(name == "Турция").lower()}">{name}</button>'
    for name in COUNTRIES
)
FIXTURE = f'''<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/mobile-controls-v1.css">
</head><body><dialog id="modal" open><div id="modal-body">
<div class="destination-search"><input class="input" type="search" placeholder="Страна, курорт или отель"></div>
<div class="destination-selection"><span>Турция</span><button class="text-button">Все курорты</button></div>
<section class="destination-section countries-section"><h3>Направления</h3><div class="destination-countries">{BUTTONS}</div></section>
<section class="destination-section resorts-section"><h3>Курорты · можно выбрать несколько</h3>
<button class="destination-row" aria-pressed="false"><span class="choice-check"></span><span><strong>Бодрум</strong><small>Турция · 8 отелей</small></span></button>
<button class="destination-row" aria-pressed="false"><span class="choice-check"></span><span><strong>Анталья</strong><small>Турция · 14 отелей</small></span></button>
</section></div><footer id="modal-footer"><button class="primary picker-apply">Применить</button></footer></dialog></body></html>'''


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/fixture.html":
            body, content_type = FIXTURE.encode("utf-8"), "text/html; charset=utf-8"
        elif path in {"/styles.css", "/mobile-controls-v1.css"}:
            body, content_type = (ROOT / path.lstrip("/")).read_bytes(), "text/css; charset=utf-8"
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
    page = browser.new_page(viewport={"width": width, "height": 720})
    errors = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    try:
        page.goto(origin + "/fixture.html", wait_until="load")
        values = page.evaluate("""() => {
          const strip = document.querySelector('.destination-countries');
          const resort = document.querySelector('.resorts-section');
          const buttons = [...strip.querySelectorAll('button')];
          const r = strip.getBoundingClientRect();
          return {
            innerWidth,
            bodyScrollWidth: document.documentElement.scrollWidth,
            mobile: matchMedia('(max-width:760px)').matches,
            flexWrap: getComputedStyle(strip).flexWrap,
            overflowX: getComputedStyle(strip).overflowX,
            stripHeight: r.height,
            stripTop: r.top,
            stripClientWidth: strip.clientWidth,
            stripScrollWidth: strip.scrollWidth,
            resortTop: resort.getBoundingClientRect().top,
            buttonCount: buttons.length,
            buttonHeights: buttons.map(b => b.getBoundingClientRect().height),
            buttonTexts: buttons.map(b => b.textContent.trim())
          };
        }""")
        assert not errors, errors
        assert values["buttonCount"] == len(COUNTRIES), values
        assert values["buttonTexts"] == COUNTRIES, values
        assert values["bodyScrollWidth"] <= width, (width, values)
        assert all(height >= 44 for height in values["buttonHeights"]), (width, values)
        if width <= 760:
            assert values["mobile"] is True
            assert values["flexWrap"] == "nowrap", (width, values)
            assert values["overflowX"] in {"auto", "scroll"}, (width, values)
            assert values["stripScrollWidth"] > values["stripClientWidth"] + 100, (width, values)
            assert values["stripHeight"] < 70, (width, values)
            assert values["resortTop"] - values["stripTop"] < 100, (width, values)
            page.locator('.destination-countries button').last.focus()
            page.wait_for_timeout(50)
            scroll_left = page.eval_on_selector('.destination-countries', 'el => el.scrollLeft')
            assert scroll_left > 0, (width, scroll_left, values)
        else:
            assert values["mobile"] is False
            assert values["flexWrap"] == "wrap", (width, values)
            assert values["stripScrollWidth"] <= values["stripClientWidth"] + 2, (width, values)
            assert values["stripHeight"] > 70, (width, values)
        if width in (320, 390, 761, 1440):
            page.screenshot(path=str(EVIDENCE / f"destination-{width}.png"), full_page=True)
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
            "test": "search3-prototype-mobile-destination-browser",
            "live_preview": False,
            "supplier_requests": 0,
            "db_requests": 0,
            "lead_requests": 0,
            "results": results,
            "status": "passed",
        }
        (EVIDENCE / "destination-receipt.json").write_text(
            json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        print("SEARCH3_MOBILE_DESTINATION_BROWSER_OK " + json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

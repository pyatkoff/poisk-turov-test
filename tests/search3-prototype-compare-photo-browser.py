"""Run the actual comparison toggle with fictional controls and repository CSS.

Only toggleCompare is extracted from app.js. Persistence/navigation/toast helpers
are explicit test doubles; this is not a full application or live-preview test.
The server permits only the fixture and two CSS assets. No supplier/DB/lead calls.
"""
import argparse
import json
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1] / "v2" / "prototype-search"
EVIDENCE = Path("mobile-controls-evidence")
APP = (ROOT / "app.js").read_text(encoding="utf-8")
START = APP.index("function toggleCompare(")
END = APP.index("function openCompare(", START)
TOGGLE = APP[START:END]
assert TOGGLE.count("function toggleCompare(") == 1

FIXTURE = """<!doctype html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/mobile-controls-v1.css">
</head><body><main style="max-width:640px;margin:24px auto;padding:16px">
<h1 style="font-size:20px">Проверочные кнопки сравнения</h1>
<div class="hotel-image-wrap" style="position:relative;min-height:210px;background:#e4ebf3;margin:18px 0">
<button id="photo" class="compare-photo-button" data-action="toggle-compare" data-id="123"
aria-pressed="false" aria-label="Сравнить: Проверочный отель" title="Сравнить отель"></button>
<button id="favorite" class="favorite-button" aria-label="В избранное: Проверочный отель">♥</button>
</div><div class="favorite-actions"><button id="saved" class="compare-btn" data-action="toggle-compare"
data-id="123" aria-pressed="false">Сравнить отель</button></div>
</main><script>
const state={compare:[]},modalType='',observed={writes:0,favorites:0};
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
function saveStored(){observed.writes++}function updateNav(){}function toast(){}
function refreshSavedView(){throw Error('Unexpected saved-view rendering in control fixture')}
function icon(name){return `<svg data-symbol="${name}" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="${name==='check'?'M5 12l4 4 10-10':'M5 5v14M12 8v11M19 3v16'}"/></svg>`}
$('#photo').innerHTML=icon('compare');
__TOGGLE__
document.addEventListener('click',event=>{const b=event.target.closest('button');
if(b?.dataset.action==='toggle-compare')toggleCompare(Number(b.dataset.id));
else if(b?.id==='favorite')observed.favorites++});
</script></body></html>""".replace("__TOGGLE__", TOGGLE)


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/fixture.html":
            body, mime = FIXTURE.encode(), "text/html; charset=utf-8"
        elif path in ("/styles.css", "/mobile-controls-v1.css"):
            body, mime = (ROOT / path[1:]).read_bytes(), "text/css; charset=utf-8"
        else:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header("Content-Type", mime)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *_):
        pass


MEASURE = """() => {
 const b=document.querySelector('#photo'),r=b.getBoundingClientRect(),s=b.querySelector('svg').getBoundingClientRect();
 const f=document.querySelector('#favorite').getBoundingClientRect(),saved=document.querySelector('#saved');
 const width=e=>{const range=document.createRange();range.selectNodeContents(e);return range.getBoundingClientRect().width};
 const textWidth=[...b.childNodes].filter(n=>n.nodeType===Node.TEXT_NODE).reduce((sum,n)=>sum+width(n),0);
 return {button:{x:r.x,y:r.y,width:r.width,height:r.height},icon:{x:s.x,y:s.y,width:s.width,height:s.height},
 textWidth,pressed:b.getAttribute('aria-pressed'),name:b.getAttribute('aria-label'),
 symbol:b.querySelector('svg').dataset.symbol,savedText:saved.textContent,savedFont:getComputedStyle(saved).fontSize,
 savedTextWidth:width(saved),favoriteGap:f.left-r.right,overflow:document.documentElement.scrollWidth>innerWidth,
 writes:observed.writes,favorites:observed.favorites,selected:[...state.compare]};
}"""


def check_geometry(values, width):
    b, icon = values["button"], values["icon"]
    assert b["width"] >= (44 if width <= 760 else 40), values
    assert b["height"] >= (44 if width <= 760 else 40), values
    assert icon["width"] >= 18 and icon["height"] >= 18, values
    assert icon["x"] >= b["x"] and icon["x"] + icon["width"] <= b["x"] + b["width"], values
    assert icon["y"] >= b["y"] and icon["y"] + icon["height"] <= b["y"] + b["height"], values
    assert values["textWidth"] <= 0.5, values
    assert values["favoriteGap"] >= 0, values
    assert not values["overflow"], values
    assert values["name"] == "Сравнить: Проверочный отель", values
    assert float(values["savedFont"].removesuffix("px")) >= 12, values
    assert values["savedTextWidth"] > 40, values


def inspect(browser, origin, width, engine):
    page = browser.new_page(viewport={"width": width, "height": 820})
    errors, external = [], []
    page.on("pageerror", lambda error: errors.append(str(error)))

    def route(request):
        if request.request.url.startswith(origin + "/"):
            request.continue_()
        else:
            external.append(request.request.url)
            request.abort()

    page.route("**/*", route)
    try:
        page.goto(origin + "/fixture.html", wait_until="load")
        assert page.get_by_role("button", name="Сравнить: Проверочный отель", exact=True).count() == 1
        initial = page.evaluate(MEASURE)
        check_geometry(initial, width)
        assert initial["pressed"] == "false" and initial["writes"] == 0
        page.locator("#photo").click()
        selected = page.evaluate(MEASURE)
        check_geometry(selected, width)
        assert selected["pressed"] == "true" and selected["symbol"] == "check"
        assert selected["selected"] == [123] and selected["writes"] == 1
        assert selected["savedText"] == "В сравнении"
        page.locator("#favorite").click()
        assert page.evaluate("observed.favorites") == 1
        assert page.evaluate("state.compare") == [123]
        page.locator("#photo").focus()
        page.keyboard.press("Space")
        cleared = page.evaluate(MEASURE)
        check_geometry(cleared, width)
        assert cleared["pressed"] == "false" and cleared["symbol"] == "compare"
        assert cleared["selected"] == [] and cleared["writes"] == 2
        assert cleared["savedText"] == "Сравнить отель"
        if width in (390, 1440):
            page.screenshot(path=str(EVIDENCE / f"compare-photo-{engine}-{width}.png"), full_page=True)
        # Mutation test: remove only this repair, keeping the entire old CSS and
        # the same actual handler. The known anonymous-label defect must return.
        page.evaluate("""() => {
 const sheet=[...document.styleSheets].find(s=>s.href?.endsWith('/mobile-controls-v1.css'));
 let removed=0;for(let i=sheet.cssRules.length-1;i>=0;i--){
 if(['.compare-photo-button','.compare-photo-button svg'].includes(sheet.cssRules[i].selectorText)){sheet.deleteRule(i);removed++}}
 if(removed!==2)throw Error('Repair rules not isolated');
}""")
        page.locator("#photo").click()
        baseline = page.evaluate(MEASURE)
        assert baseline["textWidth"] > baseline["button"]["width"], baseline
        assert not errors and not external, (errors, external)
        return {"width": width, "initial": initial, "selected": selected, "cleared": cleared,
                "baselineTextWidth": baseline["textWidth"], "baselineRejected": True,
                "pageErrors": errors, "externalRequests": len(external)}
    finally:
        page.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--browser", choices=("chromium", "webkit"), default="chromium")
    parser.add_argument("--executable-path", help="Existing local Chromium binary; CI uses the pinned bundled browser")
    args = parser.parse_args()
    assert not args.executable_path or args.browser == "chromium"
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        with sync_playwright() as playwright:
            browser = getattr(playwright, args.browser).launch(headless=True, **({"executable_path": args.executable_path} if args.executable_path else {}))
            try:
                results = [inspect(browser, f"http://127.0.0.1:{server.server_port}", width, args.browser)
                           for width in (320, 390, 760, 761, 1440)]
            finally:
                browser.close()
        receipt = {"test": "search3-prototype-compare-photo", "browser": args.browser,
                   "status": "passed", "live_preview": False, "full_application": False,
                   "actual_toggle_handler": True, "supplier_requests": 0, "db_requests": 0,
                   "lead_requests": 0, "results": results}
        (EVIDENCE / f"compare-photo-{args.browser}.json").write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n")
        print("SEARCH3_COMPARE_PHOTO_BROWSER_OK " + json.dumps(receipt, ensure_ascii=False))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


if __name__ == "__main__":
    main()

"""Focused browser evidence for the Search3 prototype mobile controls layer.

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
<div class=\"offers-section\" style=\"margin-top:18px\"><div class=\"offer-list-toolbar\">Сортировка предложений <select><option>Сначала дешевле</option></select></div>
<div class=\"offer-controls\" style=\"margin-top:12px\"><select><option>Номер FAMILY SEA VIEW · AI · 7 ночей</option></select></div></div>
<div class=\"search-secondary\" style=\"margin-top:18px\"><label class=\"secondary-label-probe\">Питание <select class=\"secondary-select-probe\"><option>Всё включено</option></select></label><button class=\"text-button more-filters secondary-more-probe\">Ещё фильтры</button></div>
<div class=\"calendar-card\" style=\"margin-top:18px\"><div class=\"calendar-heading\"><button class=\"text-button calendar-choose-probe\">Выбрать даты</button></div><div class=\"calendar-foot\"><button class=\"text-button calendar-clear-probe\">Все даты</button></div></div>
<div class=\"destination-selection\" style=\"margin-top:18px\"><span>Турция · Анталья</span><button class=\"text-button\">Изменить</button></div>
<div class=\"flex-dates\" style=\"margin-top:18px\"><button>7 ночей</button><button>10 ночей</button></div>
<div class=\"results-toolbar\" style=\"margin-top:18px\"><button class=\"secondary drawer-trigger\">Фильтры</button><div class=\"quick-chips\"><button class=\"chip\">Первая линия</button><button class=\"chip\">Для семьи</button></div></div>
<div class=\"active-filters\"><button class=\"active-filter\">5 ★</button></div>
<aside class=\"filter-panel open\" style=\"position:static;display:block;width:100%;max-height:none;margin-top:18px\">
  <div class=\"filter-top\"><h3>Фильтры</h3><button class=\"text-button filter-reset-probe\">Сбросить</button><button class=\"icon-button mobile-close\" aria-label=\"Закрыть фильтры\">×</button></div>
  <div class=\"filter-group\"><h4>Тип отдыха</h4><label class=\"check-row filter-check-probe\"><input type=\"checkbox\"><span>Первая линия</span><small>12</small></label></div>
  <div class=\"filter-group\"><h4>Категория отеля</h4><div class=\"star-options\"><button class=\"filter-star-probe\">5 ★</button><button>4 ★</button></div></div>
</aside>
<div class=\"applied-search\" style=\"display:flex;margin-top:18px\"><button class=\"secondary\" aria-label=\"Изменить поиск\">✎</button></div>
<div class=\"compact-search\" style=\"position:static;display:flex;margin-top:18px\"><button class=\"secondary\" aria-label=\"Изменить поиск в закреплённой панели\">✎</button></div>
<div class=\"hotel-links\" style=\"margin-top:18px\"><button class=\"text-button\">Об отеле</button><button class=\"compare-btn\">Сравнить</button></div>
<div style=\"display:flex;gap:8px;align-items:center;margin-top:18px\"><button class=\"favorite-button\" style=\"position:static\" aria-label=\"В избранное\">♥</button><button class=\"card-photo-arrow next\" style=\"position:static;margin:0\" aria-label=\"Следующее фото\">›</button></div>
<div class=\"hotel-more\" style=\"margin-top:18px\"><button class=\"text-button\">Показать все туры</button></div>
<div class=\"hotel-price price-card-probe\" style=\"margin-top:18px\">
  <div class=\"starting-price\"><span>Цена за всех туристов</span><strong>15 000 000 ₽</strong></div>
  <div class=\"fuel-note\">Актуальность цены проверяется при выборе</div>
  <button class=\"primary price-card-cta-probe\">Смотреть туры</button>
  <small class=\"hotel-offer-count\">12 предложений</small>
</div>
<div class=\"offer-price\" style=\"text-align:left;margin-top:18px\"><button class=\"primary\">Выбрать тур</button></div>
<div class=\"compare-tray\" style=\"position:static;transform:none;margin-top:18px\"><button class=\"primary\">Сравнить</button><button class=\"icon-button\" aria-label=\"Закрыть сравнение\">×</button></div>
<div class=\"compare-hotel-card\" style=\"position:relative;min-height:54px;margin-top:18px\"><button class=\"icon-button compare-remove\" aria-label=\"Убрать отель из сравнения\">×</button></div>
<div class=\"modal-header\" style=\"display:flex;margin-top:18px\"><button id=\"modal-back\" class=\"icon-button\" aria-label=\"Назад\">←</button><button class=\"icon-button modal-close-probe\" aria-label=\"Закрыть\">×</button></div>
<div class=\"counter\" style=\"margin-top:18px\"><button aria-label=\"Увеличить количество туристов\">+</button></div>
<div class=\"favorite-item\" style=\"margin-top:18px\"><button class=\"icon-button\" aria-label=\"Удалить из избранного\">×</button></div>
<div class=\"hotel-image-wrap photo-stage-probe\" style=\"width:100%;margin-top:18px;background:#e4ebf3\"></div>
<div class=\"hotel-detail-photos detail-photos-probe\" style=\"margin:18px 0 0\"><button></button><button></button><button></button></div>
</main>
<dialog id="tour-dialog-probe" open>
  <div class="modal-header"><div><span class="eyebrow">ПРОВЕРЬТЕ УСЛОВИЯ</span><h2>Ваш тур в деталях</h2></div></div>
  <div id="modal-body">
    <div class="tour-hero tour-probe-hero">
      <img class="tour-probe-image" alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
      <div><div class="hotel-stars">★★★★★</div><h3>Очень длинное название отеля для проверки мобильной геометрии</h3><p>Анталья, Турция</p></div>
    </div>
    <dl class="detail-grid tour-summary"><div><dt>Вылет — возвращение</dt><dd>29 сентября — 6 октября</dd></div><div><dt>Продолжительность</dt><dd>7 ночей</dd></div><div><dt>Туристы</dt><dd>2 взр.</dd></div><div><dt>Питание</dt><dd>Всё включено</dd></div></dl>
    <div class="tour-layout"><div class="tour-main-details"><section class="tour-section"><h3>Проживание и питание</h3><p class="tour-missing">Условия уточняются для конкретного предложения.</p></section></div><aside class="tour-price-details"><div class="price-breakdown"><h3>Стоимость тура</h3><div class="price-line total"><span>Цена предложения</span><strong>15 000 000 ₽</strong></div></div></aside></div>
  </div>
  <div id="modal-footer"><div class="footer-total"><span>За всех туристов</span><strong>15 000 000 ₽</strong></div><button class="primary">Выбрать этот тур →</button></div>
</dialog></body></html>"""


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
    page = browser.new_page(viewport={"width": width, "height": 1180})
    errors = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    try:
        page.goto(origin + "/fixture.html", wait_until="load")
        mobile = page.evaluate("matchMedia('(max-width:760px)').matches")
        values = page.evaluate("""() => {
          const style = selector => {
            const value = getComputedStyle(document.querySelector(selector));
            return {
              fontSize:value.fontSize,
              minWidth:value.minWidth,
              minHeight:value.minHeight,
              width:value.width,
              height:value.height,
              aspectRatio:value.aspectRatio,
              gridTemplateRows:value.gridTemplateRows,
              gridTemplateColumns:value.gridTemplateColumns,
              display:value.display,
              whiteSpace:value.whiteSpace
            };
          };
          const measure = selector => {
            const node = document.querySelector(selector), rect = node.getBoundingClientRect();
            return {
              left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,width:rect.width,height:rect.height,
              clientWidth:node.clientWidth,scrollWidth:node.scrollWidth,clientHeight:node.clientHeight,scrollHeight:node.scrollHeight
            };
          };
          return {
            hotelSort: style('.sort-label select'),
            offerSort: style('.offer-list-toolbar select'),
            offerCondition: style('.offer-controls select'),
            toolbarFont: getComputedStyle(document.querySelector('.offer-list-toolbar')).fontSize,
            secondaryLabel: style('.secondary-label-probe'),
            secondarySelect: style('.secondary-select-probe'),
            secondaryMore: style('.secondary-more-probe'),
            calendarChoose: style('.calendar-choose-probe'),
            calendarClear: style('.calendar-clear-probe'),
            destinationAction: style('.destination-selection button'),
            dateLengthShortcut: style('.flex-dates button'),
            drawer: style('.drawer-trigger'),
            preset: style('.quick-chips .chip'),
            activeFilter: style('.active-filter'),
            drawerClose: style('.filter-top .mobile-close'),
            filterReset: style('.filter-reset-probe'),
            filterCheck: style('.filter-check-probe'),
            filterStar: style('.filter-star-probe'),
            appliedEdit: style('.applied-search > .secondary'),
            compactEdit: style('.compact-search .secondary'),
            hotelLink: style('.hotel-links .text-button'),
            compare: style('.compare-btn'),
            favorite: style('.favorite-button'),
            photoArrow: style('.card-photo-arrow'),
            expandOffers: style('.hotel-more .text-button'),
            resultPriceCard: style('.price-card-probe'),
            resultPriceValue: style('.price-card-probe .starting-price > strong'),
            resultPriceCta: style('.price-card-cta-probe'),
            resultPriceCardBox: measure('.price-card-probe'),
            resultPriceValueBox: measure('.price-card-probe .starting-price > strong'),
            resultPriceCtaBox: measure('.price-card-cta-probe'),
            resultPriceCardOverflow: document.querySelector('.price-card-probe').scrollWidth > document.querySelector('.price-card-probe').clientWidth,
            offerCta: style('.offer-price .primary'),
            compareCta: style('.compare-tray > .primary'),
            compareClose: style('.compare-tray .icon-button'),
            compareRemove: style('.compare-hotel-card .compare-remove'),
            modalBack: style('#modal-back'),
            modalClose: style('.modal-close-probe'),
            counter: style('.counter button'),
            favoriteRemove: style('.favorite-item .icon-button'),
            photoStage: style('.photo-stage-probe'),
            detailPhotos: style('.detail-photos-probe'),
            tourDialog: style('#tour-dialog-probe'),
            tourHero: style('.tour-probe-hero'),
            tourHeroImage: style('.tour-probe-image'),
            tourFooter: style('#tour-dialog-probe #modal-footer'),
            tourCta: style('#tour-dialog-probe #modal-footer .primary'),
            tourDialogOverflow: document.querySelector('#tour-dialog-probe').scrollWidth > document.querySelector('#tour-dialog-probe').clientWidth,
            tourFooterOverflow: document.querySelector('#tour-dialog-probe #modal-footer').scrollWidth > document.querySelector('#tour-dialog-probe #modal-footer').clientWidth,
            overflow: document.documentElement.scrollWidth > innerWidth
          };
        }""")
        if width <= 760:
            assert mobile is True, width
            for key in ("hotelSort", "offerSort", "offerCondition"):
                assert values[key]["fontSize"] == "16px", (width, key, values[key])
                assert float(values[key]["height"].removesuffix("px")) >= 44, (width, key, values[key])
            assert values["toolbarFont"] == "16px", (width, values)
            assert values["secondarySelect"]["fontSize"] == "16px", (width, values["secondarySelect"])
            for key in ("secondaryLabel", "secondarySelect", "secondaryMore"):
                assert float(values[key]["height"].removesuffix("px")) >= 44, (width, key, values[key])
            for key in (
                "calendarChoose", "calendarClear", "destinationAction", "dateLengthShortcut",
                "drawer", "preset", "activeFilter", "filterReset", "filterCheck", "filterStar",
                "hotelLink", "compare", "expandOffers", "offerCta", "compareCta"
            ):
                assert float(values[key]["height"].removesuffix("px")) >= 44, (width, key, values[key])
            for key in (
                "drawerClose", "appliedEdit", "compactEdit", "favorite", "photoArrow",
                "compareClose", "compareRemove", "modalBack", "modalClose", "counter", "favoriteRemove"
            ):
                assert float(values[key]["width"].removesuffix("px")) >= 44, (width, key, values[key])
                assert float(values[key]["height"].removesuffix("px")) >= 44, (width, key, values[key])
            assert values["photoStage"]["aspectRatio"] == "3 / 2", (width, values["photoStage"])
            photo_width = float(values["photoStage"]["width"].removesuffix("px"))
            photo_height = float(values["photoStage"]["height"].removesuffix("px"))
            assert 1.49 <= photo_width / photo_height <= 1.51, (width, values["photoStage"])
            assert values["detailPhotos"]["gridTemplateRows"] == "108px 108px", (width, values["detailPhotos"])
            assert values["tourHero"]["display"] == "grid", (width, values["tourHero"])
            assert values["tourHero"]["gridTemplateColumns"].startswith("76px "), (width, values["tourHero"])
            assert values["tourHeroImage"]["width"] == "76px", (width, values["tourHeroImage"])
            assert values["tourHeroImage"]["height"] == "78px", (width, values["tourHeroImage"])
            assert float(values["tourDialog"]["width"].removesuffix("px")) >= width - 2, (width, values["tourDialog"])
            assert values["tourDialogOverflow"] is False, (width, values)
            assert values["tourFooterOverflow"] is False, (width, values)
            assert values["resultPriceCardOverflow"] is False, (width, values)
            card_box, price_box, cta_box = values["resultPriceCardBox"], values["resultPriceValueBox"], values["resultPriceCtaBox"]
            assert price_box["width"] > 0 and price_box["left"] >= card_box["left"] - 1 and price_box["right"] <= card_box["right"] + 1, (width, values)
            assert cta_box["width"] > 0 and cta_box["left"] >= card_box["left"] - 1 and cta_box["right"] <= card_box["right"] + 1, (width, values)
            assert values["resultPriceValue"]["display"] != "none", (width, values["resultPriceValue"])
            if width <= 374:
                assert len(values["resultPriceCard"]["gridTemplateColumns"].split()) == 1, (width, values["resultPriceCard"])
                assert float(values["resultPriceCta"]["width"].removesuffix("px")) >= card_box["width"] - 34, (width, values["resultPriceCta"])
                assert values["tourFooter"]["display"] == "grid", (width, values["tourFooter"])
                assert float(values["tourCta"]["width"].removesuffix("px")) >= width - 60, (width, values["tourCta"])
        else:
            assert mobile is False, width
            assert values["drawer"]["minHeight"] != "44px", (width, values["drawer"])
            assert values["preset"]["minHeight"] != "44px", (width, values["preset"])
            assert values["activeFilter"]["minHeight"] != "44px", (width, values["activeFilter"])
            assert values["secondarySelect"]["fontSize"] != "16px", (width, values["secondarySelect"])
            for key in ("secondaryLabel", "secondarySelect", "secondaryMore"):
                assert values[key]["minHeight"] != "44px", (width, key, values[key])
            for key in ("calendarChoose", "calendarClear", "destinationAction", "dateLengthShortcut"):
                assert values[key]["minHeight"] != "44px", (width, key, values[key])
            for key in ("favorite", "photoArrow", "compareClose", "compareRemove", "modalBack", "counter", "favoriteRemove"):
                assert values[key]["width"] != "44px", (width, key, values[key])
            assert values["photoStage"]["aspectRatio"] != "3 / 2", (width, values["photoStage"])
            assert values["detailPhotos"]["gridTemplateRows"] != "108px 108px", (width, values["detailPhotos"])
            assert values["tourHero"]["display"] == "flex", (width, values["tourHero"])
            tour_width = float(values["tourDialog"]["width"].removesuffix("px"))
            assert tour_width >= (700 if width == 761 else 900), (width, values["tourDialog"])
            assert values["tourDialogOverflow"] is False, (width, values)
            assert values["tourFooterOverflow"] is False, (width, values)
            assert values["resultPriceCardOverflow"] is False, (width, values)
            card_box, price_box, cta_box = values["resultPriceCardBox"], values["resultPriceValueBox"], values["resultPriceCtaBox"]
            assert price_box["left"] >= card_box["left"] - 1 and price_box["right"] <= card_box["right"] + 1, (width, values)
            assert cta_box["left"] >= card_box["left"] - 1 and cta_box["right"] <= card_box["right"] + 1, (width, values)
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
                           for width in (320, 390, 760, 761, 1440)]
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

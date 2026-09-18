#!/usr/bin/env python3
"""Read-only live browser acceptance for the owner's exact 4-star Search3 query.

Supplier endpoints are blocked in Chromium. Only the published local-candidate page,
static assets, canonical/profile reads and the DB-first LOCAL endpoint are allowed.
No lead submission, DB write or supplier search is performed.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import Route, sync_playwright

URL = (
    "https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/"
    "?from=1&country=4&dateFrom=2026-09-19&dateTo=2026-09-22"
    "&daysFrom=7&daysTill=10&count_people=2&child_count=0&stars=4"
)
OUT = Path(os.environ.get("EVIDENCE_DIR", "local-anex-browser-evidence"))
OUT.mkdir(parents=True, exist_ok=True)

SUPPLIER_PATH_PARTS = (
    "/api-v2.php",
    "api-anex-search3-preview.php",
    "api-andromeda-search3-preview.php",
)


def route_request(route: Route) -> None:
    parsed = urlparse(route.request.url)
    if parsed.hostname != "anytoour.ru":
        route.abort()
        return
    if any(token in parsed.path for token in SUPPLIER_PATH_PARTS):
        route.abort()
        return
    route.continue_()


def run_viewport(browser, width: int, height: int) -> dict:
    context = browser.new_context(
        viewport={"width": width, "height": height},
        locale="ru-RU",
        timezone_id="Europe/Amsterdam",
    )
    context.route("**/*", route_request)
    context.add_init_script(
        """
        window.__localDbEvents = [];
        window.__providerEvents = [];
        window.addEventListener('v2:provider-status', event => {
          const detail = event && event.detail ? JSON.parse(JSON.stringify(event.detail)) : {};
          window.__providerEvents.push(detail);
          if (detail.provider === 'local-db') window.__localDbEvents.push(detail);
        });
        """
    )
    page = context.new_page()
    errors: list[str] = []
    console_errors: list[str] = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    page.on(
        "console",
        lambda message: console_errors.append(message.text)
        if message.type == "error"
        else None,
    )
    response = page.goto(URL, wait_until="domcontentloaded", timeout=90_000)
    assert response is not None and response.status == 200, response.status if response else None
    page.wait_for_function(
        "window.__localDbEvents.some(event => event.status === 'complete')",
        timeout=90_000,
    )
    page.wait_for_function(
        """
        () => {
          const items = window.V2Results?.state?.items;
          if (!Array.isArray(items)) return false;
          return items.filter(item => Array.isArray(item?.tours) && item.tours.some(tour =>
            tour?.provider === 'anex' && tour?.cachedListing === true
          )).length >= 7;
        }
        """,
        timeout=30_000,
    )
    page.wait_for_timeout(750)
    result = page.evaluate(
        """
        () => {
          const events = window.__localDbEvents || [];
          const complete = [...events].reverse().find(event => event.status === 'complete') || null;
          const items = Array.isArray(window.V2Results?.state?.items) ? window.V2Results.state.items : [];
          const anexItems = items.filter(item => Array.isArray(item?.tours) && item.tours.some(tour =>
            tour?.provider === 'anex' && tour?.cachedListing === true
          ));
          const projected = anexItems.map(item => ({
            id: String(item?.anytourHotelId ?? item?.id ?? ''),
            name: String(item?.name ?? ''),
            category: item?.category ?? null,
            anexOffers: item.tours.filter(tour => tour?.provider === 'anex' && tour?.cachedListing === true).length,
            priceStates: [...new Set(item.tours.filter(tour => tour?.provider === 'anex' && tour?.cachedListing === true)
              .map(tour => tour?.listingPriceState ?? ''))].sort(),
          }));
          const cards = [...document.querySelectorAll('.hotel-card[data-anytour-hotel-id]')].map(card => ({
            id: String(card.dataset.anytourHotelId || ''),
            text: String(card.innerText || '').slice(0, 500),
          }));
          return {
            url: location.href,
            title: document.title,
            localDbEvents: events,
            complete,
            resultItemCount: items.length,
            anexItems: projected,
            cardIds: cards.map(card => card.id),
            cardCount: cards.length,
            bodyOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
          };
        }
        """
    )
    assert result["complete"] is not None, result
    assert int(result["complete"].get("hotels", 0)) == 7, result
    assert int(result["complete"].get("offers", 0)) == 199, result
    assert len(result["anexItems"]) == 7, result
    assert len({row["id"] for row in result["anexItems"]}) == 7, result
    assert all(row["category"] == 4 for row in result["anexItems"]), result
    assert all(row["anexOffers"] > 0 for row in result["anexItems"]), result
    assert set(row["id"] for row in result["anexItems"]).issubset(set(result["cardIds"])), result
    assert result["cardCount"] >= 7, result
    assert result["bodyOverflow"] is False, result
    result["pageErrors"] = errors
    result["consoleErrors"] = console_errors
    page.screenshot(path=str(OUT / f"exact-anex-{width}x{height}.png"), full_page=True)
    context.close()
    return result


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True, args=["--no-sandbox"])
    results = [
        run_viewport(browser, 390, 844),
        run_viewport(browser, 1440, 980),
    ]
    browser.close()

summary = {
    "status": "pass",
    "url": URL,
    "viewports": [
        {
            "width": 390 if index == 0 else 1440,
            "height": 844 if index == 0 else 980,
            "hotelCount": len(result["anexItems"]),
            "offerCount": sum(row["anexOffers"] for row in result["anexItems"]),
            "categories": sorted({row["category"] for row in result["anexItems"]}),
            "cardCount": result["cardCount"],
            "localDbComplete": result["complete"],
            "pageErrors": result["pageErrors"],
            "consoleErrors": result["consoleErrors"],
        }
        for index, result in enumerate(results)
    ],
    "supplierRequestsAllowed": 0,
    "leadSubmissions": 0,
    "dbWrites": 0,
}
(OUT / "result.json").write_text(
    json.dumps(summary, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
    encoding="utf-8",
)
print(json.dumps(summary, ensure_ascii=False, sort_keys=True))

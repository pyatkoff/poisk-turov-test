#!/usr/bin/env python3
from __future__ import annotations

import http.cookiejar
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.request

ENDPOINT = 'https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php'
PAGE = 'https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/'
OUT = Path(os.environ['RUNNER_TEMP']) / 'anex-live-acceptance-http.json'


def post(opener: urllib.request.OpenerDirector, payload: dict) -> tuple[int, dict]:
    body = json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode()
    request = urllib.request.Request(ENDPOINT, data=body, method='POST', headers={
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Origin': 'https://anytoour.ru',
        'Referer': PAGE,
        'Sec-Fetch-Site': 'same-origin',
        'User-Agent': 'AnyTour-INT-ANEX-live-acceptance/3',
    })
    try:
        with opener.open(request, timeout=90) as response:
            code = response.status
            raw = response.read(2 * 1024 * 1024)
    except urllib.error.HTTPError as error:
        code = error.code
        raw = error.read(2 * 1024 * 1024)
    data = json.loads(raw.decode('utf-8'))
    if not isinstance(data, dict):
        raise RuntimeError('ANEX_ACCEPTANCE_RESPONSE_NOT_OBJECT')
    return code, data


def save(summary: dict) -> None:
    OUT.write_text(json.dumps(summary, ensure_ascii=False, sort_keys=True) + '\n', encoding='utf-8')


def projected_tours(hotels: object) -> list[tuple[int, dict]]:
    rows: list[tuple[int, dict]] = []
    if not isinstance(hotels, list):
        return rows
    for hotel in hotels:
        if not isinstance(hotel, dict):
            continue
        local_id = hotel.get('local_id')
        tours = hotel.get('tours')
        if not isinstance(local_id, int) or local_id < 1 or not isinstance(tours, list):
            continue
        for tour in tours:
            if isinstance(tour, dict):
                rows.append((local_id, tour))
    return rows


def main() -> int:
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    generation = 1789648200
    params = {
        'departureId': '1', 'countryId': '4', 'dateFrom': '2026-10-12', 'dateTo': '2026-10-12',
        'nightsFrom': 7, 'nightsTo': 7, 'adults': 2, 'childs': [],
        'meal': '', 'hotelCategory': '', 'hotelRating': '', 'hotelTypes': [], 'hotelIds': [],
        'hotelServices': [], 'arrivalId': '', 'regionIds': [], 'subregionIds': [], 'operatorIds': [],
        'priceFrom': '', 'priceTo': '', 'currency': 'RUB', 'onlyCharter': False, 'onlyDirect': False,
    }
    summary = {
        'status': 'starting', 'supplier_searches': 0, 'expansion_calls': 0, 'apd_contexts': 0,
        'lead_calls': 0, 'booking_calls': 0, 'metrika_writes': 0, 'mapping_writes': 0,
    }
    save(summary)

    code, search = post(opener, {
        'generation': generation,
        'params': params,
        'labels': {'from': 'Москва', 'country': 'Египет'},
    })
    summary['supplier_searches'] = 1
    summary['search_http'] = code
    data = search.get('data') if isinstance(search, dict) else None
    hotels = data.get('hotels') if isinstance(data, dict) else None
    rows = projected_tours(hotels)
    summary['mapped_hotels'] = len(hotels) if isinstance(hotels, list) else 0
    summary['projected_tours'] = len(rows)
    summary['grouped_tours'] = sum(1 for _, tour in rows if tour.get('kind') == 'group_minimum')
    summary['concrete_tours_before_expand'] = sum(1 for _, tour in rows if tour.get('kind') == 'concrete')
    summary['status'] = 'search_received'
    save(summary)
    if code != 200 or search.get('ok') is not True or not isinstance(data, dict) or data.get('provider') != 'anex':
        raise RuntimeError('ANEX_ACCEPTANCE_SEARCH_FAILED')
    search_ref = data.get('search_ref')
    if not isinstance(search_ref, str) or not re.fullmatch(r'[a-f0-9]{32}', search_ref):
        raise RuntimeError('ANEX_ACCEPTANCE_SEARCH_REF')

    grouped: tuple[int, dict] | None = next(
        ((local_id, tour) for local_id, tour in rows
         if tour.get('kind') == 'group_minimum'
         and isinstance(tour.get('offer_ref'), str)
         and re.fullmatch(r'anex_online:[a-f0-9]{64}', tour['offer_ref'])),
        None,
    )
    if grouped is None:
        raise RuntimeError('ANEX_ACCEPTANCE_NO_GROUP_MINIMUM')
    group_local, group_tour = grouped
    code, expanded = post(opener, {
        'action': 'expand',
        'generation': generation,
        'search_ref': search_ref,
        'offer_ref': group_tour['offer_ref'],
        'local_hotel_id': group_local,
    })
    summary['expansion_calls'] = 1
    summary['expand_http'] = code
    edata = expanded.get('data') if isinstance(expanded, dict) else None
    expanded_hotels = edata.get('hotels') if isinstance(edata, dict) else None
    expanded_rows = projected_tours(expanded_hotels)
    summary['expanded_hotels'] = len(expanded_hotels) if isinstance(expanded_hotels, list) else 0
    summary['concrete_tours_after_expand'] = sum(1 for _, tour in expanded_rows if tour.get('kind') == 'concrete')
    summary['external_search_pending'] = bool(edata.get('external_search_pending')) if isinstance(edata, dict) else None
    summary['status'] = 'expanded'
    save(summary)
    if code != 200 or expanded.get('ok') is not True or not isinstance(edata, dict) or edata.get('status') != 'expanded':
        raise RuntimeError('ANEX_ACCEPTANCE_EXPAND_FAILED:' + str(code) + ':' + str(expanded.get('error')))

    concrete: tuple[int, dict] | None = next(
        ((local_id, tour) for local_id, tour in expanded_rows
         if tour.get('kind') == 'concrete'
         and isinstance(tour.get('offer_ref'), str)
         and re.fullmatch(r'anex_online:[a-f0-9]{64}', tour['offer_ref'])),
        None,
    )
    if concrete is None:
        raise RuntimeError('ANEX_ACCEPTANCE_EXPAND_NO_CONCRETE')
    local_id, tour = concrete
    code, batch = post(opener, {
        'action': 'additional_prices_batch',
        'generation': generation,
        'search_ref': search_ref,
        'items': [{'offer_ref': tour['offer_ref'], 'local_hotel_id': local_id}],
    })
    summary['apd_contexts'] = 1
    summary['batch_http'] = code
    bdata = batch.get('data') if isinstance(batch, dict) else None
    offers = bdata.get('offers') if isinstance(bdata, dict) else None
    row = offers[0] if isinstance(offers, list) and len(offers) == 1 and isinstance(offers[0], dict) else None
    summary['batch_status'] = bdata.get('status') if isinstance(bdata, dict) else None
    summary['final_price_ready'] = 1 if isinstance(row, dict) and row.get('finalPriceReady') is True else 0
    summary['retryable'] = bool(row.get('retryable')) if isinstance(row, dict) else False
    summary['retry_reason'] = row.get('retry_reason') if isinstance(row, dict) else None
    summary['status'] = 'batch_received'
    save(summary)
    if code != 200 or batch.get('ok') is not True:
        raise RuntimeError('ANEX_ACCEPTANCE_BATCH_FAILED:' + str(code) + ':' + str(batch.get('error')))
    if not isinstance(bdata, dict) or bdata.get('provider') != 'anex' or bdata.get('status') != 'additional_prices_batch':
        raise RuntimeError('ANEX_ACCEPTANCE_BATCH_CONTRACT')
    if not isinstance(row, dict) or row.get('finalPriceReady') is not True:
        raise RuntimeError('ANEX_ACCEPTANCE_PRICE_NOT_READY:' + str(summary['retry_reason']))
    final_price = row.get('finalPrice')
    if not isinstance(final_price, str) or not re.fullmatch(r'(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?', final_price) or not re.search(r'[1-9]', final_price):
        raise RuntimeError('ANEX_ACCEPTANCE_PRICE_INVALID')
    summary['final_price_currency'] = 'RUB'
    summary['status'] = 'http_acceptance_complete'
    save(summary)
    print(json.dumps(summary, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

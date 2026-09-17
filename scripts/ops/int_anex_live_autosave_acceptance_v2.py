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
    raw = json.dumps(payload, ensure_ascii=False, separators=(',', ':')).encode()
    req = urllib.request.Request(ENDPOINT, data=raw, method='POST', headers={
        'Content-Type': 'application/json', 'Accept': 'application/json',
        'Origin': 'https://anytoour.ru', 'Referer': PAGE,
        'Sec-Fetch-Site': 'same-origin', 'User-Agent': 'AnyTour-INT-ANEX-live-acceptance/2',
    })
    try:
        with opener.open(req, timeout=90) as response:
            code = response.status
            body = response.read(2 * 1024 * 1024)
    except urllib.error.HTTPError as error:
        code = error.code
        body = error.read(2 * 1024 * 1024)
    data = json.loads(body.decode('utf-8'))
    if not isinstance(data, dict):
        raise RuntimeError('ANEX_ACCEPTANCE_RESPONSE_NOT_OBJECT')
    return code, data


def write_summary(value: dict) -> None:
    OUT.write_text(json.dumps(value, ensure_ascii=False, sort_keys=True) + '\n', encoding='utf-8')


def main() -> int:
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    generation = 1789647600
    params = {
        'departureId': '1', 'countryId': '4', 'dateFrom': '2026-10-12', 'dateTo': '2026-10-12',
        'nightsFrom': 7, 'nightsTo': 7, 'adults': 2, 'childs': [],
        'meal': '', 'hotelCategory': '', 'hotelRating': '', 'hotelTypes': [], 'hotelIds': [],
        'hotelServices': [], 'arrivalId': '', 'regionIds': [], 'subregionIds': [], 'operatorIds': [],
        'priceFrom': '', 'priceTo': '', 'currency': 'RUB', 'onlyCharter': False, 'onlyDirect': False,
    }
    code, search = post(opener, {'generation': generation, 'params': params, 'labels': {'from': 'Москва', 'country': 'Египет'}})
    data = search.get('data') if isinstance(search, dict) else None
    hotels = data.get('hotels') if isinstance(data, dict) else None
    base_summary = {
        'status': 'search_received' if code == 200 and search.get('ok') is True else 'search_failed',
        'search_http': code,
        'mapped_hotels': len(hotels) if isinstance(hotels, list) else 0,
        'lead_calls': 0, 'booking_calls': 0, 'metrika_writes': 0, 'mapping_writes': 0,
    }
    write_summary(base_summary)
    if code != 200 or search.get('ok') is not True:
        raise RuntimeError('ANEX_ACCEPTANCE_SEARCH_FAILED:' + str(code) + ':' + str(search.get('error')))
    if not isinstance(data, dict) or data.get('provider') != 'anex' or data.get('generation') != generation:
        raise RuntimeError('ANEX_ACCEPTANCE_SEARCH_CONTRACT')
    search_ref = data.get('search_ref')
    if not isinstance(search_ref, str) or not re.fullmatch(r'[a-f0-9]{32}', search_ref):
        raise RuntimeError('ANEX_ACCEPTANCE_SEARCH_REF')
    if not isinstance(hotels, list) or not hotels:
        raise RuntimeError('ANEX_ACCEPTANCE_NO_MAPPED_HOTELS')

    chosen = None
    total_tours = 0
    concrete_tours = 0
    grouped_tours = 0
    for hotel in hotels:
        if not isinstance(hotel, dict) or not isinstance(hotel.get('local_id'), int) or hotel['local_id'] < 1:
            continue
        tours = hotel.get('tours')
        if not isinstance(tours, list):
            continue
        total_tours += len(tours)
        for tour in tours:
            if not isinstance(tour, dict):
                continue
            kind = tour.get('kind')
            if kind == 'concrete': concrete_tours += 1
            elif kind == 'group_minimum': grouped_tours += 1
            ref = tour.get('offer_ref')
            if chosen is None and kind == 'concrete' and isinstance(ref, str) and re.fullmatch(r'anex_online:[a-f0-9]{64}', ref):
                chosen = {'offer_ref': ref, 'local_hotel_id': hotel['local_id']}
    base_summary.update({'projected_tours': total_tours, 'concrete_tours': concrete_tours, 'grouped_tours': grouped_tours})
    write_summary(base_summary)
    if chosen is None:
        raise RuntimeError('ANEX_ACCEPTANCE_NO_CONCRETE_OFFER')

    code, batch = post(opener, {
        'action': 'additional_prices_batch', 'generation': generation,
        'search_ref': search_ref, 'items': [chosen],
    })
    bdata = batch.get('data') if isinstance(batch, dict) else None
    offers = bdata.get('offers') if isinstance(bdata, dict) else None
    row = offers[0] if isinstance(offers, list) and len(offers) == 1 and isinstance(offers[0], dict) else None
    summary = dict(base_summary)
    summary.update({
        'status': 'batch_received' if code == 200 and batch.get('ok') is True else 'batch_failed',
        'batch_http': code,
        'batch_status': bdata.get('status') if isinstance(bdata, dict) else None,
        'batch_offers': len(offers) if isinstance(offers, list) else 0,
        'final_price_ready': 1 if isinstance(row, dict) and row.get('finalPriceReady') is True else 0,
        'retryable': bool(row.get('retryable')) if isinstance(row, dict) else False,
        'retry_reason': row.get('retry_reason') if isinstance(row, dict) else None,
    })
    write_summary(summary)
    if code != 200 or batch.get('ok') is not True:
        raise RuntimeError('ANEX_ACCEPTANCE_BATCH_FAILED:' + str(code) + ':' + str(batch.get('error')))
    if not isinstance(bdata, dict) or bdata.get('provider') != 'anex' or bdata.get('status') != 'additional_prices_batch' or not isinstance(offers, list) or len(offers) != 1:
        raise RuntimeError('ANEX_ACCEPTANCE_BATCH_CONTRACT')
    if not isinstance(row, dict) or row.get('finalPriceReady') is not True:
        raise RuntimeError('ANEX_ACCEPTANCE_PRICE_NOT_READY:' + str(summary['retry_reason']))
    price = row.get('finalPrice')
    if not isinstance(price, str) or not re.fullmatch(r'(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?', price) or not re.search(r'[1-9]', price):
        raise RuntimeError('ANEX_ACCEPTANCE_PRICE_INVALID')
    summary.update({'status': 'http_acceptance_complete', 'final_price_currency': 'RUB', 'supplier_searches': 1, 'apd_contexts': 1})
    write_summary(summary)
    print(json.dumps(summary, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

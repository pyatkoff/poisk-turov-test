"""Bounded real-data verification of the PHP adapter, loaded by the SSH probe."""
import base64


def remote_adapter_probe(tokens):
    global SENSITIVE_VALUES
    SENSITIVE_VALUES = tuple(value for raw in tokens.values() if isinstance(raw, str)
                             for value in (raw, raw.strip()) if value)
    token = tokens.get("ANEX_API_TOKEN", "").strip()
    report = {"mode": "adapter", "ok": False, "status": "ANEX_SELECTION_ERROR", "checks": []}
    checks = report["checks"]
    if not token:
        report["status"] = "ANEX_TOKEN_REQUIRED"
        return report
    try:
        departure = choose_named(api_data(token, "SearchTour_TOWNFROMS", {}, checks), (r"москва|moscow",))
        if departure is None:
            raise StopProbe()
        params = {"TOWNFROMINC": positive_id(departure)}
        destination = choose_named(api_data(token, "SearchTour_STATES", params, checks),
                                   (r"егип|egypt", r"турци|turkey|türkiye"))
        if destination is None:
            raise StopProbe()
        params.update(STATEINC=positive_id(destination), ADULT=2, CHILD=0)
        dates = available_dates(api_data(token, "SearchTour_CHECKIN", params, checks),
                                dt.datetime.now(dt.timezone.utc).date())
        if not dates:
            raise StopProbe()
        checkin = dates[0][0].strftime("%Y%m%d")
        params.update(CHECKIN_BEG=checkin, CHECKIN_END=checkin)
        currency = choose_named(api_data(token, "SearchTour_CURRENCIES", params, checks), (r"\brub\b|руб",))
        if currency is None:
            raise StopProbe()
        params["CURRENCY"] = positive_id(currency)
        data = api_data(token, "SearchTour_NIGHTS", params, checks)
        raw_nights = data if isinstance(data, list) else data.get("places") or data.get("nights", [])
        nights = []
        for item in raw_nights:
            value = item.get("id", item.get("nights")) if isinstance(item, dict) else item
            if str(value).isdigit() and 3 <= int(value) <= 14:
                nights.append(int(value))
        if not nights:
            raise StopProbe()
        duration = min(nights, key=lambda value: abs(value - 7))
        egypt = re.search(r"егип|egypt", str(destination.get("name", "")), re.I)
        criteria = {"supplier_namespace": "anex_online", "departure_id": params["TOWNFROMINC"],
                    "destination_id": params["STATEINC"], "currency_id": params["CURRENCY"],
                    "checkin_begin": checkin, "checkin_end": checkin,
                    "nights_from": duration, "nights_till": duration, "adults": 2, "children": 0,
                    "hotel_ids": ["469", "472", "470"] if egypt else ["30536"]}
        report["search"] = {"departure": safe_label(departure.get("name")),
                            "destination": safe_label(destination.get("name")),
                            "checkin": checkin, "nights": duration, "adults": 2, "children": 0}
        modules = []
        for source in ANEX_PHP_SOURCES:
            source = source.removeprefix("<?php")
            # All dependencies are supplied from this exact commit, not the live site.
            source = re.sub(r"^require_once __DIR__ \. '/anex-(?:client|normalizer)\.php';\s*$", "", source, flags=re.M)
            modules.append(source)
        encoded = base64.b64encode(json.dumps(modules).encode()).decode()
        php = "foreach (json_decode(base64_decode('" + encoded + "'), true) as $module) { eval($module); }"
        completed = subprocess.run(
            ["php", "-d", "display_errors=0", "-d", "log_errors=0", "-r", php],
            input=json.dumps({"token": token, "criteria": criteria, "registry": ANEX_REGISTRY_JSON}),
            text=True, capture_output=True, timeout=190,
        )
        if len(completed.stdout) > 100_000 or not completed.stdout.strip():
            report["status"] = "ANEX_ADAPTER_RUNTIME_ERROR"
            return report
        output = json.loads(completed.stdout)
        if not isinstance(output, dict):
            raise ValueError("invalid adapter report")
        report.update(output)
        if completed.returncode and report.get("ok"):
            report.update(ok=False, status="ANEX_ADAPTER_RUNTIME_ERROR")
    except StopProbe:
        pass
    except subprocess.TimeoutExpired:
        report["status"] = "ANEX_ADAPTER_TIMEOUT"
    return report


def clean_adapter_report(report):
    def status(value):
        return value if value == "ok" or isinstance(value, str) and re.fullmatch(r"ANEX_[A-Z_]{1,70}", value) else "ANEX_ADAPTER_ERROR"

    def count(value, maximum=1000):
        return value if type(value) is int and 0 <= value <= maximum else 0

    def label(value):
        if value is None or not isinstance(value, (str, int)):
            return None
        value = str(value).strip()
        if (not value or len(value) > 500 or re.search(r"[\x00-\x1f\x7f]|https?:|oauth_token|[<>]", value, re.I)
                or any(secret and secret in value for secret in SENSITIVE_VALUES)):
            return None
        return value

    def price(value):
        if not isinstance(value, dict):
            return None
        money, currency = value.get("amount"), value.get("currency")
        if (not isinstance(money, str) or not re.fullmatch(r"[0-9]{1,10}(?:\.[0-9]{1,2})?", money)
                or not isinstance(currency, str) or not re.fullmatch(r"[A-Z]{3}", currency)):
            return None
        return {"amount": money, "currency": currency}

    result = {"mode": "adapter", "ok": report.get("ok") is True, "status": status(report.get("status")), "checks": []}
    for item in report.get("checks", [])[:5]:
        if isinstance(item, dict) and item.get("check") in CHECKS and item.get("status") in STATUSES:
            cleaned = {"check": item["check"], "status": item["status"]}
            for key in ("http_status", "elapsed_ms", "count"):
                if type(item.get(key)) is int:
                    cleaned[key] = count(item[key], 1_000_000)
            result["checks"].append(cleaned)
    search = report.get("search", {})
    if isinstance(search, dict):
        result["search"] = {key: label(search.get(key)) for key in ("departure", "destination", "checkin")}
        result["search"].update({key: count(search.get(key), 60) for key in ("nights", "adults", "children")})
    for key in ("search_offers", "search_mapped", "rejected_count", "expanded_offers", "adapter_requests"):
        result[key] = count(report.get(key))
    for key in ("hotel_filter_fallback", "external_search_pending", "price_hotel_card_id_agrees"):
        result[key] = report.get(key) is True
    result["samples"] = []
    for sample in report.get("samples", [])[:3]:
        if not isinstance(sample, dict):
            continue
        external = positive_id({"id": sample.get("anex_hotel_id")})
        local = positive_id({"id": sample.get("local_hotel_id")})
        if external is None or price(sample.get("price")) is None or label(sample.get("hotel")) is None:
            continue
        output = {"anex_hotel_id": external, "local_hotel_id": local,
                  "kind": "concrete" if sample.get("kind") == "concrete" else "unknown",
                  "price": price(sample["price"]), "converted_price": price(sample.get("converted_price")),
                  "nights": count(sample.get("nights"), 60), "final_price_verified": False,
                  "supplier_booking_flag": sample.get("supplier_booking_flag") if type(sample.get("supplier_booking_flag")) is bool else None}
        output.update({key: label(sample.get(key)) for key in ("hotel", "meal", "room", "checkin", "checkout")})
        result["samples"].append(output)
    flights = report.get("flights", {})
    result["flights"] = {"status": status(flights.get("status")), "route_count": count(flights.get("route_count"), 6),
                          "itinerary_details_available": flights.get("itinerary_details_available") is True,
                          "option_count": count(flights.get("option_count"), 360), "selected": False,
                          "final_price_verified": False, "routes": []}
    for route in flights.get("routes", [])[:2]:
        cleaned = {key: label(route.get(key)) for key in ("date", "from", "to")}
        cleaned["options"] = []
        for option in route.get("options", [])[:1]:
            item = {key: label(option.get(key)) for key in ("name", "carrier", "transport_type")}
            for leg in ("departure", "arrival"):
                item[leg] = {key: label(option.get(leg, {}).get(key)) for key in ("airport", "airport_code", "time")}
            item["classes"] = []
            for place in option.get("classes", [])[:10]:
                cls = {key: label(place.get(key)) for key in ("name", "baggage", "hand_baggage", "infant_baggage")}
                cls["availability"] = place.get("availability") if place.get("availability") in ("Y", "N", "F", "R") else None
                item["classes"].append(cls)
            cleaned["options"].append(item)
        result["flights"]["routes"].append(cleaned)
    result["ok"] = (result["ok"] and result["status"] == "ok" and bool(result["samples"])
                    and result["price_hotel_card_id_agrees"] and len(result["checks"]) == 5
                    and all(item["status"] == "ok" for item in result["checks"]))
    return result

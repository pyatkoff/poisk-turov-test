#!/usr/bin/env python3
"""Bounded public ANEX URL-chain evidence. Never assigns a SAMO/Andromeda ID."""
import hashlib
import html
import json
import os
import re
import sys
import time
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import urljoin, urlsplit, parse_qs
from urllib.request import HTTPRedirectHandler, Request, build_opener

OPERATION = "hotel-match-anex-media-chain-1971-20260914-v1"
SEEDS = [
    (4158, "barcelo-tiran-sharm-hotel-sharm-el-sheikh", "barcelo-tiran-sharm-hotel-sharm-el-sheikh-sharm-el-sheikh", [495437, 495438]),
    (1767, "domina-coral-bay-aquamarine-beach-sharm-el-sheikh", "domina-coral-bay-aquamarine-beach-sharm-el-sh-sharm-el-sheikh", [699145, 699146]),
    (1768, "domina-coral-bay-aquamarine-pool-sharm-el-sheikh", "domina-coral-bay-aquamarine-pool-sharm-el-she-sharm-el-sheikh", [698661, 698662]),
    (5215, "aurora-oriental-resort-sharm-el-sheikh", "aurora-oriental-resort-sharm-el-sheikh-ex-ori-sharm-el-sheikh", [532683, 532684]),
]
ALLOWED_HOSTS = frozenset({"files.anextour.ru", "www.anextour.ru", "anextour.ru", "cdn.anextour.ru", "photo.samo.ru", "static.samo.ru"})
MAX_BODY = 2 * 1024 * 1024
MAX_REQUESTS = 40


def seeds():
    out = []
    for aid, slug, media_slug, objects in SEEDS:
        for obj in objects:
            out.append({"anex_id": aid, "kind": "media", "url": f"https://files.anextour.ru/hotel/egypt/hotel/{media_slug}/o{obj}?&hotelCode={aid}"})
        out.append({"anex_id": aid, "kind": "card", "url": f"https://www.anextour.ru/tours/egypt/{slug}"})
    return out


def allowed(url):
    try:
        u = urlsplit(url)
        return (u.scheme == "https" and u.hostname in ALLOWED_HOSTS
                and not u.username and not u.password and u.port in (None, 443)
                and not re.search(r"(?:^|/)(?:api|search|booking|book|order|login)(?:/|$)", u.path, re.I))
    except ValueError:
        return False


def describe_url(url):
    u = urlsplit(url)
    values = parse_qs(u.query)
    result = {"url": url, "host": u.hostname, "path": u.path,
              "integer_path_tokens": re.findall(r"\d+", u.path),
              "query_fields": sorted(values), "hotel_code_values": [],
              "samo_hotel_id": None, "andromeda_hotel_id": None,
              "identity_confirmed": False}
    for key, vals in values.items():
        if key.lower() == "hotelcode":
            result["hotel_code_values"].extend(vals)
    match = re.search(r"/o(\d+)(?:/|$)", u.path)
    if match:
        result["media_object_token"] = match[1]
    return result


def embedded_urls(text, base):
    # No hotelCode/domain filter during extraction: retain ALL observed URL forms.
    text = html.unescape(text.replace("\\/", "/"))
    values = set(re.findall(r'https?://[^\s<>"\'\\]+', text))
    for value in re.findall(r'(?:src|href|data-src|data-original)\s*=\s*["\']([^"\']+)', text, re.I):
        full = urljoin(base, value)
        if urlsplit(full).scheme in {"https", "http"}:
            values.add(full)
    return sorted(values)


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def run(outdir):
    outdir = Path(outdir)
    reservation = json.loads((outdir / "reservation.json").read_text())
    assert reservation["operation_id"] == OPERATION
    assert reservation["state"] == "reserved_before_public_access"
    assert reservation["seed_sha256"] == hashlib.sha256(json.dumps(seeds(), sort_keys=True).encode()).hexdigest()
    assert os.environ.get("GITHUB_RUN_ATTEMPT", "1") == "1"
    opener = build_opener(NoRedirect())
    rows, stopped_hosts, request_count = [], set(), 0
    for index, seed in enumerate(seeds()):
        url, visited = seed["url"], set()
        record = dict(seed, chain=[], terminal_reason=None)
        for hop in range(5):
            host = urlsplit(url).hostname
            if not allowed(url):
                record["terminal_reason"] = "unapproved_redirect_destination_not_fetched"
                record["unfetched_url"] = describe_url(url)
                break
            if host in stopped_hosts:
                record["terminal_reason"] = "host_previously_blocked_no_retry"
                break
            if request_count >= MAX_REQUESTS or url in visited:
                record["terminal_reason"] = "request_budget_or_redirect_loop"
                break
            visited.add(url)
            request_count += 1
            item = {"url": url, "hop": hop, "url_parts": describe_url(url)}
            record["chain"].append(item)
            try:
                req = Request(url, headers={"User-Agent": "AnyTour-MATCH-PublicEvidence/1.0", "Accept": "*/*"})
                try:
                    response = opener.open(req, timeout=18)
                except HTTPError as exc:
                    response = exc
                with response:
                    status = response.code
                    keep = {"content-type", "content-length", "content-disposition", "location", "etag", "last-modified", "content-location"}
                    item.update(status=status, headers={k.lower(): v for k, v in response.headers.items() if k.lower() in keep})
                    if status in {401, 403, 429}:
                        stopped_hosts.add(host)
                        record["terminal_reason"] = "public_access_blocked_no_retry"
                        break
                    if status in {301, 302, 303, 307, 308}:
                        location = response.headers.get("Location")
                        if not location:
                            record["terminal_reason"] = "redirect_without_location"
                            break
                        url = urljoin(url, location)
                        continue
                    data = response.read(MAX_BODY + 1)
                    item.update(body_truncated=len(data) > MAX_BODY, body_bytes=len(data), body_sha256=hashlib.sha256(data).hexdigest())
                    filename = f"response-{index:02d}-{hop}.bin"
                    (outdir / filename).write_bytes(data)
                    item["body_file"] = filename
                    ctype = response.headers.get("Content-Type", "").lower()
                    if any(kind in ctype for kind in ("text/", "json", "xml", "javascript")):
                        text = data.decode("utf-8", "replace")
                        item["embedded_urls"] = embedded_urls(text, url)
                        item["identity_contexts"] = [text[max(0,m.start()-100):m.end()+140] for m in list(re.finditer(r"samo|hotelKey|hotelCode|hotelId|photo", text, re.I))[:100]]
                    record["terminal_reason"] = "http_response_captured"
                    break
            except Exception as exc:
                item.update(error_type=type(exc).__name__, error=str(exc)[:600])
                record["terminal_reason"] = "network_error_no_retry"
                stopped_hosts.add(host)
                break
            finally:
                time.sleep(0.3)
        if record["terminal_reason"] is None:
            record["terminal_reason"] = "redirect_limit"
        rows.append(record)
        (outdir / "partial.json").write_text(json.dumps(rows, ensure_ascii=False, indent=2) + "\n")
        print(json.dumps(record, ensure_ascii=False), flush=True)
    result = {"operation_id": OPERATION, "state": "completed_read_only", "seed_count": len(seeds()),
              "request_count": request_count, "rows": rows, "mapping_writes": 0, "database_access": False,
              "supplier_search_calls": 0, "tourvisor_calls": 0, "confirmed_samo_ids": [], "no_replay": True}
    payload = (json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode()
    (outdir / "result.json").write_bytes(payload)
    receipt = {"operation_id": OPERATION, "state": result["state"], "source_sha": os.environ.get("GITHUB_SHA"),
               "run_id": os.environ.get("GITHUB_RUN_ID"), "request_count": request_count,
               "result_sha256": hashlib.sha256(payload).hexdigest(), "mapping_writes": 0, "no_replay": True,
               "files": {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in outdir.iterdir() if p.is_file()}}
    (outdir / "receipt.json").write_text(json.dumps(receipt, sort_keys=True, indent=2) + "\n")
    assert hashlib.sha256((outdir / "result.json").read_bytes()).hexdigest() == receipt["result_sha256"]
    print("RECEIPT " + json.dumps(receipt, sort_keys=True), flush=True)


if __name__ == "__main__":
    run(sys.argv[1])

#!/usr/bin/env python3
from __future__ import annotations

import argparse
import difflib
import hashlib
import html
import json
import re
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict, deque
from html.parser import HTMLParser
from pathlib import Path
from typing import Any

SITE_HOSTS = {"anextour.ru", "www.anextour.ru"}
API_HOST = "api.anextour.ru"
COUNTRY_SLUG = {1: "egypt", 4: "turkey"}
GENERIC = {"hotel", "hotels", "resort", "resorts", "spa"}
UA = "AnyTour-MATCH-public-sitemap-evidence/1.0"


def clean_text(value: str) -> str:
    value = html.unescape(value or "")
    value = unicodedata.normalize("NFKD", value)
    value = "".join(ch for ch in value if not unicodedata.combining(ch))
    value = value.replace("’", "'").replace("`", "'").replace("–", "-").replace("—", "-")
    return re.sub(r"\s+", " ", value).strip()


def norm(value: str, drop_generic: bool = False) -> str:
    value = clean_text(value).casefold()
    value = re.sub(r"\b(?:ex|ex\.|former|formerly)\b", " ", value)
    value = re.sub(r"[\*+]+", " ", value)
    toks = re.findall(r"[a-z0-9]+", value)
    if drop_generic:
        toks = [t for t in toks if t not in GENERIC]
    return " ".join(toks)


def variants(value: str) -> list[str]:
    out: list[str] = []
    raw = clean_text(value)
    pieces = [raw]
    # Existing/former names are useful aliases, but do not erase meaningful qualifiers.
    for m in re.finditer(r"\((?:ex\.?|formerly?|former)\s*([^\)]+)\)", raw, re.I):
        pieces.append(m.group(1))
    pieces.append(re.sub(r"\((?:ex\.?|formerly?|former)[^\)]*\)", " ", raw, flags=re.I))
    for p in pieces:
        for drop in (False, True):
            n = norm(p, drop)
            if n and n not in out:
                out.append(n)
    return out


def slug_guess(name: str) -> str:
    n = norm(name, False)
    return re.sub(r"[^a-z0-9]+", "-", n).strip("-")


def name_score(a: str, b: str) -> float:
    av, bv = variants(a), variants(b)
    best = 0.0
    for x in av:
        xs = set(x.split())
        for y in bv:
            if x == y:
                return 1.0
            ys = set(y.split())
            seq = difflib.SequenceMatcher(None, x, y).ratio()
            jac = len(xs & ys) / max(1, len(xs | ys))
            contain = min(len(x), len(y)) / max(len(x), len(y)) if (x in y or y in x) else 0.0
            best = max(best, seq, jac * 0.96, contain * 0.94)
    return best


class LinkParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[tuple[str, str]] = []
        self._href: str | None = None
        self._text: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag.lower() != "a":
            return
        href = dict(attrs).get("href")
        if href:
            self._href = href
            self._text = []

    def handle_data(self, data: str) -> None:
        if self._href is not None:
            self._text.append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag.lower() == "a" and self._href is not None:
            self.links.append((self._href, clean_text(" ".join(self._text))))
            self._href = None
            self._text = []


def get(url: str, max_bytes: int = 4 * 1024 * 1024, tries: int = 2) -> tuple[str, bytes, int, str | None]:
    last: Exception | None = None
    for attempt in range(tries):
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "text/html,application/json,text/plain,*/*"})
        try:
            with urllib.request.urlopen(req, timeout=25) as r:
                final = r.geturl()
                body = r.read(max_bytes)
                return final, body, int(r.status), r.headers.get("content-type")
        except urllib.error.HTTPError as e:
            last = e
            if e.code == 429 and attempt + 1 < tries:
                time.sleep(2.0 + attempt)
                continue
            raise
        except Exception as e:
            last = e
            if attempt + 1 < tries:
                time.sleep(0.6)
                continue
            raise
    raise RuntimeError(str(last))


def crawl_country(country_slug: str, max_pages: int, delay: float) -> tuple[list[dict[str, Any]], list[dict[str, Any]], int]:
    start = f"https://anextour.ru/sitemap/hotels/{country_slug}"
    q = deque([start])
    seen: set[str] = set()
    hotel_by_path: dict[str, dict[str, Any]] = {}
    pages: list[dict[str, Any]] = []
    calls = 0
    prefix = f"/sitemap/hotels/{country_slug}"
    tour_prefix = f"/tours/{country_slug}/"
    while q and len(seen) < max_pages:
        url = q.popleft()
        if url in seen:
            continue
        seen.add(url)
        try:
            final, body, status, ctype = get(url)
            calls += 1
        except Exception as e:
            pages.append({"url": url, "error": type(e).__name__ + ":" + str(e)[:240]})
            continue
        p = urllib.parse.urlparse(final)
        if p.scheme != "https" or (p.hostname or "").lower() not in SITE_HOSTS:
            pages.append({"url": url, "final_url": final, "error": "redirect_outside_allowlist"})
            continue
        text = body.decode(errors="replace")
        parser = LinkParser()
        try:
            parser.feed(text)
        except Exception:
            pass
        child_count = 0
        hotel_count = 0
        for href, anchor in parser.links:
            absu = urllib.parse.urljoin(final, href)
            pr = urllib.parse.urlparse(absu)
            if pr.scheme != "https" or (pr.hostname or "").lower() not in SITE_HOSTS:
                continue
            path = pr.path.rstrip("/")
            if path.startswith(prefix + "/") and absu not in seen:
                q.append(urllib.parse.urlunparse(("https", "anextour.ru", path, "", "", "")))
                child_count += 1
            if path.startswith(tour_prefix) and len(path.split("/")) >= 4:
                row = hotel_by_path.setdefault(path, {"path": path, "name": anchor, "sources": []})
                if anchor and (not row.get("name") or len(anchor) > len(row.get("name", ""))):
                    row["name"] = anchor
                if final not in row["sources"]:
                    row["sources"].append(final)
                hotel_count += 1
        pages.append({"url": url, "final_url": final, "http": status, "content_type": ctype, "bytes": len(body), "sha256": hashlib.sha256(body).hexdigest(), "child_links": child_count, "hotel_links": hotel_count})
        time.sleep(delay)
    hotels = sorted(hotel_by_path.values(), key=lambda r: (norm(r.get("name", "")), r["path"]))
    return hotels, pages, calls


def api_detail(path: str, delay: float) -> tuple[dict[str, Any] | None, list[dict[str, Any]], int]:
    calls: list[dict[str, Any]] = []
    current = path
    count = 0
    for _ in range(3):
        url = "https://api.anextour.ru/b2c/hotel?hotel=" + urllib.parse.quote(current, safe="")
        try:
            final, body, status, ctype = get(url, max_bytes=3 * 1024 * 1024)
            count += 1
        except Exception as e:
            calls.append({"path": current, "url": url, "error": type(e).__name__ + ":" + str(e)[:240]})
            return None, calls, count
        pr = urllib.parse.urlparse(final)
        if pr.scheme != "https" or (pr.hostname or "").lower() != API_HOST:
            calls.append({"path": current, "url": url, "final_url": final, "error": "redirect_outside_allowlist"})
            return None, calls, count
        try:
            data = json.loads(body)
        except Exception as e:
            calls.append({"path": current, "url": url, "http": status, "bytes": len(body), "error": "json:" + str(e)[:160]})
            return None, calls, count
        rec: dict[str, Any] = {"path": current, "url": url, "http": status, "bytes": len(body), "sha256": hashlib.sha256(body).hexdigest(), "content_type": ctype}
        if isinstance(data, list) and len(data) == 1 and isinstance(data[0], dict) and data[0].get("code") == 301 and isinstance(data[0].get("url"), str):
            rec["canonical_to"] = data[0]["url"]
            calls.append(rec)
            nxt = data[0]["url"]
            if nxt == current:
                return None, calls, count
            current = nxt
            time.sleep(delay)
            continue
        calls.append(rec)
        if isinstance(data, list) and data and isinstance(data[0], dict) and isinstance(data[0].get("info"), dict):
            info = data[0]["info"]
            town = data[0].get("town") if isinstance(data[0].get("town"), dict) else {}
            region = data[0].get("region") if isinstance(data[0].get("region"), dict) else {}
            state = data[0].get("state") if isinstance(data[0].get("state"), dict) else {}
            rooms = data[0].get("rooms") if isinstance(data[0].get("rooms"), list) else []
            room_hotels = sorted({r.get("hotel") for r in rooms if isinstance(r, dict) and isinstance(r.get("hotel"), int)})
            out = {
                "inc": info.get("inc"), "name": info.get("name"), "lname": info.get("lname"), "exName": info.get("exName"), "exLName": info.get("exLName"),
                "slug": info.get("slug"), "b2cLink": info.get("b2cLink"), "star": info.get("star"), "starKey": info.get("starKey"), "groupStar": info.get("groupStar"), "stargname": info.get("stargname"),
                "state": info.get("state"), "stateKey": info.get("stateKey"), "town": info.get("town"), "townKey": info.get("townKey"), "address": info.get("address"),
                "latitude": info.get("latitude"), "longitude": info.get("longitude"), "partnerInc": info.get("partnerInc"),
                "state_obj": {"inc": state.get("inc"), "name": state.get("name"), "lname": state.get("lname")},
                "town_obj": {"inc": town.get("inc"), "name": town.get("name"), "lname": town.get("lname"), "region": town.get("region"), "slug": town.get("slug")},
                "region_obj": {"inc": region.get("inc"), "name": region.get("name"), "lname": region.get("lname"), "state": region.get("state"), "slug": region.get("slug")},
                "room_hotel_values": room_hotels,
            }
            return out, calls, count
        return None, calls, count
    return None, calls, count


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--queue", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--max-pages-per-country", type=int, default=220)
    ap.add_argument("--max-api-calls", type=int, default=1500)
    ap.add_argument("--top-candidates", type=int, default=15)
    ap.add_argument("--delay", type=float, default=0.08)
    ns = ap.parse_args()
    queue = json.loads(Path(ns.queue).read_text(encoding="utf-8"))
    seeds = [s for s in queue.get("seeds", []) if s.get("observed") and int(s.get("country_id") or 0) in COUNTRY_SLUG]
    by_country: dict[int, list[dict[str, Any]]] = defaultdict(list)
    for s in seeds:
        by_country[int(s["country_id"])].append(s)

    sitemap_hotels: dict[int, list[dict[str, Any]]] = {}
    sitemap_pages: dict[int, list[dict[str, Any]]] = {}
    public_calls = 0
    for cid in sorted(by_country):
        hotels, pages, calls = crawl_country(COUNTRY_SLUG[cid], ns.max_pages_per_country, ns.delay)
        sitemap_hotels[cid] = hotels
        sitemap_pages[cid] = pages
        public_calls += calls

    ranked: dict[int, list[dict[str, Any]]] = {}
    path_priority: dict[str, float] = {}
    path_seed_ids: dict[str, set[int]] = defaultdict(set)
    for s in seeds:
        sid = int(s["anex_hotel_id"])
        cid = int(s["country_id"])
        source_names = [n for n in (s.get("source_names") or []) if isinstance(n, str) and n.strip()]
        candidates: list[tuple[float, dict[str, Any]]] = []
        for h in sitemap_hotels.get(cid, []):
            score = max((name_score(sn, h.get("name", "")) for sn in source_names), default=0.0)
            if score >= 0.58:
                candidates.append((score, h))
        candidates.sort(key=lambda t: (-t[0], t[1]["path"]))
        chosen = candidates[: ns.top_candidates]
        # Guessed canonical path is cheap and catches names absent from a resort sitemap alias.
        for sn in source_names[:3]:
            g = slug_guess(sn)
            if g:
                path = f"/hotels/{COUNTRY_SLUG[cid]}/{g}"
                if all(x[1]["path"] != path for x in chosen):
                    chosen.append((0.57, {"path": path, "name": sn, "sources": ["name_guess"]}))
        ranked[sid] = [{"score": round(score, 6), **h} for score, h in chosen]
        for score, h in chosen:
            path_priority[h["path"]] = max(path_priority.get(h["path"], 0.0), score)
            path_seed_ids[h["path"]].add(sid)

    ordered_paths = sorted(path_priority, key=lambda p: (-path_priority[p], p))[: ns.max_api_calls]
    detail_by_path: dict[str, dict[str, Any]] = {}
    api_calls: list[dict[str, Any]] = []
    inc_to_paths: dict[int, list[str]] = defaultdict(list)
    for path in ordered_paths:
        detail, trace, calls = api_detail(path, ns.delay)
        public_calls += calls
        api_calls.extend(trace)
        if detail and isinstance(detail.get("inc"), int):
            detail_by_path[path] = detail
            canon = detail.get("slug")
            if isinstance(canon, str) and canon:
                detail_by_path.setdefault(canon, detail)
            inc_to_paths[int(detail["inc"])].append(path)
        time.sleep(ns.delay)

    matched: list[dict[str, Any]] = []
    unmatched: list[dict[str, Any]] = []
    for s in seeds:
        sid = int(s["anex_hotel_id"])
        hits: list[dict[str, Any]] = []
        for cand in ranked.get(sid, []):
            d = detail_by_path.get(cand["path"])
            if d and d.get("inc") == sid:
                hits.append({"candidate": cand, "detail": d})
        # A canonical detail can be reached through an alias/301 path; collapse by detail slug.
        uniq: dict[str, dict[str, Any]] = {}
        for h in hits:
            key = str(h["detail"].get("slug") or h["candidate"]["path"])
            prev = uniq.get(key)
            if prev is None or h["candidate"]["score"] > prev["candidate"]["score"]:
                uniq[key] = h
        hits = sorted(uniq.values(), key=lambda h: (-h["candidate"]["score"], str(h["detail"].get("slug") or "")))
        base = {"anex_hotel_id": sid, "country_id": int(s["country_id"]), "search_count": int(s.get("search_count") or 0), "last_seen_utc": s.get("last_seen_utc"), "source_names": s.get("source_names") or [], "source_places": s.get("source_places") or [], "review_reason": s.get("review_reason"), "candidate_ids": s.get("candidate_ids") or [], "target": s.get("target"), "best": s.get("best"), "guard": s.get("guard")}
        if hits:
            matched.append({**base, "status": "confirmed_public_anex_identity", "hits": hits})
        else:
            unmatched.append({**base, "status": "public_slug_not_confirmed", "ranked_candidates": ranked.get(sid, [])[:8]})

    result = {
        "status": "completed",
        "mode": "observed_anex_public_sitemap_and_detail_identity_read_only",
        "source_queue_operation": queue.get("operation_id"),
        "source_queue_counts": queue.get("counts"),
        "seed_count": len(seeds),
        "seed_by_country": {str(cid): len(rows) for cid, rows in sorted(by_country.items())},
        "sitemap_hotel_count": {str(cid): len(sitemap_hotels.get(cid, [])) for cid in sorted(by_country)},
        "sitemap_page_count": {str(cid): len(sitemap_pages.get(cid, [])) for cid in sorted(by_country)},
        "api_candidate_path_count": len(ordered_paths),
        "api_detail_count": len({int(d["inc"]) for d in detail_by_path.values() if isinstance(d.get("inc"), int)}),
        "confirmed_count": len(matched),
        "unmatched_count": len(unmatched),
        "public_get_calls": public_calls,
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_search_calls": 0,
        "historical_operations_replayed": False,
        "guards": {"observed_only": True, "countries": sorted(by_country), "info_inc_required_to_equal_queue_anex_hotel_id": True, "manual_decisions_overwritten": False, "existing_mappings_overwritten": False, "protected_payloads_changed": False},
        "matched": matched,
        "unmatched": unmatched,
        "sitemap_pages": {str(cid): sitemap_pages.get(cid, []) for cid in sorted(by_country)},
        "api_calls": api_calls,
    }
    Path(ns.out).write_text(json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({k: result[k] for k in ["seed_count", "seed_by_country", "sitemap_hotel_count", "sitemap_page_count", "api_candidate_path_count", "api_detail_count", "confirmed_count", "unmatched_count", "public_get_calls"]}, ensure_ascii=False, sort_keys=True))
    for row in matched[:40]:
        h = row["hits"][0]
        d = h["detail"]
        print(json.dumps({"anex_hotel_id": row["anex_hotel_id"], "search_count": row["search_count"], "source_names": row["source_names"], "score": h["candidate"]["score"], "slug": d.get("slug"), "name": d.get("name"), "state": d.get("state"), "town": d.get("town"), "region": (d.get("region_obj") or {}).get("name"), "lat": d.get("latitude"), "lon": d.get("longitude")}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

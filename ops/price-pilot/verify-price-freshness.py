import datetime as dt
import json
import pathlib
import sys
import urllib.parse
import urllib.request


def timestamp(value):
    return dt.datetime.fromisoformat(value).replace(tzinfo=dt.timezone.utc)


def get(path):
    request = urllib.request.Request(
        "https://anytoour.ru" + path, headers={"User-Agent": "AnyTour PRICE deployment verification"}
    )
    with urllib.request.urlopen(request, timeout=12) as response:
        if response.status != 200 or urllib.parse.urlsplit(response.url).hostname != "anytoour.ru":
            raise RuntimeError(f"unexpected status or origin for {path.split('?')[0]}")
        return response.read(4_000_000).decode("utf-8")


sample = json.loads(pathlib.Path(sys.argv[1]).read_text())
now = dt.datetime.now(dt.timezone.utc)
observation = timestamp(sample["observation_latest"])
seo_observed = timestamp(sample["seo_observed"])
seo_expires = timestamp(sample["seo_expires"])
if not dt.timedelta(0) <= now - observation < dt.timedelta(hours=2):
    raise RuntimeError("latest source observation exceeds one collection cycle plus one retry")
if not dt.timedelta(0) <= now - seo_observed < dt.timedelta(hours=72) or seo_expires <= now:
    raise RuntimeError("SEO country snapshot is outside the existing freshness window")

query = urllib.parse.urlencode(sample["query"])
history = json.loads(get("/data/price-intelligence-read-v1.php?" + query))
if history.get("source") != "anytour-first-party-price-history" or history.get("cachedPriceIsFinal") is not False:
    raise RuntimeError("Search3 history endpoint returned an unexpected price contract")
if history.get("segment") != sample["query"]["segment"] or history.get("windowDays") != 31:
    raise RuntimeError("Search3 history endpoint did not return the queried segment and window")
if (history.get("ok") is not True or history.get("observedDays") != sample["history_points"]
        or max(point["date"] for point in history["series"]) != sample["history_latest"]):
    raise RuntimeError("Search3 price-history dates do not match production storage")
print(f"PRICE_SEARCH3_FRESHNESS_OK latest_history={sample['history_latest']} points={sample['history_points']}")
for slug in ("turkey", "egypt"):
    page = get(f"/country/{slug}/")
    if "свеж" not in page.lower() or "/poisk-turov/" not in page:
        raise RuntimeError(f"SEO price evidence entry points are missing on {slug}")
print(f"PRICE_SEO_FRESHNESS_OK observed={sample['seo_observed']} expires={sample['seo_expires']}")

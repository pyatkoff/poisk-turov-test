import json
import pathlib
import sys
import urllib.error
import urllib.parse
import urllib.request


def get(path):
    request = urllib.request.Request(
        "https://anytoour.ru" + path, headers={"User-Agent": "AnyTour PRICE deployment verification"}
    )
    with urllib.request.urlopen(request, timeout=12) as response:
        if response.status != 200 or urllib.parse.urlsplit(response.url).hostname != "anytoour.ru":
            raise RuntimeError(f"unexpected status or origin for {path.split('?')[0]}")
        return response.read(4_000_000).decode("utf-8")


sample = json.loads(pathlib.Path(sys.argv[1]).read_text())
age = sample["observation_age_minutes"]
latest = sample["observation_latest"]
failed = False
print(f"PRICE_SOURCE_CLOCK database_offset_minutes={sample['database_offset_minutes']} rollup_timezone={sample['rollup_timezone']}")
if not 0 <= age < 120:
    print(f"PRICE_SOURCE_STALE latest_local={latest} age_minutes={age}")
    failed = True
else:
    print(f"PRICE_SOURCE_FRESH latest_local={latest} age_minutes={age}")
if not 0 <= sample["seo_age_minutes"] < 72 * 60 or sample["seo_remaining_seconds"] <= 0:
    print(f"PRICE_SEO_SNAPSHOT_STALE observed={sample['seo_observed']} expires={sample['seo_expires']}")
    failed = True

try:
    query = urllib.parse.urlencode(sample["query"])
    history = json.loads(get("/data/price-intelligence-read-v1.php?" + query))
    assert history.get("source") == "anytour-first-party-price-history"
    assert history.get("cachedPriceIsFinal") is False
    assert history.get("segment") == sample["query"]["segment"] and history.get("windowDays") == 31
    assert history.get("ok") is True and history.get("observedDays") == sample["history_points"]
    assert max(point["date"] for point in history["series"]) == sample["history_latest"]
    print(f"PRICE_SEARCH3_FRESHNESS_OK latest_history={sample['history_latest']} points={sample['history_points']}")
except urllib.error.HTTPError as error:
    print(f"PRICE_SEARCH3_FRESHNESS_FAILED http_status={error.code}")
    failed = True
except (AssertionError, KeyError, ValueError, OSError, RuntimeError):
    print("PRICE_SEARCH3_FRESHNESS_FAILED reason=consumer_contract")
    failed = True

for slug in ("turkey", "egypt"):
    try:
        page = get(f"/country/{slug}/")
        assert "свеж" in page.lower() and "/poisk-turov/" in page
        print(f"PRICE_SEO_RENDER_OK country={slug}")
    except urllib.error.HTTPError as error:
        print(f"PRICE_SEO_RENDER_FAILED country={slug} http_status={error.code}")
        failed = True
    except (AssertionError, OSError, RuntimeError):
        print(f"PRICE_SEO_RENDER_FAILED country={slug} reason=consumer_contract")
        failed = True
print(f"PRICE_SEO_SNAPSHOT observed={sample['seo_observed']} expires={sample['seo_expires']}")
raise SystemExit(int(failed))

#!/usr/bin/env python3
"""Build a compact preview-only ANEX XML identity registry from a match report."""
import argparse
import csv
import datetime as dt
import hashlib
import io
import json
from pathlib import Path
import re
import unicodedata


def norm(value):
    value = unicodedata.normalize("NFKD", str(value or "").casefold())
    value = "".join(ch for ch in value if not unicodedata.combining(ch))
    value = re.sub(r"\b(?:ex|former|formerly|hotel|hotels|resort|spa|the|отель|бывш)\b", " ", value)
    return " ".join(re.findall(r"[\w]+", value, re.UNICODE))


def country(value):
    value = norm(value)
    aliases = {
        "россия": "russia", "russian federation": "russia", "russia": "russia",
        "турция": "turkey", "turkiye": "turkey", "turkey": "turkey",
        "египет": "egypt", "egypt": "egypt",
        "оаэ": "uae", "united arab emirates": "uae", "uae": "uae",
        "таиланд": "thailand", "thailand": "thailand",
    }
    return aliases.get(value, value)


def town_matches(left, right):
    left, right = norm(left), norm(right)
    if not left or not right:
        return False
    if left == right:
        return True
    left_words, right_words = set(left.split()), set(right.split())
    return bool(left_words and right_words and (left_words <= right_words or right_words <= left_words))


def positive_int(value, maximum=2_147_483_647):
    if isinstance(value, bool):
        raise ValueError("invalid identity")
    text = str(value)
    if not re.fullmatch(r"[1-9][0-9]*", text) or int(text) > maximum:
        raise ValueError("invalid identity")
    return int(text)


def build_registry(report, evidence):
    if (not isinstance(report, dict) or report.get("schema_version") != 1
            or report.get("provider") != "anex_xml" or not isinstance(report.get("matches"), list)):
        raise ValueError("invalid report")
    strict = [row for row in report["matches"] if row.get("status") == "verified_auto"]
    mappings, seen = [], {}
    deferred_short = 0
    for row in strict:
        external_id = positive_int(row.get("external_id"), 99_999_999)
        local_id = positive_int(row.get("catalog_hotel_id"))
        candidates = row.get("candidates")
        selected = [candidate for candidate in candidates if positive_int(candidate.get("id")) == local_id] \
            if isinstance(candidates, list) else []
        names = [norm(row.get("name")), norm(row.get("alternate_name"))]
        if (len(selected) != 1 or not any(name and name == norm(selected[0].get("name")) for name in names)
                or country(row.get("country")) != country(selected[0].get("country"))
                or not (town_matches(row.get("town"), selected[0].get("town"))
                        or town_matches(row.get("town"), selected[0].get("region")))):
            raise ValueError("strict invariant failed")
        canonical_name = next(name for name in names if name and name == norm(selected[0].get("name")))
        # Short one-word names are exact but not distinctive enough to import
        # without a human check (for example Plaza, Nika or Grand).
        if len(canonical_name.split()) == 1 and len(canonical_name) < 8:
            deferred_short += 1
            continue
        if external_id in seen:
            raise ValueError("duplicate external identity")
        seen[external_id] = local_id
        mappings.append((external_id, local_id))
    mappings.sort()
    output = io.StringIO(newline="")
    writer = csv.writer(output, lineterminator="\n")
    writer.writerow(["anex_xml_id", "catalog_hotel_id"])
    writer.writerows(mappings)
    csv_bytes = output.getvalue().encode("utf-8")
    generated = str(report.get("generated_at", "")).replace("+00:00", "Z")
    dt.datetime.strptime(generated, "%Y-%m-%dT%H:%M:%S.%fZ")
    manifest = {
        "schema_version": 1,
        "scope": "preview",
        "provider": "anex_xml",
        "catalog_id_field": "catalog_hotels.id",
        "matching_rule": "exact_name_country_town_v1_distinctive",
        "evidence": {"checked_at": generated, "source_sha": evidence["source_sha"],
                     "run_url": evidence["run_url"], "artifact_url": evidence["artifact_url"],
                     "artifact_digest": evidence["artifact_digest"],
                     "reference_stamp": report.get("reference_stamp")},
        "mapping_file": "anex-xml-exact-identities.csv",
        "mapping_sha256": hashlib.sha256(csv_bytes).hexdigest(),
        "mapping_count": len(mappings),
        "strict_source_count": len(strict),
        "deferred_short_name_count": deferred_short,
    }
    if len(mappings) + deferred_short != len(strict):
        raise ValueError("count mismatch")
    return manifest, csv_bytes


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("report", type=Path)
    parser.add_argument("output_directory", type=Path)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--run-url", required=True)
    parser.add_argument("--artifact-url", required=True)
    parser.add_argument("--artifact-digest", required=True)
    args = parser.parse_args()
    report = json.loads(args.report.read_text(encoding="utf-8"))
    manifest, csv_bytes = build_registry(report, vars(args))
    args.output_directory.mkdir(parents=True, exist_ok=True)
    (args.output_directory / manifest["mapping_file"]).write_bytes(csv_bytes)
    (args.output_directory / "anex-xml-exact-registry.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"ok": True, "mapping_count": manifest["mapping_count"],
                      "deferred_short_name_count": manifest["deferred_short_name_count"]}))


if __name__ == "__main__":
    main()

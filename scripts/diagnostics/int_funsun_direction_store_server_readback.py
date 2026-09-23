#!/usr/bin/env python3
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import subprocess
import urllib.request

REPO = "pyatkoff/poisk-turov-test"
FEATURE = "feature/anex-search-adapter-20260907"
OWNER_ID = 226193297
ISSUE = 3419
PREFIX = "/run-int-direction-store-readback-v1 "
SHA_RE = re.compile(r"\A[a-f0-9]{40}\Z")
OP_RE = re.compile(r"\Aint-andromeda-funsun-direction-store-readback-[a-z0-9-]{8,96}-v[1-9][0-9]*\Z")

REMOTE_PHP = r"""<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors','0');
ini_set('log_errors','0');

function idsr_fail(string $reason): never { throw new RuntimeException($reason); }
function idsr_digest(mixed $value): ?string {
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1 ? $value : null;
}
function idsr_money(mixed $value): ?string {
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value) === 1 ? $value : null;
}
function idsr_rate(mixed $value): ?string {
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $value) === 1 ? $value : null;
}
try {
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') idsr_fail('home_missing');
    $root = rtrim($home, '/') . '/www/anytoour.ru';
    $configPath = $root . '/_preview/search3-anex-candidate/.andromeda-private.php';
    if (!is_file($configPath) || is_link($configPath) || filesize($configPath) > 65536) {
        idsr_fail('private_config_missing');
    }
    $config = require $configPath;
    $catalogPath = is_array($config) ? ($config['catalog_path'] ?? null) : null;
    if (!is_string($catalogPath) || $catalogPath === '') idsr_fail('catalog_path_missing');
    $directory = dirname($catalogPath) . '/searches';
    if (!is_dir($directory) || is_link($directory) || basename($directory) !== 'searches') {
        idsr_fail('store_root_invalid');
    }

    $expectedDirection = [
        'operator_family' => 'fun_and_sun',
        'market' => 'departure:1',
        'destination' => 'country:4',
    ];
    $files = glob($directory . '/operator-fuel-rule-v2-*.json', GLOB_NOSORT);
    if ($files === false || count($files) > 512) idsr_fail('store_inventory_invalid');
    sort($files, SORT_STRING);
    $targetFiles = [];
    $now = time();

    foreach ($files as $path) {
        if (!is_file($path) || is_link($path)) continue;
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 262144) continue;
        $base = basename($path);
        if (preg_match('/\Aoperator-fuel-rule-v2-([a-f0-9]{64})\.json\z/D', $base, $match) !== 1) continue;
        try {
            $value = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            continue;
        }
        if (!is_array($value) || ($value['direction'] ?? null) !== $expectedDirection) continue;
        if (($value['version'] ?? null) !== 2 || ($value['direction_sha256'] ?? null) !== $match[1]
            || !is_array($value['observations'] ?? null) || !array_is_list($value['observations'])) {
            idsr_fail('target_envelope_invalid');
        }

        $offers = [];
        $evidence = [];
        $facts = [];
        $rates = [];
        $freshRates = [];
        $freshExchangeCount = 0;
        $latestExchangeObservedAt = 0;
        $latestExchangeExpiresAt = 0;

        foreach ($value['observations'] as $row) {
            if (!is_array($row)
                || ($row['schema_version'] ?? null) !== 2
                || ($row['operator_family'] ?? null) !== 'fun_and_sun'
                || ($row['direction'] ?? null) !== $expectedDirection
                || !in_array($row['provider'] ?? null, ['andromeda','tourvisor'], true)
                || ($row['kind'] ?? null) !== 'fuel') {
                idsr_fail('target_observation_invalid');
            }
            $amount = idsr_money($row['amount'] ?? null);
            $currency = $row['currency'] ?? null;
            $unit = $row['unit'] ?? null;
            $relation = $row['base_relation'] ?? null;
            $offer = idsr_digest($row['offer_ref_digest'] ?? null);
            $ev = idsr_digest($row['evidence_sha256'] ?? null);
            $sourceResponse = idsr_digest($row['source_response_sha256'] ?? null);
            $observed = $row['observed_at'] ?? null;
            $expires = $row['expires_at'] ?? null;
            if ($amount === null || !is_string($currency) || preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1
                || !in_array($unit, ['party_roundtrip','per_person_one_way'], true)
                || !in_array($relation, ['included','excluded'], true)
                || $offer === null || $ev === null || $sourceResponse === null
                || !is_int($observed) || !is_int($expires) || $observed < 1 || $expires <= $observed) {
                idsr_fail('target_observation_shape');
            }
            $offers[$offer] = true;
            $evidence[$ev] = true;
            $facts[$amount . '|' . $currency . '|' . $unit . '|' . $relation] = true;

            $exchange = $row['exchange'] ?? null;
            if (is_array($exchange)) {
                $rate = idsr_rate($exchange['rate'] ?? null);
                $exchangeEvidence = idsr_digest($exchange['evidence_sha256'] ?? null);
                $exchangeObserved = $exchange['observed_at'] ?? null;
                $exchangeExpires = $exchange['expires_at'] ?? null;
                if (($exchange['from'] ?? null) !== $currency || ($exchange['to'] ?? null) !== 'RUB'
                    || $rate === null || $exchangeEvidence === null
                    || !is_int($exchangeObserved) || !is_int($exchangeExpires)
                    || $exchangeObserved < $observed || $exchangeExpires > $expires
                    || $exchangeExpires <= $exchangeObserved) {
                    idsr_fail('target_exchange_invalid');
                }
                $rates[$rate] = true;
                if ($exchangeObserved <= $now && $exchangeExpires > $now) {
                    $freshRates[$rate] = true;
                    ++$freshExchangeCount;
                    if ($exchangeObserved >= $latestExchangeObservedAt) {
                        $latestExchangeObservedAt = $exchangeObserved;
                        $latestExchangeExpiresAt = $exchangeExpires;
                    }
                }
            }
        }
        ksort($offers, SORT_STRING);
        ksort($evidence, SORT_STRING);
        ksort($facts, SORT_STRING);
        ksort($rates, SORT_STRING);
        ksort($freshRates, SORT_STRING);
        $targetFiles[] = [
            'direction_sha256' => $match[1],
            'store_sha256' => hash_file('sha256', $path),
            'observation_count' => count($value['observations']),
            'independent_offer_count' => count($offers),
            'independent_evidence_count' => count($evidence),
            'offer_set_sha256' => hash('sha256', implode("\n", array_keys($offers))),
            'evidence_set_sha256' => hash('sha256', implode("\n", array_keys($evidence))),
            'facts' => array_keys($facts),
            'exchange_rates' => array_keys($rates),
            'fresh_exchange_rates' => array_keys($freshRates),
            'fresh_exchange_count' => $freshExchangeCount,
            'latest_exchange_observed_at' => $latestExchangeObservedAt ?: null,
            'latest_exchange_expires_at' => $latestExchangeExpiresAt ?: null,
        ];
    }

    $status = 'absent';
    if (count($targetFiles) === 1) {
        $row = $targetFiles[0];
        $confirmed = $row['observation_count'] >= 2
            && $row['independent_offer_count'] >= 2
            && $row['independent_evidence_count'] >= 2
            && $row['facts'] === ['140.00|EUR|per_person_one_way|excluded']
            && $row['fresh_exchange_rates'] === ['102.7']
            && $row['fresh_exchange_count'] >= 2;
        $status = $confirmed ? 'confirmed' : 'insufficient';
    } elseif (count($targetFiles) > 1) {
        $status = 'ambiguous';
    }

    echo json_encode([
        'schema_version' => 1,
        'source' => 'int-funsun-direction-store-server-readback-v1',
        'status' => $status,
        'direction' => $expectedDirection,
        'target_store_count' => count($targetFiles),
        'target_stores' => $targetFiles,
        'supplier_calls' => 0,
        'database_reads' => 0,
        'database_writes' => 0,
        'store_writes' => 0,
        'runtime_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'final_price_verified' => false,
        'server_time' => $now,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    $reason = $error->getMessage();
    if (!is_string($reason) || preg_match('/\A[A-Za-z0-9_.:-]{1,96}\z/D', $reason) !== 1) {
        $reason = 'readback_failed';
    }
    echo json_encode([
        'schema_version' => 1,
        'source' => 'int-funsun-direction-store-server-readback-v1',
        'status' => 'failed',
        'reason' => $reason,
        'supplier_calls' => 0,
        'database_reads' => 0,
        'database_writes' => 0,
        'store_writes' => 0,
        'runtime_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'final_price_verified' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}
"""


def need(condition: bool, reason: str) -> None:
    if not condition:
        raise RuntimeError(reason)


def parse_command(body: str) -> dict[str, str]:
    need(isinstance(body, str) and body.startswith(PREFIX), "command_prefix")
    parts = body.strip().split()
    need(len(parts) == 3 and parts[0] == PREFIX.strip(), "command_shape")
    source_sha, operation_id = parts[1], parts[2]
    need(SHA_RE.fullmatch(source_sha) is not None, "source_sha")
    need(OP_RE.fullmatch(operation_id) is not None, "operation_id")
    return {"source_sha": source_sha, "operation_id": operation_id}


def api_get(path: str, token: str) -> dict:
    request = urllib.request.Request(
        "https://api.github.com/repos/" + REPO + path,
        headers={
            "Authorization": "Bearer " + token,
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        value = json.load(response)
    need(isinstance(value, dict), "github_shape")
    return value


def checked_command() -> dict[str, str]:
    token = os.environ.get("GH_TOKEN", "")
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(bool(token) and event_path.is_file(), "github_context")
    event = json.loads(event_path.read_text())
    need(
        isinstance(event, dict)
        and event.get("issue", {}).get("number") == ISSUE
        and not event.get("issue", {}).get("pull_request"),
        "issue",
    )
    comment = event.get("comment", {})
    need(
        isinstance(comment, dict)
        and comment.get("user", {}).get("id") == OWNER_ID
        and comment.get("author_association") == "OWNER",
        "owner",
    )
    comment_id = comment.get("id")
    need(isinstance(comment_id, int) and comment_id > 0, "comment_id")
    fresh = api_get("/issues/comments/" + str(comment_id), token)
    body = comment.get("body", "")
    need(
        fresh.get("body") == body
        and fresh.get("user", {}).get("id") == OWNER_ID
        and fresh.get("author_association") == "OWNER",
        "comment_changed",
    )
    command = parse_command(body)
    main_sha = api_get("/git/ref/heads/main", token).get("object", {}).get("sha")
    feature_sha = api_get("/git/ref/heads/" + FEATURE, token).get("object", {}).get("sha")
    need(main_sha == os.environ.get("GITHUB_SHA"), "main_changed")
    need(feature_sha == command["source_sha"], "feature_changed")
    return command


def validate_remote(data: dict) -> dict:
    need(isinstance(data, dict), "remote_shape")
    need(data.get("schema_version") == 1, "remote_schema")
    need(data.get("source") == "int-funsun-direction-store-server-readback-v1", "remote_source")
    status = data.get("status")
    need(status in {"confirmed", "absent", "insufficient", "ambiguous", "failed"}, "remote_status")
    for key in (
        "supplier_calls",
        "database_reads",
        "database_writes",
        "store_writes",
        "runtime_writes",
        "booking_calls",
        "lead_calls",
    ):
        need(data.get(key) == 0, "remote_authority_" + key)
    need(data.get("final_price_verified") is False, "remote_final_verified")
    if status == "failed":
        need(isinstance(data.get("reason"), str) and len(data["reason"]) <= 96, "remote_reason")
        return data

    expected_direction = {
        "operator_family": "fun_and_sun",
        "market": "departure:1",
        "destination": "country:4",
    }
    need(data.get("direction") == expected_direction, "remote_direction")
    stores = data.get("target_stores")
    need(isinstance(stores, list) and len(stores) <= 1, "remote_store_count")
    need(data.get("target_store_count") == len(stores), "remote_store_count")
    for row in stores:
        need(isinstance(row, dict), "remote_store_shape")
        for digest_key in ("direction_sha256", "store_sha256", "offer_set_sha256", "evidence_set_sha256"):
            need(
                isinstance(row.get(digest_key), str)
                and re.fullmatch(r"[a-f0-9]{64}", row[digest_key]) is not None,
                "remote_digest_" + digest_key,
            )
        for count_key in (
            "observation_count",
            "independent_offer_count",
            "independent_evidence_count",
            "fresh_exchange_count",
        ):
            need(isinstance(row.get(count_key), int) and 0 <= row[count_key] <= 128, "remote_count_" + count_key)
        need(
            isinstance(row.get("facts"), list)
            and all(isinstance(x, str) and len(x) <= 96 for x in row["facts"]),
            "remote_facts",
        )
        need(
            isinstance(row.get("exchange_rates"), list)
            and isinstance(row.get("fresh_exchange_rates"), list)
            and all(isinstance(x, str) and len(x) <= 32 for x in row["exchange_rates"] + row["fresh_exchange_rates"]),
            "remote_rates",
        )
    if status == "confirmed":
        need(len(stores) == 1, "remote_confirmed_store")
        row = stores[0]
        need(row["observation_count"] >= 2, "remote_confirmed_observations")
        need(row["independent_offer_count"] >= 2, "remote_confirmed_offers")
        need(row["independent_evidence_count"] >= 2, "remote_confirmed_evidence")
        need(row["facts"] == ["140.00|EUR|per_person_one_way|excluded"], "remote_confirmed_fact")
        need(row["fresh_exchange_rates"] == ["102.7"], "remote_confirmed_fx")
        need(row["fresh_exchange_count"] >= 2, "remote_confirmed_fx_count")
    return data


def ssh_options(key: Path, known: Path) -> list[str]:
    return [
        "-T",
        "-i",
        str(key),
        "-o",
        "IdentitiesOnly=yes",
        "-o",
        "BatchMode=yes",
        "-o",
        "StrictHostKeyChecking=yes",
        "-o",
        "UserKnownHostsFile=" + str(known),
        "-o",
        "GlobalKnownHostsFile=/dev/null",
        "-o",
        "ConnectTimeout=15",
        "-o",
        "ServerAliveInterval=15",
        "-o",
        "ServerAliveCountMax=3",
        "-o",
        "LogLevel=ERROR",
    ]


def execute(command: dict[str, str]) -> dict:
    host = os.environ.get("INT_SSH_HOST", "").strip()
    user = os.environ.get("INT_SSH_USER", "").strip()
    raw_key = os.environ.get("INT_SSH_KEY", "").strip()
    need(bool(host and user and raw_key), "ssh_config")
    need(
        re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]*", host) is not None
        and re.fullmatch(r"[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}", user) is not None,
        "ssh_identity",
    )
    output = Path(os.environ["RUNNER_TEMP"]) / "int-direction-store-readback"
    output.mkdir(mode=0o700, exist_ok=True)
    key = output / "key"
    known = output / "known_hosts"
    key.write_text(raw_key.rstrip() + "\n")
    key.chmod(0o600)
    subprocess.run(
        ["ssh-keygen", "-y", "-f", str(key)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.PIPE,
        check=True,
        timeout=10,
    )
    scan = subprocess.run(
        ["ssh-keyscan", "-T", "15", "-t", "ed25519", host],
        capture_output=True,
        check=True,
        timeout=20,
    ).stdout
    need(bool(scan), "ssh_hostkey")
    known.write_bytes(scan)
    known.chmod(0o600)
    run = subprocess.run(
        ["ssh", *ssh_options(key, known), user + "@" + host, "php -d display_errors=0 -d log_errors=0"],
        input=REMOTE_PHP.encode(),
        capture_output=True,
        timeout=90,
    )
    need(run.returncode == 0, "remote_exit")
    need(not run.stderr.strip(), "remote_stderr")
    try:
        remote = json.loads(run.stdout.decode().strip())
    except Exception as exc:
        raise RuntimeError("remote_json") from exc
    remote = validate_remote(remote)
    result = {
        "schema_version": 1,
        "source": "int-funsun-direction-store-control-readback-v1",
        "operation_id": command["operation_id"],
        "source_sha": command["source_sha"],
        "control_sha": os.environ.get("GITHUB_SHA"),
        "readback": remote,
        "supplier_calls": 0,
        "database_reads": 0,
        "database_writes": 0,
        "store_writes": 0,
        "runtime_writes": 0,
        "booking_calls": 0,
        "lead_calls": 0,
        "production_unchanged": True,
    }
    path = output / "result.json"
    path.write_text(json.dumps(result, sort_keys=True, separators=(",", ":")))
    path.chmod(0o600)
    return result


def main() -> int:
    command = checked_command()
    result = execute(command)
    print(json.dumps(result, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

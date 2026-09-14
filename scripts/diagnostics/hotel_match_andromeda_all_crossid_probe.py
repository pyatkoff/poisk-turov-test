#!/usr/bin/env python3
"""MATCH #1971: immutable read-only Andromeda `all` evidence capture.

The supplier explicitly advised using method `all` for Andromeda↔operator hotel
correspondence. This probe reuses the installed INT client unchanged, captures two
high-value core8 dictionaries (Egypt + Turkey), and preserves every HOTEL row for
later CURRENT reconciliation. It never writes mappings/DB/booking/lead data.
"""
from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys

OP_RE = re.compile(r"hotel-match-andromeda-all-crossid-1971-20260914-v\d+")
SHA_RE = re.compile(r"[0-9a-f]{40}")
INTERESTING = re.compile(r"(?:original|operator|anex|hotel.?key|hotel.?code|image|photo|media|url|link)", re.I)

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$supplierCalls=0; $phase='preflight'; $captures=[]; $transportError=null;
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
    $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('WRONG_PROJECT');
    $target=$root.'/_preview/search3-anex-candidate';
    $clientPath=$target.'/app/integrations/andromeda-client.php';
    $configPath=$target.'/.andromeda-private.php';
    if(realpath($clientPath)!==$clientPath||!is_file($clientPath)||realpath($configPath)!==$configPath||!is_file($configPath))
        throw new RuntimeException('RUNTIME_MISSING');
    require_once $clientPath; $config=require $configPath;
    if(!is_array($config)||($config['enabled']??null)!==true||!is_string($config['username']??null)||$config['username']===''||!is_string($config['password']??null)||$config['password']==='')
        throw new RuntimeException('PRIVATE_CONFIG_MISSING');
    $attempts=0; $lastStarted=0.0;
    $transport=static function(string $url,array $ignored=[])use(&$attempts,&$lastStarted,&$transportError):array{
        if(strpos($url,'https://gateway.samo.ru/api/?')!==0||strlen($url)>16384||preg_match('/[\\x00-\\x20\\x7f#]/',$url))
            throw new RuntimeException('PROBE_ENDPOINT_REJECTED');
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        if(($query['version']??null)!=='1.01'||!in_array($query['action']??null,['login','all'],true))
            throw new RuntimeException('PROBE_ACTION_REJECTED');
        if($attempts>=3)throw new RuntimeException('PROBE_REQUEST_BUDGET');
        if(!function_exists('curl_init')){$transportError='local_curl_missing';throw new RuntimeException('PROBE_CURL_REQUIRED');}
        $wait=1.05-(microtime(true)-$lastStarted); if($wait>0)usleep((int)ceil($wait*1000000));
        ++$attempts; $lastStarted=microtime(true); $h=curl_init(); if($h===false)throw new RuntimeException('PROBE_CURL_INIT_FAILED');
        $body=''; $oversize=false;
        try {
            $ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HEADER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],
                CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$body,&$oversize):int{
                    if(strlen($body)+strlen($chunk)>2097152){$oversize=true;return 0;} $body.=$chunk; return strlen($chunk);
                }]);
            if(!$ok)throw new RuntimeException('PROBE_CURL_SETUP_FAILED');
            if(curl_exec($h)===false){$transportError=$oversize?'response_too_large':'network_transport';throw new RuntimeException($oversize?'ANDROMEDA_RESPONSE_TOO_LARGE':'ANDROMEDA_NETWORK_TRANSPORT_FAILURE');}
            return ['status'=>(int)curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>$body];
        } finally { curl_close($h); }
    };
    $client=new AnyTourAndromedaClient($transport,true);
    $phase='login'; $supplierCalls=1; $client->login($config['username'],$config['password']);
    foreach ([['label'=>'egypt','townfrom'=>1,'state'=>3],['label'=>'turkey','townfrom'=>1,'state'=>6]] as $scope) {
        $phase='all_'.$scope['label']; ++$supplierCalls;
        $payload=$client->catalog('all',['TOWNFROMINC'=>$scope['townfrom'],'STATEINC'=>$scope['state']]);
        $captures[$scope['label']]=['params'=>['TOWNFROMINC'=>$scope['townfrom'],'STATEINC'=>$scope['state']],'payload'=>$payload];
    }
    $privateJson=json_encode($captures,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    foreach([$config['username'],$config['password'],rawurlencode($config['username']),rawurlencode($config['password'])] as $secret)
        if($secret!==''&&strpos($privateJson,$secret)!==false)throw new RuntimeException('CREDENTIAL_ECHO');
    $safe=['status'=>'captured','phase'=>'complete','supplier_calls'=>$supplierCalls,'database_writes'=>0,'mapping_writes'=>0,
        'booking_calls'=>0,'lead_calls'=>0,'tourvisor_calls'=>0,'transport_error'=>null,'captures'=>$captures];
} catch(Throwable $e) {
    $token=preg_match('/^[A-Z0-9_]{1,100}$/D',$e->getMessage())?$e->getMessage():'ANDROMEDA_ALL_PROBE_FAILED';
    $safe=['status'=>'unknown','phase'=>$phase,'error'=>$token,'supplier_calls'=>$supplierCalls,'database_writes'=>0,'mapping_writes'=>0,
        'booking_calls'=>0,'lead_calls'=>0,'tourvisor_calls'=>0,'transport_error'=>$transportError,'captures'=>$captures];
}
while(ob_get_level())ob_end_clean(); echo json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
'''


def canonical(value) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode()


def exclusive(path: Path, value) -> str:
    raw = canonical(value)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw); fh.flush(); os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback_failed")
    return hashlib.sha256(raw).hexdigest()


def walk(value, prefix=""):
    if isinstance(value, dict):
        for key, child in value.items():
            path = f"{prefix}.{key}" if prefix else str(key)
            yield from walk(child, path)
    elif isinstance(value, list):
        for index, child in enumerate(value):
            yield from walk(child, f"{prefix}[{index}]")
    else:
        yield prefix, value


def summarize(captures: dict) -> dict:
    scopes = {}
    evidence = []
    for label, capture in captures.items():
        payload = capture.get("payload") if isinstance(capture, dict) else None
        hotels = payload.get("HOTELS") if isinstance(payload, dict) else None
        if not isinstance(hotels, list):
            scopes[label] = {"hotel_count": None, "row_keysets": {}, "interesting_rows": 0}
            continue
        keysets = {}
        interesting = 0
        for row in hotels:
            if not isinstance(row, dict):
                continue
            sig = ",".join(sorted(str(k) for k in row))
            keysets[sig] = keysets.get(sig, 0) + 1
            hits = []
            for path, value in walk(row):
                text = str(value) if isinstance(value, (str, int, float)) else ""
                if INTERESTING.search(path) or re.search(r"(?:anextour|anex|\\.(?:jpg|jpeg|png|webp)(?:\\?|$))", text, re.I):
                    if len(text) > 500:
                        text = text[:500]
                    hits.append({"path": path, "value": text})
            # id/name themselves are not cross-ID evidence.
            hits = [h for h in hits if h["path"].lower() not in {"id", "name"}]
            if hits:
                interesting += 1
                evidence.append({"scope": label, "andromeda_hotel_id": str(row.get("id", "")), "name": row.get("name"), "signals": hits[:30]})
        scopes[label] = {"hotel_count": len(hotels), "row_keysets": keysets, "interesting_rows": interesting,
                         "payload_counts": {k: len(v) if isinstance(v, list) else None for k, v in payload.items()}}
    return {"scopes": scopes, "explicit_evidence_rows": len(evidence), "evidence": evidence}


def self_test() -> int:
    sample = {"x": {"payload": {"HOTELS": [
        {"id": 10, "name": "A", "original": {"hotelKey": 55}, "image": "https://x/5.55.10.jpg"},
        {"id": 11, "name": "B"}], "OPERATORS": []}}}
    out = summarize(sample)
    assert out["scopes"]["x"]["hotel_count"] == 2
    assert out["explicit_evidence_rows"] == 1
    assert out["evidence"][0]["andromeda_hotel_id"] == "10"
    print("andromeda all cross-id probe self-test: PASS")
    return 0


def main() -> int:
    if "--self-test" in sys.argv:
        return self_test()
    op = os.environ.get("OPERATION_ID", "")
    source_sha = os.environ.get("MATCH_SOURCE_SHA", "")
    if not OP_RE.fullmatch(op) or not SHA_RE.fullmatch(source_sha):
        raise RuntimeError("immutable_operation_identity_required")
    root = Path.cwd().resolve()
    if root.name != "anytoour.ru":
        raise RuntimeError("root_guard")
    base = Path.home() / ".anytoour-match" / "operations"
    if not base.is_dir():
        raise RuntimeError("operations_root_missing")
    out = base / op
    out.mkdir(mode=0o700, exist_ok=False)
    reservation = {"operation_id": op, "source_sha": source_sha, "state": "reserved_before_supplier_access",
                   "read_only": True, "supplier_calls": 0, "database_writes": 0, "mapping_writes": 0, "tourvisor_calls": 0, "no_replay": True}
    exclusive(out / "reservation.json", reservation)

    proc = subprocess.run(["php", "-d", "display_errors=0", "-r", PHP], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=120)
    try:
        result = json.loads(proc.stdout)
    except Exception as exc:
        unknown = {"operation_id": op, "status": "unknown", "stage": "supplier_result_parse", "remote_exit": proc.returncode,
                   "database_writes": 0, "mapping_writes": 0, "tourvisor_calls": 0, "no_replay": True}
        exclusive(out / "result.json", unknown)
        exclusive(out / "receipt.json", {**unknown, "readback_verified": True, "result_sha256": hashlib.sha256(canonical(unknown)).hexdigest()})
        raise RuntimeError("supplier_result_unknown_no_replay") from exc

    captures = result.pop("captures", {}) if isinstance(result, dict) else {}
    if not isinstance(result, dict) or not isinstance(captures, dict):
        raise RuntimeError("supplier_result_invalid")
    capture_sha = exclusive(out / "capture.json", captures)
    summary = summarize(captures)
    summary_sha = exclusive(out / "evidence.json", summary)
    final = {**result, "operation_id": op, "source_sha": source_sha, "capture_sha256": capture_sha, "evidence_sha256": summary_sha,
             "evidence_summary": {"scopes": summary["scopes"], "explicit_evidence_rows": summary["explicit_evidence_rows"]}, "no_replay": True}
    final_sha = exclusive(out / "result.json", final)
    receipt = {"operation_id": op, "source_sha": source_sha, "status": final.get("status"), "result_sha256": final_sha,
               "capture_sha256": capture_sha, "evidence_sha256": summary_sha, "readback_verified": True, "no_replay": True,
               "database_writes": 0, "mapping_writes": 0, "tourvisor_calls": 0}
    exclusive(out / "receipt.json", receipt)
    print(json.dumps({"status": final.get("status"), "phase": final.get("phase"), "supplier_calls": final.get("supplier_calls"),
                      "evidence_summary": final["evidence_summary"], "operation_id": op, "no_replay": True}, ensure_ascii=False, sort_keys=True))
    return 0 if final.get("status") == "captured" else 2


if __name__ == "__main__":
    raise SystemExit(main())

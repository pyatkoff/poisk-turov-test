#!/usr/bin/env python3
from __future__ import annotations
import json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts" / "diagnostics"))
import anex_search3_three_source_price as transport

EXPERIMENT = "int_anex_gds_apd_probe_20260917_v1"
SPEC = {
    "experiment_id": EXPERIMENT,
    "tour": 7385,
    "dateBeg": "2026-10-12",
    "nights": 7,
    "currency": 3,
    "flight_type_observed": "GDS",
}

PHP = r'''
$inputRaw=file_get_contents("php://stdin",false,null,0,4097);
$input=is_string($inputRaw)?json_decode($inputRaw,true,16,JSON_THROW_ON_ERROR):null;
$expected=["experiment_id"=>"int_anex_gds_apd_probe_20260917_v1","tour"=>7385,"dateBeg"=>"2026-10-12","nights"=>7,"currency"=>3,"flight_type_observed"=>"GDS"];
$out=["schema_version"=>1,"experiment_id"=>"int_anex_gds_apd_probe_20260917_v1","status"=>"blocked","automatic_retry"=>false,"supplier_replay_allowed"=>false,"database_writes"=>0,"booking_calls"=>0,"lead_calls"=>0];
try{
  if(!is_array($input)||$input!==$expected) throw new RuntimeException("GDS_APD_INPUT");
  $home=(string)getenv("HOME"); $root=realpath($home."/www/anytoour.ru");
  if(!$root||realpath((string)getcwd())!==$root) throw new RuntimeException("GDS_APD_RUNTIME");
  $ledger=$home."/.anytour-ops/int_anex_gds_apd_probe_20260917_v1";
  if(file_exists($ledger)) throw new RuntimeException("GDS_APD_NO_REPLAY");
  if(!is_dir($home."/.anytour-ops")&&!mkdir($home."/.anytour-ops",0700,true)) throw new RuntimeException("GDS_APD_LEDGER");
  if(!mkdir($ledger,0700)) throw new RuntimeException("GDS_APD_LEDGER");
  file_put_contents($ledger."/state","reserved\n",LOCK_EX);
  require_once $home."/.anytoour-anex/search3-preview.php"; require_once $root."/config.php";
  $token=defined("ANEX_B2B_TOKEN")&&is_string(ANEX_B2B_TOKEN)?trim(ANEX_B2B_TOKEN):"";
  if($token==="") throw new RuntimeException("GDS_APD_TOKEN");
  $clientFile=$root."/_preview/search3-anex-candidate/app/integrations/anex-additional-prices-client.php";
  if(!is_file($clientFile)) $clientFile=$root."/app/integrations/anex-additional-prices-client.php";
  if(!is_file($clientFile)) throw new RuntimeException("GDS_APD_CLIENT");
  require_once $clientFile;
  $cache=$ledger."/cache";
  $client=new AnyTourAnexAdditionalPricesClient($token,null,$cache);
  $criteria=["page"=>1,"pageSize"=>10,"tour"=>7385,"dateBeg"=>"2026-10-12","nights"=>7,"currency"=>3];
  $payload=$client->additionalPricesDaily($criteria);
  $rows=is_array($payload["data"]??null)?$payload["data"]:[];
  $out=[
    "schema_version"=>1,"experiment_id"=>"int_anex_gds_apd_probe_20260917_v1","status"=>"completed",
    "observed_at"=>gmdate("c"),"criteria"=>$criteria,"flight_type_observed"=>"GDS",
    "requests_made"=>$client->requestsMade(),"request_diagnostics"=>$client->lastRequestDiagnostics(),
    "total_count"=>$payload["totalCount"]??null,"row_count"=>count($rows),"rows"=>$rows,
    "automatic_retry"=>false,"supplier_replay_allowed"=>false,"database_writes"=>0,"booking_calls"=>0,"lead_calls"=>0
  ];
  file_put_contents($ledger."/state","completed\n",LOCK_EX);
}catch(Throwable $e){
  $m=$e->getMessage();
  $out["reason"]=is_string($m)&&preg_match("/\\A(?:GDS_APD|ANEX_B2B)_[A-Z0-9_]{1,80}\\z/D",$m)?$m:"GDS_APD_UNCONFIRMED";
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit(($out["status"]??null)==="completed"?0:1);
'''

def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit("usage: int_anex_gds_apd_probe_v1.py OUTPUT")
    out=Path(sys.argv[1])
    try:
        value=transport.ssh_php_no_mux("declare(strict_types=1);\n"+PHP, SPEC, maximum_bytes=65536)
    except Exception as exc:
        value={"schema_version":1,"experiment_id":EXPERIMENT,"status":"unconfirmed","error_kind":type(exc).__name__,"automatic_retry":False,"supplier_replay_requested":False}
    out.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+"\n")
    print(json.dumps(value,ensure_ascii=False,sort_keys=True))
    return 0 if value.get("status")=="completed" else 1

if __name__=="__main__":
    raise SystemExit(main())

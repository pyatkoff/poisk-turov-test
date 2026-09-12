#!/usr/bin/env python3
"""Inventory-backed ANEX B2B parity and retained-evidence program follow-ups."""
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_additional_parity_20260912_v1'
PROGRAM2637_EXPERIMENT='anex_additional_program2637_20260912_v1'
PROGRAM1797_EXPERIMENT='anex_additional_program1797_20260913_v1'
RETAINED_ARTIFACT_ID=10304537645
RETAINED_RESULT_SHA256='7b9a8237739b9d1334f1cebfa91fa89b6c4321f029714d76dabca710b2cae589'
CONCRETE1797_ARTIFACT_ID=10306073917
CONCRETE1797_RESULT_SHA256='e96064806391bbdb5ee63cc381d8aaa9a8b2e4132f4a07c4ecd2690b9e2e4909'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB'}
PROGRAM2637_CONTEXT={'page':1,'pageSize':10,'tour':2637,'dateBeg':'2026-10-12','nights':7,'currency':3}
PROGRAM1797_CONTEXT={'page':1,'pageSize':10,'tour':1797,'dateBeg':'2026-10-12','nights':7,'currency':3}


def php_body(path: Path, require_strict=True) -> str:
    text=path.read_text()
    if not text.startswith('<?php\n'):
        raise ValueError('php_header_invalid')
    body=text[len('<?php\n'):]
    strict='declare(strict_types=1);\n'
    if require_strict:
        if not body.startswith(strict):
            raise ValueError('php_strict_header_invalid')
        body=body[len(strict):]
    elif body.startswith(strict):
        body=body[len(strict):]
    return body


def source(program_followup=False, program1797=False) -> str:
    here=Path(__file__).resolve().parent
    paired=php_body(here/'anex_search3_paired_runner.php',require_strict=False)
    client=php_body(here.parent.parent/'app/integrations/anex-additional-prices-client.php')
    parity=php_body(here/'anex_additional_parity_v3.php')
    prefix=("declare(strict_types=1);\n"
            "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"
            "define('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY', true);\n"
            +paired+'\n'+client+'\n'+parity+'\n')
    if program1797:
        runner=r'''
$experiment='anex_additional_program1797_20260913_v1';
$out=['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'blocked','automatic_retry'=>false,
    'supplier_replay_allowed'=>false,'direct_anex_requests'=>0,'tourvisor_requests'=>0,'additional_prices_requests'=>0,
    'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'additional_prices'=>null];
$lock=null;$reserved=false;$path=null;$client1797=null;$secrets=[];$started=microtime(true);
try {
    $raw=file_get_contents('php://stdin',false,null,0,4097);
    $input=json_decode((string)$raw,true,8,JSON_THROW_ON_ERROR);
    $expected=['experiment_id'=>$experiment,'country'=>'Turkey','date'=>'2026-10-12','nights'=>7,'adults'=>2,
        'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
    if(!is_array($input)||$input!==$expected)throw new RuntimeException('ADDITIONAL_PARITY_INVALID_INPUT');
    $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
    if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('ADDITIONAL_PARITY_RUNTIME');
    require_once $home.'/.anytoour-anex/search3-preview.php';require_once $root.'/config.php';
    if(!defined('ANEX_B2B_TOKEN')||!is_string(ANEX_B2B_TOKEN)||trim(ANEX_B2B_TOKEN)==='')throw new RuntimeException('ADDITIONAL_PARITY_B2B_TOKEN');
    $secrets[] = ANEX_B2B_TOKEN;
    $dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_DIR');
    $path=$dir.'/'.$experiment.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('ADDITIONAL_PARITY_LOCK');
    if(is_file($path)){
        $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        if(($prior['status']??null)==='completed'&&is_array($prior['result']??null)){echo json_encode(array_replace($prior['result'],['reused'=>true]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";exit(0);}
        throw new RuntimeException('ADDITIONAL_PARITY_NOT_REPLAYABLE');
    }
    anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'reserved','reserved_at'=>gmdate('c')]);$reserved=true;
    $out['additional_request_context']=['page'=>1,'pageSize'=>10,'tour'=>1797,'dateBeg'=>'2026-10-12','nights'=>7,'currency'=>3];
    $client1797=new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN);
    $out['additional_prices']=$client1797->additionalPricesDaily($out['additional_request_context']);
    $out['additional_prices_requests']=$client1797->requestsMade();$out['status']='completed';
    $out['supplier_effect']='read_only_additional_from_retained_concrete_completed';$out['reused']=false;
    anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]);$reserved=false;
}catch(Throwable $e){
    $code=$e->getMessage();$safe=preg_match('/\A(?:ADDITIONAL_PARITY|ANEX_B2B)_[A-Z0-9_]{1,90}\z/D',$code)?$code:'ADDITIONAL_PARITY_UNCONFIRMED';
    $out['status']=$reserved?'unknown':'blocked';$out['reason']=$safe;$out['supplier_effect']=$reserved?'unknown':'none';
    if($reserved&&is_string($path)){try{anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe]);}catch(Throwable $ignored){}}
}finally{
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($client1797 instanceof AnyTourAnexAdditionalPricesClient)$out['additional_prices_requests']=$client1797->requestsMade();
    $out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);
}
$json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false){$out=['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'unknown','reason'=>'ADDITIONAL_PARITY_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0];break;}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";exit(($out['status']??null)==='completed'?0:1);
'''
        return prefix+runner
    argument='true' if program_followup else 'false'
    return prefix+'$report=anex_additional_parity_main('+argument+'); echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n"; exit(($report["status"]??null)==="completed"?0:1);'


def validate(value, program_followup=False, program1797=False):
    experiment=PROGRAM1797_EXPERIMENT if program1797 else (PROGRAM2637_EXPERIMENT if program_followup else EXPERIMENT)
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=experiment:
        raise ValueError('additional_parity_result_invalid')
    if value.get('automatic_retry') is not False or value.get('supplier_replay_allowed') is not False:
        raise ValueError('additional_parity_replay_invalid')
    if value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0:
        raise ValueError('additional_parity_effect_invalid')
    if (program_followup or program1797) and (value.get('direct_anex_requests')!=0 or value.get('tourvisor_requests')!=0):
        raise ValueError('additional_program_search_replay')
    status=value.get('status')
    if status not in ('completed','unknown','blocked'):
        raise ValueError('additional_parity_status_invalid')
    if status=='completed':
        effect=('read_only_additional_from_retained_concrete_completed' if program1797 else
                ('read_only_additional_from_retained_search_completed' if program_followup else 'read_only_search_and_additional_completed'))
        if value.get('supplier_effect')!=effect:
            raise ValueError('additional_parity_completion_invalid')
        additional=value.get('additional_prices')
        if not isinstance(additional,dict) or not isinstance(additional.get('data'),list):
            raise ValueError('additional_parity_additional_invalid')
        if value.get('additional_prices_requests')!=1:
            raise ValueError('additional_parity_request_count_invalid')
        if program1797:
            if value.get('additional_request_context')!=PROGRAM1797_CONTEXT: raise ValueError('additional_program_context_invalid')
        else:
            pair=value.get('selected_pair')
            if not isinstance(pair,dict) or not isinstance(pair.get('anex'),dict) or not isinstance(pair.get('tourvisor'),dict):
                raise ValueError('additional_parity_pair_invalid')
            if program_followup and value.get('additional_request_context')!=PROGRAM2637_CONTEXT:
                raise ValueError('additional_program_context_invalid')
    return value


def decimal(value):
    try:
        if isinstance(value,(str,int,float)) and not isinstance(value,bool):
            amount=Decimal(str(value))
            if amount.is_finite() and amount>=0:
                return amount
    except InvalidOperation:
        pass
    return None


def program2637_pair(value):
    """Reuse an already accepted historical identity; never infer or write mappings."""
    if not isinstance(value,dict) or value.get('experiment_id')!=EXPERIMENT or value.get('supplier_replay_allowed') is not False:
        raise ValueError('additional_program_retained_source_invalid')
    evidence=value.get('search_evidence') or {}
    expected={'local_hotel_id':21753,'date':'2026-10-12','nights':7,'adults':2,'children':0,'meal_family':'ai','currency':'RUB'}
    def matches(row):
        return isinstance(row,dict) and all(row.get(k)==v for k,v in expected.items()) and bool(row.get('room_norm')) and decimal(row.get('price')) is not None
    direct=[row for row in (evidence.get('anex') or {}).get('offers',[]) if matches(row)
            and row.get('provider')=='anex' and row.get('external_hotel_id')=='25084'
            and row.get('supplier_tour_program_id')=='2637' and row.get('supplier_currency_id')=='3']
    if len(direct)!=1:
        raise ValueError('additional_program_retained_anex_ambiguous')
    anex=direct[0]
    candidates=[row for row in (evidence.get('tourvisor') or {}).get('offers',[]) if matches(row)
                and row.get('provider')=='tourvisor' and row.get('external_hotel_id')=='21753'
                and row.get('room_norm')==anex['room_norm'] and decimal(row.get('fuel_charge')) is not None]
    if not candidates:
        raise ValueError('additional_program_retained_tourvisor_absent')
    tv=min(candidates,key=lambda row: decimal(row['price']))
    return {'basis':'retained_same_local_hotel_date_party_ai_exact_room_minimum',
            'identical_supplier_package_verified':False,'placement_compared_but_not_identity_key':True,
            'anex':anex,'tourvisor':tv}


def read_program2637_evidence(path):
    if path.stat().st_size>4000000:
        raise ValueError('additional_program_retained_size')
    raw=path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=RETAINED_RESULT_SHA256:
        raise ValueError('additional_program_retained_digest')
    return program2637_pair(json.loads(raw))


def read_program1797_evidence(path):
    if path.stat().st_size>4000000: raise ValueError('additional_program1797_retained_size')
    raw=path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=CONCRETE1797_RESULT_SHA256: raise ValueError('additional_program1797_retained_digest')
    value=json.loads(raw)
    if not isinstance(value,dict) or value.get('experiment_id')!='anex_concrete_fuel_binding_20260913_v2' \
       or value.get('status')!='completed' or value.get('supplier_replay_allowed') is not False \
       or value.get('additional_prices_requests')!=0:
        raise ValueError('additional_program1797_retained_source_invalid')
    directs=[]
    for row in value.get('concrete_offers') or []:
        if not isinstance(row,dict) or row.get('kind')!='concrete' or row.get('supplier_tour_program_id')!='1797' \
           or row.get('supplier_currency_id')!='3' or row.get('room_norm')!='standard room' or row.get('placement_norm')!='dbl': continue
        amount=decimal(row.get('price'))
        if amount is not None: directs.append(amount)
    if sorted(directs)!=[Decimal('133310'),Decimal('136541')]: raise ValueError('additional_program1797_direct_context_invalid')
    tv=[]
    for row in (value.get('tourvisor') or {}).get('offers') or []:
        if not isinstance(row,dict) or row.get('room_norm')!='standard room' or row.get('placement_norm')!='dbl': continue
        price=decimal(row.get('price'));fuel=decimal(row.get('fuel_charge'))
        if price is not None and fuel is not None: tv.append({'price':price,'fuel':fuel})
    if not tv: raise ValueError('additional_program1797_tv_context_invalid')
    exact=[]
    for base in directs:
        for row in tv:
            if row['price']-base==row['fuel']:
                exact.append({'direct_price':base,'tourvisor_price':row['price'],'tourvisor_fuel':row['fuel']})
    if len(exact)!=2 or {x['tourvisor_fuel'] for x in exact}!={Decimal('29184')}:
        raise ValueError('additional_program1797_pairing_invalid')
    return {'direct_prices':directs,'tourvisor':tv,'exact_pairs':exact,'source':value}


def analyze(value):
    report={'schema_version':1,'experiment_id':value.get('experiment_id',EXPERIMENT),'status':value.get('status'),
            'spec':dict(SPEC,experiment_id=value.get('experiment_id',EXPERIMENT)),
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0},
            'supplier_replay_allowed':False,'price_arithmetic_applied':False,
            'universal_formula_verified':False,'evidence':None}
    if 'retained_search_source' in value:
        report['retained_search_source']=value['retained_search_source']
        report['new_requests']={k:value.get(k) for k in ('direct_anex_requests','tourvisor_requests','additional_prices_requests')}
    if value.get('status')!='completed':
        report['reason']=value.get('reason'); return report
    pair=value['selected_pair']; anex=pair['anex']; tv=pair['tourvisor']; rows=value['additional_prices'].get('data') or []
    if len(rows)!=1 or not isinstance(rows[0],dict):
        report['evidence']={'state':'additional_row_absent' if not rows else 'additional_rows_ambiguous',
                            'row_count':len(rows),'basis':pair.get('basis')}; return report
    row=rows[0]
    direct=decimal(anex.get('price')); display=decimal(tv.get('price')); fuel=decimal(tv.get('fuel_charge'))
    adult=decimal(row.get('price_converted_adult')); child=decimal(row.get('price_converted_chd'))
    delta=display-direct if direct is not None and display is not None else None
    party2=adult*Decimal(2) if adult is not None else None
    report['evidence']={
        'state':'observed','basis':pair.get('basis'),'identical_supplier_package_verified':False,
        'local_hotel_id':anex.get('local_hotel_id'),'room_norm':anex.get('room_norm'),
        'direct_anex_search_price':str(direct) if direct is not None else None,
        'tourvisor_display_price':str(display) if display is not None else None,
        'tourvisor_fuel_charge_reported':str(fuel) if fuel is not None else None,
        'tourvisor_minus_direct':str(delta) if delta is not None else None,
        'additional_price_converted_adult':str(adult) if adult is not None else None,
        'additional_price_converted_child':str(child) if child is not None else None,
        'candidate_two_adult_rate_sum':str(party2) if party2 is not None else None,
        'delta_equals_tourvisor_fuel':delta is not None and fuel is not None and delta==fuel,
        'two_adult_rate_sum_equals_tourvisor_fuel':party2 is not None and fuel is not None and party2==fuel,
        'single_adult_rate_equals_tourvisor_fuel':adult is not None and fuel is not None and adult==fuel,
        'additional_native_amount_adult':str(row.get('price_adult')) if row.get('price_adult') is not None else None,
        'additional_native_amount_child':str(row.get('price_chd')) if row.get('price_chd') is not None else None,
        'cashrate':str(row.get('cashrate')) if row.get('cashrate') is not None else None,
        'application_rule_verified_for_search':False,
        'note':'candidate arithmetic is evidence comparison only, never applied to search price; retained search prices are not revalidated'}
    return report


def analyze_program1797(value, retained):
    report={'schema_version':1,'experiment_id':PROGRAM1797_EXPERIMENT,'status':value.get('status'),
            'spec':dict(SPEC,experiment_id=PROGRAM1797_EXPERIMENT),'supplier_replay_allowed':False,
            'price_arithmetic_applied':False,'universal_formula_verified':False,
            'retained_concrete_source':{'run_id':34720156085,'artifact_id':CONCRETE1797_ARTIFACT_ID,
                'result_sha256':CONCRETE1797_RESULT_SHA256,'search_replayed':False},
            'new_requests':{'direct_anex_requests':value.get('direct_anex_requests'),'tourvisor_requests':value.get('tourvisor_requests'),
                'additional_prices_requests':value.get('additional_prices_requests')},'evidence':None}
    if value.get('status')!='completed': report['reason']=value.get('reason');return report
    rows=(value.get('additional_prices') or {}).get('data') or []
    if len(rows)!=1 or not isinstance(rows[0],dict):
        report['evidence']={'state':'additional_row_absent' if not rows else 'additional_rows_ambiguous','row_count':len(rows)};return report
    row=rows[0];adult=decimal(row.get('price_converted_adult'));child=decimal(row.get('price_converted_chd'))
    party2=adult*Decimal(2) if adult is not None else None
    pairs=[]
    for pair in retained['exact_pairs']:
        fuel=pair['tourvisor_fuel'];gap=abs(party2-fuel) if party2 is not None else None
        pairs.append({'direct_price':str(pair['direct_price']),'tourvisor_price':str(pair['tourvisor_price']),
            'tourvisor_fuel':str(fuel),'tourvisor_minus_direct':str(pair['tourvisor_price']-pair['direct_price']),
            'candidate_two_adult_rate_sum_gap':str(gap) if gap is not None else None,
            'candidate_matches_within_one_ruble':gap is not None and gap<=Decimal('1')})
    report['evidence']={'state':'observed','program':'1797','native_currency_id':'3',
        'additional_price_converted_adult':str(adult) if adult is not None else None,
        'additional_price_converted_child':str(child) if child is not None else None,
        'candidate_two_adult_rate_sum':str(party2) if party2 is not None else None,
        'additional_native_amount_adult':str(row.get('price_adult')) if row.get('price_adult') is not None else None,
        'additional_native_amount_child':str(row.get('price_chd')) if row.get('price_chd') is not None else None,
        'cashrate':str(row.get('cashrate')) if row.get('cashrate') is not None else None,
        'retained_exact_concrete_tv_pairs':pairs,
        'all_retained_pairs_match_candidate_within_one_ruble':bool(pairs) and all(x['candidate_matches_within_one_ruble'] for x in pairs),
        'b2b_tour_equals_search_tour_semantics_verified':False,'additional_prices_equals_fuel_verified':False,
        'application_rule_verified_for_search':False,
        'note':'one new B2B read is compared to already completed concrete/TV evidence; no search replay and no production arithmetic'}
    return report


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp'); tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'); tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('additional_parity_report_readback')


def run(output, retained_source=None, program1797_source=None):
    program1797=program1797_source is not None
    program_followup=retained_source is not None
    if program1797 and program_followup: raise ValueError('additional_program_mode_conflict')
    retained1797=read_program1797_evidence(program1797_source) if program1797 else None
    pair=read_program2637_evidence(retained_source) if program_followup else None
    experiment=PROGRAM1797_EXPERIMENT if program1797 else (PROGRAM2637_EXPERIMENT if program_followup else EXPERIMENT)
    spec=dict(SPEC,experiment_id=experiment)
    value=transport.ssh_php_no_mux(source(program_followup,program1797),spec,maximum_bytes=4000000)
    if program_followup and isinstance(value,dict):
        value['selected_pair']=pair
        value['retained_search_source']={'experiment_id':EXPERIMENT,'run_id':34715151815,
            'artifact_id':RETAINED_ARTIFACT_ID,'result_sha256':RETAINED_RESULT_SHA256,
            'source_operation_still_no_replay':True,'search_prices_revalidated':False}
    value=validate(value,program_followup,program1797)
    save(output/'result.json',value)
    report=analyze_program1797(value,retained1797) if program1797 else analyze(value)
    save(output/'report.json',report); return report


def main():
    if len(sys.argv)==2:
        mode=None;retained=None
    elif len(sys.argv)==4 and sys.argv[2] in ('--program2637','--program1797'):
        mode=sys.argv[2];retained=Path(sys.argv[3])
    else:
        raise SystemExit('usage: anex_additional_parity_v3.py OUTPUT_DIR [--program2637 RETAINED_RESULT_JSON | --program1797 RETAINED_CONCRETE_RESULT_JSON]')
    output=Path(sys.argv[1])
    try:
        report=run(output,retained_source=retained if mode=='--program2637' else None,
                   program1797_source=retained if mode=='--program1797' else None)
        print(json.dumps(report,ensure_ascii=False,sort_keys=True)); raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True)); raise SystemExit(1) from None

if __name__=='__main__': main()

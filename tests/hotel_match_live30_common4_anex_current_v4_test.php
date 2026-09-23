<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_anex_current_v4.php';
function a4ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'77','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$cat=[100=>['id'=>100,'country_name'=>'Турция','is_active'=>1]];
$e=['anex_hotel_id'=>'5844','tv_hotel_id'=>100,'operator_id'=>13,'safe_to_write_now'=>false];
$ref=new ReflectionClass(AnyTourAnexSearchMappingRegistry::class);$reg=$ref->newInstanceWithoutConstructor();$p=$ref->getProperty('index');$p->setAccessible(true);$p->setValue($reg,[]);
$r=hmc4a_classify($e,$cat,[],[],[],[100=>[$anchor]],['5844'=>[100=>true]],$reg,['5844'=>true],['5844'=>true]);
a4ok($r['writer_ready']===true&&$r['staged_anex_hotel_present']===true&&$r['search_observation_present']===true,'ready');
$r=hmc4a_classify($e,$cat,[],['5844'=>['decision_status'=>'rejected','catalog_hotel_id'=>100]],[],[100=>[$anchor]],['5844'=>[100=>true]],$reg,[],[]);
a4ok($r['status']==='manual_source_protected'&&!$r['writer_ready'],'manual');
$r=hmc4a_classify($e,$cat,[],[],['5844'=>[100=>true]],[100=>[$anchor]],['5844'=>[100=>true]],$reg,[],[]);
a4ok($r['status']==='pair_excluded'&&!$r['writer_ready'],'excluded');
$r=hmc4a_classify($e,$cat,[],[],[],[100=>[$anchor]],['5844'=>[100=>true,101=>true]],$reg,[],[]);
a4ok($r['status']==='saved_source_collision'&&!$r['writer_ready'],'collision');
$bad=$anchor;$bad['evidence_sha256']=str_repeat('f',64);$r=hmc4a_classify($e,$cat,[],[],[],[100=>[$bad]],['5844'=>[100=>true]],$reg,[],[]);
a4ok($r['anchor_state']==='canonical_anchor_evidence_invalid'&&!$r['writer_ready'],'anchor');
echo "MATCH_LIVE30_COMMON4_ANEX_CURRENT_V4_TEST_OK\n";

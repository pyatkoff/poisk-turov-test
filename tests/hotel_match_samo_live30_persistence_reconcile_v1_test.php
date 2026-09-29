<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_samo_live30_persistence_reconcile_v1.php';

function t(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$active=[1=>1,2=>1,3=>1,4=>1];
$accepted=[
    'andromeda_catalog|A'=>[1=>1],
    'operator_115|B'=>[2=>1],
    'andromeda_catalog|X'=>[3=>1,4=>1],
];
$obs=[
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'A'],
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'U'],
    ['supplier_namespace'=>'operator_115','external_hotel_id'=>'B'],
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'X'],
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'U'],
];
$r=hmsl_reconcile($active,$accepted,$obs,[1,3,999],[1,2,999]);
t($r['mapped_local_hotels']['accounted_30d']===3,'mapped_union');
t($r['mapped_local_hotels']['price_history']===2,'price_active');
t($r['mapped_local_hotels']['offer_store']===2,'store_active');
t($r['mapped_local_hotels']['later_resolved_observations']===2,'obs_mapped');
t($r['mapped_local_hotels']['price_only']===1,'price_only');
t($r['mapped_local_hotels']['offer_store_only']===0,'store_only');
t($r['mapped_local_hotels']['observation_only']===0,'obs_only');
t($r['observation_journal']['identity_keys']===4,'dedupe');
t($r['observation_journal']['mapped_identity_keys_now']===2,'mapped_keys');
t($r['observation_journal']['unresolved_identity_keys']===1,'unresolved');
t($r['observation_journal']['source_collision_keys']===1,'collision');
t($r['observation_journal']['catalog_identity_keys']===3,'catalog_keys');
t($r['observation_journal']['catalog_unresolved_identity_keys']===1,'catalog_unresolved');
t($r['observation_journal']['by_namespace']===['andromeda_catalog'=>3,'operator_115'=>1],'namespace_counts');
t($r['unique_hotel_accounting_range']['lower_bound_mapped_local_hotels']===3,'lower');
t($r['unique_hotel_accounting_range']['upper_bound_if_every_unresolved_identity_is_distinct_new_hotel']===4,'upper');
t($r['unique_hotel_accounting_range']['exact']===false,'not_exact');

$empty=hmsl_reconcile([1=>1],[],[],[1],[1]);
t($empty['mapped_local_hotels']['accounted_30d']===1,'empty_mapped');
t($empty['unique_hotel_accounting_range']['exact']===true,'empty_exact');
t($empty['unique_hotel_accounting_range']['lower_bound_mapped_local_hotels']===1,'empty_lower');

t(hmsl_excluded_country('Россия'),'exclude_ru');
t(hmsl_excluded_country('Abkhazia'),'exclude_abkhazia');
t(!hmsl_excluded_country('Turkey'),'keep_turkey');
t(strlen($r['mapped_local_hotels']['set_sha256'])===64,'mapped_hash');
t(strlen($r['hashes']['observed_identity_keys_sha256'])===64,'obs_hash');

$catalog=hmsl_catalog_reconcile($active,$accepted,$obs,[1,3],[1,2],['A'=>100,'C'=>101,'U2'=>102]);
t($catalog['persistent_catalog_identity_keys']===3,'persistent_catalog');
t($catalog['retained_cache_catalog_identity_keys']===3,'cache_catalog');
t($catalog['overlap_identity_keys']===1,'catalog_overlap');
t($catalog['persistent_only_identity_keys']===2,'persistent_only');
t($catalog['cache_only_identity_keys']===2,'cache_only');
t($catalog['union_catalog_identity_keys']===5,'catalog_union');
t($catalog['accepted_exact_identity_keys_now']===1,'catalog_accepted');
t($catalog['unresolved_identity_keys_now']===3,'catalog_unresolved');
t($catalog['collision_identity_keys_now']===1,'catalog_collision');
t(strlen($catalog['union_sha256'])===64,'catalog_union_hash');
t($catalog['surface_status']['persistent']['identity_keys']===3,'surface_persistent');
t($catalog['surface_status']['persistent']['accepted_exact']===1,'surface_persistent_accepted');
t($catalog['surface_status']['persistent']['unresolved']===1,'surface_persistent_unresolved');
t($catalog['surface_status']['persistent']['collisions']===1,'surface_persistent_collision');
t($catalog['surface_status']['cache_only']['identity_keys']===2,'surface_cache_only');
t($catalog['surface_status']['cache_only']['accepted_exact']===0,'surface_cache_only_accepted');
t($catalog['surface_status']['cache_only']['unresolved']===2,'surface_cache_only_unresolved');
t($catalog['surface_status']['union']['identity_keys']===5,'surface_union');
echo "SAMO_LIVE30_PERSISTENCE_RECONCILE_TEST_OK 43 checks\n";

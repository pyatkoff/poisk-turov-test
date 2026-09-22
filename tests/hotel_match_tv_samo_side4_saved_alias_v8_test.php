<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v8.php';
$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v8.php');
if(str_contains($source,'hmc_tv_call(')||str_contains($source,'->price(')||str_contains($source,'v2_data_db('))throw new RuntimeException('offline_only');
if(!str_contains($source,"'database_reads'=>0")||!str_contains($source,"'mapping_writes'=>0"))throw new RuntimeException('write_guard');
echo "MATCH_TV_SAMO_SIDE4_SAVED_ALIAS_V8_TEST_OK\n";

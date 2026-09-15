<?php
declare(strict_types=1);
$path=__DIR__.'/../scripts/diagnostics/hotel_match_received_union_guards.php';
$src=file_get_contents($path);if($src===false)throw new RuntimeException('read');
foreach(['START TRANSACTION READ ONLY','relation_safe_count','unresolved_anchor_safe_count','same_provider_target_occupied','coordinate_conflict_gt5km','primary_qualifier_or_numeric_conflict','apply_manifest\'=>false'] as $needle)if(strpos($src,$needle)===false)throw new RuntimeException('missing_'.$needle);
if(preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\s/i',$src))throw new RuntimeException('mutation_sql');
$cmd='php '.escapeshellarg($path).' --self-test';exec($cmd,$out,$rc);if($rc!==0||implode("\n",$out)!=='4 guard self-tests PASS')throw new RuntimeException('self_test');
echo "guard reviewer static/read-only tests PASS\n";

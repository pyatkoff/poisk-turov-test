<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_coordinate_bridge_review.php';

function hcbr_assert(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$strict=['distance_m'=>900,'distance_margin_m'=>100,'name'=>['strict'=>true,'broad'=>true,'score'=>1.0,'shared'=>2]];
hcbr_assert(hcbr_safe_coordinate_candidate($strict)===true,'strict_name_within_1km');

$broad=['distance_m'=>400,'distance_margin_m'=>100,'name'=>['strict'=>false,'broad'=>true,'score'=>1.0,'shared'=>2]];
hcbr_assert(hcbr_safe_coordinate_candidate($broad)===true,'broad_name_within_500m');

$fuzzy=['distance_m'=>180,'distance_margin_m'=>180,'name'=>['strict'=>false,'broad'=>false,'score'=>0.67,'shared'=>2]];
hcbr_assert(hcbr_safe_coordinate_candidate($fuzzy)===true,'strong_fuzzy_coordinate');

$qualifierMismatch=['distance_m'=>35,'distance_margin_m'=>250,'name'=>['strict'=>false,'broad'=>false,'score'=>0.33,'shared'=>1]];
hcbr_assert(hcbr_safe_coordinate_candidate($qualifierMismatch)===false,'weak_name_even_exact_coordinate');

$cluster=['distance_m'=>20,'distance_margin_m'=>40,'name'=>['strict'=>false,'broad'=>false,'score'=>0.8,'shared'=>3]];
hcbr_assert(hcbr_safe_coordinate_candidate($cluster)===false,'cluster_ambiguity_guard');

$compat=hcbr_names_compatible(['APERION BEACH HOTEL'],['APERION BEACH (EX. SEA PARADISE)']);
hcbr_assert($compat['shared']>=2,'former_name_shared_tokens');

$qualifier=hcbr_names_compatible(['SUN BEACH'],['SUN GARDEN']);
hcbr_assert($qualifier['broad']===false,'significant_qualifier_not_ignored');

echo "ok\n";

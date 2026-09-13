<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_observed_coordinate_review.php';
const HMLOC_V2_OPERATION='hotel-match-live-observed-coordinate-review-2333-20260914-v2';
function hmloc_v2_review(PDO $db,string $operation=HMLOC_V2_OPERATION): array {
    if($operation!==HMLOC_V2_OPERATION)throw new RuntimeException('operation_scope');
    $out=hmloc_review($db,HMLOC_OPERATION);
    $out['operation_id']=$operation;
    return $out;
}

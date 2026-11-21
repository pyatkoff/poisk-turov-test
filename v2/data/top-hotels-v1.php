<?php
/** Tourvisor priority input, projected from the owner's explicitly ANEX roster. */
declare(strict_types=1);
require_once __DIR__ . '/top500-anex-priority-v1.php';

function v2_priority_hotel_ids(?AnyTourAnexSearchMappingRegistry $registry = null): array
{
    return v2_top500_resolution($registry)['tourvisor_ids'];
}

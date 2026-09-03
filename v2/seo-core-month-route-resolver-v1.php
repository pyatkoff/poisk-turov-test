<?php
declare(strict_types=1);
require_once __DIR__.'/seo-core-month-content-v1.php';

function v2_seo_core_month_record_for_path(string $path): array
{
    $path=trim($path);
    if($path==='' || !str_starts_with($path,'/country/') || !str_ends_with($path,'/')) {
        throw new InvalidArgumentException('Invalid core month route path');
    }
    foreach(v2_seo_core_month_content_records() as $record) {
        if(($record['path']??'')===$path) return $record;
    }
    throw new OutOfBoundsException('Unknown core month route');
}

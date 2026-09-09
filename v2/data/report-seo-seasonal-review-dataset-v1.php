<?php
/**
 * CLI-only normalizer for already generated explicit seasonal review plans.
 * Produces planning metadata only; no routes, copy, feeds or publication.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/seo-seasonal-review-dataset-v1.php';

function seasonal_dataset_arg(array $argv,string $name):?string
{
    foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return trim(substr($arg,strlen($name)+3));
    return null;
}
function seasonal_dataset_json(string $path):array
{
    $raw=@file_get_contents($path);
    if($raw===false||trim($raw)==='') throw new InvalidArgumentException('Seasonal plan input is empty');
    $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($decoded)) throw new InvalidArgumentException('Seasonal plan input must be an object');
    return $decoded;
}

try {
    $rawPlans=seasonal_dataset_arg($argv,'plans')??'';
    $rawFamilies=seasonal_dataset_arg($argv,'families')??'';
    if($rawPlans===''||$rawFamilies==='') throw new InvalidArgumentException('Usage requires --plans and --families');
    $paths=array_values(array_filter(array_map('trim',preg_split('/\s*,\s*/',$rawPlans,-1,PREG_SPLIT_NO_EMPTY)?:[])));
    $families=array_values(array_filter(array_map('trim',preg_split('/\s*,\s*/',$rawFamilies,-1,PREG_SPLIT_NO_EMPTY)?:[])));
    if($paths===[]||$families===[]) throw new InvalidArgumentException('Plans and families must be non-empty');
    $plans=array_map('seasonal_dataset_json',$paths);
    $maxItems=(int)(seasonal_dataset_arg($argv,'max-items')??'12');
    $dataset=v2_seo_seasonal_review_dataset($plans,$families,null,$maxItems);
    echo json_encode($dataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    $require=(int)(seasonal_dataset_arg($argv,'require-items')??'0');
    if($require>0&&(int)($dataset['item_count']??0)!==$require){
        fwrite(STDERR,'SEO_SEASONAL_DATASET_CLI_FAIL:require_items expected='.$require.' actual='.($dataset['item_count']??0)."\n");
        exit(3);
    }
} catch(Throwable $e) {
    fwrite(STDERR,'SEO_SEASONAL_DATASET_CLI_FAIL:'.$e->getMessage()."\n");
    exit(2);
}

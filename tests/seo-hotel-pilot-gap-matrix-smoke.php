<?php
declare(strict_types=1);
function fail_matrix(string $m):never{fwrite(STDERR,"SEO_HOTEL_PILOT_GAP_MATRIX_SMOKE_FAIL:$m\n");exit(1);}
$rows=[];
for($i=1;$i<=9;$i++){
    $rows[]=[
        'path'=>"/country/test/hotel/example-$i/",
        'dimensions'=>[
            'technical'=>['status'=>'confirmed'],
            'identity'=>['status'=>'confirmed'],
            'review_boundary'=>['status'=>'confirmed'],
            'intent'=>['status'=>'unknown'],
            'demand'=>['status'=>'unknown'],
            'uniqueness'=>['status'=>'unknown'],
            'content'=>['status'=>'unknown'],
            'commercial_inventory'=>['status'=>'confirmed'],
            'scoring_policy'=>['status'=>'pending'],
        ],
    ];
}
$status=[
    'state'=>'review_only_hotel_pilot_status_complete',
    'rows'=>$rows,
    'evidence_complete_count'=>0,
    'review_ready_count'=>0,
    'status_sha256'=>'fixture',
    'publication_candidates'=>[],
    'publication_allowed'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'canonical_launch_allowed'=>false,
    'route_launch_allowed'=>false,
    'automatic_execution_allowed'=>false,
    'explicit_user_indexation_approval_required'=>true,
];
$tmp=tempnam(sys_get_temp_dir(),'seo-gap-');file_put_contents($tmp,json_encode($status,JSON_THROW_ON_ERROR));
$cmd='php '.escapeshellarg(__DIR__.'/../v2/data/report-seo-hotel-pilot-gap-matrix-v1.php').' --status='.escapeshellarg($tmp);
exec($cmd,$out,$code);unlink($tmp);if($code!==0)fail_matrix('exit');
$r=json_decode(implode("\n",$out),true);if(!is_array($r)||($r['state']??'')!=='review_only_hotel_pilot_gap_matrix_ready')fail_matrix('state');
foreach(['intent','demand','uniqueness','content','scoring_policy'] as $d){if(($r['priority_gaps'][$d]['gap_count']??-1)!==9)fail_matrix('gap_'.$d);}
if(($r['dimension_counts']['technical']['confirmed']??0)!==9||($r['dimension_counts']['commercial_inventory']['confirmed']??0)!==9)fail_matrix('confirmed');
if(count($r['missing_by_path']??[])!==9||($r['publication_candidates']??null)!==[]||($r['route_launch_allowed']??null)!==false)fail_matrix('safety');
echo "SEO_HOTEL_PILOT_GAP_MATRIX_SMOKE_OK gaps=5x9 publication=0 indexation=0\n";

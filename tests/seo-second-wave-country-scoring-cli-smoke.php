<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-second-wave-country-review-v1.php';

function second_wave_cli_fail(string $message): never
{
    fwrite(STDERR,"SEO_SECOND_WAVE_SCORING_CLI_SMOKE_FAIL:$message\n");
    exit(1);
}

$now=1788369300;
$cli=__DIR__.'/../v2/data/report-seo-second-wave-country-scoring-v1.php';
$files=[];
foreach(['identity','demand','uniqueness','policy'] as $name){
    $tmp=tempnam(sys_get_temp_dir(),'seo-second-wave-'.$name.'-');
    if($tmp===false)second_wave_cli_fail('tempfile_'.$name);
    $files[$name]=$tmp;
}
register_shutdown_function(static function()use($files):void{foreach($files as $file)@unlink($file);});

$specs=v2_seo_second_wave_country_specs();
$paths=array_map(static fn(array $spec):string=>(string)$spec['path'],$specs);
$identity=[
    'state'=>'fresh_second_wave_production_identity_evidence',
    'domain'=>'anytoour.ru',
    'scope'=>'egypt_maldives_country_review_v1',
    'observed_at_utc'=>'2026-09-02T17:14:00+00:00',
    'production_identity_fresh'=>true,
    'publication_scope_expanded'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
    'pages'=>array_map(static fn(string $path):array=>[
        'path'=>$path,
        'http_status'=>200,
        'robots'=>'noindex,follow,max-image-preview:large',
        'canonical'=>'https://anytoour.ru'.$path,
        'sitemap_member'=>false,
    ],$paths),
];
$demand=[];$uniqueness=[];
foreach($specs as $i=>$spec){
    $demand[]=[
        'page_key'=>(string)$spec['page_key'],
        'query_cluster'=>(string)$spec['query_cluster'],
        'source_class'=>'keyword_research_export',
        'source_ref'=>'fixture://keyword-export/'.($i+1),
        'observed_at_epoch'=>$now-60,
        'status'=>'confirmed',
        'metrics'=>['monthly_searches'=>100+$i],
        'serp_intent'=>'commercial',
    ];
    $uniqueness[]=[
        'page_key'=>(string)$spec['page_key'],
        'query_cluster'=>(string)$spec['query_cluster'],
        'page_path'=>(string)$spec['path'],
        'source_class'=>'manual_serp_review',
        'source_ref'=>'fixture://serp-review/'.($i+1),
        'observed_at_epoch'=>$now-60,
        'status'=>'confirmed',
        'decision'=>'distinct',
        'competing_paths'=>[],
    ];
}
$policy=[
    'policy_id'=>'fixture-country-ranking',
    'version'=>'test-v1',
    'source_ref'=>'fixture://explicit-reviewed-test-policy',
    'approved_at_epoch'=>$now-120,
    'dimensions'=>[
        'demand'=>[
            'weight'=>60,
            'rules'=>[['field'=>'metrics.monthly_searches','operator'=>'gte','value'=>1,'score'=>80]],
        ],
        'uniqueness'=>[
            'weight'=>40,
            'rules'=>[['field'=>'decision','operator'=>'eq','value'=>'distinct','score'=>100]],
        ],
    ],
];

$write=static function(string $file,array $value):void{
    file_put_contents($file,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
};
$run=static function(array $files,int $now,bool $require=true):array use($cli){
    $cmd='php '.escapeshellarg($cli)
        .' --identity='.escapeshellarg($files['identity'])
        .' --demand='.escapeshellarg($files['demand'])
        .' --uniqueness='.escapeshellarg($files['uniqueness'])
        .' --policy='.escapeshellarg($files['policy'])
        .' --now-epoch='.escapeshellarg((string)$now)
        .($require?' --require-scored':'').' 2>&1';
    $lines=[];$code=0;exec($cmd,$lines,$code);
    return ['code'=>$code,'text'=>implode("\n",$lines)];
};

$write($files['identity'],$identity);$write($files['demand'],['rows'=>$demand]);$write($files['uniqueness'],['rows'=>$uniqueness]);$write($files['policy'],$policy);
$ok=$run($files,$now,true);
if($ok['code']!==0)second_wave_cli_fail('valid_exit_'.$ok['code'].'_'.$ok['text']);
try{$decoded=json_decode($ok['text'],true,512,JSON_THROW_ON_ERROR);}catch(JsonException){second_wave_cli_fail('valid_json');}
if(($decoded['state']??'')!=='second_wave_country_scored_review_only'||($decoded['scored_count']??0)!==2)second_wave_cli_fail('valid_state');
if(($decoded['identity_remaining_seconds']??0)<=0)second_wave_cli_fail('identity_ttl');
if(($decoded['launch_decision_state']??'')!=='requires_separate_launch_dossier')second_wave_cli_fail('launch_boundary');
if(($decoded['input_semantics']??'')!=='external_evidence_only_no_defaults')second_wave_cli_fail('input_semantics');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed'] as $flag){
    if(($decoded[$flag]??true)!==false)second_wave_cli_fail('boundary_'.$flag);
}
if(($decoded['publication_candidates']??null)!==[])second_wave_cli_fail('publication_candidates');

$missingMetric=$demand;unset($missingMetric[0]['metrics']);
$write($files['demand'],['rows'=>$missingMetric]);
$blocked=$run($files,$now,true);
if($blocked['code']!==3||!str_contains($blocked['text'],'second_wave_country_scoring_blocked'))second_wave_cli_fail('missing_metric_not_blocked');
$write($files['demand'],['rows'=>$demand]);

$stale=$identity;$stale['observed_at_utc']='2026-08-31T17:14:00+00:00';$write($files['identity'],$stale);
$blocked=$run($files,$now,true);
if($blocked['code']!==3||!str_contains($blocked['text'],'production_identity_not_valid'))second_wave_cli_fail('stale_identity_not_blocked');
$write($files['identity'],$identity);

$extra=$demand;$extra[]=$demand[0];$extra[2]['page_key']='country:turkey';$write($files['demand'],['rows'=>$extra]);
$rejected=$run($files,$now,true);
if($rejected['code']!==2||!str_contains($rejected['text'],'demand_page_key_set_mismatch'))second_wave_cli_fail('out_of_scope_demand_not_rejected');

echo "SEO_SECOND_WAVE_SCORING_CLI_OK externalEvidence=1 staleBlocked=1 missingMetricBlocked=1 outOfScopeRejected=1 publication=0 hotelTours=0\n";

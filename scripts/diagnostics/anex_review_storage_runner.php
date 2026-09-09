<?php
declare(strict_types=1);
// CLI body is composed with the two checked classes on the existing SSH path.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
try{
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('anytour_root_required');
    $raw=stream_get_contents(STDIN,20000001);if($raw===false||strlen($raw)>20000000)throw new RuntimeException('input_bound');
    $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($input)||!in_array($input['action']??'', ['inspect','apply'],true)
        ||!preg_match('/\A[0-9a-f]{40}\z/D',$input['source_sha']??''))throw new RuntimeException('storage_contract');
    $helper=false;foreach(['data/db-v1.php','v2/data/db-v1.php'] as $path){$p=realpath($root.'/'.$path);if($p&&strpos($p,$root.'/')===0&&is_file($p)){$helper=$p;break;}}
    if(!$helper)throw new RuntimeException('db_helper_required');require_once $helper;
    if(!function_exists('v2_data_db'))throw new RuntimeException('db_helper_required');
    $pdo=v2_data_db();if(!$pdo instanceof PDO)throw new RuntimeException('db_unavailable');
    $manager=new AnexReviewSchemaManager($pdo);
    $schema=$manager->run(REVIEW_SCHEMA_FILES,REVIEW_SCHEMA_DIGEST,$input['action']==='apply');
    $import=['status'=>'not_requested','inserted'=>0];
    if($input['action']==='apply'){
        if(!is_array($input['envelope']??null))throw new RuntimeException('dossier_envelope_required');
        $before=$manager->preservation();
        $import=(new AnexReviewDossierStore($pdo))->import($input['envelope']);
        $after=$manager->preservation();
        AnexReviewSchemaManager::assertPreserved($before,$after,['anex_review_dossiers','anex_review_dossier_batches']);
        $store=new AnexReviewDossierStore($pdo);$verified=0;
        foreach($input['envelope']['rows'] as $row){$latest=$store->latest($row['id']);if($latest===null||$latest['artifact_id']<$input['envelope']['artifact_id'])throw new RuntimeException('dossier_final_readback');++$verified;}
        $import['verified_ids']=$verified;$import['before']=$before;$import['after']=$after;
    }
    echo json_encode(['status'=>'ok','source_sha'=>$input['source_sha'],'supplier_requests'=>0,'schema'=>$schema,'import'=>$import,
        'auth_adapter_configured'=>(bool)getenv('ANYTOUR_ANEX_REVIEW_AUTH_FILE'),'panel_published'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    $reason=$e instanceof RuntimeException&&preg_match('/\A[a-z_]+\z/D',$e->getMessage())?$e->getMessage():'database_or_runtime_failure';
    // Structured application failure is persisted by the caller, which exits nonzero.
    echo json_encode(['status'=>'failed','reason'=>$reason,'supplier_requests'=>0,'reset_performed'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
}

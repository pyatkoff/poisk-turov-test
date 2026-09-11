<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_approx_name_rescue_v2.php';

const HMGAN3_OPERATION = 'hotel-match-global-approx-name-rescue-1971-20260911-v3';
const HMGAN3_BASE_OPERATION = HMGAN2_OPERATION;
const HMGAN3_MIN_IDENTITY_ANCHORS = 2;

function hmgan3_generic_tokens(): array {
    return array_fill_keys([
        'hotel','hotels','resort','resorts','spa','apart','apartment','apartments','apt',
        'residence','residences','suite','suites','guest','house','bungalow','bungalows',
        'boutique','member','collection','by','of','the','at','ex','former','old','city',
        'center','centre','adult','adults','only'
    ], true);
}
function hmgan3_place_tokens(array $row): array {
    $out=[];
    foreach (array_merge((array)($row['source_places']??[]),[(string)($row['target_region']??''),(string)($row['target_subregion']??'')]) as $value) {
        foreach ([(string)$value,hmgcr_latin((string)$value)] as $v) {
            foreach (hmgan_tokens($v,[]) as $token) $out[(string)$token]=true;
        }
    }
    return $out;
}
function hmgan3_semantic_guard(array $row): array {
    $anchors=(int)($row['identity_anchors']['identity_aligned']??0);
    if ($anchors < HMGAN3_MIN_IDENTITY_ANCHORS) return ['ok'=>false,'reason'=>'identity_anchors_lt_2'];
    if (($row['place_match']??false)!==true) return ['ok'=>false,'reason'=>'place_mismatch'];
    $pair=$row['pair']??[];
    if (($pair['critical_ok']??false)!==true) return ['ok'=>false,'reason'=>'critical_token_guard'];
    $generic=hmgan3_generic_tokens();$places=hmgan3_place_tokens($row);$alignedSource=[];$alignedTarget=[];
    foreach (($pair['pairs']??[]) as $p) { $s=(string)($p['source']??'');$t=(string)($p['target']??'');if($s!=='')$alignedSource[$s]=true;if($t!=='')$alignedTarget[$t]=true; }
    $significant=function(array $tokens,array $aligned) use($generic,$places): array {
        $out=[];foreach($tokens as $raw){$t=(string)$raw;if($t===''||isset($aligned[$t])||isset($generic[$t])||isset($places[$t])||ctype_digit($t)||mb_strlen($t,'UTF-8')<2)continue;$out[$t]=true;}return array_keys($out);
    };
    $sourceExtra=$significant((array)($pair['source_tokens']??[]),$alignedSource);
    $targetExtra=$significant((array)($pair['target_tokens']??[]),$alignedTarget);
    if($sourceExtra||$targetExtra) return ['ok'=>false,'reason'=>'meaningful_unmatched_qualifier','source_extra'=>$sourceExtra,'target_extra'=>$targetExtra];
    return ['ok'=>true,'reason'=>'semantic_guard_pass','source_extra'=>[],'target_extra'=>[]];
}
function hmgan3_review(PDO $db,string $operation=HMGAN3_OPERATION): array {
    if($operation!==HMGAN3_OPERATION)throw new RuntimeException('HMGAN3_OPERATION_SCOPE');
    $base=hmgan2_review($db,HMGAN2_OPERATION);
    if(($base['status']??null)!=='completed'||($base['database_writes']??null)!==0||($base['supplier_calls']??null)!==0)throw new RuntimeException('HMGAN3_BASE_INVALID');
    $prepared=[];$demoted=[];$stats=['base_prepared'=>count($base['prepared']??[]),'prepared'=>0,'prepared_live'=>0,'demoted'=>0,'demoted_meaningful_qualifier'=>0,'star_mismatch'=>0];
    foreach(($base['prepared']??[]) as $row){$guard=hmgan3_semantic_guard($row);$row['semantic_guard']=$guard;if($guard['ok']){$row['reason']='approx_name_v3_semantic_hardened';$prepared[]=$row;$stats['prepared']++;if($row['live']??false)$stats['prepared_live']++;if($row['star_mismatch']??false)$stats['star_mismatch']++;}else{$row['reason']=$guard['reason'];$demoted[]=$row;$stats['demoted']++;if($guard['reason']==='meaningful_unmatched_qualifier')$stats['demoted_meaningful_qualifier']++;}}
    return ['status'=>'completed','operation_id'=>$operation,'mode'=>'v2_current_recheck_plus_meaningful_qualifier_guard_read_only','base_operation'=>HMGAN2_OPERATION,'base_artifact'=>HMGAN2_BASE_ARTIFACT,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'policy'=>['server_current_v2_recheck'=>true,'min_non_local_identity_anchors'=>HMGAN3_MIN_IDENTITY_ANCHORS,'place_match_required'=>true,'meaningful_qualifiers_preserved'=>['annex','beach','garden','north','south','club','brand_tokens'],'generic_lodging_tokens_ignored'=>array_keys(hmgan3_generic_tokens()),'coordinate_conflict_policy_inherited'=>true,'same_provider_occupancy_guard_inherited'=>true],'coverage'=>$base['coverage'],'stats'=>$stats,'prepared'=>$prepared,'demoted'=>$demoted,'base_stats'=>$base['stats']];
}

if (!defined('FC_LIBRARY_ONLY')) { fwrite(STDERR,"library-only diagnostic; run through guarded MATCH workflow\n"); exit(2); }

<?php
declare(strict_types=1);
// Read only. Never includes application configuration or invokes any publisher.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');ini_set('log_errors','0');
const READ_PINS_B64='eyJpbnRlZ3JhdGlvbnMvQ2hhbm5lbFBvc3RBcGlUcmFuc3BvcnQucGhwIjogIjMwMDYzNGQ3N2NiOWZkZGY1MWFkZjk4Njk5NzRmOTU2Y2NhNmUzZjkiLCAibWlncmF0aW9ucy8wMzNfY2hhbm5lbF9wb3N0X2NvbnRleHQuc3FsIjogIjA1MzA0NTgwMzBhNGJmZTZiNmNjNTVkMWU1ZTQ0MTc0NWMyOWI5NDciLCAibWlncmF0aW9ucy8wMzZfY2hhbm5lbF9wb3N0X21lbWJlcl9kZWxpdmVyeS5zcWwiOiAiMWJkYTQ2M2U1MmIzMzEzMGFkMTI3NTJkZGVmNGVlNWM5MjY5OWMwNSIsICJtaWdyYXRpb25zLzA0MF9jaGFubmVsX3Bvc3RfcmVjb25jaWxpYXRpb24uc3FsIjogIjBmMjRiODM4ZWFjZDQ1MzMzYzVhZGU5YTI5OTFkMDYwNDg4YzYwMzUiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RBZG1pblNlcnZpY2UucGhwIjogImI4MGVlZmM4MDEwNTRiZmU2ZmRiNThlYzM0NWVjZGY2ODdiMmU4OWEiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RBdXRvbWF0aW9uU2VydmljZS5waHAiOiAiYzYzODZlMTU1ZDQyYmM4Mzk3MGVkYzk1ZjM1YjNlYmViNTE0MDUzMyIsICJzZXJ2aWNlcy9DaGFubmVsUG9zdEJ1dHRvblRyYW5zcG9ydC5waHAiOiAiN2I2NzExZjFkZTYzMDAwMzQ2ZDhlNTc4OWYyZGJiN2Y3MjU1MzViOCIsICJzZXJ2aWNlcy9DaGFubmVsUG9zdEVudHJ5U2VydmljZS5waHAiOiAiMTk4MmZmNzM2ODc2M2VkZjhiNzIzOGMzMTQyZTNjYzI4NTc5ZTk5MSIsICJzZXJ2aWNlcy9DaGFubmVsUG9zdE1heEFsYnVtUmVjb3ZlcnkucGhwIjogImRlMjY2Y2QwNjg1ODdjYzNiMmZmODNmMzA4YTk2NmVhNjY4ODdhMDQiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RNZWRpYVNlcnZpY2UucGhwIjogImY3OTRlZmQwZjZmMjRkZjkxNmUxODkwOWZmNjI4MWIwYmM5Mjg0MzMiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RNZW1iZXJEZWxpdmVyeVNlcnZpY2UucGhwIjogIjIwYzFkNzk4NDg5OGIyYTU5OTZhNWY5ZTIwZTRkMmQ4YzIwYjQ3NzgiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RSZWNvbmNpbGlhdGlvblNlcnZpY2UucGhwIjogIjQzMTMzZjU3Njg0NWMxNDQ0NjEwMmRlZGZmZmI3ZjBkZTJkNGJhMWMiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RSZWNvbmNpbGlhdGlvblN0YXRlLnBocCI6ICJkOTU4M2NlY2E4MGQxNDI0YTQ0YWRlMzU2MWMyMTI4MmEyM2E1MmYwIiwgInNlcnZpY2VzL0NoYW5uZWxQb3N0UmVnaXN0cnlTZXJ2aWNlLnBocCI6ICI5NGZiMTk4OGFjOGNhZjkwZGM1MTAzYWEyM2M1OGU3YWFkNmVhNjkxIiwgInNlcnZpY2VzL0NoYW5uZWxQb3N0UnVudGltZS5waHAiOiAiMmYxZGQ5Yjc3NDc0ZmRiYWE5MmYwNDAyZjNiMGFhMTcwYzU5NTQ3NCIsICJzZXJ2aWNlcy9DaGFubmVsUG9zdFRlbGVncmFtQWxidW0ucGhwIjogImZhYTFhMTRiYzI3OWE0YWYzMzkzNjI4MjEyNmJmMGQ0NmUzNDMwNDAiLCAic2VydmljZXMvQ2hhbm5lbFBvc3RXZWJob29rQ29uZmlnU2VydmljZS5waHAiOiAiZDVmZGNiMDU2MDg4ZjFlM2E1NDFiNDA0NjMyNWQ5N2VlZjY0MTdkOSIsICJzZXJ2aWNlcy9Db252ZXJzYXRpb25EYi5waHAiOiAiYmI2MjgxZmFmNTVhY2E3YmVlYWU3ZjhhNTczODlkMTk3NTE5MzZmNyIsICJzZXJ2aWNlcy9NYXhUbHNDb25maWcucGhwIjogIjAzMmMwMjExZWUzZjM5YzZmMGQyN2M2NjlhYmQ1YjQ2N2UzMjFkZWEiLCAic2VydmljZXMvTWF4VHJhbnNwb3J0LnBocCI6ICIyY2I0Zjg3ODZiNDcwNGE4ZTYyYTNhOWM0MGRjMmNjNzNkYWQ2MTU5IiwgInNlcnZpY2VzL1Byb2plY3RDb25maWcucGhwIjogIjYzYzE3YzgxMDM0MTJmOGNmZjhjZGNjNGJiYjA2MmU3NDQxMDZhZjkiLCAic2VydmljZXMvQ29udmVyc2F0aW9uUmVjb3JkQ2xvY2sucGhwIjogIjhmMWQ1YjlhNDg4ODE3NGEwODY3NDA4NTk0YTE2NGZjNzc2ZGRiZTQiLCAic2VydmljZXMvUHJvamVjdENvbmZpZ3VyYXRpb24ucGhwIjogIjA3MDE5ZDQ3M2M1ODhlMzM4NmFjZTQyOTc3ZGI5MzA5MmU4MTEyZTMifQ==';
const READ_PREFIX_B64='eyJob3RlbC1wb3N0cy10ZWxlZ3JhbS54bWwiOiB7InRnXzEyMTIwIjogImI3ODZlNjQ3MDExMzI3Y2UyMjNkNTFkZjQ5YmIxNzU0NjIzMzdhOTc5ZmYyMjdjOWM3Yzc4MDZkMmRjMTE0NGYiLCAidGdfNjQ5NTciOiAiZTYzY2I3ODE3ZjE2OTdjYzZkNzE3MTMwMTEzZDcyNGFjODgyOTVmYzU0MDY1Y2U2ZjkzOGNjNTIxZjU4NDJjMiIsICJ0Z183NjAyOSI6ICJiNDczZmM5MmQ3ODlhMDZmY2Q2MjUyN2VkOTRhYWVmYzRlOGVlYTcyMDRjNTgwOTMxNWYxMDc0MDU3ZmJlYTJiIiwgInRnXzY2MTk4IjogIjcwMzI1MWRiNzY5OTczZGI1MTBiMzRmMTg2ZGMzOGQyNWE5NjQzYjdhODQ5MDcxNTUyYmRmMGVlNzQ0Y2VmMmYiLCAidGdfNDk3NTAiOiAiMWYwNWJlMGQ0ODM1NjlhZGVkMWQ1OTk4NDk1ZjZjY2FlYmI5YzJiNWIyYjFhOWU5NDUyY2Y2NWY0OWY5NmUzYiIsICJ0Z18xMjMyNDciOiAiY2M3Zjc3NTM1ZDkzOWM5ZTY3NWZlODVmMDIzYzAxNTY3MTQ1YWY3MGY2ZmNhYmIxYmI5Y2JiOTlmNThhM2M4NSIsICJ0Z180ODE4NCI6ICJkM2Y5NDU2OTU2ZWVlMTMzMGE4M2M1OGUwYTJkNmNmMjM1MjcyYTFjMmFlMmRmOTRmZTQ4MTU5MTc0ZTVjZmU4IiwgInRnXzMyMzIyIjogIjhlYzU5MzE3Mzc3ZDI3YzgyNWNmYTk3ODMzNzMzNDMxMDY3OGIwN2E0NzQ3NTE4NzllZjNiMWNkODQyZWQ4OGEiLCAidGdfMjgyNzIiOiAiNmYzYzQ2MDQzMzNhY2E0MDA4YTU4MDFjNDY1MDY4NzliMGEwYTQ1MmExZWM3YjNkYzE4M2EwYTk1NDVmYjI2NiIsICJ0Z183NDU4IjogIjBhNDJlZTY0MzdiNjRjNDUwMTQ0ZWI0ZTZjYjBmZDQ0ZjAyMjIxNzE1OWYxMzkxZjRmYjYyZmFlMjNkOTZkMWYifSwgImhvdGVsLXBvc3RzLW1heC54bWwiOiB7Im1heF8xMjEyMCI6ICJlNThlOGQyODNiMzFiYmQ1ZmNkZDI4YTQwZDVhMzNlNmU1MmI3ODgxMjYyYzEwNzFkNzI0ZTY2YjRhZWExZTE0IiwgIm1heF82NDk1NyI6ICJjNDMxNWZhNGMyYTYwM2E0NzMxNzY3NDk4MWUzMTM3MTExOTE4NGU2MDY4YmY4ZTUyNmVkNWU0NDg2M2U4ODU2IiwgIm1heF83NjAyOSI6ICJmYjA1ODRiZGRhMmVlN2U3OWEwNjUxYzRkNWExNGM0Mjg5ZDNjYmRjMjU4NzkyMjk0ZmZkMTZkNTYyZTNlZjE3IiwgIm1heF82NjE5OCI6ICI5ZGQ2NmIzYTdhNDQ2MTVkZTU0YmE2ZTBhNzBhZGRhNTM2ZGI5ZjEzODExNzU5MGZiZGJjOWQ0NGNiMGYwZTU5IiwgIm1heF80OTc1MCI6ICJiMmE3NjA0Y2U0OTcwOTU5YTYxMTU2MDY5MmEwOGJkZmY2NGU5ZmNmNzQ5MDY2NzAwNDMwOTAyZWU1NzFlYmRlIiwgIm1heF8xMjMyNDciOiAiNjQ2MzY3ZjhlZDNlODgxNDZkYmJjNTI0MDMyMzZjYmRmZDljNTIxZmIzZDNjMzU2NDBlNGU5ZjQxNmRkMjcyMCIsICJtYXhfNDgxODQiOiAiMjU4NDRhNDJkOTM3NjMyNGJmNjVlYzNiN2IxNzkzNmRiODI3Njg2MDk0ODhlNzE3NWM5ZDc4NTE5OWQzZWExMSIsICJtYXhfMzIzMjIiOiAiMTBmYjkzMzQ1YzMyNTJmOGNkOTRmYTdlZjVhNjhhMThiOWUwOGI0OWNhYzg4NTJiMTRjN2QzZjI0ZmJhOWRjZCIsICJtYXhfMjgyNzIiOiAiMGExZjZlOTI1ZWM4ZDJiNjFjYTJlNmZmZmE5MTE3YjI0YzNhMzY2MzY4NjgzMzYxYWJmYWNlYWUwZDA4YmJlMCIsICJtYXhfNzQ1OCI6ICI5NjM2NjRkZDRmNzk1MDJiYTAxNjIwOThlODJjZGM1YTEwMTFkZjZmZDljMjcyMmQ4ZGEyY2Q2NjdkNWU2YTE4In0sICJob3RlbC1wb3N0cy1hbGwueG1sIjogeyJ0Z18xMjEyMCI6ICJiNzg2ZTY0NzAxMTMyN2NlMjIzZDUxZGY0OWJiMTc1NDYyMzM3YTk3OWZmMjI3YzljN2M3ODA2ZDJkYzExNDRmIiwgInRnXzY0OTU3IjogImU2M2NiNzgxN2YxNjk3Y2M2ZDcxNzEzMDExM2Q3MjRhYzg4Mjk1ZmM1NDA2NWNlNmY5MzhjYzUyMWY1ODQyYzIiLCAidGdfNzYwMjkiOiAiYjQ3M2ZjOTJkNzg5YTA2ZmNkNjI1MjdlZDk0YWFlZmM0ZThlZWE3MjA0YzU4MDkzMTVmMTA3NDA1N2ZiZWEyYiIsICJ0Z182NjE5OCI6ICI3MDMyNTFkYjc2OTk3M2RiNTEwYjM0ZjE4NmRjMzhkMjVhOTY0M2I3YTg0OTA3MTU1MmJkZjBlZTc0NGNlZjJmIiwgInRnXzQ5NzUwIjogIjFmMDViZTBkNDgzNTY5YWRlZDFkNTk5ODQ5NWY2Y2NhZWJiOWMyYjViMmIxYTllOTQ1MmNmNjVmNDlmOTZlM2IiLCAidGdfMTIzMjQ3IjogImNjN2Y3NzUzNWQ5MzljOWU2NzVmZTg1ZjAyM2MwMTU2NzE0NWFmNzBmNmZjYWJiMWJiOWNiYjk5ZjU4YTNjODUiLCAidGdfNDgxODQiOiAiZDNmOTQ1Njk1NmVlZTEzMzBhODNjNThlMGEyZDZjZjIzNTI3MmExYzJhZTJkZjk0ZmU0ODE1OTE3NGU1Y2ZlOCIsICJ0Z18zMjMyMiI6ICI4ZWM1OTMxNzM3N2QyN2M4MjVjZmE5NzgzMzczMzQzMTA2NzhiMDdhNDc0NzUxODc5ZWYzYjFjZDg0MmVkODhhIiwgInRnXzI4MjcyIjogIjZmM2M0NjA0MzMzYWNhNDAwOGE1ODAxYzQ2NTA2ODc5YjBhMGE0NTJhMWVjN2IzZGMxODNhMGE5NTQ1ZmIyNjYiLCAidGdfNzQ1OCI6ICIwYTQyZWU2NDM3YjY0YzQ1MDE0NGViNGU2Y2IwZmQ0NGYwMjIyMTcxNTlmMTM5MWY0ZmI2MmZhZTIzZDk2ZDFmIiwgIm1heF8xMjEyMCI6ICJlNThlOGQyODNiMzFiYmQ1ZmNkZDI4YTQwZDVhMzNlNmU1MmI3ODgxMjYyYzEwNzFkNzI0ZTY2YjRhZWExZTE0IiwgIm1heF82NDk1NyI6ICJjNDMxNWZhNGMyYTYwM2E0NzMxNzY3NDk4MWUzMTM3MTExOTE4NGU2MDY4YmY4ZTUyNmVkNWU0NDg2M2U4ODU2IiwgIm1heF83NjAyOSI6ICJmYjA1ODRiZGRhMmVlN2U3OWEwNjUxYzRkNWExNGM0Mjg5ZDNjYmRjMjU4NzkyMjk0ZmZkMTZkNTYyZTNlZjE3IiwgIm1heF82NjE5OCI6ICI5ZGQ2NmIzYTdhNDQ2MTVkZTU0YmE2ZTBhNzBhZGRhNTM2ZGI5ZjEzODExNzU5MGZiZGJjOWQ0NGNiMGYwZTU5IiwgIm1heF80OTc1MCI6ICJiMmE3NjA0Y2U0OTcwOTU5YTYxMTU2MDY5MmEwOGJkZmY2NGU5ZmNmNzQ5MDY2NzAwNDMwOTAyZWU1NzFlYmRlIiwgIm1heF8xMjMyNDciOiAiNjQ2MzY3ZjhlZDNlODgxNDZkYmJjNTI0MDMyMzZjYmRmZDljNTIxZmIzZDNjMzU2NDBlNGU5ZjQxNmRkMjcyMCIsICJtYXhfNDgxODQiOiAiMjU4NDRhNDJkOTM3NjMyNGJmNjVlYzNiN2IxNzkzNmRiODI3Njg2MDk0ODhlNzE3NWM5ZDc4NTE5OWQzZWExMSIsICJtYXhfMzIzMjIiOiAiMTBmYjkzMzQ1YzMyNTJmOGNkOTRmYTdlZjVhNjhhMThiOWUwOGI0OWNhYzg4NTJiMTRjN2QzZjI0ZmJhOWRjZCIsICJtYXhfMjgyNzIiOiAiMGExZjZlOTI1ZWM4ZDJiNjFjYTJlNmZmZmE5MTE3YjI0YzNhMzY2MzY4NjgzMzYxYWJmYWNlYWUwZDA4YmJlMCIsICJtYXhfNzQ1OCI6ICI5NjM2NjRkZDRmNzk1MDJiYTAxNjIwOThlODJjZGM1YTEwMTFkZjZmZDljMjcyMmQ4ZGEyY2Q2NjdkNWU2YTE4In19';


/** Pure input/receipt checks shared by the existing post editor and static-feed writer.
 * No supplier calls, database writes, credentials, scheduler or publication transport.
 */
function monitor_price_require(bool $ok,string $reason):void {
    if(!$ok)throw new RuntimeException($reason);
}
/** Bounded verifyChannel evidence only; never retain provider payloads, paths or identities. */
function monitor_price_channel_preflight(string $platform,array $verification):array {
    $states=['ready','unknown','requires_attention'];
    $denied=['channel_admin_required','channel_read_permission_required','channel_edit_permission_required'];
    $identity=['unsupported_platform','invalid_channel_id','canonical_bot_url_mismatch','invalid_expected_public_channel_url',
        'canonical_bot_identity_mismatch','membership_identity_mismatch','channel_type_mismatch','public_channel_identity_mismatch'];
    $reasons=array_merge(['capability_verified','provider_read_failed','verification_failure','request_budget_exhausted'],$denied,$identity);
    $state=in_array($verification['state']??null,$states,true)?$verification['state']:'unknown';
    $reason=in_array($verification['reason']??null,$reasons,true)?$verification['reason']:'unrecognized_verification_result';
    // Accept the same safe projection when carrying the child result into its parent journal.
    $receipt=array_key_exists('receipt',$verification)?$verification['receipt']:$verification;
    $receipt=is_array($receipt)?$receipt:[];$http=$receipt['http_results']??null;
    $reads=$receipt['request_count']??null;$writes=$receipt['write_attempts']??null;
    $out=['platform'=>in_array($platform,['max','telegram'],true)?$platform:'unknown','state'=>$state,'reason'=>$reason,
        'request_count'=>is_int($reads)&&$reads>=0&&$reads<=5?$reads:null,
        'write_attempts'=>is_int($writes)&&$writes>=0&&$writes<=1?$writes:null,
        'http_results'=>[],'http_results_truncated'=>($receipt['http_results_truncated']??false)===true||(is_array($http)&&count($http)>5)];
    $valid=is_array($http)&&array_is_list($http)&&!$out['http_results_truncated']&&$out['platform']!=='unknown'
        &&($verification['state']??null)===$state&&($verification['reason']??null)===$reason
        &&(!array_key_exists('receipt_valid',$receipt)||$receipt['receipt_valid']===true)
        &&$out['request_count']!==null&&$out['request_count']===count($http)&&$out['write_attempts']===0;
    foreach(is_array($http)?array_slice($http,0,5):[] as $item){
        $item=is_array($item)?$item:[];
        $operation=in_array($item['operation']??null,['bot_identity','channel_rights','channel_type'],true)?$item['operation']:'unknown';
        $status=$item['http']??null;$transport=$item['transport_error']??null;
        $status=is_int($status)&&$status>=0&&$status<=599?$status:null;$transport=is_bool($transport)?$transport:null;
        $valid=$valid&&$operation!=='unknown'&&$status!==null&&$transport!==null;
        $out['http_results'][]=['operation'=>$operation,'http'=>$status,'transport_error'=>$transport];
    }
    $error='provider_channel_verification_failed';
    if($state==='unknown'&&in_array($reason,['provider_read_failed','verification_failure'],true))$error='provider_channel_preflight_unavailable';
    elseif($state==='requires_attention'&&in_array($reason,$denied,true))$error='provider_channel_permission_denied';
    elseif($state==='requires_attention'&&in_array($reason,$identity,true))$error='provider_channel_identity_mismatch';
    elseif($state==='requires_attention'&&$reason==='request_budget_exhausted')$error='provider_channel_preflight_unavailable';
    elseif($state==='ready'&&$reason==='capability_verified'&&$valid)$error=null;
    $out['receipt_valid']=$valid;$out['error']=$error;return $out;
}
/** Exact compatibility for five accepted pre-batch publications; publisher null is a real role. */
function monitor_price_legacy_binding(array $row):array {
    $roles=[310=>null,469=>null,498=>228,1971=>null,1981=>877];
    $tv=$row['tourvisor_hotel_id']??null;$b=$row['legacy_proof']??null;
    $why='canonical_original_receipt_mismatch';
    monitor_price_require(is_int($tv)&&array_key_exists($tv,$roles)&&is_array($b)
        &&($b['schema_version']??null)===1&&($b['kind']??null)==='confirmed_legacy_price_receipt_v1'
        &&($b['native_hotel_id']??null)===$tv&&($b['expected_country_id']??null)===1
        &&array_key_exists('hotel_id',$row)&&$row['hotel_id']===$roles[$tv]
        &&array_key_exists('publisher_hotel_id',$b)&&$b['publisher_hotel_id']===$roles[$tv]
        &&($b['identity_provenance']??null)===($roles[$tv]===null?'accepted_native_catalog_read':'accepted_typed_bridge')
        &&!array_key_exists('original_publication_operation',$row)&&!array_key_exists('cover_sha256',$row)
        &&!array_key_exists('original_text_sha256',$row),'canonical_original_receipt_mismatch');
    $prior=$b['prior_price']??[];$contexts=$b['contexts']??[];
    monitor_price_require(($prior['operation']??null)==='commercial-price-update-20261105-legacy9-pass1-h'.$tv.'-v1'
        &&($prior['source_key']??null)==='legacy-'.$tv
        &&is_string($prior['manifest_hash']??null)&&preg_match('/\A[a-f0-9]{64}\z/D',$prior['manifest_hash'])===1
        &&is_string($prior['text_sha256']??null)&&preg_match('/\A[a-f0-9]{64}\z/D',$prior['text_sha256'])===1
        &&is_string($b['context_frame_sha256']??null)&&preg_match('/\A[a-f0-9]{64}\z/D',$b['context_frame_sha256'])===1
        &&is_array($contexts)&&count($contexts)===2&&isset($contexts['max'],$contexts['telegram'])
        &&($row['key']??null)==='prior-priced-search-link-'.$tv&&is_string($row['logical_id']??null),$why);
    foreach(['max','telegram'] as $platform){
        $p=$contexts[$platform];$source=$platform==='max'?'max_anytour_msk1':'tg_anytour_msk';
        monitor_price_require(is_array($p)&&is_int($p['publication_id']??null)&&$p['publication_id']>0
            &&is_int($p['version_id']??null)&&$p['version_id']>0
            &&is_int($p['baseline_version_id']??null)&&$p['baseline_version_id']>$p['version_id']
            &&($p['logical_id']??null)===$row['logical_id']&&($p['source_key']??null)===$source
            &&is_string($p['message_id']??null)&&preg_match('/\A[A-Za-z0-9_.-]{1,191}\z/D',$p['message_id'])===1
            &&is_string($p['photo_signature']??null)&&preg_match('/\A'. $platform.':[A-Za-z0-9_-]+\z/D',$p['photo_signature'])===1
            &&is_string($p['post_url']??null)&&($row['posts'][$platform]??null)===$p['post_url'],$why);
        foreach(['start_payload_sha256','button_url_sha256','baseline_text_sha256'] as $field)
            monitor_price_require(is_string($p[$field]??null)&&preg_match('/\A[a-f0-9]{64}\z/D',$p[$field])===1,$why);
        $prefix=$platform==='max'?'https://max.ru/channel_anytour/':'https://t.me/any_toour/';
        monitor_price_require(str_starts_with($p['post_url'],$prefix)
            &&preg_match('/\A[A-Za-z0-9_-]+\z/D',substr($p['post_url'],strlen($prefix)))===1,$why);
        if(array_key_exists('expected_text_sha256',$row))monitor_price_require(($row['expected_text_sha256'][$platform]??null)===$p['baseline_text_sha256'],$why);
    }
    monitor_price_require($contexts['max']['publication_id']!==$contexts['telegram']['publication_id'],$why);
    return $b;
}

/** Read-only parser for the original own price journal, not a replacement sender receipt. */
function monitor_price_legacy_receipts(array $row,string $raw):array {
    $b=monitor_price_legacy_binding($row);$prior=$b['prior_price'];$why='canonical_original_receipt_mismatch';
    monitor_price_require($raw!==''&&strlen($raw)<=10000000&&str_ends_with($raw,"\n"),'hook_legacy_source_incomplete_stop');
    $last=null;$completed=null;
    foreach(explode("\n",substr($raw,0,-1)) as $line){
        try{$entry=json_decode($line,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('hook_legacy_source_incomplete_stop');}
        monitor_price_require(is_array($entry)&&($entry['operation']??null)===$prior['operation']
            &&($entry['manifest_hash']??null)===$prior['manifest_hash'],$why);
        monitor_price_require($completed===null||$completed===$entry,'hook_legacy_source_unresolved_stop');
        if(($entry['complete']??false)===true)$completed=$entry;
        $last=$entry;
    }
    monitor_price_require(($last['complete']??false)===true&&is_array($last['posts']??null)
        &&count($last['posts'])===2&&is_array($last['configs']??null),'hook_legacy_source_unresolved_stop');
    foreach($last['configs'] as $config)monitor_price_require(($config['state']??null)==='confirmed','hook_legacy_source_unresolved_stop');
    $receipts=[];
    foreach(['max','telegram'] as $platform){
        $member=$last['posts'][$platform.':'.$prior['source_key']]??[];$r=$member['receipt']??[];$c=$b['contexts'][$platform];
        monitor_price_require(($member['state']??null)==='confirmed','hook_legacy_source_unresolved_stop');
        foreach(['publication_id','message_id','version_id','logical_id','source_key','post_url','photo_signature'] as $field)
            monitor_price_require(($r[$field]??null)===$c[$field],$why);
        monitor_price_require(($r['platform']??null)===$platform&&($r['state']??null)==='sent'
            &&($r['body_photo_button_verified']??null)===true&&($r['existing_message_reused']??null)===true
            &&($r['text_sha256']??null)===$prior['text_sha256']&&is_string($r['button_url']??null)
            &&hash_equals($c['button_url_sha256'],hash('sha256',$r['button_url'])),$why);
        $receipts[$platform]=$r;
    }
    return $receipts;
}

/** Immutable physical identity/photo/token; initial caption is pinned separately from later monitor receipts. */
function monitor_price_legacy_publication_matches(array $p,array $row,string $platform,string $buttonUrl):bool {
    $b=monitor_price_legacy_binding($row);$c=$b['contexts'][$platform]??null;$s=$p['snapshot']??[];
    if(!is_array($c))return false;
    $media=$s['attachments']??[];$m=$media[0]??[];
    if(!is_array($media)||count($media)!==1||($m['type']??null)!=='image')return false;
    $photo=(string)($m['provider']??'').':'.(string)($m['photo_id']??$m['file_unique_id']??'');
    return ($p['project_key']??null)==='anytour'&&($p['platform']??null)===$platform&&($p['state']??null)==='sent'
        &&($p['bot_key']??null)===($platform==='max'?'id9704048781_1_bot':'anyru_bot')
        &&($p['channel_id']??null)===($platform==='max'?'-80492244209683':'-1002262666616')
        &&(int)($p['publication_id']??0)===$c['publication_id']&&($p['external_message_id']??null)===$c['message_id']
        &&($p['source_key']??null)===$c['source_key']&&($s['post_url']??null)===$c['post_url']
        &&empty($p['claim_token'])&&empty($p['claimed_at'])&&$photo===$c['photo_signature']
        &&is_string($p['start_payload']??null)&&$p['start_payload']!==''
        &&hash_equals($c['start_payload_sha256'],hash('sha256',$p['start_payload']))
        &&hash_equals($c['button_url_sha256'],hash('sha256',$buttonUrl));
}
function monitor_price_legacy_initial_caption_matches(array $p,array $row,string $platform):bool {
    $b=monitor_price_legacy_binding($row);$c=$b['contexts'][$platform]??null;$s=$p['snapshot']??[];
    return is_array($c)&&($s['metadata']['monitor_price_record_id']??null)===null
        &&(int)($p['publication_id']??0)===$c['publication_id']&&(int)($p['version_id']??0)===$c['baseline_version_id']
        &&is_string($s['text']??null)&&hash_equals($c['baseline_text_sha256'],hash('sha256',$s['text']));
}
/** The original caption editor used manifest_sha256, never a publisher hotel identity. */
function monitor_price_caption_source(array $source):void {
    $pins=[
        'commercial-content-quality-20261108-cohort159-batch002-v1'=>'323ae3b92705ab9b97035227da5df7353f942b1790aae087795607e8d8e34403',
        'commercial-content-quality-20261108-cohort159-batch006-v1'=>'f8e313cbdd57bd7df1431cc5301c4d9bb6839dfcfa24b0dcc44cbe8e4ad3bd78',
    ];
    monitor_price_require(count($source)===4&&is_string($source['operation']??null)&&isset($pins[$source['operation']])
        &&($source['manifest_sha256']??null)===$pins[$source['operation']]
        &&($source['installed_sha']??null)==='160e019701165cd53dd5a9be0fa3db533d82120e'
        &&($source['row_count']??null)===50,'canonical_original_receipt_mismatch');
}
function monitor_price_caption_receipt_fields():array {
    return ['hotel_id','platform','publication_id','previous_version_id','version_id','message_id','post_url',
        'text_sha256','previous_text_sha256','photo_signature','previous_attachments_sha256','attachments_sha256',
        'button_url_sha256','button_text','source_key','state','body_photo_button_verified','formatting_verified',
        'existing_message_reused','existing_token_resolves_to_current_version'];
}
/** Only these three imported pairs lack an original sender; native identity is independently pinned. */
function monitor_price_caption_binding(array $row):array {
    $scope=[381=>['rehana','at-hotel-rehana',71,104,'002'],67910=>['rixos','at-hotel-rixos',70,101,'006'],68323=>['solara','at-hotel-solara',72,105,'006']];
    $tv=$row['tourvisor_hotel_id']??null;$b=$row['caption_proof']??null;$why='canonical_original_receipt_mismatch';
    monitor_price_require(is_int($tv)&&isset($scope[$tv])&&is_array($b)
        &&($b['schema_version']??null)===1&&($b['kind']??null)==='confirmed_caption_receipt_v1'
        &&($b['native_hotel_id']??null)===$tv&&array_key_exists('hotel_id',$row)&&$row['hotel_id']===null
        &&array_key_exists('publisher_hotel_id',$b)&&$b['publisher_hotel_id']===null
        &&($b['identity_provenance']??null)==='accepted_native592_post_binding'
        &&($b['native_projection_sha256']??null)==='f017b9445949557199053b852cec3881bfeb0c36602aef8ad18f9db55cdcb19f'
        &&($row['key']??null)===$scope[$tv][0]&&($row['logical_id']??null)===$scope[$tv][1],$why);
    foreach(['original_publication_operation','original_text_sha256','cover_sha256','legacy_proof'] as $field)
        monitor_price_require(!array_key_exists($field,$row),$why);
    monitor_price_require(is_array($b['source']??null),$why);monitor_price_caption_source($b['source']);
    monitor_price_require($b['source']['operation']==='commercial-content-quality-20261108-cohort159-batch'.$scope[$tv][4].'-v1'
        &&is_array($b['contexts']??null)&&count($b['contexts'])===2&&isset($b['contexts']['max'],$b['contexts']['telegram']),$why);
    foreach(['max','telegram'] as $i=>$platform){
        $c=$b['contexts'][$platform];
        monitor_price_require(is_array($c)&&count($c)===count(monitor_price_caption_receipt_fields())
            &&count(array_diff(monitor_price_caption_receipt_fields(),array_keys($c)))===0
            &&$c['hotel_id']===$tv&&$c['platform']===$platform&&$c['publication_id']===$scope[$tv][$i+2]
            &&is_int($c['previous_version_id'])&&$c['previous_version_id']>0&&is_int($c['version_id'])&&$c['version_id']>$c['previous_version_id']
            &&is_string($c['message_id'])&&preg_match('/\A[A-Za-z0-9_.-]{1,191}\z/D',$c['message_id'])===1
            &&is_string($c['post_url'])&&($row['posts'][$platform]??null)===$c['post_url']
            &&is_string($c['photo_signature'])&&preg_match('/\A'.$platform.':[A-Za-z0-9_-]+\z/D',$c['photo_signature'])===1
            &&$c['source_key']===($platform==='max'?'max_anytour_msk1':'tg_anytour_msk')
            &&$c['state']==='sent'&&$c['button_text']==='✈️ Узнать стоимость тура',$why);
        foreach(['text_sha256','previous_text_sha256','previous_attachments_sha256','attachments_sha256','button_url_sha256'] as $field)
            monitor_price_require(is_string($c[$field])&&preg_match('/\A[a-f0-9]{64}\z/D',$c[$field])===1,$why);
        foreach(['body_photo_button_verified','formatting_verified','existing_message_reused','existing_token_resolves_to_current_version'] as $field)
            monitor_price_require($c[$field]===true,$why);
        $prefix=$platform==='max'?'https://max.ru/channel_anytour/':'https://t.me/any_toour/';
        monitor_price_require(str_starts_with($c['post_url'],$prefix)&&preg_match('/\A[A-Za-z0-9_-]+\z/D',substr($c['post_url'],strlen($prefix)))===1
            &&($platform!=='telegram'||$c['post_url']===$prefix.$c['message_id']),$why);
        if(array_key_exists('expected_text_sha256',$row))monitor_price_require(($row['expected_text_sha256'][$platform]??null)===$c['text_sha256'],$why);
    }
    return $b;
}
/** Read a caller-owned locked stream; no paths, writes or network. Cache the returned terminal by source pins. */
function monitor_price_caption_journal($stream,array $source):array {
    monitor_price_caption_source($source);$why='hook_caption_source_incomplete_stop';
    monitor_price_require(is_resource($stream)&&get_resource_type($stream)==='stream'&&ftell($stream)===0,$why);
    $stat=fstat($stream);monitor_price_require(is_array($stat)&&is_int($stat['size']??null)&&$stat['size']>0&&$stat['size']<=134217728,$why);
    $bytes=0;$last=null;$terminal=null;
    while(($line=fgets($stream))!==false){
        $bytes+=strlen($line);monitor_price_require($bytes<=134217728&&str_ends_with($line,"\n"),$why);
        try{$entry=json_decode($line,true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException($why);}
        monitor_price_require(is_array($entry),'canonical_original_receipt_mismatch');
        foreach($source as $field=>$value)monitor_price_require(($entry[$field]??null)===$value,'canonical_original_receipt_mismatch');
        monitor_price_require(is_bool($entry['complete']??null)&&is_array($entry['posts']??null)&&count($entry['posts'])<=$source['row_count']
            &&empty($entry['error']),'hook_caption_source_unresolved_stop');
        monitor_price_require($terminal===null||$terminal===$entry,'hook_caption_source_unresolved_stop');
        foreach($entry['posts'] as $post)monitor_price_require(is_array($post)&&in_array($post['state']??null,['edit_started','provider_response_recorded','text_edit_returned','confirmed'],true),'hook_caption_source_unresolved_stop');
        if($entry['complete'])$terminal=$entry;
        $last=$entry;
    }
    monitor_price_require(feof($stream)&&$bytes===$stat['size'],$why);
    monitor_price_require(is_array($last)&&($last['complete']??false)===true&&count($last['posts'])===$source['row_count'],'hook_caption_source_unresolved_stop');
    foreach($last['posts'] as $key=>$post){$r=$post['receipt']??[];
        monitor_price_require(($post['state']??null)==='confirmed'&&is_array($r)&&($r['state']??null)==='sent'
            &&in_array($r['platform']??null,['max','telegram'],true)&&is_int($r['publication_id']??null)&&$r['publication_id']>0
            &&$key===$r['platform'].':'.$r['publication_id'],'hook_caption_source_unresolved_stop');
        foreach(['body_photo_button_verified','formatting_verified','existing_message_reused','existing_token_resolves_to_current_version'] as $field)
            monitor_price_require(($r[$field]??null)===true,'hook_caption_source_unresolved_stop');
    }
    return $last;
}
/** Select the exact pair from the original full terminal, retaining only a hash of its saved token. */
function monitor_price_caption_receipts(array $row,array $journal):array {
    $b=monitor_price_caption_binding($row);$why='canonical_original_receipt_mismatch';
    foreach($b['source'] as $field=>$value)monitor_price_require(($journal[$field]??null)===$value,$why);
    monitor_price_require(($journal['complete']??false)===true&&is_array($journal['posts']??null)&&count($journal['posts'])===$b['source']['row_count'],'hook_caption_source_unresolved_stop');
    $receipts=[];
    foreach(['max','telegram'] as $platform){
        $c=$b['contexts'][$platform];$post=$journal['posts'][$platform.':'.$c['publication_id']]??[];$r=$post['receipt']??[];$plan=$post['plan']??[];
        monitor_price_require(($post['state']??null)==='confirmed'&&is_array($r)&&count($r)===count($c)&&is_array($plan),$why);
        foreach($c as $field=>$value)monitor_price_require(array_key_exists($field,$r)&&$r[$field]===$value,$why);
        foreach(['hotel_id','platform','publication_id','message_id','post_url','source_key','photo_signature','button_url_sha256','button_text'] as $field)
            monitor_price_require(($plan[$field]??null)===$c[$field],$why);
        monitor_price_require(($plan['version_id']??null)===$c['previous_version_id']
            &&($plan['new_text_sha256']??null)===$c['text_sha256']&&($plan['old_text_sha256']??null)===$c['previous_text_sha256']
            &&($plan['attachments_sha256']??null)===$c['previous_attachments_sha256']
            &&is_string($plan['new_text']??null)&&hash_equals($c['text_sha256'],hash('sha256',$plan['new_text']))
            &&is_string($plan['old_text']??null)&&hash_equals($c['previous_text_sha256'],hash('sha256',$plan['old_text']))
            &&is_string($plan['token']??null)&&preg_match('/\Ap_[A-Za-z0-9_-]{8,60}\z/D',$plan['token'])===1
            &&is_string($plan['button_url']??null)&&hash_equals($c['button_url_sha256'],hash('sha256',$plan['button_url']))
            &&str_ends_with($plan['button_url'],'?start='.rawurlencode($plan['token'])),$why);
        $r['start_payload_sha256']=hash('sha256',$plan['token']);$receipts[$platform]=$r;
    }
    return $receipts;
}
function monitor_price_caption_publication_matches(array $p,array $row,string $platform,string $buttonUrl,array $sourceReceipt):bool {
    $b=monitor_price_caption_binding($row);$c=$b['contexts'][$platform]??null;$s=$p['snapshot']??[];
    if(!is_array($c))return false;
    foreach($c as $field=>$value)if(($sourceReceipt[$field]??null)!==$value)return false;
    $media=$s['attachments']??[];$m=$media[0]??[];
    if(!is_array($media)||count($media)!==1||($m['type']??null)!=='image')return false;
    $photo=(string)($m['provider']??'').':'.(string)($m['photo_id']??$m['file_unique_id']??'');
    return ($p['project_key']??null)==='anytour'&&($p['platform']??null)===$platform&&($p['state']??null)==='sent'
        &&($p['bot_key']??null)===($platform==='max'?'id9704048781_1_bot':'anyru_bot')
        &&($p['channel_id']??null)===($platform==='max'?'-80492244209683':'-1002262666616')
        &&(int)($p['publication_id']??0)===$c['publication_id']&&($p['external_message_id']??null)===$c['message_id']
        &&($p['source_key']??null)===$c['source_key']&&($s['post_url']??null)===$c['post_url']&&$photo===$c['photo_signature']
        &&empty($p['claim_token'])&&empty($p['claimed_at'])&&is_string($p['start_payload']??null)
        &&is_string($sourceReceipt['start_payload_sha256']??null)
        &&hash_equals($sourceReceipt['start_payload_sha256'],hash('sha256',$p['start_payload']))
        &&hash_equals($c['button_url_sha256'],hash('sha256',$buttonUrl));
}
function monitor_price_caption_initial_caption_matches(array $p,array $row,string $platform):bool {
    $b=monitor_price_caption_binding($row);$c=$b['contexts'][$platform]??null;$s=$p['snapshot']??[];
    return is_array($c)&&($s['metadata']['monitor_price_record_id']??null)===null
        &&(int)($p['publication_id']??0)===$c['publication_id']&&(int)($p['version_id']??0)===$c['version_id']
        &&is_string($s['text']??null)&&hash_equals($c['text_sha256'],hash('sha256',$s['text']));
}
function monitor_price_time($value):int {
    monitor_price_require(is_string($value)&&preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\z/D',$value)===1,'monitor_time_invalid');
    $time=strtotime($value);monitor_price_require($time!==false,'monitor_time_invalid');return $time;
}
function monitor_price_observation(array $snapshot,int $tourvisorId,int $now,bool $requireExportFresh=true):array {
    monitor_price_require(($snapshot['format']??null)==='anytour-hotel-monitor-prices-v1'
        &&($snapshot['identityNamespace']??null)==='tourvisor'&&($snapshot['source']??null)==='scheduled_monitor'
        &&in_array($snapshot['observationWindowHours']??null,[24,36],true)&&($snapshot['supplierRequests']??null)===0&&($snapshot['writes']??null)===0
        &&($snapshot['timeBasis']??null)==='database-relative; stored observation timezone is not asserted','monitor_source_invalid');
    // Keep the window recorded with each snapshot; new policy never extends an old receipt.
    $maximumAge=$snapshot['observationWindowHours']*3600;
    $source=parse_url((string)($snapshot['sourceUrl']??''));
    monitor_price_require(is_array($source)&&($source['scheme']??'')==='https'&&($source['host']??'')==='anytoour.ru'
        &&($source['path']??'')==='/data/hotel-monitor-prices-read-v1.php'&&!isset($source['user'])&&!isset($source['pass'])&&!isset($source['port'])&&!isset($source['fragment']),'monitor_source_url_invalid');
    $scope=$snapshot['scope']??[];$item=$snapshot['item']??[];$offers=$item['offers']??[];
    monitor_price_require($tourvisorId>0&&in_array($tourvisorId,$scope['tourvisorHotelIds']??[],true)&&($item['tourvisorHotelId']??null)===$tourvisorId
        &&($item['state']??null)==='observed'&&count($offers)>=1&&count($offers)<=3,'monitor_identity_invalid');
    monitor_price_require(($scope['departureId']??null)===1&&($scope['adults']??null)===2&&($scope['childrenCount']??null)===0
        &&($scope['currency']??null)==='RUB'&&($scope['nightsFrom']??null)===5&&($scope['nightsTo']??null)===14,'monitor_scope_invalid');
    $generated=monitor_price_time($snapshot['generatedAt']??null);$previous=0.;$seen=[];
    foreach($offers as $offer){
        $price=$offer['price']??null;$id=$offer['recordId']??null;
        monitor_price_require((is_int($price)||is_float($price))&&is_finite((float)$price)&&$price>0&&$price>=$previous
            &&abs($price*100-round($price*100))<0.00001&&is_string($id)&&preg_match('/\A[a-f0-9]{64}\z/D',$id)===1&&!isset($seen[$id]),'monitor_price_invalid');
        $previous=(float)$price;$seen[$id]=true;
        monitor_price_require(($offer['tourvisorHotelId']??null)===$tourvisorId&&($offer['departureId']??null)===1
            &&($offer['adults']??null)===2&&($offer['childrenCount']??null)===0&&($offer['currency']??null)==='RUB'
            &&is_int($offer['nights']??null)&&$offer['nights']>=5&&$offer['nights']<=14
            &&is_string($offer['tourId']??null)&&$offer['tourId']!==''&&is_string($offer['roomType']??null)&&trim($offer['roomType'])!==''
            &&isset(monitor_price_meals()[$offer['mealId']??0]),'monitor_terms_invalid');
        $day=$offer['departureDate']??'';
        monitor_price_require(is_string($day)&&preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$day)===1
            &&gmdate('Y-m-d',strtotime($day)?:0)===$day&&$day>=($scope['dateFrom']??'9999')&&$day<=($scope['dateTo']??''),'monitor_departure_invalid');
        foreach(['currentPriceVerified','fullPackagePriceVerified','availabilityVerified'] as $flag)monitor_price_require(($offer[$flag]??null)===false,'monitor_verification_claim_invalid');
        monitor_price_require(array_key_exists('fuelIncluded',$offer)&&$offer['fuelIncluded']===null
            &&($offer['source']??null)==='scheduled_monitor'&&($offer['priceKind']??null)==='listing_observation','monitor_price_basis_invalid');
        $age=$offer['ageSecondsAtExport']??null;$expiry=monitor_price_time($offer['expiresAt']??null);
        monitor_price_require(is_int($age)&&$age>=0&&$age<$maximumAge&&$generated<=$now+60&&$age+max(0,$now-$generated)<$maximumAge
            &&$expiry>=$generated&&$expiry-$generated<=min(900,$maximumAge-$age)&&(!$requireExportFresh||$expiry>$now),'monitor_price_expired');
    }
    $minimum=$offers[0];
    return ['record_id'=>$minimum['recordId'],'tourvisor_hotel_id'=>$tourvisorId,'tour_id'=>$minimum['tourId'],'price'=>$minimum['price'],'currency'=>'RUB',
        'observed_at'=>gmdate('Y-m-d\TH:i:s\Z',$generated-$minimum['ageSecondsAtExport']),
        'price_basis'=>'scheduled_monitor_result','fuel_added_separately'=>false,'full_package_price_verified'=>false,'availability_verified'=>false,'offer'=>$minimum];
}
function monitor_price_meals():array {return [2=>'Без питания',3=>'Завтраки',4=>'Завтрак и ужин',5=>'Полный пансион',7=>'Всё включено',9=>'Ультра всё включено'];}
function monitor_price_amount($price):string {return rtrim(rtrim(number_format((float)$price,2,'.',''),'0'),'.');}
function monitor_price_operation(array $price):string {return 'commercial-monitor-price-h'.$price['tourvisor_hotel_id'].'-'.$price['record_id'];}
function monitor_price_validate_text(string $text,array $price):void {
    $o=$price['offer'];$normal=str_replace(["\u{00a0}","\u{202f}"],' ',$text);
    $amount=number_format((float)$price['price'],(floor((float)$price['price'])===(float)$price['price'])?0:2,',',' ');
    $amount=rtrim(str_contains($amount,',')?rtrim($amount,'0'):$amount,',');
    $day=implode('.',array_reverse(explode('-',$o['departureDate'])));$n=$o['nights'];$night=$n>=11&&$n<=14?'ночей':($n%10===1?'ночь':(in_array($n%10,[2,3,4],true)?'ночи':'ночей'));
    $block="💰 От $amount ₽ за двоих\n✈️ Вылет из Москвы $day · $n $night\n👥 2 взрослых\n🛏 ".$o['roomType']."\n🍽 ".monitor_price_meals()[$o['mealId']];
    $closings=[
        140=>'✈️ Подберём категорию под состав семьи и рассчитаем тур в Golden Beach с перелётом на ваши даты.',
        386=>'✈️ Подберём категорию и расположение номера, рассчитаем тур с перелётом на ваши даты.',
        1033=>'✈️ Подберём семейный номер в Barut Hemera и рассчитаем тур с перелётом на ваши даты.',
        1095=>'✈️ Подберём тур в Side Star Resort с перелётом и подходящим номером. Рассчитаем полную стоимость на вашу семью.',
        8053=>'✈️ Подберём номер и питание, рассчитаем тур с перелётом на ваши даты.',
        33313=>'✈️ Подберём номер и питание в Sugar Marina Surf, рассчитаем тур с перелётом на ваши даты и состав семьи.',
        58987=>'✈️ Подберём номер и питание в Seashells, рассчитаем тур с перелётом на ваши даты.',
    ];
    $ctaCount=substr_count($normal,'Узнать стоимость тура')
        +substr_count($normal,'✈️ Подберём подходящий номер и рассчитаем тур с перелётом на ваши даты.');
    $closingAllowed=true;
    foreach($closings as $tv=>$closing){$n=substr_count($normal,$closing);$ctaCount+=$n;
        if($n>0&&(($price['tourvisor_hotel_id']??null)!==$tv||!str_ends_with($normal,"\n\n".$closing)))$closingAllowed=false;
    }
    monitor_price_require(preg_match('/\A✈️ Туры в [^\r\n]+\n/u',$normal)===1&&!preg_match('/с перел[её]том/u',explode("\n",$normal)[0])
        &&substr_count($normal,$block)===1&&substr_count($normal,'💰')===1&&$ctaCount===1&&$closingAllowed
        &&str_contains($normal,'Цена найдена мониторингом '),'monitor_text_conditions_mismatch');
    monitor_price_require(mb_strlen($text,'UTF-8')>0&&strlen(mb_convert_encoding($text,'UTF-16LE','UTF-8'))/2<=1024,'monitor_caption_too_long');
}
/** Feed accepts only a completed two-channel journal from the existing editor. */
function monitor_price_confirmed_journal(array $journal,array $binding,int $now):array {
    monitor_price_require(($journal['complete']??false)===true&&isset($journal['monitor_snapshot'])&&is_array($journal['monitor_snapshot']),'monitor_posts_not_confirmed');
    $price=monitor_price_observation($journal['monitor_snapshot'],(int)($binding['tourvisor_hotel_id']??0),$now,false);
    monitor_price_require(($journal['operation']??'')===monitor_price_operation($price)&&($binding['operation']??'')===$journal['operation']
        &&is_string($binding['manifest_hash']??null)&&hash_equals($journal['manifest_hash']??'',$binding['manifest_hash']),'monitor_journal_binding_changed');
    $expectedBinding=$binding;unset($expectedBinding['operation'],$expectedBinding['manifest_hash']);
    monitor_price_require(($journal['feed_binding']??null)===$expectedBinding,'monitor_feed_binding_changed');
    $receipts=[];
    foreach($journal['posts']??[] as $post){
        $r=$post['receipt']??[];$channel=$r['platform']??'';
        monitor_price_require(($post['state']??'')==='confirmed'&&in_array($channel,['max','telegram'],true)&&!isset($receipts[$channel])
            &&($r['state']??'')==='sent'&&($r['existing_message_reused']??false)===true&&($r['body_photo_button_verified']??false)===true
            &&($r['monitor_price']??null)===$price&&is_string($r['text']??null)&&hash('sha256',$r['text'])===($r['text_sha256']??'')
            &&($r['post_url']??'')===($binding['posts'][$channel]??null),'monitor_receipt_mismatch');
        monitor_price_validate_text($r['text'],$price);$receipts[$channel]=$r;
    }
    monitor_price_require(count($receipts)===2,'monitor_posts_not_confirmed');
    return ['price'=>$price,'binding'=>$binding,'receipts'=>$receipts];
}
/** Exact patch of target offer blocks. Other URLs, IDs, photos and blocks stay byte-identical. */
function monitor_price_render_feed(string $xml,array $updates,string $name,int $at):string {
    monitor_price_require(in_array($name,['hotel-posts-max.xml','hotel-posts-telegram.xml','hotel-posts-all.xml'],true)
        &&stripos($xml,'<!DOCTYPE')===false&&stripos($xml,'<!ENTITY')===false&&stripos($xml,'available=')===false,'monitor_feed_invalid');
    $dom=new DOMDocument();$prior=libxml_use_internal_errors(true);
    try{$loaded=$dom->loadXML($xml,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
    monitor_price_require($loaded&&$dom->documentElement?->tagName==='yml_catalog','monitor_feed_invalid');
    $elements=[];foreach($dom->getElementsByTagName('offer') as $e){$id=$e->getAttribute('id');monitor_price_require($id!==''&&!isset($elements[$id]),'monitor_feed_duplicate');$elements[$id]=$e;}
    $targets=[];
    foreach($updates as $update)foreach(['max','telegram'] as $channel){
        if(($name==='hotel-posts-max.xml'&&$channel!=='max')||($name==='hotel-posts-telegram.xml'&&$channel!=='telegram'))continue;
        $b=$update['binding'];$id=$b['offer_ids'][$channel]??'';$url=$b['destinations'][$channel]??'';
        monitor_price_require(is_string($id)&&preg_match('/\A'.($channel==='max'?'max':'tg').'_[A-Za-z0-9_-]+\z/D',$id)===1&&!isset($targets[$id])&&isset($elements[$id]),'monitor_feed_target_invalid');
        $e=$elements[$id];$urls=$e->getElementsByTagName('url');
        monitor_price_require($urls->length===1&&$urls->item(0)->textContent===$url&&$e->getElementsByTagName('description')->length===1
            &&$e->getElementsByTagName('price')->length<=1&&$e->getElementsByTagName('currencyId')->length<=1,'monitor_feed_destination_changed');
        $text=$update['receipts'][$channel]['text'];$targets[$id]=['price'=>$update['price']['price'],'text'=>$text];
    }
    monitor_price_require(count($targets)>0,'monitor_feed_targets_empty');$found=[];
    $next=preg_replace_callback('/<offer id="([A-Za-z0-9_-]+)">.*?<\/offer>/s',static function(array $m)use($targets,&$found):string{
        $id=$m[1];if(!isset($targets[$id]))return $m[0];monitor_price_require(!isset($found[$id]),'monitor_feed_duplicate');$found[$id]=true;
        $block=preg_replace('/\s*<(price|currencyId|description)>.*?<\/\1>/s','',$m[0]);
        monitor_price_require(is_string($block)&&substr_count($block,'</url>')===1,'monitor_feed_block_invalid');
        $value=$targets[$id];$addition="\n        <price>".monitor_price_amount($value['price'])."</price>\n        <currencyId>RUB</currencyId>\n        <description>".htmlspecialchars($value['text'],ENT_XML1|ENT_QUOTES,'UTF-8').'</description>';
        return str_replace('</url>','</url>'.$addition,$block);
    },$xml);
    monitor_price_require(is_string($next)&&count($found)===count($targets),'monitor_feed_target_not_patched');
    $date=(new DateTimeImmutable('@'.$at))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d H:i');
    $next=preg_replace('/<yml_catalog date="[^"<>]+">/','<yml_catalog date="'.$date.'">',$next,1,$changed);
    monitor_price_require(is_string($next)&&$changed===1,'monitor_feed_date_invalid');return $next;
}


function tuple_hash(array $v): string {return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
function stable_offer(array $v):array {unset($v['ageSecondsAtExport'],$v['expiresAt']);ksort($v);return $v;}
function read_photo_signature(array $s):string {$m=$s['attachments']??[];need(count($m)===1&&($m[0]['type']??null)==='image','photo_shape_changed');return (string)($m[0]['provider']??'').':'.(string)($m[0]['photo_id']??$m[0]['file_unique_id']??'');}
function read_public(string $url):string {
 $c=curl_init($url);$raw='';curl_setopt_array($c,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>40,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache'],CURLOPT_WRITEFUNCTION=>static function($c,string $x)use(&$raw):int{if(strlen($raw)+strlen($x)>2000000)return 0;$raw.=$x;return strlen($x);}]);$ok=curl_exec($c);$http=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);need($ok===true&&$http===200,'stored_reader_failed');return $raw;
}

$pdo=null;$handles = [];
function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function read_git(array $args): string {
    $lines=[];$status=0;
    exec('GIT_OPTIONAL_LOCKS=0 git -c core.fsmonitor=false -c core.untrackedCache=false '.implode(' ',array_map('escapeshellarg',$args)).' 2>/dev/null',$lines,$status);
    need($status===0,'runtime_git_failed');return implode("\n",$lines);
}
function snapshot(string $path, int $maximum, array &$handles): array {
    need(!is_link($path) && is_file($path) && realpath($path) === $path, 'read_path_invalid');
    $f = fopen($path, 'rb'); need(is_resource($f), 'read_open_failed');
    $handles[] = $f;
    need(flock($f, LOCK_SH | LOCK_NB), 'read_busy_no_retry');
    $before = fstat($f); $named = lstat($path);
    need(is_array($before) && is_array($named) && $before['ino'] === $named['ino'] && $before['dev'] === $named['dev'], 'read_identity_changed');
    need($before['size'] > 0 && $before['size'] <= $maximum, 'read_size_invalid');
    $raw = stream_get_contents($f, $maximum + 1);
    need(is_string($raw) && strlen($raw) === $before['size'], 'read_size_changed');
    clearstatcache(true, $path); $after = fstat($f); $named = lstat($path);
    foreach (['ino', 'dev', 'size', 'mtime', 'ctime'] as $k) {
        need(($after[$k] ?? null) === $before[$k] && ($named[$k] ?? null) === $before[$k], 'read_changed');
    }
    return ['raw' => $raw, 'bytes' => strlen($raw), 'sha256' => hash('sha256', $raw)];
}
/** Read only one complete final state, regardless of total historical journal size. */
function tail_snapshot(string $path, int $maximum, array &$handles, array &$evidence): string {
    need(!is_link($path) && is_file($path) && realpath($path) === $path, 'global_path_invalid');
    $f = fopen($path, 'rb'); need(is_resource($f), 'global_open_failed'); $handles[] = $f;
    need(flock($f, LOCK_SH | LOCK_NB), 'global_busy_no_retry');
    $before = fstat($f); $named = lstat($path);
    need(is_array($before) && is_array($named), 'global_stat_failed');
    $evidence = array_intersect_key($before, array_flip(['size','ino','dev','mtime','ctime']));
    $evidence['hash_scope'] = 'last_complete_line_only_not_entire_journal';
    need($before['ino'] === $named['ino'] && $before['dev'] === $named['dev'], 'global_identity_changed');
    need($before['size'] > 0, 'global_empty_stop');
    $offset = max(0, $before['size'] - $maximum);
    need(fseek($f, $offset, SEEK_SET) === 0, 'global_seek_failed');
    $raw = stream_get_contents($f, $maximum + 1);
    need(is_string($raw) && strlen($raw) === $before['size'] - $offset, 'global_tail_size_changed');
    need(str_ends_with($raw, "\n"), 'global_journal_incomplete');
    $body = substr($raw, 0, -1); $separator = strrpos($body, "\n");
    need($offset === 0 || $separator !== false, 'global_last_line_exceeds_bound');
    $last = $separator === false ? $body : substr($body, $separator + 1);
    need($last !== '', 'global_last_line_empty');
    clearstatcache(true, $path); $after = fstat($f); $named = lstat($path);
    foreach (['ino','dev','size','mtime','ctime'] as $key) {
        need(($after[$key] ?? null) === $before[$key] && ($named[$key] ?? null) === $before[$key], 'global_changed');
    }
    $evidence['tail_offset'] = $offset; $evidence['tail_bytes_read'] = strlen($raw);
    $evidence['last_line_offset'] = $before['size'] - strlen($last) - 1;
    $evidence['last_line_bytes'] = strlen($last) + 1;
    $evidence['last_line_sha256'] = hash('sha256', $last . "\n");
    return $last;
}
/** Return only recognized provider error descriptions; never arbitrary text/URLs/tokens. */
function safe_description(mixed $value): ?string {
    if (!is_string($value)) return null;
    $allowed = [
        'Bad Request: message is not modified', 'Bad Request: message to edit not found',
        "Bad Request: message can't be edited", 'Bad Request: message caption is too long',
        'Bad Request: caption is too long', 'Bad Request: chat not found',
        'Bad Request: MESSAGE_ID_INVALID', 'Unauthorized', 'Forbidden: bot was kicked from the channel chat',
        'Forbidden: bot is not a member of the channel chat', 'Bad Request: not enough rights to edit the message',
    ];
    if (in_array($value,$allowed,true)) return $value;
    if (preg_match('/\AToo Many Requests: retry after [0-9]{1,9}\z/D',$value)===1) return $value;
    if (str_starts_with($value,'Bad Request: message is not modified:')) return 'Bad Request: message is not modified [details omitted]';
    if (str_starts_with($value,"Bad Request: can't parse entities:")) return "Bad Request: can't parse entities [details omitted]";
    return '[unrecognized provider description omitted]';
}
function provider_projection(array $r): array {
    $o=[];
    if(is_int($r['http']??null))$o['http']=$r['http'];
    $d=$r['data']??null;
    if(is_array($d)){
        $o['data']=[];
        if(is_bool($d['ok']??null))$o['data']['ok']=$d['ok'];
        if(is_int($d['error_code']??null))$o['data']['error_code']=$d['error_code'];
        if(is_string($d['description']??null)){
            $o['data']['description']=safe_description($d['description']);
            $o['data']['description_bytes']=strlen($d['description']);
        }
        if(is_int($d['parameters']['retry_after']??null))$o['data']['parameters']=['retry_after'=>$d['parameters']['retry_after']];
        $o['data']['result_present']=is_array($d['result']??null);
    }
    foreach(['transport_error','curl_error','error'] as $k) if(array_key_exists($k,$r)) {
        $v=$r[$k];$o[$k.'_present']=!empty($v);
        if(is_int($v)||is_bool($v)||$v===null)$o[$k]=$v;
        elseif(is_string($v))$o[$k.'_bytes']=strlen($v);
    }
    foreach(['curl_errno','errno'] as $k)if(is_int($r[$k]??null))$o[$k]=$r[$k];
    return $o;
}
function projection(array $record): array {
    $out = [];
    foreach (['operation','feed_operation','event','type','state','phase','at','reason','error','complete','tourvisor_hotel_id','hotel_id','platform','publication_id','version_id','message_id','manifest_hash','manifest_sha256','text_sha256','old_text_sha256','new_text_sha256','photo_sha256','body_photo_button_verified','post_write_attempts','db_writes','message_sends','exit_code','child_exit_code'] as $key) {
        if (!array_key_exists($key, $record)) continue;
        $v = $record[$key];
        if (is_int($v) || is_bool($v) || $v === null) $out[$key] = $v;
        elseif (is_string($v) && strlen($v) <= 200 && preg_match('/\A[A-Za-z0-9_:.+\/-]*\z/D', $v) === 1) $out[$key] = $v;
    }
    if(is_array($record['provider_response']??null))$out['provider_response']=provider_projection($record['provider_response']);
    if(is_array($record['plan']??null)){
        $p=$record['plan'];$out['plan']=[];
        if(is_bool($p['update_text']??null))$out['plan']['update_text']=$p['update_text'];
        if(in_array($p['platform']??null,['max','telegram'],true))$out['plan']['platform']=$p['platform'];
        if(is_string($p['text']??null)){
            $out['plan']['text_bytes']=strlen($p['text']);
            $out['plan']['text_sha256']=hash('sha256',$p['text']);
            if(function_exists('mb_strlen'))$out['plan']['text_characters']=mb_strlen($p['text'],'UTF-8');
            if(function_exists('mb_convert_encoding'))$out['plan']['text_utf16_units']=strlen(mb_convert_encoding($p['text'],'UTF-16LE','UTF-8'))/2;
        }
    }
    foreach (['receipt','child_failure','terminal'] as $key) if (is_array($record[$key] ?? null)) $out[$key] = projection($record[$key]);
    foreach (['posts','configs'] as $key) if (is_array($record[$key] ?? null)) {
        $out[$key . '_count'] = count($record[$key]);
        $out[$key] = [];
        foreach (array_slice($record[$key],0,8) as $item) if (is_array($item)) $out[$key][] = projection($item);
    }
    return $out;
}
$out = ['ok'=>false,'mode'=>'read_only_pending_price_operation','checked_at'=>gmdate('c'),'server_writes'=>0,'post_writes'=>0,'feed_writes'=>0,'supplier_calls'=>0,'customer_messages'=>0];
try {
    $root = '/var/www/anytoour/data/channel-post-operations';
    need(!is_link($root) && realpath($root) === $root, 'private_root_changed');
    $out['global_journal'] = [];
    $last = tail_snapshot($root.'/monitor-price-after-collection-v1.jsonl', 4194304, $handles, $out['global_journal']);
    $state = json_decode($last, true, 64, JSON_THROW_ON_ERROR);
    need(is_array($state) && array_key_exists('pending', $state), 'global_state_invalid');
    $out['completed_hotels'] = is_array($state['completed'] ?? null) ? count($state['completed']) : null;
    $out['held_hotels'] = is_array($state['holds'] ?? null) ? count($state['holds']) : null;
    $cycle = $state['nightly_cycle'] ?? null;
    $out['nightly_cycle'] = is_array($cycle) ? array_intersect_key($cycle, array_flip(['collector_run_id','state','night_date','started_at','finished_at','updated_pairs','unchanged_pairs','held_pairs','scanned_pairs','remaining_unscanned_pairs'])) : null;
    $pending = $state['pending'];
    need($pending === null || is_array($pending), 'pending_invalid');
    $out['pending'] = $pending === null ? null : projection($pending);
    $out['pending_keys'] = $pending === null ? [] : array_keys($pending);
    $out['cycle_tuple_sha256'] = hash('sha256',json_encode($cycle,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $out['completed_tuple_sha256'] = hash('sha256',json_encode($state['completed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    if (is_array($pending)) {
        $op = $pending['operation'] ?? null;
        need(is_string($op) && preg_match('/\Acommercial-monitor-price-h[1-9][0-9]*-[a-f0-9]{64}\z/D', $op) === 1, 'pending_operation_invalid');
        $dir = $root.'/'.$op;
        need(!is_link($dir) && realpath($dir) === $dir, 'child_directory_invalid');
        $child = snapshot($dir.'/receipt.jsonl', 10000000, $handles);
        need(str_ends_with($child['raw'], "\n"), 'child_journal_incomplete');
        $rows = explode("\n", substr($child['raw'],0,-1)); need(count($rows) <= 1000, 'child_record_bound');
        $out['child_journal'] = ['sha256'=>$child['sha256'],'bytes'=>$child['bytes'],'records'=>count($rows),'entries'=>[]];
        foreach ($rows as $line) {
            $record = json_decode($line, true, 64, JSON_THROW_ON_ERROR); need(is_array($record), 'child_record_invalid');
            $entry = projection($record); $entry['keys'] = array_slice(array_keys($record),0,50);
            $out['child_journal']['entries'][] = $entry;
        }
        $out['pending_feed_files'] = [];
        $out['saved_monitor'] = ['generatedAt'=>$record['monitor_snapshot']['generatedAt'] ?? null, 'offers_count'=>count($record['monitor_snapshot']['item']['offers'] ?? [])];
        foreach (($pending['files'] ?? []) as $file) {
            need(is_array($file), 'pending_feed_file_invalid');
            $name = $file['name'] ?? null;
            need(in_array($name, ['hotel-posts-telegram.xml','hotel-posts-max.xml','hotel-posts-all.xml'],true), 'pending_feed_name_invalid');
            $actual = snapshot('/var/www/anytoour/data/www/app.anytoour.ru/feed/'.$name, 4000000, $handles);
            $out['pending_feed_files'][] = ['name'=>$name,'actual_sha256'=>$actual['sha256'],'expected_sha256'=>$file['sha256'] ?? null,'matches_expected'=>($file['sha256'] ?? null) === $actual['sha256'],'expected_previous_sha256'=>$file['expected_previous_sha256'] ?? null,'matches_previous'=>($file['expected_previous_sha256'] ?? null) === $actual['sha256']];
        }
    }
    $app='/var/www/anytoour/data/www/app.anytoour.ru';
    need(realpath($app)===$app && chdir($app),'app_root_invalid');
    $out['runtime']=['head'=>null,'tracked_clean'=>null,'git_checked'=>false];
    $head=read_git(['rev-parse','HEAD']);
    need(preg_match('/\A[a-f0-9]{40}\z/D',$head)===1,'runtime_head_invalid');
    $status=read_git(['status','--porcelain','--untracked-files=no']);
    need(read_git(['rev-parse','HEAD'])===$head,'runtime_head_changed');
    $out['runtime']=['head'=>$head,'tracked_clean'=>$status==='','git_checked'=>true];
    need($out['runtime']['head']==='cc017ee5bb669f4c7c13efc91d5c5812a50e55f2'&&$out['runtime']['tracked_clean'],'exact_runtime_changed');
    need(($out['global_journal']['last_line_sha256']??null)==='047d01c9f80a82cc83ff0ba0fc09fa82f9d8cc73671f997f44416ea7b2215092','exact_global_tail_changed');
    need(($out['child_journal']['sha256']??null)==='684472cc095e51ce3445b48ceeeac50c89f88f0123cde48c631af044ba4b079c','exact_child_changed');
    need(($pending['tourvisor_hotel_id']??null)===81173&&($pending['operation']??null)==='commercial-monitor-price-h81173-a95d49d1391292a05d61705bbe200f525ba4d5ae9f7780f1c5615c92e88036a2'&&($pending['phase']??null)==='post_started'&&($pending['child_failure']['error']??null)==='telegram_edit_not_confirmed_no_retry'&&($cycle['collector_run_id']??null)==='44204094292'&&($cycle['state']??null)==='started','exact_pending_changed');
    $pins=json_decode(base64_decode(READ_PINS_B64,true),true,32,JSON_THROW_ON_ERROR);
    need(count($pins)===23,'pin_count_invalid');
    foreach($pins as $path=>$pin){need(!is_link($path)&&realpath($path)===$app.'/'.$path,'pin_path_invalid');need(read_git(['hash-object','--',$path])===$pin,'runtime_pin_changed');}
    $gf=$handles[0];need(fseek($gf,0)===0,'global_rewind_failed');$hc=hash_init('sha256');$bytes=0;$lines=0;
    while(!feof($gf)){$chunk=fread($gf,1048576);need(is_string($chunk),'global_stream_failed');$bytes+=strlen($chunk);$lines+=substr_count($chunk,"\n");hash_update($hc,$chunk);}
    need($bytes===$out['global_journal']['size'],'global_size_changed');$out['global_prefix']=['sha256'=>hash_final($hc),'bytes'=>$bytes,'lines'=>$lines];
    $childState=$record;$plans=[];
    foreach($childState['posts']??[] as $entry){$p=$entry['plan']??null;need(is_array($p)&&in_array($p['platform']??null,['max','telegram'],true),'plan_missing');$plans[$p['platform']]=['plan'=>$p,'entry'=>$entry];}
    need(count($plans)===2&&$plans['max']['entry']['state']==='confirmed'&&$plans['telegram']['entry']['state']==='provider_response_recorded','post_states_changed');
    $reject=$plans['telegram']['entry']['provider_response'];need($reject['http']===429&&($reject['data']['ok']??null)===false&&($reject['data']['error_code']??null)===429&&($reject['data']['parameters']['retry_after']??null)===21,'original_rejection_changed');
    $out['tuples']=[];foreach(['monitor_snapshot','feed_binding','posts'] as $k)$out['tuples'][$k.'_sha256']=tuple_hash($childState[$k]);
    $out['tuples']['manifest_hash']=$childState['manifest_hash'];$out['tuples']['pending_sha256']=tuple_hash($pending);
    ob_start();try{require $app.'/config.php';require_once $app.'/services/ChannelPostRuntime.php';require_once $app.'/services/ChannelPostEntryService.php';require_once $app.'/integrations/ChannelPostApiTransport.php';require_once $app.'/services/ChannelPostButtonTransport.php';}finally{ob_end_clean();}
    $pdo=ConversationDb::connection();$pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();$out['database_session_read_only']=true;
    $registry=new ChannelPostRegistryService($pdo);$pubs=[];
    foreach($plans as $platform=>$v){
        $plan=$v['plan'];$p=$registry->publication((int)$plan['publication_id']);need(is_array($p),'publication_missing');
        $t=$platform==='max'?['id9704048781_1_bot','-80492244209683','max_anytour_msk1',9]:['anyru_bot','-1002262666616','tg_anytour_msk',10];
        need($p['project_key']==='anytour'&&$p['platform']===$platform&&$p['bot_key']===$t[0]&&$p['channel_id']===$t[1]&&$p['source_key']===$t[2]&&$p['state']==='sent'&&(string)$p['external_message_id']===(string)$plan['message_id']&&$p['snapshot']['post_url']===$plan['post_url']&&hash_equals($plan['token'],$p['start_payload'])&&(int)$p['channel']['id']===$t[3]&&(int)$p['channel']['config_version']===2&&$p['channel']['button_text']==='✈️ Узнать стоимость тура','publication_context_changed');
        need(read_photo_signature($p['snapshot'])===$plan['photo_signature']&&ChannelPostIncomingAdapter::containsOnlyButton($p['snapshot'],ChannelPostButtonTransport::buttonUrl($p),'✈️ Узнать стоимость тура'),'registry_media_button_changed');
        $version=$platform==='max'?8095:(int)$plan['old_version_id'];need((int)$p['version_id']===$version,'publication_version_changed');
        if($platform==='max')need(hash('sha256',$p['snapshot']['text'])===hash('sha256',$plan['text']),'max_registry_text_changed');
        $pubs[$platform]=$p;$out['registry'][$platform]=['publication_id'=>(int)$p['publication_id'],'version_id'=>$version,'state'=>$p['state'],'message_id'=>(string)$plan['message_id'],'snapshot_sha256'=>tuple_hash($p['snapshot']),'text_sha256'=>hash('sha256',$p['snapshot']['text']),'plan_sha256'=>tuple_hash($plan),'text_matches_plan'=>$p['snapshot']['text']===$plan['text'],'config_version'=>2];
    }
    $mid=$plans['max']['plan']['message_id'];need(is_string($mid)&&preg_match('/\A[A-Za-z0-9_.-]{1,191}\z/D',$mid)===1,'max_id_invalid');
    $out['max_gets']=1;$r=ChannelPostApiTransport::buttonRequest('max','GET','/messages/'.$mid,[],null);need(empty($r['transport_error'])&&(int)$r['http']===200&&is_array($r['data']),'max_read_failed');
    $snap=ChannelPostIncomingAdapter::maxSnapshot($r['data']);$mp=$plans['max']['plan'];need(is_array($snap)&&($snap['metadata']['capability']??null)==='supported'&&$snap['text']===$mp['text']&&$snap['post_url']===$mp['post_url']&&read_photo_signature($snap)===$mp['photo_signature']&&ChannelPostIncomingAdapter::containsOnlyButton($snap,ChannelPostButtonTransport::buttonUrl($pubs['max']),'✈️ Узнать стоимость тура'),'max_readback_mismatch');
    $out['max_readback']=['http'=>200,'text_sha256'=>hash('sha256',$snap['text']),'photo_signature_sha256'=>hash('sha256',read_photo_signature($snap)),'body_photo_button_verified'=>true];
    $pdo->rollBack();$pdo=null;
    $original=$childState['monitor_snapshot'];$scope=$original['scope'];$url='https://anytoour.ru/data/hotel-monitor-prices-read-v1.php?'.http_build_query(['tourvisorHotelIds'=>[81173],'departureId'=>1,'dateFrom'=>$scope['dateFrom'],'dateTo'=>$scope['dateTo']],'','&',PHP_QUERY_RFC3986);
    $out['saved_monitor_gets']=1;$freshPayload=json_decode(read_public($url),true,64,JSON_THROW_ON_ERROR);need(($freshPayload['ok']??null)===true&&count($freshPayload['items']??[])===1&&$freshPayload['scope']['tourvisorHotelIds']===[81173],'fresh_scope_changed');
    $fresh=$freshPayload;unset($fresh['ok'],$fresh['items']);$fresh['item']=$freshPayload['items'][0];$fresh['sourceUrl']=$url;
    $out['fresh_admission']=['ok'=>false,'generated_at'=>$fresh['generatedAt']??null,'state'=>$fresh['item']['state']??null];
    try{$oldPrice=monitor_price_observation($original,81173,time(),false);$newPrice=monitor_price_observation($fresh,81173,time(),true);
        need($oldPrice['record_id']===$newPrice['record_id']&&array_map('stable_offer',$original['item']['offers'])===array_map('stable_offer',$fresh['item']['offers']),'fresh_record_changed');
        $out['fresh_admission']+=['record_id'=>$newPrice['record_id'],'expires_at'=>$newPrice['offer']['expiresAt'],'snapshot_sha256'=>tuple_hash($fresh)];$out['fresh_admission']['ok']=true;
    }catch(Throwable $e){$out['fresh_admission']['error']=preg_match('/\A[a-z_]+\z/D',$e->getMessage())?$e->getMessage():'fresh_admission_failed';}
    $prefixIds=[12120,64957,76029,66198,49750,123247,48184,32322,28272,7458];
    $expected=json_decode(base64_decode(READ_PREFIX_B64,true),true,32,JSON_THROW_ON_ERROR);
    foreach(['hotel-posts-max.xml'=>670,'hotel-posts-telegram.xml'=>670,'hotel-posts-all.xml'=>1340] as $name=>$count){
        $file=snapshot($app.'/feed/'.$name,4000000,$handles);preg_match_all('/<offer id="([A-Za-z0-9_-]+)">.*?<\/offer>/s',$file['raw'],$matches,PREG_SET_ORDER);$blocks=[];foreach($matches as $m){need(!isset($blocks[$m[1]]),'feed_duplicate');$blocks[$m[1]]=hash('sha256',$m[0]);}need(count($blocks)===$count,'feed_count_changed');
        foreach($prefixIds as $id)foreach(['max','tg'] as $plat){if(($name==='hotel-posts-max.xml'&&$plat==='tg')||($name==='hotel-posts-telegram.xml'&&$plat==='max'))continue;$key=$plat.'_'.$id;need(isset($expected[$name][$key])&&($blocks[$key]??null)===$expected[$name][$key],'prefix_changed');}
        $out['feeds'][$name]=['sha256'=>$file['sha256'],'bytes'=>$file['bytes'],'rows'=>$count,'prefix10_preserved'=>true];
    }
    clearstatcache(true,$root.'/monitor-price-after-collection-v1.jsonl');$end=fstat($gf);$named=lstat($root.'/monitor-price-after-collection-v1.jsonl');foreach(['dev','ino','size','mtime','ctime'] as $key)need(($end[$key]??null)===$out['global_journal'][$key]&&($named[$key]??null)===$out['global_journal'][$key],'global_changed_during_read');
    need(read_git(['rev-parse','HEAD'])===$out['runtime']['head']&&read_git(['status','--porcelain','--untracked-files=no'])==='','runtime_changed_during_read');
    $out['ready_for_narrow_repair']=$out['fresh_admission']['ok'];

    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = preg_match('/\A[a-z_]+\z/D',$e->getMessage()) === 1 ? $e->getMessage() : 'read_failed';
} finally {
    if($pdo instanceof PDO && $pdo->inTransaction())$pdo->rollBack();
    foreach (array_reverse($handles) as $f) { flock($f, LOCK_UN); fclose($f); }
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit($out['ok'] ? 0 : 2);

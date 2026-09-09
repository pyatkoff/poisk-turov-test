<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-hotel-pilot-review-dossier-v1.php';
$options=getopt('',['evidence:','now-epoch::','require-ready']);
$file=trim((string)($options['evidence']??''));
if($file===''){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:missing_evidence_file\n");exit(2);}
$raw=@file_get_contents($file);
if($raw===false||trim($raw)===''){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:empty_evidence_file\n");exit(2);}
try{$input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:invalid_json\n");exit(2);}
if(!is_array($input)){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:evidence_must_be_array\n");exit(2);}
$rows=array_is_list($input)?$input:($input['rows']??null);
if(!is_array($rows)){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:rows_must_be_array\n");exit(2);}
$nowRaw=$options['now-epoch']??null;$now=$nowRaw===null?time():filter_var($nowRaw,FILTER_VALIDATE_INT);
if($now===false||$now<=0){fwrite(STDERR,"SEO_HOTEL_PILOT_REVIEW_EVIDENCE_CLI_FAIL:invalid_now_epoch\n");exit(2);}
$result=v2_seo_hotel_pilot_review_dossier($rows,(int)$now);
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
if(isset($options['require-ready'])&&($result['state']??'')!=='review_only_hotel_pilot_evidence_ready')exit(3);
exit(0);

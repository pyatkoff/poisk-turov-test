<?php
declare(strict_types=1);
$out=['ok'=>false,'files'=>[]];
try {
 foreach(['hotel-posts-telegram.xml'=>670,'hotel-posts-max.xml'=>670,'hotel-posts-all.xml'=>1340] as $name=>$count){
  $p='/var/www/anytoour/data/www/app.anytoour.ru/feed/'.$name;
  if(is_link($p)||realpath($p)!==$p||!is_file($p)||filesize($p)>4000000)throw new RuntimeException('feed_path_invalid');
  $raw=file_get_contents($p);if(!is_string($raw)||str_contains($raw,'<!DOCTYPE')||str_contains($raw,'<!ENTITY'))throw new RuntimeException('feed_invalid');
  preg_match_all('/<offer id="([A-Za-z0-9_-]+)">.*?<\/offer>/s',$raw,$m,PREG_SET_ORDER);$rows=[];
  foreach($m as $r){if(isset($rows[$r[1]]))throw new RuntimeException('duplicate');$stable=preg_replace('/\s*<(price|currencyId|description)>.*?<\/\1>/s','',$r[0]);$rows[$r[1]]=['full'=>hash('sha256',$r[0]),'stable'=>hash('sha256',$stable)];}
  if(count($rows)!==$count)throw new RuntimeException('feed_count_changed');
  $envelope=preg_replace('/<offer id="([A-Za-z0-9_-]+)">.*?<\/offer>/s','OFFER',$raw);$envelope=preg_replace('/<yml_catalog date="[^"<>]+">/','<yml_catalog date="DATE">',$envelope);
  $out['files'][$name]=['envelope'=>hash('sha256',$envelope),'sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw),'rows'=>$rows];
 }
 $out['ok']=true;
}catch(Throwable $e){$out['error']='feed_readback_failed';}
echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),"\n";exit($out['ok']?0:2);

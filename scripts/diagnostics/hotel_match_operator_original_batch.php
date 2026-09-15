<?php
declare(strict_types=1);
/* MATCH identity evidence only. Existing client/transport, no database or bookings. */
require_once __DIR__.'/../../app/integrations/andromeda-client.php';
require_once __DIR__.'/../../app/integrations/andromeda-transport.php';
const MOB_OP='hotel-match-operator-original-batch-1971-20260915-v4';
const MOB_MAX_PRICE=120;
function mob_json(array $x): string {return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function mob_write(string $file,array $x): string {
    $raw=mob_json($x);$f=fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_output');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function mob_input(string $file,string $digest): array {if(!hash_equals($digest,hash_file('sha256',$file)))throw new RuntimeException('input_hash');$x=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);if(!is_array($x))throw new RuntimeException('input_shape');return $x;}
function mob_chunks(array $ids): array {
    $out=[];$part=[];foreach($ids as $id){if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,19}$/D',$id))throw new RuntimeException('target_id');if($part&&(count($part)>=30||strlen(implode(',',array_merge($part,[$id])))>300)){$out[]=$part;$part=[];}$part[]=$id;}if($part)$out[]=$part;return $out;
}

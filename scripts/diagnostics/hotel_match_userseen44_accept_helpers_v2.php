<?php
declare(strict_types=1);
function n8_need(bool $x,string $w):void{if(!$x)throw new RuntimeException($w);}
function n8_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function n8_hash(mixed $v):string{return hash('sha256',n8_json($v));}
function n8_write(string $p,array $v):string{$raw=n8_json($v)."\n";$f=fopen($p,'xb');n8_need(is_resource($f),'open');try{n8_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))n8_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function n8_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}

<?php
declare(strict_types=1);
function s26_need(bool $x,string $w):void{if(!$x)throw new RuntimeException($w);}
function s26_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function s26_hash(mixed $v):string{return hash('sha256',s26_json($v));}
function s26_write(string $p,array $v):string{$raw=s26_json($v)."\n";$f=fopen($p,'xb');s26_need(is_resource($f),'open');try{s26_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))s26_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function s26_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}

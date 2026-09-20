<?php
declare(strict_types=1);
function sf_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function sf_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sf_hash(mixed $v):string{return hash('sha256',sf_json($v));}
function sf_row_hash(array $v):string{return hash('sha256',sf_json($v));}
function sf_write(string $path,array $v):string{$raw=sf_json($v)."\n";$f=fopen($path,'xb');sf_need(is_resource($f),'open_file');try{sf_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write_file');if(function_exists('fsync'))sf_need(fsync($f),'sync_file');}finally{fclose($f);}return hash('sha256',$raw);}
function sf_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sf_norm(string $s):string{$s=function_exists('mb_strtoupper')?mb_strtoupper($s,'UTF-8'):strtoupper($s);$s=preg_replace('/[^\pL\pN]+/u',' ',$s)??$s;$parts=preg_split('/\s+/u',trim($s))?:[];$drop=['HOTEL'=>1,'RESORT'=>1,'SPA'=>1,'VILLA'=>1,'VILLAS'=>1,'THE'=>1,'AND'=>1,'ZANZIBAR'=>1];$parts=array_values(array_filter($parts,fn($x)=>$x!==''&&!isset($drop[$x])));return implode(' ',$parts);}
function sf_compatible(string $a,string $b):bool{$a=sf_norm($a);$b=sf_norm($b);return $a!==''&&$b!==''&&($a===$b||str_contains($a,$b)||str_contains($b,$a));}

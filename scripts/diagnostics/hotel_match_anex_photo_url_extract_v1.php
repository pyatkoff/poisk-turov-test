<?php
declare(strict_types=1);
/** MATCH #1971: exhaustive URL extraction from public ANEX hotel documents.
 * Pure parser: no DB/network writes. Feed document on STDIN, base URL argv[1].
 */
function m_unescape(string $s): string {
    for ($i=0;$i<3;$i++) {
        $n=html_entity_decode(str_replace(['\\/','\\u0026','\\u003d','\\u003D','\\u002F','\\u002f'],['/','&','=','=', '/', '/'],$s),ENT_QUOTES|ENT_HTML5,'UTF-8');
        if ($n===$s) break; $s=$n;
    }
    return trim($s," \t\r\n\"'");
}
function m_abs(string $raw,string $base): ?string {
    $raw=m_unescape($raw); if($raw==='') return null;
    if(str_starts_with($raw,'//')) $raw='https:'.$raw;
    if(str_starts_with($raw,'https://')) return $raw;
    if(!str_starts_with($raw,'/')) return null;
    $p=parse_url($base); if(!is_array($p)||empty($p['host'])) return null;
    return 'https://'.$p['host'].$raw;
}
function m_add(array &$out,string $raw,string $base,string $source): void {
    $u=m_abs($raw,$base); if($u===null||strlen($u)>8192) return;
    $p=parse_url($u); if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||isset($p['user'])||isset($p['pass'])) return;
    $out[$u][$source]=true;
}
function m_urls(string $doc,string $base): array {
    $text=m_unescape($doc); $out=[];
    preg_match_all('~https?://[^\\s\"\'<>\\\\]+~iu',$text,$m); foreach($m[0] as $u) m_add($out,$u,$base,'absolute');
    preg_match_all('~(?:href|src|data-src|data-original|data-lazy-src|data-image|data-url|poster|content)\\s*=\\s*[\"\']([^\"\']+)[\"\']~iu',$text,$m); foreach($m[1] as $u) m_add($out,$u,$base,'attribute');
    preg_match_all('~(?:srcset|data-srcset)\\s*=\\s*[\"\']([^\"\']+)[\"\']~iu',$text,$m); foreach($m[1] as $set) foreach(explode(',',$set) as $item){$u=preg_split('/\\s+/',trim($item))[0]??'';m_add($out,$u,$base,'srcset');}
    preg_match_all('~url\\(\\s*[\"\']?([^\)\"\']+)[\"\']?\\s*\\)~iu',$text,$m); foreach($m[1] as $u) m_add($out,$u,$base,'css');
    preg_match_all('~[\"\'](?:url|src|image|imageUrl|imageURL|photo|photoUrl|photoURL|original|large|medium|small|path)[\"\']\\s*:\\s*[\"\']([^\"\']+)[\"\']~iu',$text,$m); foreach($m[1] as $u) m_add($out,$u,$base,'json');
    ksort($out,SORT_STRING); return $out;
}
function m_hotel_code(string $url): ?int {
    $p=parse_url($url); if(!is_array($p)||strtolower((string)($p['host']??''))!=='files.anextour.ru'||!str_starts_with((string)($p['path']??''),'/hotel/')) return null;
    parse_str((string)($p['query']??''),$q); $v=$q['hotelCode']??null;
    if(is_array($v)||!is_scalar($v)||!preg_match('/^[1-9][0-9]{0,11}$/D',(string)$v)) return null;
    return (int)$v;
}
$base=$argv[1]??''; if(!str_starts_with($base,'https://')){fwrite(STDERR,"base https URL required\n");exit(2);} $doc=stream_get_contents(STDIN); if($doc===false) exit(2);
$urls=m_urls($doc,$base);$codes=[];$media=[];$sources=[];
foreach($urls as $u=>$ss){foreach($ss as $s=>$_)$sources[$s]=($sources[$s]??0)+1;$c=m_hotel_code($u);if($c!==null){$codes[$c]=true;$media[]=['url'=>$u,'hotelCode'=>$c,'sources'=>array_keys($ss)];}}
ksort($codes,SORT_NUMERIC);
echo json_encode(['status'=>'completed','base_url'=>$base,'document_sha256'=>hash('sha256',$doc),'url_count'=>count($urls),'source_counts'=>$sources,'hotel_codes'=>array_map('intval',array_keys($codes)),'hotelcode_url_count'=>count($media),'hotelcode_urls'=>$media],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;

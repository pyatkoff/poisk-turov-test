<?php
/** Internal AnyTour sales-leader enrichment for V2 search results. */
function sales_leader_normalize(string $value): string
{
    $value=trim($value);
    if($value==='')return '';
    $value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
    $value=str_replace(['ё','&'],['е',' and '],$value);
    $value=preg_replace('/[^\p{L}\p{N}]+/u',' ',$value)??$value;
    return trim(preg_replace('/\s+/u',' ',$value)??$value);
}
function sales_leader_country_key($country): string
{
    if(is_array($country))$country=$country['name']??$country['russianName']??$country['title']??'';
    elseif(is_object($country))$country=$country->name??$country->russianName??$country->title??'';
    $key=sales_leader_normalize((string)$country);
    $aliases=[
        'объединенные арабские эмираты'=>'оаэ',
        'объединенные арабские эмираты оаэ'=>'оаэ',
        'uae'=>'оаэ',
        'sri lanka'=>'шри ланка',
        'vietnam'=>'вьетнам',
        'thailand'=>'таиланд',
        'turkey'=>'турция',
        'egypt'=>'египет',
        'oman'=>'оман',
        'russia'=>'россия',
    ];
    return $aliases[$key]??$key;
}
function sales_leader_name_variants(string $name): array
{
    $values=[$name];
    $withoutParentheses=preg_replace('/\s*\([^)]*\)\s*/u',' ',$name);
    if(is_string($withoutParentheses)&&trim($withoutParentheses)!=='')$values[]=$withoutParentheses;
    $withoutEx=preg_replace('/\s*[\(\[]?\s*ex[\.\s].*$/iu','',$name);
    if(is_string($withoutEx)&&trim($withoutEx)!=='')$values[]=$withoutEx;
    $out=[];
    foreach($values as $value){$key=sales_leader_normalize((string)$value);if($key!=='')$out[$key]=true;}
    return array_keys($out);
}
function sales_leader_index(): array
{
    static $index=null;
    if(is_array($index))return $index;
    $index=[];$countryCounters=[];
    for($part=1;$part<=5;$part++){
        $file=__DIR__.'/data/sales-leaders-v1-'.$part.'.json';
        if(!is_file($file))continue;
        $rows=json_decode((string)file_get_contents($file),true);
        if(!is_array($rows))continue;
        foreach($rows as $row){
            $country=sales_leader_country_key($row['country']??'');
            $hotel=trim((string)($row['hotel']??''));
            if($country===''||$hotel==='')continue;
            $countryCounters[$country]=($countryCounters[$country]??0)+1;
            $countryRank=$countryCounters[$country];
            foreach(sales_leader_name_variants($hotel) as $nameKey){
                $key=$country.'|'.$nameKey;
                if(!isset($index[$key])||$countryRank<$index[$key])$index[$key]=$countryRank;
            }
        }
    }
    return $index;
}
function sales_leader_match(array $hotel): ?array
{
    $country=sales_leader_country_key($hotel['country']??'');
    $name=trim((string)($hotel['name']??''));
    if($country===''||$name==='')return null;
    $index=sales_leader_index();
    foreach(sales_leader_name_variants($name) as $nameKey){
        $key=$country.'|'.$nameKey;
        if(isset($index[$key]))return ['rank'=>(int)$index[$key]];
    }
    return null;
}
function sales_leader_enrich_results(array $results): array
{
    foreach($results as $i=>$hotel){
        if(!is_array($hotel))continue;
        $match=sales_leader_match($hotel);
        if(!$match)continue;
        $results[$i]['salesLeader']=true;
        $results[$i]['salesLeaderRank']=$match['rank'];
    }
    return $results;
}

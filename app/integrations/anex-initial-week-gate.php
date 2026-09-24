<?php
declare(strict_types=1);

/**
 * Direct ANEX initial-search quota gate.
 *
 * One browser search generation may spend supplier quota for exactly one initial
 * date window. Later date windows from the same generation are returned as
 * skipped before supplier reservation/access. A new generation starts a new
 * user search and may establish its own first window.
 */
function anytour_anex_initial_week_gate(array &$session, array $request): array
{
    $generation=$request['generation']??null;
    $params=$request['params']??null;
    if(!is_int($generation)||$generation<1||$generation>2147483647||!is_array($params)){
        throw new InvalidArgumentException('ANEX_INITIAL_WEEK_GATE_REQUEST');
    }
    $from=$params['dateFrom']??null;$to=$params['dateTo']??null;
    foreach([$from,$to] as $date){
        if(!is_string($date)||preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$date,$m)!==1
            ||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])){
            throw new InvalidArgumentException('ANEX_INITIAL_WEEK_GATE_DATE');
        }
    }
    if($from>$to){
        throw new InvalidArgumentException('ANEX_INITIAL_WEEK_GATE_DATE');
    }
    $start=new DateTimeImmutable($from);
    $end=new DateTimeImmutable($to);
    if($start->diff($end)->days>6){
        throw new InvalidArgumentException('ANEX_INITIAL_WEEK_GATE_RANGE');
    }
    $fingerprint=hash('sha256',json_encode($params,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $current=$session['anex_initial_week_v1']??null;
    if(!is_array($current)||($current['generation']??null)!==$generation){
        $session['anex_initial_week_v1']=[
            'generation'=>$generation,'date_from'=>$from,'date_to'=>$to,'fingerprint'=>$fingerprint,
        ];
        return ['action'=>'allow','generation'=>$generation,'date_from'=>$from,'date_to'=>$to];
    }
    if(($current['date_from']??null)===$from&&($current['date_to']??null)===$to
        &&is_string($current['fingerprint']??null)&&hash_equals($current['fingerprint'],$fingerprint)){
        return ['action'=>'allow','generation'=>$generation,'date_from'=>$from,'date_to'=>$to];
    }
    return ['action'=>'skip','generation'=>$generation,'date_from'=>$from,'date_to'=>$to];
}

function anytour_anex_initial_week_skipped(array $gate): array
{
    if(($gate['action']??null)!=='skip'||!is_int($gate['generation']??null)
        ||!is_string($gate['date_from']??null)||!is_string($gate['date_to']??null)){
        throw new InvalidArgumentException('ANEX_INITIAL_WEEK_GATE_RESULT');
    }
    return [
        'generation'=>$gate['generation'],'provider'=>'anex',
        'date_range'=>['from'=>$gate['date_from'],'to'=>$gate['date_to']],
        'hotels'=>[],'external_search_pending'=>false,'pages_read'=>0,
        'first_page_only'=>true,'initial_week_only'=>true,
    ];
}

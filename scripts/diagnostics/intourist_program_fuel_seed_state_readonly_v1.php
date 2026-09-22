<?php
declare(strict_types=1);

function sro_read(string $path,int $max=262144): ?array {
    if(!is_file($path)||is_link($path))return null;
    $size=filesize($path);if(!is_int($size)||$size<2||$size>$max)return null;
    try{$v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);return is_array($v)?$v:null;}
    catch(Throwable $ignored){return null;}
}
function sro_digest(mixed $v): ?string {
    return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/D',$v)===1?$v:null;
}
$home=rtrim((string)getenv('HOME'),'/');
if($home==='')exit(2);
$private=$home.'/.anytoour-andromeda';
$operation='int-andromeda-program30-tour34-fuel-seed-20260922-v1';
$op=$private.'/'.$operation;
$reservation=sro_read($op.'/reservation.json',65536);
$result=sro_read($op.'/result.json',65536);
$registry=[];
$searches=$private.'/searches';
if(is_dir($searches)&&!is_link($searches)){
    foreach(new DirectoryIterator($searches) as $entry){
        if($entry->isDot()||$entry->isLink()||!$entry->isFile())continue;
        $name=$entry->getFilename();
        if(!preg_match('/\Aoperator-program-fuel-v1-[a-f0-9]{64}\.json\z/D',$name))continue;
        $row=sro_read($entry->getPathname());
        if(!is_array($row)){$registry[]=['valid'=>false];continue;}
        $obs=$row['observations']??null;$summary=[];
        if(is_array($obs)&&array_is_list($obs)){
            foreach($obs as $o){
                if(!is_array($o))continue;
                $summary[]=[
                    'amount'=>$o['amount']??null,'currency'=>$o['currency']??null,'unit'=>$o['unit']??null,
                    'direction_count'=>$o['direction_count']??null,'base_relation'=>$o['base_relation']??null,
                    'offer_ref_digest'=>sro_digest($o['offer_ref_digest']??null),
                    'evidence_sha256'=>sro_digest($o['evidence_sha256']??null),
                    'source'=>$o['source']??null,
                    'observed_at'=>$o['observed_at']??null,'expires_at'=>$o['expires_at']??null,
                    'flight_pair'=>$o['flight_pair']??null,
                    'exchange'=>is_array($o['exchange']??null)?[
                        'from'=>$o['exchange']['from']??null,'to'=>$o['exchange']['to']??null,
                        'rate'=>$o['exchange']['rate']??null,'observed_at'=>$o['exchange']['observed_at']??null,
                        'expires_at'=>$o['exchange']['expires_at']??null,
                        'evidence_sha256'=>sro_digest($o['exchange']['evidence_sha256']??null),
                    ]:null,
                ];
            }
        }
        $registry[]=[
            'valid'=>($row['version']??null)===1&&is_array($row['key']??null)&&sro_digest($row['key_sha256']??null)!==null,
            'key'=>$row['key']??null,'key_sha256'=>sro_digest($row['key_sha256']??null),
            'observation_count'=>count($summary),'observations'=>$summary,
            'file_sha256'=>hash_file('sha256',$entry->getPathname()),
            'mtime'=>$entry->getMTime(),
        ];
    }
}
$out=[
    'schema_version'=>1,'source'=>'intourist-program-fuel-seed-state-readonly-v1',
    'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'filesystem_writes'=>0,
    'operation'=>[
        'directory_exists'=>is_dir($op)&&!is_link($op),
        'reservation_present'=>is_array($reservation),
        'reservation_operation'=>$reservation['operation']??null,
        'reservation_feature_source'=>$reservation['feature_source']??null,
        'result_present'=>is_array($result),
        'result_status'=>$result['status']??null,
        'result_reason'=>$result['reason']??null,
        'result_rule'=>is_array($result['rule']??null)?[
            'amount'=>$result['rule']['amount']??null,'currency'=>$result['rule']['currency']??null,
            'unit'=>$result['rule']['unit']??null,'sample_count'=>$result['rule']['sample_count']??null,
            'registry_sha256'=>sro_digest($result['rule']['registry_sha256']??null),
        ]:null,
    ],
    'registry_files'=>$registry,
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";

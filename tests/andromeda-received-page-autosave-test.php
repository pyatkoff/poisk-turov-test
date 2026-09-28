<?php
declare(strict_types=1);

/**
 * Standalone received-page regression with REAL production normalization, money,
 * context, handoff and producer classes. Only mapping/canonical lookup, retained
 * pricing lookup and the final SQL ingest boundary are injected test callbacks.
 * No supplier requests, credentials, live database or other test-suite execution.
 */
require_once dirname(__DIR__) . '/app/integrations/andromeda-anytour-offer-autosave.php';

$paChecks = [];
function pa_assert(bool $condition, string $message): void {
    global $paChecks;
    if (!$condition) throw new RuntimeException('received-page: ' . $message);
    $paChecks[] = $message;
}
function pa_request(int $page = 1): array {
    return ['generation'=>1,'page'=>$page,'params'=>[
        'departureId'=>1,'countryId'=>1,'dateFrom'=>'2026-10-10','dateTo'=>'2026-10-10',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[7],'currency'=>'RUB',
    ]];
}
function pa_offer(string $ref, string $operator = 'FUN&SUN', int $local = 101, string $amount = '185125'): array {
    return ['offer_ref'=>'offer_'.hash('sha256',$ref),'operator'=>$operator,
        'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)($local+900),
        'local_hotel_id'=>$local,'check_in'=>'2026-10-10','nights'=>7,'adults'=>2,'children'=>1,
        'meal'=>['raw_label'=>'AI','label'=>'AI'],'room_raw'=>'Deluxe Sea View',
        'placement_raw'=>'2AD+1CH','price'=>['amount'=>$amount,'currency'=>'RUB']];
}
function pa_state(string $ref, int $page, int $pages, int $at, array $offers): array {
    return ['status'=>$page===$pages?'complete':'partial','search_ref'=>$ref,'generation'=>1,
        'store'=>['version'=>1,'search_ref'=>$ref,'generation'=>1,'created_at'=>$at,'expires_at'=>$at+900,
            'snapshot'=>['provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>$page,
                'pages_count'=>$pages,'offers'=>$offers,'rejected'=>[],'selection_enabled'=>false]]];
}
function pa_dir(): string {
    $dir=sys_get_temp_dir().'/anytour-received-page-test-'.bin2hex(random_bytes(8)).'/searches';
    if (!mkdir($dir,0700,true)) throw new RuntimeException('fixture directory');
    return $dir;
}
function pa_path(string $dir,string $ref,int $created,int $page): string {
    return $dir.'/'.$ref.($page===1?'-1':'-'.$created.'-'.$page).'.json';
}
function pa_save(string $path,array $value): bool {
    $bytes=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (file_put_contents($path,$bytes)!==strlen($bytes)) throw new RuntimeException('fixture write');
    chmod($path,0600);
    return true;
}
function pa_cleanup(string $dir): void {
    // Only this test's freshly created private, flat fixture is removed.
    foreach (new DirectoryIterator($dir) as $item) {
        if ($item->isDot()) continue;
        if ($item->isLink() || !$item->isFile()) throw new RuntimeException('unexpected fixture entry');
        unlink($item->getPathname());
    }
    rmdir($dir);rmdir(dirname($dir));
}
function pa_callbacks(array &$ingests,bool $mapped=true,?array $pricing=null,bool $canonical=true): array {
    return [
        static function(array $offers) use($mapped): array {
            $out=[];
            if ($mapped) foreach($offers as $offer) $out[json_encode([
                $offer['supplier_namespace'],(string)$offer['external_hotel_id']
            ],JSON_THROW_ON_ERROR)]=$offer['local_hotel_id'];
            return $out;
        },
        static function(array $ids) use($canonical): array {
            $out=[];foreach($ids as $id)$out[$id]=$canonical?$id+900:null;return $out;
        },
        static fn(array $state,int $at,array $offer,array $current): ?array=>$pricing,
        static fn(string $path,array $value): bool=>pa_save($path,$value),
        static function(string $provider,array $search,array $rows,DateTimeImmutable $now) use(&$ingests): array {
            $ingests[]=compact('provider','search','rows','now');
            return ['source'=>'test_ingest_boundary','offerCount'=>count($rows)];
        },
    ];
}
function pa_consume(string $dir,string $ref,int $at,int $page,array $callbacks,?int $now=null,bool $complete=false): array {
    return AnyTourAndromedaOfferAutosaveV1::consume(
        pa_request($page),$dir,$ref,1,new DateTimeImmutable('@'.($now??($at+30))),
        $callbacks[0],$callbacks[1],$callbacks[2],$callbacks[3],$callbacks[4],$complete?null:$page
    );
}
function pa_confirmation(array $dto,string $amount,int $issuedAt,int $page): void {
    pa_assert($dto['finalPriceReady']===false && $dto['finalPrice']===null
        && $dto['price']===$amount && $dto['currency']==='RUB','original search price, not final');
    pa_assert($dto['money']['fuel_charge_reported']===null
        && !array_key_exists('search_price_with_surcharge',$dto['money'])
        && $dto['money']['arithmetic_applied']===false,'unknown fuel stays unknown');
    pa_assert($dto['quote_state']==='unknown' && $dto['final_price_verified']===false
        && $dto['selection_state']==='disabled' && $dto['booking_enabled']===false,'no selection or booking authority');
    pa_assert($dto['context']['issued_at']===$issuedAt && $dto['context']['expires_at']===$issuedAt+900
        && $dto['context']['page']===$page && $dto['tour']['observed_at']===gmdate('Y-m-d\TH:i:s\Z',$issuedAt),
        'original page time and expiry preserved');
}

$at=(new DateTimeImmutable('2026-09-29T00:00:00Z'))->getTimestamp();
$dir=pa_dir();$ref=hash('sha256','first-page-real-production-modules');$ingests=[];$cb=pa_callbacks($ingests);
$one=pa_offer('one');$two=pa_offer('two','Intourist',102,'220000');
try {
    pa_save(pa_path($dir,$ref,$at,1),pa_state($ref,1,4,$at,[$one]));
    $legacy=pa_consume($dir,$ref,$at,1,$cb,null,true);
    pa_assert($legacy['reason']==='cohort_incomplete' && $ingests===[],'complete API still rejects an incomplete cohort');
    $first=pa_consume($dir,$ref,$at,1,$cb);
    pa_assert($first['published']===true && $first['snapshotMode']==='partial_additive','first page of four persists now');
    pa_assert(count($ingests)===1 && count($ingests[0]['rows'])===1,'one received row submitted');
    pa_assert($first['readyOfferCount']===0 && $first['confirmationRequiredOfferCount']===1,'real producer reports confirmation count');
    pa_confirmation($ingests[0]['rows'][0]['dto'],'185125',$at,1);
    pa_assert(!file_exists(pa_path($dir,$ref,$at,2)),'page two never acquired');
    pa_assert(pa_consume($dir,$ref,$at,1,$cb)['reason']==='already_published' && count($ingests)===1,'first page repeat idempotent');

    pa_save(pa_path($dir,$ref,$at,2),pa_state($ref,2,4,$at+5,[$two]));
    $second=pa_consume($dir,$ref,$at,2,$cb);
    pa_assert($second['published']===true && count($ingests)===2,'second page persists without third');
    pa_assert(count($ingests[1]['rows'])===1 && $ingests[1]['rows'][0]['anytour_hotel_id']===1002,'only the newly received page submitted');
    pa_confirmation($ingests[1]['rows'][0]['dto'],'220000',$at+5,2);
    pa_consume($dir,$ref,$at,1,$cb);pa_consume($dir,$ref,$at,2,$cb);
    pa_assert(count($ingests)===2,'interleaved page checkpoints remain independent');
    pa_assert(pa_consume($dir,$ref,$at,3,$cb)['reason']==='received_page_missing' && count($ingests)===2,'missing page cannot clear earlier data');
    $empty=pa_state($ref,3,0,$at+10,[]);$empty['status']='complete';pa_save(pa_path($dir,$ref,$at,3),$empty);
    pa_assert(pa_consume($dir,$ref,$at,3,$cb)['published']===false && count($ingests)===2,'terminal empty page cannot clear earlier data');
    unlink(pa_path($dir,$ref,$at,3));
    $bad=pa_state($ref,2,4,$at+5,[$two]);$bad['generation']=2;pa_save(pa_path($dir,$ref,$at,2),$bad);
    try{pa_consume($dir,$ref,$at,2,$cb);throw new LogicException('generation accepted');}
    catch(DomainException $expected){pa_assert(count($ingests)===2,'wrong generation rejected');}
    pa_save(pa_path($dir,$ref,$at,2),pa_state($ref,2,4,$at+5,[$two]));

    $changed=$one;$changed['price']['amount']='185225';pa_save(pa_path($dir,$ref,$at,1),pa_state($ref,1,4,$at,[$changed]));
    pa_assert(pa_consume($dir,$ref,$at,1,$cb)['published']===true && count($ingests)===3,'changed facts persist once');
    pa_confirmation($ingests[2]['rows'][0]['dto'],'185225',$at,1);
    pa_consume($dir,$ref,$at,1,$cb);pa_assert(count($ingests)===3,'changed page repeat idempotent');
    pa_assert(pa_consume($dir,$ref,$at,1,$cb,null,true)['reason']==='cohort_incomplete' && count($ingests)===3,'page checkpoints do not manufacture completeness');
    pa_assert(pa_consume($dir,$ref,$at,1001,$cb)['reason']==='context_invalid','page bound retained');
    try{pa_consume($dir,$ref,$at,1,$cb,$at+900);throw new LogicException('expiry accepted');}
    catch(DomainException $expected){pa_assert(count($ingests)===3,'expired context not renewed');}
    $withdrawn=pa_callbacks($ingests,false);
    pa_assert(pa_consume($dir,$ref,$at,1,$withdrawn)['published']===false && count($ingests)===3,'current mapping withdrawal blocks cached publication');

    $dup=[$changed,$changed];pa_save(pa_path($dir,$ref,$at,1),pa_state($ref,1,4,$at,$dup));
    pa_consume($dir,$ref,$at,1,$cb);pa_assert(count($ingests)===3,'identical boundary row deduplicated');
    $dup[1]['price']['amount']='199999';pa_save(pa_path($dir,$ref,$at,1),pa_state($ref,1,4,$at,$dup));
    pa_assert(pa_consume($dir,$ref,$at,1,$cb)['reason']==='conflicting_offer_identity' && count($ingests)===3,'conflicting same-page identity rejected');
} finally {pa_cleanup($dir);}

foreach(['unmapped','no_canonical','excluded','empty','failed_ingest','bad_price','bad_party'] as $case){
    $dir=pa_dir();$ref=hash('sha256','case-'.$case);$ingests=[];$offer=pa_offer($case,$case==='excluded'?'Anex Tour':'FUN&SUN');
    if($case==='bad_price')$offer['price']['amount']='1e6';
    if($case==='bad_party')$offer['children']=2;
    $cb=pa_callbacks($ingests,$case!=='unmapped',null,$case!=='no_canonical');
    if($case==='failed_ingest')$cb[4]=static function(){throw new RuntimeException('synthetic_ingest_failure');};
    try{
        pa_save(pa_path($dir,$ref,$at,1),pa_state($ref,1,4,$at,$case==='empty'?[]:[$offer]));
        if($case==='failed_ingest'){
            try{pa_consume($dir,$ref,$at,1,$cb);throw new LogicException('failed write accepted');}
            catch(RuntimeException $expected){pa_assert($expected->getMessage()==='synthetic_ingest_failure','failed ingest propagated');}
            pa_assert(count(glob($dir.'/*anytour-offer-autosave*.json'))===0,'failed ingest has no success checkpoint');
        }else{
            $result=pa_consume($dir,$ref,$at,1,$cb);
            pa_assert($result['published']===false && $ingests===[],$case.' never clears previous snapshot');
        }
    }finally{pa_cleanup($dir);}
}

// Exercise actual producer guard, not a producer double.
foreach([
    ['andromeda',['complete'=>true,'authoritative_empty'=>true,'offers'=>[]],true],
    ['andromeda',['complete'=>false,'authoritative_empty'=>true,'offers'=>[]],true],
    ['andromeda',['complete'=>false,'authoritative_empty'=>false,'offers'=>[]],true],
    ['tourvisor',['complete'=>false,'authoritative_empty'=>false,'offers'=>[]],true],
    ['andromeda',['complete'=>false,'authoritative_empty'=>false,'offers'=>[]],false],
] as [$provider,$refresh,$partial]){
    $calls=0;
    try{
        AnyTourIntOfferSnapshotProducerV1::produce($provider,pa_request()['params'],$refresh,
            new DateTimeImmutable('@'.$at),static function()use(&$calls):array{$calls++;return [];},$partial);
        throw new RuntimeException('invalid producer mode accepted');
    }catch(InvalidArgumentException|DomainException $expected){pa_assert($calls===0,'invalid partial mode or authoritative empty rejected');}
}

$evidence=['status'=>'passed','checks'=>count($paChecks),'cases'=>$paChecks,
    'real_production_normalizer_money_context_handoff_producer'=>true,
    'mapping_and_ingest_callbacks'=>'test doubles, no SQL execution',
    'supplier_http'=>0,'live_db_reads'=>0,'live_db_writes'=>0,
    'source_sha256'=>hash_file('sha256',dirname(__DIR__).'/app/integrations/andromeda-anytour-offer-autosave.php'),
    'producer_sha256'=>hash_file('sha256',dirname(__DIR__).'/app/integrations/anytour-offer-snapshot-producer.php')];
echo json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";

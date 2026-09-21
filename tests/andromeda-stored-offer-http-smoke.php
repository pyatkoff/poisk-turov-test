<?php
declare(strict_types=1);
// Reuse the actual native #3342 fixture/selector; no database, provider or browser requests.
if (!in_array('--native', $argv, true)) $argv[] = '--native';
require __DIR__ . '/stored-provider-offer-context-smoke.php';
require_once __DIR__ . '/../app/integrations/andromeda-saved-package-runtime.php';
require_once __DIR__ . '/../v2/api-andromeda-stored-offer-preview.php';
$baselineChecks = $checks;
function stored_http_refuses(callable $run, string $message): void {
    try { $run(); } catch (InvalidArgumentException | DomainException | RuntimeException $e) { check(true, $message); return; }
    throw new LogicException('Expected refusal: ' . $message);
}
$directory = sys_get_temp_dir() . '/anytour-stored-http-' . bin2hex(random_bytes(8)) . '/searches';
mkdir($directory, 0700, true);
$put = static function (string $path, array $value): void { file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR)); };
try {
    [$row, $second, $keys] = fixture('andromeda', $now);
    foreach ($second['snapshot']['offers'] as &$offer) { $offer['operator'] = 'FUN&SUN'; $offer['price']['currency'] = 'RUB'; }
    unset($offer);
    $second['snapshot']['pages_count'] = 2;
    $first = $second; $first['criteria']['PAGE'] = 1; $first['snapshot']['page'] = 1;
    $first['snapshot']['offers'] = [$second['snapshot']['offers'][0]];
    $second['snapshot']['offers'] = [$second['snapshot']['offers'][1]];
    $ref = $second['search_ref']; $created = $first['created_at'];
    // Native start() timestamps each page separately; its filename still uses the first page's time.
    $second['created_at'] += 30; $second['expires_at'] += 30;
    $firstPath = $directory . '/' . $ref . '-1.json';
    $secondPath = $directory . '/' . $ref . '-' . $created . '-2.json';
    $lockPath = $directory . '/' . $ref . '.lock';
    file_put_contents($lockPath, '');
    $put($firstPath, ['status'=>'partial', 'store'=>$first]); $put($secondPath, ['status'=>'complete', 'store'=>$second]);
    // A file from another search is not opened; private authentication files are not read.
    file_put_contents($directory . '/' . str_repeat('f',64) . '-1.json', 'not valid JSON');
    file_put_contents($directory . '/' . $ref . '-auth.json', 'DO NOT READ THIS PRIVATE AUTH FILE');
    $params = ['departureId'=>'1','countryId'=>'4','dateFrom'=>'2027-01-25','dateTo'=>'2027-01-25','nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[0,17]];
    $reply = ['source'=>'anytour-db-first-results-v1','scopeVersion'=>1,'scopeDigest'=>str_repeat('e',64),'selectionAuthority'=>false,'hotels'=>[[
        'anytourHotelId'=>4234,'hotel'=>['catalog'=>'anytour','id'=>4234,'name'=>'Название из нашего каталога'],
        'offers'=>[['provider'=>'andromeda','legacyHotelId'=>101,'price'=>$row['price'],'currency'=>'RUB','expiresAt'=>$row['expiresAt'],
            'sourceScopeDigest'=>$row['sourceScopeDigest'],'listing'=>$row['offer']]],
    ]]];
    $dbReads = 0; $canonicalCalls = 0; $nativeCalls = 0;
    $canonicalAllowed = true; $mappingAllowed = true;
    $read = static function(array $requested) use (&$dbReads,&$reply,$params): array {
        ++$dbReads; return $requested === $params ? $reply : array_replace($reply,['hotels'=>[]]);
    };
    $canonical = static function(string $provider,string $digest,int $legacy,int $own) use (&$canonicalCalls,&$canonicalAllowed,$row): bool {
        ++$canonicalCalls;
        return $canonicalAllowed && $provider==='andromeda' && $digest===$row['offer']['identity']['provider_hotel_ref_digest'] && $legacy===101 && $own===4234;
    };
    $mapping = static function(array $offer) use (&$nativeCalls,&$mappingAllowed): bool {
        ++$nativeCalls; return $mappingAllowed && $offer['local_hotel_id']===101 && $offer['supplier_namespace']==='operator_315';
    };
    $handles = [];
    $prepare = ['action'=>'prepare','params'=>$params,'anytourHotelId'=>4234,'identity'=>$row['offer']['identity']];
    $run = static function(array $request, ?int $at = null) use (&$handles,$directory,$read,$canonical,$mapping,$now): array {
        return anytour_stored_samo_request($request,$handles,$directory,$read,$canonical,$mapping,$at ?? $now);
    };
    $filesBefore = [hash_file('sha256',$firstPath),hash_file('sha256',$secondPath)];
    $result = $run($prepare); $handle = $result['handle'];
    check($dbReads===1 && $canonicalCalls>0 && $nativeCalls>0,'CURRENT reader + canonical + native mapping all used');
    check((bool)preg_match('/^stored_[a-f0-9]{64}$/D',$handle),'Opaque viewer handle');
    check($result['hotel']['name']==='Название из нашего каталога','Supplier cannot replace canonical hotel presentation');
    check($result['tour']['room']['raw']==='DELUXE SEA VIEW','Exact second-page offer, not the cheaper STANDARD');
    check($result['tour']['party']['child_ages']===[0,17],'Child ages 0/17 retained');
    check($result['listingPrice']['amount']==='133500.50','Listing precision unchanged');
    check($result['quote']===['state'=>'confirmation_required','finalPrice'=>null,'expiresAt'=>null],'No invented quote when evidence missing');
    check($result['selectionEnabled']===false && $result['bookingEnabled']===false,'Context read is not booking authority');
    check(!str_contains(json_encode($result),$ref) && !str_contains(json_encode($result),'private-package-'),'No private source or supplier package identity in browser response');
    check(!str_contains(json_encode($handles),'private-package-'),'Session handle needs no raw supplier package ID');
    $again = $run(['action'=>'read','handle'=>$handle]);
    check($dbReads===2 && $again===$result,'Read uses CURRENT DB again and retains handle and expiry');
    check(count($handles)===1,'Read does not allocate another handle');
    check($filesBefore===[hash_file('sha256',$firstPath),hash_file('sha256',$secondPath)],'Original retained snapshots unchanged');

    $otherViewer=[];
    stored_http_refuses(function() use (&$otherViewer,$directory,$read,$canonical,$mapping,$now,$handle) { anytour_stored_samo_request(['action'=>'read','handle'=>$handle],$otherViewer,$directory,$read,$canonical,$mapping,$now); },'Handle from another viewer');
    $readsBefore = $dbReads;
    foreach (['price'=>'1','row'=>$row,'provider'=>'anex','path'=>'/etc/passwd','search_ref'=>$ref] as $key=>$value) {
        stored_http_refuses(fn()=>$run($prepare+[$key=>$value]),'Browser cannot supply '.$key);
    }
    foreach ([[],null,'../x','stored_'.str_repeat('z',64)] as $bad) stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$bad]),'Malformed handle');
    check($dbReads===$readsBefore,'Malformed requests rejected before DB/source access');
    $wrong=$prepare; $wrong['params']['departureId']='2'; stored_http_refuses(fn()=>$run($wrong),'Different trip has no current row');
    $wrong=$prepare; $wrong['anytourHotelId']=101; stored_http_refuses(fn()=>$run($wrong),'Legacy ID cannot stand for own hotel ID');
    $savedReply=$reply;
    $reply['hotels']=[]; stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$handle]),'Offer no longer present in current LOCAL results'); $reply=$savedReply;
    $reply['hotels'][0]['offers'][]=$reply['hotels'][0]['offers'][0]; stored_http_refuses(fn()=>$run($prepare),'Ambiguous current row'); $reply=$savedReply;
    $reply['hotels'][0]['offers'][0]['listing']['tour']['room']['raw']='STANDARD'; stored_http_refuses(fn()=>$run($prepare),'Changed room cannot reuse digest'); $reply=$savedReply;
    $reply['scopeDigest']=str_repeat('d',64); stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$handle]),'Handle bound to original scope'); $reply=$savedReply;
    $canonicalAllowed=false; stored_http_refuses(fn()=>$run($prepare),'Changed canonical mapping'); $canonicalAllowed=true;
    $mappingAllowed=false; stored_http_refuses(fn()=>$run($prepare),'Changed CURRENT SAMO identity'); $mappingAllowed=true;
    $duplicate=$first; $duplicate['snapshot']['offers'][]=$second['snapshot']['offers'][0];
    $put($firstPath,['status'=>'partial','store'=>$duplicate]); stored_http_refuses(fn()=>$run($prepare),'Duplicate exact identity across pages'); $put($firstPath,['status'=>'partial','store'=>$first]);
    $new=$first; $new['generation']++; $put($firstPath,['status'=>'partial','store'=>$new]); stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$handle]),'Handle cannot follow replacement source generation'); $put($firstPath,['status'=>'partial','store'=>$first]);
    $new=$first; $new['snapshot']['pages_count']=1001; $put($firstPath,['status'=>'partial','store'=>$new]); stored_http_refuses(fn()=>$run($prepare),'Page count bounded'); $put($firstPath,['status'=>'partial','store'=>$first]);
    $reply['hotels'][0]['offers'][0]['listing']['finalPriceVerified']=true;
    stored_http_refuses(fn()=>$run($prepare,$now+800),'24h verified listing cannot revive expired native context'); $reply=$savedReply;
    stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$handle],$now+800),'Expired viewer handle');
    $busy=fopen($lockPath,'rb'); flock($busy,LOCK_EX);
    stored_http_refuses(fn()=>$run($prepare),'Busy native lock refuses rather than waiting or taking over'); flock($busy,LOCK_UN); fclose($busy);
    rename($secondPath,$secondPath.'.saved'); symlink($secondPath.'.saved',$secondPath);
    stored_http_refuses(fn()=>$run($prepare),'Symlinked source refused'); unlink($secondPath); rename($secondPath.'.saved',$secondPath);
    rename($lockPath,$lockPath.'.saved'); stored_http_refuses(fn()=>$run($prepare),'Missing source lock not recreated'); check(!file_exists($lockPath),'No new native lock'); rename($lockPath.'.saved',$lockPath);

    // Reuse the actual pagination contract: a later page can grow the count or end early with PAGES_COUNT=0.
    $third=$second; $third['criteria']['PAGE']=3; $third['snapshot']['page']=3; $third['snapshot']['pages_count']=3;
    $thirdPath=$directory.'/'.$ref.'-'.$created.'-3.json';
    $growing=$second; $growing['snapshot']['offers']=[]; $growing['snapshot']['pages_count']=3;
    $put($secondPath,['status'=>'partial','store'=>$growing]); $put($thirdPath,['status'=>'complete','store'=>$third]);
    $grown=$run($prepare);
    check($handles[$grown['handle']]['locator']['page']===3,'Follow native page-count growth to the exact offer');
    check($run(['action'=>'read','handle'=>$grown['handle']])['tour']===$result['tour'],'Handle beyond first advertised page count remains readable');
    check($grown['expiresAt']===gmdate('Y-m-d\TH:i:s\Z',$created+900),'Later page timestamp does not renew source/handle expiry');
    $eof=$third; $eof['snapshot']['offers']=[]; $eof['snapshot']['pages_count']=0;
    $advertised=$second; $advertised['snapshot']['pages_count']=5;
    $put($secondPath,['status'=>'partial','store'=>$advertised]); $put($thirdPath,['status'=>'complete','store'=>$eof]);
    check($run($prepare)['tour']===$result['tour'],'Native terminal empty page ends scan without requiring imaginary pages');
    unlink($thirdPath); $put($secondPath,['status'=>'complete','store'=>$second]);
    $changed=$second; $changed['criteria']['HOTELS']='501'; $put($secondPath,['status'=>'complete','store'=>$changed]);
    stored_http_refuses(fn()=>$run($prepare),'Later page cannot change the source search criteria'); $put($secondPath,['status'=>'complete','store'=>$second]);

    // Produce actual reader-shaped evidence; use the unchanged native pricing validator.
    $resolved=AnyTourStoredProviderOfferContext::resolveAndromeda($row,$second,$canonical,$mapping,$now);
    $quote=['schema_version'=>1,'provider'=>'andromeda','state'=>'quote_verified','quote_state'=>'verified',
        'final_price_verified'=>true,'flight_selection_required'=>false,'booking_enabled'=>false,'local_id'=>101,'operator'=>'FUN&SUN',
        'search_price'=>['amount'=>'133500.50','currency'=>'RUB'],'final_price'=>['amount'=>'159999.90','currency'=>'RUB'],
        'extra_private_data'=>'private-package-DO-NOT-EXPOSE'];
    $record=['version'=>1,'status'=>'complete','implementation_sha256'=>hash_file('sha256',__DIR__.'/../app/integrations/andromeda-saved-package-runtime.php'),
        'source'=>str_repeat('a',40),'context'=>$resolved['context'],'criteria_sha256'=>$resolved['criteria_sha256'],
        'supplier_offer_sha256'=>$resolved['supplier_offer_sha256'],'package_sha256'=>str_repeat('b',64),'snapshot_created_at'=>$created,
        'observed_at'=>$now-10,'expires_at'=>$now+290,'verified_quote'=>$quote];
    $pricingPath=$directory.'/'.$ref.'-'.$created.'-2-'.$keys[1].'-surcharge-v1.json'; $put($pricingPath,$record);
    $pricingBefore=hash_file('sha256',$pricingPath);
    $priced=$run(['action'=>'read','handle'=>$handle]);
    check($priced['quote']['state']==='verified' && $priced['quote']['finalPrice']===['amount'=>'159999.90','currency'=>'RUB'],'Actual saved-pricing reader returns exact verified total');
    check($priced['quote']['expiresAt']===gmdate('Y-m-d\TH:i:s\Z',$now+290),'Quote has original shorter evidence TTL');
    check($priced['listingPrice']===$result['listingPrice'],'Final price not added to original listing');
    check(!str_contains(json_encode($priced),'private-package-') && !str_contains(json_encode($priced),'supplier_offer'),'Verified quote response is strictly projected');
    check($priced['selectionEnabled']===false && $priced['bookingEnabled']===false,'Verified stored money still does not enable lead/booking');
    check(hash_file('sha256',$pricingPath)===$pricingBefore,'Reading never renews or modifies quote evidence');
    $expired=$run(['action'=>'read','handle'=>$handle],$now+290);
    check($expired['quote']['state']==='confirmation_required' && $expired['quote']['finalPrice']===null,'Expired price not revived by handle/native TTL');
    $record['criteria_sha256']=str_repeat('f',64); $put($pricingPath,$record);
    check($run(['action'=>'read','handle'=>$handle])['quote']['state']==='confirmation_required','Unbound evidence refused');
    unlink($pricingPath);
    for($i=0;$i<15;$i++)$run($prepare);
    check(count($handles)===12,'Viewer handle cache bounded');
    stored_http_refuses(fn()=>$run(['action'=>'read','handle'=>$handle]),'Evicted handle cannot revive old state');
    $server=['REQUEST_METHOD'=>'POST','CONTENT_TYPE'=>'application/json; charset=utf-8','HTTP_X_REQUESTED_WITH'=>'AnyTourSearch3','HTTP_ORIGIN'=>'https://anytoour.ru','HTTP_SEC_FETCH_SITE'=>'same-origin'];
    check(anytour_stored_samo_http_guard($server,'{}')===[200,'ok'],'Same-origin POST envelope');
    foreach (['REQUEST_METHOD'=>'GET','HTTP_X_REQUESTED_WITH'=>'wrong','HTTP_ORIGIN'=>'https://foreign.test','HTTP_SEC_FETCH_SITE'=>'cross-site','CONTENT_TYPE'=>'text/plain'] as $key=>$value) {
        check(anytour_stored_samo_http_guard(array_replace($server,[$key=>$value]),'{}')[0]!==200,'HTTP guard refuses '.$key);
    }
    check(anytour_stored_samo_http_guard($server,str_repeat('x',16385))[0]===400,'Body size bound');
    check(anytour_stored_samo_http_guard($server,'')[0]===400,'Empty body');
    echo 'Stored SAMO HTTP processor: '.($checks-$baselineChecks).' checks passed; actual native and saved-price readers; supplier/DB writes/lead calls=0; HTTP deployment and UI activation not implied.'.PHP_EOL;
} finally {
    foreach(new DirectoryIterator($directory) as $file)if(!$file->isDot())unlink($file->getPathname());
    rmdir($directory);rmdir(dirname($directory));
}

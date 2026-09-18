<?php
declare(strict_types=1);
function hmd_collect_frontier(PDO $db):array{
  $accepted=[];$occ=[];$protectedExt=[];
  foreach(hmd_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1")as$r){
    $a=(int)$r['anex_hotel_id'];$l=(int)$r['catalog_hotel_id'];
    if($a>0){$protectedExt[$a]['mapping']=true;if($l>0)$occ[$a][$l]=true;}
    if($l>0)$accepted[$l]=true;
  }
  foreach(hmd_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions")as$r){
    $a=(int)$r['anex_hotel_id'];$l=(int)($r['catalog_hotel_id']??0);$st=(string)($r['decision_status']??'');
    if($a>0)$protectedExt[$a][$st!==''?$st:'decision']=true;
    if($st==='accepted'&&$l>0){$accepted[$l]=true;$occ[$a][$l]=true;}
  }
  $andr=[];foreach(hmd_rows($db,"SELECT DISTINCT local_hotel_id local_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")as$r)$andr[(int)$r['local_id']]=true;
  $ex=[];foreach(hmd_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")as$r)$ex[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $hotels=[];foreach(hmd_rows($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude,is_active FROM catalog_hotels WHERE is_active=1")as$r)$hotels[(int)$r['id']]=$r;
  $front=[];foreach($hotels as$id=>$h)if(!isset($accepted[$id],$andr[$id],HMD_EXCLUDED_COUNTRIES[(int)$h['country_id']])&&!hmd_product((string)$h['name']))$front[$id]=$h;
  return compact('front','hotels','accepted','andr','occ','ex','protectedExt');
}
function hmd_observations(PDO $db,array $front):array{if(!$front)return[];$out=[];foreach(array_chunk(array_keys($front),600)as$chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));$out=array_merge($out,hmd_rows($db,"SELECT hotel_id,departure_id,country_id,departure_date,nights,COUNT(*) obs FROM tour_price_observations WHERE source='user_search' AND departure_date>=CURRENT_DATE AND adults=2 AND children_count=0 AND hotel_id IN ($ph) GROUP BY hotel_id,departure_id,country_id,departure_date,nights",$chunk));}return$out;}
function hmd_plan_a(array $obs,array $front):array{$byRoute=[];foreach($obs as$r){$lid=(int)$r['hotel_id'];if(!isset($front[$lid]))continue;$k=(int)$r['departure_id'].'|'.(int)$r['country_id'];$byRoute[$k][]=$r;}$best=null;foreach($byRoute as$k=>$rows){$dates=array_values(array_unique(array_map(fn($r)=>(string)$r['departure_date'],$rows)));sort($dates,SORT_STRING);foreach($dates as$d){$from=new DateTimeImmutable($d);$to=$from->modify('+6 day');$hot=[];$weight=0;$slice=[];foreach($rows as$r){$rd=new DateTimeImmutable((string)$r['departure_date']);if($rd<$from||$rd>$to)continue;$lid=(int)$r['hotel_id'];$hot[$lid]=($hot[$lid]??0)+(int)$r['obs'];$weight+=(int)$r['obs'];$slice[]=$r;}if(!$hot)continue;$score=[count($hot),$weight];if($best===null||$score[0]>$best['score'][0]||($score[0]===$best['score'][0]&&$score[1]>$best['score'][1])){[$dep,$cid]=array_map('intval',explode('|',$k));arsort($hot,SORT_NUMERIC);$best=['departure_id'=>$dep,'country_id'=>$cid,'date_from'=>$from->format('Y-m-d'),'date_to'=>$to->format('Y-m-d'),'hotel_weights'=>$hot,'slice'=>$slice,'score'=>$score];}}}if(!$best)return[];$ids=array_slice(array_keys($best['hotel_weights']),0,HMD_A_BATCH_SIZE*HMD_A_MAX_BATCHES);$best['target_ids']=$ids;$best['mode_nights']=hmd_mode_nights($best['slice']);return$best;}
function hmd_plan_b(array $obs,array $front):array{
  $g=[];
  foreach($obs as$r){
    $lid=(int)$r['hotel_id'];$h=$front[$lid]??null;if(!$h)continue;
    $region=(int)($h['region_id']??0);$star=(int)($h['category']??0);if($region<1||$star<3||$star>5)continue;
    $start=(string)$r['departure_date'];$from=new DateTimeImmutable($start);$to=$from->modify('+6 day');
    $k=implode('|',[(int)$r['departure_id'],(int)$r['country_id'],$region,$star,(int)$r['nights'],$start]);
    if(!isset($g[$k]))$g[$k]=[
      'departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],'region_id'=>$region,
      'region_name'=>(string)($h['region_name']??''),'subregion_name'=>(string)($h['subregion_name']??''),
      'star'=>$star,'nights'=>(int)$r['nights'],'date_from'=>$from->format('Y-m-d'),'date_to'=>$to->format('Y-m-d'),
      'hotels'=>[],'obs'=>0
    ];
    $g[$k]['hotels'][$lid]=true;$g[$k]['obs']+=(int)$r['obs'];
  }
  foreach($g as&$x)$x['hotel_count']=count($x['hotels']);unset($x);
  $v=array_values($g);usort($v,fn($a,$b)=>$b['hotel_count']<=>$a['hotel_count']?:$b['obs']<=>$a['obs']?:strcmp($a['date_from'],$b['date_from']));
  $out=[];$seen=[];
  foreach($v as$x){$route=implode('|',[$x['departure_id'],$x['country_id'],$x['region_id'],$x['star'],$x['nights']]);if(($seen[$route]??0)>=2)continue;$seen[$route]=($seen[$route]??0)+1;$out[]=$x;if(count($out)>=12)break;}
  return$out;
}
function hmd_anex_rows(mixed $r):array{if(is_array($r)&&is_array($r['prices']??null))return$r['prices'];if(is_array($r)&&array_is_list($r))return$r;return[];}
function hmd_offer_date(mixed $v):?string{
  if(!is_scalar($v))return null;$raw=trim((string)$v);
  foreach(['!Y-m-d','!Ymd','!d.m.Y']as$fmt){$d=DateTimeImmutable::createFromFormat($fmt,$raw,new DateTimeZone('UTC'));$e=DateTimeImmutable::getLastErrors();if($d&&($e===false||(($e['warning_count']??0)===0&&($e['error_count']??0)===0)))return$d->format('Y-m-d');}
  return null;
}
function hmd_anex_search($cl,array $ctx,array $depNames,array $countryNames):array{
  $townfrom=$cl->request('SearchTour_TOWNFROMS',[]);$dep=hmd_dict_id(is_array($townfrom)?$townfrom:[],$depNames);if(!$dep)throw new RuntimeException('anex_departure_bind');
  $states=$cl->request('SearchTour_STATES',['TOWNFROMINC'=>$dep]);$state=hmd_dict_id(is_array($states)?$states:[],$countryNames);if(!$state)throw new RuntimeException('anex_country_bind');
  $towns=$cl->request('SearchTour_TOWNS',['TOWNFROMINC'=>$dep,'STATEINC'=>$state]);$town=hmd_dict_id(is_array($towns)?$towns:[],hmd_resort_aliases($ctx['region_name'],$ctx['subregion_name']));if(!$town)throw new RuntimeException('anex_town_bind');
  $stars=$cl->request('SearchTour_STARS',['TOWNFROMINC'=>$dep,'STATEINC'=>$state]);$star=hmd_star_id(is_array($stars)?$stars:[],$ctx['star']);if(!$star)throw new RuntimeException('anex_star_bind');
  $beg=str_replace('-','',$ctx['date_from']);$end=str_replace('-','',$ctx['date_to']);
  $curr=$cl->request('SearchTour_CURRENCIES',['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'CHECKIN_BEG'=>$beg,'CHECKIN_END'=>$end,'ADULT'=>2,'CHILD'=>0]);$rub=hmd_dict_id(is_array($curr)?$curr:[],['RUB','RUR','Рубль','Рубли','Руб']);if(!$rub)throw new RuntimeException('anex_rub_bind');
  $base=['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'TOWNTOINC'=>$town,'STARS'=>$star,'CHECKIN_BEG'=>$beg,'CHECKIN_END'=>$end,'NIGHTS_FROM'=>$ctx['nights'],'NIGHTS_TILL'=>$ctx['nights'],'ADULT'=>2,'CHILD'=>0,'CURRENCY'=>$rub,'FREIGHT'=>1,'FILTER'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
  $out=[];$pages=[];$seenRows=[];
  for($p=1;$p<=HMD_ANEX_MAX_PAGES;$p++){
    $raw=$cl->request('SearchTour_PRICES',$base+['PRICEPAGE'=>$p]);$rows=hmd_anex_rows($raw);$newRows=0;
    foreach($rows as$r){
      if(!is_array($r))continue;$rs=hash('sha256',hmd_json($r));if(!isset($seenRows[$rs])){$seenRows[$rs]=true;$newRows++;}
      $id=hmd_id($r['hotelKey']??null);$date=hmd_offer_date($r['checkIn']??null);if(!$id||$date===null||$date<$ctx['date_from']||$date>$ctx['date_to'])continue;
      $key=$id.'|'.$date;$room=hmd_text($r['room']??$r['roomName']??$r['roomType']??'',300);
      if(!isset($out[$key]))$out[$key]=['native_anex_id'=>$id,'check_in'=>$date,'name'=>hmd_text($r['hotel']??'',300),'town'=>hmd_text($r['town']??'',200),'star'=>hmd_star_value($r['star']??null),'rooms'=>[]];
      if($room!=='')$out[$key]['rooms'][hmd_room_key($room)]=['raw'=>$room,'key'=>hmd_room_key($room)];
    }
    $pages[]=['page'=>$p,'rows'=>count($rows),'new_rows'=>$newRows,'unique_hotel_dates'=>count($out)];
    if(count($rows)===0||$newRows===0){foreach($out as&$x)$x['rooms']=array_values($x['rooms']);unset($x);return['hotels'=>$out,'pages'=>$pages,'fully_drained'=>true,'bindings'=>['townfrom'=>$dep,'state'=>$state,'town'=>$town,'star'=>$star]];}
  }
  throw new RuntimeException('anex_page_cap');
}
function hmd_and_search(AnyTourAndromedaClient $cl,array $ctx,array $depNames,array $countryNames):array{
  $townfrom=$cl->catalog('townfrom',[]);$dep=hmd_dict_id((array)($townfrom['TOWNFROM']??[]),$depNames);if(!$dep)throw new RuntimeException('and_departure_bind');
  $states=$cl->catalog('state',['TOWNFROMINC'=>$dep]);$state=hmd_dict_id((array)($states['STATE']??[]),$countryNames);if(!$state)throw new RuntimeException('and_country_bind');
  $all=$cl->catalog('all',['TOWNFROMINC'=>$dep,'STATEINC'=>$state]);$op=hmd_operator_id((array)($all['OPERATORS']??[]),'anex');if(!$op)throw new RuntimeException('and_anex_operator');
  $town=hmd_dict_id((array)($all['TOWNTO']??[]),hmd_resort_aliases($ctx['region_name'],$ctx['subregion_name']));if(!$town)throw new RuntimeException('and_town_bind');
  $star=hmd_star_id((array)($all['STARS']??[]),$ctx['star']);if(!$star)throw new RuntimeException('and_star_bind');
  $base=['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'TOWNTOINC'=>$town,'STARS'=>$star,'CHECKIN_BEG'=>str_replace('-','',$ctx['date_from']),'CHECKIN_END'=>str_replace('-','',$ctx['date_to']),'NIGHTS_FROM'=>$ctx['nights'],'NIGHTS_TILL'=>$ctx['nights'],'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'OPERATORS'=>(string)$op,'PACKETTYPE'=>0,'GROUP_BY'=>32];
  $out=[];$meta=[];$pages=null;
  for($p=1;$p<=HMD_AND_MAX_PAGES;$p++){
    $reply=$cl->price($base+['PAGE'=>$p]);$pages=(int)($reply['PAGES_COUNT']??0);if($pages>HMD_AND_MAX_PAGES)throw new RuntimeException('and_page_cap');$rows=(array)($reply['PRICES']??[]);
    foreach($rows as$r){
      if(!is_array($r)||(string)($r['operatorKey']??'')!==(string)$op||!in_array($r['isOperatorHotelKey']??null,[0,'0'],true)||!is_array($r['original']??null))continue;
      $native=hmd_id($r['original']['hotelKey']??null);$date=hmd_offer_date($r['checkIn']??null);if(!$native||$date===null||$date<$ctx['date_from']||$date>$ctx['date_to'])continue;
      $key=$native.'|'.$date;$room=hmd_text($r['room']??$r['roomName']??$r['roomType']??'',300);
      if(!isset($out[$key]))$out[$key]=['native_anex_id'=>$native,'check_in'=>$date,'andromeda_hotel_id'=>hmd_text($r['hotelKey']??'',80),'name'=>hmd_text($r['hotel']??'',300),'town'=>hmd_text($r['town']??'',200),'rooms'=>[]];
      if($room!=='')$out[$key]['rooms'][hmd_room_key($room)]=['raw'=>$room,'key'=>hmd_room_key($room)];
    }
    $meta[]=['page'=>$p,'pages_count'=>$pages,'rows'=>count($rows),'unique_hotel_dates'=>count($out)];
    if($pages===0&&$p===1&&count($rows)===0)return['hotels'=>[],'pages'=>$meta,'fully_drained'=>true,'bindings'=>['townfrom'=>$dep,'state'=>$state,'town'=>$town,'star'=>$star,'operator'=>$op]];
    if($p>=$pages)break;
  }
  if($pages===null||($pages>0&&count($meta)!==$pages))throw new RuntimeException('and_not_drained');
  foreach($out as&$x)$x['rooms']=array_values($x['rooms']);unset($x);
  return['hotels'=>$out,'pages'=>$meta,'fully_drained'=>true,'bindings'=>['townfrom'=>$dep,'state'=>$state,'town'=>$town,'star'=>$star,'operator'=>$op]];
}
function hmd_tv_fresh_anchors(string $tvToken,string $home,string $lane,array $rows,array $targetIds,array $front,array $occ,array $ex,array $protectedExt,int $detailLimit=50):array{
  $wanted=array_fill_keys(array_map('intval',$targetIds),true);$anchors=[];$holds=[];$details=0;$notFound=0;$kill=false;$room=[];
  foreach($rows as$h){
    if(!is_array($h)||!($lid=hmd_id($h['id']??null))||!isset($wanted[$lid],$front[$lid]))continue;
    $tour=null;foreach((array)($h['tours']??[])as$t)if(is_array($t)&&hmd_text($t['id']??$t['tourId']??'',220)!==''){$tour=$t;break;}
    if(!$tour||$details>=$detailLimit||$kill)continue;
    $tid=hmd_text($tour['id']??$tour['tourId']??'',220);
    $detail=hmd_tv_get($tvToken,$home,$lane,'/tours/'.rawurlencode($tid),['currency'=>'RUB'],true);$details++;
    if(($detail['_hmd_http_status']??null)===404){
      $notFound++;$holds[]=['local_id'=>$lid,'tour_id'=>$tid,'reason'=>'fresh_detail_404'];
      if(hmd_detail_kill($details,$notFound))$kill=true;
      continue;
    }
    $link=hmd_detail_link($detail);
    if(count($link['native_ids'])!==1){$holds[]=['local_id'=>$lid,'tour_id'=>$tid,'reason'=>'native_link_not_unique','native_ids'=>$link['native_ids']];continue;}
    $native=(int)$link['native_ids'][0];
    if(isset($protectedExt[$native])){$holds[]=['local_id'=>$lid,'native_anex_id'=>$native,'reason'=>'existing_anex_identity_or_decision_protected','states'=>array_keys($protectedExt[$native])];continue;}
    $other=array_diff(array_keys($occ[$native]??[]),[$lid]);if($other){$holds[]=['local_id'=>$lid,'native_anex_id'=>$native,'reason'=>'external_occupied_other_local','other_locals'=>array_values($other)];continue;}
    if(isset($ex[$native][$lid])){$holds[]=['local_id'=>$lid,'native_anex_id'=>$native,'reason'=>'pair_excluded'];continue;}
    $tvRooms=[];foreach((array)($h['tours']??[])as$tr){if(!is_array($tr))continue;$rr=hmd_text($tr['roomType']??$tr['room']??'',300);if($rr!=='')$tvRooms[hmd_room_key($rr)]=['raw'=>$rr,'key'=>hmd_room_key($rr)];}
    $tvRooms=array_values($tvRooms);$anchors[$lid]=['local_id'=>$lid,'local_name'=>(string)$front[$lid]['name'],'native_anex_id'=>$native,'fresh_tour_id'=>$tid,'operator_link_urls'=>$link['urls'],'tv_rooms'=>$tvRooms];
    foreach($tvRooms as$re)$room[]=['local_id'=>$lid,'native_anex_id'=>$native,'provider'=>'tourvisor']+$re;
  }
  return['anchors'=>array_values($anchors),'holds'=>$holds,'detail_calls'=>$details,'detail_404'=>$notFound,'detail_404_kill'=>$kill,'room_evidence'=>$room];
}
function hmd_direct_details($cl,array $anchors,array $front):array{
  $out=[];
  foreach($anchors as$a){
    $id=(int)$a['native_anex_id'];$lid=(int)$a['local_id'];$local=$front[$lid]??null;
    if(!$local){$out[$id]=['currentization_state'=>'protected_hold','reason'=>'local_not_current_frontier'];continue;}
    try{
      $r=$cl->request('Hotels_DETAILS',['HOTELINC'=>$id]);
      $country=hmd_text($r['country']??'',120);$lat=$r['latitude']??null;$lon=$r['longitude']??null;
      $distance=hmd_dist($lat,$lon,$local['latitude']??null,$local['longitude']??null);
      $countryOk=$country===''||hmd_country_key($country)===hmd_country_key((string)$local['country_name']);
      $state='currentized';$reason=null;
      if(!$countryOk){$state='protected_hold';$reason='explicit_country_conflict';}
      elseif($distance!==null&&$distance>5000){$state='protected_hold';$reason='coordinate_conflict_gt_5km';}
      $out[$id]=[
        'local_id'=>$lid,'name'=>hmd_text($r['name']??$r['hotel']??'',300),'country'=>$country,
        'state'=>hmd_text($r['state']??'',120),'region'=>hmd_text($r['region']??'',120),'town'=>hmd_text($r['town']??'',120),
        'latitude'=>$lat,'longitude'=>$lon,'country_ok'=>$countryOk,'distance_m'=>$distance===null?null:(int)round($distance),
        'currentization_state'=>$state,'reason'=>$reason
      ];
    }catch(Throwable$e){$out[$id]=['local_id'=>$lid,'currentization_state'=>'detail_error','error'=>preg_replace('/[^A-Za-z0-9_.:-]/','_',hmd_cut($e->getMessage(),80))];}
  }
  return$out;
}

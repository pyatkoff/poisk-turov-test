<?php
/** Build Yandex YML feeds from fresh country SEO snapshots. No Tourvisor calls. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';

function yf_arg(array $argv,string $name,?string $fallback=null): ?string { foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return substr($arg,strlen($name)+3); return $fallback; }
function yf_xml(string $v): string { return htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8'); }
function yf_slug(string $v): string { $v=mb_strtolower(trim($v)); $v=preg_replace('/[^a-z0-9_-]+/u','-',$v)??''; return trim($v,'-') ?: 'feed'; }
function yf_write(string $path,string $content): void { $dir=dirname($path); if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('cannot create '.$dir); $tmp=$path.'.tmp.'.getmypid(); if(file_put_contents($tmp,$content,LOCK_EX)===false) throw new RuntimeException('cannot write '.$tmp); if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('cannot replace '.$path);} }
function yf_document(array $rows,string $title): string {
  $date=(new DateTimeImmutable('now'))->format('Y-m-d H:i');
  $out="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<yml_catalog date=\"".yf_xml($date)."\"><shop>";
  $out.='<name>'.yf_xml($title).'</name><company>AnyTour</company><url>https://anytoour.ru/</url><currencies><currency id="RUB" rate="1"/></currencies><categories><category id="1">Туры</category></categories><offers>';
  foreach($rows as $r){
    $dep=(int)$r['departure_id'];$country=(int)$r['country_id'];$price=(float)$r['min_price'];
    if($dep<=0||$country<=0||$price<=0) continue;
    $countryName=trim((string)$r['country_name']) ?: ('Страна '.$country);
    $departureName=trim((string)$r['departure_name']) ?: ('город '.$dep);
    $url='https://anytoour.ru/poisk-turov/?from='.$dep.'&country='.$country;
    $name='Туры в '.$countryName.' из '.$departureName.' от '.number_format($price,0,'.',' ').' ₽';
    $description='Актуальная минимальная цена по данным AnyTour. Поиск туров из '.$departureName.' в '.$countryName.'.';
    $id='country_'.$dep.'_'.$country;
    $out.='<offer id="'.yf_xml($id).'" available="true"><url>'.yf_xml($url).'</url><price>'.yf_xml(number_format($price,0,'.','')).'</price><currencyId>RUB</currencyId><categoryId>1</categoryId><name>'.yf_xml($name).'</name><description>'.yf_xml($description).'</description></offer>';
  }
  return $out.'</offers></shop></yml_catalog>';
}

$pdo=v2_data_db();
$sql="SELECT s.departure_id,s.country_id,s.min_price,s.currency,s.offer_count,s.observed_at,s.expires_at,COALESCE(c.name,'') country_name,COALESCE(d.name,'') departure_name,COALESCE(c.slug,CAST(s.country_id AS CHAR)) country_slug FROM seo_offer_snapshots s LEFT JOIN catalog_countries c ON c.id=s.country_id LEFT JOIN catalog_departures d ON d.id=s.departure_id WHERE s.page_type='country' AND s.expires_at>=NOW() AND s.min_price>0 AND s.currency='RUB' ORDER BY s.country_id,s.departure_id";
$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
$outDir=yf_arg($argv,'output-dir',null);
if(!$outDir){$parent=dirname(__DIR__);$root=basename($parent)==='v2'?dirname($parent):$parent;$outDir=$root.'/feed/generated';}
$outDir=rtrim((string)$outDir,'/');

try{
  yf_write($outDir.'/countries.yml',yf_document($rows,'AnyTour — туры по странам'));
  $byCountry=[];foreach($rows as $r)$byCountry[(int)$r['country_id']][]=$r;
  $files=['countries.yml'];
  foreach($byCountry as $countryId=>$countryRows){$slug=yf_slug((string)($countryRows[0]['country_slug']??$countryId));$file='country-'.$countryId.'-'.$slug.'.yml';yf_write($outDir.'/'.$file,yf_document($countryRows,'AnyTour — '.((string)$countryRows[0]['country_name'] ?: 'туры')));$files[]=$file;}
  $manifest=['generatedAt'=>(new DateTimeImmutable('now'))->format(DATE_ATOM),'source'=>'seo_offer_snapshots','type'=>'country','rows'=>count($rows),'files'=>$files];
  yf_write($outDir.'/manifest.json',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
  echo 'YANDEX_COUNTRY_FEEDS_OK rows='.count($rows).' countries='.count($byCountry).' files='.count($files)." output={$outDir}\n";
}catch(Throwable $e){fwrite(STDERR,'YANDEX_COUNTRY_FEEDS_FAILED '.mb_substr($e->getMessage(),0,1000)."\n");exit(1);}

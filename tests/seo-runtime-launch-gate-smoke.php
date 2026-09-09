<?php
require_once __DIR__ . '/../v2/site-page-shell-v1.php';

function runtime_gate_fail(string $message): void
{
    fwrite(STDERR, "SEO_RUNTIME_GATE_FAIL:$message\n");
    exit(1);
}

$tmp=sys_get_temp_dir().'/anytour-seo-runtime-'.bin2hex(random_bytes(4));
if(!mkdir($tmp,0777,true)&&!is_dir($tmp)) runtime_gate_fail('tmp_dir');
$_SERVER['DOCUMENT_ROOT']=$tmp;
$_SERVER['HTTP_HOST']='anytoour.ru';

file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_CONTROLLED_LAUNCH'=>true];\n");
foreach(['/country/turkey/kemer/','/country/egypt/','/country/maldives/'] as $path){
    $_SERVER['REQUEST_URI']=$path.'?utm_source=test';
    $ctx=sp_context($path,'Launch page','Description');
    if(!str_starts_with((string)$ctx['robots'],'index,follow')) runtime_gate_fail('allowed_not_indexable_'.$path);
}

$_SERVER['REQUEST_URI']='/poisk-turov/?country=4&region=22';
$search=sp_context('/poisk-turov/','Search','Search description');
if(!str_starts_with((string)$search['robots'],'noindex,follow')) runtime_gate_fail('search_leaked');
$_SERVER['REQUEST_URI']='/country/egypt/hotel/example-123/';
$hotel=sp_context('/country/egypt/hotel/example-123/','Hotel','Hotel description');
if(!str_starts_with((string)$hotel['robots'],'noindex,follow')) runtime_gate_fail('hotel_tours_leaked');
$_SERVER['REQUEST_URI']='/country/oae/';
$other=sp_context('/country/oae/','OAE','OAE description');
if(!str_starts_with((string)$other['robots'],'noindex,follow')) runtime_gate_fail('other_country_leaked');

// Backward-compatible production kill switch still disables the whole controlled slice.
file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_TURKEY_LAUNCH'=>false];\n");
$_SERVER['REQUEST_URI']='/country/egypt/';
$legacyDisabled=sp_context('/country/egypt/','Egypt','Egypt description');
if(!str_starts_with((string)$legacyDisabled['robots'],'noindex,follow')) runtime_gate_fail('legacy_disabled_flag');

file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_CONTROLLED_LAUNCH'=>false,'SEO_TURKEY_LAUNCH'=>true];\n");
$_SERVER['REQUEST_URI']='/country/turkey/kemer/';
$disabled=sp_context('/country/turkey/kemer/','Kemer','Kemer description');
if(!str_starts_with((string)$disabled['robots'],'noindex,follow')) runtime_gate_fail('controlled_flag_precedence');

@unlink($tmp.'/site_conf.php'); @rmdir($tmp);
echo "SEO_RUNTIME_GATE_OK controlledPaths=8 turkey=1 egypt=1 maldives=1 searchProtected=1 hotelTours=0 disabledSafe=1\n";

<?php
/** Review-only Egypt hotel-tour manifest. Stable identity only. */
function v2_seo_egypt_hotel_manifest(): array
{
    return [
        ['file'=>'seo-content-pilot-egypt-dexon-roma-v1.php','function'=>'v2_seo_content_pilot_egypt_dexon_roma','label'=>'Туры в DEXON ROMA HOTEL','href'=>'/country/egypt/hotel/dexon-roma-hotel-ex-roma-host-way-388/'],
        ['file'=>'seo-content-pilot-egypt-empire-aqua-v1.php','function'=>'v2_seo_content_pilot_egypt_empire_aqua','label'=>'Туры в EMPIRE HOTEL AQUA PARK','href'=>'/country/egypt/hotel/empire-hotel-aqua-park-ex-the-three-corners-triton-empire-502/'],
        ['file'=>'seo-content-pilot-egypt-sun-sea-v1.php','function'=>'v2_seo_content_pilot_egypt_sun_sea','label'=>'Туры в SUN & SEA HOTEL','href'=>'/country/egypt/hotel/sun-sea-hotel-9372/'],
    ];
}

function v2_seo_egypt_hotel_links(): array
{
    return array_map(static fn(array $entry): array => ['label'=>$entry['label'],'href'=>$entry['href']], v2_seo_egypt_hotel_manifest());
}

function v2_seo_egypt_hotel_records(): array
{
    $records=[];
    $seen=[];
    foreach (v2_seo_egypt_hotel_manifest() as $entry) {
        $file=(string)$entry['file']; $function=(string)$entry['function']; $href=(string)$entry['href'];
        if ($file==='' || $function==='' || $href==='' || isset($seen[$href])) throw new InvalidArgumentException('Egypt hotel review manifest contains invalid identity');
        $seen[$href]=true;
        require_once __DIR__.'/'.$file;
        if (!function_exists($function)) throw new InvalidArgumentException('Egypt hotel review manifest function missing: '.$function);
        $record=$function();
        if (($record['path']??'')!==$href) throw new InvalidArgumentException('Egypt hotel review manifest path mismatch: '.$function);
        $records[]=$record;
    }
    return $records;
}

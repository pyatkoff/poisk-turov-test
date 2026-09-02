<?php
/** Review-only Egypt hotel-tour manifest. Stable identity only. */
function v2_seo_egypt_hotel_manifest(): array
{
    return [
        ['file'=>'seo-content-pilot-egypt-dexon-roma-v1.php','function'=>'v2_seo_content_pilot_egypt_dexon_roma','label'=>'Туры в DEXON ROMA HOTEL','href'=>'/country/egypt/hotel/dexon-roma-hotel-ex-roma-host-way-388/'],
        ['file'=>'seo-content-pilot-egypt-empire-aqua-v1.php','function'=>'v2_seo_content_pilot_egypt_empire_aqua','label'=>'Туры в EMPIRE HOTEL AQUA PARK','href'=>'/country/egypt/hotel/empire-hotel-aqua-park-ex-the-three-corners-triton-empire-502/'],
        ['file'=>'seo-content-pilot-egypt-sun-sea-v1.php','function'=>'v2_seo_content_pilot_egypt_sun_sea','label'=>'Туры в SUN & SEA HOTEL','href'=>'/country/egypt/hotel/sun-sea-hotel-9372/'],
        ['file'=>'seo-content-pilot-egypt-mirage-bay-v1.php','function'=>'v2_seo_content_pilot_egypt_mirage_bay','label'=>'Туры в MIRAGE BAY RESORT & AQUAPARK','href'=>'/country/egypt/hotel/mirage-bay-resort-aquapark-299/'],
        ['file'=>'seo-content-pilot-egypt-sand-beach-v1.php','function'=>'v2_seo_content_pilot_egypt_sand_beach','label'=>'Туры в SAND BEACH','href'=>'/country/egypt/hotel/sand-beach-405/'],
        ['file'=>'seo-content-pilot-egypt-il-mercato-v1.php','function'=>'v2_seo_content_pilot_egypt_il_mercato','label'=>'Туры в IL MERCATO SPLASH AQUA PARK','href'=>'/country/egypt/hotel/il-mercato-splash-aqua-park-2904/'],
        ['file'=>'seo-content-pilot-egypt-mazar-v1.php','function'=>'v2_seo_content_pilot_egypt_mazar','label'=>'Туры в MAZAR RESORT & SPA','href'=>'/country/egypt/hotel/mazar-resort-spa-56104/'],
        ['file'=>'seo-content-pilot-egypt-coral-hills-v1.php','function'=>'v2_seo_content_pilot_egypt_coral_hills','label'=>'Туры в CORAL HILLS RESORT','href'=>'/country/egypt/hotel/coral-hills-resort-162/'],
        ['file'=>'seo-content-pilot-egypt-tropitel-dahab-v1.php','function'=>'v2_seo_content_pilot_egypt_tropitel_dahab','label'=>'Туры в TROPITEL DAHAB OASIS','href'=>'/country/egypt/hotel/tropitel-dahab-oasis-512/'],
        ['file'=>'seo-content-pilot-egypt-tivoli-v1.php','function'=>'v2_seo_content_pilot_egypt_tivoli','label'=>'Туры в TIVOLI HOTEL AQUA PARK','href'=>'/country/egypt/hotel/tivoli-hotel-aqua-park-511/'],
        ['file'=>'seo-content-pilot-egypt-old-vic-v1.php','function'=>'v2_seo_content_pilot_egypt_old_vic','label'=>'Туры в OLD VIC SHARM','href'=>'/country/egypt/hotel/old-vic-sharm-56094/'],
        ['file'=>'seo-content-pilot-egypt-faraana-v1.php','function'=>'v2_seo_content_pilot_egypt_faraana','label'=>'Туры в FARAANA HEIGHTS AQUA PARK','href'=>'/country/egypt/hotel/faraana-heights-aqua-park-1968/'],
        ['file'=>'seo-content-pilot-egypt-el-khan-v1.php','function'=>'v2_seo_content_pilot_egypt_el_khan','label'=>'Туры в EL KHAN SHARM HOTEL','href'=>'/country/egypt/hotel/el-khan-sharm-hotel-81245/'],
        ['file'=>'seo-content-pilot-egypt-swiss-heaven-v1.php','function'=>'v2_seo_content_pilot_egypt_swiss_heaven','label'=>'Туры в SWISS HEAVEN SHARMING INN','href'=>'/country/egypt/hotel/swiss-heaven-sharming-inn-453/'],
        ['file'=>'seo-content-pilot-egypt-viking-v1.php','function'=>'v2_seo_content_pilot_egypt_viking','label'=>'Туры в VIKING CLUB','href'=>'/country/egypt/hotel/viking-club-518/'],
        ['file'=>'seo-content-pilot-egypt-uni-sharm-v1.php','function'=>'v2_seo_content_pilot_egypt_uni_sharm','label'=>'Туры в UNI SHARM AQUA PARK','href'=>'/country/egypt/hotel/uni-sharm-aqua-park-ex-karma-eastotels-516/'],
        ['file'=>'seo-content-pilot-egypt-viva-sharm-v1.php','function'=>'v2_seo_content_pilot_egypt_viva_sharm','label'=>'Туры в VIVA SHARM','href'=>'/country/egypt/hotel/viva-sharm-ex-top-choice-viva-sharm-9377/'],
        ['file'=>'seo-content-pilot-egypt-verginia-v1.php','function'=>'v2_seo_content_pilot_egypt_verginia','label'=>'Туры в VERGINIA SHARM RESORT & AQUA PARK','href'=>'/country/egypt/hotel/verginia-sharm-resort-aqua-park-eh-verginia-sharm-sol-verginia-447/'],
        ['file'=>'seo-content-pilot-egypt-ecotel-dahab-v1.php','function'=>'v2_seo_content_pilot_egypt_ecotel_dahab','label'=>'Туры в ECOTEL DAHAB BAY VIEW RESORT','href'=>'/country/egypt/hotel/ecotel-dahab-bay-view-resort-322/'],
        ['file'=>'seo-content-pilot-egypt-eden-rock-v1.php','function'=>'v2_seo_content_pilot_egypt_eden_rock','label'=>'Туры в EDEN ROCK HOTEL','href'=>'/country/egypt/hotel/eden-rock-hotel-198/'],
        ['file'=>'seo-content-pilot-egypt-panorama-naama-v1.php','function'=>'v2_seo_content_pilot_egypt_panorama_naama','label'=>'Туры в PANORAMA NAAMA HEIGHTS','href'=>'/country/egypt/hotel/panorama-naama-heights-350/'],
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

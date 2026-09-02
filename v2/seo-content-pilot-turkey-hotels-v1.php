<?php
/**
 * Review-only Turkey hotel-tour manifest.
 * Stable identity only; no volatile price/availability/rating/region attributes.
 */
function v2_seo_turkey_hotel_manifest(): array
{
    return [
        ['file'=>'seo-content-pilot-turkey-first-class-v1.php','function'=>'v2_seo_content_pilot_turkey_first_class','label'=>'Туры в FIRST CLASS HOTEL','href'=>'/country/turkey/hotel/first-class-hotel-1188/'],
        ['file'=>'seo-content-pilot-turkey-bodrum-beach-resort-v1.php','function'=>'v2_seo_content_pilot_turkey_bodrum_beach_resort','label'=>'Туры в BODRUM BEACH RESORT','href'=>'/country/turkey/hotel/bodrum-beach-resort-9250/'],
        ['file'=>'seo-content-pilot-turkey-club-hotel-sunbel-v1.php','function'=>'v2_seo_content_pilot_turkey_club_hotel_sunbel','label'=>'Туры в CLUB HOTEL SUNBEL','href'=>'/country/turkey/hotel/club-hotel-sunbel-1102/'],
        ['file'=>'seo-content-pilot-turkey-dedeman-kemer-v1.php','function'=>'v2_seo_content_pilot_turkey_dedeman_kemer','label'=>'Туры в DEDEMAN KEMER RESORT','href'=>'/country/turkey/hotel/dedeman-kemer-resort-ex-club-jovia-kemer-28476/'],
        ['file'=>'seo-content-pilot-turkey-concordia-celes-v1.php','function'=>'v2_seo_content_pilot_turkey_concordia_celes','label'=>'Туры в CONCORDIA CELES HOTEL','href'=>'/country/turkey/hotel/concordia-celes-hotel-1134/'],
        ['file'=>'seo-content-pilot-turkey-casa-fora-v1.php','function'=>'v2_seo_content_pilot_turkey_casa_fora','label'=>'Туры в CASA FORA BEACH RESORT BY MIRAMOR','href'=>'/country/turkey/hotel/casa-fora-beach-resort-by-miramor-90704/'],
    ];
}

function v2_seo_turkey_hotel_links(): array
{
    return array_map(
        static fn(array $entry): array => ['label'=>$entry['label'],'href'=>$entry['href']],
        v2_seo_turkey_hotel_manifest()
    );
}

function v2_seo_turkey_hotel_records(): array
{
    $records=[];
    $seen=[];
    foreach (v2_seo_turkey_hotel_manifest() as $entry) {
        $file=trim((string)($entry['file']??''));
        $function=trim((string)($entry['function']??''));
        $href=trim((string)($entry['href']??''));
        if ($file==='' || $function==='' || $href==='' || isset($seen[$href])) {
            throw new InvalidArgumentException('Turkey hotel review manifest contains invalid identity');
        }
        $seen[$href]=true;
        $absolute=__DIR__.'/'.$file;
        if (!is_file($absolute)) throw new InvalidArgumentException('Turkey hotel review manifest file missing: '.$file);
        require_once $absolute;
        if (!function_exists($function)) throw new InvalidArgumentException('Turkey hotel review manifest function missing: '.$function);
        $record=$function();
        if (($record['path']??'')!==$href) throw new InvalidArgumentException('Turkey hotel review manifest path mismatch: '.$function);
        $records[]=$record;
    }
    return $records;
}

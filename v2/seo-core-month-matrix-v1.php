<?php
declare(strict_types=1);

require_once __DIR__.'/seo-seasonal-family-registry-v1.php';

/**
 * Structural month matrix for AnyTour core SEO families.
 *
 * This is architecture, not volatile travel data: it expands verified country
 * and resort identities across the 12 calendar months. It does not publish,
 * index, add sitemap URLs or change Search/Tourvisor contracts.
 */
function v2_seo_core_month_matrix(): array
{
    $months = [
        1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',
        7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december',
    ];
    $families = v2_seo_seasonal_family_registry();
    $rows = [];

    foreach ($families as $familyKey=>$family) {
        $country = is_array($family['country']??null)?$family['country']:[];
        $countryPath = rtrim((string)($country['path']??''),'/').'/';
        $countryId = (int)($family['country_id']??0);
        if ($countryId<=0 || !preg_match('~^/country/[a-z0-9-]+/$~',$countryPath)) {
            throw new RuntimeException('Core month matrix requires verified country route: '.$familyKey);
        }

        if (in_array('month',$family['supported_page_types']??[],true)) {
            foreach ($months as $monthNo=>$monthSlug) {
                $rows[] = [
                    'family'=>'country_month','country_key'=>$familyKey,'country_id'=>$countryId,
                    'region_id'=>null,'month'=>$monthNo,'month_slug'=>$monthSlug,
                    'path'=>$countryPath.$monthSlug.'/',
                ];
            }
        }

        if (in_array('resort_month',$family['supported_page_types']??[],true)) {
            foreach (($family['resorts']??[]) as $resort) {
                $resortPath = rtrim((string)($resort['path']??''),'/').'/';
                $regionId = (int)($resort['data']['search_state']['region']??0);
                if ($regionId<=0 || !str_starts_with($resortPath,$countryPath)) {
                    throw new RuntimeException('Core month matrix requires verified resort identity');
                }
                foreach ($months as $monthNo=>$monthSlug) {
                    $rows[] = [
                        'family'=>'resort_month','country_key'=>$familyKey,'country_id'=>$countryId,
                        'region_id'=>$regionId,'month'=>$monthNo,'month_slug'=>$monthSlug,
                        'path'=>$resortPath.$monthSlug.'/',
                    ];
                }
            }
        }
    }

    $paths=[];
    foreach ($rows as $row) {
        if (isset($paths[$row['path']])) throw new RuntimeException('Duplicate core month path: '.$row['path']);
        $paths[$row['path']]=true;
    }

    return [
        'state'=>'core_month_matrix_ready',
        'months'=>$months,
        'rows'=>$rows,
        'country_month_count'=>count(array_filter($rows,fn($r)=>$r['family']==='country_month')),
        'resort_month_count'=>count(array_filter($rows,fn($r)=>$r['family']==='resort_month')),
        'total_count'=>count($rows),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
    ];
}

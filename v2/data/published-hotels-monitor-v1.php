<?php
/** Published hotel identities already accepted by the AnyTour typed catalogue bridge.
 * The owner's original 492 hotel roster remains unchanged.
 * Source: max-search-bot/knowledge/hotels/manifest.json accepted_binding.accepted_chain.
 */
declare(strict_types=1);
require_once __DIR__.'/top-hotels-v1.php';

function v2_published_hotel_monitor_bindings(): array
{
    return [
        ['anytour_id'=>1554,'local_tv_id'=>9374,'name'=>"SUNRISE ARABIAN BEACH RESORT"],
        ['anytour_id'=>81,'local_tv_id'=>241,'name'=>"ALBATROS MAKADI RESORT"],
        ['anytour_id'=>80,'local_tv_id'=>240,'name'=>"HAPPY LIFE VILLAGE"],
        ['anytour_id'=>99,'local_tv_id'=>268,'name'=>"JAZ MAKADI SARAYA RESORT (EX. IBEROTEL MAKADI SARAYA RESORT)"],
        ['anytour_id'=>877,'local_tv_id'=>1981,'name'=>"JAZ NEO NAAMA BAY (EX. SOLYMAR NAAMA BAY)"],
        ['anytour_id'=>1654,'local_tv_id'=>12111,'name'=>"MOVENPICK RESORT SHARM EL SHEIKH NAAMA BAY"],
        ['anytour_id'=>228,'local_tv_id'=>498,'name'=>"TITANIC BEACH SPA & AQUA PARK"],
        ['anytour_id'=>4101,'local_tv_id'=>157359,'name'=>"AVIA RESORT & SPA"],
        ['anytour_id'=>477,'local_tv_id'=>1077,'name'=>"CLUB AQUA PLAZA (EX. SUNEOCLUB AQUA PLAZA)"],
        ['anytour_id'=>3726,'local_tv_id'=>109900,'name'=>"DREAM FUN WORLD"],
        ['anytour_id'=>2077,'local_tv_id'=>28666,'name'=>"PRIMASOL HANE FAMILY RESORT (EX. FAMILY HANE RESORT)"],
        ['anytour_id'=>2432,'local_tv_id'=>46672,'name'=>"JADORE DELUXE HOTEL & SPA"],
        ['anytour_id'=>3742,'local_tv_id'=>111129,'name'=>"SIDE MOON PALACE HOTEL"],
        ['anytour_id'=>3737,'local_tv_id'=>111045,'name'=>"VOX MARIS RESORT ADULTS ONLY 16+"],
        ['anytour_id'=>516,'local_tv_id'=>1146,'name'=>"AKRA ANTALYA"],
    ];
}

function v2_collection_priority_hotel_ids(): array
{
    return array_values(array_unique(array_merge(
        v2_priority_hotel_ids(),
        array_column(v2_published_hotel_monitor_bindings(), 'local_tv_id')
    )));
}

/** Keep every existing monthly target intact; only append disjoint new targets. */
function v2_append_published_hotel_monthly_plan(array $plan, array $hotels, array $departures, string $today, int $months, int $departureId): array
{
    $additional=array_values(array_diff(v2_collection_priority_hotel_ids(),v2_priority_hotel_ids()));
    if($additional===[])return $plan;
    try {
        $extra=v2_top500_monthly_plan($additional,$hotels,$departures,$today,$months,$departureId);
    } catch(RuntimeException $e) {
        if($e->getMessage()!=='no priority hotels have both a factual active catalog mapping and an active departure route')throw $e;
        $plan['published_hotels']=['requested'=>count($additional),'searchable'=>0,'state'=>'awaiting_catalog_or_route','hotel_ids'=>$additional];
        return $plan;
    }
    $keys=array_fill_keys(array_column($plan['targets'],'target_key'),true);
    foreach($extra['targets'] as $target){
        if(isset($keys[$target['target_key']]))throw new RuntimeException('published hotel target collides with existing target');
        $keys[$target['target_key']]=true;
        $plan['targets'][]=$target;
    }
    foreach(['source_hotel_count','catalog_hotel_count','hotel_count','unavailable_count','missing_catalog_count','no_departure_count'] as $key)$plan[$key]+=$extra[$key];
    foreach(['missing_catalog_ids','no_departure_hotels'] as $key)$plan[$key]=array_merge($plan[$key],$extra[$key]);
    $plan['batch_count']=count($plan['targets']);
    $plan['max_batch_size']=max($plan['max_batch_size'],$extra['max_batch_size']);
    $plan['published_hotels']=['requested'=>count($additional),'searchable'=>$extra['hotel_count'],'state'=>'included_in_existing_collector','hotel_ids'=>$additional];
    return $plan;
}

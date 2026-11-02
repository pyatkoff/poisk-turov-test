<?php
/** Published hotel identities already accepted by the AnyTour typed catalogue bridge.
 * The owner's original 492 hotel roster remains unchanged.
 * Typed source: max-search-bot/knowledge/hotels/manifest.json accepted_binding.accepted_chain.
 * Native source: accepted HotelDatabase catalog reads for commercial packets 6..10.
 * Cohorts are append-only so already-started monthly target identities remain stable.
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

/** These are native catalog_hotels.id identities, not legacy AnyTour integers.
 * Evidence: accepted catalog reads and publication manifests for packets 6..10.
 */
function v2_published_native_hotel_monitor_bindings(): array
{
    return [
        ['local_tv_id'=>482,'name'=>"SUNRISE Mamlouk Palace Resort"],
        ['local_tv_id'=>476,'name'=>"SUNRISE Garden Beach Resort"],
        ['local_tv_id'=>81173,'name'=>"White Hills Resort"],
        ['local_tv_id'=>9373,'name'=>"SUNRISE Diamond Beach Resort"],
        ['local_tv_id'=>32577,'name'=>"SUNRISE Montemare Resort"],
        ['local_tv_id'=>4191,'name'=>"Tropitel Sahl Hasheesh"],
        ['local_tv_id'=>416,'name'=>"SUNRISE Crystal Bay Resort"],
        ['local_tv_id'=>12112,'name'=>"SUNRISE Aqua Joy Resort"],
        ['local_tv_id'=>478,'name'=>"SUNRISE Holidays Resort"],
        ['local_tv_id'=>77914,'name'=>"SUNRISE TUCANA RESORT"],
        ['local_tv_id'=>237,'name'=>"SUNRISE ALMA BAY RESORT (EX. GRAND SEAS BY SUNRISE)"],
        ['local_tv_id'=>32575,'name'=>"SUNRISE ALORA AQUA PARK RESORT (EX. CAESAR PALACE)"],
        ['local_tv_id'=>112914,'name'=>"SUNRISE ANJUM RESORT"],
        ['local_tv_id'=>64895,'name'=>"MERAKI RESORT ADULTS ONLY 16+"],
        ['local_tv_id'=>262,'name'=>"JAZ AQUAMARINE RESORT (EX. IBEROTEL AQUAMARINE RESORT)"],
        ['local_tv_id'=>12117,'name'=>"JAZ BLUEMARINE"],
        ['local_tv_id'=>232,'name'=>"JAZ ELITE CASA DEL MAR BEACH (EX. JAZ CASA DEL MAR BEACH)"],
        ['local_tv_id'=>233,'name'=>"JAZ CASA DEL MAR RESORT"],
        ['local_tv_id'=>13872,'name'=>"JAZ MAKADINA"],
        ['local_tv_id'=>13873,'name'=>"JAZ MAKADI OASIS RESORT & CLUB (EX. IBEROTEL MAKADI OASIS CLUB)"],
        ['local_tv_id'=>280,'name'=>"JAZ MAKADI STAR & SPA"],
        ['local_tv_id'=>456,'name'=>"JAZ NEO DAR EL MADINA"],
        ['local_tv_id'=>265,'name'=>"JAZ LAMAYA RESORT (EX. IBEROTEL LAMAYA RESORT)"],
        ['local_tv_id'=>454,'name'=>"JAZ SOLAYA (EX. SOL Y MAR SOLAYA)"],
        ['local_tv_id'=>12119,'name'=>"STEIGENBERGER AL DAU BEACH"],
        ['local_tv_id'=>26044,'name'=>"STEIGENBERGER AQUA MAGIC"],
        ['local_tv_id'=>66150,'name'=>"STEIGENBERGER PURE LIFE STYLE ADULTS ONLY 16+"],
        ['local_tv_id'=>21475,'name'=>"BARON PALACE RESORT SAHL HASHEESH"],
        ['local_tv_id'=>1963,'name'=>"BARON RESORT"],
    ];
}

function v2_collection_priority_hotel_ids(): array
{
    return array_values(array_unique(array_merge(
        v2_priority_hotel_ids(),
        array_column(v2_published_hotel_monitor_bindings(), 'local_tv_id'),
        array_column(v2_published_native_hotel_monitor_bindings(), 'local_tv_id')
    )));
}

/** Keep every base and previously admitted cohort target intact. */
function v2_append_published_hotel_monthly_plan(array $plan, array $hotels, array $departures, string $today, int $months, int $departureId): array
{
    $seen=v2_priority_hotel_ids();
    $cohorts=[
        'accepted_typed_15'=>array_column(v2_published_hotel_monitor_bindings(),'local_tv_id'),
        'accepted_native_29'=>array_column(v2_published_native_hotel_monitor_bindings(),'local_tv_id'),
    ];
    $keys=array_fill_keys(array_column($plan['targets'],'target_key'),true);
    $summary=['requested'=>0,'searchable'=>0,'state'=>'awaiting_catalog_or_route','hotel_ids'=>[],'cohorts'=>[]];
    foreach($cohorts as $cohort=>$ids){
        $additional=array_values(array_diff(array_unique($ids),$seen));
        $seen=array_merge($seen,$additional);
        if($additional===[])continue;
        $summary['requested']+=count($additional);
        $summary['hotel_ids']=array_merge($summary['hotel_ids'],$additional);
        try {
            // Separate planning deliberately preserves every older cohort hash.
            $extra=v2_top500_monthly_plan($additional,$hotels,$departures,$today,$months,$departureId);
        } catch(RuntimeException $e) {
            if($e->getMessage()!=='no priority hotels have both a factual active catalog mapping and an active departure route')throw $e;
            $summary['cohorts'][$cohort]=['requested'=>count($additional),'searchable'=>0,'state'=>'awaiting_catalog_or_route'];
            continue;
        }
        foreach($extra['targets'] as $target){
            if(isset($keys[$target['target_key']]))throw new RuntimeException('published hotel target collides with existing target');
            $keys[$target['target_key']]=true;
            $plan['targets'][]=$target;
        }
        foreach(['source_hotel_count','catalog_hotel_count','hotel_count','unavailable_count','missing_catalog_count','no_departure_count'] as $key)$plan[$key]+=$extra[$key];
        foreach(['missing_catalog_ids','no_departure_hotels'] as $key)$plan[$key]=array_merge($plan[$key],$extra[$key]);
        $plan['max_batch_size']=max($plan['max_batch_size'],$extra['max_batch_size']);
        $summary['searchable']+=$extra['hotel_count'];
        $summary['cohorts'][$cohort]=['requested'=>count($additional),'searchable'=>$extra['hotel_count'],'state'=>'included_in_existing_collector'];
    }
    if($summary['requested']===0)return $plan;
    $plan['batch_count']=count($plan['targets']);
    if($summary['searchable']>0)$summary['state']=$summary['searchable']===$summary['requested']?'included_in_existing_collector':'partially_awaiting_catalog_or_route';
    $plan['published_hotels']=$summary;
    return $plan;
}

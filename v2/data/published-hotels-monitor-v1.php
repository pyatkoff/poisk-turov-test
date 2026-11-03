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

/** Four completed publications and 25 active native catalogue stock identities.
 * The stock catalogue read confirms identity only, never availability or a price.
 */
function v2_publication_stock_monitor_bindings_20261102(): array
{
    return [
        ['local_tv_id'=>358,'name'=>"RIXOS SHARM EL SHEIKH ADULTS ONLY"],
        ['local_tv_id'=>37668,'name'=>"RIXOS PREMIUM SEAGATE"],
        ['local_tv_id'=>174,'name'=>"PICKALBATROS DANA BEACH RESORT"],
        ['local_tv_id'=>55574,'name'=>"PICKALBATROS WHITE BEACH RESORT"],
        ['local_tv_id'=>383,'name'=>"JAZ GRAND MARSA (EX. JAZ GRAND RESTA)"],
        ['local_tv_id'=>269,'name'=>"JAZ SAMAYA RESORT (EX. IBEROTEL SAMAYA)"],
        ['local_tv_id'=>67897,'name'=>"JAZ MARAYA"],
        ['local_tv_id'=>75725,'name'=>"JAZ COSTA MARES ADULT ONLY (EX. IBEROTEL COSTA MARES)"],
        ['local_tv_id'=>108090,'name'=>"JAZ ELITE AMARA"],
        ['local_tv_id'=>73342,'name'=>"JAZ SOMA BEACH (EX. SOLYMAR SOMA BEACH)"],
        ['local_tv_id'=>278,'name'=>"JAZ BELVEDERE"],
        ['local_tv_id'=>281,'name'=>"JAZ MIRABEL BEACH"],
        ['local_tv_id'=>246,'name'=>"JAZ FAYROUZ RESORT (EX. HILTON SHARM FAYROUZ RESORT)"],
        ['local_tv_id'=>384,'name'=>"JAZ NEO REEF MARSA (EX. SOLYMAR REEF MARSA)"],
        ['local_tv_id'=>263,'name'=>"STEIGENBERGER CORAYA BEACH ADULTS ONLY 16+"],
        ['local_tv_id'=>267,'name'=>"IBEROTEL MAKADI BEACH"],
        ['local_tv_id'=>150,'name'=>"PICKALBATROS CITADEL RESORT SAHL HASHEESH"],
        ['local_tv_id'=>172,'name'=>"PICKALBATROS SANDS PORT GHALIB"],
        ['local_tv_id'=>275,'name'=>"PICKALBATROS THE PALACE PORT GHALIB"],
        ['local_tv_id'=>173,'name'=>"PICKALBATROS OASIS PORT GHALIB"],
        ['local_tv_id'=>110,'name'=>"PICKALBATROS JUNGLE AQUA PARK RESORT NEVERLAND"],
        ['local_tv_id'=>57028,'name'=>"PICKALBATROS AQUA PARK SHARM"],
        ['local_tv_id'=>125,'name'=>"PICKALBATROS AQUA BLU RESORT SHARM EL SHEIKH"],
        ['local_tv_id'=>178,'name'=>"DESERT ROSE RESORT"],
        ['local_tv_id'=>371,'name'=>"REEF OASIS BEACH RESORT"],
        ['local_tv_id'=>132,'name'=>"BEACH ALBATROS RESORT HURGADA"],
        ['local_tv_id'=>69155,'name'=>"STEIGENBERGER RESORT ALAYA MARSA ALAM ADULTS ONLY"],
        ['local_tv_id'=>69409,'name'=>"STEIGENBERGER RESORT RAS SOMA"],
        ['local_tv_id'=>4184,'name'=>"SENTIDO REEF OASIS SENSES RESORT (EX. REEF OASIS SENSES RESORT)"],
    ];
}

/** Twelve new native catalogue stock identities from bounded accepted catalog reads.
 * Goal: use the owner's existing collector for the next publication stock.
 * Keep the 492, typed15, native29 and stock29 cohorts and pending target keys intact.
 * This is an input addition only; no price, availability, quote or send acceptance.
 */
function v2_publication_stock_monitor_bindings_20261102b(): array
{
    return [
        ['local_tv_id'=>389,'name'=>"PICKALBATROS ROYAL MODERNA SHARM EL SHEIKH"],
        ['local_tv_id'=>109,'name'=>"PICKALBATROS AQUA VISTA RESORT"],
        ['local_tv_id'=>293,'name'=>"PICKALBATROS LAGUNA VISTA BEACH"],
        ['local_tv_id'=>107965,'name'=>"PICKALBATROS LAGUNA CLUB RESORT (EX. ALBATROS LAGUNA VISTA GARDEN)"],
        ['local_tv_id'=>393,'name'=>"PICKALBATROS ROYAL GRAND RESORT ADULTS ONLY 16+"],
        ['local_tv_id'=>78898,'name'=>"PICKALBATROS BLU SPA RESORT HURGHADA ADULTS ONLY 16+"],
        ['local_tv_id'=>83105,'name'=>"PICKALBATROS VITA RESORT PORTOFINO MARSA ALAM"],
        ['local_tv_id'=>79059,'name'=>"PICKALBATROS WATER VALLEY RESORT NEVERLAND HURGHADA"],
        ['local_tv_id'=>115918,'name'=>"RIXOS RADAMIS SHARM EL SHEIKH"],
        ['local_tv_id'=>56092,'name'=>"GRAVITY HOTEL & AQUA PARK HURGHADA"],
        ['local_tv_id'=>80964,'name'=>"MERAKI RESORT SHARM EL SHEIKH"],
        ['local_tv_id'=>107,'name'=>"BEACH ALBATROS RESORT MARSA ALAM"],
    ];
}

/** Nine new active native catalogue rows from accepted stock read41532640369.
 * Add input only, preserve every admitted target and incomplete search.
 * Combined Fanara Resort/Residence scope is not publication acceptance.
 */
function v2_publication_stock_monitor_bindings_20261102c(): array
{
    return [
        ['local_tv_id'=>264,'name'=>"JAZ DAHABEYA (EX. IBEROTEL DAHABEYA)"],
        ['local_tv_id'=>12107,'name'=>"JAZ FANARA RESORT & RESIDENCE (EX. IBEROTEL CLUB)"],
        ['local_tv_id'=>13869,'name'=>"JAZ NEO SARAYA PALMS (EX. JAZ MAKADI SARAYA PALMS)"],
        ['local_tv_id'=>245,'name'=>"JAZ SHARM DREAMS RESORT"],
        ['local_tv_id'=>43204,'name'=>"STEIGENBERGER ALCAZAR"],
        ['local_tv_id'=>13870,'name'=>"STEIGENBERGER MAKADI ADULTS ONLY"],
        ['local_tv_id'=>282,'name'=>"JAZ MIRABEL CLUB"],
        ['local_tv_id'=>1983,'name'=>"JAZ NEO SHARKS BAY (EX. JAZ SHARKS BAY)"],
        ['local_tv_id'=>111801,'name'=>"JAZ ELITE ASTERIA"],
    ];
}

/** Four active native stock identities from accepted bounded read41537721856.
 * Add monitor inputs only; no published price or availability is asserted.
 * Preserve all six prior cohorts and their unfinished monthly search identities.
 */
function v2_publication_stock_monitor_bindings_20261103(): array
{
    return [
        ['local_tv_id'=>287,'name'=>"KEMPINSKI HOTEL SOMA BAY"],
        ['local_tv_id'=>435,'name'=>"SHERATON SOMA BAY RESORT"],
        ['local_tv_id'=>109149,'name'=>"THE BREAKERS DIVING & SURFING LODGE"],
        ['local_tv_id'=>62505,'name'=>"ROBINSON SOMA BAY"],
    ];
}

/** Eighteen proven active native stock identities from bounded reads41603842788/41605170445.
 * Append input only; preserve all590 previous IDs, monthly targets and incomplete searches.
 * Stella465 is already in the original492 and is deliberately not repeated.
 */
function v2_publication_stock_monitor_bindings_20261103b(): array
{
    return [
        ['local_tv_id'=>432,'name'=>"SHERATON MIRAMAR RESORT"],
        ['local_tv_id'=>328,'name'=>"MOVENPICK RESORT & SPA EL GOUNA"],
        ['local_tv_id'=>382,'name'=>"RENAISSANCE GOLDEN VIEW BEACH"],
        ['local_tv_id'=>341,'name'=>"NUBIAN VILLAGE"],
        ['local_tv_id'=>340,'name'=>"NUBIAN ISLAND"],
        ['local_tv_id'=>272,'name'=>"CONTINENTAL HOTEL HURGHADA"],
        ['local_tv_id'=>260,'name'=>"PARK REGENCY SHARM EL SHEIKH (EX. HYATT REGENCY SSH)"],
        ['local_tv_id'=>21476,'name'=>"CLEOPATRA LUXURY RESORT MAKADI BAY (EX. ALDIANA MAKADI)"],
        ['local_tv_id'=>4181,'name'=>"HILTON MARSA ALAM NUBIAN RESORT"],
        ['local_tv_id'=>273,'name'=>"PALM ROYALE SOMA BAY (EX. SENTIDO PALM ROYALE)"],
        ['local_tv_id'=>441,'name'=>"SIVA GRAND BEACH"],
        ['local_tv_id'=>473,'name'=>"SUNNY DAYS EL PALACIO (EX. SUNNY DAYS RESORTS SPA & AQUA PARK)"],
        ['local_tv_id'=>483,'name'=>"SUNRISE ROYAL MAKADI RESORT"],
        ['local_tv_id'=>408,'name'=>"SAVOY SHARM EL SHEIKH"],
        ['local_tv_id'=>249,'name'=>"HILTON PLAZA"],
        ['local_tv_id'=>315,'name'=>"HURGADA MARRIOTT BEACH RESORT"],
        ['local_tv_id'=>21477,'name'=>"MOVENPICK WATERPARK RESORT & SPA SOMA BAY"],
        ['local_tv_id'=>250,'name'=>"SWISS INN RESORT HURGHADA (EX. HILTON RESORT)"],
    ];
}

/** Six accepted hotel titles resolved against the actual native catalog in bounded read41616228743. These are native identities,not invented legacy AnyTour integers. */
function v2_legacy_publication_monitor_bindings_20261103(): array
{
    return [
        ['local_tv_id'=>310,'name'=>"PICKALBATROS GOLF BEACH RESORT (EX. GOLF BEACH RESORT MANAGED BY RIXOS)"],
        ['local_tv_id'=>469,'name'=>"SULTAN GARDENS RESORT"],
        ['local_tv_id'=>67910,'name'=>"RIXOS PREMIUM MAGAWISH SUITES & VILLAS"],
        ['local_tv_id'=>381,'name'=>"REHANA ROYAL BEACH RESORT AQUA PARK & SPA"],
        ['local_tv_id'=>68323,'name'=>"SUNRISE SOLARA AQUA PARK RESORT (EX.CAESAR DREAMS RESORT BY SUNRISE)"],
        ['local_tv_id'=>1971,'name'=>"PICKALBATROS PALACE RESORT SHARM EL SHEIKH (EX. CYRENE GRAND)"],
    ];
}

/** Six newly admitted reserve identities proven in accepted bounded catalog reads. Input admission only;no price or availability assertion. */
function v2_publication_stock_monitor_bindings_20261103c(): array
{
    return [
        ['local_tv_id'=>62504,'name'=>"SINDBAD CLUB"],
        ['local_tv_id'=>231,'name'=>"GRAND MAKADI"],
        ['local_tv_id'=>15693,'name'=>"THE MAKADI SPA ADULTS ONLY 18+"],
        ['local_tv_id'=>65779,'name'=>"TITANIC ROYAL HOTEL"],
        ['local_tv_id'=>932,'name'=>"TITANIC PALACE RESORT & SPA"],
        ['local_tv_id'=>497,'name'=>"TITANIC RESORT & AQUA PARK"],
    ];
}

/** One promoted reserve identity from the accepted native catalog read. Input admission only. */
function v2_publication_stock_monitor_bindings_20261103d(): array
{
    return [
        ['local_tv_id'=>60589,'name'=>"JAZ NEO IVORY (EX. SOLYMAR IVORY SUITES)"],
    ];
}

/** One promoted native reserve identity from an accepted catalog read. */
function v2_publication_stock_monitor_bindings_20261103e(): array
{
    return [
        ['local_tv_id'=>68907,'name'=>"AMARINA ABU SOMA RESORT & AQUAPARK"],
    ];
}

/** Two promoted reserve identities proven by accepted native catalog reads. */
function v2_publication_stock_monitor_bindings_20261103f(): array
{
    return [
        ['local_tv_id'=>343,'name'=>"OLD PALACE RESORT SAHL HASHESH"],
        ['local_tv_id'=>83563,'name'=>"SERRY BEACH RESORT"],
    ];
}

function v2_collection_priority_hotel_ids(): array
{
    return array_values(array_unique(array_merge(
        v2_priority_hotel_ids(),
        array_column(v2_published_hotel_monitor_bindings(), 'local_tv_id'),
        array_column(v2_published_native_hotel_monitor_bindings(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261102(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261102b(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261102c(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103b(), 'local_tv_id'),
        array_column(v2_legacy_publication_monitor_bindings_20261103(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103c(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103d(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103e(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103f(), 'local_tv_id')
    )));
}

/** Keep every base and previously admitted cohort target intact. */
function v2_append_published_hotel_monthly_plan(array $plan, array $hotels, array $departures, string $today, int $months, int $departureId): array
{
    $seen=v2_priority_hotel_ids();
    $cohorts=[
        'accepted_typed_15'=>array_column(v2_published_hotel_monitor_bindings(),'local_tv_id'),
        'accepted_native_29'=>array_column(v2_published_native_hotel_monitor_bindings(),'local_tv_id'),
        'publication_stock_20261102_29'=>array_column(v2_publication_stock_monitor_bindings_20261102(),'local_tv_id'),
        'publication_stock_20261102b_12'=>array_column(v2_publication_stock_monitor_bindings_20261102b(),'local_tv_id'),
        'publication_stock_20261102c_9'=>array_column(v2_publication_stock_monitor_bindings_20261102c(),'local_tv_id'),
        'publication_stock_20261103_4'=>array_column(v2_publication_stock_monitor_bindings_20261103(),'local_tv_id'),
        'publication_stock_20261103b_18'=>array_column(v2_publication_stock_monitor_bindings_20261103b(),'local_tv_id'),
        'legacy_publication_20261103_6'=>array_column(v2_legacy_publication_monitor_bindings_20261103(),'local_tv_id'),
        'publication_stock_20261103c_6'=>array_column(v2_publication_stock_monitor_bindings_20261103c(),'local_tv_id'),
        'publication_stock_20261103d_1'=>array_column(v2_publication_stock_monitor_bindings_20261103d(),'local_tv_id'),
        'publication_stock_20261103e_1'=>array_column(v2_publication_stock_monitor_bindings_20261103e(),'local_tv_id'),
        'publication_stock_20261103f_2'=>array_column(v2_publication_stock_monitor_bindings_20261103f(),'local_tv_id'),
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

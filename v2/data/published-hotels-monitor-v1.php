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

/** One promoted native reserve identity, proven in the accepted bounded catalogue read. */
function v2_publication_stock_monitor_bindings_20261103g(): array
{
    return [
        ['local_tv_id'=>235,'name'=>"GRAND RESORT HURGHADA"],
    ];
}

/** Four promoted native reserve identities from accepted country1 catalogue reads. */
function v2_publication_stock_monitor_bindings_20261103h(): array
{
    return [
        ['local_tv_id'=>73343,'name'=>"THE GRAND PALACE ADULTS ONLY 18+"],
        ['local_tv_id'=>418,'name'=>"SERENITY ALPHA BEACH (EX. SERENITY MAKADI BEACH)"],
        ['local_tv_id'=>464,'name'=>"STEIGENBERGER GOLF RESORT EL GOUNA"],
        ['local_tv_id'=>103021,'name'=>"THE CHEDI EL GOUNA"],
    ];
}

/** Two promoted El Gouna reserve identities from the accepted bounded catalogue read. */
function v2_publication_stock_monitor_bindings_20261103i(): array
{
    return [
        ['local_tv_id'=>129,'name'=>"COOKS CLUB EL GOUNA ADULTS ONLY 16+"],
        ['local_tv_id'=>74258,'name'=>"CASA COOK EL GOUNA ADULTS ONLY"],
    ];
}

/** One promoted Sharm reserve identity proven by the accepted bounded native catalogue read. */
function v2_publication_stock_monitor_bindings_20261103j(): array
{
    return [
        ['local_tv_id'=>165,'name'=>"CORAL SEA WATER WORLD (EX. CORAL SEA RESORT)"],
    ];
}

/** Four proven native publication/reserve inputs from accepted bounded catalogue reads. */
function v2_publication_stock_monitor_bindings_20261103k(): array
{
    return [
        ['local_tv_id'=>111,'name'=>"PICKALBATROS PALACE RESORT HURGHADA"],
        ['local_tv_id'=>163,'name'=>"CORAL SEA HOLIDAY RESORT & AQUA PARK"],
        ['local_tv_id'=>12116,'name'=>"CORAL SEA AQUA CLUB"],
        ['local_tv_id'=>56091,'name'=>"ANCIENT SANDS GOLF RESORT & RESIDENCES"],
    ];
}

/** Three proven native publication/reserve identities from accepted bounded catalogue reads. */
function v2_publication_stock_monitor_bindings_20261103l(): array
{
    return [
        ['local_tv_id'=>103124,'name'=>"IBEROTEL LUXOR"],
        ['local_tv_id'=>488,'name'=>"THE THREE CORNERS FAYROUZ PLAZA BEACH RESORT"],
        ['local_tv_id'=>102031,'name'=>"STEIGENBERGER NILE PALACE LUXOR"],
    ];
}

/** Proven native publication inputs from the closed bounded catalogue read. */
function v2_publication_stock_monitor_bindings_20261103m(): array
{
    return [
        ['local_tv_id'=>98723,'name'=>"CLEOPATRA LUXURY RESORT SHARM ADULTS ONLY 16+"],
        ['local_tv_id'=>139,'name'=>"BRAYKA BAY RESORT"],
        ['local_tv_id'=>342,'name'=>"THE OBEROI SAHL HASHEESH"],
    ];
}

/** Four native publication inputs proven by accepted bounded catalogue reads. */
function v2_publication_stock_monitor_bindings_20261103n(): array
{
    return [
        ['local_tv_id'=>1291,'name'=>"AKRA KEMER (EX. KEMER BARUT COLLECTION)"],
        ['local_tv_id'=>15869,'name'=>"AKRA SORGUN TUI BLUE SENSATORI"],
        ['local_tv_id'=>253,'name'=>"SAFIR SHARM WATERFALLS (EX. HILTON)"],
        ['local_tv_id'=>75377,'name'=>"LIBERTY FABAY"],
    ];
}

/** Two native hotel identities from the accepted bounded Turkey catalogue read. */
function v2_publication_stock_monitor_bindings_20261103o(): array
{
    return [
        ['local_tv_id'=>74503,'name'=>"NG PHASELIS BAY"],
        ['local_tv_id'=>1582,'name'=>"VOYAGE BELEK GOLF & SPA"],
    ];
}

function v2_publication_stock_monitor_bindings_20261103p(): array
{
    return [
        ['local_tv_id'=>1449,'name'=>"RIXOS PREMIUM BELEK THE LAND OF LEGENDS FREE ACCESS"],
        ['local_tv_id'=>1453,'name'=>"RIXOS PREMIUM TEKIROVA THE LAND OF LEGENDS FREE ACCESS"],
        ['local_tv_id'=>1151,'name'=>"DELPHIN PALACE DELUXE COLLECTION"],
        ['local_tv_id'=>1583,'name'=>"VOYAGE SORGUN"],
    ];
}

function v2_publication_stock_monitor_bindings_20261103q(): array
{
    return [
        ['local_tv_id'=>1379,'name'=>"MIRACLE RESORT"],
        ['local_tv_id'=>1133,'name'=>"CONCORDE DE LUXE RESORT"],
        ['local_tv_id'=>27316,'name'=>"REGNUM CARYA GOLF & SPA"],
        ['local_tv_id'=>1559,'name'=>"TITANIC DELUXE LARA"],
        ['local_tv_id'=>3405,'name'=>"MAXX ROYAL BELEK GOLF & SPA"],
    ];
}

function v2_publication_stock_monitor_bindings_20261104(): array
{
    return [
        ['local_tv_id'=>1417,'name'=>"PALOMA GRIDA"],
        ['local_tv_id'=>1348,'name'=>"SEVEN SEAS HOTEL LIFE (EX. OTIUM HOTEL LIFE)"],
    ];
}

function v2_publication_stock_monitor_bindings_20261104b(): array
{
    return [
        ['local_tv_id'=>1199,'name'=>"GLORIA SERENITY RESORT"],
        ['local_tv_id'=>1464,'name'=>"ROYAL WINGS"],
        ['local_tv_id'=>9322,'name'=>"PALOMA PASHA RESORT"],
        ['local_tv_id'=>1672,'name'=>"KEMPINSKI BARBAROSS BAY HOTEL"],
    ];
}

function v2_publication_stock_monitor_bindings_20261104c(): array
{
    return [
        ['local_tv_id'=>1659,'name'=>"TUI BLUE SARIGERME PARK"],
        ['local_tv_id'=>21695,'name'=>"TUI BLUE SENO ADULTS ONLY 16+"],
        ['local_tv_id'=>28668,'name'=>"RIXOS PREMIUM GOCEK ADULTS ONLY 13+"],
        ['local_tv_id'=>55205,'name'=>"EFTALIA OCEAN"],
        ['local_tv_id'=>1334,'name'=>"LEODIKYA KIRMAN PREMIUM (EX. KIRMAN LEODIKYA RESORT)"],
        ['local_tv_id'=>1715,'name'=>"VOYAGE TORBA"],
    ];
}

function v2_publication_stock_monitor_bindings_20261104d(): array
{
    return [
        ['local_tv_id'=>1543,'name'=>"SUSESI LUXURY RESORT"],
    ];
}

function v2_publication_stock_monitor_bindings_20261104e(): array
{
    return [
        ['local_tv_id'=>17480,'name'=>"TUI BLUE PALM GARDEN"],
        ['local_tv_id'=>1100,'name'=>"CLUB HOTEL SERA"],
    ];
}

/** Actual batch49 publication41961272340; native catalogue1422 is country4/active1 and canonically paired. */
function v2_publication_stock_monitor_bindings_20261105(): array
{
    return [
        ['local_tv_id'=>1422,'name'=>"PAPILLON ZEUGMA RELAXURY"],
    ];
}

/** Actual batch51 publication41969870445; native78251 country4/active1 and paired in both channels. */
function v2_publication_stock_monitor_bindings_20261105b(): array
{
    return [
        ['local_tv_id'=>78251,'name'=>"CULLINAN BELEK (EX. CULLINAN GOLF RESORT BELEK)"],
    ];
}

function v2_collection_priority_hotel_ids(?AnyTourAnexSearchMappingRegistry $registry = null): array
{
    return array_values(array_unique(array_merge(
        v2_priority_hotel_ids($registry),
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
        array_column(v2_publication_stock_monitor_bindings_20261103f(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103g(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103h(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103i(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103j(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103k(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103l(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103m(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103n(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103o(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103p(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261103q(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261104(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261104b(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261104c(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261104d(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261104e(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261105(), 'local_tv_id'),
        array_column(v2_publication_stock_monitor_bindings_20261105b(), 'local_tv_id')
    )));
}

/** Owner-private Site export, registry217, 2026-11-11:509 published +96 accepted/queued.
 * Exact saved Tourvisor namespace only; no ANEX/name/legacy inference.
 * Source projection SHA256: 83e81812a15ce16a1a792ab58c2d58d5c5a7de23928cee98344be8646cc3746a
 * Append-only input snapshot, not current price/availability or an automatic Site sync.
 * Keep v2_collection_priority_hotel_ids unchanged: it also defines the historical
 * TOP500 exclusion set. Adding these IDs there would regroup old monthly targets.
 */
function v2_sites_hotel_monitor_ids_20261111(): array
{
    return [
        106,109,110,111,114,124,125,129,132,134,139,140,150,163,165,172,173,174,178,195,212,213,231,232,
        233,235,237,240,241,245,246,247,249,250,253,254,260,262,263,264,265,267,268,269,272,273,275,278,
        280,281,282,287,288,293,302,310,315,319,325,328,340,341,342,343,352,358,364,365,371,380,381,382,
        383,384,386,389,393,408,415,416,418,432,435,441,443,454,456,462,464,465,466,468,469,476,478,482,
        483,488,496,497,498,501,502,512,513,613,681,703,713,748,800,817,818,932,948,949,954,955,956,961,
        964,967,968,969,970,973,975,981,983,990,996,1004,1013,1014,1015,1026,1029,1031,1032,1033,1035,1042,1051,1055,
        1065,1067,1070,1074,1077,1095,1096,1100,1101,1105,1112,1124,1126,1133,1134,1137,1139,1140,1141,1142,1143,1145,1146,1151,
        1154,1165,1173,1183,1186,1189,1196,1198,1199,1201,1203,1212,1216,1217,1218,1221,1228,1236,1246,1255,1257,1258,1260,1273,
        1287,1291,1292,1293,1294,1313,1330,1331,1334,1335,1336,1337,1338,1339,1344,1346,1348,1350,1352,1353,1354,1362,1368,1369,
        1370,1379,1380,1402,1410,1416,1417,1420,1421,1422,1429,1433,1436,1439,1440,1449,1451,1452,1453,1456,1459,1464,1465,1469,
        1474,1483,1486,1491,1493,1494,1501,1507,1512,1521,1530,1531,1543,1551,1555,1559,1569,1579,1582,1583,1585,1586,1588,1591,
        1614,1647,1649,1653,1659,1672,1684,1690,1699,1702,1712,1715,1716,1959,1960,1962,1963,1971,1972,1981,1983,2015,2050,2144,
        2175,2219,2222,2469,2514,2536,2559,2569,2575,2651,2673,2688,2791,2810,3405,3415,3434,3452,3469,4054,4060,4071,4074,4078,
        4079,4091,4095,4181,4184,4185,4191,4432,4499,5389,7938,7985,8008,8053,8964,9260,9287,9303,9321,9322,9354,9373,9374,9419,
        9432,9434,12107,12111,12112,12114,12116,12117,12119,13844,13869,13870,13872,13873,13941,14133,14135,14140,15693,15869,15871,15872,15873,15879,
        15889,15904,15905,15913,15917,16750,16794,17480,17595,17669,20288,20971,21475,21476,21477,21673,21695,21817,21847,22660,22791,23766,24462,24492,
        24778,24898,24909,26044,26530,27316,27349,27426,27642,27647,27652,28159,28325,28326,28327,28448,28509,28512,28581,28666,28668,28704,28754,28779,
        30095,30158,32232,32339,32342,32356,32575,32577,33313,35313,36451,36453,37168,37360,37364,37396,37527,37668,41149,41495,42662,42928,43060,43204,
        43236,45199,45496,45516,46670,46672,46770,47025,47027,47470,48863,49083,49342,49345,49365,50624,50659,50869,51238,52925,53514,53578,54193,54592,
        54605,54621,55205,55511,55574,55647,55721,55828,55925,56091,56092,57028,57039,57064,57066,57082,57217,57650,57652,58473,58987,59116,59563,60589,
        61159,61591,61760,62504,62505,63673,63674,63833,64371,64670,64672,64895,64914,65020,65254,65266,65712,65779,65782,65877,65895,66150,66496,66555,
        66564,67897,67910,68323,68661,68907,68984,69116,69155,69184,69261,69409,70030,70032,70033,70048,70233,70289,71147,71259,73055,73342,73343,73974,
        74032,74258,74503,75040,75041,75377,75725,76515,76588,77625,77914,78251,78898,78905,79059,79071,80605,80713,80915,80964,81154,81173,81358,81578,
        82545,82567,82606,82716,83105,83189,83563,88750,90236,91404,95159,95165,98723,101694,102031,102774,103021,103124,103731,107938,107940,107965,108090,108146,
        108167,108942,109149,109655,109786,109900,110289,111045,111129,111801,112914,113292,115918,116932,116934,117935,118475,119046,120117,120209,123374,123757,128087,128508,
        129161,129406,132002,132016,157359,
    ];
}

function v2_all_hotel_monitor_input_ids(?AnyTourAnexSearchMappingRegistry $registry = null): array
{
    return array_values(array_unique(array_merge(v2_collection_priority_hotel_ids($registry),v2_sites_hotel_monitor_ids_20261111())));
}

/** Keep every base and previously admitted cohort target intact. */
function v2_append_published_hotel_monthly_plan(array $plan, array $hotels, array $departures, string $today, int $months, int $departureId, bool $includeSitesRoster = false): array
{
    $seen=[];
    foreach($plan['targets'] as $target) $seen=array_merge($seen,$target['hotel_ids']);
    $seen=array_values(array_unique($seen));
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
        'publication_stock_20261103g_1'=>array_column(v2_publication_stock_monitor_bindings_20261103g(),'local_tv_id'),
        'publication_stock_20261103h_4'=>array_column(v2_publication_stock_monitor_bindings_20261103h(),'local_tv_id'),
        'publication_stock_20261103i_2'=>array_column(v2_publication_stock_monitor_bindings_20261103i(),'local_tv_id'),
        'publication_stock_20261103j_1'=>array_column(v2_publication_stock_monitor_bindings_20261103j(),'local_tv_id'),
        'publication_stock_20261103k_4'=>array_column(v2_publication_stock_monitor_bindings_20261103k(),'local_tv_id'),
        'publication_stock_20261103l_3'=>array_column(v2_publication_stock_monitor_bindings_20261103l(),'local_tv_id'),
        'publication_stock_20261103m_3'=>array_column(v2_publication_stock_monitor_bindings_20261103m(),'local_tv_id'),
        'publication_stock_20261103n_4'=>array_column(v2_publication_stock_monitor_bindings_20261103n(),'local_tv_id'),
        'publication_stock_20261103o_2'=>array_column(v2_publication_stock_monitor_bindings_20261103o(),'local_tv_id'),
        'publication_stock_20261103p_4'=>array_column(v2_publication_stock_monitor_bindings_20261103p(),'local_tv_id'),
        'publication_stock_20261103q_5'=>array_column(v2_publication_stock_monitor_bindings_20261103q(),'local_tv_id'),
        'publication_stock_20261104_2'=>array_column(v2_publication_stock_monitor_bindings_20261104(),'local_tv_id'),
        'publication_stock_20261104b_4'=>array_column(v2_publication_stock_monitor_bindings_20261104b(),'local_tv_id'),
        'publication_stock_20261104c_6'=>array_column(v2_publication_stock_monitor_bindings_20261104c(),'local_tv_id'),
        'publication_stock_20261104d_1'=>array_column(v2_publication_stock_monitor_bindings_20261104d(),'local_tv_id'),
        'publication_stock_20261104e_2'=>array_column(v2_publication_stock_monitor_bindings_20261104e(),'local_tv_id'),
        'publication_stock_20261105_1'=>array_column(v2_publication_stock_monitor_bindings_20261105(),'local_tv_id'),
        'publication_stock_20261105b_1'=>array_column(v2_publication_stock_monitor_bindings_20261105b(),'local_tv_id'),
    ];
    if($includeSitesRoster)$cohorts['sites_registry217_20261111']=v2_sites_hotel_monitor_ids_20261111();
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

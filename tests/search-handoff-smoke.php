<?php
require __DIR__.'/../v2/form-defaults.php';
require __DIR__.'/../v2/seo-page-primitives-v1.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$generic=v2_seo_search_handoff_url('/poisk-turov/',[]);
check($generic==='/poisk-turov/','Generic handoff changed');
$country=v2_seo_search_handoff_url('/poisk-turov/',['country'=>4]);
check($country==='/poisk-turov/?country=4','Country handoff changed');
$state=v2_seo_offer_search_state(['country'=>4,'region'=>22],['departureId'=>1,'departureDate'=>'2099-09-17','nights'=>9]);
parse_str(parse_url(v2_seo_search_handoff_url('/poisk-turov/',$state),PHP_URL_QUERY),$query);
$form=v2_form_defaults($query);
check($form['date_from']==='2099-09-17'&&$form['date_till']==='2099-09-17','Offer date lost');
check($form['nights_from']===9&&$form['nights_till']===9,'Offer duration lost');
check($query['region']==='22'&&$form['count_people']===2,'Destination/party lost');
$hot=['dateFrom'=>'2099-09-10','dateTo'=>'2099-09-24','daysFrom'=>10,'daysTill'=>10];
parse_str(parse_url(v2_seo_search_handoff_url('/poisk-turov/',$hot),PHP_URL_QUERY),$hotQuery);
check($hotQuery===array_map('strval',$hot),'Hot date/duration handoff changed');
$filtered=v2_seo_search_handoff_url('/poisk-turov/',['country'=>4,'unsupported'=>'drop','child_age'=>['7']]);
check($filtered==='/poisk-turov/?country=4','Handoff allowlist changed');
$form=v2_form_defaults(['daysFrom'=>'8','daysTill'=>'12','child_age'=>['0','17']]);
check($form['child_ages']===[0,17]&&$form['nights_till']===12,'Family handoff lost');
check(v2_form_defaults(['child_age'=>['18','bad',[]]])['child_ages']===[],'Invalid child age accepted');

// Malformed public query values must recover before the form is rendered.
$fallback=new DateTimeImmutable('2099-10-05');
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
try {
    foreach (["2099-09\0-17",[],['2099-09-17'],null,'','invalid','2099-02-29'] as $invalidDate) {
        check(v2_date_value($invalidDate,$fallback)==='2099-10-05','Malformed date did not use the existing fallback');
    }
    foreach (['2099-09-17'=>'2099-09-17',' 2099-9-7 '=>'2099-09-07','2096-02-29'=>'2096-02-29'] as $raw=>$expected) {
        check(v2_date_value($raw,$fallback)===$expected,'Valid date normalization changed');
    }
    $defaultForm=v2_form_defaults([]);
    foreach (['date_from','dateFrom','date_till','dateTo'] as $alias) {
        foreach (["2099-09\0-17",['2099-09-17']] as $invalidDate) {
            check(v2_form_defaults([$alias=>$invalidDate])===$defaultForm,'Malformed date alias did not recover to normal form defaults');
        }
    }
} finally {
    restore_error_handler();
}

// Exercise the hotel page that actually emits the links, not just its URL helper.
// Every render restores request globals so the existing generic contracts above
// and subsequent callers remain independent of this route's preview context.
$renderHotel = static function (array $incoming, string $prefix = ''): array {
    $savedGet = $_GET;
    $savedServer = $_SERVER;
    $savedGlobals = [];
    foreach (['SP_PREVIEW_NAV_BUFFER', 'SP_SCHEMA_CURRENT_PATH'] as $key) {
        $savedGlobals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
    }
    $bufferLevel = ob_get_level();
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        $_GET = $incoming;
        $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/fixtures/no-site-config';
        $_SERVER['HTTP_HOST'] = 'anytoour.ru';
        $_SERVER['SCRIPT_NAME'] = $prefix . '/jaz-makadi-saraya/index.php';
        $_SERVER['REQUEST_URI'] = $prefix . '/jaz-makadi-saraya/';
        ob_start();
        include __DIR__ . '/../v2/jaz-makadi-saraya/index.php';
        $html = (string)ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
        $_GET = $savedGet;
        $_SERVER = $savedServer;
        foreach ($savedGlobals as $key => [$existed, $value]) {
            if ($existed) $GLOBALS[$key] = $value;
            else unset($GLOBALS[$key]);
        }
        restore_error_handler();
    }

    preg_match_all('/<a\b[^>]*\bdata-hotel-search\b[^>]*>/i', $html, $anchors);
    check(count($anchors[0]) === 2, 'Hotel page must render both contextual search links');
    $urls = [];
    foreach ($anchors[0] as $anchor) {
        check((bool)preg_match('/\bhref=(["\'])(.*?)\1/s', $anchor, $href), 'Hotel search link has no href');
        $urls[] = html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    check($urls[0] === $urls[1], 'Hotel CTAs disagree about the trip');
    check(parse_url($urls[0], PHP_URL_PATH) === $prefix . '/poisk-turov/', 'Hotel handoff escaped its site context');
    parse_str((string)parse_url($urls[0], PHP_URL_QUERY), $query);
    foreach (['country' => '1', 'region' => '5', 'hotel' => '268', 'search3_restore' => '1'] as $key => $expected) {
        check(($query[$key] ?? null) === $expected, 'Hotel identity/manual confirmation lost: ' . $key);
    }
    check((bool)preg_match('/<meta\b[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*\bnoindex\b/i', $html), 'Hotel preview became indexable');
    check(!preg_match('/"@type"\s*:\s*"(?:Offer|AggregateOffer)"/', $html), 'Unverified hotel offer was advertised');
    check(str_contains(strip_tags($html), 'Madinat Makadi'), 'Hotel address is missing from server HTML');
    $hasNotice = (bool)preg_match('/<[^>]*class=["\'][^"\']*\bjaz-handoff-notice\b[^"\']*["\'][^>]*>/i', $html, $notice);
    if ($hasNotice) check(!preg_match('/\s(?:hidden|aria-hidden=["\']true["\'])(?:\s|>|\/)/i', $notice[0]), 'Handoff warning is hidden');
    return ['html' => $html, 'url' => $urls[0], 'query' => $query, 'notice' => $hasNotice];
};

$plainHotel = $renderHotel([]);
check(!array_diff(array_keys($plainHotel['query']), ['country', 'region', 'hotel', 'search3_restore']), 'Hotel invented unknown trip conditions');
check(!$plainHotel['notice'], 'A fresh hotel visit should not show a malformed-link warning');

$trip = [
    'from' => '2', 'dateFrom' => '2099-09-17', 'dateTo' => '2099-09-22',
    'daysFrom' => '8', 'daysTill' => '12', 'count_people' => '3',
    'food' => '7', 'operator' => '12', 'price_from' => '120000', 'price_till' => '180000',
    'arrival' => '51', 'hotel_type' => '2', 'hotel_service' => ['7', '12'],
    'onlyDirect' => '1', 'onlyCharter' => '1',
];
$knownHotel = $renderHotel($trip);
foreach ($trip as $key => $expected) {
    check(($knownHotel['query'][$key] ?? null) === $expected, 'Hotel dropped a known trip condition: ' . $key);
}
check(!$knownHotel['notice'], 'Valid trip conditions unexpectedly require correction');
check(substr_count($knownHotel['url'], 'hotel_service%5B%5D=') === 2, 'Hotel services must use repeated [] keys understood by search hydration');

$aliases = $renderHotel([
    'date_from' => '2099-09-18', 'dateFrom' => '2099-09-01',
    'date_till' => '2099-09-23', 'dateTo' => '2099-09-02',
    'days_from' => '9', 'daysFrom' => '3', 'days_till' => '13', 'daysTill' => '4',
    'only_direct' => 'true', 'only_charter' => 'yes',
]);
foreach (['dateFrom' => '2099-09-18', 'dateTo' => '2099-09-23', 'daysFrom' => '9', 'daysTill' => '13', 'onlyDirect' => '1', 'onlyCharter' => '1'] as $key => $expected) {
    check(($aliases['query'][$key] ?? null) === $expected, 'Hotel legacy alias/precedence lost: ' . $key);
}

foreach ([['0', '17'], ['5', '5']] as $ages) {
    $family = $renderHotel($trip + ['child_count' => '2', 'child_age' => $ages]);
    check(($family['query']['child_count'] ?? null) === '2' && ($family['query']['child_age'] ?? null) === $ages, 'Hotel lost the complete family');
    check(substr_count($family['url'], 'child_age%5B%5D=') === 2, 'Child ages must use repeated [] keys understood by search hydration');
    check(!preg_match('/child_age%5B\d+%5D=/i', $family['url']), 'Indexed ages disappear during search hydration');
    $familyForm = v2_form_defaults($family['query']);
    check($familyForm['count_people'] === 3 && $familyForm['child_ages'] === array_map('intval', $ages), 'Receiving server form lost family conditions');
    check(!$family['notice'], 'Valid complete family unexpectedly requires correction');
}

$lockedHotel = $renderHotel($trip + ['country' => '4', 'region' => '22', 'hotel' => '999', 'subregion' => '77', 'search3_hotel' => '999', 'search3_search' => '123', 'unexpected' => 'discard']);
foreach (['subregion', 'search3_hotel', 'search3_search', 'unexpected'] as $key) {
    check(!array_key_exists($key, $lockedHotel['query']), 'Hotel forwarded unrelated search identity: ' . $key);
}
check($lockedHotel['notice'], 'A supplied subregion must not disappear without an explanation');

foreach ([
    ['from' => ['2']],
    ['count_people' => '7'],
    ['dateFrom' => '2099-02-30'],
    ['dateFrom' => ['2099-09-17']],
    ['daysFrom' => '29'],
    ['price_till' => '-1'],
] as $invalid) {
    $correctable = $renderHotel($invalid);
    check($correctable['notice'], 'Invalid hotel query must visibly ask for correction');
    foreach (array_keys($invalid) as $key) {
        check(!array_key_exists($key, $correctable['query']), 'Hotel forwarded a malformed condition: ' . $key);
    }
}

foreach ([
    ['child_count' => '2'],
    ['child_count' => '2', 'child_age' => ['5']],
    ['child_count' => '2', 'child_age' => ['5', '18']],
    ['child_age' => ['0', [], '17']],
    ['child_age' => ['1', '2', '3', '4']],
] as $invalidFamily) {
    $correctable = $renderHotel(['count_people' => '3'] + $invalidFamily);
    check($correctable['notice'], 'Incomplete family must visibly ask for correction');
    check(!array_key_exists('child_count', $correctable['query']) && !array_key_exists('child_age', $correctable['query']), 'Hotel forwarded a partial family or invented a child age');
    check(($correctable['query']['count_people'] ?? null) === '3', 'Malformed children erased known adults');
}

$past = $renderHotel(['dateFrom' => '2000-01-01', 'dateTo' => '2000-01-03']);
check($past['notice'], 'Expired trip dates must visibly ask for correction');
foreach (['dateFrom' => '2000-01-01', 'dateTo' => '2000-01-03'] as $key => $original) {
    check(!isset($past['query'][$key]) || $past['query'][$key] === $original, 'Expired dates were silently replaced with a new trip');
}

$preview = $renderHotel($trip + ['child_count' => '2', 'child_age' => ['0', '17']], '/_preview/search3-site-candidate');
check(($preview['query']['child_age'] ?? null) === ['0', '17'], 'Preview handoff lost children');
check(v2_seo_search_handoff_url('/poisk-turov/', []) === '/poisk-turov/', 'Hotel rendering leaked preview state to other routes');
echo "SEARCH_HANDOFF_OK\n";

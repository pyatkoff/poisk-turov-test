<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-surcharge-cache-autosave.php';

function cache_autosave_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cache_autosave_offer(
    string $suffix,
    string $base,
    string $program = 'program_1',
    string $date = '2026-10-30',
    int $nights = 7,
    string $tour = 'tour_1'
): array {
    return [
        'provider' => 'andromeda',
        'operator_ref' => 'operator_1',
        'offer_ref' => 'offer_' . hash('sha256', 'cache-offer-' . $suffix),
        'external_hotel_id' => 'hotel_' . $suffix,
        'room_raw' => 'room_' . $suffix,
        'meal' => ['raw_label' => 'AI_' . $suffix],
        'check_in' => $date,
        'nights' => $nights,
        'adults' => 2,
        'children' => 0,
        'price' => ['amount' => $base, 'currency' => 'RUB'],
        'transport_context' => [
            'freight_external' => true,
            'program_ref' => $program,
            'tour_ref' => $tour,
            'spo_ref' => 'spo_' . $suffix,
        ],
    ];
}

function cache_autosave_fact(string $base = '185125', string $surcharge = '14265', string $total = '199390'): array
{
    return [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'state' => 'estimated',
        'search_price' => ['amount' => $base, 'currency' => 'RUB'],
        'party_surcharge' => [
            'amount' => $surcharge,
            'currency' => 'RUB',
            'source' => 'andromeda_get_flights_transport',
        ],
        'search_price_with_surcharge' => [
            'amount' => $total,
            'currency' => 'RUB',
            'source' => 'derived_search_estimate',
        ],
        'surcharge_scope' => 'party',
        'arithmetic_applied' => true,
        'final_price_verified' => false,
    ];
}

function cache_autosave_program_fact(string $base, string $surcharge, string $total, string $aggregation='single_distinct_party_markup'): array
{
    $fact=cache_autosave_fact($base,$surcharge,$total);
    $fact['transport_markup_reported']=[
        'amount'=>$surcharge,
        'currency'=>'RUB',
        'source'=>'andromeda_get_flights_transport',
        'aggregation'=>$aggregation,
    ];
    return $fact;
}

$root = sys_get_temp_dir() . '/andromeda-cache-autosave-' . bin2hex(random_bytes(6));
$directory = $root . '/searches';
if (!mkdir($directory, 0700, true)) throw new RuntimeException('mkdir');
try {
    $now = 1800000000;
    $request = ['departureId' => 1, 'countryId' => 4];
    $sourceOffer = cache_autosave_offer('source', '185125');
    $sourceRef = $sourceOffer['offer_ref'];
    $exactEstimated = ['state' => 'estimated', 'fact' => cache_autosave_fact(), 'verified_quote' => null];
    $meta = [
        'source_sha' => str_repeat('a', 40),
        'source_search_ref' => str_repeat('b', 64),
        'source_offer_ref' => $sourceRef,
        'observed_at' => $now - 5,
        'expires_at' => $now + 120,
    ];
    $writes = 0;
    $writer = static function(string $path, array $value) use (&$writes): bool {
        ++$writes;
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return file_put_contents($path, $encoded, LOCK_EX) === strlen($encoded);
    };

    // Existing unclassified estimated pricing stays strict and seeds exactly one
    // legacy cache specimen. Cross-night behavior must not be enabled by omission.
    $resolved = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        $exactEstimated, $sourceOffer, $request, $directory, $now, $meta, $writer
    );
    cache_autosave_assert($resolved === $exactEstimated, 'exact estimated pricing changed');
    cache_autosave_assert($writes === 1, 'unclassified estimate must seed only strict cache');

    // Same strict transport group but different hotel/room/meal/SPO/base PRICE reuses
    // only the party surcharge and rebases it to this offer's own search price.
    $sibling = cache_autosave_offer('sibling', '190000');
    $fallback = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        null, $sibling, $request, $directory, $now
    );
    cache_autosave_assert(($fallback['state'] ?? null) === 'estimated', 'cache fallback state');
    cache_autosave_assert(array_key_exists('verified_quote',$fallback) && $fallback['verified_quote'] === null, 'cache fallback gained verified quote');
    cache_autosave_assert(($fallback['fact']['search_price'] ?? null) === ['amount' => '190000', 'currency' => 'RUB'], 'cache fallback did not rebase base price');
    cache_autosave_assert(($fallback['fact']['party_surcharge']['amount'] ?? null) === '14265', 'cache fallback surcharge changed');
    cache_autosave_assert(($fallback['fact']['search_price_with_surcharge']['amount'] ?? null) === '204265', 'cache fallback total');
    cache_autosave_assert(($fallback['fact']['final_price_verified'] ?? null) === false, 'cache fallback became final');

    // Unclassified/choice-unknown evidence still does NOT cross nights.
    $strictOtherNights=cache_autosave_offer('strict-other-nights','190000','program_1','2026-10-30',10);
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$strictOtherNights,$request,$directory,$now)===null,
        'unclassified estimate crossed nights'
    );

    // Owner-approved fixed-program class: one classified single-distinct party
    // surcharge seeds both the unchanged strict entry and a NEW versioned program
    // entry. The latter deliberately ignores only nights.
    $programSource=cache_autosave_offer('program-source','200000','program_fixed','2026-11-01',7,'tour_fixed');
    $programFact=cache_autosave_program_fact('200000','5000','205000');
    $programExact=['state'=>'estimated','fact'=>$programFact,'verified_quote'=>null];
    $programMeta=$meta;
    $programMeta['source_offer_ref']=$programSource['offer_ref'];
    $beforeProgramWrites=$writes;
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
            $programExact,$programSource,$request,$directory,$now,$programMeta,$writer
        )===$programExact,
        'program exact pricing changed'
    );
    cache_autosave_assert($writes-$beforeProgramWrites===2,'program-fixed fact must seed strict plus program cache');

    foreach([10=>'215000',14=>'225000'] as $nights=>$base){
        $programSibling=cache_autosave_offer('program-'.$nights,$base,'program_fixed','2026-11-01',$nights,'tour_fixed');
        $programFallback=AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
            null,$programSibling,$request,$directory,$now
        );
        cache_autosave_assert(($programFallback['state']??null)==='estimated','program cross-night fallback state');
        cache_autosave_assert(($programFallback['fact']['reuse_scope']??null)==='program_fixed','program fallback scope');
        cache_autosave_assert(($programFallback['fact']['search_price']??null)===['amount'=>$base,'currency'=>'RUB'],'program target base retained');
        cache_autosave_assert(($programFallback['fact']['party_surcharge']['amount']??null)==='5000','program surcharge reused once');
        cache_autosave_assert(($programFallback['fact']['search_price_with_surcharge']['amount']??null)===(string)((int)$base+5000),'program total rebased');
        cache_autosave_assert(($programFallback['fact']['final_price_verified']??null)===false,'program fallback became final');
    }

    // Only nights are relaxed. Existing operator/program/tour/date/route/party and
    // currency discriminators remain effective in program-fixed fallback.
    $programMismatchCases=[];
    $x=cache_autosave_offer('m-program','215000','other_program','2026-11-01',10,'tour_fixed');$programMismatchCases[]=$x;
    $x=cache_autosave_offer('m-tour','215000','program_fixed','2026-11-01',10,'other_tour');$programMismatchCases[]=$x;
    $x=cache_autosave_offer('m-date','215000','program_fixed','2026-11-02',10,'tour_fixed');$programMismatchCases[]=$x;
    $x=cache_autosave_offer('m-party','215000','program_fixed','2026-11-01',10,'tour_fixed');$x['adults']=3;$programMismatchCases[]=$x;
    $x=cache_autosave_offer('m-currency','215000','program_fixed','2026-11-01',10,'tour_fixed');$x['price']['currency']='USD';$programMismatchCases[]=$x;
    $x=cache_autosave_offer('m-operator','215000','program_fixed','2026-11-01',10,'tour_fixed');$x['operator_ref']='operator_2';$programMismatchCases[]=$x;
    foreach($programMismatchCases as $index=>$mismatch){
        cache_autosave_assert(
            AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$mismatch,$request,$directory,$now)===null,
            'program fixed mismatch reused cache '.$index
        );
    }
    $programSibling=cache_autosave_offer('m-route','215000','program_fixed','2026-11-01',10,'tour_fixed');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$programSibling,['departureId'=>2,'countryId'=>4],$directory,$now)===null,
        'program fixed route mismatch reused cache'
    );

    // Choice-dependent minimum is explicitly NOT the fixed-program class. It may
    // seed the unchanged strict cache for its own exact nights, but never cross nights.
    $choiceSource=cache_autosave_offer('choice-source','230000','program_choice','2026-11-03',7,'tour_choice');
    $choiceFact=cache_autosave_program_fact('230000','3000','233000','minimum_complete_required_roundtrip_markup');
    $choiceExact=['state'=>'estimated','fact'=>$choiceFact,'verified_quote'=>null];
    $choiceMeta=$meta;$choiceMeta['source_offer_ref']=$choiceSource['offer_ref'];
    $beforeChoiceWrites=$writes;
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
            $choiceExact,$choiceSource,$request,$directory,$now,$choiceMeta,$writer
        )===$choiceExact,
        'choice-dependent exact pricing changed'
    );
    cache_autosave_assert($writes-$beforeChoiceWrites===1,'choice-dependent fact seeded broader program cache');
    $choiceOtherNights=cache_autosave_offer('choice-10','235000','program_choice','2026-11-03',10,'tour_choice');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$choiceOtherNights,$request,$directory,$now)===null,
        'choice-dependent minimum crossed nights'
    );

    // Verified exact quote always outranks cache and never seeds/rewrites it.
    $verified = ['state' => 'verified', 'fact' => null, 'verified_quote' => [
        'final_price' => ['amount' => '201000', 'currency' => 'RUB'],
        'final_price_verified' => true,
    ]];
    $beforeVerifiedWrites = $writes;
    $verifiedResult = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        $verified, $sibling, $request, $directory, $now, $meta, $writer
    );
    cache_autosave_assert($verifiedResult === $verified, 'verified exact pricing changed');
    cache_autosave_assert($writes === $beforeVerifiedWrites, 'verified exact pricing touched cache');

    // Any exact envelope, even one later rejected downstream, blocks cache fallback.
    $invalidExact = ['state' => 'unknown'];
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($invalidExact, $sibling, $request, $directory, $now) === $invalidExact,
        'cache overrode invalid exact pricing'
    );

    // Strict group mismatch must fail closed.
    $differentProgram = cache_autosave_offer('other-program', '190000', 'program_2');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $differentProgram, $request, $directory, $now) === null,
        'program mismatch reused cache'
    );
    $differentDate = cache_autosave_offer('other-date', '190000', 'program_1', '2026-10-31');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $differentDate, $request, $directory, $now) === null,
        'date mismatch reused cache'
    );
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $sibling, ['departureId' => 2, 'countryId' => 4], $directory, $now) === null,
        'departure mismatch reused cache'
    );

    // Expiry is inherited from the original evidence; resolve never extends it.
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $sibling, $request, $directory, $now + 120) === null,
        'expired cache reused'
    );
    $programExpired=cache_autosave_offer('program-expired','215000','program_fixed','2026-11-01',10,'tour_fixed');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$programExpired,$request,$directory,$now+120)===null,
        'expired program cache reused'
    );

    // Invalid seed metadata and cache write failure are best-effort: exact pricing survives.
    $badMeta = $meta; $badMeta['source_sha'] = 'not-a-sha';
    $beforeBadMetaWrites = $writes;
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exactEstimated, $sourceOffer, $request, $directory, $now, $badMeta, $writer) === $exactEstimated,
        'invalid metadata suppressed exact pricing'
    );
    cache_autosave_assert($writes === $beforeBadMetaWrites, 'invalid metadata wrote cache');

    $newerMeta = $meta;
    $newerMeta['observed_at'] = $now;
    $newerMeta['expires_at'] = $now + 60;
    $failingWriter = static function(string $path, array $value): bool { throw new RuntimeException('expected cache write failure'); };
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exactEstimated, $sourceOffer, $request, $directory, $now, $newerMeta, $failingWriter) === $exactEstimated,
        'cache write failure suppressed exact pricing'
    );

    echo "ANDROMEDA_SURCHARGE_CACHE_AUTOSAVE_OK exact=1 reuse=1 rebase=1 program_fixed_cross_night=2 program_mismatch=7 choice_minimum_strict=1 verified_priority=1 strict=3 expiry=2 fail_closed=2\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

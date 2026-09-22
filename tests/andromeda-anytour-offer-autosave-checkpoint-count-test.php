<?php
declare(strict_types=1);

// Reuse the real autosave/producer fixtures and execute their regression suite first.
// All files and ingests below are disposable local fixtures; supplier/live DB calls are zero.
require __DIR__ . '/andromeda-anytour-offer-autosave-test.php';

function checkpoint_count_case(string $label, ?array $pricing, int $expectedReady, int $expectedConfirmation): void
{
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'checkpoint-count-' . $label);
        $created = time() - 30;
        $ingests = [];
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('checkpoint-' . $label)]));
        [$mapping, $canonical, $reader, $save, $ingest] = callbacks($ingests, $pricing);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $first = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($first['published'] === true
            && $first['readyOfferCount'] === $expectedReady
            && $first['confirmationRequiredOfferCount'] === $expectedConfirmation,
            $label . ' fresh producer counts');
        aassert(count($ingests) === 1, $label . ' fresh intake once');

        $checkpointPath = $dir . '/' . $ref . '-' . $created . '-anytour-offer-autosave-v1.json';
        $checkpoint = json_decode((string)file_get_contents($checkpointPath), true, 64, JSON_THROW_ON_ERROR);
        aassert(($checkpoint['ready_offer_count'] ?? null) === $expectedReady,
            $label . ' checkpoint ready count');
        aassert(array_key_exists('confirmation_required_offer_count', $checkpoint)
            && $checkpoint['confirmation_required_offer_count'] === $expectedConfirmation,
            $label . ' checkpoint confirmation count');

        $again = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($again['published'] === false && $again['reason'] === 'already_published'
            && $again['readyOfferCount'] === $expectedReady
            && ($again['confirmationRequiredOfferCount'] ?? null) === $expectedConfirmation,
            $label . ' idempotent counts');
        aassert(count($ingests) === 1, $label . ' idempotent no second intake');

        // Backward compatibility: a valid historical v1 checkpoint has no confirmation
        // field. Keep it readable, but do not infer a count from entries or ready count.
        unset($checkpoint['confirmation_required_offer_count']);
        file_put_contents($checkpointPath, json_encode($checkpoint, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        chmod($checkpointPath, 0600);
        $legacy = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($legacy['reason'] === 'already_published' && $legacy['readyOfferCount'] === $expectedReady
            && !array_key_exists('confirmationRequiredOfferCount', $legacy),
            $label . ' legacy checkpoint remains readable without inferred confirmation');
        aassert(count($ingests) === 1, $label . ' legacy reread no intake');
    } finally {
        cleanup_dir($dir);
    }
}

function verified_evidence_pricing(array $base, bool $required, bool $packet): array
{
    $pricing = $base;
    $quote = $pricing['verified_quote'] ?? null;
    aassert(is_array($quote), 'verified evidence fixture base quote');
    $quote['fuel_surcharges_reported'] = [[
        'amount' => '4200',
        'currency' => 'RUB',
        'route_index' => '0',
        'source' => 'andromeda_claim_service',
        'service_type' => '5',
        'required_reported' => $required,
        'packet_reported' => $packet,
    ]];
    $quote['operator_currency_rates_reported'] = [];
    $quote['calc_money_facts_reported'] = [];
    $pricing['verified_quote'] = $quote;
    return $pricing;
}

function verified_evidence_checkpoint_case(array $basePricing): void
{
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'checkpoint-verified-evidence-change');
        $created = time() - 30;
        $ingests = [];
        write_state($dir, $ref, $created, 1, state(
            $ref, 1, 1, 1, $created, [normalized_offer('checkpoint-verified-evidence')]
        ));
        [$mapping, $canonical, , $save, $ingest] = callbacks($ingests, null);
        $pricing = verified_evidence_pricing($basePricing, false, true);
        $reader = static function(array $state, int $createdAt, array $offer, array $current) use (&$pricing): array {
            return $pricing;
        };
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $first = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($first['published'] === true && $first['readyOfferCount'] === 1 && count($ingests) === 1,
            'initial verified evidence published');
        $firstDto = $ingests[0]['rows'][0]['dto'] ?? null;
        aassert(is_array($firstDto)
            && $firstDto['finalPrice'] === '199390'
            && ($firstDto['money']['fuel_surcharges_reported'][0]['required_reported'] ?? null) === false,
            'initial verified evidence dto');
        $firstEvidence = $firstDto['quote_evidence_digest'] ?? null;
        aassert(is_string($firstEvidence) && preg_match('/^[a-f0-9]{64}$/D', $firstEvidence) === 1,
            'initial evidence digest');

        // The supplier can refine literal evidence while keeping the verified total
        // unchanged. This is not price arithmetic: LOCAL must receive the newer facts.
        $pricing = verified_evidence_pricing($basePricing, true, true);
        $second = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($second['published'] === true && $second['readyOfferCount'] === 1 && count($ingests) === 2,
            'same-price changed verified evidence was suppressed by checkpoint');
        $secondDto = $ingests[1]['rows'][0]['dto'] ?? null;
        aassert(is_array($secondDto)
            && $secondDto['finalPrice'] === '199390'
            && ($secondDto['money']['fuel_surcharges_reported'][0]['required_reported'] ?? null) === true,
            'changed verified evidence did not reach dto');
        aassert(is_string($secondDto['quote_evidence_digest'] ?? null)
            && $secondDto['quote_evidence_digest'] !== $firstEvidence,
            'changed supplier evidence did not change quote evidence digest');

        $third = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($third['published'] === false && $third['reason'] === 'already_published'
            && $third['readyOfferCount'] === 1 && count($ingests) === 2,
            'identical changed evidence republished');
        aassert($secondDto['selection_state'] === 'disabled' && $secondDto['booking_enabled'] === false,
            'evidence refresh gained selection or booking authority');
        echo "ANDROMEDA_AUTOSAVE_VERIFIED_EVIDENCE_REFRESH_OK initial=1 changed=1 idempotent=1 price_unchanged=1 supplier=0 live_db=0\n";
    } finally {
        cleanup_dir($dir);
    }
}

function estimated_evidence_checkpoint_case(): void
{
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'checkpoint-estimated-evidence-change');
        $created = time() - 30;
        $ingests = [];
        write_state($dir, $ref, $created, 1, state(
            $ref, 1, 1, 1, $created, [normalized_offer('checkpoint-estimated-evidence')]
        ));
        [$mapping, $canonical, , $save, $ingest] = callbacks($ingests, null);
        $pricing = party_surcharge('185125', '14265', '199390');
        $reader = static function(array $state, int $createdAt, array $offer, array $current) use (&$pricing): array {
            return $pricing;
        };
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $first = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($first['published'] === true && $first['readyOfferCount'] === 0
            && $first['confirmationRequiredOfferCount'] === 1 && count($ingests) === 1,
            'initial estimated evidence published');
        $firstDto = $ingests[0]['rows'][0]['dto'] ?? null;
        aassert(is_array($firstDto), 'initial estimated dto missing');
        assert_confirmation_dto($firstDto, '185125');
        aassert(($firstDto['money']['additional_prices_reported'][0]['amount'] ?? null) === '14265'
            && ($firstDto['money']['search_price_fuel_relation'] ?? null) === 'unknown',
            'initial estimated money provenance missing');

        $same = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($same['published'] === false && $same['reason'] === 'already_published'
            && count($ingests) === 1, 'identical estimated evidence republished');

        // A newer retained program-level surcharge must refresh persisted money facts,
        // while customer display price remains the supplier base and fuel stays unknown.
        $pricing = party_surcharge('185125', '15265', '200390');
        $changed = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($changed['published'] === true && $changed['readyOfferCount'] === 0
            && $changed['confirmationRequiredOfferCount'] === 1 && count($ingests) === 2,
            'changed estimated evidence was suppressed by checkpoint');
        $changedDto = $ingests[1]['rows'][0]['dto'] ?? null;
        aassert(is_array($changedDto), 'changed estimated dto missing');
        assert_confirmation_dto($changedDto, '185125');
        aassert(($changedDto['money']['additional_prices_reported'][0]['amount'] ?? null) === '15265'
            && ($changedDto['money']['search_price_fuel_relation'] ?? null) === 'unknown'
            && $changedDto['finalPriceReady'] === false && $changedDto['final_price_verified'] === false,
            'changed surcharge gained final/fuel authority or failed to reach dto');

        $after = AnyTourAndromedaOfferAutosaveV1::consume(
            search_request(), $dir, $ref, 1, $now, $mapping, $canonical, $reader, $save, $ingest
        );
        aassert($after['published'] === false && $after['reason'] === 'already_published'
            && count($ingests) === 2, 'identical changed estimated evidence republished');
        echo "ANDROMEDA_AUTOSAVE_ESTIMATED_EVIDENCE_REFRESH_OK initial=1 same=0 changed=1 repeated=0 base=185125 surcharge=15265 fuel=unknown supplier=0 live_db=0\n";
    } finally {
        cleanup_dir($dir);
    }
}

checkpoint_count_case('confirmation', party_surcharge(), 0, 1);
checkpoint_count_case('verified', $verifiedPricing, 1, 0);
verified_evidence_checkpoint_case($verifiedPricing);
estimated_evidence_checkpoint_case();

echo "ANDROMEDA_AUTOSAVE_CHECKPOINT_COUNTS_OK new=2 idempotent=2 legacy=2 supplier=0 live_db=0\n";

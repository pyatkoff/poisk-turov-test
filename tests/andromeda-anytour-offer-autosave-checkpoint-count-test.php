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

checkpoint_count_case('confirmation', party_surcharge(), 0, 1);
checkpoint_count_case('verified', $verifiedPricing, 1, 0);

echo "ANDROMEDA_AUTOSAVE_CHECKPOINT_COUNTS_OK new=2 idempotent=2 legacy=2 supplier=0 live_db=0\n";

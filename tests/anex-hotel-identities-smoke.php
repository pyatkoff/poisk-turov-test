<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../app/integrations/hotel-identities.php';
$includeOutput = ob_get_clean();
$checks = 0;
function identity_check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $name);
    }
    $checks++;
}
function identity_invalid($document, string $name): void
{
    try {
        AnyTourHotelIdentityRegistry::fromJson(is_string($document) ? $document : json_encode($document, JSON_THROW_ON_ERROR));
    } catch (UnexpectedValueException $e) {
        identity_check($e->getMessage() === 'hotel_identity_registry_invalid', $name . ' generic error');
        return;
    }
    throw new RuntimeException('FAILED: ' . $name . ' accepted');
}

identity_check($includeOutput === '', 'include has no output');
$path = __DIR__ . '/../app/integrations/data/anex-hotel-identities.json';
$data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
$registry = AnyTourHotelIdentityRegistry::fromFile($path);
$verified = 0;
$review = 0;
foreach ($data['entries'] as $entry) {
    foreach ($entry['identities'] as $identity) {
        $provider = $identity['provider'];
        $id = $identity['external_id'];
        identity_check($registry->resolve($provider, $id) === null, 'production default never maps pilot');
        identity_check($registry->resolve($provider, $id, 'production') === null, 'explicit production never maps pilot');
        identity_check($registry->resolve($provider, $id, 'PREVIEW') === null, 'scope must be explicit exact preview');
        if ($entry['status'] === 'verified_preview') {
            identity_check($registry->resolve($provider, $id, 'preview') === $entry['candidate_hotel_id'], 'verified source mapping');
            identity_check($registry->resolve($provider, (int) $id, 'preview') === $entry['candidate_hotel_id'], 'integer external ID');
        } else {
            identity_check($registry->resolve($provider, $id, 'preview') === null, 'review candidate cannot resolve');
        }
        identity_check($registry->status($provider, $id) === $entry['status'], 'status remains evidence status');
    }
    $entry['status'] === 'verified_preview' ? $verified++ : $review++;
}
identity_check($verified === 5 && $review === 5, 'pilot retains five verified hotels and five review candidates');
identity_check($registry->resolve('anex_online', '40430', 'preview') === null, 'no fallback to equal local ID');
identity_check($registry->resolve('anex', '30160', 'preview') === null, 'no implicit provider alias');
identity_check($registry->resolve('andromeda', '30160', 'preview') === null, 'no cross-supplier identity assumption');
identity_check($registry->resolve('tourvisor_api', '30160', 'preview') === null, 'unknown source stays unmapped');
identity_check($registry->status('anex_online', '999999999') === 'unmapped', 'unknown ID status');
foreach (['030160', '+30160', '30160 ', '3.016e4', '', '-1', 30160.0, true, [], null] as $id) {
    identity_check($registry->resolve('anex_online', $id, 'preview') === null, 'malformed request ID stays unmapped');
}

// Source namespaces can contain equal numbers without identity collision.
$first = $data['entries'][1];
$first['identities'] = [['provider' => 'anex_online', 'external_id' => '777']];
$second = $data['entries'][2];
$second['identities'] = [['provider' => 'tourvisor_api', 'external_id' => '777']];
$fixture = $data;
$fixture['entries'] = [$first, $second];
$distinct = AnyTourHotelIdentityRegistry::fromJson(json_encode($fixture, JSON_THROW_ON_ERROR));
identity_check($distinct->resolve('anex_online', '777', 'preview') === 40430, 'source one equal number');
identity_check($distinct->resolve('tourvisor_api', '777', 'preview') === 17469, 'source two equal number');
identity_check($distinct->resolve('anex_xml', '777', 'preview') === null, 'online does not imply XML');

$second = $first;
$second['identities'][0]['external_id'] = '778';
$fixture['entries'] = [$first, $second];
$many = AnyTourHotelIdentityRegistry::fromJson(json_encode($fixture, JSON_THROW_ON_ERROR));
identity_check($many->resolve('anex_online', '777', 'preview') === $many->resolve('anex_online', '778', 'preview'), 'explicit many-to-one allowed');

$fixture['entries'] = [$first, $first];
identity_invalid($fixture, 'identical duplicate source identity');
$fixture['entries'][1]['candidate_hotel_id'] = 17469;
identity_invalid($fixture, 'conflicting duplicate source identity');
$fixture['entries'][1]['status'] = 'needs_review';
identity_invalid($fixture, 'review conflict cannot shadow verified identity');
$fixture = $data;
$fixture['entries'][0]['identities'][] = $fixture['entries'][0]['identities'][0];
identity_invalid($fixture, 'duplicate within one entry');

foreach ([0, -1, '40430', 40430.5, true, null] as $badId) {
    $fixture = $data;
    $fixture['entries'][1]['candidate_hotel_id'] = $badId;
    identity_invalid($fixture, 'malformed local ID');
}
foreach (['confirmed', 'approved', 'VERIFIED_PREVIEW', '', null] as $badStatus) {
    $fixture = $data;
    $fixture['entries'][1]['status'] = $badStatus;
    identity_invalid($fixture, 'unrecognized status');
}
$fixture = $data;
$fixture['entries'][1]['identities'][0]['external_id'] = '030160';
identity_invalid($fixture, 'noncanonical external ID declaration');
$fixture = $data;
$fixture['entries'][1]['identities'][0]['provider'] = 'anex:online';
identity_invalid($fixture, 'malformed provider declaration');
$fixture = $data;
$fixture['scope'] = 'production';
identity_invalid($fixture, 'manifest cannot enable production');
$fixture = $data;
$fixture['entries'][0]['hotel_id'] = $fixture['entries'][0]['candidate_hotel_id'];
identity_invalid($fixture, 'unrecognized shortcut field');
$fixture = $data;
$fixture['evidence']['checked_at'] = '2026-02-31T16:23:20Z';
identity_invalid($fixture, 'invalid evidence date');
$fixture = $data;
unset($fixture['entries'][9]['reason']);
identity_invalid($fixture, 'one bad entry rejects the whole registry');
identity_invalid('{', 'invalid JSON');
identity_invalid('null', 'null JSON');
identity_invalid(str_repeat(' ', 524289), 'bounded registry bytes');
foreach (['https://example.invalid/map.json', 'data://text/plain,{}', "bad\0path", __DIR__ . '/missing-identity-registry.json'] as $badPath) {
    try {
        AnyTourHotelIdentityRegistry::fromFile($badPath);
        throw new RuntimeException('FAILED: file path accepted');
    } catch (UnexpectedValueException $e) {
        identity_check($e->getMessage() === 'hotel_identity_registry_invalid', 'invalid path stays generic');
    }
}
echo 'ANEX hotel identities: ' . $checks . " checks passed\n";

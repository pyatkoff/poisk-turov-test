<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/andromeda-client.php';
require __DIR__ . '/../app/integrations/andromeda-operator-config.php';
require __DIR__ . '/../app/integrations/andromeda-claim-actions.php';

// Execute the actual HTTP quote supplier factory with the real protocol client.
// Only the network transport and budget persistence are replaced by local fakes.
final class AnyTourAndromedaTransport
{
    public static array $requests = [];
    public function __construct(bool $capture = false, bool $enabled = false) {}
    public function __invoke(string $url, array $options): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::$requests[] = $query;
        $payload = match ($query['action'] ?? null) {
            'login' => ['sid' => 'quote_operator_test_session'],
            'broninit' => ['claimDocument' => [['catalogKey' => 'synthetic/catalog/key']]],
            default => throw new RuntimeException('UNEXPECTED_PROTOCOL_ACTION'),
        };
        return ['status' => 200, 'body' => json_encode($payload, JSON_THROW_ON_ERROR)];
    }
}
$budgetCalls = 0;
function anytour_andromeda_search3_budget(string $directory): void
{
    global $budgetCalls;
    ++$budgetCalls;
}
$source = file_get_contents(__DIR__ . '/../v2/api-andromeda-quote-preview.php');
if (!is_string($source)) throw new RuntimeException('SOURCE_UNREADABLE');
$extract = static function (string $name) use ($source): string {
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) throw new RuntimeException('FUNCTION_MISSING');
    $open = strpos($source, '{', $start);
    $depth = 0;
    for ($i = $open; $i < strlen($source); ++$i) {
        if ($source[$i] === '{') ++$depth;
        elseif ($source[$i] === '}' && --$depth === 0) return substr($source, $start, $i - $start + 1);
    }
    throw new RuntimeException('FUNCTION_UNCLOSED');
};
eval($extract('anytour_andromeda_quote_supplier'));
eval($extract('anytour_andromeda_quote_failure_category'));

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
};
$config = ['catalog_path' => '/synthetic/catalog.json', 'username' => 'gateway-user', 'password' => 'gateway-password'];
$login = 'operator+quote@example.test';
$password = 'synthetic p@ss &+%/=?';

foreach ([['operator_login' => $login, 'operator_password' => $password], []] as $pair) {
    AnyTourAndromedaTransport::$requests = [];
    $budgetCalls = 0;
    [$client, $actions] = anytour_andromeda_quote_supplier($config + $pair);
    $check($actions instanceof AnyTourAndromedaClaimActions, 'CLAIM_ACTIONS_MISSING');
    $client->package('synthetic-opaque-selected-offer');
    $requests = AnyTourAndromedaTransport::$requests;
    $check(count($requests) === 2 && $budgetCalls === 2, 'REQUEST_BUDGET_CHANGED');
    $check(!array_key_exists('OPERATOR_LOGIN', $requests[0]) && !array_key_exists('OPERATOR_PASSWORD', $requests[0]), 'PAIR_LEAKED_TO_GATEWAY_LOGIN');
    if ($pair) {
        $check(($requests[1]['OPERATOR_LOGIN'] ?? null) === $login, 'QUOTE_OPERATOR_LOGIN_NOT_FORWARDED');
        $check(($requests[1]['OPERATOR_PASSWORD'] ?? null) === $password, 'QUOTE_OPERATOR_PASSWORD_NOT_FORWARDED');
    } else {
        $check(!array_key_exists('OPERATOR_LOGIN', $requests[1]) && !array_key_exists('OPERATOR_PASSWORD', $requests[1]), 'ABSENT_PAIR_BEHAVIOR_CHANGED');
    }
    try {
        $client->package('synthetic-opaque-selected-offer');
        throw new LogicException('PACKAGE_REPLAY_ACCEPTED');
    } catch (RuntimeException $e) {
        $check($e->getMessage() === 'ANDROMEDA_PACKAGE_REPLAY_REFUSED', 'PACKAGE_REPLAY_GUARD_CHANGED');
        $check(count(AnyTourAndromedaTransport::$requests) === 2, 'PACKAGE_REPLAY_SENT_REQUEST');
    }
}

foreach ([
    ['operator_login' => $login], ['operator_password' => $password],
    ['operator_login' => null, 'operator_password' => null],
    ['operator_login' => '', 'operator_password' => $password],
    ['operator_login' => $login, 'operator_password' => ''],
    ['operator_login' => str_repeat('l', 257), 'operator_password' => $password],
    ['operator_login' => $login, 'operator_password' => str_repeat('p', 4097)],
] as $pair) {
    AnyTourAndromedaTransport::$requests = [];
    $budgetCalls = 0;
    try {
        anytour_andromeda_quote_supplier($config + $pair);
        throw new LogicException('INVALID_PAIR_ACCEPTED');
    } catch (RuntimeException $e) {
        $check(in_array($e->getMessage(), ['ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED', 'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID'], true), 'INVALID_PAIR_WRONG_ERROR');
        $check(anytour_andromeda_quote_failure_category($e) === 'supplier_auth', 'INVALID_PAIR_WRONG_CATEGORY');
        $check(AnyTourAndromedaTransport::$requests === [] && $budgetCalls === 0, 'INVALID_PAIR_SPENT_REQUEST');
        $check(!str_contains($e->getMessage(), $login) && !str_contains($e->getMessage(), $password), 'INVALID_PAIR_LEAKED');
    }
}
$check(str_contains($source, "require_once \$quoteApp . '/andromeda-operator-config.php';"), 'QUOTE_CONFIG_DEPENDENCY_MISSING');
// The public deadline is the retained search deadline, never a new lease on replay.
eval($extract('anytour_andromeda_quote_with_expiry'));
$resolved = ['expires_at' => 1000];
$quote = ['state' => 'quote_verified', 'final_price' => ['amount' => '125500', 'currency' => 'RUB']];
$public = anytour_andromeda_quote_with_expiry($quote, $resolved, 900);
$check($public['expires_at'] === 1000 && $public['final_price'] === $quote['final_price'], 'QUOTE_EXPIRY_OR_MONEY_CHANGED');
$check(anytour_andromeda_quote_with_expiry($public, $resolved, 999) === $public, 'REPLAY_EXTENDED_EXPIRY');
$check(anytour_andromeda_quote_with_expiry(['state' => 'flight_selection_required'], $resolved, 900)['expires_at'] === 1000, 'FLIGHT_CHOICE_EXPIRY_MISSING');
foreach ([[], ['expires_at' => '1000'], ['expires_at' => 900], ['expires_at' => 899]] as $expired) {
    try {
        anytour_andromeda_quote_with_expiry($quote, $expired, 900);
        throw new LogicException('EXPIRED_QUOTE_ACCEPTED');
    } catch (DomainException $e) {
        $check($e->getMessage() === 'offer_expired', 'EXPIRY_WRONG_ERROR');
    }
}
try {
    anytour_andromeda_quote_with_expiry($public, $resolved, 1000);
    throw new LogicException('EXPIRY_BOUNDARY_ACCEPTED');
} catch (DomainException $e) { $check($e->getMessage() === 'offer_expired', 'EXPIRY_BOUNDARY_WRONG_ERROR'); }
echo "Andromeda quote operator config: $checks checks passed; external requests=0.\n";

<?php
declare(strict_types=1);

final class Search3GatewayOutput extends RuntimeException
{
    public array $payload;
    public int $httpStatus;

    public function __construct(array $payload, int $httpStatus)
    {
        parent::__construct((string)($payload['error'] ?? 'gateway output'));
        $this->payload = $payload;
        $this->httpStatus = $httpStatus;
    }
}

function out($data, int $status = 200): void
{
    throw new Search3GatewayOutput((array)$data, $status);
}

function source_function(string $source, string $wanted): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $name = null;
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && $token[0] === T_STRING) {
                $name = $token[1];
                break;
            }
            if ($token === '(') break;
        }
        if ($name !== $wanted) continue;

        $body = '';
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $count; $j++) {
            $token = $tokens[$j];
            $text = is_array($token) ? $token[1] : $token;
            $body .= $text;
            if ($text === '{') {
                $started = true;
                $depth++;
            } elseif ($text === '}' && $started) {
                $depth--;
                if ($depth === 0) return $body;
            }
        }
    }
    throw new RuntimeException('Missing source function: ' . $wanted);
}

$gateway = file_get_contents(__DIR__ . '/../v2/api-v2.php');
if (!is_string($gateway)) throw new RuntimeException('Cannot read gateway source');

eval(source_function($gateway, 'strict_int'));
eval(source_function($gateway, 'search_results_limit'));

foreach ([25, 100, 101, 500, 1500, 5000] as $limit) {
    if (search_results_limit((string)$limit) !== $limit) {
        throw new RuntimeException('Valid limit was rewritten: ' . $limit);
    }
}

foreach ([0, -1, 5001, 'invalid', '100.5'] as $limit) {
    try {
        search_results_limit($limit);
        throw new RuntimeException('Invalid limit was accepted: ' . var_export($limit, true));
    } catch (Search3GatewayOutput $error) {
        if ($error->httpStatus !== 400 || ($error->payload['error'] ?? '') !== 'limit must be between 1 and 5000') {
            throw new RuntimeException('Invalid limit did not fail explicitly');
        }
    }
}

if (!str_contains($gateway, "search_results_limit(\$_GET['limit'] ?? 25)")) {
    throw new RuntimeException('search_results action does not use the validated limit owner');
}
if (str_contains($gateway, "bounded_int(\$_GET['limit'] ?? 25, 1, 100, 25)")) {
    throw new RuntimeException('Legacy silent 100 clamp is still active');
}

echo "SEARCH3_TOURVISOR_RESULTS_LIMIT_OK preserved=25,100,101,500,1500,5000 invalid=explicit-400\n";

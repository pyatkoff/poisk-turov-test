<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/andromeda-client.php';

/**
 * Diagnostic-only schema projection. It intentionally keeps field names, container
 * shapes and bounded counts, but never supplier values.
 */
function anytour_andromeda_schema_value($value, int $depth = 0): array
{
    if ($depth > 3) return ['type' => 'depth_limit'];
    if (!is_array($value)) return ['type' => get_debug_type($value)];

    $isList = array_is_list($value);
    $result = ['type' => $isList ? 'list' : 'object', 'count' => count($value)];
    if (!$value) return $result;

    if ($isList) {
        $fieldTypes = [];
        foreach (array_slice($value, 0, 50) as $row) {
            if (!is_array($row)) {
                $fieldTypes['$item'][get_debug_type($row)] = true;
                continue;
            }
            foreach ($row as $key => $item) {
                $key = (string) $key;
                if (!preg_match('/^[A-Za-z0-9_]{1,80}$/D', $key)) $key = '$other';
                $fieldTypes[$key][is_array($item) ? (array_is_list($item) ? 'list' : 'object') : get_debug_type($item)] = true;
            }
        }
        ksort($fieldTypes);
        $result['fields'] = [];
        foreach ($fieldTypes as $field => $types) {
            $names = array_keys($types); sort($names);
            $result['fields'][$field] = $names;
        }
        return $result;
    }

    $fields = [];
    foreach ($value as $key => $item) {
        $key = (string) $key;
        if (!preg_match('/^[A-Za-z0-9_]{1,80}$/D', $key)) $key = '$other';
        $fields[$key] = anytour_andromeda_schema_value($item, $depth + 1);
    }
    ksort($fields);
    $result['fields'] = $fields;
    return $result;
}

function anytour_andromeda_raw_catalog_schema(array $reply): array
{
    $schema = [];
    foreach ($reply as $key => $value) {
        $safeKey = preg_match('/^[A-Za-z0-9_]{1,80}$/D', (string) $key) ? (string) $key : '$other';
        $schema[$safeKey] = anytour_andromeda_schema_value($value);
    }
    ksort($schema);
    return ['schema_version' => 1, 'top_level' => $schema];
}

/**
 * One existing documented `all` call only. No guessed supplier action is added.
 * Reflection is deliberately confined to this diagnostic so production client API
 * remains unchanged until the actual supplier transport contract is known.
 */
function anytour_andromeda_discover_all_schema(AnyTourAndromedaClient $client, int $departureId, int $countryId): array
{
    if ($departureId < 1 || $countryId < 1) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
    $session = $client->privateSession();
    if (!isset($session['sid']) || !is_string($session['sid'])) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');

    $send = new ReflectionMethod(AnyTourAndromedaClient::class, 'send');
    $reply = $send->invoke($client, 'all', ['sid' => $session['sid'], 'TOWNFROMINC' => $departureId, 'STATEINC' => $countryId]);
    if (!is_array($reply)) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');

    $raw = json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (str_contains($raw, $session['sid'])) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
    return anytour_andromeda_raw_catalog_schema($reply);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    fwrite(STDERR, "This source-only helper is not an armed supplier runner.\n");
    exit(2);
}

<?php
declare(strict_types=1);

/** Explicit supplier identities for an isolated preview; no work runs on include. */
final class AnyTourHotelIdentityRegistry
{
    private const MAX_BYTES = 524288;
    private $index;

    private function __construct(array $index)
    {
        $this->index = $index;
    }

    public static function fromFile(string $path = __DIR__ . '/data/anex-hotel-identities.json'): self
    {
        // Explicit local reads only: PHP stream wrappers must never fetch a registry.
        if (strpos($path, '://') !== false || strpos($path, "\0") !== false || !@is_file($path)) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        $json = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if (!is_string($json)) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        return self::fromJson($json);
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        self::keys($data, ['schema_version', 'scope', 'catalog_id_field', 'evidence', 'entries']);
        if ($data['schema_version'] !== 1 || $data['scope'] !== 'preview'
            || $data['catalog_id_field'] !== 'catalog_hotels.id') {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        self::keys($data['evidence'], ['checked_at', 'source_sha', 'source_file', 'run_url']);
        $evidence = $data['evidence'];
        if (!self::timestamp($evidence['checked_at'])
            || !is_string($evidence['source_sha'])
            || !preg_match('/\A[a-f0-9]{40}\z/D', $evidence['source_sha'])
            || !is_string($evidence['source_file'])
            || !preg_match('/\Adocs\/integrations\/[a-z0-9-]+\.json\z/D', $evidence['source_file'])
            || !is_string($evidence['run_url'])
            || !preg_match('~\Ahttps://github\.com/pyatkoff/poisk-turov-test/actions/runs/[1-9][0-9]*\z~D', $evidence['run_url'])) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
        self::listValue($data['entries'], 1000);
        $index = [];
        foreach ($data['entries'] as $entry) {
            self::keys($entry, ['identities', 'candidate_hotel_id', 'status', 'reason', 'anex_name', 'candidate_name', 'distance_m']);
            if (!is_int($entry['candidate_hotel_id']) || $entry['candidate_hotel_id'] < 1
                || !in_array($entry['status'], ['verified_preview', 'needs_review'], true)
                || !self::textValue($entry['reason'], 1200)
                || !self::textValue($entry['anex_name'], 600)
                || !self::textValue($entry['candidate_name'], 600)) {
                throw new UnexpectedValueException('hotel_identity_registry_invalid');
            }
            $distance = $entry['distance_m'];
            if ($distance !== null && ((!is_int($distance) && !is_float($distance))
                || !is_finite((float) $distance) || $distance < 0 || $distance > 20000000)) {
                throw new UnexpectedValueException('hotel_identity_registry_invalid');
            }
            self::listValue($entry['identities'], 8);
            foreach ($entry['identities'] as $identity) {
                self::keys($identity, ['provider', 'external_id']);
                if (!self::provider($identity['provider']) || !is_string($identity['external_id'])
                    || self::externalId($identity['external_id']) === null) {
                    throw new UnexpectedValueException('hotel_identity_registry_invalid');
                }
                $key = $identity['provider'] . ':' . $identity['external_id'];
                // Even identical duplicate declarations are rejected; order cannot choose a winner.
                if (isset($index[$key])) {
                    throw new UnexpectedValueException('hotel_identity_registry_invalid');
                }
                $index[$key] = ['id' => $entry['candidate_hotel_id'], 'status' => $entry['status']];
            }
        }
        return new self($index);
    }

    /** Only an explicit preview caller can use the five reviewed pilot links. */
    public function resolve(string $provider, $externalId, string $scope = 'production'): ?int
    {
        if ($scope !== 'preview') {
            return null;
        }
        $entry = $this->lookup($provider, $externalId);
        return $entry !== null && $entry['status'] === 'verified_preview' ? $entry['id'] : null;
    }

    public function status(string $provider, $externalId): string
    {
        $entry = $this->lookup($provider, $externalId);
        return $entry === null ? 'unmapped' : $entry['status'];
    }

    private function lookup(string $provider, $externalId): ?array
    {
        $id = self::externalId($externalId);
        if (!self::provider($provider) || $id === null) {
            return null;
        }
        return $this->index[$provider . ':' . $id] ?? null;
    }

    private static function provider($value): bool
    {
        return is_string($value) && preg_match('/\A[a-z][a-z0-9_]{1,47}\z/D', $value) === 1;
    }

    private static function externalId($value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        return is_string($value) && preg_match('/\A[1-9][0-9]{0,31}\z/D', $value) === 1 ? $value : null;
    }

    private static function keys($value, array $expected): void
    {
        if (!is_array($value) || count($value) !== count($expected)
            || array_diff($expected, array_keys($value)) !== []) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
    }

    private static function listValue($value, int $maximum): void
    {
        if (!is_array($value) || $value === [] || count($value) > $maximum || array_values($value) !== $value) {
            throw new UnexpectedValueException('hotel_identity_registry_invalid');
        }
    }

    private static function textValue($value, int $maximum): bool
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= $maximum
            && !preg_match('/[\x00-\x1f\x7f]/', $value);
    }

    private static function timestamp($value): bool
    {
        if (!is_string($value) || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d{1,9})?Z\z/D', $value, $parts)) {
            return false;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (int) $parts[4] <= 23 && (int) $parts[5] <= 59 && (int) $parts[6] <= 59;
    }
}

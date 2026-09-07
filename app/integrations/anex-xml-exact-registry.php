<?php
declare(strict_types=1);

/** Preview-only compact identities produced by the audited ANEX XML exact pass. */
final class AnyTourAnexXmlExactRegistry
{
    private const MANIFEST_MAX_BYTES = 16384;
    private const MAPPING_MAX_BYTES = 524288;
    private const MAPPING_MAX_ROWS = 20000;
    private $index;

    private function __construct(array $index)
    {
        $this->index = $index;
    }

    public static function fromFile(string $path = __DIR__ . '/data/anex-xml-exact-registry.json'): self
    {
        if (strpos($path, '://') !== false || strpos($path, "\0") !== false || !@is_file($path)) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        $manifestJson = @file_get_contents($path, false, null, 0, self::MANIFEST_MAX_BYTES + 1);
        if (!is_string($manifestJson) || strlen($manifestJson) > self::MANIFEST_MAX_BYTES) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        try {
            $manifest = json_decode($manifestJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        self::keys($manifest, ['schema_version', 'scope', 'provider', 'catalog_id_field',
            'matching_rule', 'evidence', 'mapping_file', 'mapping_sha256', 'mapping_count',
            'strict_source_count', 'deferred_short_name_count']);
        if ($manifest['schema_version'] !== 1 || $manifest['scope'] !== 'preview'
            || $manifest['provider'] !== 'anex_xml' || $manifest['catalog_id_field'] !== 'catalog_hotels.id'
            || $manifest['matching_rule'] !== 'exact_name_country_town_v1_distinctive'
            || $manifest['mapping_file'] !== 'anex-xml-exact-identities.csv'
            || !is_string($manifest['mapping_sha256'])
            || !preg_match('/\A[a-f0-9]{64}\z/D', $manifest['mapping_sha256'])
            || !is_int($manifest['mapping_count']) || $manifest['mapping_count'] < 1
            || $manifest['mapping_count'] > self::MAPPING_MAX_ROWS
            || !is_int($manifest['strict_source_count']) || !is_int($manifest['deferred_short_name_count'])
            || $manifest['strict_source_count'] !== $manifest['mapping_count'] + $manifest['deferred_short_name_count']) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        self::evidence($manifest['evidence']);
        $mappingPath = dirname($path) . DIRECTORY_SEPARATOR . $manifest['mapping_file'];
        $realDirectory = realpath(dirname($path));
        $realMapping = realpath($mappingPath);
        if ($realDirectory === false || $realMapping === false || dirname($realMapping) !== $realDirectory
            || !is_file($realMapping)) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        $csv = @file_get_contents($realMapping, false, null, 0, self::MAPPING_MAX_BYTES + 1);
        if (!is_string($csv) || strlen($csv) > self::MAPPING_MAX_BYTES
            || !hash_equals($manifest['mapping_sha256'], hash('sha256', $csv))) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        $lines = explode("\n", $csv);
        if (array_pop($lines) !== '' || array_shift($lines) !== 'anex_xml_id,catalog_hotel_id'
            || count($lines) !== $manifest['mapping_count']) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
        $index = [];
        foreach ($lines as $line) {
            if (!preg_match('/\A([1-9][0-9]{0,7}),([1-9][0-9]{0,9})\z/D', $line, $parts)
                || isset($index[$parts[1]])) {
                throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
            }
            $index[$parts[1]] = (int) $parts[2];
        }
        return new self($index);
    }

    public function resolve(string $provider, $externalId, string $scope = 'production'): ?int
    {
        $id = self::externalId($externalId);
        if ($scope !== 'preview' || $provider !== 'anex_xml' || $id === null) {
            return null;
        }
        return $this->index[$id] ?? null;
    }

    public function count(): int
    {
        return count($this->index);
    }

    private static function externalId($value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        return is_string($value) && preg_match('/\A[1-9][0-9]{0,7}\z/D', $value) ? $value : null;
    }

    private static function evidence($value): void
    {
        self::keys($value, ['checked_at', 'source_sha', 'run_url', 'artifact_url',
            'artifact_digest', 'reference_stamp']);
        if (!is_string($value['checked_at'])
            || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{1,9}Z\z/D', $value['checked_at'])
            || !is_string($value['source_sha']) || !preg_match('/\A[a-f0-9]{40}\z/D', $value['source_sha'])
            || !is_string($value['run_url'])
            || !preg_match('~\Ahttps://github\.com/pyatkoff/poisk-turov-test/actions/runs/[1-9][0-9]*\z~D', $value['run_url'])
            || !is_string($value['artifact_url'])
            || !preg_match('~\Ahttps://github\.com/pyatkoff/poisk-turov-test/actions/runs/[1-9][0-9]*/artifacts/[1-9][0-9]*\z~D', $value['artifact_url'])
            || !is_string($value['artifact_digest']) || !preg_match('/\Asha256:[a-f0-9]{64}\z/D', $value['artifact_digest'])
            || !is_string($value['reference_stamp']) || !preg_match('/\A0x[0-9a-f]{16}\z/D', $value['reference_stamp'])) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
    }

    private static function keys($value, array $expected): void
    {
        if (!is_array($value) || count($value) !== count($expected)
            || array_diff($expected, array_keys($value)) !== []) {
            throw new UnexpectedValueException('anex_xml_exact_registry_invalid');
        }
    }
}

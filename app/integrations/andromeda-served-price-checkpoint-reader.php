<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-served-price-accuracy.php';

/**
 * Read-only projection of browser-safe price observations already persisted inside
 * completed Andromeda quote checkpoints. No checkpoint path or private identity is returned.
 */
final class AnyTourAndromedaServedPriceCheckpointReader
{
    private const PATTERNS = ['*-quote-v1.json', '*-quote-flight-v1.json'];

    public static function summarizeDirectory(string $directory, int $maxFiles = 10000, int $maxBytes = 131072): array
    {
        if ($maxFiles < 1 || $maxFiles > 100000 || $maxBytes < 1024 || $maxBytes > 1048576) {
            throw new InvalidArgumentException('ANDROMEDA_ACCURACY_SCAN_LIMIT');
        }
        if (!is_dir($directory) || is_link($directory)) {
            throw new InvalidArgumentException('ANDROMEDA_ACCURACY_SCAN_DIRECTORY');
        }
        $root = realpath($directory);
        if (!is_string($root)) throw new InvalidArgumentException('ANDROMEDA_ACCURACY_SCAN_DIRECTORY');

        $paths = [];
        foreach (self::PATTERNS as $pattern) {
            foreach (glob($root . DIRECTORY_SEPARATOR . $pattern) ?: [] as $path) $paths[$path] = true;
        }
        $paths = array_keys($paths);
        sort($paths, SORT_STRING);

        $scan = [
            'matched_files' => count($paths),
            'files_examined' => 0,
            'completed_checkpoints' => 0,
            'observations' => 0,
            'no_observation' => 0,
            'not_completed' => 0,
            'invalid_files' => 0,
            'truncated_by_limit' => count($paths) > $maxFiles,
        ];
        $observations = [];
        foreach (array_slice($paths, 0, $maxFiles) as $path) {
            ++$scan['files_examined'];
            $observation = self::readObservation($path, $root, $maxBytes, $scan);
            if ($observation !== null) {
                $observations[] = $observation;
                ++$scan['observations'];
            }
        }

        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'population' => 'natural_customer_actualizations',
            'source' => 'completed_quote_checkpoints',
            'scan' => $scan,
            'accuracy' => AnyTourAndromedaServedPriceAccuracy::summarize($observations),
            'supplier_calls' => 0,
            'db_writes' => 0,
            'checkpoint_writes' => 0,
        ];
    }

    private static function readObservation(string $path, string $root, int $maxBytes, array &$scan): ?array
    {
        try {
            if (!is_file($path) || is_link($path)) throw new RuntimeException();
            $real = realpath($path);
            if (!is_string($real) || dirname($real) !== $root) throw new RuntimeException();
            $size = filesize($real);
            if (!is_int($size) || $size < 2 || $size > $maxBytes) throw new RuntimeException();
            $raw = file_get_contents($real);
            if (!is_string($raw) || strlen($raw) !== $size) throw new RuntimeException();
            $envelope = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($envelope) || array_keys($envelope) !== ['state'] || !is_array($envelope['state'])) {
                throw new RuntimeException();
            }
            $state = $envelope['state'];
            if (($state['status'] ?? null) !== 'completed' || !is_array($state['result'] ?? null)) {
                ++$scan['not_completed'];
                return null;
            }
            ++$scan['completed_checkpoints'];
            $observation = $state['result']['served_price_observation'] ?? null;
            if ($observation === null) {
                ++$scan['no_observation'];
                return null;
            }
            if (!is_array($observation)) throw new RuntimeException();
            // Reuse the strict public-fact validator without duplicating its contract here.
            AnyTourAndromedaServedPriceAccuracy::summarize([$observation]);
            return $observation;
        } catch (Throwable $ignored) {
            ++$scan['invalid_files'];
            return null;
        }
    }
}

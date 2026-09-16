<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/andromeda-served-price-checkpoint-reader.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}
if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "usage: php scripts/diagnostics/andromeda_served_price_accuracy.php <searches-directory> [max-files]\n");
    exit(2);
}
$maxFiles = $argc === 3 ? filter_var($argv[2], FILTER_VALIDATE_INT) : 10000;
if (!is_int($maxFiles)) {
    fwrite(STDERR, "invalid max-files\n");
    exit(2);
}

try {
    $result = AnyTourAndromedaServedPriceCheckpointReader::summarizeDirectory($argv[1], $maxFiles);
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "accuracy readback failed\n");
    exit(1);
}

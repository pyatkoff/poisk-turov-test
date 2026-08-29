<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    $root . '/src/Database.php',
    $root . '/src/EntityRepository.php',
    $root . '/src/ContentRepository.php',
    $root . '/src/PageAssembler.php',
    $root . '/migrations/20260829_001_site_core.sql',
    $root . '/seeds/reference_turkey.sql',
];

foreach ($requiredFiles as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('Missing Site Platform file: ' . $file);
    }
}

$schema = (string) file_get_contents($root . '/migrations/20260829_001_site_core.sql');
foreach (['entity_key', 'at_entity_external_ids', 'at_content_blocks', 'at_page_overrides'] as $needle) {
    if (!str_contains($schema, $needle)) {
        throw new RuntimeException('Schema contract missing: ' . $needle);
    }
}

$seed = (string) file_get_contents($root . '/seeds/reference_turkey.sql');
if (!str_contains($seed, "'tourvisor', '4', 'country'")) {
    throw new RuntimeException('Turkey reference seed must retain confirmed Tourvisor country ID 4.');
}

fwrite(STDOUT, "SITE_PLATFORM_CONTRACT_OK\n");

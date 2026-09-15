<?php
declare(strict_types=1);

$schema = file_get_contents(__DIR__ . '/../v2/data/migrate-hotel-details-v1.php');
$collector = file_get_contents(__DIR__ . '/../v2/data/collect-hotel-details-v1.php');
$select = file_get_contents(__DIR__ . '/../v2/data/hotels-select-v1.php');
if ($schema === false || $collector === false || $select === false) throw new RuntimeException('required local hotel sources missing');

$required = ['description','primary_image_url','images_json','infrastructure_json','services_json','room_types'];
foreach ($required as $field) {
    if (!str_contains($schema, $field)) throw new RuntimeException("schema missing {$field}");
    if (!str_contains($collector, $field)) throw new RuntimeException("collector missing {$field}");
}

// Current Search3 local select is intentionally only a search/catalog DTO; it does not expose rich hotel presentation.
foreach (['description','images_json','infrastructure_json','services_json','room_types'] as $field) {
    if (str_contains($select, "'{$field}'")) throw new RuntimeException("hotels-select unexpectedly owns rich presentation field {$field}");
}

// The product fix must use a dedicated read-only details DTO keyed by hotel id rather than bloating/re-owning the search select.
echo "SEARCH3_LOCAL_HOTEL_DETAILS_READER_CONTRACT_OK\n";

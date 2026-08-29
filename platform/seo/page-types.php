<?php

declare(strict_types=1);

return [
    'country' => [
        'template' => 'country',
        'required_roles' => ['country'],
        'required_blocks' => ['hero', 'live_tours', 'popular_resorts'],
        'requires_inventory' => true,
        'min_quality_score' => 80,
        'link_budget' => 40,
    ],
    'resort' => [
        'template' => 'resort',
        'required_roles' => ['country', 'resort'],
        'required_blocks' => ['hero', 'live_tours', 'hotels'],
        'requires_inventory' => true,
        'min_quality_score' => 80,
        'link_budget' => 40,
    ],
    'hotel' => [
        'template' => 'hotel',
        'required_roles' => ['country', 'resort', 'hotel'],
        'required_blocks' => ['hero', 'hotel_facts', 'live_tours'],
        'requires_inventory' => true,
        'min_quality_score' => 85,
        'link_budget' => 30,
    ],
    'departure_country' => [
        'template' => 'departure_country',
        'required_roles' => ['departure_city', 'country'],
        'required_blocks' => ['hero', 'live_tours', 'popular_resorts'],
        'requires_inventory' => true,
        'min_quality_score' => 85,
        'link_budget' => 40,
    ],
    'country_month' => [
        'template' => 'country_month',
        'required_roles' => ['country', 'month'],
        'required_blocks' => ['hero', 'live_tours', 'season_info'],
        'requires_inventory' => true,
        'min_quality_score' => 90,
        'link_budget' => 30,
    ],
    'holiday_type_country' => [
        'template' => 'holiday_type_country',
        'required_roles' => ['holiday_type', 'country'],
        'required_blocks' => ['hero', 'live_tours', 'editorial'],
        'requires_inventory' => true,
        'min_quality_score' => 90,
        'link_budget' => 30,
    ],
];

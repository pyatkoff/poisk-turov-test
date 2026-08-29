<?php

declare(strict_types=1);

return [
    'country' => [
        'title' => 'Туры в {country_name_accusative} — цены {year} | AnyTour',
        'description' => 'Туры в {country_name_prepositional}: актуальные цены, курорты, отели и предложения туроператоров. Подберите тур онлайн в AnyTour.',
        'h1' => 'Туры в {country_name_accusative}',
        'canonical' => '/country/{country_slug}/',
    ],
    'resort' => [
        'title' => 'Туры в {resort_name} — цены {year} | AnyTour',
        'description' => 'Туры в {resort_name}: актуальные предложения, отели и цены. Подберите подходящий тур онлайн в AnyTour.',
        'h1' => 'Туры в {resort_name}',
        'canonical' => '/country/{country_slug}/{resort_slug}/',
    ],
    'departure_country' => [
        'title' => 'Туры в {country_name_accusative} из {departure_name_genitive} — цены {year} | AnyTour',
        'description' => 'Туры в {country_name_prepositional} с вылетом из {departure_name_genitive}: актуальные цены, даты и отели. Поиск туров AnyTour.',
        'h1' => 'Туры в {country_name_accusative} из {departure_name_genitive}',
        'canonical' => '/tours-from-{departure_slug}/{country_slug}/',
    ],
];

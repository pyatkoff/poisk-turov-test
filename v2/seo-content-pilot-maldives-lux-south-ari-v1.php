<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_lux_south_ari(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 12126,
        'hotel_slug' => 'lux-south-ari-atoll-resorts-villas-12126',
        'hotel_name' => 'LUX* South Ari Atoll Resorts & Villas',
    ], [
        'title' => 'Туры в LUX* South Ari Atoll Resorts & Villas — цены | AnyTour',
        'description' => 'Подберите тур в LUX* South Ari Atoll Resorts & Villas: сравните даты, продолжительность, питание и стоимость пакета, а актуальные варианты проверьте в поиске AnyTour.',
        'intro' => 'Здесь собраны ориентиры для выбора пакетного тура именно в LUX* South Ari Atoll Resorts & Villas. Название отеля не заменяет проверку самого турпакета: при сравнении важны город и дата вылета, количество ночей, питание и итоговая стоимость.',
        'sections' => [
            [
                'id' => 'compare',
                'title' => 'Как сравнивать туры в LUX* South Ari Atoll Resorts & Villas',
                'paragraphs' => [
                    'Для одного отеля одновременно могут быть доступны предложения с разными датами, продолжительностью и городами вылета. Чтобы сравнение было корректным, сначала зафиксируйте основные параметры поездки.',
                    'Цена на странице не считается постоянной характеристикой отеля: актуальный вариант нужно подтверждать в поиске перед заявкой.',
                ],
            ],
            [
                'id' => 'stay',
                'title' => 'Питание и вариант размещения',
                'paragraphs' => [
                    'Проверяйте питание и размещение внутри конкретного предложения. Разные комбинации могут менять как итоговую стоимость, так и состав включённых услуг.',
                    'Если нужен определённый формат размещения или питания, сравнивайте только те пакеты, где он явно указан.',
                ],
            ],
            [
                'id' => 'package',
                'title' => 'Перелёт, трансфер и состав пакета',
                'paragraphs' => [
                    'Данные о перелёте, багаже, трансфере и других услугах относятся к выбранному турпакету. Их нельзя автоматически считать одинаковыми для всех предложений в этот отель.',
                    'Перед заявкой проверьте финальные параметры выбранного тура в поиске AnyTour.',
                ],
            ],
            [
                'id' => 'search',
                'title' => 'Подбор тура в LUX* South Ari Atoll Resorts & Villas',
                'paragraphs' => [
                    'Откройте поиск с уже выбранным отелем, задайте город вылета, даты, продолжительность и состав туристов. После этого сравните доступные предложения по одинаковым параметрам.',
                    'Если подходящего варианта нет, можно изменить период или город вылета, не сбрасывая выбранный отель.',
                ],
            ],
        ],
        'content_notes' => [
            'Hotel ID 12126 and slug lux-south-ari-atoll-resorts-villas-12126 were verified by fresh production hotel snapshot evidence on 2026-09-01.',
            'The hotel name contains South Ari, but no separate atoll region/subregion search binding is inferred from it.',
        ],
    ]);
}

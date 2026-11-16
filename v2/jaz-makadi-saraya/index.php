<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-page-shell-v1.php';
require_once __DIR__ . '/handoff.php';

/*
 * SITE1719 / SEO1720: bounded hotel reference, not a current tour offer.
 * Accepted corpus: max-search-bot1170, commit0fd3aa2d8a4c5ea53fb15c06cfeaa0ace633313a,
 * knowledge/hotels/anytour-99-apartments.json, SHA256934f2c3b98b2b509ab845feeb6bb0e9d50c570906ac29fb515da8a04f0ae8bdb.
 * Identity: AnyTour99/rev1 -> local-tv268/rev3, country1/region5.
 * No current price, availability, exact room-code binding or room photography.
 */
$context = sp_context(
    '/jaz-makadi-saraya/',
    'JAZ Makadi Saraya — номера, апартаменты и туры | AnyTour',
    'JAZ Makadi Saraya Resort в Макади-Бей: сравнение апартаментов 70 и 100 м² и Family Deluxe, особенности пляжа и переход к турам на ваши даты.'
);
$context['robots'] = v2_seo_robots_content(false);
header('X-Robots-Tag: noindex, nofollow');
$handoff = jaz_makadi_saraya_handoff($_GET);
$searchUrl = $handoff['url'];
$photoUrl = 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573305.jpg';
$officialUrl = 'https://www.jazhotels.com/hoteldetail/20-egypt-hurghada-makadi-bay-jaz-makadi-saraya-resort/overview';
$factsheetUrl = 'https://partner.jazhotels.com/Images/uploads/gallery/Downloads/JazMakadiSaraya/JAZ%20Makadi%20Saraya%20-Factsheet.pdf.pdf';
$intouristUrl = 'https://intourist.ru/info/egypt/hurgada/makadi-bej/hotel/jaz-makadi-saraya';
$corendonUrl = 'https://www.corendon.nl/egypte/rode-zee/hurghada/makadi-bay/jaz-makadi-saraya-resort';
$pageCssUrl = v2_site_href('/jaz-makadi-saraya/page.css') . '?v=' . v2_asset_content_version(__DIR__ . '/page.css');
$hotelSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Hotel',
    '@id' => 'https://anytoour.ru/jaz-makadi-saraya/#hotel',
    'name' => 'JAZ Makadi Saraya Resort',
    'alternateName' => 'Iberotel Makadi Saraya Resort',
    'url' => 'https://anytoour.ru/jaz-makadi-saraya/',
    'sameAs' => $officialUrl,
    'image' => $photoUrl,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Makadi Bay, Madinat Makadi, P.O. Box 178',
        'addressLocality' => 'Hurghada',
        'addressRegion' => 'Red Sea',
        'addressCountry' => 'EG',
    ],
];

sp_head($context);
?>
<link rel="stylesheet" href="<?=sp_e($pageCssUrl)?>">
<?php
sp_header($context);
sp_breadcrumbs([
    ['label' => 'AnyTour', 'href' => '/'],
    ['label' => 'Египет', 'href' => '/country/egypt/'],
    ['label' => 'JAZ Makadi Saraya'],
]);
?>
<script type="application/ld+json"><?=v2_seo_json_ld($hotelSchema)?></script>
<main class="sp-main jaz-page" data-anytour-hotel-id="99" data-local-tv-hotel-id="268">
    <section class="jaz-hero" aria-labelledby="hotel-title">
        <div class="jaz-hero__heading">
            <span class="jaz-eyebrow">Египет · Макади-Бей</span>
            <h1 id="hotel-title">JAZ Makadi Saraya Resort</h1>
        </div>
        <figure class="jaz-hero__photo">
            <img src="<?=sp_e($photoUrl)?>" width="1024" height="554"
                 alt="Территория JAZ Makadi Saraya: бассейн и невысокие корпуса"
                 fetchpriority="high" decoding="async">
            <figcaption>Территория отеля · фото из каталога Tourvisor</figcaption>
        </figure>
        <div class="jaz-hero__body">
            <p class="jaz-lead">Когда в поездке хочется больше места: апартаменты с гостиной, кухней и одной или двумя спальнями.</p>
            <ul class="jaz-facts" aria-label="Главное перед выбором">
                <li>Апартаменты 70 и 100 м²</li>
                <li>Вторая береговая линия</li>
            </ul>
            <div class="jaz-hero__actions">
                <a class="sp-primary" data-hotel-search href="<?=sp_e($searchUrl)?>">Найти тур в этот отель</a>
                <a class="jaz-text-link" href="#rooms">Сравнить номера <span aria-hidden="true">↓</span></a>
            </div>
            <p class="jaz-caption">Отель уже выбран. Даты, состав гостей и актуальные предложения — в поиске.</p>
            <?php foreach ($handoff['notices'] as $notice): ?>
                <p class="jaz-handoff-notice"><?=sp_e($notice)?></p>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="rooms" aria-labelledby="rooms-title">
        <div class="jaz-section-heading">
            <div><span class="jaz-eyebrow">Номера и апартаменты</span><h2 id="rooms-title">Выберите подходящую планировку</h2></div>
            <p>Сравните площадь и спальни. Названия категорий — из описания Интуриста.</p>
        </div>
        <div class="jaz-rooms">
            <article class="jaz-room">
                <div class="jaz-room__heading"><h3>Одна спальня</h3><span class="jaz-room__area">70 <small>м²</small></span></div>
                <p class="jaz-room__name">One-Bedroom Apartment</p>
                <dl>
                    <div><dt>Пространство</dt><dd>Спальня, гостиная, кухня и обеденная зона</dd></div>
                    <div><dt>Кровати</dt><dd>Двуспальная либо две односпальные</dd></div>
                </dl>
                <p class="jaz-room__choice">Если достаточно одной спальни и хочется отдельной гостиной.</p>
            </article>
            <article class="jaz-room">
                <div class="jaz-room__heading"><h3>Две спальни</h3><span class="jaz-room__area">100 <small>м²</small></span></div>
                <p class="jaz-room__name">Two-Bedroom Apartment</p>
                <dl>
                    <div><dt>Пространство</dt><dd>Две спальни, гостиная, кухня и обеденная зона</dd></div>
                    <div><dt>Кровати</dt><dd>Двуспальная и две односпальные</dd></div>
                </dl>
                <p class="jaz-room__choice">Если нужны две спальни и больше места для вашей компании.</p>
            </article>
            <article class="jaz-room">
                <div class="jaz-room__heading"><h3>Family Deluxe</h3><span class="jaz-room__area">55 <small>м²</small></span></div>
                <p class="jaz-room__name">Семейный номер</p>
                <dl>
                    <div><dt>Размещение</dt><dd>В описании — «2+2» или «3+1», до четырёх гостей</dd></div>
                    <div><dt>Что проверить</dt><dd>Допустимый возраст детей по условиям тура</dd></div>
                </dl>
                <p class="jaz-room__choice">Для пяти гостей эта категория не подтверждена.</p>
            </article>
        </div>
        <div class="jaz-room-note">
            <strong>Перед выбором номера</strong>
            <p>Состав гостей, дополнительные места и закрывающиеся двери между комнатами нужно уточнить по конкретному туру. Для пятого гостя в апартаментах отдельно проверьте спальное место и возрастные условия. Описания категорий не гарантируют их наличие на ваши даты.</p>
        </div>
    </section>

    <section id="location" aria-labelledby="location-title">
        <div class="jaz-section-heading"><div><span class="jaz-eyebrow">Расположение и территория</span><h2 id="location-title">Что учесть перед поездкой</h2></div></div>
        <div class="jaz-location">
            <article>
                <h3>До пляжа нужно пройти</h3>
                <p>Интурист указывает вторую береговую линию и 300 м до пляжа, Corendon — около 500 м. Точный маршрут не измерен.</p>
            </article>
            <article>
                <h3>Корпуса без лифтов</h3>
                <p>По описанию Corendon, в отеле 13 корпусов до трёх этажей, лифтов нет. Если важен доступ без лестниц, заранее проверьте этаж номера и путь к нему.</p>
            </article>
        </div>
        <div class="jaz-address">
            <span class="jaz-eyebrow">Адрес отеля</span>
            <address>Makadi Bay, Madinat Makadi, P.O. Box 178, Hurghada, Red Sea, Egypt</address>
            <a href="<?=sp_e($officialUrl)?>" rel="noreferrer">Страница отеля JAZ <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <section class="jaz-search" aria-labelledby="search-title" data-offer-state="unverified">
        <div>
            <span class="jaz-eyebrow">Следующий шаг</span>
            <h2 id="search-title">Сравните туры в этот отель</h2>
            <p>JAZ Makadi Saraya уже выбран в поиске. Проверьте город вылета, даты и состав гостей — и посмотрите предложения для своей поездки.</p>
            <p class="jaz-search__availability">Цена и наличие проверяются на ваши даты. Если предложений нет, попробуйте соседние даты или другую длительность. Сохранённое ранее предложение нужно проверить заново.</p>
        </div>
        <a class="sp-primary" data-hotel-search href="<?=sp_e($searchUrl)?>">Найти тур в этот отель <span aria-hidden="true">→</span></a>
    </section>

    <section class="jaz-sources" aria-label="Источники и подробности">
        <details>
            <summary>Источники и подробности о номерах</summary>
            <div class="jaz-sources__body">
                <p>Это JAZ Makadi Saraya Resort, ранее Iberotel Makadi Saraya Resort. Saraya Palms и Makadina — другие отели. Фотография показывает территорию, а не выбранную категорию номера.</p>
                <p>Интурист описывает эти категории с видом на сад или бассейн и публикует максимум «4+1» для One-Bedroom Apartment и «5+1» для Two-Bedroom Apartment. Возрастные условия и все дополнительные места не раскрыты; пятое спальное место отдельно не описано. Закрывающиеся внутренние двери не подтверждены. Названия категорий у разных операторов не устанавливают точного соответствия номеру в туре.</p>
                <p>Питание, включённые услуги и допустимый состав гостей определяются условиями выбранного предложения.</p>
                <ul>
                    <li><a href="<?=sp_e($factsheetUrl)?>" rel="noreferrer">Официальный factsheet JAZ</a> — категории апартаментов, обеденный стол и микроволновая печь. Прочитан 19 октября 2026 года.</li>
                    <li><a href="<?=sp_e($intouristUrl)?>" rel="noreferrer">Карточка Интуриста</a> — площади, планировки, опубликованная вместимость и пляж. Прочитана 19 октября 2026 года.</li>
                    <li><a href="<?=sp_e($corendonUrl)?>" rel="noreferrer">Карточка Corendon</a> — корпуса, лифты и другая оценка расстояния до пляжа. Прочитана 20 октября; раздел о корпусах — отдельно 25 октября 2026 года.</li>
                </ul>
                <p class="jaz-caption">Даты публикации материалов не указаны. Дата чтения не означает обновление самих сведений.</p>
            </div>
        </details>
    </section>
</main>
<?php sp_end($context);

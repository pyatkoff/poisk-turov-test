<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-page-shell-v1.php';
require_once __DIR__ . '/handoff.php';

/*
 * SITE1719 / SEO1720: bounded hotel reference, not a current tour offer.
 * Accepted corpus: max-search-bot1170, commit0fd3aa2d8a4c5ea53fb15c06cfeaa0ace633313a,
 * knowledge/hotels/anytour-99-apartments.json, SHA256934f2c3b98b2b509ab845feeb6bb0e9d50c570906ac29fb515da8a04f0ae8bdb.
 * Identity: AnyTour99/rev1 -> local-tv268/rev3, country1/region5.
 * Interior photos: existing canonical AnyTour99 catalogue gallery, read2026-11-16.
 * Hotel facilities: same canonical snapshot (SHA256066c84f1c89bfc5b58fb74cc55004e81de5816bd672268d11725dd022ad54fe3)
 * cross-checked with official factsheet pp4/5/7, read2026-11-16; source update date unknown.
 * No current price, availability, exact room-code or photo-to-bookable-55/70/100 sqm binding.
 */
$context = sp_context(
    '/jaz-makadi-saraya/',
    'JAZ Makadi Saraya Resort — туры, цены и фото | AnyTour',
    'JAZ Makadi Saraya Resort, Макади-Бей: минимальная найденная цена из мониторинга, условия тура, фотографии, бассейны и поиск на свои даты.'
);
$context['robots'] = v2_seo_robots_content(false);
header('X-Robots-Tag: noindex, nofollow');
$handoff = jaz_makadi_saraya_handoff($_GET);
$searchUrl = $handoff['url'];
$photoUrl = 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573305.jpg';
$galleryPhotos = [
    ['url' => 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573321.jpg', 'caption' => 'Гостиная', 'alt' => 'Гостиная в JAZ Makadi Saraya: диван, столик и выход на террасу'],
    ['url' => 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573324.jpg', 'caption' => 'Обеденная зона', 'alt' => 'Обеденный стол и гостиная за аркой в JAZ Makadi Saraya'],
    ['url' => 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573318.jpg', 'caption' => 'Спальня', 'alt' => 'Спальня с двумя кроватями в JAZ Makadi Saraya'],
];
/* Exact /hotel/20 official photo-pool captions; owner requested use in preview on 2026-11-17.
 * Attribution remains; no assumption that a category image proves the 55/70/100 sqm floor plan or a bookable code.
 * Owner instruction is not a separate third-party license grant. */
$officialPhotoPool = 'https://partner.jazhotels.com/hotel/20';
$officialMedia = [
    ['caption' => 'One-Bedroom Apartment', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010907One%20Bedroom%20Apartment%20-%20Living%20Room%20Garden%20View.png', 'alt' => 'Гостиная One-Bedroom Apartment в JAZ Makadi Saraya', 'note' => 'Официальная фотография категории; конкретный номер, площадь и вид при бронировании не подтверждены'],
    ['caption' => 'Two-Bedroom Apartment', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010906Two%20Bedroom%20Apartment%20-%20Living%20Garden%20View%20(2).png', 'alt' => 'Гостиная Two-Bedroom Apartment в JAZ Makadi Saraya', 'note' => 'Официальная фотография категории; конкретный номер, площадь и вид при бронировании не подтверждены'],
    ['caption' => 'Family Deluxe', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/000957Family%20Deluxe%20-%20Queen%20Garden%20View%20Living.png', 'alt' => 'Гостиная Family Deluxe в JAZ Makadi Saraya', 'note' => 'Официальная фотография категории; конкретный номер, площадь и вид при бронировании не подтверждены'],
    ['caption' => 'Бассейны', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010900Drone%20-%20Saraya%20Resort%20Pool%20Overview.png', 'alt' => 'Вид сверху на бассейны JAZ Makadi Saraya', 'note' => 'Официальная фотография инфраструктуры; доступность и включение услуг зависят от тарифа и дат'],
    ['caption' => 'JAZY Kids Club', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010943Jazy%20Kids%20Club.png', 'alt' => 'Детский клуб JAZY в JAZ Makadi Saraya', 'note' => 'Официальная фотография инфраструктуры; доступность и включение услуг зависят от тарифа и дат'],
    ['caption' => 'Sunflower', 'url' => 'https://partner.jazhotels.com/Images/uploads/gallery/photos/new/010908Sunflower%20Main%20Restaurant%20-%20Indoor.png', 'alt' => 'Ресторан Sunflower в JAZ Makadi Saraya', 'note' => 'Официальная фотография инфраструктуры; доступность и включение услуг зависят от тарифа и дат'],
];
$officialUrl = 'https://www.jazhotels.com/hoteldetail/20-egypt-hurghada-makadi-bay-jaz-makadi-saraya-resort/overview';
$factsheetUrl = 'https://partner.jazhotels.com/Images/uploads/gallery/Downloads/JazMakadiSaraya/JAZ%20Makadi%20Saraya%20-Factsheet.pdf.pdf';
$intouristUrl = 'https://intourist.ru/info/egypt/hurgada/makadi-bej/hotel/jaz-makadi-saraya';
$corendonUrl = 'https://www.corendon.nl/egypte/rode-zee/hurghada/makadi-bay/jaz-makadi-saraya-resort';
$pageCssUrl = v2_site_href('/jaz-makadi-saraya/page.css') . '?v=' . v2_asset_content_version(__DIR__ . '/page.css');
$viewerCssUrl = v2_site_href('/hotel-photo-viewer-v1.css') . '?v=' . v2_asset_content_version(dirname(__DIR__) . '/hotel-photo-viewer-v1.css');
$galleryScriptUrl = v2_site_href('/jaz-makadi-saraya/gallery.js') . '?v=' . v2_asset_content_version(__DIR__ . '/gallery.js');
$priceScriptUrl = v2_site_href('/jaz-makadi-saraya/price-observation.js') . '?v=' . v2_asset_content_version(__DIR__ . '/price-observation.js');
$hotelSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Hotel',
    '@id' => 'https://anytoour.ru/jaz-makadi-saraya/#hotel',
    'name' => 'JAZ Makadi Saraya Resort',
    'alternateName' => 'Iberotel Makadi Saraya Resort',
    'url' => 'https://anytoour.ru/jaz-makadi-saraya/',
    'sameAs' => $officialUrl,
    'image' => array_merge([$photoUrl], array_column($galleryPhotos, 'url')),
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
<link rel="stylesheet" href="<?=sp_e($viewerCssUrl)?>">
<link rel="stylesheet" href="<?=sp_e($pageCssUrl)?>">
<script src="<?=sp_e($galleryScriptUrl)?>" defer></script>
<script src="<?=sp_e($priceScriptUrl)?>" defer></script>
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
            <a href="<?=sp_e($photoUrl)?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
               data-photo-caption="Территория отеля" data-photo-note="Фото из каталога Tourvisor"
               aria-label="Открыть фото: Территория отеля">
                <img src="<?=sp_e($photoUrl)?>" width="1024" height="554"
                     alt="Территория JAZ Makadi Saraya: бассейн и невысокие корпуса"
                     fetchpriority="high" decoding="async">
                <span class="jaz-photo-hint" aria-hidden="true">Смотреть 10 фото</span>
            </a>
            <figcaption>Территория отеля · фото из каталога Tourvisor</figcaption>
        </figure>
        <div class="jaz-hero__body">
            <p class="jaz-lead">Курортный отель в Макади-Бей: бассейны, рестораны и пляж на второй линии. Посмотрите минимальную найденную цену и подберите тур на свои даты.</p>
            <div id="price-example" class="jaz-price" aria-label="Минимальная найденная цена"
                     data-jaz-price-example data-price-endpoint="/data/hotel-monitor-prices-read-v1.php"
                     data-price-search="<?=sp_e(jaz_makadi_saraya_handoff([])['url'])?>">
                <div>
                    <span class="jaz-eyebrow">Цены на туры</span>
                    <h2 id="jaz-price-title">Найдённая минимальная цена</h2>
                    <p>Самая низкая свежая цена из мониторинга <strong>с вылетом из Москвы, на двоих</strong> за ближайший год.</p>
                    <p class="jaz-caption">Не окончательная стоимость тура: доплаты и наличие проверяются отдельно.</p>
                </div>
                <div class="jaz-price__action">
                    <button type="button" class="jaz-price__button" data-price-load>Обновить найденную цену</button>
                    <p class="jaz-price__status" data-price-status role="status" aria-live="polite">Проверяем свежие цены…</p>
                    <div class="jaz-price__result" data-price-result hidden>
                        <span class="jaz-price__tag">Минимум среди свежих наблюдений — не итоговая стоимость</span>
                        <strong class="jaz-price__amount" data-price-amount></strong>
                        <p data-price-terms></p>
                        <p class="jaz-price__warning" data-price-extra></p>
                        <p class="jaz-price__source" data-price-source></p>
                        <a class="jaz-text-link" data-price-search-link href="<?=sp_e(jaz_makadi_saraya_handoff([])['url'])?>" hidden>Проверить туры на эти даты <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </div>
            <ul class="jaz-facts" aria-label="Главные особенности отеля">
                <li>3 бассейна и 2 детских</li>
                <li>Пляж на второй линии</li>
                <li>JAZY Kids Club</li>
            </ul>
            <div class="jaz-hero__actions">
                <a class="sp-primary" data-hotel-search href="<?=sp_e($searchUrl)?>">Проверить туры на свои даты</a>
                <a class="jaz-text-link" href="#interiors">Посмотреть фотографии <span aria-hidden="true">↓</span></a>
            </div>
            <p class="jaz-caption">JAZ уже выбран в форме. Указанные условия поездки сохраняются при переходе в поиск.</p>
            <?php foreach ($handoff['notices'] as $notice): ?>
                <p class="jaz-handoff-notice"><?=sp_e($notice)?></p>
            <?php endforeach; ?>
        </div>
    </section>

    <nav class="jaz-quick-nav" aria-label="Разделы страницы отеля">
        <span>На странице:</span>
        <a href="#price-example">Цены</a>
        <a href="#interiors">Фотографии</a>
        <a href="#rooms">Номера</a>
        <a href="#stay">Бассейны и питание</a>
        <a href="#location">Пляж</a>
        <a href="#questions">Частые вопросы</a>
    </nav>

    <section id="interiors" aria-labelledby="interiors-title">
        <div class="jaz-section-heading">
            <div><span class="jaz-eyebrow">Фотографии отеля</span><h2 id="interiors-title">Загляните внутрь</h2></div>
            <p>Гостиная, обеденная зона и спальня. Нажмите на фото, чтобы рассмотреть детали.</p>
        </div>
        <div class="jaz-gallery">
            <?php foreach ($galleryPhotos as $galleryPhoto): ?>
                <figure>
                    <a href="<?=sp_e($galleryPhoto['url'])?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
                       data-photo-caption="<?=sp_e($galleryPhoto['caption'])?>" data-photo-note="Интерьер отеля; категория номера не указана"
                       aria-label="<?=sp_e('Открыть фото: ' . $galleryPhoto['caption'])?>">
                        <img src="<?=sp_e($galleryPhoto['url'])?>" width="1024" height="683" alt="<?=sp_e($galleryPhoto['alt'])?>" loading="lazy" decoding="async">
                    </a>
                    <figcaption><?=sp_e($galleryPhoto['caption'])?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="jaz-caption">Это фотографии интерьеров отеля, а не подтверждённые снимки конкретных категорий номеров.</p>
    </section>

    <section id="rooms" aria-labelledby="rooms-title">
        <div class="jaz-section-heading">
            <div><span class="jaz-eyebrow">Номера</span><h2 id="rooms-title">Как выглядят номера</h2></div>
            <p>Есть разные категории размещения. Выбирайте подходящую по условиям и стоимости конкретного тура.</p>
        </div>
        <div class="jaz-room-photos">
            <?php foreach (array_slice($officialMedia, 0, 3) as $media): ?>
                <figure class="jaz-official-photo">
                    <a href="<?=sp_e($media['url'])?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
                       data-photo-caption="<?=sp_e($media['caption'])?>" data-photo-note="<?=sp_e($media['note'])?>"
                       aria-label="<?=sp_e('Открыть фото: ' . $media['caption'])?>">
                        <img src="<?=sp_e($media['url'])?>" width="800" height="533" alt="<?=sp_e($media['alt'])?>" loading="lazy" decoding="async">
                    </a>
                    <figcaption><?=sp_e($media['caption'])?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="jaz-caption">Это фотографии категорий из медиатеки отеля, а не подтверждённые фотографии номера в найденном туре.</p>
    </section>

    <section id="stay" aria-labelledby="stay-title">
        <div class="jaz-section-heading"><div><span class="jaz-eyebrow">В отеле</span><h2 id="stay-title">Что есть в отеле</h2></div></div>
        <div class="jaz-stay">
            <article>

                <?php $media = $officialMedia[3]; ?>
                <figure class="jaz-official-photo">
                    <a href="<?=sp_e($media['url'])?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
                       data-photo-caption="<?=sp_e($media['caption'])?>" data-photo-note="<?=sp_e($media['note'])?>"
                       aria-label="<?=sp_e('Открыть фото: ' . $media['caption'])?>">
                        <img src="<?=sp_e($media['url'])?>" width="800" height="533" alt="<?=sp_e($media['alt'])?>" loading="lazy" decoding="async">
                    </a>

                </figure>
                <h3>Бассейны</h3>
                <p>В отеле три бассейна и два детских бассейна.</p>
            </article>
            <article>

                <?php $media = $officialMedia[4]; ?>
                <figure class="jaz-official-photo">
                    <a href="<?=sp_e($media['url'])?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
                       data-photo-caption="<?=sp_e($media['caption'])?>" data-photo-note="<?=sp_e($media['note'])?>"
                       aria-label="<?=sp_e('Открыть фото: ' . $media['caption'])?>">
                        <img src="<?=sp_e($media['url'])?>" width="800" height="533" alt="<?=sp_e($media['alt'])?>" loading="lazy" decoding="async">
                    </a>

                </figure>
                <h3>Детский клуб</h3>
                <p>Для детей есть JAZY Kids Club.</p>
            </article>
            <article>

                <?php $media = $officialMedia[5]; ?>
                <figure class="jaz-official-photo">
                    <a href="<?=sp_e($media['url'])?>" target="_blank" rel="noopener noreferrer" data-jaz-photo
                       data-photo-caption="<?=sp_e($media['caption'])?>" data-photo-note="<?=sp_e($media['note'])?>"
                       aria-label="<?=sp_e('Открыть фото: ' . $media['caption'])?>">
                        <img src="<?=sp_e($media['url'])?>" width="800" height="533" alt="<?=sp_e($media['alt'])?>" loading="lazy" decoding="async">
                    </a>

                </figure>
                <h3>Рестораны</h3>
                <p>Главные рестораны — Sunflower и Al Nakhil. В Sunflower питание организовано по системе «шведский стол».</p>
            </article>
        </div>
        <p class="jaz-caption">Тип питания и доступность услуг зависят от выбранного тура и дат поездки. Расписание и возрастные условия детского клуба указаны не для всех тарифов.</p>
    </section>

    <section id="location" aria-labelledby="location-title">
        <div class="jaz-section-heading"><div><span class="jaz-eyebrow">Пляж, кухня и территория</span><h2 id="location-title">Пляж и особенности размещения</h2></div></div>
        <div class="jaz-location">
            <article>
                <h3>До пляжа нужно пройти</h3>
                <p>Отель расположен на второй береговой линии. По данным источников, до пляжа около 300–500 м. Путь зависит от вашего корпуса и маршрута.</p>
            </article>
            <article>
                <h3>Кухня в апартаментах</h3>
                <p>В апартаментах описаны кухня, обеденный стол и микроволновая печь. Включённое питание определяется условиями конкретного тура.</p>
            </article>
            <article>
                <h3>Корпуса без лифтов</h3>
                <p>13 корпусов высотой до трёх этажей, без лифтов. С коляской или при ограниченной подвижности важны этаж и путь к номеру.</p>
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
            <h2 id="search-title">Узнайте варианты на свои даты</h2>
            <p>JAZ Makadi Saraya уже выбран. Укажите город вылета, даты и всех гостей — поиск сохранит переданные условия.</p>
            <p class="jaz-search__availability">Цена «от» выше взята из сохранённого мониторинга и может включать неизвестные доплаты; актуальную стоимость и наличие проверьте в результатах поиска.</p>
        </div>
        <a class="sp-primary" data-hotel-search href="<?=sp_e($searchUrl)?>">Проверить туры на свои даты <span aria-hidden="true">→</span></a>
    </section>

    <section id="questions" class="jaz-questions" aria-labelledby="questions-title">
        <div class="jaz-section-heading">
            <div><span class="jaz-eyebrow">Ответы перед поездкой</span><h2 id="questions-title">Частые вопросы о JAZ Makadi Saraya</h2></div>
        </div>
        <div class="jaz-questions__grid">
            <details>
                <summary>Далеко ли от отеля до пляжа?</summary>
                <p>JAZ Makadi Saraya находится на второй береговой линии в Макади-Бей. Источники указывают примерно 300–500 м; точный путь от корпуса зависит от маршрута.</p>
            </details>
            <details>
                <summary>Какие номера есть в отеле?</summary>
                <p>В описаниях есть стандартные номера и апартаменты, включая One-Bedroom, Two-Bedroom и Family Deluxe. Доступные категории, размещение и стоимость зависят от выбранного тура.</p>
            </details>
            <details>
                <summary>Есть ли бассейны и занятия для детей?</summary>
                <p>В описании отеля указаны три бассейна, два детских и клуб JAZY Kids Club. Работу услуг, расписание и возрастные условия нужно сопоставить с датами поездки.</p>
            </details>
            <details>
                <summary>Что известно о питании?</summary>
                <p>В Sunflower предусмотрен шведский стол, также указан ресторан Al Nakhil. Какие рестораны и услуги включены, зависит от выбранного тарифа.</p>
            </details>
            <details>
                <summary>Есть ли лифты в корпусах?</summary>
                <p>По данным гостиничного описания, корпуса до трёх этажей и без лифтов. При необходимости заранее выбирайте подходящий этаж и маршрут.</p>
            </details>
            <details>
                <summary>Как посмотреть цену тура в этот отель?</summary>
                <p>Откройте подбор туров, укажите даты, вылет и состав гостей. На этой странице нет подтверждённой текущей цены: она должна относиться к конкретному предложению с известными условиями.</p>
            </details>
        </div>
    </section>

    <section class="jaz-sources" aria-label="Источники и подробности">
        <details>
            <summary>Источники и подробности об отеле</summary>
            <div class="jaz-sources__body">
                <p>Официальные фотографии категорий, бассейна, детского клуба и Sunflower — из <a href="<?=sp_e($officialPhotoPool)?>" rel="noreferrer">медиатеки JAZ Makadi Saraya</a> (отель №20). Снимки подписаны по категориям самим JAZ, но не подтверждают совпадение с конкретным номером или стоимостью тура. Фотографии © Jaz Hotel Group.</p>
                <p>Это JAZ Makadi Saraya Resort, ранее Iberotel Makadi Saraya Resort. Saraya Palms и Makadina — другие отели. Фотографии территории и интерьеров взяты из галереи этого отеля в каталоге Tourvisor; категории номеров и даты съёмки не указаны. Галерея проверена 16 ноября 2026 года.</p>
                <p>Интурист описывает эти категории с видом на сад или бассейн и публикует максимум «4+1» для One-Bedroom Apartment и «5+1» для Two-Bedroom Apartment. Возрастные условия и все дополнительные места не раскрыты; пятое спальное место отдельно не описано. Закрывающиеся внутренние двери не подтверждены. Названия категорий у разных операторов не устанавливают точного соответствия номеру в туре.</p>
                <p>Площади и названия категорий приведены по Интуристу. Питание, включённые услуги и допустимый состав гостей определяются условиями выбранного предложения. Описание категории не подтверждает её наличие на ваши даты.</p>
                <p>Расстояние до пляжа в источниках различается: Интурист — 300 м, Corendon — около 500 м. Точный маршрут не измерен.</p>
                <ul>
                    <li><a href="<?=sp_e($factsheetUrl)?>" rel="noreferrer">Официальный factsheet JAZ</a> — категории апартаментов, обеденный стол и микроволновая печь; бассейны (стр. 4), детский клуб (стр. 5), рестораны и шведский стол в Sunflower (стр. 7). Прочитан 19 октября и повторно 16 ноября 2026 года. Работа услуг на даты поездки отдельно не проверена.</li>
                    <li><a href="<?=sp_e($intouristUrl)?>" rel="noreferrer">Карточка Интуриста</a> — площади, планировки, опубликованная вместимость и пляж. Прочитана 19 октября 2026 года.</li>
                    <li><a href="<?=sp_e($corendonUrl)?>" rel="noreferrer">Карточка Corendon</a> — корпуса, лифты и другая оценка расстояния до пляжа. Прочитана 20 октября; раздел о корпусах — отдельно 25 октября 2026 года.</li>
                </ul>
                <p class="jaz-caption">Даты публикации материалов не указаны. Дата чтения не означает обновление самих сведений.</p>
            </div>
        </details>
    </section>
</main>
<?php sp_end($context);

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-page-shell-v1.php';

/*
 * SITE1719 publication-backed page. Source: MAX publication70/version82.
 * Approved copy/photo: max-search-bot ed2b80348861fe1ff313bd8944c18330596ea036,
 * .github/traffic-publish/hotel-six-20261026/manifest.json and rixos.png.
 * This page creates no offer, price, attribution, enquiry or conversion.
 */
$context = sp_context(
    '/rixos-premium-magawish/',
    'Rixos Premium Magawish Suites & Villas — туры с перелётом | AnyTour',
    'Rixos Premium Magawish в Хургаде: просторные сьюты и виллы, песчаный пляж и ультра всё включено. Подберите тур с перелётом на свои даты в AnyTour.'
);
$context['robots'] = v2_seo_robots_content(false);
header('X-Robots-Tag: noindex, nofollow');
$entryUrl = 'https://max.ru/id9704048781_2_bot?startapp=post1_70';
$postUrl = 'https://max.ru/channel_anytour/AaF5iOyMXHU';
$botUrl = 'https://max.ru/id9704048781_1_bot?start=p_4tf3o3YPBWed2GVg36LMWg8E';
$photoUrl = 'https://raw.githubusercontent.com/pyatkoff/max-search-bot/ed2b80348861fe1ff313bd8944c18330596ea036/.github/traffic-publish/hotel-six-20261026/rixos.png';

sp_head($context);
sp_header($context);
sp_breadcrumbs([
    ['label' => 'AnyTour', 'href' => '/'],
    ['label' => 'Rixos Premium Magawish Suites & Villas'],
]);
sp_hero(
    'Египет · Хургада',
    'Rixos Premium Magawish Suites & Villas',
    'Просторные сьюты и виллы, песчаный пляж и «ультра всё включено» — для отдыха семьёй или вдвоём.'
);
?>
<main class="sp-main">
    <section class="sp-card">
        <img src="<?=sp_e($photoUrl)?>" width="600" height="600"
             alt="Rixos Premium Magawish Suites &amp; Villas — фотография отеля в оформлении AnyTour"
             fetchpriority="high" decoding="async"
             style="display:block;width:100%;max-width:600px;height:auto;margin:0 auto;border-radius:var(--at-radius-md)">
        <div class="sp-actions">
            <a class="sp-primary" href="<?=sp_e($entryUrl)?>">Посмотреть предложение в MAX</a>
            <a class="sp-secondary" href="<?=sp_e($postUrl)?>">Открыть пост об отеле</a>
        </div>
    </section>
    <div class="sp-grid">
        <section class="sp-card">
            <h2>Собственный песчаный пляж</h2>
            <p>Первая линия и пляж протяжённостью около километра.</p>
        </section>
        <section class="sp-card">
            <h2>Сьюты и виллы</h2>
            <p>Выбор размещения для отдыха семьёй или вдвоём.</p>
        </section>
        <section class="sp-card">
            <h2>«Ультра всё включено»</h2>
            <p>Живая музыка и вечерние шоу.</p>
        </section>
    </div>
    <section class="sp-card">
        <h2>Подобрать тур на ваши даты</h2>
        <p>Напишите даты поездки, город вылета и состав гостей в MAX — подберём тур в Rixos Premium Magawish с перелётом.</p>
        <div class="sp-actions">
            <a class="sp-primary" href="<?=sp_e($botUrl)?>">Рассчитать на мои даты</a>
        </div>
    </section>
</main>
<?php sp_end($context);

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/site-page-shell-v1.php';
require_once dirname(__DIR__) . '/seo-page-primitives-v1.php';

/*
 * SITE1719: bounded published hotel reference, not a current tour offer.
 * Source: max-search-bot1170, commit0fd3aa2d8a4c5ea53fb15c06cfeaa0ace633313a,
 * knowledge/hotels/anytour-99-apartments.json, SHA256934f2c3b98b2b509ab845feeb6bb0e9d50c570906ac29fb515da8a04f0ae8bdb.
 * Accepted identity: AnyTour99/rev1 -> local-tv268/rev3, country1.
 * Hotel/category/source limitations remain conditional; no price or room-code mapping.
 */
$context = sp_context(
    '/jaz-makadi-saraya/',
    'JAZ Makadi Saraya — номера и апартаменты в Макади-Бей | AnyTour',
    'JAZ Makadi Saraya: апартаменты с одной или двумя спальнями, гостиной и кухней. Сравните номера, узнайте особенности пляжа и подберите тур на свои даты.'
);
$context['robots'] = v2_seo_robots_content(false);
header('X-Robots-Tag: noindex, nofollow');
// Existing search catalogue uses local-tv268, not the canonical AnyTour99 ID.
// Country1/region5/selected hotel268 were read from the existing local catalogue.
$searchUrl = v2_seo_search_handoff_url('/poisk-turov/', [
    'country' => 1,
    'region' => 5,
    'hotel' => 268,
]);
$photoUrl = 'https://static.tourvisor.ru/hotel_pics/verybig/1/iberotel-makadi-saraya-resort-19573305.jpg';
$factsheetUrl = 'https://partner.jazhotels.com/Images/uploads/gallery/Downloads/JazMakadiSaraya/JAZ%20Makadi%20Saraya%20-Factsheet.pdf.pdf';
$intouristUrl = 'https://intourist.ru/info/egypt/hurgada/makadi-bej/hotel/jaz-makadi-saraya';
$corendonUrl = 'https://www.corendon.nl/egypte/rode-zee/hurghada/makadi-bay/jaz-makadi-saraya-resort';

sp_head($context);
sp_header($context);
sp_breadcrumbs([
    ['label' => 'AnyTour', 'href' => '/'],
    ['label' => 'Египет', 'href' => '/country/egypt/'],
    ['label' => 'JAZ Makadi Saraya'],
]);
?>
<main class="sp-main sp-seo-editorial-page" style="display:grid;gap:var(--at-space-6)" data-anytour-hotel-id="99" data-local-tv-hotel-id="268">
    <section class="sp-country-intent sp-country-intent--hero" aria-labelledby="hotel-title">
        <div class="sp-country-intent__copy">
            <span class="sp-country-intent__eyebrow">Египет · Макади-Бей</span>
            <h1 id="hotel-title">JAZ Makadi Saraya</h1>
            <p>Апартаменты с одной или двумя спальнями, гостиной и кухней — если в поездке вам важны пространство и отдельные зоны для отдыха.</p>
            <ul class="sp-country-intent__signals" aria-label="Главное об апартаментах">
                <li>1 или 2 спальни</li>
                <li>70 и 100 м²</li>
                <li>Гостиная и кухня</li>
            </ul>
            <div class="sp-actions">
                <a class="sp-primary" href="<?=sp_e($searchUrl)?>">Найти тур в этот отель</a>
                <a class="sp-secondary" href="#rooms">Сравнить номера</a>
            </div>
            <p style="font-size:13px">Даты, стоимость и доступные категории — в поиске туров.</p>
        </div>
        <figure style="margin:0;align-self:center;min-width:0">
            <img src="<?=sp_e($photoUrl)?>" width="1024" height="554"
                 alt="Территория JAZ Makadi Saraya: бассейн и невысокие корпуса"
                 fetchpriority="high" decoding="async"
                 style="display:block;width:100%;height:auto;border-radius:var(--at-radius-md)">
            <figcaption style="margin-top:10px;color:var(--at-muted);font-size:12px;line-height:1.5">Общий вид территории отеля</figcaption>
        </figure>
    </section>

    <section aria-labelledby="why-title">
        <div class="sp-section-head">
            <h2 id="why-title">Кому стоит присмотреться</h2>
            <p>Тем, кто выбирает отель по планировке: хочет отдельную спальню, больше места для отдыха и кухню в апартаментах.</p>
        </div>
        <div class="sp-grid sp-grid--balanced-three">
            <article class="sp-card">
                <h3>Больше пространства</h3>
                <p>Апартаменты площадью 70 или 100 м² с гостиной и обеденной зоной.</p>
            </article>
            <article class="sp-card">
                <h3>Выбор числа спален</h3>
                <p>Сравните варианты с одной и двумя спальнями под привычный вам формат отдыха.</p>
            </article>
            <article class="sp-card">
                <h3>Кухня в апартаментах</h3>
                <p>В описании есть кухня и обеденная зона, а официальный factsheet указывает стол и микроволновую печь.</p>
            </article>
        </div>
    </section>

    <section class="sp-card" id="rooms" aria-labelledby="rooms-title">
        <div class="sp-section-head">
            <h2 id="rooms-title">Какой номер выбрать</h2>
            <p>Начните с площади и количества спален. Ниже — категории из описания Интуриста, с видом на сад или бассейн.</p>
        </div>
        <div class="sp-offer-list">
            <article class="sp-offer-item">
                <h3>С одной спальней</h3>
                <p>1 Bedroom Apartment <strong>70 м²</strong></p>
                <p>Двуспальная либо две односпальные кровати, гостиная, кухня и обеденная зона.</p>
                <p><b>Если достаточно одной спальни</b> и хочется отдельного места для отдыха днём.</p>
                <p>Состав гостей и дополнительные спальные места проверьте в выбранном туре.</p>
            </article>
            <article class="sp-offer-item">
                <h3>С двумя спальнями</h3>
                <p>2 Bedroom Apartment <strong>100 м²</strong></p>
                <p>Двуспальная и две односпальные кровати, гостиная, кухня и обеденная зона.</p>
                <p><b>Если нужны две спальни</b> и больше пространства для вашей компании.</p>
                <p>Для пятого гостя отдельно проверьте спальное место и допустимый возраст.</p>
            </article>
            <article class="sp-offer-item">
                <h3>Family Deluxe</h3>
                <p>Номер с видом на сад или бассейн <strong>55 м²</strong></p>
                <p>В описании — варианты размещения на четверых: «2+2» либо «3+1».</p>
                <p><b>Если выбираете размещение на четверых.</b> Возраст детей проверяется по условиям тура.</p>
                <p>Для пяти гостей эта категория не подтверждена.</p>
            </article>
        </div>
        <p style="margin-top:20px;font-size:14px">Для апартаментов уточните все спальные места и наличие закрывающихся дверей между комнатами. Точную категорию, состав гостей и питание смотрите в условиях конкретного предложения.</p>
    </section>

    <section id="location" aria-labelledby="location-title">
        <div class="sp-section-head">
            <h2 id="location-title">Пляж и территория</h2>
            <p>Два условия, которые стоит учесть перед выбором номера.</p>
        </div>
        <div class="sp-grid sp-grid--balanced-two">
            <article class="sp-card">
                <h3>До пляжа нужно пройти</h3>
                <p>Интурист указывает вторую береговую линию и 300 м до пляжа; Corendon — около 500 м. Точный маршрут не измерен.</p>
            </article>
            <article class="sp-card">
                <h3>Невысокие корпуса без лифтов</h3>
                <p>По описанию Corendon, в отеле 13 корпусов до трёх этажей. Если важен доступ без лестниц, заранее уточните этаж номера и маршрут к нему.</p>
            </article>
        </div>
    </section>

    <section class="sp-card sp-search-callout">
        <h2>Теперь — тур на ваши даты</h2>
        <p>JAZ Makadi Saraya уже будет выбран в поиске. Укажите город вылета, даты и состав гостей, чтобы проверить доступные предложения и полную стоимость.</p>
        <div class="sp-actions">
            <a class="sp-primary" href="<?=sp_e($searchUrl)?>">Подобрать тур в JAZ Makadi Saraya</a>
        </div>
    </section>

    <section class="sp-card sp-card--supporting" aria-label="Подробные сведения и источники">
        <details>
            <summary style="min-height:44px;padding:10px 0;color:var(--at-brand);font-size:18px;font-weight:800;cursor:pointer">Источники и подробности перед бронированием</summary>
            <div style="padding-top:16px">
                <p>Страница посвящена JAZ Makadi Saraya Resort в Макади-Бей. Saraya Palms, Makadina и другие отели JAZ — отдельные объекты. Фотография показывает общий вид территории и не определяет категорию номера.</p>
                <p><b>Вместимость и названия категорий.</b> Для One-Bedroom Apartment Интурист публикует максимум «4+1», для Two-Bedroom Apartment — «5+1». Возрастные границы и полная схема дополнительных мест не раскрыты; пятое спальное место в апартаментах с двумя спальнями не описано. Family Deluxe 55 м² (2+2 либо 3+1) и Family 45 м² у другого оператора — разные опубликованные категории. Их соответствие конкретному коду номера в туре не установлено. Наличие гостиной само по себе не подтверждает размещение пяти гостей.</p>
                <p><b>Условия поездки.</b> Закрывающиеся внутренние двери, конкретный этаж и доступный маршрут нужно проверить для выбранного номера. Первая береговая линия не подтверждена. Условия Water World, Dine Around и других услуг зависят от договора и тарифа. Опубликованные описания не подтверждают будущую работу услуг, питание по тарифу, наличие или стоимость тура.</p>
                <p><a href="<?=sp_e($factsheetUrl)?>" rel="noreferrer">Официальный factsheet JAZ</a> — категории апартаментов, стол и микроволновая печь; прочитан 19 октября 2026 года. Внутренние двери и возрастная сетка в описании не установлены.</p>
                <p><a href="<?=sp_e($intouristUrl)?>" rel="noreferrer">Карточка Интуриста</a> — площади, планировки, опубликованная вместимость и пляж; прочитана 19 октября 2026 года.</p>
                <p><a href="<?=sp_e($corendonUrl)?>" rel="noreferrer">Карточка Corendon</a> — корпуса, лифты и другая оценка расстояния до пляжа; прочитана 20 октября, раздел о корпусах — отдельно 25 октября 2026 года.</p>
                <p>Даты публикации материалов не указаны. Дата чтения не означает обновление самих сведений.</p>
            </div>
        </details>
    </section>
</main>
<?php sp_end($context);

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
    'JAZ Makadi Saraya — апартаменты, пляж и условия размещения | AnyTour',
    'JAZ Makadi Saraya в Макади-Бей: опубликованные категории апартаментов, вместимость, путь к пляжу и отсутствие лифтов. Что проверить при выборе тура.'
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
sp_hero(
    'Египет · Макади-Бей',
    'JAZ Makadi Saraya',
    'Апартаменты с одной или двумя спальнями. Перед выбором сравните точную категорию номера, размещение гостей и путь к пляжу.',
    $searchUrl,
    'Подобрать тур в этот отель'
);
?>
<main class="sp-main sp-seo-editorial-page" data-anytour-hotel-id="99" data-local-tv-hotel-id="268">
    <figure class="sp-card">
        <img src="<?=sp_e($photoUrl)?>" width="1024" height="554"
             alt="Общий вид территории JAZ Makadi Saraya с бассейном и невысокими корпусами"
             fetchpriority="high" decoding="async"
             style="display:block;width:100%;height:auto;border-radius:var(--at-radius-md)">
        <figcaption>Общий вид отеля из гостиничного каталога. Фотография не определяет выбранную категорию номера.</figcaption>
    </figure>
    <section class="sp-card sp-card--supporting">
        <h2>Что полезно знать</h2>
        <p>В официальном factsheet отдельно перечислены One-Bedroom Apartments и Two-Bedroom Apartments. Для апартаментов указаны обеденный стол и микроволновая печь. Тип внутренних дверей в этом описании не установлен.</p>
        <p>Отель находится в Макади-Бей. Не путайте Saraya Resort с Saraya Palms, Makadina и другими отелями JAZ.</p>
    </section>
    <div class="sp-grid">
        <section class="sp-card">
            <h2>1 Bedroom Apartment</h2>
            <p>У Интуриста Garden View и Pool View описаны как апартаменты площадью <strong>70 м²</strong>: двуспальная либо две односпальные кровати, гостиная, обеденная зона и кухня.</p>
            <p>Опубликован максимум «4+1». Возрастные границы и полная схема дополнительных спальных мест не раскрыты.</p>
        </section>
        <section class="sp-card">
            <h2>2 Bedroom Apartment</h2>
            <p>У Интуриста Garden View и Pool View — <strong>100 м²</strong>: двуспальная и две односпальные кровати, гостиная, обеденная зона и кухня.</p>
            <p>Указан максимум «5+1», но пятое спальное место и допуск гостей конкретного возраста не описаны.</p>
        </section>
        <section class="sp-card">
            <h2>Family Deluxe</h2>
            <p>Garden View и Pool View у Интуриста — <strong>55 м²</strong>, размещение 2+2 либо 3+1: опубликованная схема рассчитана на четырёх гостей.</p>
            <p>Наличие гостиной само по себе не подтверждает возможность разместить пять человек в одном таком номере.</p>
        </section>
    </div>
    <div class="sp-grid">
        <section class="sp-card">
            <h2>Пляж и расстояние</h2>
            <p>Интурист указывает вторую береговую линию и 300 м до пляжа. У Corendon опубликована другая оценка — около 500 м.</p>
            <p>Разница источников остаётся открытой: маршрут не измерен, расположение на первой линии не подтверждено.</p>
        </section>
        <section class="sp-card">
            <h2>Корпуса и доступность</h2>
            <p>По описанию Corendon — <strong>13 корпусов высотой до трёх этажей, без лифтов</strong>.</p>
            <p>Если важен доступ без лестниц, отдельно проверьте этаж выделяемого номера и весь нужный маршрут. Отсутствие лифта не описывает доступность каждого номера.</p>
        </section>
    </div>
    <section class="sp-card">
        <h2>Что уточнить для вашей поездки</h2>
        <p>One-/Two-Bedroom Apartments, Family Deluxe 55 м² и Family 45 м² у другого оператора — разные опубликованные категории. Их соответствие конкретному коду номера в туре не установлено.</p>
        <p>Формулы «4+1» и «5+1» не заменяют проверку возраста детей, всех спальных мест и внутренних дверей. Условия Water World, Dine Around и других продуктов зависят от выбранного договора и тарифа.</p>
        <p>Здесь приведены опубликованные сведения об отеле. Они не подтверждают работу услуг на будущие даты, питание по выбранному тарифу, стоимость или наличие тура.</p>
    </section>
    <section class="sp-card sp-card--supporting">
        <h2>Источники и актуальность</h2>
        <p><a href="<?=sp_e($factsheetUrl)?>" rel="noreferrer">Официальный factsheet JAZ</a> — перечень категорий апартаментов; текст прочитан 19 октября 2026 года. Внутренние двери и возрастная сетка в описании не установлены.</p>
        <p><a href="<?=sp_e($intouristUrl)?>" rel="noreferrer">Карточка Интуриста</a> — площади, опубликованная вместимость и пляж; прочитана 19 октября 2026 года.</p>
        <p><a href="<?=sp_e($corendonUrl)?>" rel="noreferrer">Карточка Corendon</a> — корпуса, лифты и другая оценка расстояния; исходное чтение 20 октября, раздел о корпусах прочитан отдельно 25 октября 2026 года.</p>
        <p>Даты публикации материалов не указаны. Дата чтения не означает обновление самих сведений.</p>
    </section>
    <section class="sp-card sp-search-callout">
        <h2>Подобрать тур в JAZ Makadi Saraya</h2>
        <p>Перейдите к поиску с выбранным отелем. Укажите город вылета, даты и состав гостей; номер, питание, полную стоимость и доступность проверьте в конкретном предложении.</p>
        <div class="sp-actions">
            <a class="sp-primary" href="<?=sp_e($searchUrl)?>">Найти туры в этот отель</a>
        </div>
    </section>
</main>
<?php sp_end($context);

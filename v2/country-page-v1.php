<?php
require_once __DIR__ . '/site-page-shell-v1.php';

function cp_related_destinations(string $slug): array
{
    $catalog = [
        'turkey' => ['Турция', '/country/turkey/', 'Курорты Средиземного моря и большой выбор отелей'],
        'egypt' => ['Египет', '/country/egypt/', 'Красное море и круглогодичный пляжный отдых'],
        'oae' => ['ОАЭ', '/country/oae/', 'Городской комфорт, пляжи и высокий уровень сервиса'],
        'tailand' => ['Таиланд', '/country/tailand/', 'Тропические курорты, острова и насыщенный отдых'],
        'russia' => ['Россия', '/country/russia/', 'Море, курорты и поездки без международного перелёта'],
        'tunis' => ['Тунис', '/country/tunis/', 'Средиземное море, пляжи и спокойные курортные зоны'],
        'vetnam' => ['Вьетнам', '/country/vetnam/', 'Тропическое побережье и разнообразные курорты'],
        'dominikana' => ['Доминикана', '/country/dominikana/', 'Карибские пляжи и курортный формат all inclusive'],
        'cyprus' => ['Кипр', '/country/cyprus/', 'Средиземноморские курорты и компактные расстояния'],
        'cuba' => ['Куба', '/country/cuba/', 'Карибское море, пляжи и яркая островная атмосфера'],
        'maldives' => ['Мальдивы', '/country/maldives/', 'Островной отдых, лагуны и приватные курорты'],
        'mexico' => ['Мексика', '/country/mexico/', 'Карибское побережье и насыщенная экскурсионная программа'],
        'sri-lanka' => ['Шри-Ланка', '/country/sri-lanka/', 'Океан, природа и сочетание пляжей с поездками по острову'],
        'tanzania' => ['Танзания', '/country/tanzania/', 'Занзибар, океан и экзотический пляжный отдых'],
    ];
    $related = [
        'turkey' => ['egypt','tunis','cyprus'], 'egypt' => ['turkey','oae','tunis'], 'oae' => ['egypt','maldives','tanzania'],
        'tailand' => ['vetnam','sri-lanka','maldives'], 'russia' => ['turkey','cyprus','tunis'], 'tunis' => ['turkey','egypt','cyprus'],
        'vetnam' => ['tailand','sri-lanka','maldives'], 'dominikana' => ['cuba','mexico','maldives'], 'cyprus' => ['turkey','tunis','egypt'],
        'cuba' => ['dominikana','mexico','tanzania'], 'maldives' => ['sri-lanka','tanzania','oae'], 'mexico' => ['dominikana','cuba','tanzania'],
        'sri-lanka' => ['maldives','tailand','vetnam'], 'tanzania' => ['maldives','oae','sri-lanka'],
    ];
    $items = [];
    foreach ($related[$slug] ?? [] as $key) if (isset($catalog[$key])) $items[] = $catalog[$key];
    return $items;
}

function cp_render(array $page): void
{
    $slug = trim((string)($page['slug'] ?? ''), '/');
    $name = trim((string)($page['name'] ?? 'Направление'));
    $title = trim((string)($page['title'] ?? ('Туры в ' . $name . ' — AnyTour')));
    $description = trim((string)($page['description'] ?? ('Туры в ' . $name . ': подбор актуальных предложений AnyTour.')));
    $h1 = trim((string)($page['h1'] ?? ('Туры в ' . $name)));
    $intro = trim((string)($page['intro'] ?? 'Сравните актуальные предложения и проверьте конкретный тур перед заявкой.'));
    $resorts = array_values(array_filter((array)($page['resorts'] ?? [])));
    $facts = array_values(array_filter((array)($page['facts'] ?? [])));
    $editorialSections = array_values(array_filter((array)($page['editorialSections'] ?? []), 'is_array'));
    $relatedDestinations = cp_related_destinations($slug);
    $countryId = isset($page['countryId']) ? (int)$page['countryId'] : 0;
    $searchHref = '/poisk-turov/' . ($countryId > 0 ? '?country=' . $countryId : '');
    $searchLabel = $countryId > 0 ? ('Найти туры в ' . $name) : 'Открыть поиск туров';
    $c = sp_context('/country/' . $slug . '/', $title, $description);
    sp_head($c); sp_header($c);
    sp_breadcrumbs([['label'=>'Главная','href'=>'/'],['label'=>'Страны','href'=>'/country/'],['label'=>$h1]]);
    ?>
    <main class="sp-main sp-country-page">
      <section class="sp-country-intent sp-country-intent--hero" aria-labelledby="country-intent-title">
        <div class="sp-country-intent__copy"><span class="sp-country-intent__eyebrow">AnyTour · направление</span><h1 id="country-intent-title"><?=sp_e($h1)?></h1><p><?=sp_e($intro)?></p><ul class="sp-country-intent__signals" aria-label="Что можно проверить в поиске"><li>Актуальные предложения</li><li>Перелёт и багаж, когда доступны</li><li>Цена перед заявкой</li></ul><div class="sp-actions"><a class="sp-primary" href="<?=sp_e($searchHref)?>"><?=sp_e($searchLabel)?></a><a class="sp-secondary" href="/contacts/">Помощь менеджера</a></div></div>
        <div class="sp-country-intent__visual" data-country-visual-slot aria-label="Популярные курорты направления"><div class="sp-country-intent__visual-label">Направление AnyTour</div><?php if ($resorts): ?><div class="sp-country-intent__resorts"><span class="sp-country-intent__label">Популярные курорты</span><div class="sp-resort-list"><?php foreach ($resorts as $resort): ?><span class="sp-resort-chip"><?=sp_e($resort)?></span><?php endforeach; ?></div></div><?php else: ?><div class="sp-country-intent__resorts"><span class="sp-country-intent__label">Подбор по всей стране</span><p>Выберите даты и параметры поездки — актуальные курорты и отели появятся в поиске.</p></div><?php endif; ?></div>
      </section>
      <?php if ($facts): ?><section aria-labelledby="country-guide-title"><div class="sp-section-head"><h2 id="country-guide-title">Что важно при выборе</h2><p>Короткие ориентиры перед тем, как сравнивать отели, даты и конкретные варианты тура.</p></div><div class="sp-grid sp-grid--balanced-three"><?php foreach ($facts as $fact): ?><article class="sp-card"><h3><?=sp_e((string)($fact['title'] ?? 'Важно знать'))?></h3><p><?=sp_e((string)($fact['text'] ?? ''))?></p></article><?php endforeach; ?></div></section><?php endif; ?>
      <?php foreach ($editorialSections as $section): $sectionTitle=trim((string)($section['title']??'')); $paragraphs=array_values(array_filter(array_map(fn($p)=>trim((string)$p),(array)($section['paragraphs']??[])))); if($sectionTitle===''||!$paragraphs) continue; ?><section class="sp-card"><h2><?=sp_e($sectionTitle)?></h2><?php foreach($paragraphs as $paragraph): ?><p><?=sp_e($paragraph)?></p><?php endforeach; ?></section><?php endforeach; ?>
      <?php if ($relatedDestinations): ?><section aria-labelledby="country-related-title" data-related-destinations><div class="sp-section-head"><h2 id="country-related-title">Сравните похожие направления</h2><p>Если даты или формат отдыха ещё не окончательные, посмотрите несколько альтернатив и затем сравните живые предложения в общем поиске.</p></div><div class="sp-country-grid sp-country-grid--related"><?php foreach ($relatedDestinations as [$relatedName,$relatedHref,$relatedNote]): ?><a class="sp-country" href="<?=sp_e($relatedHref)?>"><span><?=sp_e($relatedName)?></span><small><?=sp_e($relatedNote)?></small><span class="sp-country-action">Открыть направление</span></a><?php endforeach; ?></div></section><?php endif; ?>
    </main>
    <?php sp_end($c);
}

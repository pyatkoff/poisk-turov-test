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
    ];
    $related = [
        'turkey' => ['egypt','oae','tailand'],
        'egypt' => ['turkey','oae','tailand'],
        'oae' => ['egypt','turkey','tailand'],
        'tailand' => ['oae','egypt','turkey'],
        'russia' => ['turkey','egypt','oae'],
    ];
    $items = [];
    foreach ($related[$slug] ?? [] as $key) {
        if (isset($catalog[$key])) $items[] = $catalog[$key];
    }
    return $items;
}

function cp_render(array $page): void
{
    $slug = trim((string)($page['slug'] ?? ''), '/');
    $name = trim((string)($page['name'] ?? 'Направление'));
    $title = trim((string)($page['title'] ?? ('Туры в ' . $name . ' — AnyTour')));
    $description = trim((string)($page['description'] ?? ('Туры в ' . $name . ': подбор актуальных предложений AnyTour.')));
    $intro = trim((string)($page['intro'] ?? 'Сравните актуальные предложения и проверьте конкретный тур перед заявкой.'));
    $resorts = array_values(array_filter((array)($page['resorts'] ?? [])));
    $facts = array_values(array_filter((array)($page['facts'] ?? [])));
    $relatedDestinations = cp_related_destinations($slug);
    $countryId = isset($page['countryId']) ? (int)$page['countryId'] : 0;
    $searchHref = '/poisk-turov/' . ($countryId > 0 ? '?country=' . $countryId : '');
    $searchLabel = $countryId > 0 ? ('Найти туры в ' . $name) : 'Открыть поиск туров';
    $c = sp_context('/country/' . $slug . '/', $title, $description);
    sp_head($c);
    sp_header($c);
    sp_breadcrumbs([
        ['label' => 'Главная', 'href' => '/'],
        ['label' => 'Страны', 'href' => '/country/'],
        ['label' => 'Туры в ' . $name],
    ]);
    sp_hero('AnyTour · ' . $name, 'Туры в ' . $name, $intro);
    ?>
    <main class="sp-main">
      <?php if ($facts): ?>
      <section aria-labelledby="country-guide-title">
        <div class="sp-section-head"><h2 id="country-guide-title">Что важно при выборе</h2><p>Короткие ориентиры перед тем, как перейти к актуальным ценам и доступным вариантам.</p></div>
        <div class="sp-grid sp-grid--balanced-three">
          <?php foreach ($facts as $fact): ?>
            <article class="sp-card"><h3><?=sp_e((string)($fact['title'] ?? 'Важно знать'))?></h3><p><?=sp_e((string)($fact['text'] ?? ''))?></p></article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <?php if ($resorts): ?>
      <section class="sp-card"><h2>Популярные курорты</h2><p>Используйте их как ориентир, а затем сравните доступные отели, даты и перелёты в общем поиске.</p><div class="sp-resort-list"><?php foreach ($resorts as $resort): ?><span class="sp-resort-chip"><?=sp_e($resort)?></span><?php endforeach; ?></div></section>
      <?php endif; ?>
      <?php if ($relatedDestinations): ?>
      <section aria-labelledby="country-related-title" data-related-destinations>
        <div class="sp-section-head"><h2 id="country-related-title">Сравните похожие направления</h2><p>Если даты или формат отдыха ещё не окончательные, посмотрите несколько альтернатив и затем сравните живые предложения в общем поиске.</p></div>
        <div class="sp-country-grid sp-country-grid--related">
          <?php foreach ($relatedDestinations as [$relatedName,$relatedHref,$relatedNote]): ?>
            <a class="sp-country" href="<?=sp_e($relatedHref)?>"><span><?=sp_e($relatedName)?></span><small><?=sp_e($relatedNote)?></small></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <section class="sp-card sp-search-callout"><h2>Найдите актуальный тур</h2><p>Цены и доступность меняются, поэтому страница направления не подменяет живой поиск статичной витриной. Выберите даты и параметры — покажем актуальные варианты и дадим открыть конкретный тур перед заявкой.</p><div class="sp-actions"><a class="sp-primary" href="<?=sp_e($searchHref)?>"><?=sp_e($searchLabel)?></a><a class="sp-secondary" href="/contacts/">Помощь менеджера</a></div></section>
    </main>
    <?php
    sp_end($c);
}

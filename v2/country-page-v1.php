<?php
require_once __DIR__ . '/site-page-shell-v1.php';

function cp_render(array $page): void
{
    $slug = trim((string)($page['slug'] ?? ''), '/');
    $name = trim((string)($page['name'] ?? 'Направление'));
    $title = trim((string)($page['title'] ?? ('Туры в ' . $name . ' — AnyTour')));
    $description = trim((string)($page['description'] ?? ('Туры в ' . $name . ': подбор актуальных предложений AnyTour.')));
    $intro = trim((string)($page['intro'] ?? 'Сравните актуальные предложения и проверьте конкретный тур перед заявкой.'));
    $resorts = array_values(array_filter((array)($page['resorts'] ?? [])));
    $facts = array_values(array_filter((array)($page['facts'] ?? [])));
    $countryId = isset($page['countryId']) ? (int)$page['countryId'] : 0;
    $searchHref = '/poisk-turov/' . ($countryId > 0 ? '?country=' . $countryId : '');
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
        <div class="sp-grid">
          <?php foreach ($facts as $fact): ?>
            <article class="sp-card"><h3><?=sp_e((string)($fact['title'] ?? 'Важно знать'))?></h3><p><?=sp_e((string)($fact['text'] ?? ''))?></p></article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <?php if ($resorts): ?>
      <section class="sp-card"><h2>Популярные курорты</h2><p>Используйте их как ориентир, а затем сравните доступные отели, даты и перелёты в общем поиске.</p><div class="sp-resort-list"><?php foreach ($resorts as $resort): ?><span class="sp-resort-chip"><?=sp_e($resort)?></span><?php endforeach; ?></div></section>
      <?php endif; ?>
      <section class="sp-card sp-search-callout"><h2>Найдите актуальный тур</h2><p>Цены и доступность меняются, поэтому страница направления не подменяет живой поиск статичной витриной. Выберите даты и параметры — покажем актуальные варианты и дадим открыть конкретный тур перед заявкой.</p><div class="sp-actions"><a class="sp-primary" href="<?=sp_e($searchHref)?>">Найти туры в <?=sp_e($name)?></a><a class="sp-secondary" href="/contacts/">Помощь менеджера</a></div></section>
    </main>
    <?php
    sp_end($c);
}

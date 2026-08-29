<?php

declare(strict_types=1);

namespace AnyTour\Platform;

final class ResortHtmlRenderer
{
    /** @param array<string,mixed> $page */
    public function render(array $page): string
    {
        $resort = $page['entity'];
        $country = $page['country'];
        $seo = $page['seo'];
        $meta = $seo['metadata'];
        $resortData = is_array($resort['data'] ?? null) ? $resort['data'] : [];
        $countryData = is_array($country['data'] ?? null) ? $country['data'] : [];
        $resortAccusative = (string) ($resortData['name_accusative'] ?? $resort['name']);
        $resortPrepositional = (string) ($resortData['name_prepositional'] ?? $resort['name']);
        $countryPrepositional = (string) ($countryData['name_prepositional'] ?? $country['name']);
        $intro = 'Подберите отдых на курорте с актуальными предложениями туроператоров и удобным переходом в живой поиск AnyTour.';
        foreach ($page['blocks'] as $block) {
            if (($block['key'] ?? '') === 'hero' && trim((string) ($block['content']['intro'] ?? '')) !== '') {
                $intro = (string) $block['content']['intro'];
            }
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $canonical = 'https://anytoour.ru' . (string) $meta['canonical'];
        $countryUrl = '/country/' . rawurlencode((string) $country['slug']) . '/';
        $schema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string) $meta['h1'],
            'url' => $canonical,
            'breadcrumb' => [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://anytoour.ru/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => (string) $country['name'], 'item' => 'https://anytoour.ru' . $countryUrl],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $resort['name'], 'item' => $canonical],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return '<!doctype html><html lang="ru"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
            . '<title>' . $e($meta['title']) . '</title>'
            . '<meta name="description" content="' . $e($meta['description']) . '">'
            . '<meta name="robots" content="' . $e($seo['robots']) . '">'
            . '<link rel="canonical" href="' . $e($canonical) . '">'
            . '<script type="application/ld+json">' . $schema . '</script>'
            . '<style>' . $this->css() . '</style></head><body>'
            . '<header class="atp-header"><a class="atp-logo" href="/">AnyTour</a><nav><a href="/poisk-turov/">Поиск туров</a><a href="/country/">Страны</a><a href="/hot/">Горящие туры</a><a href="/contacts/">Контакты</a></nav><a class="atp-header-cta" href="' . $e($page['search_url']) . '">Найти тур</a></header>'
            . '<main><div class="atp-wrap atp-breadcrumbs"><a href="/">Главная</a><span>›</span><a href="' . $e($countryUrl) . '">' . $e($country['name']) . '</a><span>›</span><span>' . $e($resort['name']) . '</span></div>'
            . '<section class="atp-hero"><div class="atp-wrap"><div class="atp-kicker">Курорт · ' . $e($country['name']) . '</div><h1>' . $e($meta['h1']) . '</h1><p>' . $e($intro) . '</p><div class="atp-actions"><a class="atp-primary" href="' . $e($page['search_url']) . '">Смотреть туры в ' . $e($countryPrepositional) . '</a><a class="atp-secondary" href="' . $e($countryUrl) . '">Все курорты ' . $e($countryPrepositional) . '</a></div></div></section>'
            . '<section class="atp-section atp-wrap"><div class="atp-section-head"><div><div class="atp-kicker">Отдых на курорте</div><h2>Что важно знать об ' . $e($resortPrepositional) . '</h2></div><p>Сравнивайте варианты размещения, питание, даты и перелёты уже в поиске. Для начала поиска страна будет выбрана автоматически.</p></div><div class="atp-facts"><article><span>Направление</span><strong>' . $e($country['name']) . '</strong></article><article><span>Курорт</span><strong>' . $e($resort['name']) . '</strong></article><article><span>Поиск</span><strong>Актуальные цены</strong></article></div></section>'
            . '<section class="atp-section atp-wrap"><div class="atp-search-card"><div><div class="atp-kicker">Живые предложения</div><h2>Туры в ' . $e($resortAccusative) . '</h2><p>Цены и доступность меняются, поэтому окончательные варианты показываем в живом поиске, а не фиксируем на SEO-странице.</p></div><a class="atp-primary atp-nowrap" href="' . $e($page['search_url']) . '">Открыть поиск</a></div></section></main>'
            . '<footer class="atp-footer"><div><strong>AnyTour</strong><span>Поиск и подбор туров онлайн</span></div><div><a href="/contacts/">Контакты</a><a href="/how-to-buy/">Как купить</a></div></footer>'
            . '</body></html>';
    }

    private function css(): string
    {
        return ':root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f6f8fb}*{box-sizing:border-box}body{margin:0;background:#f6f8fb;color:#172033}a{color:inherit;text-decoration:none}.atp-wrap{width:min(1180px,calc(100% - 40px));margin:0 auto}.atp-header{height:76px;display:flex;align-items:center;gap:34px;padding:0 max(20px,calc((100% - 1180px)/2));background:#fff;border-bottom:1px solid #e8edf4;position:sticky;top:0;z-index:10}.atp-logo{font-size:25px;font-weight:850;letter-spacing:-1px}.atp-header nav{display:flex;gap:24px;font-size:14px;flex:1}.atp-header-cta,.atp-primary{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border-radius:14px;background:#172033;color:#fff;font-weight:750}.atp-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border-radius:14px;background:#fff;border:1px solid #dfe5ed;font-weight:700}.atp-nowrap{white-space:nowrap}.atp-breadcrumbs{display:flex;gap:9px;align-items:center;padding-top:22px;font-size:13px;color:#758196}.atp-breadcrumbs a:hover{color:#172033}.atp-hero{margin-top:20px;padding:70px 0 54px;background:linear-gradient(135deg,#fff 0%,#edf4ff 100%)}.atp-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:12px;font-weight:800;color:#67758b}.atp-hero h1{font-size:clamp(40px,7vw,72px);line-height:.98;letter-spacing:-.05em;margin:14px 0 20px;max-width:850px}.atp-hero p{font-size:19px;line-height:1.55;color:#526178;max-width:720px;margin:0}.atp-actions{display:flex;gap:12px;margin-top:30px}.atp-section{padding:58px 0}.atp-section-head{display:grid;grid-template-columns:1fr minmax(280px,420px);gap:40px;align-items:end;margin-bottom:24px}.atp-section h2{font-size:clamp(28px,4vw,44px);letter-spacing:-.04em;margin:8px 0 0}.atp-section-head p,.atp-search-card p{color:#657286;line-height:1.55;margin:0}.atp-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.atp-facts article{background:#fff;border:1px solid #e5eaf1;border-radius:20px;padding:22px;min-height:130px;display:flex;flex-direction:column;justify-content:flex-end;box-shadow:0 12px 35px rgba(33,48,74,.05)}.atp-facts span{font-size:12px;color:#7b8798;text-transform:uppercase;letter-spacing:.08em}.atp-facts strong{font-size:22px;margin-top:7px}.atp-search-card{background:#fff;border:1px solid #e5eaf1;border-radius:24px;padding:30px;display:flex;align-items:center;justify-content:space-between;gap:30px}.atp-search-card h2{margin:8px 0 8px;font-size:32px}.atp-footer{max-width:1180px;margin:22px auto 0;padding:30px 20px 46px;border-top:1px solid #dde4ec;display:flex;justify-content:space-between;color:#67758b}.atp-footer div{display:flex;gap:18px;align-items:center}.atp-footer strong{color:#172033}@media(max-width:800px){.atp-header{height:66px;padding:0 18px}.atp-header nav{display:none}.atp-header-cta{margin-left:auto;min-height:42px;padding:0 16px}.atp-wrap{width:min(100% - 28px,1180px)}.atp-breadcrumbs{padding-top:16px;white-space:nowrap;overflow:hidden}.atp-hero{margin-top:14px;padding:52px 0 42px}.atp-hero h1{font-size:46px}.atp-hero p{font-size:17px}.atp-actions{flex-direction:column}.atp-primary,.atp-secondary{width:100%}.atp-section{padding:42px 0}.atp-section-head{grid-template-columns:1fr;gap:14px}.atp-facts{grid-template-columns:1fr}.atp-search-card{align-items:stretch;flex-direction:column}.atp-footer{margin:10px 14px 0;flex-direction:column;gap:20px}.atp-footer div{flex-wrap:wrap}}@media(max-width:430px){.atp-logo{font-size:22px}.atp-header-cta{font-size:13px}.atp-hero h1{font-size:39px}.atp-facts article{min-height:105px;padding:17px}.atp-facts strong{font-size:19px}.atp-search-card{padding:22px}}';
    }
}

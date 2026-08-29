<?php

declare(strict_types=1);

namespace AnyTour\Platform;

final class CountryHtmlRenderer
{
    /** @param array<string,mixed> $page */
    public function render(array $page): string
    {
        $entity = $page['entity'];
        $seo = $page['seo'];
        $meta = $seo['metadata'];
        $resorts = $page['children']['resorts'] ?? [];
        $name = (string) $entity['name'];
        $data = is_array($entity['data'] ?? null) ? $entity['data'] : [];
        $nameAccusative = (string) ($data['name_accusative'] ?? $name);
        $namePrepositional = (string) ($data['name_prepositional'] ?? $name);
        $intro = 'Сравните актуальные туры, курорты и отели. Выберите подходящий вариант и продолжите в живом поиске AnyTour.';
        foreach ($page['blocks'] as $block) {
            if (($block['key'] ?? '') === 'hero' && trim((string) ($block['content']['intro'] ?? '')) !== '') {
                $intro = (string) $block['content']['intro'];
            }
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $resortCards = '';
        foreach ($resorts as $resort) {
            $resortCards .= '<article class="atp-resort"><span>Курорт</span><strong>' . $e($resort['name']) . '</strong></article>';
        }

        $canonical = 'https://anytoour.ru' . (string) $meta['canonical'];
        $schema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string) $meta['h1'],
            'url' => $canonical,
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'AnyTour', 'url' => 'https://anytoour.ru/'],
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
            . '<main><section class="atp-hero"><div class="atp-wrap"><div class="atp-kicker">AnyTour · направление</div><h1>' . $e($meta['h1']) . '</h1><p>' . $e($intro) . '</p><div class="atp-actions"><a class="atp-primary" href="' . $e($page['search_url']) . '">Найти туры в ' . $e($nameAccusative) . '</a><a class="atp-secondary" href="/contacts/">Помощь менеджера</a></div></div></section>'
            . '<section class="atp-section atp-wrap"><div class="atp-section-head"><div><div class="atp-kicker">Выберите курорт</div><h2>Популярные курорты ' . $e($namePrepositional) . '</h2></div><p>Сравните курорты по атмосфере и формату отдыха, а затем откройте актуальные туры уже с выбранным направлением.</p></div><div class="atp-grid">' . $resortCards . '</div></section>'
            . '<section class="atp-section atp-wrap"><div class="atp-search-card"><div><div class="atp-kicker">Актуальные предложения</div><h2>Цены проверяем в живом поиске</h2><p>Покажем свежие варианты на ваши даты и состав туристов, чтобы перед выбором видеть актуальную стоимость тура.</p></div><a class="atp-primary atp-search-cta" href="' . $e($page['search_url']) . '">Показать туры</a></div></section></main>'
            . '<footer class="atp-footer"><div><strong>AnyTour</strong><span>Поиск и подбор туров онлайн</span></div><div><a href="/contacts/">Контакты</a><a href="/how-to-buy/">Как купить</a></div></footer>'
            . '</body></html>';
    }

    private function css(): string
    {
        return ':root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f6f8fb}*{box-sizing:border-box}body{margin:0;background:#f6f8fb;color:#172033}a{color:inherit;text-decoration:none}.atp-wrap{width:min(1180px,calc(100% - 40px));margin:0 auto}.atp-header{height:76px;display:flex;align-items:center;gap:34px;padding:0 max(20px,calc((100% - 1180px)/2));background:#fff;border-bottom:1px solid #e8edf4;position:sticky;top:0;z-index:10}.atp-logo{font-size:25px;font-weight:850;letter-spacing:-1px}.atp-header nav{display:flex;gap:24px;font-size:14px;flex:1}.atp-header-cta,.atp-primary{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border-radius:14px;background:#172033;color:#fff;font-weight:750}.atp-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border-radius:14px;background:#fff;border:1px solid #dfe5ed;font-weight:700}.atp-hero{padding:70px 0 54px;background:linear-gradient(135deg,#fff 0%,#edf4ff 100%)}.atp-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:12px;font-weight:800;color:#67758b}.atp-hero h1{font-size:clamp(40px,7vw,74px);line-height:.98;letter-spacing:-.05em;margin:14px 0 20px;max-width:850px}.atp-hero p{font-size:19px;line-height:1.55;color:#526178;max-width:720px;margin:0}.atp-actions{display:flex;gap:12px;margin-top:30px}.atp-section{padding:58px 0}.atp-section-head{display:grid;grid-template-columns:1fr minmax(280px,420px);gap:40px;align-items:end;margin-bottom:24px}.atp-section h2{font-size:clamp(28px,4vw,44px);letter-spacing:-.04em;margin:8px 0 0}.atp-section-head p,.atp-search-card p{color:#657286;line-height:1.55;margin:0}.atp-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.atp-resort{background:#fff;border:1px solid #e5eaf1;border-radius:20px;padding:22px;min-height:140px;display:flex;flex-direction:column;justify-content:flex-end;box-shadow:0 12px 35px rgba(33,48,74,.05)}.atp-resort span{font-size:12px;color:#7b8798;text-transform:uppercase;letter-spacing:.08em}.atp-resort strong{font-size:22px;margin-top:7px}.atp-search-card{background:#fff;border:1px solid #e5eaf1;border-radius:24px;padding:30px;display:flex;align-items:center;justify-content:space-between;gap:30px}.atp-search-card h2{margin:8px 0 8px;font-size:32px}.atp-search-cta{min-width:180px;white-space:nowrap;flex:0 0 auto}.atp-footer{max-width:1180px;margin:22px auto 0;padding:30px 20px 46px;border-top:1px solid #dde4ec;display:flex;justify-content:space-between;color:#67758b}.atp-footer div{display:flex;gap:18px;align-items:center}.atp-footer strong{color:#172033}@media(max-width:800px){.atp-header{height:66px;padding:0 18px}.atp-header nav{display:none}.atp-header-cta{margin-left:auto;min-height:42px;padding:0 16px}.atp-wrap{width:min(100% - 28px,1180px)}.atp-hero{padding:52px 0 42px}.atp-hero h1{font-size:46px}.atp-hero p{font-size:17px}.atp-actions{flex-direction:column}.atp-primary,.atp-secondary{width:100%}.atp-section{padding:42px 0}.atp-section-head{grid-template-columns:1fr;gap:14px}.atp-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.atp-search-card{align-items:stretch;flex-direction:column}.atp-search-cta{min-width:0;white-space:normal}.atp-footer{margin:10px 14px 0;flex-direction:column;gap:20px}.atp-footer div{flex-wrap:wrap}}@media(max-width:430px){.atp-logo{font-size:22px}.atp-header-cta{font-size:13px}.atp-hero h1{font-size:39px}.atp-grid{grid-template-columns:1fr 1fr}.atp-resort{min-height:116px;padding:17px}.atp-resort strong{font-size:19px}.atp-search-card{padding:22px}}';
    }
}

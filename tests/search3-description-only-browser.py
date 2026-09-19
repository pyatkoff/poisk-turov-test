#!/usr/bin/env python3
"""Offline regression for native description-only disclosure; no external traffic."""
from pathlib import Path
import base64
import json
import mimetypes
import os
import subprocess
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
PAYLOAD = ROOT / 'v2'
OUT = Path(os.environ.get('SEARCH3_EVIDENCE_DIR', str(ROOT / 'canonical-card-evidence')))
OUT.mkdir(parents=True, exist_ok=True)
css_files = json.loads(subprocess.check_output([
    'php', '-r', 'require ' + json.dumps(str(PAYLOAD / 'bundle-manifest-v1.php'))
    + '; echo json_encode(v2_bundle_files("css", "search3"));',
], text=True))
css_files += ['search3-results-filters-v1.css', 'search3-entry-v1.css',
              'search3-results-cards-v2.css', 'search3-selected-flow-v2.css']
CSS = '\n'.join((PAYLOAD / name).read_text() for name in css_files)
CODE = '\n'.join((PAYLOAD / name).read_text() for name in [
    'search3-canonical-profiles-v1.js', 'results-renderer-v5.js',
    'search3-hotel-details-presentation-v1.js',
])
HTML = '''<!doctype html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><style>''' + CSS + '''</style>
</head><body class="search3-candidate"><main class="v2-shell">
<p>Компонентный тест · вымышленные данные</p><form id="tourSearch" hidden></form>
<section id="status" hidden></section><div class="results-layout">
<aside class="results-filter-rail"></aside><section id="results" class="results"></section>
</div><section id="selectedTour" hidden></section></main></body></html>'''
DESCRIPTION = ('Тестовое описание отеля без дополнительных характеристик. '
               + 'Вымышленные данные для проверки доступности полного текста. ' * 12
               + 'КОНЕЦ ПОЛНОГО ОПИСАНИЯ.')


def image(label):
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="480">'
           '<rect width="800" height="480" fill="#e5e7eb"/>'
           '<text x="25" y="240" font-size="30">Тестовое фото ' + label + '</text></svg>')
    return 'data:image/svg+xml;base64,' + base64.b64encode(svg.encode()).decode()


PHOTOS = {'https://fixture.invalid/photos/a.svg': image('A'),
          'https://fixture.invalid/photos/b.svg': image('B')}
PROFILE = {'id': 1, 'catalog': 'anytour', 'revision': 1,
           'name': 'Тестовый отель с подробным описанием', 'description': DESCRIPTION,
           'detailsAvailable': True, 'category': 5, 'primaryImage': next(iter(PHOTOS)),
           'images': list(PHOTOS), 'hotelInformation': {}}
HOTEL = {'id': '102', 'provider': 'tourvisor', 'name': 'НЕЛЬЗЯ: имя поставщика',
         'price': 125000, 'tours': [{'id': '731', 'provider': 'tourvisor', 'price': 125000,
         'date': '2026-10-05', 'nights': 7, 'meal': {'name': 'AI'}, 'roomType': 'Standard',
         'operator': {'name': 'Coral Travel'}}]}
LOGOS = {p.name: 'data:' + (mimetypes.guess_type(p.name)[0] or 'application/octet-stream')
         + ';base64,' + base64.b64encode(p.read_bytes()).decode()
         for p in (PAYLOAD / 'assets/operator-logos').iterdir() if p.suffix in ['.png', '.svg']}
REPORT = []

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path=os.environ.get('CHROMIUM_PATH') or None,
                                         headless=True, args=['--no-sandbox'])
    for width in [375, 768, 1440]:
        context = browser.new_context(viewport={'width': width, 'height': 1000},
                                      is_mobile=width <= 760, has_touch=width <= 760)
        def fixture_route(route):
            photo = PHOTOS.get(route.request.url)
            if photo:
                route.fulfill(status=200, content_type='image/svg+xml',
                              body=base64.b64decode(photo.split(',', 1)[1]))
            else:
                route.abort()
        context.route('**/*', fixture_route)
        page = context.new_page()
        page.set_default_timeout(3000)
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.set_content(HTML)
        page.evaluate('''({code, profile}) => {
          window.__catalogCalls = 0;
          window.fetch = async () => {
            window.__catalogCalls++;
            return {ok:true, json:async () => ({ok:true, source:'anytour-canonical-catalog',
              catalog:'anytour', requestedLegacyIds:['102'], items:[profile],
              links:[{legacyHotelId:102, anytourHotelId:1}], missingLegacyIds:[]})};
          };
          window.V2Runtime = {fetch:window.fetch};
          window.Search3LocalHotelFilter = {project:items => items};
          const location = new URL('https://fixture.invalid/_preview/search3-local-candidate/poisk-turov/');
          const fixtureWindow = new Proxy(window, {
            get(target, key) {if(key==='location') return location;
              const value=Reflect.get(target,key,target);
              return typeof value==='function' ? value.bind(target) : value;},
            set(target,key,value) {target[key]=value; return true;}
          });
          new Function('window', code)(fixtureWindow);
        }''', {'code': CODE, 'profile': PROFILE})
        page.evaluate('''hotel => {window.__hotel=hotel; window.__before=JSON.stringify(hotel);
                         V2Results.render([hotel]);}''', HOTEL)
        expect(page.locator('.hotel-card')).to_have_count(1)
        details = page.locator('.hotel-details')
        expect(details).to_have_count(1)
        page.evaluate('''logos => document.querySelectorAll('img.hotel-operator-logo').forEach(img => {
          const name=img.getAttribute('src').split('/').pop(); if(logos[name]) img.src=logos[name];
        })''', LOGOS)
        expect(details.locator('summary')).to_have_text('Подробнее об отеле')
        expect(details.locator('.hotel-description')).to_have_text(DESCRIPTION)
        expect(details.locator('.hotel-description')).not_to_be_visible()
        expect(details.locator('dl, section')).to_have_count(0)
        teaser = page.locator('.hotel-description-summary')
        thumbs = page.locator('.hotel-gallery-thumbs')
        if width <= 760:
            expect(teaser).not_to_be_visible()
            expect(thumbs).not_to_be_visible()
        else:
            expect(teaser).to_be_visible()
            assert teaser.evaluate('node => node.clientHeight < node.scrollHeight')
        photo_link = page.get_by_role('link', name='Фото отеля Тестовый отель с подробным описанием — открыть в новой вкладке', exact=True)
        expect(photo_link).to_have_count(1)
        expect(photo_link).to_have_attribute('href', PROFILE['primaryImage'])
        expect(photo_link).to_have_attribute('target', '_blank')
        expect(photo_link).to_have_attribute('rel', 'noopener noreferrer')
        image_box = page.locator('.hotel-gallery-main').bounding_box()
        link_box = photo_link.bounding_box()
        assert all(abs(image_box[key] - link_box[key]) <= 1 for key in ['x', 'y', 'width', 'height'])
        original_url = page.url
        with page.expect_popup() as opened:
            if width <= 760:
                photo_link.tap()
            else:
                photo_link.click()
        popup = opened.value
        popup.wait_for_load_state('domcontentloaded')
        assert popup.url == PROFILE['primaryImage']
        popup.close()
        assert page.url == original_url
        expect(details).not_to_have_attribute('open', '')
        page.screenshot(path=str(OUT / f'description-only-closed-{width}.png'), full_page=True)
        details.locator('summary').focus()
        page.keyboard.press('Enter')
        expect(details).to_have_attribute('open', '')
        expect(teaser).not_to_be_visible()
        full = details.locator('.hotel-description')
        expect(full).to_be_visible()
        assert full.evaluate('node => node.scrollHeight <= node.clientHeight + 1')
        expect(thumbs).to_be_visible()
        main = page.locator('.hotel-gallery-main')
        first_image = main.get_attribute('src')
        thumbs.locator('button').first.click()
        assert main.get_attribute('src') != first_image
        expect(photo_link).to_have_attribute('href', main.get_attribute('src'))
        photo_link.focus()
        with page.expect_popup() as opened:
            page.keyboard.press('Enter')
        popup = opened.value
        popup.wait_for_load_state('domcontentloaded')
        assert popup.url == main.get_attribute('src')
        popup.close()
        assert page.url == original_url
        assert page.evaluate('Search3HotelDetailsPresentationV1.normalize(document.getElementById("results"))') == 0
        expect(details).to_have_attribute('open', '')
        assert page.evaluate('JSON.stringify(__hotel)===__before')
        assert page.evaluate('__catalogCalls') == 1
        assert not page.evaluate('document.documentElement.scrollWidth > innerWidth + 1')
        assert not errors, errors
        page.screenshot(path=str(OUT / f'description-only-open-{width}.png'), full_page=True)
        details.locator('summary').focus()
        page.keyboard.press('Space')
        expect(details.locator('.hotel-description')).not_to_be_visible()
        if width <= 760:
            expect(teaser).not_to_be_visible()
            expect(thumbs).not_to_be_visible()
        else:
            expect(teaser).to_be_visible()
        REPORT.append({'width': width, 'native_disclosure': True, 'full_text': True,
                       'single_visible_description': True, 'gallery': True,
                       'native_photo_tab': True, 'current_thumbnail_photo': True,
                       'touch_photo': width <= 760, 'keyboard': True,
                       'no_extra_catalog_calls': True, 'overflow': False})
        context.close()
    browser.close()

(OUT / 'description-only-report.json').write_text(json.dumps(REPORT, ensure_ascii=False, indent=2) + '\n')
print('SEARCH3_DESCRIPTION_ONLY_BROWSER_OK ' + json.dumps(REPORT))

"""One-shot HC-1 source assembly. No server, suppliers, DB or ref writes."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import urllib.request

BASE = '2583c284b2631c1cdfa94df710f71f492fe3d38f'
PARENT = '2a9efb9bebaa662467ae4dcad1c84ce426fdbd04'
APP = '9a4cb4f812f0eb03056ae1a639887f00c29980b4'
GALLERY = 'c905a9b38e50c771a475768103afabe97e98abe0'
NEW_TEST = 'tests/search3-hotel-content-browser.py'
REPO = 'pyatkoff/poisk-turov-test'
OUT = Path(os.environ.get('HC1_ASSEMBLY_OUTPUT', '/tmp/hc1-consumer-source'))


def git(*args):
    return subprocess.check_output(['git', *args]).decode('utf-8').replace('\r\n', '\n')


def source(ref, path):
    return git('show', f'{ref}:{path}')


def replace(text, old, new):
    assert text.count(old) == 1, f'Non-unique/missing replacement: {old[:100]!r}'
    return text.replace(old, new, 1)


FACTS = r'''// Hotel content is inert text from the canonical profile, never offer conditions.
function hotelContentText(value){
 if(typeof value!=='string'&&typeof value!=='number')return '';
 const template=document.createElement('template');template.innerHTML=String(value);
 template.content.querySelectorAll('script,style,iframe,object,embed,svg,math,template').forEach(node=>node.remove());
 template.content.querySelectorAll('br').forEach(node=>node.replaceWith('\n'));
 template.content.querySelectorAll('p,div,li,ul,ol,tr,h1,h2,h3,h4,section').forEach(node=>node.append('\n'));
 return (template.content.textContent||'').split('\n').map(line=>line.replace(/\s+/g,' ').trim()).filter(Boolean).join('\n');
}
const plainHotelText=value=>hotelContentText(value).replace(/\s+/g,' ').trim();
const hotelTextHTML=value=>esc(hotelContentText(value)).replace(/\n/g,'<br>');
function hotelDescription(h){
 const text=hotelContentText(h.note);if(!text)return '';
 if(text.length<=460)return `<p class="hotel-detail-copy" data-hotel-description="full">${esc(text).replace(/\n/g,'<br>')}</p>`;
 const summary=text.slice(0,420).replace(/\s+\S*$/,'');
 return `<p class="hotel-detail-copy" data-hotel-description="summary">${esc(summary)}…</p><details class="search-response-details hotel-content-more"><summary>Полное описание</summary><p class="hotel-detail-copy" data-hotel-description="full">${esc(text).replace(/\n/g,'<br>')}</p></details>`;
}
function hotelHighlights(h){
 const priority=[3,1,5,8,2],facts=[...(h.amenities||[])].sort((a,b)=>priority.indexOf(a.groupId)-priority.indexOf(b.groupId));
 if(facts.length)return [...new Set(facts.map(a=>a.label))].slice(0,4).join(' · ');
 const place=plainHotelText(h.raw?.place);return place.length>160?place.slice(0,157).replace(/\s+\S*$/,'')+'…':place;
}
function hotelDetailFacts(h,full=false){
 const info=h.raw?.hotelInformation||{},services=info.services||h.raw?.services||{},infrastructure=info.infrastructure||h.raw?.infrastructure||{},meals=info.meals||h.raw?.meals||{};
 const sections=full?[
 ['address','Адрес',h.raw?.address],['build','Строительство',h.raw?.build],['repair','Реновация',h.raw?.repair],['square','Территория и площадь',h.raw?.square],
 ['services.available','Услуги и удобства',services.available],['services.animation','Развлечения',services.animation],['services.free','Бесплатные услуги',services.free],['services.servicesPay','Платные услуги',services.servicesPay],['services.inRoom','Общее оснащение номеров',services.inRoom],
 ['meals.description','Питание в отеле',typeof meals==='string'?meals:meals.description],['meals.list','Варианты питания в отеле',meals.list],['roomTypes','Описание номерного фонда',info.roomTypes||h.raw?.roomTypes]
 ]:[['place','Расположение',h.raw?.place],['infrastructure.beach','Пляж',infrastructure.beach],['infrastructure.territory','Территория',infrastructure.territory],['services.child','Для детей',services.child]];
 return sections.map(([key,label,value])=>hotelContentText(value)?`<section data-hotel-field="${key}"><h3 class="detail-section-title">${label}</h3><p class="hotel-detail-copy">${hotelTextHTML(value)}</p></section>`:'').join('');
}
'''
OPEN = r'''function openHotelDetails(id){
 const h=hotels.find(h=>h.id===id);if(!h)return;
 const offers=hotelOffers(h),photos=h.photos.slice(0,3),fullFacts=hotelDetailFacts(h,true);
 const photoBlock=photos.length?`<div class="hotel-detail-photos" data-photo-count="${photos.length}">${photos.map((p,i)=>`<button data-action="hotel-gallery" data-id="${h.id}" data-value="${i}" aria-label="Открыть фото ${i+1}"><img src="${esc(p)}" alt="Фото отеля ${esc(h.name)}" loading="lazy"></button>`).join('')}</div>`:'<p class="hotel-detail-copy">Фото пока нет</p>';
 const rating=Number.isFinite(h.rating)?`<span class="detail-rating">${ratingText(h)} / 5</span>`:'';
 const rooms=offers.length?`<h3 class="detail-section-title">Номера в найденных турах</h3><div class="room-options">${[...new Set(offers.map(o=>o.room))].map(room=>`<div><strong>${esc(room)}</strong></div>`).join('')}</div>`:'';
 const reference=fullFacts?`<details class="search-response-details hotel-content-more" data-hotel-reference><summary>Все сведения об отеле</summary><p class="hotel-detail-copy">Справочная информация об отеле. Номер и питание выбранного тура указаны в конкретном предложении.</p>${fullFacts}</details>`:'';
 showModal('hotel-details',h.name,`${esc(h.resort)} · ${h.stars?h.stars+' ★':'Категория не указана'}`,`${photoBlock}<div class="hotel-detail-heading"><h3>Об отеле</h3>${rating}</div>${hotelDescription(h)}${hotelDetailFacts(h)}${rooms}${reference}`,true);
 if(offers.length){$('#modal-footer').hidden=false;$('#modal-footer').innerHTML=`<div class="footer-total"><span>Туры за ${guestsText()}</span><strong>от ${money(offers[0].total)}</strong></div><button class="primary" data-action="all-offers" data-id="${id}">Выбрать тур ${icon('arrow')}</button>`;}
}
'''


def assemble():
    files = {p:source(PARENT,p) for p in [
        'v2/prototype-search/data.js','v2/prototype-search/styles.css',
        'v2/prototype-search/search-lifecycle-v1.js',
        'tests/search3-prototype-search-lifecycle-v1.cjs',
        'tests/search3-prototype-inventory-lifecycle.cjs',
        '.github/workflows/build-search3-whole-site-preview.yml']}
    changed=git('diff','--name-only','00a553e5a4c2ef34d06bf5303e4ec8e4af49dbe8',BASE).splitlines()
    assert changed==['v2/visual-search/index.php'], changed
    files['v2/prototype-search/app.js'] = source(APP,'v2/prototype-search/app.js')
    assert 'supplierAlreadyScopedResorts' in files['v2/prototype-search/app.js']
    manifest=json.loads(source(GALLERY,'patches/search3-hc1-full-gallery-v2.json'))
    for entry in manifest:
        for change in entry['replacements']:
            files[entry['path']]=replace(files[entry['path']],change['old'],change['new'])
    app=files['v2/prototype-search/app.js']
    a=app.index('const plainHotelText=');b=app.index('function updateFacetCounts()',a)
    app=app[:a]+FACTS+app[b:]
    a=app.index('function openHotelDetails(');b=app.index('function renderResults(',a)
    app=app[:a]+OPEN+app[b:]
    app=app.replace('Сохранённых предложений пока нет','Условия сохранены. Запустите новый поиск')
    files['v2/prototype-search/app.js']=app
    css=files['v2/prototype-search/styles.css']
    css=replace(css,'.hotel-detail-photos button:first-child{grid-row:1/3}',
        '.hotel-detail-photos button:first-child{grid-row:1/3}.hotel-detail-photos[data-photo-count="1"]{grid-template-columns:1fr}.hotel-detail-photos[data-photo-count="2"]{grid-template-columns:1fr 1fr}.hotel-detail-photos[data-photo-count="2"] button{grid-row:1/3}')
    css=replace(css,'.hotel-detail-copy{font-size:14px;color:var(--muted);margin-top:12px;line-height:1.8}',
        '.hotel-detail-copy{font-size:14px;color:var(--muted);margin-top:12px;line-height:1.8;overflow-wrap:anywhere}.hotel-content-more>summary{min-height:44px;display:flex;align-items:center}.hotel-content-more:not([open])>summary::before{content:"+";margin-right:8px}.hotel-content-more[open]>summary::before{content:"−";margin-right:8px}')
    files['v2/prototype-search/styles.css']=css
    test=source(GALLERY,'tests/search3-hc1-full-gallery-browser.py')
    test=replace(test,"'hc1-gallery-evidence'","'local-db-price-evidence/hotel-content'")
    test=test.replace('43','240').replace('(101,240),(102,1),(103,0)','(101,240),(102,1),(103,0),(104,2)')
    test=test.replace('value="42"','value="239"').replace('/photo-101-42.svg','/photo-101-239.svg')
    test=replace(test,"document.querySelectorAll('.hotel-card').length===3","document.querySelectorAll('.hotel-card').length===4")
    needle='    profiles[old] = p\n'
    test=replace(test,needle,needle+'''
profiles[101].update(address='Проверенный адрес &amp; корпус Б',build='2001',repair='2026',square='12000 м²',place='Рядом с набережной')
profiles[101]['hotelInformation'] = {
    'infrastructure': {'beach':'<ul><li>Песчаный пляж</li><li>Шезлонги</li></ul>', 'territory':'<p>Сад &amp; бассейн</p>'},
    'services': {'child':'Мини-клуб', 'animation':'Вечерняя программа', 'free':'Wi-Fi', 'servicesPay':'Спа за плату',
                 'available':'<p>Камера хранения</p><script>window.HC1_INJECTED=true</script>', 'inRoom':'Холодильник'},
    'meals': {'description':'Ресторан отеля', 'list':'<ul><li>Завтраки в отеле</li></ul>'},
    'roomTypes':'<p>Справочный SUPERIOR</p><p>Другой номер FAMILY</p>'}
profiles[103].update(description='',rating=None)
''')
    test=replace(test,"ids = list(map(int,q['legacyHotelIds[]']))",'''if 'anytourHotelId' in q:
                own=int(q['anytourHotelId'][0]); value=profiles[own+100]
                reply({'ok':True,'catalog':'anytour','source':'anytour-canonical-catalog','item':value});return
            ids = list(map(int,q['legacyHotelIds[]']))''')
    test=replace(test,"assert page.locator('.hotel-detail-copy').inner_text()==profiles[101]['description']",'''assert page.locator('[data-hotel-description="full"]').inner_text()==profiles[101]['description']
        assert 'Песчаный пляж' in page.locator('[data-hotel-field="infrastructure.beach"]').inner_text()
        assert 'Мини-клуб' in page.locator('[data-hotel-field="services.child"]').inner_text()
        page.locator('[data-hotel-reference] > summary').click()
        expected={'address':'Проверенный адрес & корпус Б','build':'2001','repair':'2026','square':'12000 м²',
                  'services.available':'Камера хранения','services.animation':'Вечерняя программа','services.free':'Wi-Fi',
                  'services.servicesPay':'Спа за плату','services.inRoom':'Холодильник','meals.description':'Ресторан отеля',
                  'meals.list':'Завтраки в отеле','roomTypes':'Справочный SUPERIOR'}
        for key,value in expected.items(): assert value in page.locator(f'[data-hotel-field="{key}"]').inner_text(), key
        assert 'FAMILY SEA VIEW' in page.locator('.room-options').inner_text()
        assert 'SUPERIOR' not in page.locator('.room-options').inner_text(), 'Hotel room descriptions replaced offer room'
        assert page.evaluate('window.HC1_INJECTED === undefined')
        assert page.locator('#modal script,#modal iframe,#modal img[onerror]').count()==0
        page.screenshot(path=str(OUT/f'hotel-facts-{width}.png'))
        checks['allHotelFactsAndOfferIsolation']=True''')
    test=replace(test,"checks['oneAndZeroPhotoHotelsRemainUsable']=True",'''checks['oneAndZeroPhotoHotelsRemainUsable']=True
        page.locator('#hotel-3 [data-action="hotel-details"]').click()
        assert page.locator('[data-hotel-description], [data-hotel-reference], .detail-rating').count()==0
        assert 'Описание пока не заполнено' not in page.locator('#modal-body').inner_text()
        page.locator('#modal [data-action="close-modal"]').click()
        for own in [2,4]:
            page.locator(f'#hotel-{own} [data-action="hotel-details"]').click()
            photo_geometry=page.locator('.hotel-detail-photos').evaluate("""node=>({width:node.clientWidth, children:[...node.children].map(c=>c.getBoundingClientRect().width)})""")
            assert sum(photo_geometry['children'])>=photo_geometry['width']-10, photo_geometry
            page.locator('#modal [data-action="close-modal"]').click()
        checks['oneTwoAndEmptyHotelDetails']=True
        profiles[101]['revision']+=1
        profiles[101]['description']='Новое описание. '+('Подробная информация об отеле. '*30)+'Последняя строка описания.'
        before_calls=len(api_calls)
        page.evaluate("""async () => {await Search3CanonicalProfilesV1.current().readProfile(1); Search3CanonicalProfilesV1.current().refresh();}""")
        card.locator('[data-action="hotel-details"]').click()
        assert page.locator('[data-hotel-description="summary"]').is_visible()
        page.locator('summary').filter(has_text='Полное описание').click()
        assert 'Последняя строка описания.' in page.locator('[data-hotel-description="full"]').inner_text()
        page.locator('#modal [data-action="close-modal"]').click()
        page.locator('#sort').select_option('rating')
        card.locator('[data-action="gallery"]').click()
        assert page.locator('.gallery-thumbs button').count()==240
        page.locator('#modal [data-action="close-modal"]').click()
        assert len(api_calls)==before_calls, 'Hotel content refresh started supplier/quote work'
        assert not errors and not forbidden
        checks['newProfileRevisionKeepsOffersAndGallery']=True''')
    test=replace(test,"    ctx = browser.new_context", "    profiles[101]['description']='Описание фиктивного отеля 101.'\n    ctx = browser.new_context")
    files[NEW_TEST]=test
    workflow=files['.github/workflows/build-search3-whole-site-preview.yml']
    workflow=replace(workflow,"      - 'tests/search3-prototype-inventory-browser.py'", "      - 'tests/search3-prototype-inventory-browser.py'\n      - 'tests/search3-hotel-content-browser.py'")
    workflow=replace(workflow,'tests/search3-prototype-inventory-browser.py; then','tests/search3-prototype-inventory-browser.py tests/search3-hotel-content-browser.py v2/prototype-search/styles.css; then')
    workflow=replace(workflow,'          python tests/search3-prototype-contact-recovery.py','          python tests/search3-prototype-contact-recovery.py\n          python tests/search3-hotel-content-browser.py')
    files['.github/workflows/build-search3-whole-site-preview.yml']=workflow
    p='tests/search3-prototype-inventory-browser.py'
    files[p]=replace(source(PARENT,p),'Сохранённых предложений пока нет','Условия сохранены. Запустите новый поиск')
    return files


def main():
    OUT.mkdir(parents=True,exist_ok=True)
    files=assemble()
    for path,text in files.items():
        target=Path(path);target.parent.mkdir(parents=True,exist_ok=True);target.write_text(text)
        output=OUT/'files'/path;output.parent.mkdir(parents=True,exist_ok=True);output.write_text(text)
    for path in ['v2/prototype-search/app.js','v2/prototype-search/data.js']:
        subprocess.run(['node','--check',path],check=True)
    subprocess.run(['python3','-m','py_compile',NEW_TEST],check=True)
    subprocess.run(['git','diff','--check'],check=True)
    token=os.environ['GH_TOKEN']
    def api(method,path,payload=None):
        request=urllib.request.Request('https://api.github.com/repos/'+REPO+'/'+path,
            data=json.dumps(payload).encode() if payload is not None else None,
            headers={'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json','Content-Type':'application/json'},method=method)
        with urllib.request.urlopen(request,timeout=30) as response:return json.load(response)
    assert api('GET','git/ref/heads/release/search3-production-ready-v1')['object']['sha']==BASE
    parent=api('GET','git/commits/'+BASE)
    tree=[]
    for path,text in files.items():
        blob=api('POST','git/blobs',{'encoding':'utf-8','content':text})
        expected=hashlib.sha1(b'blob '+str(len(text.encode())).encode()+b'\0'+text.encode()).hexdigest()
        assert blob['sha']==expected, path
        tree.append({'path':path,'mode':'100644','type':'blob','sha':blob['sha']})
    t=api('POST','git/trees',{'base_tree':parent['tree']['sha'],'tree':tree})
    commit=api('POST','git/commits',{'message':'Search3: full canonical hotel content and galleries with live-only integration','tree':t['sha'],'parents':[BASE]})
    result={'state':'source_objects_created_no_ref_changed','parent':BASE,'donor':PARENT,'base':BASE,'commit':commit['sha'],'tree':t['sha'],
            'files':[{'path':p,'sha256':hashlib.sha256(s.encode()).hexdigest()} for p,s in files.items()],
            'supplierCalls':0,'databaseWrites':0,'refWrites':0,'deploy':False}
    (OUT/'result.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))

if __name__=='__main__':main()

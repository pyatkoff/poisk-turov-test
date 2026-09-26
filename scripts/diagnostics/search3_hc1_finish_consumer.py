"""Apply the reviewed HC-1 delta to fresh source; optionally store Git objects.

No branch updates, deployment, database or supplier access. The ordinary PR/CI
and guarded publisher remain separate. Old multi-donor assembly is not called.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import urllib.request

BASE = '27ded93a212229d11d478c43e5efb3fc4478703d'
OUT = Path(os.environ['HC1_ASSEMBLY_OUTPUT'])
CARRIER = Path(__file__).resolve().parents[2]
PINS = {
 'v2/prototype-search/app.js': ('504f70dbc3c552fe43157c08f44245d3bf4db403','0774a11e4e1623a9a5c39b7d18e8913b19dca799'),
 'v2/prototype-search/styles.css': ('081f0cfe60814178f234b8a85f1bef1c0d53ebf9','348b202567d2cff0e5095c835522046807e33da6'),
 'tests/search3-hotel-content-browser.py': ('a9c1bef19f1b9f49f40d9d15e0d72edc6cce8244','2923f509b472b7ccb23f9ff753a7e4c15ae6ea40'),
}
TEXT = r'''// Source HTML remains inert; only its readable text is rendered into Search3.
function hotelContentText(value){
 if(typeof value!=='string'&&typeof value!=='number')return '';
 const template=document.createElement('template');template.innerHTML=String(value);
 template.content.querySelectorAll('script,style,iframe,object,embed,svg,math,template').forEach(node=>node.remove());
 template.content.querySelectorAll('br').forEach(node=>node.replaceWith('\n'));
 template.content.querySelectorAll('p,div,li,ul,ol,tr,h1,h2,h3,h4,section').forEach(node=>node.append('\n'));
 return (template.content.textContent||'').split('\n').map(line=>line.replace(/\s+/g,' ').trim()).filter(Boolean).join('\n');
}
const plainHotelText=value=>hotelContentText(value).replace(/\s+/g,' ').trim();
const hotelTextHTML=value=>esc(value).replace(/\n/g,'<br>');
function hotelDescription(h){
 const text=hotelContentText(h.note);if(!text)return '';
 if(text.length<=460)return `<p class="hotel-detail-copy" data-hotel-description="full">${hotelTextHTML(text)}</p>`;
 const summary=text.slice(0,420).replace(/\s+\S*$/,'');
 return `<p class="hotel-detail-copy" data-hotel-description="summary">${esc(summary)}…</p><details class="search-response-details hotel-content-more hotel-description-more"><summary>Полное описание</summary><p class="hotel-detail-copy" data-hotel-description="full">${hotelTextHTML(text)}</p></details>`;
}
'''
FACTS = r'''function hotelSectionText(value){
 if(Array.isArray(value))return [...new Set(value.map(hotelSectionText).filter(Boolean))].join('\n');
 if(value&&typeof value==='object'){
  const parts=['description','name','text','list','value']
   .filter(key=>Object.prototype.hasOwnProperty.call(value,key))
   .map(key=>hotelSectionText(value[key])).filter(Boolean);
  return [...new Set(parts)].join('\n');
 }
 return hotelContentText(value);
}
function hotelDetailFacts(h,full=false){
 const info=h.raw?.hotelInformation||{},services=info.services||h.raw?.services||{},infrastructure=info.infrastructure||h.raw?.infrastructure||{};
 const sections=full?[
  ['address','Адрес',h.raw?.address],['services.available','Услуги в отеле',services.available],
  ['services.inRoom','В номере',services.inRoom],['services.animation','Развлечения',services.animation],
  ['services.free','Бесплатные услуги',services.free],['services.servicesPay','Платные услуги',services.servicesPay],
  ['meals','Питание в отеле',info.meals||h.raw?.meals],['roomTypes','Номера отеля',info.roomTypes||h.raw?.roomTypes],
  ['build','Год постройки',h.raw?.build],['repair','Ремонт',h.raw?.repair],['square','Площадь территории',h.raw?.square]
 ]:[['place','Расположение',h.raw?.place],['infrastructure.beach','Пляж',infrastructure.beach],
    ['infrastructure.territory','Территория',infrastructure.territory],['services.child','Для детей',services.child]];
 return sections.map(([key,label,value])=>{const text=hotelSectionText(value);return text?`<section data-hotel-field="${key}"><h3 class="detail-section-title">${label}</h3><p class="hotel-detail-copy">${hotelTextHTML(text)}</p></section>`:'';}).join('');
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


def git_hash(data):
    return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()


def replace_range(text,start,end,new):
    assert text.count(start)==text.count(end)==1, (start,end)
    a=text.index(start);b=text.index(end,a)
    return text[:a]+new+text[b:]


def prepare():
    assert subprocess.check_output(['git','rev-parse','HEAD']).decode().strip()==BASE
    files={path:Path(path).read_bytes() for path in PINS}
    for path,data in files.items():assert git_hash(data)==PINS[path][0], ('preimage',path,git_hash(data))
    app=files['v2/prototype-search/app.js'].decode()
    app=replace_range(app,'const plainHotelText=','function hotelHighlights(',TEXT)
    app=replace_range(app,'function hotelSectionText(','function updateFacetCounts(',FACTS)
    app=replace_range(app,'function openHotelDetails(','function renderResults(',OPEN)
    files['v2/prototype-search/app.js']=app.encode()
    css=files['v2/prototype-search/styles.css'].decode()
    changes=[
      ('.hotel-detail-photos button:first-child{grid-row:1/3}', '.hotel-detail-photos button:first-child{grid-row:1/3}.hotel-detail-photos[data-photo-count="1"]{grid-template-columns:1fr}.hotel-detail-photos[data-photo-count="2"]{grid-template-columns:1fr 1fr}.hotel-detail-photos[data-photo-count="2"] button{grid-row:1/3}'),
      ('.hotel-detail-copy{font-size:14px;color:var(--muted);margin-top:12px;line-height:1.8}', '.hotel-detail-copy{font-size:14px;color:var(--muted);margin-top:12px;line-height:1.8;overflow-wrap:anywhere}.hotel-content-more>summary{min-height:44px;display:flex;align-items:center}.hotel-content-more:not([open])>summary::before{content:"+";margin-right:8px}.hotel-content-more[open]>summary::before{content:"−";margin-right:8px}')]
    for old,new in changes:
        assert css.count(old)==1;css=css.replace(old,new,1)
    files['v2/prototype-search/styles.css']=css.encode()
    files['tests/search3-hotel-content-browser.py']=(CARRIER/'patches/search3-hc1-hotel-content-browser.py').read_bytes()
    for path,data in files.items():
        target=OUT/'files'/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
        assert git_hash(data)==PINS[path][1], ('postimage',path,git_hash(data))
    for path,data in files.items():Path(path).write_bytes(data)
    subprocess.run(['node','--check','v2/prototype-search/app.js'],check=True)
    subprocess.run(['python3','-m','py_compile','tests/search3-hotel-content-browser.py'],check=True)
    subprocess.run(['git','diff','--check'],check=True)
    changed=subprocess.check_output(['git','diff','--name-only']).decode().splitlines()
    assert sorted(changed)==sorted(PINS),changed
    (OUT/'changes.patch').write_bytes(subprocess.check_output(['git','diff','--no-ext-diff']))
    print('HC1_CURRENT_DELTA_PREPARED three_files no_donors no_ref_writes')


def objects():
    result=json.loads((OUT/'browser/result.json').read_text())
    assert [r['width'] for r in result['results']]==[390,768,1440]
    assert all(r['status']=='passed' and r['externalCalls']==0 for r in result['results'])
    def api(method,path,payload=None):
        request=urllib.request.Request('https://api.github.com/repos/pyatkoff/poisk-turov-test/'+path,
            data=json.dumps(payload).encode() if payload is not None else None,
            headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','Content-Type':'application/json'},method=method)
        with urllib.request.urlopen(request,timeout=30) as response:return json.load(response)
    assert api('GET','git/ref/heads/release/search3-production-ready-v1')['object']['sha']==BASE,'release changed'
    parent=api('GET','git/commits/'+BASE)
    entries=[];manifest=[]
    for path in PINS:
        data=Path(path).read_bytes();assert git_hash(data)==PINS[path][1]
        blob=api('POST','git/blobs',{'content':data.decode(),'encoding':'utf-8'})
        assert blob['sha']==PINS[path][1]
        entries.append({'path':path,'mode':'100644','type':'blob','sha':blob['sha']})
        manifest.append({'path':path,'before':PINS[path][0],'after':blob['sha'],'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data)})
    tree=api('POST','git/trees',{'base_tree':parent['tree']['sha'],'tree':entries})
    commit=api('POST','git/commits',{'message':'Search3: complete safe hotel descriptions and factual details without changing exact offers','tree':tree['sha'],'parents':[BASE]})
    output={'state':'checked_source_objects_only','base':BASE,'commit':commit['sha'],'tree':tree['sha'],'files':manifest,'supplierCalls':0,'databaseWrites':0,'refWrites':0,'deploy':False}
    (OUT/'result.json').write_text(json.dumps(output,indent=2)+'\n');print(json.dumps(output))


if __name__=='__main__':
    OUT.mkdir(parents=True,exist_ok=True)
    if sys.argv[1:]==['--objects']:objects()
    else:
        assert not sys.argv[1:];prepare()

#!/usr/bin/env python3
"""Offline-only BG geographic evidence join; never turn a BG key into a SAMO key."""
from __future__ import annotations
import collections
import hashlib
import io
import json
from pathlib import Path
import re
import sys
import zipfile

ARTIFACT_SHA = 'b43709917b9ad5c33718addc8243843b1e69f78a41a694445a5b37b3950d22f2'
SOURCE_SHA = '8f91697ac30340a9a75aaad74c6b0f9db99c7c65'
RESULT_SHA = '6ace75420f7b2ecf37149d2ce81fb8e04bfcf7e0da4d516b2f5688f099fcf13b'
# Each rule pins the actual official city label and country. It expresses place
# semantics, not a learned hotel-ID equivalence. Narrow beaches require a matching
# local subdivision; neighboring beaches and unspecified local places do not pass.
# (country ISO, official Russian label, local region, allowed local subdivisions)
# A None subdivision set means an explicitly named region-level place only.
RULES = {
 '100527581649': ('TH','о.Пхукет. Пляж Патонг','Пхукет',('Патонг',)),
 '100527581650': ('TH','о.Пхукет. Пляж Камала','Пхукет',('Камала',)),
 '100527581651': ('TH','о.Пхукет. Пляж Ката','Пхукет',('Ката',)),
 '100527581652': ('TH','о.Пхукет. Пляж Карон','Пхукет',('Карон',)),
 '100527581654': ('TH','о.Пхукет. Пляж Най Харн','Пхукет',('Най Харн',)),
 '100527581655': ('TH','о.Пхукет. Пляж Май Хао','Пхукет',('Май Кхао',)),
 '100527581656': ('TH','о.Пхукет. Пляж Най Янг','Пхукет',('Най Янг',)),
 '100527581657': ('TH','о.Пхукет. Пляж Найтон','Пхукет',('Най Тхон',)),
 '100527581658': ('TH','о.Пхукет. Пляж Бангтао','Пхукет',('Банг Тао',)),
 '100527581659': ('TH','о.Пхукет. Пляж Сурин','Пхукет',('Сурин',)),
 '100527581663': ('TH','о.Пхукет. Пляж Панва','Пхукет',('Панва',)),
 '100532767724': ('EG','Сома бэй','Хургада',('Сома Бей',)),
 '100532767727': ('EG','Макади бэй','Хургада',('Макади Бей',)),
 '100532767872': ('EG','Сахл хашиш','Хургада',('Сахль-Хашиш',)),
 '100525075779': ('TR','Кемер-Белдиби-Гойнюк','Кемер',('Кемер - центр','Бельдиби','Гойнюк')),
 '100525075783': ('TR','Кириш-Чамьюва-Текирова-Демре','Кемер',('Кириш','Чамьюва','Текирова')),
 '100525075698': ('TR','Анталия (Лара-Кунду)','Анталья',('Лара','Кунду')),
 '100525075696': ('TR','Анталия (город)','Анталья',('Коньяалты','Анталия-центр',None)),
 '100520746849': ('TR','Турунч','Мармарис',('Турундж',)),
 '100525109504': ('TR','Измир (Чешме)','Чешме',None),
 '100551209525': ('TR','Фетхие-Олюдениз','Фетхие',('Олюдениз',)),
 '100510039029': ('CN','о.Хайнань. Бухта Санья','Хайнань',('Санья',)),
 '100510039030': ('CN','о.Хайнань. Бухта Ялонг','Хайнань',('Ялунвань',)),
 '100510039031': ('CN','о.Хайнань. Бухта Дадунхай','Хайнань',('Дадунхай',)),
 '100539148673': ('IN','Северный Гоа. Кандолим','Север Гоа',('Кандолим',)),
 '100539148678': ('IN','Северный Гоа. Калангут','Север Гоа',('Калангут',)),
 '100539148712': ('IN','Северный Гоа. Морджим','Север Гоа',('Морджим бич',)),
 '100539148714': ('IN','Северный Гоа. Мандрем','Север Гоа',('Мандрем бич',)),
 '100539148715': ('IN','Северный Гоа. Арамболь','Север Гоа',('Арамболь',)),
 '100539148720': ('IN','Южный Гоа. Маджорда','Юг Гоа',('Маджорда',)),
 '100539148726': ('IN','Южный Гоа. Бенаулим','Юг Гоа',('Бенаулим',)),
 '100539148728': ('IN','Южный Гоа. Кавелоссим','Юг Гоа',('Кавелоссим',)),
 '100569324916': ('LK','Индурувва','Бентота',('Индурува',)),
 '100530850549': ('AE','Рас Аль Хайма','Рас-эль-Хайма',None),
 # The English BG label limits this city key to Deira/Bur Dubai. Do not use
 # Russian generic "Dubai" to approve Jumeirah or an unspecified neighborhood.
 '100510535941': ('AE','Дубай','Дубай',('Дейра','Бур-Дубаи')),
 '100578846727': ('AE','Дубай Джумейра/Барша/Марина','Дубай',('Джумейра','Аль-Барша','Марина')),
 '100531855395': ('AE','Палм Джумейра','Дубай',('Палм Джумейра',)),
}


def digest(raw): return hashlib.sha256(raw).hexdigest()

def norm(value): return ' '.join(re.findall(r'[^\W_]+', str(value or '').casefold().replace('ё','е'), re.UNICODE))

def require(ok, text):
    if not ok: raise ValueError(text)


def country_supported(local, country):
    label = norm(local.get('country_name'))
    if label and label in {norm(country.get(k)) for k in ('title_ru','title_en')}:
        return True
    return (local.get('country_id') == 9 and label == norm('ОАЭ')
            and country.get('code') == 'AE' and str(country.get('id')) == '100410510097'
            and norm(country.get('title_ru')) == norm('Объединенные Арабские Эмираты'))


def place_supported(local, evidence):
    country, city = evidence['official_country'], evidence['official_city']
    if not country or not city or not country_supported(local,country):
        return False, 'country_unresolved'
    if str(city.get('country')) != str(country.get('id')):
        return False, 'official_city_country_conflict'
    if str(evidence['hotel'].get('countryKey')) != str(country.get('id')) or str(evidence['hotel'].get('cityKey')) != str(city.get('id')):
        return False, 'official_hotel_geography_conflict'
    rule = RULES.get(str(city['id']))
    if rule:
        code, label, region, subdivisions = rule
        if country.get('code') != code or norm(city.get('title_ru')) != norm(label):
            return False, 'pinned_city_semantics_changed'
        if norm(local.get('region_name')) != norm(region):
            return False, 'different_region'
        if subdivisions is not None and norm(local.get('subregion_name')) not in {norm(x) for x in subdivisions}:
            return False, 'specific_place_not_supported'
        return True, 'explicit_compound_place_rule:' + str(city['id'])
    local_labels = {norm(local.get(k)) for k in ('region_name','subregion_name') if local.get(k)}
    if local_labels & {norm(city.get(k)) for k in ('title_ru','title_en') if city.get(k)}:
        return True, 'exact_official_place_label'
    return False, 'geography_needs_more_evidence'


def reconcile(data):
    counts = collections.Counter(); output=[]
    for row in data['rows']:
        r=dict(row); proofs=[]
        for evidence in row['official_evidence']:
            supported, reason = place_supported(row['catalog_hotel'],evidence)
            proofs.append({'native_namespace':'bgoperator','native_id':evidence['native_id'],
                           'supported':supported,'rule':reason,
                           'official_country_id':evidence['hotel']['countryKey'],
                           'official_city_id':evidence['hotel']['cityKey']})
        single = len(row['f4_candidates'])==1 and len(row['accepted_catalog_ids'])==1 and not row['manual']
        exact = (single and len(proofs)==1 and proofs[0]['supported']
                 and row['official_evidence'][0]['name_exact'] and row['official_evidence'][0]['category_exact']
                 and row['catalog_hotel'].get('is_active') == 1)
        # New evidence is staged; a separate writer must validate current source
        # and target occupancy/manual decisions AND the real namespace contract.
        r.update(retained_geography=proofs,compound_evidence_candidate=bool(exact),safe_to_write_now=False,
                 write_holds=['supplier_native_namespace_must_be_proven','current_writer_checks_not_performed'])
        counts['hotels_examined']+=1;counts['official_keys_examined']+=len(proofs)
        counts['country_supported_hotels']+=int(all(country_supported(row['catalog_hotel'],e['official_country']) for e in row['official_evidence']))
        counts['geography_supported_hotels']+=int(all(p['supported'] for p in proofs))
        counts['compound_evidence_candidates']+=int(exact)
        counts['original_strict_evidence_candidates']+=int(row['strict_evidence_candidate'])
        counts['additional_evidence_candidates']+=int(exact and not row['strict_evidence_candidate'])
        output.append(r)
    return {'schema':'bg-retained-place-evidence/1','source_artifact_id':10688006443,
            'source_zip_sha256':ARTIFACT_SHA,'source_acquisition_sha':SOURCE_SHA,
            'counts':dict(counts),'rows':output,'supplier_http_requests':0,'database_writes':0,'mapping_writes':0,
            'safe_to_write_now':False,'ids_promoted_to_operator115':False,
            'rule_table':RULES,'acquisition_replayed':False}


def run(archive, destination):
    raw=Path(archive).read_bytes();require(digest(raw)==ARTIFACT_SHA,'source_archive_sha')
    z=zipfile.ZipFile(io.BytesIO(raw));receipt=json.loads(z.read('receipt.json'))
    require(receipt['state']=='completed_geography_evidence' and receipt['source_sha']==SOURCE_SHA,'source_receipt')
    require(receipt['supplier_http_requests']==2 and receipt['unknown_requests']==[],'source_accounting')
    for name,sha in receipt['files_sha256'].items():require(digest(z.read(name))==sha,'member_sha_'+name)
    source=z.read('geography-candidates.json');require(digest(source)==RESULT_SHA,'source_evidence_sha')
    data=json.loads(source);require(len(data['rows'])==816,'source_membership')
    require(sum(len(r['f4_candidates']) for r in data['rows'])==838,'raw_f4_membership')
    require(sum(len(r['official_evidence']) for r in data['rows'])==820,'official_membership')
    require(len({r['tv_hotel_id'] for r in data['rows']})==816,'unique_local_membership')
    output=reconcile(data)
    encoded=(json.dumps(output,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
    with Path(destination).open('xb') as handle:handle.write(encoded)
    print(json.dumps({'counts':output['counts'],'result_sha256':digest(encoded),'supplier_http_requests':0,'mapping_writes':0},ensure_ascii=False))


def tests():
    c={'id':'100410000051','code':'TH','title_ru':'Таиланд','title_en':'Thailand'}
    e={'native_id':'102610000001','official_country':c,
       'official_city':{'id':'100527581649','country':c['id'],'title_ru':'о.Пхукет. Пляж Патонг','title_en':'Phuket. Patong Beach'},
       'hotel':{'countryKey':c['id'],'cityKey':'100527581649'}}
    l={'country_id':2,'country_name':'Таиланд','region_name':'Пхукет','subregion_name':'Патонг'}
    require(place_supported(l,e)[0],'compound_positive')
    for sub in ('Карон','Ката','Патонг Аннекс',None):
        require(not place_supported(dict(l,subregion_name=sub),e)[0],'narrow_beach_negative')
    require(not place_supported(dict(l,region_name='Као-Лак'),e)[0],'phuket_khao_lak_negative')
    e['official_city']['country']='100410000047';require(not place_supported(l,e)[0],'parent_country_negative')
    e['official_city']['country']=c['id'];e['official_city']['title_ru']='о.Пхукет. Другие регионы'
    require(not place_supported(l,e)[0],'changed_label_negative')
    ae={'id':'100410510097','code':'AE','title_ru':'Объединенные Арабские Эмираты','title_en':'United Arab Emirates'}
    require(country_supported({'country_id':9,'country_name':'ОАЭ'},ae),'abbreviation_positive')
    require(not country_supported({'country_id':9,'country_name':'ОАЭ'},c),'abbreviation_wrong_country')
    e={'official_country':ae,'official_city':{'id':'100510535941','country':ae['id'],'title_ru':'Дубай','title_en':'Dubai Deira / Bur Dubai'},
       'hotel':{'countryKey':ae['id'],'cityKey':'100510535941'}}
    l={'country_id':9,'country_name':'ОАЭ','region_name':'Дубай','subregion_name':'Дейра'}
    require(place_supported(l,e)[0],'dubai_specific_positive')
    for sub in ('Джумейра','Марина',None):require(not place_supported(dict(l,subregion_name=sub),e)[0],'dubai_area_not_collapsed')
    require(norm('GRAND GOSIA')!=norm('GOSIA') and norm('FAMILY')!=norm('PALMA'),'qualifiers_kept')
    print('offline geographic semantics tests PASS')

if __name__=='__main__':
    if sys.argv[1]=='test':tests()
    else:run(sys.argv[1],sys.argv[2])

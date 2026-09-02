<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-verified-content-evidence-v1.php';

/** Authored review-only seasonal content prototypes backed by official source claims. */
function v2_seo_seasonal_review_content_prototypes(): array
{
    $trUrl='https://www.mgm.gov.tr/veridegerlendirme/il-ve-ilceler-istatistik.aspx?m=ANTALYA';
    $mvUrl='https://www.meteorology.gov.mv/climate';
    return [
        'antalya-september'=>[
            'state'=>'authored_review_only_requires_fresh_identity_rebind','page_key'=>'resort_month:1:4:20:2026-09','country_id'=>4,'region_id'=>20,
            'title'=>'Анталья в сентябре: климатические ориентиры для поездки','h1'=>'Анталья в сентябре',
            'intro'=>'Сентябрьские условия в Анталье лучше оценивать по официальной климатической статистике, а конкретный тур — по актуальной выдаче поиска AnyTour.',
            'sections'=>[
                ['heading'=>'Температура в сентябре','text_template'=>'По данным MGM для Антальи, средняя температура сентября — {{avg_temp}}, средняя максимальная — {{avg_high}}, а средняя минимальная — {{avg_low}}.','claim_keys'=>['avg_temp','avg_high','avg_low']],
                ['heading'=>'Осадки','text_template'=>'Средний месячный объём осадков в сентябре — {{precipitation}}, а среднее число дождливых дней — {{rainy_days}}.','claim_keys'=>['precipitation','rainy_days']],
            ],
            'claims'=>[
                ['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'avg_temp','type'=>'climate_temperature','value'=>'25,3 °C','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>$trUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'avg_high','type'=>'climate_temperature','value'=>'31,2 °C','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>$trUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'avg_low','type'=>'climate_temperature','value'=>'19,6 °C','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>$trUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'precipitation','type'=>'climate_precipitation','value'=>'16,7 мм','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>$trUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'rainy_days','type'=>'climate_precipitation','value'=>'1,71 дня','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>$trUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
            ],
            'source_note'=>'Официальная климатическая статистика MGM; источник указывает период наблюдений 1930–2025. Перед любым решением о публикации повторно проверьте источник и свежую production identity.',
            'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_creation_allowed'=>false,
        ],
        'maldives-september'=>[
            'state'=>'authored_review_only_requires_fresh_identity_rebind','page_key'=>'month:1:8:2026-09','country_id'=>8,'region_id'=>null,
            'title'=>'Мальдивы в сентябре: климат и особенности сезона','h1'=>'Мальдивы в сентябре',
            'intro'=>'Для сентябрьской поездки на Мальдивы полезно отделять общие климатические ориентиры от краткосрочного прогноза и от наличия конкретных туров.',
            'sections'=>[
                ['heading'=>'Температура','text_template'=>'Maldives Meteorological Service описывает климат страны как тёплый и влажный круглый год; средняя температура находится в диапазоне {{temperature_range}}.','claim_keys'=>['temperature_range']],
                ['heading'=>'Осадки и сезон','text_template'=>'По данным MMS, {{wet_season}}.','claim_keys'=>['wet_season']],
                ['heading'=>'Солнечный свет','text_template'=>'Для месяцев вне января–марта MMS указывает в среднем {{daylight_range}} солнечного света; этот ориентир относится и к сентябрю.','claim_keys'=>['daylight_range']],
            ],
            'claims'=>[
                ['country_id'=>8,'page_key'=>'month:1:8:2026-09','claim_key'=>'temperature_range','type'=>'climate_temperature','value'=>'25–32 °C','source_class'=>'official_meteorological','source_id'=>'mv-mms-climate','source_url'=>$mvUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>8,'page_key'=>'month:1:8:2026-09','claim_key'=>'wet_season','type'=>'climate_precipitation','value'=>'влажный сезон обычно продолжается с середины мая до ноября, а в период юго-западного муссона осадков больше','source_class'=>'official_meteorological','source_id'=>'mv-mms-climate','source_url'=>$mvUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
                ['country_id'=>8,'page_key'=>'month:1:8:2026-09','claim_key'=>'daylight_range','type'=>'daylight','value'=>'7–9 часов','source_class'=>'official_meteorological','source_id'=>'mv-mms-climate','source_url'=>$mvUrl,'observed_at'=>'2026-09-02T11:17:00Z'],
            ],
            'source_note'=>'Официальная климатическая страница Maldives Meteorological Service. Перед любым решением о публикации повторно проверьте источник и свежую production identity.',
            'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_creation_allowed'=>false,
        ],
    ];
}

function v2_seo_seasonal_render_review_content(array $record, ?int $nowEpoch=null): array
{
    if(($record['state']??'')!=='authored_review_only_requires_fresh_identity_rebind') throw new InvalidArgumentException('Seasonal content prototype state is invalid');
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_creation_allowed'] as $flag) if(($record[$flag]??true)!==false) throw new InvalidArgumentException('Seasonal content prototype crossed launch boundary');
    $pageKey=trim((string)($record['page_key']??'')); $countryId=(int)($record['country_id']??0); $regionId=$record['region_id']??null;
    if($pageKey===''||$countryId<=0) throw new InvalidArgumentException('Seasonal content prototype identity is invalid');
    $claims=is_array($record['claims']??null)?$record['claims']:[];
    if($claims===[]) throw new InvalidArgumentException('Seasonal content prototype requires claims');
    foreach($claims as $claim){
        if(!is_array($claim)||(string)($claim['page_key']??'')!==$pageKey||(int)($claim['country_id']??0)!==$countryId) throw new InvalidArgumentException('Seasonal content claim identity mismatch');
    }
    $parts=explode(':',$pageKey);
    if(($parts[0]??'')==='month') { if($regionId!==null) throw new InvalidArgumentException('Seasonal country prototype region mismatch'); }
    elseif(($parts[0]??'')==='resort_month') { if((int)($parts[3]??0)<=0||(int)$regionId!==(int)$parts[3]) throw new InvalidArgumentException('Seasonal resort prototype region mismatch'); }
    else throw new InvalidArgumentException('Seasonal content prototype page type is invalid');

    $evidence=v2_seo_seasonal_verified_content_evidence($claims,$nowEpoch);
    if(($evidence['state']??'')!=='review_ready') return ['state'=>'blocked','blocked'=>$evidence['blocked']??[]];
    $values=[]; foreach(($evidence['claims']??[]) as $claim) $values[(string)$claim['claim_key']]=(string)$claim['value'];
    $sections=[];
    foreach(($record['sections']??[]) as $section){
        $text=(string)($section['text_template']??''); $keys=is_array($section['claim_keys']??null)?$section['claim_keys']:[];
        if($text===''||$keys===[]) throw new InvalidArgumentException('Seasonal content section requires explicit claims');
        foreach($keys as $key){ $key=(string)$key; if(!array_key_exists($key,$values)||!str_contains($text,'{{'.$key.'}}')) throw new InvalidArgumentException('Seasonal content template claim mismatch'); $text=str_replace('{{'.$key.'}}',$values[$key],$text); }
        if(preg_match('/\{\{[^}]+\}\}/u',$text)) throw new InvalidArgumentException('Seasonal content template contains unresolved claims');
        $sections[]=['heading'=>(string)($section['heading']??''),'text'=>$text,'claim_keys'=>array_values($keys)];
    }
    return ['state'=>'rendered_review_only_seasonal_content','page_key'=>$pageKey,'title'=>(string)$record['title'],'h1'=>(string)$record['h1'],'intro'=>(string)$record['intro'],'sections'=>$sections,'claims'=>$evidence['claims'],'source_note'=>(string)($record['source_note']??''),'publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_creation_allowed'=>false,'requires_fresh_identity_rebind'=>true];
}

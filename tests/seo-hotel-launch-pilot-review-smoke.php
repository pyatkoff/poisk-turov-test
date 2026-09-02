<?php
require_once __DIR__ . '/../v2/seo-hotel-launch-pilot-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotels-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-maldives-hotels-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-egypt-hotels-v1.php';

function pilot_review_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_PILOT_REVIEW_FAIL:$message\n");
    exit(1);
}

$spec=v2_seo_hotel_launch_pilot_spec();
$records=array_merge(
    v2_seo_turkey_hotel_records(),
    v2_seo_maldives_hotel_records(),
    v2_seo_egypt_hotel_records()
);
$byPath=[];
foreach($records as $record){
    $path=(string)($record['path']??'');
    if($path==='') continue;
    if(isset($byPath[$path])) pilot_review_fail('duplicate_record_path');
    $byPath[$path]=$record;
}

$seenTitles=[];$seenH1=[];$seenIntro=[];$checked=0;
foreach($spec['countries'] as $bucket){
    $countryId=(int)($bucket['country_id']??0);
    foreach(($bucket['paths']??[]) as $path){
        $record=$byPath[$path]??null;
        if(!is_array($record)) pilot_review_fail('missing_record');
        if(($record['status']??'')!=='review') pilot_review_fail('not_review');
        if(($record['type']??'')!=='hotel_tours') pilot_review_fail('wrong_type');
        $data=is_array($record['data']??null)?$record['data']:[];
        $state=is_array($data['search_state']??null)?$data['search_state']:[];
        if((int)($state['country']??0)!==$countryId) pilot_review_fail('country_identity');
        if(!preg_match('~-([1-9][0-9]*)/$~',(string)$path,$m)) pilot_review_fail('path_hotel_identity');
        if((int)($state['hotel']??0)!==(int)$m[1]) pilot_review_fail('hotel_identity');

        $title=trim((string)($data['title']??''));
        $h1=trim((string)($data['h1']??''));
        $description=trim((string)($data['description']??''));
        $intro=trim((string)($data['intro']??''));
        $sections=is_array($data['sections']??null)?$data['sections']:[];
        if(mb_strlen($title,'UTF-8')<35) pilot_review_fail('thin_title');
        if(mb_strlen($h1,'UTF-8')<12) pilot_review_fail('thin_h1');
        if(mb_strlen($description,'UTF-8')<100) pilot_review_fail('thin_description');
        if(mb_strlen($intro,'UTF-8')<100) pilot_review_fail('thin_intro');
        if(count($sections)<3) pilot_review_fail('thin_sections');
        $paragraphs=0;
        foreach($sections as $section){
            $sectionTitle=trim((string)($section['title']??''));
            $items=is_array($section['paragraphs']??null)?$section['paragraphs']:[];
            if($sectionTitle==='' || !$items) pilot_review_fail('empty_section');
            foreach($items as $paragraph){
                if(mb_strlen(trim((string)$paragraph),'UTF-8')<45) pilot_review_fail('thin_paragraph');
                $paragraphs++;
            }
        }
        if($paragraphs<6) pilot_review_fail('insufficient_paragraphs');
        foreach([['title',$title,&$seenTitles],['h1',$h1,&$seenH1],['intro',$intro,&$seenIntro]] as &$check){
            $fingerprint=mb_strtolower(preg_replace('/\s+/u',' ',trim($check[1])),'UTF-8');
            if(isset($check[2][$fingerprint])) pilot_review_fail('duplicate_'.$check[0]);
            $check[2][$fingerprint]=true;
        }
        unset($check);
        $checked++;
    }
}
if($checked!==9) pilot_review_fail('pilot_count');

echo "SEO_HOTEL_PILOT_REVIEW_OK pages=9 reviewOnly=1 identity=1 metadata=1 editorialDepth=1 uniqueCopy=1\n";

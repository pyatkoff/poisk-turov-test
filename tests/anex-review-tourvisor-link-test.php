<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/admin/anex-review/view.php';
$checks = 0;
function tv_check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
$candidate = ['catalog_hotel_id'=>'21477', 'current'=>['id'=>'21477', 'country_id'=>'1', 'name'=>'Movenpick']];
$expected = 'https://tourvisor.ru/search.php?ts_dosearch=1&s_form_mode=0&s_nights_from=8&s_nights_to=8&s_directflight=0&s_regular=1&x_hotel_codes=21477&s_j_date_from=18.09.2026&s_j_date_to=18.09.2026&s_adults=2&s_meal=7&s_flyfrom=1&s_country=1';
tv_check(anex_review_tourvisor_link($candidate, []) === $expected, 'owner example preserved exactly');
$turkey = $candidate;
$turkey['current']['country_id'] = '4';
$turkey['anex_country_id'] = '1';
$custom = ['tv_date'=>'2028-02-29','tv_nights'=>'12','tv_adults'=>'3','tv_meal'=>'0','tv_departure'=>'2'];
parse_str((string)parse_url((string)anex_review_tourvisor_link($turkey, $custom), PHP_URL_QUERY), $params);
tv_check($params['s_country'] === '4', 'country comes only from current local candidate');
tv_check($params['s_j_date_from'] === '29.02.2028' && $params['s_j_date_to'] === '29.02.2028', 'valid leap day remains one day');
tv_check($params['s_nights_from'] === '12' && $params['s_nights_to'] === '12' && $params['s_adults'] === '3' && $params['s_meal'] === '0' && $params['s_flyfrom'] === '2', 'editable criteria retained');
tv_check(count($params) === 13 && !isset($params['operator']), 'no guessed operator or added query contract');
foreach ([null, [], ['id'=>21476,'country_id'=>1], ['id'=>21477,'country_id'=>0], ['id'=>21477,'country_id'=>null], ['id'=>'21477&evil=1','country_id'=>1], ['id'=>21477,'country_id'=>[]]] as $local) {
    $invalid = $candidate; $invalid['current'] = $local;
    tv_check(anex_review_tourvisor_link($invalid, []) === null, 'deleted/incomplete/inconsistent local candidate disabled');
}
foreach ([0,-1,'021477','21477,21478','21477&next=https://evil.test',true,[],9999999999] as $id) {
    $invalid = $candidate; $invalid['catalog_hotel_id'] = $id;
    tv_check(anex_review_tourvisor_link($invalid, []) === null, 'malformed target ID disabled');
}
foreach ([['tv_date'=>'2026-02-29'],['tv_date'=>'2026-09-31'],['tv_date'=>'18.09.2026'],['tv_date'=>[]],['tv_date'=>'2026-09-18&x=1'],['tv_date'=>'1999-12-31'],['tv_nights'=>'0'],['tv_nights'=>'61'],['tv_nights'=>'8e0'],['tv_adults'=>'7'],['tv_meal'=>'100'],['tv_departure'=>'0'],['tv_departure'=>[]]] as $input) {
    tv_check(anex_review_tourvisor_link($candidate, $input) === null, 'invalid search criteria disabled');
}
$queue = ['total'=>1,'countries'=>[['country_id'=>4]],'items'=>[['anex_hotel_id'=>5200,'hotel_name'=>'Test','country_id'=>4,'search_count'=>2,'last_seen_utc'=>'2026-09-09 12:00:00','mapped_id'=>null]],'page'=>1,'pages'=>2];
$detail = ['anex_hotel_id'=>5200,'hotel_name'=>'Test','country_id'=>999,'mapped_id'=>null,'observation'=>['search_count'=>2,'last_seen_utc'=>'2026-09-09 12:00:00'],'evidence'=>['candidate_count'=>1],'version'=>str_repeat('a',64),'source'=>[], 'content'=>null,'candidates'=>[$candidate],'exclusions'=>[],'manual'=>null,'audit'=>[]];
$html = anex_review_render($queue,$detail,$custom+['q'=>'<script>','page'=>'1'],'csrf',false,'nonce');
tv_check(strpos($html,'Открыть этот отель на Tourvisor') !== false, 'candidate has link in read-only owner panel');
tv_check(strpos($html,'s_country=1') !== false && strpos($html,'s_country=999') === false, 'render does not use ANEX country');
tv_check(strpos($html,'target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"') !== false, 'external tab sends no referrer and cannot use opener');
tv_check(strpos($html,'s_j_date_from=29.02.2028&amp;') !== false, 'link query escaped in HTML');
tv_check(strpos($html,'<script>') === false && strpos($html,'&lt;script&gt;') !== false, 'search filter escapes correctly');
tv_check(strpos($html,'name="id" value="5200"') !== false, 'criteria form preserves active dossier');
tv_check(strpos($html,'tv_date=2028-02-29') !== false && strpos($html,'page=2') !== false, 'queue and pagination keep selected criteria');
tv_check(strpos($html,'Наличие ID ANEX пока не подтверждено') !== false, 'no promise that Tourvisor exposes supplier identity');
$badHtml = anex_review_render($queue,$detail,['tv_date'=>'invalid'],'csrf',false,'nonce');
tv_check(strpos($badHtml,'role="alert"') !== false && strpos($badHtml,'href="https://tourvisor.ru/') === false, 'invalid criteria surface message and no outgoing links');
echo 'Tourvisor owner link checks: ' . $checks . " passed\n";

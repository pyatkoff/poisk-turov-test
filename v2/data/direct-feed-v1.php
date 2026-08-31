<?php
declare(strict_types=1);

function v2_direct_offer_id($hotelId): string {
    $id = filter_var($hotelId, FILTER_VALIDATE_INT);
    return $id !== false && (int)$id > 0 ? 'hotel_' . (int)$id : '';
}

function v2_direct_feed_escape($value): string {
    return htmlspecialchars(trim((string)$value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function v2_direct_feed_price($value): ?string {
    if (!is_numeric($value) || (float)$value <= 0) return null;
    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
}

function v2_direct_feed_url(array $row): string {
    $query = [
        'from'=>(int)($row['departure_id']??0),
        'country'=>(int)($row['country_id']??0),
        'region'=>(int)($row['region_id']??0),
        'hotel'=>(int)($row['hotel_id']??0),
        'dateFrom'=>(string)($row['departure_date']??''),
        'dateTo'=>(string)($row['departure_date']??''),
        'daysFrom'=>(int)($row['nights']??0),
        'daysTill'=>(int)($row['nights']??0),
        'count_people'=>2,
    ];
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== 0);
    return 'https://anytoour.ru/poisk-turov/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function v2_direct_feed_best_by_hotel(array $rows): array {
    $best=[];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id=(int)($row['hotel_id']??0); $price=(float)($row['price']??0);
        if ($id<=0 || $price<=0) continue;
        if (!isset($best[$id]) || $price < (float)$best[$id]['price']) $best[$id]=$row;
    }
    uasort($best, static fn($a,$b) => ((float)$a['price'] <=> (float)$b['price']));
    return array_values($best);
}

function v2_direct_feed_render(array $rows, string $date=''): string {
    $rows=v2_direct_feed_best_by_hotel($rows);
    $date=$date!==''?$date:(new DateTimeImmutable())->format('Y-m-d H:i');
    $categories=[];
    foreach ($rows as $row) {
        $id=(int)($row['country_id']??0);
        if ($id>0) $categories[$id]=trim((string)($row['country_name']??'')) ?: 'Страна '.$id;
    }
    ksort($categories);
    $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<yml_catalog date=\"".v2_direct_feed_escape($date)."\">\n  <shop>\n    <name>AnyTour</name>\n    <company>AnyTour</company>\n    <url>https://anytoour.ru/</url>\n    <currencies><currency id=\"RUB\" rate=\"1\"/></currencies>\n    <categories>\n";
    foreach ($categories as $id=>$name) $xml.='      <category id="'.$id.'">'.v2_direct_feed_escape($name)."</category>\n";
    $xml.="    </categories>\n    <offers>\n";
    foreach ($rows as $row) {
        $offerId=v2_direct_offer_id($row['hotel_id']??null); $price=v2_direct_feed_price($row['price']??null); $countryId=(int)($row['country_id']??0); $name=trim((string)($row['hotel_name']??''));
        if ($offerId==='' || $price===null || $countryId<=0 || $name==='') continue;
        $stars=(int)($row['hotel_category']??0); $title=$name.($stars>0?' '.$stars.'★':'');
        $picture=trim((string)($row['picture_url']??''));
        $xml.='      <offer id="'.v2_direct_feed_escape($offerId).'" available="true">'."\n";
        $xml.='        <url>'.v2_direct_feed_escape(v2_direct_feed_url($row))."</url>\n";
        $xml.='        <price>'.$price."</price>\n        <currencyId>RUB</currencyId>\n        <categoryId>".$countryId."</categoryId>\n";
        if (str_starts_with(strtolower($picture),'https://')) $xml.='        <picture>'.v2_direct_feed_escape($picture)."</picture>\n";
        $xml.='        <name>'.v2_direct_feed_escape($title)."</name>\n";
        $xml.='        <description>'.v2_direct_feed_escape('Тур в '.$name.' — актуальная цена AnyTour.')."</description>\n      </offer>\n";
    }
    return $xml."    </offers>\n  </shop>\n</yml_catalog>\n";
}

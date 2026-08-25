<?php
header('Content-Type: application/json; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once dirname(__DIR__).'/tv_api/search.php';

$out = ['ok' => true, 'items' => []];
try {
    \Bitrix\Main\Loader::includeModule('highloadblock');
    $hl = \Bitrix\Highloadblock\HighloadBlockTable::getById(TVToursTable::$hotelHL)->fetch();
    $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hl);
    $dataClass = $entity->getDataClass();
    $rows = $dataClass::getList([
        'select' => ['UF_HID','UF_NAME','UF_PHOTO'],
        'order' => ['UF_HID' => 'DESC'],
        'limit' => 100,
    ]);
    while ($hotel = $rows->fetch()) {
        if (empty($hotel['UF_PHOTO']) || !is_array($hotel['UF_PHOTO']) || empty($hotel['UF_PHOTO'][0])) {
            continue;
        }
        $data = TVToursTable::GetHotelList([$hotel['UF_HID']]);
        $img = $data[$hotel['UF_HID']]['IMG'] ?? '';
        $out['items'][] = [
            'hid' => $hotel['UF_HID'],
            'name' => $hotel['UF_NAME'],
            'photo_id' => $hotel['UF_PHOTO'][0],
            'img' => $img,
            'file_exists' => $img ? is_file($_SERVER['DOCUMENT_ROOT'].$img) : false,
        ];
        if (count($out['items']) >= 5) break;
    }
    $out['count'] = count($out['items']);
} catch (Throwable $e) {
    http_response_code(500);
    $out = ['ok' => false, 'error' => $e->getMessage()];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

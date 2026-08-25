<?php
/**
 * Test-sandbox AJAX entry point.
 * Executes the frozen search handler from site-template, but redirects
 * Tourvisor PHP dependencies to /poisk-turov-test/tv_api/.
 * Missing hotel photos are lazily filled only in the test sandbox flow.
 */
$sourceFile = dirname(__DIR__, 2) . '/site-template/components/rhat.search/form/form/ajax.php';
$source = file_get_contents($sourceFile);
if ($source === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Test AJAX source not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$source = str_replace(
    [
        "'/bitrix/php_interface/tv_api/search.php'",
        "'/bitrix/php_interface/tv_api/tvapi.php'",
    ],
    [
        "'/poisk-turov-test/tv_api/search.php'",
        "'/poisk-turov-test/tv_api/tvapi.php'",
    ],
    $source
);

$source = preg_replace('/^\s*<\?(?:php)?/i', '', $source, 1);

ob_start();
eval($source);
$raw = ob_get_clean();
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo $raw;
    exit;
}

if (!isset($_SESSION['poisk_test_photo_attempts']) || !is_array($_SESSION['poisk_test_photo_attempts'])) {
    $_SESSION['poisk_test_photo_attempts'] = [];
}

if (!empty($data['tours']) && is_array($data['tours'])) {
    $budget = 3;
    foreach ($data['tours'] as &$tour) {
        if ($budget <= 0) {
            break;
        }
        if (!empty($tour['hotel_img'])) {
            continue;
        }

        $hid = isset($tour['id']) ? (int)$tour['id'] : 0;
        if ($hid <= 0 || !empty($_SESSION['poisk_test_photo_attempts'][$hid])) {
            continue;
        }

        $_SESSION['poisk_test_photo_attempts'][$hid] = 1;
        $budget--;

        try {
            \TvApi::updateHotelData($hid);
            $hotelData = \TVToursTable::GetHotelList([$hid]);
            $img = $hotelData[$hid]['IMG'] ?? '';
            if ($img) {
                $tour['hotel_img'] = "<img src='" . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . "' />";
                $tour['photo_lazy_loaded'] = 1;
            }
        } catch (\Throwable $e) {
            $tour['photo_lazy_loaded'] = 0;
        }
    }
    unset($tour);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);

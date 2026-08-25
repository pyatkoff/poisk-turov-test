<?php
/** V2 analytics config bridge. Exposes only the public counter id, never OAuth credentials. */
function v2_metrika_counter_id(): int
{
    $privateConfig = __DIR__ . '/config.php';
    if (is_file($privateConfig)) require_once $privateConfig;
    if (!defined('METRIKA_COUNTER_ID')) return 0;
    $id = filter_var(METRIKA_COUNTER_ID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int)$id;
}

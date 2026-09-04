<?php
/** Search3 presentation on the canonical search route; existing runtime owns all data. */
require_once __DIR__ . '/assets.php';

function v2_search3_enabled(): bool
{
    return defined('V2_SEARCH3_PRESENTATION') && V2_SEARCH3_PRESENTATION === true;
}

function v2_search3_asset_tags(string $type): string
{
    if (!v2_search3_enabled()) return '';
    if (!in_array($type, ['css', 'js'], true)) {
        throw new InvalidArgumentException('Invalid Search3 asset type');
    }
    $html = '';
    foreach (['search3-results-filters-v1', 'search3-entry-v1', 'search3-results-cards-v2', 'search3-selected-flow-v2'] as $name) {
        $url = htmlspecialchars(v2_asset($name . '.' . $type), ENT_QUOTES, 'UTF-8');
        $id = $name . ($type === 'css' ? '-style' : '-script');
        $html .= $type === 'css'
            ? '<link id="' . $id . '" rel="stylesheet" href="' . $url . '">'
            : '<script id="' . $id . '" src="' . $url . '"></script>';
    }
    return $html;
}

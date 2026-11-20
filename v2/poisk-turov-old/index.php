<?php
/** Previous search presentation; same maintained search and lead runtime. */
define('V2_SEARCH3_PRESENTATION', false);
define('V2_LEGACY_SEARCH', true);
define('V2_FORCE_SEARCH_PAGE', true);
define('V2_PUBLIC_BASE_PATH', '');
define('V2_CANONICAL_PATH', '/poisk-turov/');
header('X-Robots-Tag: noindex, follow');
// The standalone publisher moves the search here before replacing index.php with the homepage.
$root = dirname(__DIR__);
$searchEntry = $root . '/search-page-v2.php';
if (!is_file($searchEntry)) $searchEntry = $root . '/index.php';
require $searchEntry;

<?php
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once $siteConf;
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/form-defaults.php';
require_once __DIR__ . '/seo-config.php';
require_once __DIR__ . '/site-footer-v1.php';
require __DIR__ . '/home-v1.php';

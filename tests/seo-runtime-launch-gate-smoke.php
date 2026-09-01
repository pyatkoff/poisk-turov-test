<?php
require_once __DIR__ . '/../v2/site-page-shell-v1.php';

function runtime_gate_fail(string $message): void
{
    fwrite(STDERR, "SEO_RUNTIME_GATE_FAIL:$message\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/anytour-seo-runtime-' . bin2hex(random_bytes(4));
if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) runtime_gate_fail('tmp_dir');
$_SERVER['DOCUMENT_ROOT'] = $tmp;
$_SERVER['HTTP_HOST'] = 'anytoour.ru';

file_put_contents($tmp . '/site_conf.php', "<?php\n\$params=['SEO_TURKEY_LAUNCH'=>true];\n");

$_SERVER['REQUEST_URI'] = '/country/turkey/kemer/?utm_source=test';
$kemer = sp_context('/country/turkey/kemer/', 'Kemer', 'Kemer description');
if (!str_starts_with((string)$kemer['robots'], 'index,follow')) runtime_gate_fail('kemer_not_indexable');

$_SERVER['REQUEST_URI'] = '/poisk-turov/?country=4&region=22';
$search = sp_context('/poisk-turov/', 'Search', 'Search description');
if (!str_starts_with((string)$search['robots'], 'noindex,follow')) runtime_gate_fail('search_leaked');

$_SERVER['REQUEST_URI'] = '/country/egypt/';
$other = sp_context('/country/egypt/', 'Egypt', 'Egypt description');
if (!str_starts_with((string)$other['robots'], 'noindex,follow')) runtime_gate_fail('other_country_leaked');

file_put_contents($tmp . '/site_conf.php', "<?php\n\$params=['SEO_TURKEY_LAUNCH'=>false];\n");
$_SERVER['REQUEST_URI'] = '/country/turkey/kemer/';
$disabled = sp_context('/country/turkey/kemer/', 'Kemer', 'Kemer description');
if (!str_starts_with((string)$disabled['robots'], 'noindex,follow')) runtime_gate_fail('disabled_flag');

@unlink($tmp . '/site_conf.php');
@rmdir($tmp);

echo "SEO_RUNTIME_GATE_OK turkeyFlag=1 selectedPathsOnly=1 searchProtected=1 disabledSafe=1\n";

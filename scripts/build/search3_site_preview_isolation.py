"""Exact, fail-closed transformations of the copied preview payload only."""
from pathlib import Path
import sys


def replace_once(path: Path, old: str, new: str) -> None:
    source = path.read_text()
    if source.count(old) != 1:
        raise ValueError(f"Preview isolation source drift: {path.name}")
    path.write_text(source.replace(old, new))


def isolate(payload: Path) -> None:
    # The production DOCUMENT_ROOT may contain Bitrix, analytics and other
    # side effects. The preview must not execute these implicitly.
    search = payload / "search-page-v2.php"
    replace_once(search, """$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$bitrixProlog = $docRoot . '/bitrix/modules/main/include/prolog_before.php';
if ($docRoot !== '' && is_file($bitrixProlog)) require($bitrixProlog);
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once($siteConf);
""", "// Preview: do not bootstrap the production document root.\n")
    replace_once(search, "$metrikaCounter=v2_metrika_counter_id();", "$metrikaCounter=0;")
    replace_once(search, '<script src="https://app.anytoour.ru/web-consultant/widget.js" async></script>', '')
    home_bootstrap = """$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once $siteConf;
"""
    for name in ("index.php", "home-entry-v1.php"):
        replace_once(payload / name, home_bootstrap, "// Preview: use verified source defaults, not production site_conf.\n")
    replace_once(payload / "site-page-shell-v1.php", """  $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
  $siteConf=$docRoot.'/site_conf.php'; if($docRoot!==''&&is_file($siteConf)) require $siteConf;
""", "  // Preview: do not execute production site_conf.\n")

    # The compatibility entry otherwise includes the transformed homepage and
    # retains a root-relative production lead URL. Keep the legacy UI isolated.
    legacy = payload / "poisk-turov-old/index.php"
    replace_once(legacy, "define('V2_PUBLIC_BASE_PATH', '');", """require_once dirname(__DIR__) . '/site-path-v1.php';
define('V2_PUBLIC_BASE_PATH', v2_site_base_path());
define('V2_API_PUBLIC_PATH', '/api-v2.php');
define('V2_LEAD_PUBLIC_PATH', v2_site_href('/preview-lead-disabled.php'));""")
    replace_once(legacy, "require dirname(__DIR__) . '/index.php';", "require dirname(__DIR__) . '/search-page-v2.php';")


if __name__ == "__main__":
    isolate(Path(sys.argv[1]))

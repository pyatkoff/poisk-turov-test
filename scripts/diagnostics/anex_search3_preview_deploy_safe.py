"""Preserve the server-side ANEX B2B credential during an isolated preview refresh.

The existing ANEX preview publisher deliberately transports only the public API
credential from GitHub Actions.  AdditionalPricesDaily uses a separate B2B
credential already installed in the private server config.  This wrapper keeps
that credential server-side: it validates and copies it into the newly generated
private config before the preview/config switch.  The token is never emitted to
stdout or copied into workflow artifacts.
"""
from __future__ import annotations

import importlib.util
from pathlib import Path
import shlex


BASE_PATH = Path(__file__).with_name("anex_search3_preview_deploy.py")
SPEC = importlib.util.spec_from_file_location("anex_search3_preview_deploy_base", BASE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("ANEX preview publisher import failed")
base = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(base)

_BASE_REMOTE_SCRIPT = base.remote_script

PRESERVE_PHP = r'''if ($argc !== 3) { exit(10); }
$old = $argv[1];
$new = $argv[2];
foreach ([$old, $new] as $path) {
    if (!is_file($path) || is_link($path)) { exit(11); }
}
ob_start();
try {
    require $old;
} catch (Throwable $e) {
    ob_end_clean();
    exit(12);
}
$output = ob_get_clean();
if ($output !== '') { exit(13); }
if (!defined('ANEX_B2B_TOKEN') || !is_string(ANEX_B2B_TOKEN)) { exit(14); }
$token = ANEX_B2B_TOKEN;
if ($token === '' || strlen($token) > 16384 || stripos($token, 'Bearer ') === 0 || preg_match('/[\x00-\x20\x7f]/', $token)) {
    exit(15);
}
$next = file_get_contents($new);
if (!is_string($next) || strlen($next) > 65536 || strpos($next, 'ANEX_B2B_TOKEN') !== false) { exit(16); }
$line = "define('ANEX_B2B_TOKEN', base64_decode('" . base64_encode($token) . "', true));\n";
$updated = $next . $line;
if (file_put_contents($new, $updated, LOCK_EX) !== strlen($updated)) { exit(17); }
if (!chmod($new, 0600)) { exit(18); }
echo "ANEX_PRIVATE_CONFIG_READY\n";'''

_OLD_CONFIG_SWITCH = '''if test -f "$private/search3-preview.php"; then
  cp -p "$private/search3-preview.php" "$work/previous-search3-preview.php"
fi
mv "$work/search3-preview.php" "$private/search3-preview.php"'''


def _inject_preservation(script: str) -> str:
    """Replace the old config switch with a fail-closed server-only B2B transfer."""
    if script.count(_OLD_CONFIG_SWITCH) != 1:
        raise ValueError("ANEX preview private config switch drift")
    php = shlex.quote(PRESERVE_PHP)
    replacement = '''test -f "$private/search3-preview.php"
test ! -L "$private/search3-preview.php"
cp -p "$private/search3-preview.php" "$work/previous-search3-preview.php"
php -d display_errors=0 -d log_errors=0 -r ''' + php + ''' \
  "$work/previous-search3-preview.php" "$work/search3-preview.php" \
  > "$work/private-config-preserve-result.txt"
test "$(cat "$work/private-config-preserve-result.txt")" = ANEX_PRIVATE_CONFIG_READY
mv "$work/search3-preview.php" "$private/search3-preview.php"'''
    return script.replace(_OLD_CONFIG_SWITCH, replacement, 1)


def remote_script(release: str) -> str:
    return _inject_preservation(_BASE_REMOTE_SCRIPT(release))


def main() -> int:
    # ssh_deploy() resolves remote_script from the imported module's globals.
    # Replace only that boundary; payload construction, isolation, rollback and
    # sanitized receipts remain the already-reviewed publisher implementation.
    base.remote_script = remote_script
    return base.main()


if __name__ == "__main__":
    raise SystemExit(main())

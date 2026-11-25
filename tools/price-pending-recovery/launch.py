"""Run one source-bound continuation through the deployed PRICE runtime."""
import hashlib
import pathlib
import subprocess
import sys

APP = pathlib.Path('/var/www/anytoour/data/www/app.anytoour.ru')
NATIVE = pathlib.Path('/var/www/anytoour/data/www/anytoour.ru/data')
PINS = {
    'monitor-price-hook.php': '1b42c413b0d5763d02ac7fdd62218245e9577f969cf6ec34b3b5514bbc67a1c5',
    'published-hotels-monitor-v1.php': '918ad7401632de3bfce5e349627ed7b8449b10c9a8c5b0aa0014511ecc9e7c06',
    'top500-daily-collector-v1.php': '2236c151e284221b16bf19b6f42db36de5741b45108bd6a87b5d1726871d8ed9',
}


def main():
    import base64
    import re
    for name, sha in PINS.items():
        path = NATIVE / name
        assert path.resolve() == path and hashlib.sha256(path.read_bytes()).hexdigest() == sha, name
    assert hashlib.sha256(FINISH).hexdigest() == FINISH_SHA
    source = (NATIVE / 'monitor-price-hook.php').read_text()
    end = source.rfind('\n$inspect=')
    assert end > 0
    prefix = source[:end].replace('__DIR__', repr(str(NATIVE)))
    match = re.search(r'const HOOK_EDITOR_B64="([^"]+)";', prefix)
    assert match is not None
    editor = base64.b64decode(match[1], validate=True).decode()
    start = editor.index('function need(')
    stop = editor.index('// END MONITOR_PRICE_LINK_V1.')
    php = prefix + '\n' + editor[start:stop] + '\n' + FINISH.decode().removeprefix('<?php\n')
    subprocess.run(['php', '-l'], input=php, text=True, cwd=APP, check=True, stdout=subprocess.DEVNULL)
    p = subprocess.run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0'], input=php,
                       text=True, cwd=APP, capture_output=True, timeout=140)
    lines = [line for line in p.stdout.splitlines() if line.startswith('ORIGINAL_PRICE_FINISH=')]
    assert len(lines) == 1, 'original_recovery_result_missing'
    print(lines[0], flush=True)
    sys.exit(p.returncode)


if __name__ == '__main__':
    main()

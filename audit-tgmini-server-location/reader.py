"""Read only fixed Mini App file metadata under the configured deploy account."""
import hashlib
import json
import os
import re
from pathlib import Path

home = Path.home()
sites = ['anytoour.ru', 'anytour.online', 'tg.rhat.ru', 'tgz.ru', 'rhat.ru']
report = {'kind': 'tgmini_anytour_location', 'server_writes': False, 'code_executed': False,
          'database_reads': False, 'customer_log_reads': False, 'site_roots': [], 'miniapps': []}
for site in sites:
    root = home / 'www' / site
    item = {'site': site, 'exists': root.is_dir()}
    if root.is_dir():
        item['resolved_path'] = str(root.resolve())
        item['bitrix_present'] = (root/'bitrix/modules/main/include/prolog_before.php').is_file()
        folder = root/'tgmini'
        if folder.is_dir():
            files = []
            for name in ['index.php','save.php','bot_webhook.php']:
                p = folder/name
                if not p.is_file():
                    continue
                if p.stat().st_size > 300000:
                    files.append({'name': name, 'size_bound': False})
                    continue
                data = p.read_bytes()
                source = data.decode('utf-8', errors='replace')
                normalized = source.replace('\r\n','\n').strip()
                meta = {'name': name, 'size': len(data), 'sha256': hashlib.sha256(data).hexdigest(),
                        'normalized_sha256': hashlib.sha256(normalized.encode()).hexdigest(),
                        'writable': os.access(p, os.W_OK), 'mtime_unix': int(p.stat().st_mtime)}
                if name == 'save.php':
                    m = re.search(r'''\$BOT_TOKEN\s*=\s*(['"])(.*?)\1''', source)
                    meta['literal_verification_token_present'] = bool(m and m[2])
                    meta['has_get_link_call'] = 'TelegramSubApi::getLink(' in source
                    meta['has_init_data_check'] = 'checkTelegramInitData' in source
                files.append(meta)
            report['miniapps'].append({'site': site, 'folder': str(folder), 'resolved_path': str(folder.resolve()),
                                       'folder_writable': os.access(folder, os.W_OK), 'files': files})
    report['site_roots'].append(item)
print(json.dumps(report))

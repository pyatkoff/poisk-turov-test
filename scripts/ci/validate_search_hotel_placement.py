#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[2]
js = (root / 'v2' / 'hotel-autocomplete-v1.js').read_text(encoding='utf-8')
index = (root / 'v2' / 'index.php').read_text(encoding='utf-8')

for forbidden in ('.main-fields', 'mainFields', 'countryField'):
    if forbidden in js:
        raise SystemExit(f'hotel autocomplete must not reparent into primary search grid: found {forbidden!r}')

if "field.classList.add('hotel-autocomplete-field')" not in js:
    raise SystemExit('hotel autocomplete field marker is missing')

extras_start = index.find('<details class="extras">')
hotel_field = index.find('<label class="field"><span>Конкретный отель</span><select name="hotel">')
extras_end = index.find('</details>', hotel_field)

if extras_start < 0 or hotel_field < 0 or extras_end < 0:
    raise SystemExit('could not locate canonical advanced-filter hotel field')
if not (extras_start < hotel_field < extras_end):
    raise SystemExit('hotel field must remain inside details.extras advanced filters')

print('SEARCH_HOTEL_PLACEMENT_OK advanced_only=true')

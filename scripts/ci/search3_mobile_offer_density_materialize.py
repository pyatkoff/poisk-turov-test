from pathlib import Path

source = Path('src/search3/styles/results-layout.css')
text = source.read_text()
old = """& .tour-row{padding:14px}\n& .tour-action{grid-template-columns:1fr}\n& .tour-action>small,& .tour-action>.hotel-price,& .tour-action .direct-tour{grid-column:1;grid-row:auto}\n& .tour-action .direct-tour{width:100%;margin-top:6px}"""
new = """& .tour-row{padding:10px 12px;gap:9px}\n& .tour-facts{gap:5px 10px;margin-top:5px}\n& .tour-secondary-facts{gap:2px 8px;margin-top:5px;padding-top:5px}\n& .tour-action{grid-template-columns:minmax(0,1fr) minmax(128px,42%);gap:3px 10px;padding-top:8px}\n& .tour-action>small,& .tour-action>.hotel-price{grid-column:1}\n& .tour-action .direct-tour{grid-column:2;grid-row:1/3;width:auto;margin-top:0;padding-inline:10px}"""
if text.count(old) != 1:
    raise SystemExit('mobile offer density anchor mismatch')
source.write_text(text.replace(old, new, 1))

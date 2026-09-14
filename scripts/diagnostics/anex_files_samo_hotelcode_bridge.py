#!/usr/bin/env python3
"""Extract direct SAMO/Andromeda ↔ ANEX bridge evidence from files.anextour.ru URLs.

Observed contract:
  /hotel/<country>/hotel/<slug>/o<SAMO_OBJECT_ID>?...&hotelCode=<ANEX_HOTEL_CODE>

The oNNNNNN path token and hotelCode query value are kept as distinct namespaces.
This tool is evidence-only; it performs no DB writes.
"""
from __future__ import annotations
import argparse, json, re, sys
from urllib.parse import urlparse, parse_qs

HOSTS={"files.anextour.ru","files.anextour.com","cdn.anextour.ru"}
PATH_RE=re.compile(r"/hotel/([^/]+)/hotel/([^/]+)/o(\d+)(?:/|$)",re.I)

def extract(url:str):
    p=urlparse(url.strip())
    if (p.hostname or '').lower() not in HOSTS: return None
    m=PATH_RE.search(p.path)
    q=parse_qs(p.query)
    hc=(q.get('hotelCode') or q.get('hotelcode') or [None])[0]
    if not m or not hc or not str(hc).isdigit(): return None
    return {'source_url':url.strip(),'country_slug':m.group(1).lower(),'hotel_slug':m.group(2),'samo_object_id':int(m.group(3)),'anex_hotel_code':int(hc),'evidence':'direct_files_url_dual_id'}

def main():
    ap=argparse.ArgumentParser(); ap.add_argument('urls',nargs='*'); ns=ap.parse_args()
    urls=ns.urls or [x.strip() for x in sys.stdin if x.strip()]
    rows=[r for u in urls if (r:=extract(u))]
    print(json.dumps({'status':'completed','input':len(urls),'direct_bridges':len(rows),'rows':rows,'database_writes':0,'mapping_writes':0},ensure_ascii=False,indent=2))
if __name__=='__main__': main()

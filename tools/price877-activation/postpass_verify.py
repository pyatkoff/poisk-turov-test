"""Pure fail-closed verifier; no network, files or mutation."""
import re,hashlib
APP='cc017ee5bb669f4c7c13efc91d5c5812a50e55f2'
def need(v,m):
 if not v:raise RuntimeError(m)
def verify(result,before,after,journal,config,rows):
 need(result.get('ok') is True and result.get('installed_sha')==APP and result.get('roster_pairs')==877 and result.get('budget_seconds')==480,'pass_scope_changed')
 count=result.get('updated_pairs');need(type(count) is int and 0<=count<=30,'pair_limit_changed')
 need(all(result.get(k)==0 for k in ['new_posts','supplier_calls','site_writes']),'unexpected_write')
 need(not any(result.get(k) for k in ['error','child_failure','pending','preflight_block','error_counts']),'pass_errors')
 need(journal.get('ok') is True and journal.get('prefix_verified') is True and 'pending' in journal and journal['pending'] is None and journal.get('cycle_state')=='interrupted_reconciled' and journal.get('cycle_collector_run_id')=='44204094292' and journal.get('writes')==0,'journal_not_verified')
 need(journal['pin']['bytes']>=config['journal_pin']['bytes'] and journal['pin']['lines']>=config['journal_pin']['lines'],'journal_shrunk')
 bytv={r['tourvisor_hotel_id']:r for r in rows};need(len(rows)==len(bytv)==877 and set(bytv)==set(config['expected_ids']),'roster_changed')
 updates=result.get('updates',[]);need(isinstance(updates,list) and len(updates)==count,'updates_count_changed');ops=set();updated=set();allowed=set()
 for u in updates:
  tv=u['tourvisor_hotel_id'];need(tv in bytv and tv not in updated,'updated_scope_changed');updated.add(tv);row=bytv[tv]
  rid=u.get('record_id');need(isinstance(rid,str) and re.fullmatch('[a-f0-9]{64}',rid),'record_invalid')
  op='commercial-monitor-price-h'+str(tv)+'-'+rid;need(u.get('operation')==op and op not in ops and op not in config['previous_operations'],'operation_repeated_or_changed');ops.add(op)
  posts=u.get('posts',[]);need(len(posts)==2 and {p.get('platform') for p in posts}=={'max','telegram'},'pair_receipts_missing')
  for p in posts:
   need(p.get('state')=='sent' and all(p.get(k) is True for k in ['body_photo_button_verified','existing_message_reused','content_updated','price_link_verified']),'existing_pair_not_confirmed')
   need(p.get('post_url')==row['posts'][p['platform']] and p.get('logical_id')==row['logical_id'],'publication_identity_changed')
   need(type(p.get('publication_id')) is int and p['publication_id']>0 and type(p.get('version_id')) is int and type(p.get('previous_version_id')) is int and p['version_id']>p['previous_version_id']>0 and p.get('message_id'),'version_identity_invalid')
   need(p.get('price_link_url')==p.get('button_url') and isinstance(p.get('text'),str) and hashlib.sha256(p['text'].encode()).hexdigest()==p.get('text_sha256'),'content_receipt_invalid')
   mp=p.get('monitor_price',{});need(mp.get('record_id')==rid and mp.get('tourvisor_hotel_id')==tv and mp.get('price')==u.get('price'),'price_receipt_changed')
  allowed.update(row['feed_binding']['offer_ids'].values())
 names={'hotel-posts-telegram.xml':877,'hotel-posts-max.xml':877,'hotel-posts-all.xml':1754}
 need(before.get('ok') is True and after.get('ok') is True and set(before['files'])==set(after['files'])==set(names),'feeds_not_verified')
 for name,n in names.items():
  b=before['files'][name];a=after['files'][name]
  need(len(b['rows'])==len(a['rows'])==n and list(a['rows'])==list(b['rows']),'feed_members_changed')
  need(a['envelope']==b['envelope'],'feed_envelope_changed')
  for key,value in b['rows'].items():
   need(a['rows'][key]['stable']==value['stable'],'feed_nonprice_changed')
   if key not in allowed:need(a['rows'][key]['full']==value['full'],'unselected_offer_changed')
 scan=updated|set(result.get('unchanged_ids',[]))|set(result.get('held_ids',[]))|{x['tourvisor_hotel_id'] for x in result.get('deferred',[])}
 need(scan<=set(bytv),'scan_scope_changed')
 return {'ok':True,'roster_pairs':877,'updated_pairs':count,'confirmed_messages':2*count,'new_price_operations':len(ops),'updated_ids':sorted(updated),'operations':sorted(ops),'new_distinct_hotels':len(updated-set(config['previous_updated_hotel_ids'])),'cumulative_distinct_updated_hotels':len(updated|set(config['previous_updated_hotel_ids'])),'scanned_pairs':result.get('scanned_pairs'),'new_scanned_ids':sorted(scan-set(config['previous_scanned_ids'])),'cumulative_unique_scanned':len(scan|set(config['previous_scanned_ids'])),'journal':journal,'feed_counts':[877,877,1754],'all877_fresh_claimed':False}

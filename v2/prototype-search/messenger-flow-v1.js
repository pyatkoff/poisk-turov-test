'use strict';
// Browser invocation owner. Request is the canonical same-origin
// producer adapter, never a caller URL, supplier, bot sender or storage owner.
(function(root){
  const states=new Set(['prepared','unknown','revoke_unknown','ready','missing','revoked','expired','unavailable']);
  const operationId=value=>typeof value==='string'&&/^[a-f0-9]{32}$/.test(value);
  const immutable=value=>{if(value&&typeof value==='object'){Object.values(value).forEach(immutable);Object.freeze(value);}return value;};
  const failureCodes=new Set(['invalid_request','forbidden','not_found','handoff_limit','handoff_missing','handoff_stale','handoff_session_changed','signer_unavailable','retained_fact_missing','handoff_unavailable','handoff_unknown','temporarily_unavailable']);
  const failureStages=new Set(['request','transport','http','response']);
  const failureFields=new Set(['flights.retained_pair','flights.retained_pair_binding','flights.selected_labels','handoff.session','hotel.catalog_labels','offer.retained_identity','price.receiver_bounds','price.retained','quote.complete_exact_pair','quote.observed_deadline','record.checkpoint_changed','record.checkpoint_deadline','record.checkpoint_unknown','record.clock','record.current_retained','record.immutable_terms','record.integrity','record.invalid_retained','record.limit','record.observed_deadline','record.receiver_size','record.server_terms','record.source','search.current_context','selection.pins','stay.hotel_place','stay.meal','stay.operator','stay.placement','stay.retained','stay.room','trip.childAges','trip.countryUiId','trip.departure_nights','trip.origin','trip.party','trip.retained','trip.stayEnd']);
  const failureOwners=new Set(['INT','SEARCH','anex_session']);
  const invalidReply=(action,code='handoff_unavailable')=>Object.assign(new Error('handoff_unavailable'),{handoffFailure:{action,stage:'response',code}});
  // Copy only the adapter's bounded public facts, never its exception or request.
  function failure(error,action,tourvisor=false){
    const value=error?.handoffFailure,result={action,stage:'transport',code:'handoff_unavailable'};
    if(value&&(value.action===action||tourvisor&&action==='handoff_freeze'&&value.action==='tv_select')){
      result.action=value.action;
      if(failureStages.has(value.stage))result.stage=value.stage;
      if(failureCodes.has(value.code))result.code=value.code;
      if(Number.isInteger(value.httpStatus)&&value.httpStatus>=100&&value.httpStatus<=599)result.httpStatus=value.httpStatus;
      if(result.code==='retained_fact_missing'&&failureFields.has(value.field)&&failureOwners.has(value.owner)){
        result.field=value.field;result.owner=value.owner;
      }
    }
    return immutable(result);
  }
  function links(value){
    if(!value||Object.keys(value).sort().join(',')!=='max,telegram')return null;
    const result={};
    for(const [platform,host] of [['telegram','t.me'],['max','max.ru']]){
      const raw=value[platform];if(typeof raw!=='string'||raw.length>2048)return null;
      let url;try{url=new URL(raw);}catch{return null;}
      if(url.href!==raw||url.protocol!=='https:'||url.hostname!==host||url.port||url.username||url.password||url.hash||url.pathname==='/')return null;
      result[platform]=raw;
    }
    return immutable(result);
  }
  function createSession({selection,request,clock=Date.now}){
    if(!selection||typeof selection.isCurrent!=='function'||typeof (selection.selector||selection.anexSelector)!=='function'||typeof request!=='function'||typeof clock!=='function')throw Error('invalid_handoff_owner');
    const captures=new WeakMap(),operations=new Map(),failedPreparations=new Map(),preparations=new Map();let tokenTask=null;
    const selector=snapshot=>(selection.selector||selection.anexSelector).call(selection,snapshot);
    const expired=entry=>{const deadline=entry?.observation?.price?.expiresAt;return Number.isSafeInteger(deadline)&&deadline*1000<=clock();};
    const current=snapshot=>{try{return selection.isCurrent(snapshot)&&selector(snapshot)!==null&&!expired(captures.get(snapshot));}catch{return false;}};
    function view(snapshot){
      const entry=snapshot&&typeof snapshot==='object'?captures.get(snapshot):null,op=entry?.operation;
      if(!current(snapshot))return immutable({state:expired(entry)?'expired':'stale',canCheck:!!op&&!op.pending,canRevoke:!!op&&op.state!=='revoked'});
      if(!entry)return immutable({state:'idle',canCheck:false,canRevoke:false});
      const settled=op?.revokeRequested&&!['revoked','expired','missing','unavailable'].includes(op.state)?'revoke_unknown':op?.state==='prepared'&&op.attempted?'unknown':op?.state;
      const state=entry.pending?'preparing':op?.pending?op.pendingAction==='handoff_revoke'?'revoking':'pending':settled||entry.state;
      const result={state,canCheck:!!op&&!op.pending,canRevoke:!!op&&op.state!=='revoked'};
      if(entry.observation)result.observation=entry.observation;
      if(op?.state==='ready'&&op.links&&!op.pending&&!op.revokeRequested)result.links=op.links;
      if(entry.error)result.error=entry.error;
      if(entry.error&&entry.failure)result.failure=entry.failure;
      return immutable(result);
    }
    async function token(){
      if(!tokenTask)tokenTask=Promise.resolve().then(()=>request('handoff_token')).then(reply=>{
        if(reply?.ok!==true||typeof reply.csrf!=='string'||!/^[a-f0-9]{64}$/.test(reply.csrf))throw invalidReply('handoff_token');
        return reply.csrf;
      }).catch(error=>{tokenTask=null;throw error;});
      return tokenTask;
    }
    function prepare(snapshot){
      if(!current(snapshot))return Promise.resolve(view(snapshot));
      let entry=captures.get(snapshot);
      if(entry?.pending)return entry.pending.then(()=>view(snapshot));
      if(entry?.operation||entry?.state==='unavailable')return Promise.resolve(view(snapshot));
      const pins=selector(snapshot),identity=JSON.stringify(pins),failed=failedPreparations.get(identity);
      if(failed){captures.set(snapshot,failed);return Promise.resolve(view(snapshot));}
      // A new contact binding has a fresh capture, not a new retained selector.
      const pending=preparations.get(identity);
      if(pending){pending.snapshot=snapshot;captures.set(snapshot,pending);return pending.pending.then(()=>view(snapshot));}
      entry=entry||{state:'idle'};captures.set(snapshot,entry);entry.error=null;entry.failure=null;entry.snapshot=snapshot;
      // Keep failed exact identities without eviction: reopening must not replay
      // token/select/freeze. Share the existing eight-operation session bound.
      const identities=new Set([...operations.values()].map(op=>op.identity).concat([...failedPreparations.keys()],[...preparations.keys()]));
      if(identities.size>=8&&!identities.has(identity)){
        entry.state='unavailable';entry.error='handoff_unavailable';entry.failure=immutable({action:'handoff_freeze',stage:'request',code:'handoff_limit'});
        return Promise.resolve(view(snapshot));
      }
      entry.pending=Promise.resolve().then(async()=>{
        let action='handoff_token';
        try{
          const csrf=await token();if(!current(entry.snapshot))return;
          action='handoff_freeze';
          const reply=await request('handoff_freeze',{...pins,csrf}),data=reply?.data;
          if(reply?.ok!==true||!operationId(data?.operation_id)||!states.has(data.state)||!data.observation
            ||data.observation.schema!==1||data.observation.kind!=='search3_browser_observation'
            ||data.observation.price?.finalPriceVerified!==false||data.observation.price?.authority!=='browser-observation-only'
            ||pins.provider==='tourvisor'&&(!Number.isSafeInteger(data.observation.price.expiresAt)||data.observation.price.expiresAt<1))throw invalidReply(action);
          let op=operations.get(data.operation_id);const identity=JSON.stringify(pins),observation=JSON.stringify(data.observation);
          if(op&&(op.identity!==identity||op.observation!==observation)){op.links=null;op.state='unknown';throw invalidReply(action);}
          if(!op){
            if(operations.size>=8)throw invalidReply(action,'handoff_limit');
            op={id:data.operation_id,csrf,identity,observation,state:data.state==='ready'?'unknown':data.state,attempted:data.state!=='prepared',links:null,revokeRequested:data.state==='revoked'||data.state==='revoke_unknown'};
            operations.set(op.id,op);
          }else if(data.state==='revoked'||data.state==='revoke_unknown'){
            op.revokeRequested=true;op.state=data.state;op.links=null;
          }else if(data.state!=='prepared'&&!op.pending){
            op.attempted=true;op.state=data.state==='ready'?'unknown':data.state;op.links=null;
          }
          entry.operation=op;entry.observation=immutable(JSON.parse(observation));
        }catch(error){
          entry.state='unavailable';entry.error='handoff_unavailable';entry.failure=failure(error,action,pins.provider==='tourvisor');
          if(failedPreparations.size<8)failedPreparations.set(identity,entry);
        }
      }).finally(()=>{entry.pending=null;if(preparations.get(identity)===entry)preparations.delete(identity);}).then(()=>view(snapshot));
      preparations.set(identity,entry);
      return entry.pending;
    }
    function perform(snapshot,action){
      const entry=snapshot&&typeof snapshot==='object'?captures.get(snapshot):null,op=entry?.operation;
      if(!op)return Promise.resolve(view(snapshot));
      const requestedAction=action;
      if(action==='handoff_revoke'){op.revokeRequested=true;op.links=null;}
      if(op.pending){
        if(op.pendingAction===action||op.pendingRequest===action)return op.pending.then(()=>view(snapshot));
        return op.pending.then(()=>perform(snapshot,action));
      }
      if(action==='handoff_create'){
        if(!current(snapshot)||op.revokeRequested||['revoked','expired','missing','unavailable'].includes(op.state))return Promise.resolve(view(snapshot));
        if(op.attempted)action='handoff_status';
        else if(op.state!=='prepared')return Promise.resolve(view(snapshot));
      }
      if(action==='handoff_revoke'&&op.state==='revoked')return Promise.resolve(view(snapshot));
      op.pendingAction=action;op.pendingRequest=requestedAction;op.links=null;entry.error=null;entry.failure=null;
      if(action==='handoff_create'){op.attempted=true;op.state='unknown';}
      if(action==='handoff_revoke')op.state='revoke_unknown';
      op.pending=Promise.resolve().then(async()=>{
        // Selection may change between the click and this queued operation.
        if(action==='handoff_create'&&!current(snapshot))return;
        try{
          const reply=await request(action,{operation_id:op.id,csrf:op.csrf}),data=reply?.data;
          if(reply?.ok!==true||data?.operation_id!==op.id||!states.has(data.state))throw invalidReply(action);
          const ready=data.state==='ready'?links(data.links):null;
          if(data.state==='ready'&&(!ready||op.revokeRequested))throw invalidReply(action);
          op.state=data.state;op.links=ready;
          if(data.state!=='prepared')op.attempted=true;
        }catch(error){op.state=op.revokeRequested?'revoke_unknown':'unknown';entry.error='handoff_unavailable';entry.failure=failure(error,action);}
      }).finally(()=>{op.pending=null;op.pendingAction=null;op.pendingRequest=null;}).then(()=>view(snapshot));
      return op.pending;
    }
    // Recheck at the actual explicit click; a previously rendered view is historical.
    const link=(snapshot,platform)=>['telegram','max'].includes(platform)?view(snapshot).links?.[platform]||null:null;
    return Object.freeze({prepare,view,link,create:snapshot=>perform(snapshot,'handoff_create'),
      status:snapshot=>perform(snapshot,'handoff_status'),revoke:snapshot=>perform(snapshot,'handoff_revoke')});
  }
  root.AnyTourSearch3MessengerFlowV1=Object.freeze({createSession,version:1});
})(typeof window==='object'?window:globalThis);

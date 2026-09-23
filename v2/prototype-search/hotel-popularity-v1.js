'use strict';
((root) => {
  const attr=root.document?.body?.getAttribute?.('data-popular-hotel-legacy-ids')||'';
  const supplied=Array.isArray(root.AnyTourTopHotelLegacyIds)
    ? root.AnyTourTopHotelLegacyIds
    : attr.split(',').map(value=>value.trim()).filter(Boolean);
  const rankByLegacyId=new Map();
  for(const rawId of supplied){
    const id=Number(rawId);
    if(!Number.isSafeInteger(id)||id<1)continue;
    const key=String(id);
    if(!rankByLegacyId.has(key))rankByLegacyId.set(key,rankByLegacyId.size+1);
  }

  function rank(hotel){
    if(!hotel||!Array.isArray(hotel.legacyIds))return null;
    let best=null;
    for(const rawId of hotel.legacyIds){
      const id=Number(rawId);
      if(!Number.isSafeInteger(id)||id<1)continue;
      const value=rankByLegacyId.get(String(id));
      if(value!==undefined&&(best===null||value<best))best=value;
    }
    return best;
  }
  function badge(hotel){
    const value=rank(hotel);
    if(value===null)return '';
    return 'Популярный отель';
  }
  function boost(hotel){
    const value=rank(hotel);
    if(value===null)return 0;
    return .5;
  }

  root.AnyTourHotelPopularityV1=Object.freeze({
    source:'owner-top500',
    size:rankByLegacyId.size,
    rank,
    badge,
    boost,
    isPopular:hotel=>rank(hotel)!==null
  });
})(window);

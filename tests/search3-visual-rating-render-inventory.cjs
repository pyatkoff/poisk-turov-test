// Characterize the actual combined results/rating owner without booting the app.
// No supplier transport, quote, lead submission or persistent cache executes.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const acorn=require('../scripts/build/search3-js/node_modules/acorn');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8');
function functions(code,names){const nodes=acorn.parse(code,{ecmaVersion:'latest'}).body[1].expression.callee.body.body;return nodes.filter(node=>node.type==='FunctionDeclaration'&&names.includes(node.id.name)).map(node=>code.slice(node.start,node.end)).join('\n');}
const line=(code,name)=>{const match=code.match(new RegExp('^const '+name+'=[^\\n]+','m'));assert(match,name+' owner');return match[0];};
const owner=code=>line(code,'ratingValue')+'\n'+line(code,'touristAgesFallback')+'\n'+functions(code,['hotelPlaces','hotelMatch','touristAgesKey','hotelOfferPredicate','hotelOffers','recommendedHotelScore','recommendedHotelRank','sortResultItems','resultInventory','results'])+'\nconst actualHotelOffers=hotelOffers;hotelOffers=function(...args){work.hotelVisits++;const offers=actualHotelOffers(...args);return new Proxy(offers,{get(target,key,receiver){if(key===\'length\')work.lengthReads++;return Reflect.get(target,key,receiver)}})};const actualPredicate=hotelOfferPredicate;hotelOfferPredicate=function(...args){work.predicates++;const predicate=actualPredicate(...args);return offer=>{work.offerChecks++;return predicate(offer)}};globalThis.offerOwner=hotelOffers;globalThis.sortOwner=sortResultItems;globalThis.inventoryOwner=resultInventory;globalThis.resultsOwner=results;';
const search={origin:'Москва',country:'4',from:'2026-10-12',to:'2026-10-18',minNights:7,maxNights:7,adults:2,ages:[]};
const plain=value=>JSON.parse(JSON.stringify(value));
const filters=rating=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating,beach:false,family:false,spa:false});
const offer=(valid,index)=>({search:{origin:valid?'Москва':'Другой город',country:'4'},adults:2,ages:[],day:'2026-10-14',nights:7,meal:'AI',total:100000+index,operator:'TV',flight:'regular'});
const makeHotel=(id,rating,validIndex=49)=>({id,country:'4',rating,resort:'Кемер',region:'Анталья',subRegion:'',stars:5,amenities:[],beach:50,family:true,spa:true,offers:Array.from({length:50},(_,index)=>offer(index===validIndex,index))});
function environment(code,hotels,rating=false){const work={hotelVisits:0,predicates:0,offerChecks:0,lengthReads:0},ctx={Array,JSON,Math,Number,Object,Proxy,Reflect,Set,String,hotels,work,popularity:{boost:()=>0,rank:()=>null},state:{search,filters:filters(rating),favorites:[],selectedDate:null,onlyFavorites:false,sort:'price'},matchesHotelQuery:()=>true,matchesMeal:()=>true};vm.createContext(ctx);vm.runInContext(owner(code),ctx);return ctx;}
const reset=work=>{work.hotelVisits=work.predicates=work.offerChecks=work.lengthReads=0;};
const snapshot=work=>({...work});
function assertRows(actual,expected,label){
 assert.equal(actual.length,expected.length,label+' membership');
 actual.forEach((row,index)=>{assert.strictEqual(row.hotel,expected[index].hotel,label+' hotel identity/order');assert.equal(row.offers.length,expected[index].offers.length,label+' offer membership');row.offers.forEach((offer,offerIndex)=>assert.strictEqual(offer,expected[index].offers[offerIndex],label+' offer identity/order'));});
}
function legacy(ctx,rating){
 ctx.state.filters.rating=rating;reset(ctx.work);
 const items=ctx.sortOwner(ctx.hotels.map(h=>({hotel:h,offers:ctx.offerOwner(h)})).filter(row=>row.offers.length));
 const model={filters:{...ctx.state.filters,rating:false},selectedDate:null,onlyFavorites:false};
 const ratingCounts=ctx.hotels.reduce((counts,h)=>{if(!ctx.offerOwner(h,{...model,firstOnly:true}).length)return counts;counts.total++;if(h.rating>=4.5)counts.rating++;return counts;},{total:0,rating:0});
 return {items,total:items.reduce((sum,row)=>sum+row.offers.length,0),ratingCounts:plain(ratingCounts),work:snapshot(ctx.work)};
}
function measured(code=source,rating=false,validIndex=49,verify=true){
 const hotels=Array.from({length:100},(_,index)=>makeHotel(index+1,index%2?4:index%4?4.8:4.5,validIndex)),refs=hotels.map(h=>[h,h.offers,...h.offers]),before=JSON.stringify(hotels),ctx=environment(code,hotels,rating);
 const previous=legacy(ctx,rating);reset(ctx.work);ctx.state.filters.rating=rating;const current=ctx.inventoryOwner(),work=snapshot(ctx.work);
 if(verify){assertRows(current.items,previous.items,'rating='+rating);assert.equal(current.total,previous.total,'rating='+rating+' offer total');assert.deepEqual(plain(current.ratingCounts),previous.ratingCounts,'rating='+rating+' counts');}
 assert.equal(JSON.stringify(hotels),before,'inventory preserves raw values/order');
 refs.forEach((row,index)=>{assert.strictEqual(hotels[index],row[0]);assert.strictEqual(hotels[index].offers,row[1]);hotels[index].offers.forEach((offer,offerIndex)=>assert.strictEqual(offer,row[offerIndex+2]));});
 return {counts:plain(current.ratingCounts),previous:previous.work,current:work,items:current.items.length};
}
const off=measured(source,false),on=measured(source,true);
assert.deepEqual(off,{counts:{total:100,rating:50},previous:{hotelVisits:200,predicates:200,offerChecks:10000,lengthReads:300},current:{hotelVisits:100,predicates:100,offerChecks:5000,lengthReads:100},items:100});
assert.deepEqual(on,{counts:{total:100,rating:50},previous:{hotelVisits:200,predicates:150,offerChecks:7500,lengthReads:250},current:{hotelVisits:100,predicates:100,offerChecks:5000,lengthReads:100},items:50});
assert.notDeepEqual(measured(source.replace('const hotelRating=ratingValue(h),rated=hotelRating>=4.5','const hotelRating=ratingValue(h),rated=hotelRating>4.5'),true,49,false).counts,on.counts,'4.5 threshold mutation detected');
assert.notDeepEqual(measured(source.replace('{filters:{...state.filters,rating:false}}','{filters:state.filters}'),true,49,false).counts,on.counts,'base-rating reset mutation detected');
const early=measured(source,true,0);
assert.equal(early.current.offerChecks,2550,'unrated hotels stop at their first eligible offer');
const unbounded=measured(source.replace('{...options,firstOnly:true}','{...options,firstOnly:false}'),true,0);
assert.equal(unbounded.current.offerChecks,5000,'first-only mutation performs full scans');

// Native reduce visits inherited slots, skips holes and snapshots initial length.
{
 const inherited=makeHotel(2,4),prototype=Object.create(Array.prototype);prototype[1]=inherited;
 const hotels=[];Object.setPrototypeOf(hotels,prototype);hotels.length=4;hotels[0]=makeHotel(1,4.8);hotels[3]=makeHotel(3,4.5);
 const ctx=environment(source,hotels,true),inventory=ctx.inventoryOwner();
 assert.deepEqual(plain(inventory.ratingCounts),{total:3,rating:2},'inherited slots count while sparse holes remain empty');
 assert.equal(inventory.total,2,'inherited rated slots contribute their exact offer total');
 assert.deepEqual(Array.from(inventory.items,row=>row.hotel.id),[1,3],'rated result order follows native reduce slots');
}
{
 const appended=makeHotel(2,4.8),first=makeHotel(1,4.8),offers=first.offers,hotels=[first];let extended=false;
 Object.defineProperty(first,'offers',{get(){if(!extended){extended=true;hotels.push(appended);}return offers;}});
 const ctx=environment(source,hotels,false),inventory=ctx.inventoryOwner();
 assert.deepEqual(plain(inventory.ratingCounts),{total:1,rating:1},'pass keeps the initial hotel length');assert.equal(inventory.items.length,1);assert.equal(inventory.total,1);assert.equal(hotels.length,2,'fixture appended after reduce began');
}

// Result headings consume the same inventory total. The current pass reads each
// offer-array length once and performs no second retained-row traversal.
function totalWork(code){
 const hotels=Array.from({length:1000},(_,index)=>makeHotel(index+1,index%2?4:4.8,0)),ctx=environment(code,hotels,false);reset(ctx.work);
 const inventory=ctx.inventoryOwner();let traversals=0,total;
 if(code.includes('total=inventory.total'))total=inventory.total;
 else total=inventory.items.reduce((sum,row)=>{traversals++;return sum+row.offers.length;},0);
 return {hotels,inventory,total,traversals,work:snapshot(ctx.work)};
}
const previousTotalSource=source
 .replace('const offerCount=offers.length;if(!offerCount)return value;','if(!offers.length)return value;')
 .replace('value.items.push({hotel:h,offers,rating:hotelRating});value.total+=offerCount;','value.items.push({hotel:h,offers,rating:hotelRating});')
 .replace('{items:[],total:0,ratingCounts:','{items:[],ratingCounts:')
 .replace('total=inventory.total','total=items.reduce((s,r)=>s+r.offers.length,0)');
const previousTotal=totalWork(previousTotalSource),currentTotal=totalWork(source);
assert.equal(currentTotal.total,previousTotal.total);assert.equal(currentTotal.total,1000);
assert.deepEqual([previousTotal.traversals,currentTotal.traversals],[1000,0]);
assert.deepEqual([previousTotal.work.lengthReads,currentTotal.work.lengthReads],[2000,1000]);
currentTotal.inventory.items.forEach((row,index)=>{assert.strictEqual(row.hotel,currentTotal.hotels[index]);row.offers.forEach(offer=>assert(row.hotel.offers.includes(offer)));});
const repeatedLength=totalWork(source.replace('value.total+=offerCount','value.total+=offers.length'));
assert.equal(repeatedLength.total,currentTotal.total);assert.equal(repeatedLength.work.lengthReads,2000,'repeated offer length read detected');
assert.notEqual(totalWork(source.replace('value.total+=offerCount','value.total+=offerCount+1')).total,currentTotal.total,'total mutation detected');
console.log('PASS result offer total inventory: 1000 exact offer totals; retained-row traversal 1000 → 0, offer-array length reads 2000 → 1000; sparse/inherited/initial-length and raw identities retained');

// Shared counts are consumed only for the applied model; a draft recalculates.
{
 const calls=[],ctx={filterDraft:null,renderFilters:(...args)=>calls.push(['renderFilters',args]),updateFacetCounts:(...args)=>calls.push(['updateFacetCounts',args]),syncFilterResetState:()=>calls.push(['sync'])};
 vm.createContext(ctx);vm.runInContext(functions(source,['refreshResultFilters']),ctx);
 ctx.refreshResultFilters({keepFilters:false},{rating:7});assert.deepEqual(calls.splice(0),[['renderFilters',[7]]]);
 ctx.refreshResultFilters({keepFilters:true},{rating:7});assert.deepEqual(calls.splice(0),[['updateFacetCounts',[7]],['sync']]);
 ctx.filterDraft={};ctx.refreshResultFilters({keepFilters:false},{rating:7});assert.deepEqual(calls.splice(0),[['renderFilters',[]]],'draft does not reuse applied counts');
 ctx.filterDraft=null;ctx.refreshResultFilters({keepFilters:true});assert.deepEqual(calls.splice(0),[['updateFacetCounts',[]],['sync']],'standalone refresh recalculates');
}
assert(source.includes('const inventory=resultInventory(),items=inventory.items'),'render uses one results/rating inventory');
assert(source.includes('renderActive(inventory.ratingCounts)')&&source.includes('refreshResultFilters(options,inventory.ratingCounts)'),'one render shares exact ephemeral counts');
console.log('PASS results/rating render inventory: rating-off visits '+off.previous.hotelVisits+'->'+off.current.hotelVisits+', predicates '+off.previous.predicates+'->'+off.current.predicates+', offer checks '+off.previous.offerChecks+'->'+off.current.offerChecks+'; rating-on visits '+on.previous.hotelVisits+'->'+on.current.hotelVisits+', predicates '+on.previous.predicates+'->'+on.current.predicates+', offer checks '+on.previous.offerChecks+'->'+on.current.offerChecks+'; exact rows/counts/raw identity/order/sparse/inherited/initial-length/draft divergence preserved; supplier/lead HTTP 0');

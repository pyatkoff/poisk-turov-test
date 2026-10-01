// Characterize the actual rating-facet owner without booting the application.
// No supplier transport, quote, lead submission or persistent cache executes.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const acorn=require('../scripts/build/search3-js/node_modules/acorn');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8');
function functions(code,names){const nodes=acorn.parse(code,{ecmaVersion:'latest'}).body[1].expression.callee.body.body;return nodes.filter(node=>node.type==='FunctionDeclaration'&&names.includes(node.id.name)).map(node=>code.slice(node.start,node.end)).join('\n');}
const line=(code,name)=>{const match=code.match(new RegExp('^const '+name+'=[^\\n]+','m'));assert(match,name+' owner');return match[0];};
const owner=code=>line(code,'ratingValue')+'\n'+functions(code,['hotelPlaces','hotelMatch','hotelOfferPredicate','hotelOffers','ratingFacetCounts'])+'\n'+line(code,'countMatchingHotels')+`;globalThis.countOwner=countMatchingHotels;globalThis.ratingOwner=ratingFacetCounts;
const actualHotelOffers=hotelOffers;hotelOffers=function(...args){work.hotelVisits++;return actualHotelOffers(...args)};
const actualPredicate=hotelOfferPredicate;hotelOfferPredicate=function(...args){work.predicates++;const predicate=actualPredicate(...args);return offer=>{work.offerChecks++;return predicate(offer)}};`;
const search={origin:'Москва',country:'4',from:'2026-10-12',to:'2026-10-18',minNights:7,maxNights:7,adults:2,ages:[]};
const plain=value=>JSON.parse(JSON.stringify(value));
const filters=()=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
const offer=(valid,index)=>({search:{origin:valid?'Москва':'Другой город',country:'4'},adults:2,ages:[],day:'2026-10-14',nights:7,meal:'AI',total:100000+index,operator:'TV',flight:'regular'});
const makeHotel=(id,rating)=>({id,country:'4',rating,resort:'Кемер',region:'Анталья',subRegion:'',stars:5,amenities:[],beach:50,family:true,spa:true,offers:Array.from({length:50},(_,index)=>offer(index===49,index))});
function environment(code,hotels){const work={hotelVisits:0,predicates:0,offerChecks:0},ctx={Array,JSON,Math,Number,Object,Set,String,hotels,work,state:{search,favorites:[]},matchesHotelQuery:()=>true,matchesMeal:()=>true};vm.createContext(ctx);vm.runInContext(owner(code),ctx);return ctx;}
const reset=work=>{work.hotelVisits=work.predicates=work.offerChecks=0;};
const snapshot=work=>({...work});
function measured(code=source,verify=true){
 const hotels=Array.from({length:100},(_,index)=>makeHotel(index+1,index%2?4:index%4?4.8:4.5)),refs=hotels.map(h=>[h,h.offers]),before=JSON.stringify(hotels),ctx=environment(code,hotels);
 const base={filters:filters(),selectedDate:null,onlyFavorites:false},rated={...base,filters:{...base.filters,rating:true}};
 reset(ctx.work);const total=ctx.countOwner(base),rating=ctx.countOwner(rated),filterRating=ctx.countOwner(rated),legacy=snapshot(ctx.work);
 reset(ctx.work);const counts=plain(ctx.ratingOwner(rated)),current=snapshot(ctx.work);
 assert.deepEqual({total,rating,filterRating},{total:100,rating:50,filterRating:50});if(verify)assert.deepEqual(counts,{total:100,rating:50});
 assert.equal(JSON.stringify(hotels),before,'rating counting preserves raw values/order');refs.forEach(([hotel,offers],index)=>{assert.strictEqual(hotels[index],hotel);assert.strictEqual(hotel.offers,offers);});
 return {counts,legacy,current};
}
const result=measured();
assert.deepEqual(result.legacy,{hotelVisits:300,predicates:200,offerChecks:10000});
assert.deepEqual(result.current,{hotelVisits:100,predicates:100,offerChecks:5000});
assert.notDeepEqual(measured(source.replace('if(ratingValue(h)>=4.5)counts.rating++','if(ratingValue(h)>4.5)counts.rating++'),false).counts,result.counts,'4.5 threshold mutation detected');
assert.notDeepEqual(measured(source.replace('filters:{...model.filters,rating:false}','filters:model.filters'),false).counts,result.counts,'base-rating reset mutation detected');

// Native reduce visits inherited slots, skips holes and snapshots initial length.
{
 const inherited=makeHotel(2,4),prototype=Object.create(Array.prototype);prototype[1]=inherited;
 const hotels=[];Object.setPrototypeOf(hotels,prototype);hotels.length=4;hotels[0]=makeHotel(1,4.8);hotels[3]=makeHotel(3,4.5);
 const ctx=environment(source,hotels),counts=plain(ctx.ratingOwner({filters:{...filters(),rating:true},selectedDate:null,onlyFavorites:false}));
 assert.deepEqual(counts,{total:3,rating:2},'inherited slots count while sparse holes remain empty');
}
{
 const appended=makeHotel(2,4.8),first=makeHotel(1,4.8),offers=first.offers,hotels=[first];let extended=false;
 Object.defineProperty(first,'offers',{get(){if(!extended){extended=true;hotels.push(appended);}return offers;}});
 const ctx=environment(source,hotels),counts=plain(ctx.ratingOwner({filters:filters(),selectedDate:null,onlyFavorites:false}));
 assert.deepEqual(counts,{total:1,rating:1},'pass keeps the initial hotel length');assert.equal(hotels.length,2,'fixture appended after reduce began');
}

// Shared counts are consumed only for the applied model; a draft recalculates.
{
 const calls=[],ctx={filterDraft:null,renderFilters:(...args)=>calls.push(['renderFilters',args]),updateFacetCounts:(...args)=>calls.push(['updateFacetCounts',args]),syncFilterResetState:()=>calls.push(['sync'])};
 vm.createContext(ctx);vm.runInContext(functions(source,['refreshResultFilters']),ctx);
 ctx.refreshResultFilters({keepFilters:false},{rating:7});assert.deepEqual(calls.splice(0),[['renderFilters',[7]]]);
 ctx.refreshResultFilters({keepFilters:true},{rating:7});assert.deepEqual(calls.splice(0),[['updateFacetCounts',[7]],['sync']]);
 ctx.filterDraft={};ctx.refreshResultFilters({keepFilters:false},{rating:7});assert.deepEqual(calls.splice(0),[['renderFilters',[]]],'draft does not reuse applied counts');
 ctx.filterDraft=null;ctx.refreshResultFilters({keepFilters:true});assert.deepEqual(calls.splice(0),[['updateFacetCounts',[]],['sync']],'standalone refresh recalculates');
}
assert(source.includes('const ratingCounts=renderActive();updateNav();renderSummary();updateURL();refreshResultFilters(options,ratingCounts);'),'one render wires the ephemeral result to filter refresh');
console.log(`PASS rating render inventory: hotel eligibility ${result.legacy.hotelVisits}->${result.current.hotelVisits}; predicates ${result.legacy.predicates}->${result.current.predicates}; offer checks ${result.legacy.offerChecks}->${result.current.offerChecks}; exact counts/raw identity/order/sparse/inherited/initial-length/draft divergence preserved; supplier/lead HTTP 0`);

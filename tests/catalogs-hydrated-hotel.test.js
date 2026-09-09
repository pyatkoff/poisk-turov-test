'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync(require('node:path').join(__dirname,'../v2/catalogs-v2.js'),'utf8');
function fixture(region='',reply=[]){
 const hotel={value:'9365',selectedIndex:0,options:[{textContent:'Отель #9365'}]};let calls=0;
 const form={elements:{hotel,country:{value:'1'},region:{value:region},subregion:{value:''}}};
 const context={form,active:()=>true,api:async()=>{calls++;if(reply instanceof Error)throw reply;return reply;},catalogRating:()=>'',console:{warn(){}},fillSelect(el,items){const current=el.value;el.value=items.some(x=>String(x.id)===current)?current:'';}};
 vm.createContext(context);vm.runInContext(source.split('\n').find(l=>l.startsWith('async function loadHotels('))+'\nthis.load=loadHotels;',context);
 return{hotel,form,load:context.load,calls:()=>calls};
}
test('lazy hotel catalog keeps URL choice without a resort and without API',async()=>{const f=fixture();await f.load(1,true);assert.equal(f.hotel.value,'9365');assert.equal(f.calls(),0);});
test('bounded lazy list does not erase a selected hotel absent from its first page',async()=>{const f=fixture('4',[{id:123,name:'Another hotel'}]);await f.load(1,true);assert.equal(f.hotel.value,'9365');assert.equal(f.calls(),1);});
test('explicit resort changes retain normal incompatible hotel reset',async()=>{const f=fixture('4',[{id:123}]);await f.load(1);assert.equal(f.hotel.value,'');});
test('lazy catalog failure retains selection; cleared country does not',async()=>{const f=fixture('4',new Error('offline'));await f.load(1,true);assert.equal(f.hotel.value,'9365');f.form.elements.country.value='';await f.load(1,true);assert.equal(f.hotel.value,'');});

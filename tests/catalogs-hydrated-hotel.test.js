'use strict';

const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');

const source=fs.readFileSync(path.join(__dirname,'../v2/catalogs-v2.js'),'utf8');

function fixture(region='',reply=[]){
 const hotel={value:'9365',selectedIndex:0,options:[{textContent:'Отель #9365'}]};
 let calls=0;
 const form={elements:{hotel,country:{value:'1'},region:{value:region},subregion:{value:''}}};
 const context={
  form,
  active:()=>true,
  api:async()=>{calls++;if(reply instanceof Error)throw reply;return reply;},
  catalogRating:()=>'',
  console:{warn(){}},
  fillSelect(el,items){
   const current=el.value;
   el.value=items.some(item=>String(item.id)===current)?current:'';
  }
 };
 vm.createContext(context);
 vm.runInContext(source.split('\n').find(line=>line.startsWith('async function loadHotels('))+'\nthis.load=loadHotels;',context);
 return{hotel,form,load:context.load,calls:()=>calls};
}

function childAgeFixture(count='1'){
 const appended=[];
 const childCount={value:String(count),attributes:{},setAttribute(name,value){this.attributes[name]=String(value);}};
 const childAges={hidden:true,innerHTML:'',querySelectorAll(){return[];},appendChild(node){appended.push(node);}};
 const context={childCount,childAges,document:{createElement(){return{className:'',innerHTML:''};}},Array,Number,String};
 vm.createContext(context);
 const lines=source.split('\n');
 const labelLine=lines.find(line=>line.startsWith('function childAgeLabel('));
 const renderLine=lines.find(line=>line.startsWith('function renderChildAges('));
 assert.ok(labelLine,'childAgeLabel owner must exist');
 assert.ok(renderLine,'renderChildAges owner must exist');
 vm.runInContext(labelLine+'\n'+renderLine+'\nthis.render=renderChildAges;this.labelForAge=childAgeLabel;',context);
 return{childCount,childAges,appended,render:context.render,labelForAge:context.labelForAge};
}

test('lazy hotel catalog keeps URL choice without a resort and without API',async()=>{
 const f=fixture();
 await f.load(1,true);
 assert.equal(f.hotel.value,'9365');
 assert.equal(f.calls(),0);
});

test('bounded lazy list does not erase a selected hotel absent from its first page',async()=>{
 const f=fixture('4',[{id:123,name:'Another hotel'}]);
 await f.load(1,true);
 assert.equal(f.hotel.value,'9365');
 assert.equal(f.calls(),1);
});

test('explicit resort changes retain normal incompatible hotel reset',async()=>{
 const f=fixture('4',[{id:123}]);
 await f.load(1);
 assert.equal(f.hotel.value,'');
});

test('lazy catalog failure retains selection; cleared country does not',async()=>{
 const f=fixture('4',new Error('offline'));
 await f.load(1,true);
 assert.equal(f.hotel.value,'9365');
 f.form.elements.country.value='';
 await f.load(1,true);
 assert.equal(f.hotel.value,'');
});

test('child-age picker uses customer copy for infants and exposes expanded state',()=>{
 const f=childAgeFixture('1');
 assert.equal(f.labelForAge(0),'до 1 года');
 assert.equal(f.labelForAge(1),'1 год');
 assert.equal(f.labelForAge(2),'2 года');
 assert.equal(f.labelForAge(5),'5 лет');
 f.render();
 assert.equal(f.childCount.attributes['aria-expanded'],'true');
 assert.equal(f.childAges.hidden,false);
 assert.equal(f.appended.length,1);
 assert.match(f.appended[0].innerHTML,/>до 1 года<\/option>/);
 assert.doesNotMatch(f.appended[0].innerHTML,/>0 лет<\/option>/);
 f.childCount.value='0';
 f.render();
 assert.equal(f.childCount.attributes['aria-expanded'],'false');
 assert.equal(f.childAges.hidden,true);
});

'use strict';

const fs=require('fs');
const path=require('path');

const source=fs.readFileSync(path.join(__dirname,'..','v2','search-continue-v6.js'),'utf8');

if(!source.includes('Проверим дополнительные предложения у туроператоров')){
  throw new Error('neutral continuation helper copy missing');
}
if(!source.includes('Дополнительный поиск не завершился за 75 секунд')){
  throw new Error('neutral continuation timeout copy missing');
}
for(const provider of ['Tourvisor','Андромеда','ANEX API','Источник предложения']){
  if(source.includes(provider))throw new Error('customer continuation copy leaks provider jargon: '+provider);
}
if(!source.includes("rt.api('search_continue',{searchId:id})")||!source.includes("rt.api('search_results',{searchId:id,limit:100})")){
  throw new Error('continuation transport contract changed unexpectedly');
}

console.log('search3 continuation copy: neutral provider copy + unchanged continuation transport');

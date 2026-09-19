/* Shared acceptance against the actual served form; no extra provider requests. */
const assert=require('node:assert/strict');
const path=require('node:path');
async function setParty(page,adults,ages){
 const trigger=page.locator('[data-search3-parameter=party]');
 if(await trigger.isVisible()){
  await trigger.click();const dialog=page.getByRole('dialog',{name:'Кто едет',exact:true});
  let count=Number(await dialog.locator('output').textContent());
  while(count!==adults){await dialog.getByRole('button',{name:count<adults?'Увеличить число взрослых':'Уменьшить число взрослых'}).click();count+=count<adults?1:-1}
  while(await dialog.locator('[data-age]').count()>ages.length)await dialog.locator('[data-remove]').last().click();
  while(await dialog.locator('[data-age]').count()<ages.length)await dialog.getByRole('button',{name:'Добавить ребёнка +',exact:true}).click();
  for(let i=0;i<ages.length;i++)await dialog.getByLabel('Возраст ребёнка '+(i+1),{exact:true}).selectOption(String(ages[i]));
  await dialog.getByRole('button',{name:'Выбрать',exact:true}).click();await dialog.waitFor({state:'hidden'});
 }else{
  await page.locator('#tourSearch [name=count_people]').selectOption(String(adults));
  await page.locator('#tourSearch [name=child_count]').selectOption(String(ages.length));
  for(let i=0;i<ages.length;i++)await page.locator('#childAges select').nth(i).selectOption(String(ages[i]));
 }
}
async function setNights(page,from,to){
 const trigger=page.locator('[data-search3-parameter=nights]');
 if(await trigger.isVisible()){
  await trigger.click();const dialog=page.getByRole('dialog',{name:'На сколько ночей',exact:true});
  await dialog.locator('[data-night="'+from+'"]').click();if(from!==to)await dialog.locator('[data-night="'+to+'"]').click();
  await dialog.locator('.search-parameter-apply').click();await dialog.waitFor({state:'hidden'});
 }else{await page.locator('#tourSearch [name=daysFrom]').selectOption(String(from));await page.locator('#tourSearch [name=daysTill]').selectOption(String(to))}
}
async function setDates(page,from,to){
 const trigger=page.locator('[data-search3-parameter=dates]');
 if(await trigger.isVisible()){
  await trigger.click();const dialog=page.getByRole('dialog',{name:'Даты вылета',exact:true});
  await dialog.getByLabel('Вылет с',{exact:true}).fill(from);await dialog.getByLabel('Вылет до',{exact:true}).fill(to);
  await dialog.locator('.search-parameter-apply').click();await dialog.waitFor({state:'hidden'});
 }else{await page.locator('#tourSearch [name=dateFrom]').fill(from);await page.locator('#tourSearch [name=dateTo]').fill(to)}
}
async function checkMobileParameters(page,width,output){
 if(width>700)return;
 const snapshot=()=>page.locator('#tourSearch').evaluate(form=>({data:[...new FormData(form)],generation:window.V2SearchLifecycle.generation,dirty:window.V2SearchLifecycle.dirty}));
 const before=await snapshot(),dialog=page.locator('.search-parameter-dialog');
 assert.equal(await page.locator('[data-search3-parameter]:visible').count(),3,'all three primary parameter groups remain visible');
 for(const trigger of await page.locator('[data-search3-parameter]').all())assert.ok((await trigger.boundingBox()).height>=44);
 await page.locator('[data-search3-parameter=party]').click();
 await dialog.getByRole('button',{name:'Увеличить число взрослых'}).click();
 await dialog.getByRole('button',{name:'Добавить ребёнка +',exact:true}).click();
 assert.deepEqual(await snapshot(),before,'editing the party draft changes neither canonical values nor lifecycle');
 await dialog.getByRole('button',{name:'Выбрать',exact:true}).click();
 assert.equal(await dialog.isVisible(),true,'missing age cannot be applied');
 assert.match(await dialog.getByRole('alert').innerText(),/возраст каждого ребёнка/);
 assert.deepEqual(await snapshot(),before,'invalid draft still leaves canonical values intact');
 await dialog.getByRole('button',{name:'Отмена',exact:true}).click();
 assert.deepEqual(await snapshot(),before,'Cancel discards every draft value');
 assert.equal(await page.locator('[data-search3-parameter=party]').evaluate(node=>node===document.activeElement),true,'Cancel returns focus to its trigger');
 await setParty(page,6,['0','17','6']);
 assert.deepEqual(await page.locator('#tourSearch').evaluate(form=>{const f=new FormData(form);return [f.get('count_people'),f.get('child_count'),...f.getAll('child_age[]')]}),['6','3','0','17','6']);
 await page.locator('[data-search3-parameter=party]').click();
 assert.equal(await dialog.getByRole('button',{name:'Увеличить число взрослых'}).isDisabled(),true);
 assert.equal(await dialog.getByRole('button',{name:'Добавить ребёнка +',exact:true}).isDisabled(),true);
 await dialog.screenshot({path:path.join(output,`mobile-party-${width}.png`)});
 await dialog.getByRole('button',{name:'Убрать ребёнка 2',exact:true}).click();
 assert.deepEqual(await dialog.locator('[data-age]').evaluateAll(nodes=>nodes.map(node=>node.value)),['0','6'],'removing the middle child preserves the other exact ages');
 await page.keyboard.press('Escape');assert.equal(await dialog.isVisible(),false);
 assert.deepEqual(await page.locator('#childAges select').evaluateAll(nodes=>nodes.map(node=>node.value)),['0','17','6'],'Escape also discards child removal');
 await page.locator('[data-search3-parameter=nights]').click();
 await dialog.locator('[data-night="1"]').click();await dialog.locator('[data-night="28"]').click();
 assert.match(await dialog.getByRole('alert').innerText(),/не больше 10/);
 await dialog.getByRole('button',{name:'Отмена',exact:true}).click();
 await setNights(page,28,28);
 assert.equal(await page.locator('#tourSearch [name=daysTill]').inputValue(),'28');
 await page.locator('[data-search3-parameter=nights]').click();
 await dialog.getByRole('button',{name:'7–10 ночей',exact:true}).click();
 await dialog.screenshot({path:path.join(output,`mobile-nights-${width}.png`)});
 await dialog.locator('.search-parameter-apply').click();
 const priorDates=await page.locator('#tourSearch').evaluate(form=>[form.elements.dateFrom.value,form.elements.dateTo.value]);
 await page.locator('[data-search3-parameter=dates]').click();
 await dialog.getByLabel('Вылет с',{exact:true}).fill('2099-09-10');await dialog.getByLabel('Вылет до',{exact:true}).fill('2099-09-09');
 await dialog.locator('.search-parameter-apply').click();
 assert.match(await dialog.getByRole('alert').innerText(),/раньше первой/);
 assert.deepEqual(await page.locator('#tourSearch').evaluate(form=>[form.elements.dateFrom.value,form.elements.dateTo.value]),priorDates);
 await dialog.getByLabel('Вылет до',{exact:true}).fill('2099-10-02');await dialog.locator('.search-parameter-apply').click();
 assert.match(await dialog.getByRole('alert').innerText(),/не больше 21/);
 await dialog.getByLabel('Вылет до',{exact:true}).fill('2099-09-12');await dialog.getByRole('button',{name:'±3 дня',exact:true}).click();
 assert.equal(await dialog.getByLabel('Вылет с',{exact:true}).inputValue(),'2099-09-07');
 await dialog.getByRole('button',{name:'±1 день',exact:true}).click();
 assert.equal(await dialog.getByLabel('Вылет с',{exact:true}).inputValue(),'2099-09-09','flex choices use the same center, not repeated subtraction');
 await dialog.screenshot({path:path.join(output,`mobile-dates-${width}.png`)});
 await dialog.getByRole('button',{name:'Отмена',exact:true}).click();
 assert.deepEqual(await page.locator('#tourSearch').evaluate(form=>[form.elements.dateFrom.value,form.elements.dateTo.value]),priorDates);
 // A calendar selection applies the exact ISO range without submitting.
 await page.locator('[data-search3-parameter=dates]').click();
 await dialog.getByLabel('Вылет с',{exact:true}).fill('2099-09-10');await dialog.getByLabel('Вылет до',{exact:true}).fill('2099-09-12');
 await dialog.locator('[data-day="2099-09-10"]').click();await dialog.locator('[data-day="2099-09-12"]').click();await dialog.locator('.search-parameter-apply').click();
 assert.deepEqual(await page.locator('#tourSearch').evaluate(form=>[form.elements.dateFrom.value,form.elements.dateTo.value]),['2099-09-10','2099-09-12']);
 assert.equal((await snapshot()).generation,before.generation,'Apply never starts a search');
 await page.locator('[data-search3-parameter=dates]').click();
 const box=await dialog.boundingBox();assert.ok(box.x>=0&&box.x+box.width<=width+1,'dialog fits narrow screen');
 const oldHeight=page.viewportSize().height;await page.setViewportSize({width,height:568});
 assert.equal(await dialog.locator('.search-parameter-apply').evaluate(node=>{const r=node.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight}),true,'Apply stays visible on a short screen');
 await page.keyboard.press('Tab');assert.equal(await dialog.evaluate(node=>node.contains(document.activeElement)),true,'native modal retains keyboard focus');
 await page.keyboard.press('Escape');await page.setViewportSize({width,height:oldHeight});
 // Restore starting values so other canonical owner checks keep their own fixture.
 const entries=before.data,first=name=>entries.find(pair=>pair[0]===name)?.[1];
 await setParty(page,Number(first('count_people')),entries.filter(pair=>pair[0]==='child_age[]').map(pair=>pair[1]));
 await setNights(page,Number(first('daysFrom')),Number(first('daysTill')));
 await page.locator('[data-search3-parameter=dates]').click();
 await dialog.getByLabel('Вылет с',{exact:true}).fill(priorDates[0]);await dialog.getByLabel('Вылет до',{exact:true}).fill(priorDates[1]);await dialog.locator('.search-parameter-apply').click();
 assert.deepEqual((await snapshot()).data,before.data,'all canonical fields retain their original names and values');
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
}
module.exports={setParty,setNights,setDates,checkMobileParameters};

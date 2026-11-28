'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');

module.exports = async function captureCanonicalNextJourney(page, output, viewport) {
  const screen = await page.evaluate(() => ({
    width:innerWidth, height:innerHeight, dpr:devicePixelRatio, touch:navigator.maxTouchPoints>0,
    route:location.pathname, entry:window.AnyTourPublicConfigV1?.entry,
    profile:window.AnyTourPublicConfigV1?.profile,
    api:window.V2_CONFIG?.api, anexApi:window.V2_CONFIG?.anexApi
  }));
  assert.deepEqual([screen.width,screen.height],[viewport.width,viewport.height]);
  assert.equal(screen.route,'/_preview/search3-next-candidate/poisk-turov/');
  assert.equal(screen.entry,'search');
  assert.equal(screen.profile,'review');
  assert.equal(screen.api,null);
  assert.equal(screen.anexApi,null);
  const handheld=viewport.width===430;
  assert.equal(screen.touch,handheld);
  assert.equal(screen.dpr,handheld?3:1);
  const action=locator=>handheld?locator.tap():locator.click();
  const firstPrice=page.locator('#hotel-900001 .starting-price strong');
  const value=async locator=>Number((await locator.textContent()).replace(/\D/g,''));
  const frames=[];
  async function frame(stage){
    const name='canonical-next-journey-'+viewport.width+'x'+viewport.height+'-'+stage+'.png';
    await page.screenshot({path:path.join(output,name),animations:'disabled'});
    frames.push({stage,name});
  }
  await firstPrice.waitFor();
  assert.equal(await value(firstPrice),186400);
  await frame('results');

  await action(page.locator('#hotel-900001 h3 [data-action="hotel-details"]'));
  const room=page.locator('#modal[open] [data-action="select-hotel-offer"][data-key="demo-flight-24"]');
  await room.waitFor();
  await room.scrollIntoViewIfNeeded();
  await frame('hotel');

  await action(room);
  const application=page.locator('#modal[open] .application-review-card');
  await application.waitFor();
  const footer=page.locator('#modal-footer .footer-total>strong');
  assert.equal(await value(footer),186400);
  const contactFlights=page.locator('#modal[open] [data-action="contact-flights"]');
  await action(contactFlights);
  const flight=page.locator('#tour-flight-options [name="flight-pair"][value="2"]');
  await flight.waitFor();
  await action(flight);
  assert.equal(await flight.isChecked(),true);
  await frame('flight');
  await action(page.locator('#modal[open] [data-action="apply-flight"]'));

  await application.waitFor();
  const breakdown=page.locator('#modal .application-price-breakdown');
  const total=breakdown.locator('[data-price-part="total"] dd');
  await total.waitFor();
  const priceParts={};
  for(const key of ['tour','flight','total']) {
    priceParts[key]=await value(breakdown.locator('[data-price-part="'+key+'"] dd'));
  }
  assert.deepEqual(priceParts,{tour:186400,flight:2600,total:189000});
  assert.equal(await value(footer),189000);
  await frame('application');
  await breakdown.scrollIntoViewIfNeeded();
  await frame('price');

  await action(page.locator('#messenger-entry [data-action="modal-back"]'));
  const retained=page.locator('#modal[open] [data-action="select-hotel-offer"][data-key="demo-flight-24"][aria-pressed="true"]');
  const offerTotal=page.locator('#modal[open] #detail-total');
  await retained.or(offerTotal).first().waitFor();
  if(await offerTotal.isVisible()) assert.equal(await value(offerTotal),189000);
  await frame('return');
  return {screen,frames,priceParts,retained:true,supplierRequests:0,leadSent:0};
};

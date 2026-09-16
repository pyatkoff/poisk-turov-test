'use strict';
const assert=require('node:assert/strict');

global.window={};
require('../v2/search3-room-normalizer-v1.js');
const rooms=window.Search3RoomNormalizerV1;

assert.ok(rooms&&typeof rooms.identity==='function'&&typeof rooms.label==='function');
assert.deepEqual(rooms.identity('STANDARD LAND VIEW'),{key:'room:standard-land-view',label:'Стандарт · территория'});
assert.equal(rooms.label('Standard room'),'Стандарт');
for(const value of ['Standard Pool View','STANDARD ROOM POOL VIEW','Стандартный номер · вид на бассейн','Стандарт · вид на бассейн']){
  assert.deepEqual(rooms.identity(value),{key:'room:standard-pool-view',label:'Стандарт · вид на бассейн'},value);
}
assert.equal(rooms.label('STANDARD POOL VIEW WITH PRIVATE POOL'),'STANDARD POOL VIEW WITH PRIVATE POOL','additional room conditions remain verbatim');
assert.equal(rooms.label('FAMILY SUITE WITH TWO BEDROOMS AND SIDE SEA VIEW'),'Семейный люкс · 2 спальни · боковой вид на море');
assert.equal(rooms.label('EXECUTIVE SEA VIEW WITH BALCONY'),'EXECUTIVE SEA VIEW WITH BALCONY','unreviewed supplier room remains verbatim');
assert.deepEqual(rooms.identity('standard sea view room'),{key:'room:standard-sea-view',label:'Стандарт · море'});
assert.deepEqual(rooms.identity('standard side sea view room'),{key:'room:standard-side-sea-view',label:'Стандарт · боковой вид на море'});
assert.deepEqual(rooms.identity('superior garden view room'),{key:'room:superior-garden-view',label:'Улучшенный · вид на сад'});
assert.deepEqual(rooms.identity('family one bedroom'),{key:'room:family-one-bedroom',label:'Семейный · 1 спальня'});
assert.equal(rooms.identity(''),null);
console.log('SEARCH3_ROOM_NORMALIZER_OK canonical_aliases=1 unknown_verbatim=1');

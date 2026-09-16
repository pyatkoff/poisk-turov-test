(function(){'use strict';
if(window.Search3RoomNormalizerV1)return;
const aliases=[
{key:'standard',label:'Стандарт',aliases:['standard','standard room','стандарт','стандартный номер']},
{key:'economy',label:'Эконом',aliases:['economy','economy room','эконом','эконом номер','номер эконом']},
{key:'promo',label:'Промо',aliases:['promo','promo room','promotional room','промо','промо номер','номер промо']},
{key:'standard-pool-view',label:'Стандарт · вид на бассейн',aliases:['standard pool view','standard pool view room','standard room pool view','стандарт вид на бассейн','стандартный номер вид на бассейн']},
{key:'standard-land-view',label:'Стандарт · территория',aliases:['standard land view','standard room land view','стандартный номер вид на территорию','стандартный номер · вид на территорию','стандарт вид на территорию','стандарт · вид на территорию','стандарт территория','стандарт · территория']},
{key:'standard-garden-view',label:'Стандарт · вид на сад',aliases:['standard garden view','standard garden view room','standard room with garden view']},
{key:'standard-garden-or-pool-view',label:'Стандарт · вид на сад или бассейн',aliases:['standard garden or pool view']},
{key:'standard-pool-or-lagoon-view',label:'Стандарт · вид на бассейн или лагуну',aliases:['standard pool / lagoon view']},
{key:'standard-marina-view',label:'Стандарт · вид на марину',aliases:['standard marina view']},
{key:'standard-sea-view',label:'Стандарт · море',aliases:['standard sea view','standard sea view room','standard room sea view','стандартный номер вид на море','стандартный номер · вид на море','стандарт вид на море','стандарт · вид на море','стандарт море','стандарт · море']},
{key:'standard-side-sea-view',label:'Стандарт · боковой вид на море',aliases:['standard side sea view','standard side sea view room','standard room side sea view','стандартный номер боковой вид на море','стандартный номер · боковой вид на море','стандарт боковой вид на море','стандарт · боковой вид на море']},
{key:'superior',label:'Улучшенный',aliases:['superior','superior room','улучшенный','улучшенный номер']},
{key:'superior-garden-view',label:'Улучшенный · вид на сад',aliases:['superior garden view','superior garden view room','superior room garden view']},
{key:'superior-side-sea-view',label:'Улучшенный · боковой вид на море',aliases:['superior side sea view']},
{key:'family',label:'Семейный',aliases:['family','family room','семейный','семейный номер']},
{key:'family-one-bedroom',label:'Семейный · 1 спальня',aliases:['family one bedroom']},
{key:'club',label:'Клубный',aliases:['club room']},
{key:'premium-garden-view',label:'Премиум · вид на сад',aliases:['premium garden view room']},
{key:'premium-mountain-view',label:'Премиум · вид на горы',aliases:['premium room mountain']},
{key:'deluxe',label:'Делюкс',aliases:['deluxe','deluxe room','делюкс','номер делюкс']},
{key:'junior-suite',label:'Полулюкс',aliases:['junior suite','полулюкс']},
{key:'suite',label:'Люкс',aliases:['suite','suite room','люкс']},
{key:'family-suite',label:'Семейный люкс',aliases:['family suite','семейный люкс']},
{key:'family-suite-2-bedroom-side-sea-view',label:'Семейный люкс · 2 спальни · боковой вид на море',aliases:['family suite with two bedrooms and side sea view','семейный люкс 2 спальни боковой вид на море']}
];
// Keep exact alias keys and first-match priority; do not broaden room identities.
const byAlias=new Map();
for(const entry of aliases)for(const alias of entry.aliases)if(!byAlias.has(alias))byAlias.set(alias,entry);
function text(value){return String(value==null?'':value).replace(/\s+/g,' ').trim();}
function normalize(value){return text(value).toLocaleLowerCase('ru-RU').replace(/ё/g,'е').replace(/[–—]/g,'-').replace(/[.,]/g,'').replace(/\s*[-·]\s*/g,' ').replace(/\s+/g,' ').trim();}
function identity(value){const raw=text(value);if(!raw)return null;const normalized=normalize(raw),item=byAlias.get(normalized);return item?{key:'room:'+item.key,label:item.label}:{key:'room:label:'+normalized,label:raw};}
function label(value){const item=identity(value);return item?item.label:text(value);}
window.Search3RoomNormalizerV1={identity,label,normalize,version:1};
})();
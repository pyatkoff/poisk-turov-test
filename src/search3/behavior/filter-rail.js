/* Retired Search3 desktop rail: v2/ds2-results-filters.js is the sole UI/filter owner. */
/* @include behavior/filter-rail/availability.js */
/* @include behavior/filter-rail/render.js */
(function(){'use strict';
var rail=document.querySelector('.results-filter-rail');
if(!rail||!window.DS2ResultsFilters)return;
window.addEventListener('v2:results-rendered',function(event){
  var items=event&&event.detail&&Array.isArray(event.detail.items)?event.detail.items:[];
  rail.dataset.s3EmptyResults=window.__DS2ResultsRailApplying&&!items.length?'1':'';
});
window.addEventListener('v2:search-reset',function(){rail.dataset.s3EmptyResults='';});
})();

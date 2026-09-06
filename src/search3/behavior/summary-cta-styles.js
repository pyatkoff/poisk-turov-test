/* Static mobile final review styles; kept at the original injection position. */
(function () {
  'use strict';
  var reviewScope = "html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry)";
function injectMobileFinalReviewStyle(){if(document.getElementById('search3-mobile-final-review-v2'))return;const s=document.createElement('style');s.id='search3-mobile-final-review-v2';s.textContent='@media(max-width:640px){'+
reviewScope + '{display:grid!important;grid-template-columns:108px minmax(0,1fr)!important;column-gap:10px!important;row-gap:10px!important;align-items:stretch!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
reviewScope + ' .back-results{display:inline-flex!important;grid-column:1/-1!important;grid-row:1!important;margin:0 0 2px!important}'+
reviewScope + ' .search3-booking-stepper{display:flex!important;grid-column:1/-1!important;grid-row:2!important;width:100%!important;margin:0!important}'+
reviewScope + ' .search3-review-heading{display:block!important;grid-column:1/-1!important;grid-row:3!important;width:100%!important;margin:0!important;padding:0!important}'+
reviewScope + ' .search3-review-heading h2{font-size:22px!important;line-height:1.1!important}'+
reviewScope + ' .selected-picture{display:block!important;grid-column:1!important;grid-row:4!important;width:108px!important;height:104px!important;min-height:104px!important;margin:0!important;border-radius:12px!important;overflow:hidden!important}'+
reviewScope + ' .selected-picture img{width:100%!important;height:100%!important;object-fit:cover!important}'+
reviewScope + ' .selected-head{display:block!important;grid-column:2!important;grid-row:4!important;min-width:0!important;min-height:104px!important;margin:0!important;padding:11px!important;border:1px solid var(--at-line)!important;border-radius:12px!important;background:#fff!important}'+
reviewScope + ' .selected-head h2{margin:0!important;font-size:15px!important;line-height:1.12!important}'+
reviewScope + ' .selected-head .selected-price,' + reviewScope + ' .selected-head .selected-lead-cta,' + reviewScope + ' .selected-head .selected-lead-cta-note{display:none!important}'+
reviewScope + ' .facts,' + reviewScope + ' .facts-secondary-toggle,' + reviewScope + ' .selected-choice-summary,' + reviewScope + ' .hotel-desc,' + reviewScope + ' .hotel-desc-toggle,' + reviewScope + ' .room-details-host,' + reviewScope + ' .selected-confidence,' + reviewScope + ' .search3-tour-detail-rail{display:none!important}'+
reviewScope + ' .tour-flights{display:block!important;grid-column:1/-1!important;grid-row:5!important;width:100%!important;margin:0!important}'+
reviewScope + ' .search3-final-sections{display:grid!important;grid-column:1/-1!important;grid-row:6!important;width:100%!important;margin:0!important;gap:10px!important}'+
reviewScope + ' .search3-lead-shell{display:block!important;grid-column:1/-1!important;grid-row:7!important;width:100%!important;margin:0!important}'+
reviewScope + ' .search3-lead-shell>.lead-form{display:none!important}'+
reviewScope + ' .search3-booking-summary{display:block!important;width:100%!important;max-width:none!important;position:static!important;margin:0!important;padding:14px!important;box-sizing:border-box!important}'+
reviewScope + ' .search3-booking-summary__image,' + reviewScope + ' .search3-booking-summary__hotel,' + reviewScope + ' .search3-booking-summary__place,' + reviewScope + ' .search3-booking-summary dl,' + reviewScope + ' .search3-booking-summary__flight-costs,' + reviewScope + ' .search3-booking-summary__price-note{display:none!important}'+
reviewScope + ' .search3-booking-summary__title{margin:0 0 8px!important;font-size:14px!important}'+
reviewScope + ' .search3-booking-summary__total{margin:0!important;padding:0 0 10px!important;border:0!important}'+
reviewScope + ' .search3-booking-summary__total strong{font-size:24px!important}'+
reviewScope + ' .search3-summary-actions{display:block!important;margin-top:8px!important}'+
reviewScope + ' .search3-summary-submit{width:100%!important;min-height:48px!important}'+
'}';document.head.appendChild(s)}
injectMobileFinalReviewStyle();
})();

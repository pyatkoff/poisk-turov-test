/* Static mobile convergence styles; kept at the original injection position. */
(function () {
  'use strict';
  if (!document.getElementById('selectedTour')) return;
  var flightScope = "html body.search3-candidate.search3-selected-open #selectedTour:not(.search3-final-review)";
  var reviewScope = "html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review:not(.search3-lead-entry)";
  var leadScope = "html body.search3-candidate.search3-selected-open #selectedTour.search3-final-review.search3-lead-entry";
function injectMobileConvergence(){if(document.getElementById('search3-mobile-convergence-style'))return;var s=document.createElement('style');s.id='search3-mobile-convergence-style';s.textContent='@media(max-width:640px){'+
flightScope + ' .selected-lead-cta,' + flightScope + ' .selected-lead-cta-note,' + flightScope + ' .selected-choice-summary,' + flightScope + ' .facts-secondary-toggle,' + flightScope + ' .selected-confidence,' + flightScope + ' .search3-lead-shell,' + flightScope + '>.lead-form{display:none!important}'+
flightScope + '{display:flex!important;flex-direction:column!important}'+
flightScope + ' .back-results{order:0!important}'+
flightScope + ' .search3-booking-stepper{display:grid!important;order:4!important;width:100%!important;margin:0 0 8px!important;padding:7px 6px!important}'+
flightScope + ' .selected-picture{order:1!important;height:148px!important;min-height:148px!important;border-radius:12px 12px 0 0!important}'+
flightScope + ' .selected-head{order:2!important;min-height:0!important;padding:10px 12px!important}'+
flightScope + ' .facts{order:3!important}'+
flightScope + ' .hotel-desc{order:8!important}'+
flightScope + ' .hotel-desc-toggle{order:9!important;align-self:flex-start!important}'+
flightScope + ' .room-details-host{order:6!important;width:100%!important}'+
flightScope + ' .search3-final-sections{order:7!important;width:100%!important}'+
flightScope + ' .tour-flights{order:5!important;width:100%!important;margin-top:10px!important;padding:12px!important;border-radius:12px!important}'+
reviewScope + '{display:flex!important;flex-direction:column!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
reviewScope + ' .back-results{order:0!important}'+
reviewScope + ' .search3-booking-stepper{order:1!important;width:100%!important;margin:0 0 10px!important}'+
reviewScope + ' .search3-review-heading{display:block!important;order:2!important;width:100%!important;margin:0 0 10px!important;padding:0!important}'+
reviewScope + ' .search3-review-heading h2{font-size:22px!important;line-height:1.1!important}'+
reviewScope + ' .selected-picture,' + reviewScope + ' .selected-head,' + reviewScope + ' .facts,' + reviewScope + ' .facts-secondary-toggle,' + reviewScope + ' .selected-choice-summary,' + reviewScope + ' .hotel-desc,' + reviewScope + ' .hotel-desc-toggle,' + reviewScope + ' .room-details-host,' + reviewScope + ' .tour-flights,' + reviewScope + ' .search3-final-sections,' + reviewScope + ' .selected-confidence,' + reviewScope + ' .search3-tour-detail-rail{display:none!important}'+
reviewScope + ' .search3-lead-shell{display:block!important;order:3!important;width:100%!important;margin:0!important}'+
reviewScope + ' .search3-lead-shell>.lead-form{display:none!important}'+
reviewScope + ' .search3-booking-summary{display:block!important;width:100%!important;max-width:none!important;position:static!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:12px!important;box-sizing:border-box!important}'+
reviewScope + ' .search3-booking-summary__image{height:150px!important}'+
reviewScope + ' .search3-summary-actions{display:block!important;margin-top:12px!important}'+
reviewScope + ' .search3-summary-submit{width:100%!important;min-height:48px!important}'+
leadScope + '{display:flex!important;flex-direction:column!important;width:auto!important;max-width:none!important;margin:10px var(--at-page-edge) 36px!important}'+
leadScope + ' .search3-lead-back:not([hidden]){display:inline-flex!important;order:0!important;align-self:flex-start!important;margin:0 0 8px!important;padding:0!important}'+
leadScope + ' .search3-lead-shell{display:flex!important;flex-direction:column!important;order:1!important;width:100%!important;max-width:none!important;gap:10px!important;margin:0!important}'+
leadScope + ' .search3-lead-shell>.lead-form{display:block!important;order:1!important;width:100%!important;max-width:none!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:16px 14px!important;box-sizing:border-box!important;border-radius:12px!important}'+
leadScope + ' .search3-lead-shell>.search3-booking-summary{display:block!important;order:2!important;width:100%!important;max-width:none!important;position:static!important;grid-column:auto!important;grid-row:auto!important;margin:0!important;padding:12px!important;box-sizing:border-box!important}'+
leadScope + ' .lead-fields{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}'+
leadScope + ' .lead-fields>label{display:grid!important;gap:6px!important;width:100%!important}'+
leadScope + ' .lead-form .section-heading strong{font-size:22px!important;line-height:1.1!important}'+
leadScope + ' .lead-form[data-search3-lead-state]{display:block!important;min-height:0!important;align-items:stretch!important}'+
leadScope + ' .lead-form[data-search3-lead-state] .section-heading,' + leadScope + ' .lead-form[data-search3-lead-state] .lead-fields,' + leadScope + ' .lead-form[data-search3-lead-state] .lead-consent,' + leadScope + ' .lead-form[data-search3-lead-state] .search3-lead-protection,' + leadScope + ' .lead-form[data-search3-lead-state] .lead-message,' + leadScope + ' .lead-form[data-search3-lead-state] .search3-lead-comment,' + leadScope + ' .lead-form[data-search3-lead-state]>button[type=submit]{display:none!important}'+
leadScope + ' .lead-form[data-search3-lead-state] .search3-lead-status{display:grid!important;width:100%!important;grid-template-columns:1fr!important;gap:12px!important;padding:8px 2px!important}'+
flightScope + ' .selected-head h2{font-size:18px!important;line-height:1.08!important}' + flightScope + ' .selected-head p{margin-top:3px!important;font-size:8px!important}'+
flightScope + ' .tour-flights .section-heading{display:flex!important;flex-direction:row!important;align-items:baseline!important;gap:8px!important;margin-bottom:9px!important}' + flightScope + ' .tour-flights .section-heading strong{font-size:15px!important}' + flightScope + ' .tour-flights .section-heading span{font-size:8px!important;text-align:right!important}'+
flightScope + ' .flight-variants{gap:8px!important}' + flightScope + ' .flight-choice{grid-template-columns:17px minmax(0,1fr)!important;padding:8px 9px!important}' + flightScope + ' .flight-choice b{grid-column:2!important;justify-self:start!important}' + flightScope + ' .flight-segment{padding:8px 9px!important}' + flightScope + ' .flight-segment-title{display:flex!important;flex-direction:row!important;align-items:baseline!important;gap:8px!important;margin-bottom:6px!important}' + flightScope + ' .flight-segment-title span{margin-left:auto!important;text-align:right!important}' + flightScope + ' .flight-baggage{margin-top:6px!important}'+
'.search3-selected-mobile-bar:not([hidden]){gap:12px!important}.search3-selected-mobile-bar__price{display:grid!important;grid-template-columns:minmax(0,1fr)!important;align-items:baseline!important;max-width:60%!important}.search3-selected-mobile-bar__price small{white-space:normal!important;line-height:1.2!important}.search3-selected-mobile-bar__price strong{display:block!important;margin-top:2px!important;line-height:1.2!important;white-space:normal!important}'+
'}'+
'@media(min-width:641px) and (max-width:999px){'+
flightScope + '{display:grid!important;grid-template-columns:230px minmax(0,1fr)!important;column-gap:0!important;align-items:stretch!important;margin:14px var(--at-page-edge) 44px!important}'+
flightScope + ' .selected-lead-cta,' + flightScope + ' .selected-lead-cta-note,' + flightScope + ' .selected-choice-summary,' + flightScope + ' .facts-secondary-toggle,' + flightScope + ' .selected-confidence,' + flightScope + ' .search3-lead-shell,' + flightScope + '>.lead-form{display:none!important}'+
flightScope + ' .back-results{grid-column:1/-1!important;grid-row:1!important;justify-self:start!important;margin:0 0 10px!important}' + flightScope + ' .search3-booking-stepper{display:grid!important;grid-column:1/-1!important;grid-row:4!important;margin:0 0 10px!important}'+
flightScope + ' .selected-picture{grid-column:1!important;grid-row:2!important;width:100%!important;height:178px!important;min-height:178px!important;margin:0!important;border:1px solid var(--at-line)!important;border-right:0!important;border-radius:12px 0 0 12px!important;overflow:hidden!important}' + flightScope + ' .selected-picture img{width:100%!important;height:100%!important;object-fit:cover!important}'+
flightScope + ' .selected-head{grid-column:2!important;grid-row:2!important;align-content:center!important;min-height:178px!important;height:178px!important;margin:0!important;padding:16px 18px!important;border:1px solid var(--at-line)!important;border-left:0!important;border-radius:0 12px 12px 0!important;box-sizing:border-box!important}' + flightScope + ' .selected-head h2{font-size:22px!important;line-height:1.08!important}' + flightScope + ' .selected-price{font-size:22px!important}'+
flightScope + ' .facts{grid-column:1/-1!important;grid-row:3!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;margin-top:10px!important;border-top:1px solid var(--at-line)!important;border-radius:12px!important}' + flightScope + ' .facts>div,' + flightScope + ' .facts>div:nth-child(n){min-height:0!important;padding:10px 11px!important;border-top:0!important}'+
flightScope + ' .hotel-desc{grid-column:1/-1!important;grid-row:8!important;margin-top:10px!important;padding:14px 16px!important}' + flightScope + ' .room-details-host{grid-column:1/-1!important;grid-row:6!important;width:100%!important}' + flightScope + ' .search3-final-sections{grid-column:1/-1!important;grid-row:7!important;width:100%!important}'+
flightScope + ' .tour-flights{grid-column:1/-1!important;grid-row:5!important;width:100%!important;margin-top:12px!important;padding:14px 16px!important;border-radius:12px!important}' + flightScope + ' .flight-variants{gap:9px!important}' + flightScope + ' .flight-choice{padding:9px 11px!important}' + flightScope + ' .flight-segment{padding:9px 11px!important}'+
'}'+
'@media(min-width:1000px){html body.search3-candidate.search3-has-results .results-tools--ds2{position:relative!important;top:auto!important;z-index:1!important;box-sizing:border-box!important;width:calc(100% - var(--at-page-gutter) - 210px)!important;margin-left:calc(var(--at-page-edge) + 210px)!important;margin-right:var(--at-page-edge)!important;margin-bottom:10px!important}html body.search3-candidate.search3-has-results .results-layout{clear:both!important;position:relative!important;z-index:0!important}}';document.head.appendChild(s)}
injectMobileConvergence();
})();

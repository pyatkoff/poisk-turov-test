/* Static mobile convergence styles; kept at the original injection position. */
(function () {
  'use strict';
  if (!document.getElementById('selectedTour')) return;
function injectMobileConvergence(){if(document.getElementById('search3-mobile-convergence-style'))return;var s=document.createElement('style');s.id='search3-mobile-convergence-style';s.textContent=/* @css-string styles/injected/selected-tour-mobile.css */ "";document.head.appendChild(s)}
injectMobileConvergence();
})();

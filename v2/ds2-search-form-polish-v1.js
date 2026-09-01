(function(){'use strict';
var form=document.getElementById('tourSearch');
if(!form||form.dataset.ds2InitialPolish==='1')return;
form.dataset.ds2InitialPolish='1';
function arrange(){
  var main=form.querySelector('.main-fields');
  if(!main)return false;
  var stars=main.querySelector('.main-stars');
  if(!stars)return false;
  stars.classList.add('ds2-search-quick-stars');
  var title=stars.querySelector(':scope > span');
  if(title)title.textContent='Быстрый выбор';
  main.insertAdjacentElement('afterend',stars);
  return true;
}
if(!arrange()){
  var observer=new MutationObserver(function(){if(arrange())observer.disconnect();});
  observer.observe(form,{childList:true,subtree:true});
  setTimeout(function(){observer.disconnect();},4000);
}
})();
(function(){'use strict';
var form=document.getElementById('tourSearch');if(!form)return;
var details=form.querySelector('details.extras,details.extras-secondary');
var servicePicker=details&&details.querySelector('details.service-picker');
var serviceBox=document.getElementById('hotelServices');
if(!details||!servicePicker||!serviceBox)return;
var timer=0,opening=false;
function norm(v){return String(v||'').toLowerCase().replace(/ё/g,'е').replace(/\s+/g,' ').trim();}
function isInitial(){return form.classList.contains('ds2-search-initial');}
function trustedRole(label){
  var s=norm(label);
  if(['первая линия','1-я линия','1 линия','first line'].indexOf(s)>=0)return{key:'first-line',label:'Первая линия'};
  if(['для детей','с детьми','отдых с детьми','семейный отдых','семейный'].indexOf(s)>=0)return{key:'children',label:'С детьми'};
  return null;
}
function quickTarget(){return form.querySelector('.ds2-search-quick-stars .stars-quick');}
function removeShortcuts(){var target=quickTarget();if(!target)return;target.querySelectorAll('.service-shortcut').forEach(function(btn){btn.remove();});}
function render(){
  var target=quickTarget();if(!target)return;
  if(!isInitial()){removeShortcuts();return;}
  var found={};
  serviceBox.querySelectorAll('label.service-check').forEach(function(label){
    var input=label.querySelector('input[name="hotel_service[]"]');
    var text=label.querySelector('span');
    var role=trustedRole(text?text.textContent:label.textContent);
    if(input&&role&&!found[role.key])found[role.key]={input:input,role:role};
  });
  target.querySelectorAll('.service-shortcut').forEach(function(btn){if(!found[btn.dataset.serviceRole])btn.remove();});
  ['first-line','children'].forEach(function(key){
    if(!found[key])return;
    var item=found[key],btn=target.querySelector('.service-shortcut[data-service-role="'+key+'"]');
    if(!btn){
      btn=document.createElement('button');btn.type='button';btn.className='stars-choice service-shortcut';btn.dataset.serviceRole=key;btn.dataset.ds2QuickRole='service';btn.textContent=item.role.label;
      btn.addEventListener('click',function(){
        var current=found[key]&&found[key].input;
        if(!current||!current.isConnected){render();current=serviceBox.querySelector('input[name="hotel_service[]"][value="'+CSS.escape(String(btn.dataset.serviceValue||''))+'"]');}
        if(!current)return;
        current.checked=!current.checked;current.dispatchEvent(new Event('change',{bubbles:true}));render();
      });
    }
    btn.dataset.serviceValue=String(item.input.value||'');
    btn.classList.toggle('is-active',!!item.input.checked);
    btn.setAttribute('aria-pressed',item.input.checked?'true':'false');
    target.appendChild(btn);
  });
  target.setAttribute('aria-label','Быстрые фильтры');
}
function probe(){
  clearTimeout(timer);
  timer=setTimeout(function(){
    if(!isInitial()||opening)return;
    var country=form.elements.country&&form.elements.country.value;if(!country)return;
    if(serviceBox.querySelector('input[name="hotel_service[]"]')){render();return;}
    opening=true;
    var hadOpen=servicePicker.open;
    servicePicker.open=true;
    setTimeout(function(){if(!hadOpen)servicePicker.open=false;opening=false;render();},1200);
  },250);
}
var observer=new MutationObserver(function(){render();});observer.observe(serviceBox,{childList:true,subtree:true});
var classObserver=new MutationObserver(function(){if(isInitial())probe();else removeShortcuts();});classObserver.observe(form,{attributes:true,attributeFilter:['class']});
form.addEventListener('change',function(e){var n=e.target&&e.target.name;if(n==='country'||n==='region'||n==='arrival')probe();if(n==='hotel_service[]')render();});
setTimeout(probe,2600);
})();

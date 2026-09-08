/* Native Search3 entry: server markup and canonical lifecycle own all controls. */
(function(){'use strict';var form=document.getElementById('tourSearch');if(!form)return;form.dataset.search3Ready='1';window.addEventListener('v2:search-started',function(){form.querySelectorAll('details[open]').forEach(function(node){node.open=false;});});})();

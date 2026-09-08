  var d1=refs.dateFrom,d2=refs.dateTo;if(d1&&d2){var dateBox=makeComposite('Дата вылета','search3-dates'),dateCtl=dateBox.querySelector('.search3-composite__control');d1.classList.add('search3-direct-control');d2.classList.add('search3-direct-control');d1.setAttribute('aria-label','Вылет не раньше');d2.setAttribute('aria-label','Вылет не позже');dateCtl.appendChild(d1);var dash=document.createElement('span');dash.className='search3-composite__dash';dash.textContent='—';dateCtl.appendChild(dash);dateCtl.appendChild(d2);main.appendChild(dateBox);}
  var n1=refs.daysFrom,n2=refs.daysTill;if(n1&&n2){
    var nightBox=makeComposite('Ночей','search3-nights'),nightCtl=nightBox.querySelector('.search3-composite__control');
    function nightSelect(input,label){
      var select=document.createElement('select');select.className='search3-direct-control';select.setAttribute('aria-label',label);select.dataset.search3Night=input.name;
      for(var i=1;i<=28;i++){var option=document.createElement('option');option.value=String(i);option.textContent=String(i);select.appendChild(option);}
      function sync(){select.value=String(clampNight(input.value));}
      input.hidden=true;input.tabIndex=-1;input.setAttribute('aria-hidden','true');
      input.addEventListener('input',sync);input.addEventListener('change',sync);select.addEventListener('focus',sync);
      select.addEventListener('change',function(){input.value=select.value;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));});
      form.addEventListener('reset',function(){setTimeout(sync,0);});sync();nightCtl.appendChild(input);nightCtl.appendChild(select);
    }
    nightSelect(n1,'Минимум ночей');var nd=document.createElement('span');nd.className='search3-composite__dash';nd.textContent='—';nightCtl.appendChild(nd);nightSelect(n2,'Максимум ночей');main.appendChild(nightBox);
  }
  // Keep canonical controls directly editable; no second popup or mirrored values.
  var adults=refs.count_people,children=refs.child_count;
  if(adults&&children){
    var touristBox=makeComposite('Туристы','search3-tourists'),touristCtl=touristBox.querySelector('.search3-composite__control');
    [[adults,'Взрослых'],[children,'Детей']].forEach(function(pair){
      var select=pair[0];select.classList.remove('ux-native-hidden');select.classList.add('search3-direct-control');select.removeAttribute('aria-hidden');select.tabIndex=0;select.setAttribute('aria-label',pair[1]);touristCtl.appendChild(select);
    });
    if(childAges){childAges.classList.remove('guests-ages');childAges.classList.add('search3-tourists__ages');touristCtl.appendChild(childAges);}
    main.appendChild(touristBox);
  }

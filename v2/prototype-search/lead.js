(function(root){
  'use strict';
  let draft={name:'',phone:'',comment:''};
  const preview=new URL(root.V2_CONFIG.leadApi,root.location.href).pathname.endsWith('/preview-lead-disabled.php');
  function markup(){
    return `<form id="prototype-lead-form">
      <p class="modal-intro">${preview?'Проверьте контакты и выбранные условия. В версии для проверки заявка не отправляется.':'Менеджер получит выбранный тур и рейс и свяжется с вами. Оплата на этом шаге не требуется.'}</p>
      <div class="form-row"><label>Имя (необязательно)<input class="input" name="name" autocomplete="name" maxlength="120" placeholder="Как к вам обращаться"></label><label>Телефон<input class="input" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="40" required placeholder="+7 999 123-45-67" aria-describedby="prototype-phone-hint"></label></div>
      <p class="modal-intro" id="prototype-phone-hint">Введите номер с кодом страны или города.</p>
      <div class="form-row"><label>Комментарий (необязательно)<textarea class="input" name="comment" rows="3" maxlength="1000" placeholder="Ваши пожелания к поездке"></textarea></label></div>
      <label class="meal-option"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку персональных данных</span></label>
      <p class="lead-message modal-intro" role="status" aria-live="polite"></p>
    </form>`;
  }
  function action(){return `<button class="primary" type="submit" form="prototype-lead-form">${preview?'Проверить заявку':'Отправить заявку'}</button>`;}
  function bind(offer){
    const form=document.getElementById('prototype-lead-form');if(!form)return;
    const phone=form.elements.phone,button=document.querySelector('[type="submit"][form="prototype-lead-form"]'),message=form.querySelector('.lead-message');
    for(const name of ['name','phone','comment'])form.elements[name].value=draft[name];
    let session;
    try{session=root.AnyTourPrototypeData.leadSession(offer);}
    catch(error){message.textContent=error.message;message.setAttribute('role','alert');button.disabled=true;}
    form.addEventListener('input',()=>{
      for(const name of ['name','phone','comment'])draft[name]=form.elements[name].value;
      root.V2LeadFormGuard.validatePhone(phone);
      if(preview&&session){message.textContent='';delete form.dataset.checked;}
    });
    form.addEventListener('submit',async event=>{
      event.preventDefault();
      if(!session||form.dataset.sent==='1'||button.disabled)return;
      if(!root.V2LeadFormGuard.validatePhone(phone)||!form.reportValidity()){phone.reportValidity();return;}
      try{
        // Construct through the same owner even in preview; no preview request is sent.
        session.payload(new FormData(form));
        if(preview){form.dataset.checked='1';message.setAttribute('role','status');message.textContent='Данные проверены. Тур, выбранный рейс и контакты готовы к передаче. В этой версии заявка не отправлена.';message.scrollIntoView({block:'nearest'});return;}
        if(await session.submit(form,{button}))draft={name:'',phone:'',comment:''};
      }catch(error){message.textContent=error.message;message.setAttribute('role','alert');}
    });
  }
  function reset(){draft={name:'',phone:'',comment:''};}
  root.AnyTourPrototypeLead=Object.freeze({markup,action,bind,reset});
})(window);

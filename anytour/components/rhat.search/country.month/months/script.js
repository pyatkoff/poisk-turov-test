var 
requestDetail = true,
clientID = "",
yclid = "",
utmSource = "",
utmMedium = "",
utmCampaign = "",
utmContent = "",
utmTerm = "";

$(document).ready(function() {
	
    ym(69158110, 'getClientID', function(cID) {
		clientID = cID;
	});
	
	yclid       = (Cookies.get('yclid')!==undefined)       ? Cookies.get('yclid') : "" ;
	utmSource   = (Cookies.get('utmSource')!==undefined)   ? Cookies.get('utmSource') : "" ;
	utmMedium   = (Cookies.get('utmMedium')!==undefined)   ? Cookies.get('utmMedium') : "" ;
	utmCampaign = (Cookies.get('utmCampaign')!==undefined) ? Cookies.get('utmCampaign') : "" ;
	utmContent  = (Cookies.get('utmContent')!==undefined)  ? Cookies.get('utmContent') : "" ;
	utmTerm     = (Cookies.get('utmTerm')!==undefined)     ? Cookies.get('utmTerm') : "" ;	

    $("body").on("click",".hotItem",function(e)
    {
		
        var tid = $(this).attr("data-tid"),
		hid = $(this).attr("data-hid"),
        data={hid:hid,tid:tid,get_tour_info:"y"};
        
        $.ajax( hotAjaxUrl, {
            cache: false,
            data: data,
            dataType: "json",
            error: errorHandler,
            success: successTourInfo,
            type: "POST"
        });
    });
	
	$('body').on('click','.orderSendType',function(){
        $(this).addClass('active');
        $('.hotelOrderOffice').addClass('active');
        $('.hotelOrderOnline').removeClass('active');
        $('.orderBuyType').removeClass('active');
    });
    
    $('body').on('click','.orderBuyType',function(){
        $(this).addClass('active');
        $('.hotelOrderOnline').addClass('active');
        $('.hotelOrderOffice').removeClass('active');
        $('.orderSendType').removeClass('active');
    });
	
	$("body").on("click",".hotelNextStep",function(e)
    {
        
		if(!$(this).hasClass('loading'))
		{
			if($(this).hasClass('orderOpen'))
			{
				$('.tourHotelWrap').removeClass('formshow');
				$(this).removeClass('orderOpen')
					   .html("Подробнее");
			}
			else
			{
				$('.tourHotelWrap').addClass('formshow');
				$(this).addClass('orderOpen')
					   .html("Об отеле");
					   
				if(!requestDetail && $('.hotelOrderOnline').length>0 )
				{
					
					requestDetail = true;
					var 
					hid = $('.hotelOrderOnline input[name=ordHid]').val(),
					tid = $('.hotelOrderOnline input[name=ordTid]').val();
					data={hid:hid,tid:tid,update_detail:"y"};
					
					$.ajax( hotAjaxUrl, {
						cache: false,
						data: data,
						dataType: "json",
						//error: errorHandler,
						//success: successTourInfo,
						type: "POST"
					});
				}	
					   
					   
			}
		}
    });

    
    $("body").on("submit","form[name=orderOffice]",function(e)
    {
      
        e.preventDefault();
        $(this).find('#userName').parent().removeClass('errorBord');
        $(this).find('#userPhone').parent().removeClass('errorBord');
        $(this).find('#userEmail').parent().removeClass('errorBord');
        var uname  = $(this).find("#userName").val();
        var uemail = $(this).find("#userEmail").val();
        var uphone = $(this).find("#userPhone").val();
        var utext  = $(this).find("#userComment").val();
        var ohid   = $(this).find("#ordHid").val();
        var otid   = $(this).find("#ordTid").val();

        var yaclient      = $(this).find(".yaClient").val();
		var yaclid        = $(this).find(".yaClid").val();
		var yautmsource   = $(this).find(".yaUtmSource").val();
		var yautmmedium   = $(this).find(".yaUtmMedium").val();
		var yautmcampaign = $(this).find(".yaUtmCampaign").val();
		var yautmcontent  = $(this).find(".yaUtmContent").val();
		var yautmterm     = $(this).find(".yaUtmTerm").val();

        if (uname=="" ||  uphone=="" &&  uemail=="")
        {
            if (uname==""){$(this).find('#userName').attr('placeholder','Укажите Ваше имя').parent().addClass('errorBord');}
            if (uphone==""){$(this).find('#userPhone').attr('placeholder','Укажите Ваш телефон').parent().addClass('errorBord');}
          
        }  
        else if (!$(this).find("#userPhone").hasClass('validphone')){
            $(this).find("#userPhone").attr('placeholder','Укажите корректный телефон').parent().addClass('errorBord');
           
        }
        
        else
        {    
           
            $('.orderSend').hide();
            data={
                officeorder:1, 
                name:uname, 
                email:uemail, 
                phone:uphone, 
                text:utext, 
                hid: ohid, 
                tid : otid ,
                yaclient:yaclient,
				yaclid:yaclid,
				yautmsource:yautmsource,
				yautmmedium:yautmmedium,
				yautmcampaign:yautmcampaign,
				yautmcontent:yautmcontent,
				yautmterm:yautmterm
            };
           
            $.ajax(  hotAjaxUrl, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successOrder,
                type: "POST"
            });
        }
    });
	
    $("body").on("submit","form[name=orderOnline]",function(e)
    {
		e.preventDefault();
        $(this).find('.errorBord').removeClass('errorBord');
		$(this).find('.errorBlock').remove();
        var uname  = $("#userName").val(),
        uemail = $("#userEmail").val(),
        uphone = $("#userPhone").val(),
        utext  = $("#userComment").val(),
		uPassSerNum  = $("#userPassSerNum").val(),
		uPassD       = $("#userPassD").val(),
		uPassM       = $("#userPassM").val(),
		uPassY       = $("#userPassY").val(),
		uPassWho     = $("#userPassWho").val(),
        ohid   = $("#ordHid").val(),
        otid   = $("#ordTid").val(),
		tourists = [],
		error  = false;

		var yaclient      = $(this).find(".yaClient").val();
		var yaclid        = $(this).find(".yaClid").val();
		var yautmsource   = $(this).find(".yaUtmSource").val();
		var yautmmedium   = $(this).find(".yaUtmMedium").val();
		var yautmcampaign = $(this).find(".yaUtmCampaign").val();
		var yautmcontent  = $(this).find(".yaUtmContent").val();
		var yautmterm     = $(this).find(".yaUtmTerm").val();


		$(this).find('.turistBlock').each(function(index) {
            var indx = index+1;
            
            var name = $("#name"+indx).val();
            if (name=="") {error = true; $("#name"+indx).parent().addClass('errorBord');}  
            
            var sname = $("#sname"+indx).val();
            if (sname=="") {error = true; $("#sname"+indx).parent().addClass('errorBord');} 
            
            var pasSer =  $("#pasSer"+indx).val();
            if (pasSer=="") {error = true; $("#pasSer"+indx).parent().addClass('errorBord');} 
            
            var pasNom =  $("#pasNom"+indx).val();
            if (pasNom=="") {error = true; $("#pasNom"+indx).parent().addClass('errorBord');}
            
            var pasOut =  $("#pasOut"+indx).val();
            if (pasOut=="") {error = true; $("#pasOut"+indx).parent().addClass('errorBord'); } 
            
            var nat =  $("#nat"+indx).val();
            if (nat=="") {error = true; $("#nat"+indx).parent().addClass('errorBord');} 
            
            var gend =  $("#gend"+indx).val();
            
            var bdate = checkDate ("bdate",indx);
            if (bdate.error) {error = true; $("#bdate"+indx).addClass('errorBord');} 
            var pasFrom = checkDate ("pasFrom",indx);
            if (pasFrom.error) {error = true; $("#pasFrom"+indx).addClass('errorBord');} 
            var pasTill = checkDate ("pasTill",indx);
            if (pasTill.error) {error = true; $("#pasTill"+indx).addClass('errorBord');} 
                
            
            tourists[index] = {
                name:name, 
                sname:sname, 
                pser:pasSer, 
                pnom:pasNom, 
                nat:nat, 
                gend:gend,
                pout:pasOut,
                bdate:bdate,
                pasfrom:pasFrom,
                pastill:pasTill
            };  
        });
		//console.log( tourists);
		
        if ( uname=="" ||  uphone=="" &&  uemail=="")
        {
			error = true;
            if (uname==""){$(this).find('#userName').attr('placeholder','Укажите Ваше имя').parent().addClass('errorBord');}
			
            if (uphone==""){$(this).find('#userPhone').attr('placeholder','Укажите Ваш телефон').parent().addClass('errorBord');}
        } 
		
		if ( uPassSerNum=="" || uPassWho=="")
        {
			error = true;
            if (uPassSerNum==""){$(this).find('#userPassSerNum').attr('placeholder','Укажите серию и номер паспорта').parent().addClass('errorBord');}
            if (uPassWho==""){$(this).find('#userPassWho').attr('placeholder','Укажите кем выдан паспорт').parent().addClass('errorBord');}
        } 
		
		var uPassDate = checkDate("userPass",0);
		if (uPassDate.error) {error = true; $('.orderBdateD').parent().addClass('errorBord');} 
		
		
		
		if (!$(this).find("#userPhone").hasClass('validphone')){
            $(this).find("#userPhone").attr('placeholder','Укажите корректный телефон').parent().addClass('errorBord');
            error = true;
        }  
        
		if(error)
		{
			$(this).append('<div class="errorBlock">Необходимо заполнить все обязательные поля</div>');
		}	
		else
		{
            $('.orderSend').hide();
			data={
				onlineorder:1, 
				name:uname, 
				email:uemail,
				passnum:uPassSerNum,
				passwho:uPassWho,
				passdate:uPassDate,
				phone:uphone, 
				text:utext, 
				hid: ohid, 
				tid: otid, 
				tourists:tourists,
                yaclient:yaclient,
				yaclid:yaclid,
				yautmsource:yautmsource,
				yautmmedium:yautmmedium,
				yautmcampaign:yautmcampaign,
				yautmcontent:yautmcontent,
				yautmterm:yautmterm	  
			};
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( hotAjaxUrl, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successOrder,
                type: "POST"
            });
		}
		
	});
	
	$('body').on('change','.sSelect', function() {
        var vl = $(this).val();
        var txt = ($(this).attr('id')=="from") ?  $(this).children("option[value='"+vl+"']").attr("data-name") :  $(this).children("option[value='"+vl+"']").text();
        console.log(vl+" "+txt);
        $(this).prev('span').html(txt);
        
    });   
});	



function successTourInfo( data )
{
    var div = $('<div class="tourHotelWrap"></div>'),
	//hotelWrap = $('<div class="tourHotelWrapInner loading"></div>'),
	hotelWrap = $('<div class="tourHotelWrapInner loading"></div>'),
	hotelBlock = $('<div class="hotelBlock"></div>'),
	hotelName = $('<div class="hotelName">'+data.hotel_info.name+'</div>'),
	hotelStars = $('<div class="hotelStars"></div>'),
	hotelCountry = $('<div class="hotelCountry">'+data.hotel_info.country+'</div>'),
	hotelDates = $('<div class="hotelTourDates"></div>'),
	hotelTourInfo = $('<div class="hotelTourInfo"></div>'),
	hotelPrice = $('<div class="hotelPriceInfo"><div class="hotelTourPrice"><span>'+data.tour_info.price+'</span> руб.</div><div class="hotelNextStep loading">Продолжить</div><div class="fuelBlock">В том числе топливный сбор: <span>'+data.tour_info.fuel+'</span></div></div>'),
	hotelOrder = $('<div class="hotelOrderWrapper"></div>'),
	hotelOrderOnline = $('<div class="hotelOrderOnline"></div>'),
	hotelOrderOffice = $('<div class="hotelOrderOffice"></div>');
   
	if (data.hotel_info.stars>0)
	{
		for($i=1;$i<=data.hotel_info.stars;$i++) 
			hotelStars.append('<div class="gStar"></div>');
		hotelStars.append('<div class="clear"></div>');
	}

	hotelDates.append(
						'<div class="tourDatesBlock tourDateFrom">'+
							'Вылет:<br><span>'+data.tour_info.date_from+'</span>'+
						'</div>'+
						'<div class="tourDatesBlock tourDateTo">'+
							'Прилет:<br><span>'+data.tour_info.date_to+'</span>'+
						'</div>'+
						'<div class="tourDatesBlock tourDateNights">'+
							'Ночей:<br><span>'+data.tour_info.nights+'</span>'+
						'</div>'
	);
	
	hotelTourInfo.append(
						'<div class="tourInfoLine">'+
							'Вылет из: <span>'+data.tour_info.departure+'</span>'+
						'</div>'+
						'<div class="tourInfoLine tourRoom">'+
							'Размещение: <span>'+data.tour_info.room+'</span>'+
						'</div>'+
						'<div class="tourInfoLine tourFood">'+
							'Питание: <span>'+data.tour_info.meal_name+'</span>'+
						'</div>'
	);
	
	hotelBlock.append(hotelName)
			 .append(hotelStars)
			 .append(hotelCountry)
			 .append(hotelPrice)
			 .append(hotelDates)
			 .append(hotelTourInfo);
	
	
	if (data.hotel_info.photo)
	{
		var hotelPhoto = $('<div class="hotelPhoto"></div>');
		$.each(data.hotel_info.photo,function(index,val) {
			var celm = $('<div class="tourInfoCaruselItem"><img src="'+data.hotel_info.photo[index]['big']+'"></div>');
			hotelPhoto.append(celm);
		});
        
        hotelBlock.append(hotelPhoto);
	}
	
	if (data.hotel_info.desc!="")
	{
		var hotelDesc = $('<div class="infoWrapper"><div class="infoLabel">Описание</div><div class="">'+data.hotel_info.desc+'</div></div>');
		hotelBlock.append(hotelDesc);
	}
	
	if (data.hotel_info.beach!="")
	{
		var hotelBeach = $('<div class="infoWrapper"><div class="infoLabel">Пляж</div><div class="">'+data.hotel_info.beach+'</div></div>');
		hotelBlock.append(hotelBeach);
	}
	
	if (data.hotel_info.territory!="")
	{
		var hotelTer = $('<div class="infoWrapper"><div class="infoLabel">Территория</div><div class="">'+data.hotel_info.territory+'</div></div>');
		hotelBlock.append(hotelTer);
	}
	
	if (data.hotel_info.service_free!="")
	{
		var hotelTer = $('<div class="infoWrapper"><div class="infoLabel">Бесплатные услуги</div><div class="">'+data.hotel_info.service_free+'</div></div>');
		hotelBlock.append(hotelTer);
	}
	if (data.hotel_info.service_pay!="")
	{
		var hotelTer = $('<div class="infoWrapper"><div class="infoLabel">Платные услуги</div><div class="">'+data.hotel_info.service_pay+'</div></div>');
		hotelBlock.append(hotelTer);
	}
	if (data.hotel_info.child!="")
	{
		var hotelTer = $('<div class="infoWrapper"><div class="infoLabel">Для детей</div><div class="">'+data.hotel_info.child+'</div></div>');
		hotelBlock.append(hotelTer);
	}
	
	
	if (data.hotel_info.inroom!="")
	{
		var hotelTer = $('<div class="infoWrapper"><div class="infoLabel">В номере</div><div class="">'+data.hotel_info.inroom+'</div></div>');
		hotelBlock.append(hotelTer);
	}
	/***************************************/
   // if(data.tour_info.regular==0)
   // {    
        hotelOrderType = $('<div class="hotelOrderType"><div class="orderBuyType active">Купить онлайн</div><div class="orderSendType">Отправить запрос</div></div>');
        hotelOrder.append(hotelOrderType);
    
        var formOnline = $('<form method="post" name="orderOnline"></form>');
        formOnline.append('<div class="orderSubTtl">Возрослые:</div>');
        j=1;
        for (var i=1;i<=data.tour_info.adults;i++)
        {
            pasportForm (formOnline,j,false,false);
            j++;
        } 
        if (data.tour_info.child>0)
        {
            formOnline.append('<div class="orderSubTtl">Дети:</div>');
            for (var i=1;i<=data.tour_info.child;i++)
            {
                pasportForm (formOnline,j,true,false);
                j++;
            } 
        }
        var formOnlineDop = $('<div class="orderOnlineDop"></div>');
		formOnlineDop.append('<div class="turistBlockSubTitile">Заказчик</div>');
        formOnlineDop.append('<div class="orderWide"><div class="orderInner"><div class="orderInputText">Ваше ФИО (полностью)</div><div class="orderInputWrap"><input id="userName" type="text" value=""></div></div></div>');
		
		formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Серия и номер гражд. паспорта</div> <div class="orderInputWrap"><input type="text" id="userPassSerNum" name="userPassSerNum" value="" /></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Дата выдачи паспорта</div> <div class="orderInputBdWrap" id=""><div class="orderBdateD"><input type="text" id="userPassd0" name="userPassd0" value="" placeholder="ДД" class="numField" data-len="2"> </div><div class="orderBdateM"><input type="text" id="userPassm0" name="userPassm0" value="" placeholder="ММ" class="numField" data-len="2"></div><div class="orderBdateY"><input type="text" id="userPassy0" name="userPassy0" value="" placeholder="ГГГГ" class="numField" data-len="4"></div></div></div>');
		
		
		formOnlineDop.append('<div class="clear"></div><div class="orderWide"><div class="orderInner"><div class="orderInputText">Кем выдан</div><div class="orderOnlineTextWrap"><textarea id="userPassWho" name="userPassWho" placeholder="УФМС г. Москва"></textarea></div></div>');
		
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш телефон</div><div class="orderInputWrap"><input id="userPhone" type="text" name="userPhone" value="" placeholder="+7 (___) ___-__-__"></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш email</div> <div class="orderInputWrap"><input type="text" id="userEmail" name="userEmail" value="" /></div></div></div>');
        
        formOnlineDop.append('<div class="clear"></div><div class="clear"></div>');
        formOnlineDop.append('<div class="orderBot"><div class="orderInputText">Дополнительная информация</div><div class="orderTextWrap"><textarea id="userComment" name="userComment" ></textarea></div></div>');
        
        formOnlineDop.append('<input type="hidden" name="ordHid" id="ordHid" value="'+data.tour_info.hid+'" />');
        formOnlineDop.append('<input type="hidden" name="ordTid" id="ordTid" value="'+data.tour_info.tid+'" />');
        formOnlineDop.append('<input type="hidden" name="yaClient"      class="yaClient" value="'+clientID+'" />');
		formOnlineDop.append('<input type="hidden" name="yaClid"        class="yaClid" value="'+yclid+'" />');
		formOnlineDop.append('<input type="hidden" name="yaUtmSource"   class="yaUtmSource" value="'+utmSource+'" />');
		formOnlineDop.append('<input type="hidden" name="yaUtmMedium"   class="yaUtmMedium" value="'+utmMedium+'" />');
		formOnlineDop.append('<input type="hidden" name="yaUtmCampaign" class="yaUtmCampaign" value="'+utmCampaign+'" />');
		formOnlineDop.append('<input type="hidden" name="yaUtmContent"  class="yaUtmContent" value="'+utmContent+'" />');
		formOnlineDop.append('<input type="hidden" name="yaUtmTerm"     class="yaUtmTerm" value="'+utmTerm+'" />');
        formOnlineDop.append('<input type="submit" class="orderSend" value="Отправить заявку">');
        
        formOnline.append(formOnlineDop); 
        hotelOrderOnline.addClass('active');
        hotelOrderOnline.append(formOnline);
        hotelOrder.append(hotelOrderOnline);
       
   /* }
    else
    {
       
        hotelOrderType = $('<div class="hotelOrderType"><div class="orderSendType active">Отправить запрос</div></div>');
        hotelOrder.append(hotelOrderType);
        hotelOrderOffice.addClass('active');
    }  */  
	/**************************************/
	var form = $('<form method="post" name="orderOffice"></form>');
    form.append('<div class="orderLeft"><div class="orderInner"><div class="orderInputText">Ваше имя</div><div class="orderInputWrap"><input id="userName" type="text" value=""></div></div></div>');
    form.append('<div class="orderRight"><div class="orderInner"><div class="orderInputText">Ваш телефон</div><div class="orderInputWrap"><input id="userPhone" type="text" name="userPhone" value="" placeholder="+7 (___) ___-__-__"></div></div></div>');
     form.append('<div class="orderMid"><div class="orderInner"><div class="orderInputText">Ваш email</div> <div class="orderInputWrap"><input type="text" id="userEmail" name="userEmail" value="" /></div></div></div>');
    form.append('<div class="clear"></div><div class="clear"></div>');
    form.append('<div class="orderBot"><div class="orderInputText">Дополнительная информация</div><div class="orderTextWrap"><textarea id="userComment" name="userComment" ></textarea></div></div>');
    
    form.append('<input type="hidden" name="ordHid" id="ordHid" value="'+data.tour_info.hid+'" />');
    form.append('<input type="hidden" name="ordTid" id="ordTid" value="'+data.tour_info.tid+'" />');
    form.append('<input type="hidden" name="yaClient"      class="yaClient" value="'+clientID+'" />');
	form.append('<input type="hidden" name="yaClid"        class="yaClid" value="'+yclid+'" />');
	form.append('<input type="hidden" name="yaUtmSource"   class="yaUtmSource" value="'+utmSource+'" />');
	form.append('<input type="hidden" name="yaUtmMedium"   class="yaUtmMedium" value="'+utmMedium+'" />');
	form.append('<input type="hidden" name="yaUtmCampaign" class="yaUtmCampaign" value="'+utmCampaign+'" />');
	form.append('<input type="hidden" name="yaUtmContent"  class="yaUtmContent" value="'+utmContent+'" />');
	form.append('<input type="hidden" name="yaUtmTerm"     class="yaUtmTerm" value="'+utmTerm+'" />');


    form.append('<input type="submit" class="orderSend" value="Отправить заявку">');
	
	hotelOrderOffice.append('<div class="orderFormTitle">Запрос на информацию по туру</div>')
					.append(form);
					
	hotelOrder.append(hotelOrderOffice);
	/**************************************/
	hotelWrap.append(hotelBlock)
			 .append(hotelOrder);

	div.append(hotelWrap);

	$.fancybox.open( [div],{"wrapCSS":"turZakazWrap","touch":false} );
    
    
    var phoneOptions =  {
        onComplete: function(cep) {
           
            $('input[name=userPhone]').addClass('validphone');
            
        },
        onChange: function(cep){
            if (cep.length<18) {
                $('input[name=userPhone]').removeClass('validphone');
            }
        },
        onKeyPress: function(cep, event, currentField, options){
            if (cep.length<18) {
                $('input[name=userPhone]').removeClass('validphone');
            }
        },
        onInvalid: function(val, e, f, invalid, options){
            $('input[name=userPhone]').removeClass('validphone');
        }
    };
    
	$('input[name=userPhone]').mask('+7 (000) 000-00-00',phoneOptions);
    
	$('.hotelPhoto').slick({
        infinite: false,
        speed: 300,
        centerMode: false,
        prevArrow: '<div class="slick-prev"><div class="bIcon"></div></div>',
        nextArrow: '<div class="slick-next"><div class="bIcon"></div></div>'
    });
	
	/**************************************/
	
	
	data={tid:data.tour_info.tid,act_tour_info:"y"};
	var url = $("form[name=searchForm]").attr('action');
	$.ajax( hotAjaxUrl, {
		cache: false,
		data: data,
		dataType: "json",
		error: errorHandler,
		success: successTourInfoUpdate,
		type: "POST"
	});
	
	
}

function successTourInfoUpdate( data )
{
	$('.tourHotelWrapInner').removeClass('loading');
	$('.hotelNextStep').removeClass('loading');
	if(data['data']['price'])
		$('.tourHotelWrapInner .hotelTourPrice span').html(data['data']['price']);
	if(data['data']['date_from'])
		$('.tourHotelWrapInner .tourDateFrom span').html(data['data']['date_from']);
	if(data['data']['date_to'])
		$('.tourHotelWrapInner .tourDateTo span').html(data['data']['date_to']);
	if(data['data']['nights'])
		$('.tourHotelWrapInner .tourDateNights span').html(data['data']['nights']);
	if(data['data']['placement'])
		$('.tourHotelWrapInner .tourRoom span').html(data['data']['placement']);
    if(data['data']['fuel'] && data['data']['fuel']!="0")
    {     
		$('.tourHotelWrapInner .fuelBlock span').html(data['data']['fuel']+" руб.");
        $('.tourHotelWrapInner .fuelBlock').show();
    }    
	
	if(data['data']['adults']>0)
	{	

		var formOnline = $('form[name=orderOnline]');
		formOnline.find('.orderSubTtl').remove();
		formOnline.find('.turistBlock').remove();
        formOnline.find('.orderOnlineDop').before('<div class="orderSubTtl">Возрослые:</div>');
        j=1;
        for (var i=1;i<=data['data']['adults'];i++)
        {
            pasportForm(formOnline,j,false,true);
            j++;
        } 
        if (data['data']['child']>0)
        {
            formOnline.find('.orderOnlineDop').before('<div class="orderSubTtl">Дети:</div>');
            for (var i=1;i<=data['data']['child'];i++)
            {
                pasportForm (formOnline,j,true,true);
                j++;
            } 
        }
	}
	requestDetail = false;
	
}	

function pasportForm (form,j,isChild,addBeforeDop)
{
    var block = $('<div class="turistBlock"></div>');
    var line=$('<div class="onLine"></div>');
    if(isChild)
        line.append('<div class="turistBlockSubTitile">Ребенок '+j+'</div>');
    else
        line.append('<div class="turistBlockSubTitile">Взрослый '+j+'</div>');
    
    line.append('<div class="onName"><div class="orderInputText">Имя (латиницей)</div><div class="orderInputWrap"><input type="text" id="name'+j+'" name="name'+j+'" value="" placeholder="Ivan" class="latField"/></div></div>');
    line.append('<div class="onSname"><div class="orderInputText">Фамилия (латиницей)</div> <div class="orderInputWrap"><input type="text" id="sname'+j+'" name="sname'+j+'" value="" placeholder="Ivanov" class="latField"/></div></div>');
    line.append('<div class="onBdate"><div class="orderInputText">Дата рождения</div><div class="orderInputBdWrap" id="bdate'+j+'"><div class="orderBdateD"><input type="text" id="bdated'+j+'" name="bdated'+j+'" value="" placeholder="ДД" class="numField" data-len="2"/></div>  <div class="orderBdateM"><input type="text" id="bdatem'+j+'" name="bdatem'+j+'" value="" placeholder="ММ" class="numField" data-len="2"/></div><div class="orderBdateY"><input type="text" id="bdatey'+j+'" name="bdatey'+j+'" value="" placeholder="ГГГГ" class="numField" data-len="4"/></div></div></div>');
    line.append('<div class="onNat"><div class="orderInputText">Гражд.</div> <div class="orderInputWrap"><input type="text" id="nat'+j+'" name="nat'+j+'" value="RU" /> </div></div>');
    line.append('<div class="onGend"><div class="orderInputText">Пол</div><div class="searchSelect sSelectWrap"><span class="searchSelectText sSelectSpan"> Ж </span><select id="gend'+j+'" class="sSelect"><option selected="selected" value="F">Ж</option><option value="M">М</option></select></div></div>');
    line.append('<div class="clear"></div>');
    block.append(line);
    line=$('<div class="onLine"></div>');
    line.append('<div class="onName"><div class="orderInputText">Серия загранпаспорта</div><div class="orderInputWrap"><input type="text" id="pasSer'+j+'" name="pasSer'+j+'" value="" placeholder="01"/></div></div>');
    line.append('<div class="onSname"><div class="orderInputText">Номер загранпаспорта</div><div class="orderInputWrap"><input type="text" id="pasNom'+j+'" name="pasNom'+j+'" value="" placeholder="23456789"/></div></div>');
    line.append('<div class="onBdate"><div class="orderInputText">Дата выдачи</div><div class="orderInputBdWrap" id="pasFrom'+j+'"><div class="orderBdateD"><input type="text" id="pasFromd'+j+'" name="pasFromd'+j+'" value="" placeholder="ДД" class="numField" data-len="2"/> </div><div class="orderBdateM"><input type="text" id="pasFromm'+j+'" name="pasFromm'+j+'" value="" placeholder="ММ" class="numField" data-len="2"/></div><div class="orderBdateY"><input type="text" id="pasFromy'+j+'" name="pasFromy'+j+'" value="" placeholder="ГГГГ" class="numField" data-len="4"/></div></div></div>');
    line.append('<div class="onNat onTill"><div class="orderInputText">Годен до</div><div class="orderInputBdWrap" id="pasTill'+j+'"><div class="orderBdateD"><input type="text" id="pasTilld'+j+'" name="pasTilld'+j+'" value="" placeholder="ДД" class="numField" data-len="2"/> </div><div class="orderBdateM"><input type="text" id="pasTillm'+j+'" name="pasTillm'+j+'" value="" placeholder="ММ" class="numField" data-len="2"/></div><div class="orderBdateY"><input type="text" id="pasTilly'+j+'" name="pasTilly'+j+'" value="" placeholder="ГГГГ" class="numField" data-len="4"/></div></div></div>');
    line.append('<div class="clear"></div>');
    block.append(line);
    block.append('<div class="orderBot"><div class="orderInputText">Кем выдан</div><div class="orderOnlineTextWrap"><textarea id="pasOut'+j+'" name="pasOut'+j+'"  placeholder="УФМС г. Москва"></textarea></div></div>');
    if (addBeforeDop)
		form.find('.orderOnlineDop').before(block);
	else	
		form.append(block);
}    

function successOrder (res)
{
	$.fancybox.close();
    if (res.send==1)
	{
        var mess = $('<div id="claimOkMain">Спасибо за Вашу заявку!<br /><br>В ближайшее время с Вами свяжутся наши менеджеры</div>');
		if (res.data.commerce==1)
		{
			window.dataLayer = window.dataLayer || [];
			dataLayer.push({
				"ecommerce": {
					"purchase": {
						"actionField": {
							"id" : res.data.id,
							"revenue" : res.data.revenue,
							"goal_id":278300417
						},
						"products": [
							{
								"name": res.data.name,
								"price": res.data.price,
								"category": res.data.category
							}
							
						]
					}
				}
			});
			
			/*gtag('event', 'purchase', {
				"transaction_id": res.data.id,
				"value": res.data.revenue,
				"currency": "RUB",
				"items": [{
					"name": res.data.name,
					"price": res.data.price,
					"category": res.data.category,
					"quantity": 1
				}]
			});*/
			VK.Goal('purchase', {value: res.data.revenue});
			
		}
	}	
    else
        var mess = $('<div id="claimOkMain">Произошла ошибка!<br /><br>Повторите отправку заявки</div>');
    $.fancybox.open( [mess],{"wrapCSS":"turZakazWrap"} );
}

function errorHandler() {
	alert( "Произошла ошибка. Повторите запрос" );
	$("#search_start").removeClass('disabled');
}

function getRandomInt(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min)) + min; //Максимум не включается, минимум включается
}

function checkDate (name,indx)
{
	//console.log(name);
    res = {};
    var er = false;
    var year = new Date().getFullYear();
    var bd = parseInt($("#"+name+"d"+indx).val());
    var bm = parseInt($("#"+name+"m"+indx).val());
    var by = parseInt($("#"+name+"y"+indx).val());
    
    if (bd<1 || bd>31 || isNaN(bd)) {er = true;console.log('err1');}
    if (bm<1 || bm>12 || isNaN(bm)) {er = true;console.log('err12');}
    if (by<1920 || by>year && name!="pasTill" || isNaN(by)) {er = true;console.log('err3');}
    if (!er)
    {    
        //bm = bm-1;
        var dt = new Date(by,(bm-1),bd);
        //res.date= $.datepicker.formatDate( "yy-mm-dd", dt );
		var m = dt.getMonth()+1,
		d = dt.getDate();
		if(m<10)
			m = "0"+m;
		if(d<10)
			d = "0"+d;
		res.date=  dt.getFullYear() +  "-" + m + "-" + d;
		res.dt=dt;
    }
    res.error = er;
    return res;
}

Date.prototype.yyyymmdd = function() {
  var mm = this.getMonth() + 1; // getMonth() is zero-based
  var dd = this.getDate();

  return [this.getFullYear(),
          (mm>9 ? '' : '0') + mm,
          (dd>9 ? '' : '0') + dd
         ].join('-');
};


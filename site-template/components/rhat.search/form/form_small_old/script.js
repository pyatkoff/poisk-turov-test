var tid = 0;
reqid = 0,
interval = 3000,
tours=0,
requestDetail = true;

$(document).ready(function() {
	
	
	$('.sSelect').change( function() {
        var vl = $(this).val();
        var txt =$(this).children("option[value='"+vl+"']").text();
        $(this).prev('span').html(txt);
    });    
   
	
	
	
    $('#count_child').change( function() {
        var cn = $(this).val();
        if (cn >= 1)
        {
            $('#child_year1').prop("disabled", false).prev().text(0).parent().removeClass('disabled');
            if (cn == 2)
            {
                $('#child_year2').prop("disabled", false).prev().text(0).parent().removeClass('disabled');
                $('#child_year3').prop("disabled", true).prev().text('нет').parent().addClass('disabled');
                $('#child_year3').val(0);
            }	
            else if (cn == 3)
            {
                $('#child_year2').prop("disabled", false).prev().text(0).parent().removeClass('disabled');
                $('#child_year3').prop("disabled", false).prev().text(0).parent().removeClass('disabled');
                  
            }	
            else
            {
                $('#child_year2').prop("disabled", true).prev().text('нет').parent().addClass('disabled');
                $('#child_year2').val(0);
                $('#child_year3').prop("disabled", true).prev().text('нет').parent().addClass('disabled');
                $('#child_year3').val(0); 
            }	  
        }
        else
        {
            $('#child_year1').prop("disabled", true);
            $('#child_year2').prop("disabled", true);
            $('#child_year3').prop("disabled", true);
            $('#child_year1').val(0).prev().text('нет').parent().addClass('disabled');
            $('#child_year2').val(0).prev().text('нет').parent().addClass('disabled');
            $('#child_year3').val(0).prev().text('нет').parent().addClass('disabled');
        }  
    });
    /* 
    $("#hotel_get").click( function(e) {
        if ($(this).is(':checked') && $("#hotel_all").is(':checked'))
        {    
            $("#hotelWrap .searchLabel").removeClass("bigMargin");
            $("#hotelBlock").show().addClass('loaded');
            $("#hotel_all").prop( "checked", false );
            var region =[];
            $("input[name=region]:checked").each(function(index){
                region[index]= $(this).val();
            });
            var cn = $("#country").val();
            var data = { mode:"hotel" , country: cn};
            if (region.length>0)
            {    
                data.resort=region;
            }
            $.ajax( $("form[name=searchForm]").attr('action'), {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successHotel,
                type: "POST"
            });
        }    
        else
            e.preventDefault();
      
    })
    
    $(".allcb").click( function(e) {
        var name = $(this).attr("data-for");
        $('input[name='+name+']:checked').prop('checked', false);
        $(this).prop('disabled', true);
    });
    
    $('.sCbSearch').click( function(e) {
        var name = $(this).attr('name');
        if ($(this).is(':checked'))
        {    
            $('#'+name+'_all').prop('checked', false).prop('disabled', false);
        }
        else
        {    
            var l  = $('input[name='+name+']:checked').length;
            if (l == 0)
            {
                $('#'+name+'_all').prop('checked', true).prop('disabled', true);
            }    
        }    
    });
    
    $("#hotel_all").click( function(e) {
        if ($(this).is(':checked') && $("#hotel_get").is(':checked'))
        {    
            $("#hotel_get").prop( "checked", false );
            $("#hotelBlock").find('input[type=checkbox]:checked').prop('checked', false).parent().removeClass('activelbl');
        }    
        else
            e.preventDefault();
    })
    
    $("#reg_all").click( function(e) {
        $("#regionBlock").find('input[type=checkbox]:checked').prop('checked', false);
        $("#regionBlock").find('.activelbl').removeClass('activelbl');
        $(this).prop('disabled', true);
        if ($("#hotelBlock").hasClass('loaded')) 
        {
            var cn = $("#country").val();
            var data = { mode:"hotel" , country: cn};

            $.ajax( "/include/ajax/aj.php", {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successHotel,
                type: "POST"
            });
        }
        
    });
        
    $('.sSelect').change( function() {
        var vl = $(this).val();
        var txt =$(this).children("option[value='"+vl+"']").text();
        $(this).prev('span').html(txt);
    });    
    
    $('#country').change( function() {
        var cn = $(this).val();
        var data = { mode:"resort", country: cn };
        $.ajax( $("form[name=searchForm]").attr('action'), {
            cache: false,
            data: data,
            dataType: "json",
            error: errorHandler,
            success: successResort,
            type: "POST"
        });
        if ( $("#hotelBlock").hasClass('loaded'))
        {    
            var data = { mode:"hotel" , country: cn };
            $.ajax( $("form[name=searchForm]").attr('action'), {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successHotel,
                type: "POST"
            });
        }
    }); 
    
    $("body").on( "change", '.sCbList input', function() {
        if ($(this).is(":checked"))
        {
            $(this).parent().addClass('activelbl');
            if ($(this).attr("name")=="region")
            {
                if ($("#hotelBlock").hasClass('loaded'))
                {    
                    var region =[];
                    $("input[name=region]:checked").each(function(index){
                        region[index]= $(this).val();
                    });
                    if (region.length>0)
                    {    
                        var cn = $("#country").val();
                        var data = { mode:"hotel" , country: cn, resort:region };
                        $.ajax( $("form[name=searchForm]").attr('action'), {
                            cache: false,
                            data: data,
                            dataType: "json",
                            error: errorHandler,
                            success: successHotel,
                            type: "POST"
                        });
                    }
                }
                
                $('#reg_all').prop('checked', false).prop('disabled', false);
            }  
        }  
        else    
        {
            $(this).parent().removeClass('activelbl');
            if ($(this).attr("name")=="region")
            {
                var region =[];
                $("input[name=region]:checked").each(function(index){
                    region[index]= $(this).val();
                });
                var cn = $("#country").val();
                var data = { mode:"hotel" , country: cn};
                if (region.length>0)
                {    
                    data.resort=region;
                }
                else
                {
                    $('#reg_all').prop('checked', true).prop('disabled', true);
                }    
                if ($("#hotelBlock").hasClass('loaded')) {
                    $.ajax( $("form[name=searchForm]").attr('action'), {
                        cache: false,
                        data: data,
                        dataType: "json",
                        error: errorHandler,
                        success: successHotel,
                        type: "POST"
                    });
                }
               
            }  
        }      
    });    
    $('input[name=stars]').change( function() {
		var checked = $(this).is(":checked"),
		val = parseInt($(this).val()),
		parent=$(this).parent(),
		sib = parent.siblings();
		if (checked)
		{
			$.each(sib,function(i,v){
				var cb = $(this).find('input');
				if(parseInt($(this).attr('data-star'))>val)
					cb.prop('checked',false);
				else
					cb.prop('checked',true);
				
			});
		}
		else
		{
			$.each(sib,function(i,v){
				var cb = $(this).find('input');
			
				if(parseInt($(this).attr('data-star'))>val)
					cb.prop('checked',false);
				
				
			});
		}
	});
	
	$('input[name=food]').change( function() {
		if ($(this).is(":checked"))
		{
			$("#foodWrap").find('input.sCbSearch:checked').not("#"+$(this).attr('id')).prop('checked',false);
		}
	});
	
	
    $("form[name=searchForm]").submit(
		function(e)
		{
            e.preventDefault();
            
            $("#searchInfo").show();
            $("#searchStatus .start").show();
            $("#searchStatus .finish").hide(); 
			$("#showMoreResultWrap").hide();
            $("#toursPrice").hide(); 
            $("#searchResult").hide(); 
            $("#searchCount").html('0');
            $("#searchProc").html('0');
            $(".seacrhMetInner").css({'width': '0%'});
            if (tid>0) clearTimeout(tid);
            tid = 0;
            reqid = 0;
            tours=0;
            url = $(this).attr('action');
            data = prepareData();
            /*console.log(data);*
            
            
            $.ajax( url, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successCreate,
                type: "POST"
            });
        }
    );  

    $("#showMoreResult").click(function(){
        if (reqid>0) 
            getCurrentResult(1, true);    
    }); 
    
    $("body").on('click',".hotelTours",function(){
        if (reqid>0) 
        {     
            var hid =$(this).attr('data-id');
            data={req:reqid, hid:hid,get_hotel_tours:'y' };
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successTours,
                type: "POST"
            });
        }   
    });
    
    $("body").on('click',".hotelInfo",function(){
        if (reqid>0) 
        {     
            var hid =$(this).attr('data-id');
            data={hid:hid,get_hotel_info:'y' };
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successHotelInfo,
                type: "POST"
            });
        }   
    });
    
    
    $("#content").on("click",".tourInfoOffline",function(e)
    {
        var req = $(this).attr('data-req');
        var sor = $(this).attr('data-sor'); 
        var off = $(this).attr('data-off'); 
        constructOrder (req,off,sor);        
    });
    
    $("body").on("click",".airTourVar",function(e)
    {
        var req = $(this).attr("data-req"),
        hid = $(this).attr("data-hid"),
        tid = $(this).attr("data-tid"),
        data={req:req,hid:hid,tid:tid,get_tour_info:"y"};
        var url = $("form[name=searchForm]").attr('action');
        $.ajax( url, {
            cache: false,
            data: data,
            dataType: "json",
            error: errorHandler,
            success: successTourInfo,
            type: "POST"
        });
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
					var req = $('.hotelOrderOnline input[name=ordReq]').val(),
					hid = $('.hotelOrderOnline input[name=ordHid]').val(),
					tid = $('.hotelOrderOnline input[name=ordTid]').val();
					data={req:req,hid:hid,tid:tid,update_detail:"y"};
					var url = $("form[name=searchForm]").attr('action');
					$.ajax( url, {
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
        $('#userName').parent().removeClass('errorBord');
        $('#userPhone').parent().removeClass('errorBord');
        $('#userEmail').parent().removeClass('errorBord');
        var uname  = $("#userName").val();
        var uemail = $("#userEmail").val();
        var uphone = $("#userPhone").val();
        var utext  = $("#userComment").val();
        var oreq   = $("#ordReq").val();
        var ohid   = $("#ordHid").val();
        var otid   = $("#ordTid").val();
        if (uname=="" ||  uphone=="" &&  uemail=="")
        {
            if (uname==""){$('#userName').attr('placeholder','Укажите Ваше имя').parent().addClass('errorBord');}
            if (uphone=="" &&  uemail=="")
            {    
                if (uphone==""){$('#userPhone').attr('placeholder','Укажите Ваш телефон').parent().addClass('errorBord');}
                if (uemail==""){$('#userEmail').attr('placeholder','Укажите Ваш email').parent().addClass('errorBord');}
            }
            
        } 
        else
        {    
            data={officeorder:1, name:uname, email:uemail, phone:uphone, text:utext, req:oreq, hid: ohid, tid : otid };
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
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
		oreq   = $("#ordReq").val(),
        ohid   = $("#ordHid").val(),
        otid   = $("#ordTid").val(),
		tourists = [],
		error  = false;
		
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
		
        if ( uname=="" ||  uphone=="" &&  uemail=="")
        {
			error = true;
            if (uname==""){$('#userName').attr('placeholder','Укажите Ваше имя').parent().addClass('errorBord');}
			
            if (uphone=="" &&  uemail=="")
            {    
                if (uphone==""){$('#userPhone').attr('placeholder','Укажите Ваш телефон').parent().addClass('errorBord');}
                if (uemail==""){$('#userEmail').attr('placeholder','Укажите Ваш email').parent().addClass('errorBord');}
            }
            
        } 
		if(error)
		{
			$(this).append('<div class="errorBlock">Необходимо заполнить все обязательные поля</div>');
		}	
		else
		{
			data={onlineorder:1, name:uname, email:uemail, phone:uphone, text:utext, req:oreq, hid: ohid, tid : otid, tourists:tourists  };
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successOrder,
                type: "POST"
            });
		}
		
	});
	
	
	$('body').on('click','.orderSendType',function(){
        $(this).addClass('active');
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
	*/
});	

function errorHandler() {
	alert( "Произошла ошибка. Повторите запрос" );
}

function successResort( data ) {
    if (data.resortList != null)
    { 
        var  div = '';
        $.each(data.resortList,function(index,val) {
            div += '<div class="sCbList" id="hotel'+val.id+'W">';
            div += '<label><input id="region'+val.id+'" type="checkbox" name="region" value="'+val.id+'">';
            div += '<span>'+val.name+'</span>';
            div += '</label></div>';  
        });
        $("#regionBlock").html(div);
    }
}

/*

function successCreate( data ) {
    reqid = data.reqid;
    $("#searchCount").html(data.tcount);
    $("#searchProc").html(data.percent);
    $(".seacrhMetInner").css({'width': data.percent+'%'});
    if (data.tcount > 0)
    {    
        $("#searchResult").show();
        tours = data.tcount;
        if (data.minprice!=false)
        {
            $("#toursPrice").show();
            $("#searchPrice").html(data.minprice);
        }    
        //constructResult(data);
		constructHotelsResult(data);
    }    
    if (data.percent<100)
        tid = setTimeout(checkReqState, interval);
    
}

function successUpdate( data ) {
    $("#searchProc").html(data.percent);
    $(".seacrhMetInner").css({'width': data.percent+'%'});
    if (data.minprice!=false)
    {
        $("#searchPrice").html(data.minprice);
    }  
    if (data.tcount > 0)
    {    
        if (tours==0)
        {    
            tours = data.tcount;
            //constructResult(data);
			constructHotelsResult(data);
            $("#toursPrice").show();
            $("#searchResult").show();
            $("#searchCount").html(data.tcount);
        }    
        else if (data.tcount > tours)
        {    
            $("#searchCount").html('<span class="small">еще</span> '+(data.tcount-tours));
			$("#searchCount2 span").html((data.tcount-tours));
            $("#showMoreResultWrap").show();
        }   
    }
    
    if (data.percent<100)
        tid = setTimeout(checkReqState, interval);
    else
    {
        $("#searchStatus .start").hide(); 
        $("#searchStatus .finish").show();
    }    
}

function successCurrentResult( data ) {
  
    if (data.tcount > 0)
    {    
        if (data.update == 1)
        {    
            $("#showMoreResultWrap").hide();
            $("#searchCount").html(data.tcount);
        }
        //constructResult(data);
		constructHotelsResult(data);
        tours = data.tcount;
        $("html,body").animate({ scrollTop: $("#searchInfo").offset().top-20  }, "slow");
    }

}

function checkReqState() {  
    
    if (reqid > 0)
    {    
        url = $("form[name=searchForm]").attr('action');
        data = { search_update: 1,  reqid: reqid};
        $.ajax( url, {
            cache: false,
            data: data,
            dataType: "json",
            error: errorHandler,
            success: successUpdate,
            type: "POST"
        });
    }
}

function getCurrentResult(page, update) {  
    
    if (reqid > 0 && page>0)
    {    
        url = $("form[name=searchForm]").attr('action');
        data = { search_result: 1,  reqid: reqid, page:page, upd:update};
        $.ajax( url, {
            cache: false,
            data: data,
            dataType: "json",
            error: errorHandler,
            success: successCurrentResult,
            type: "POST"
        });
    }
}

/*function constructResult( data ) {
    $("#searchResultTable").html('');
    var tours = data.tours;
    
    $.each(tours,function(index,val) {
        var  div = $('<div class="airTour"></div>');
        var  airPic = $('<div class="airPic"></div>');
        var  air480 = $('<div class="dop480"></div>');
        var  airHotel = $('<div class="airHotel"></div>');
        var  airPrice = $('<div class="airPrice"></div>');
        var  airInfo = $('<div class="airInfo"></div>');
		var  airDop = $('<div class="airDopBtns"><div class="dopBtn">Об отеле</div><div class="dopBtn">Цены</div></div>');
        var  hotelName = $('<div class="hotelName">'+val.hotel+'</div>');
        var  hotelStars = $('<div class="hotelStars"></div>');
        var  hotelCountry = $('<div class="hotelCountry"></div>');
        var  hotelRate = $('<div class="hotelRate"></div>');
        if (val.hotel_star>0)
        {
            for($i=1;$i<=val.hotel_star;$i++) 
                hotelStars.append('<div class="gStar"></div>');
            hotelStars.append('<div class="clear"></div>');
        }
        if (val.hotel_rate<=0) { val.hotel_rate = '?'; }
            hotelRate.append('<div class="hotelRateTxt">Рейтинг отеля: '+val.hotel_rate+' </div>');
            
        if (val.hotel_rate!='?') {  val.hotel_rate = val.hotel_rate*20;} else {val.hotel_rate=0;}
        hotelRate.append('<div class="rateMet"><div class="rateMetInner" style="width:'+val.hotel_rate+'%"></div></div>');
        
        if (val.hotel_img!=false) 
            airPic.append(val.hotel_img);
        else
            airPic.append('<img class="noPic" width="70" height="70" src="/upload/no_img.png">');
        /*****
        hotelCountry.append(val.country);
        if (val.resort!="")
            hotelCountry.append(", "+val.resort);
        /*****
        air480.append('<div class="airInfoLine">Ночей: <span class="bold">'+val.nights+'</span></div>');
        air480.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.date+'</span></div>');
        air480.append('<div class="airInfoLine">Размещение: <span class="bold">'+val.room+'</span></div>');
        air480.append('<div class="airInfoLine">Питание: <span class="bold">'+val.meal+'</span></div>');
        air480.append(hotelCountry.clone());
        air480.append(hotelRate.clone());
        /*****     
       
        airHotel.append(hotelName).append(hotelStars).append(hotelCountry).append(hotelRate);
        /*****
        airPrice.append('<div class="airMainPrices"><div class="airMainPrice"><span class="grey">от</span><span class="mainPrice"> '+val.price + ' </span> руб.</div></div>');
       
        /*****
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.cityfrom+'</span></div>');
        airInfo.append('<div class="airInfoLine lineRoom">Размещение: <span class="bold">'+val.room+'</span></div>');
        airInfo.append('<div class="airInfoLine">Питание: <span class="bold">'+val.meal+'</span></div>');
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.date+'</span></div>');
        airInfo.append('<div class="airInfoLine">Ночей: <span class="bold">'+val.nights+'</span></div>');
        /*****
		
        div.append(airPic).append(air480).append(airHotel).append(airPrice).append(airInfo).append('<div class="clear"></div>').append(airDop).append('<div class="clear"></div>');
		 
		
        $("#searchResultTable").append(div);
        $("#searchResultTable").append('<div class="clear"></div>');
    });
    $("#searchResultTable").append(data.pager);
}*/
/*
function constructHotelsResult( data ) {
    $("#searchResultTable").html('');
    var tours = data.tours;
    
    $.each(tours,function(index,val) {
        var  div = $('<div class="airTour" id="hotel_'+val.id+'"></div>');
        var  airPic = $('<div class="airPic"></div>');
        var  air480 = $('<div class="dop480"></div>');
        var  airHotel = $('<div class="airHotel"></div>');
        var  airPrice = $('<div class="airPrice"></div>');
        var  airInfo = $('<div class="airInfo"></div>');
		var  airDop = $('<div class="airDopBtns"><div class="airDopBtn hotelInfo" data-id="'+val.id+'">Об отеле</div><div class="airDopBtn hotelTours" data-id="'+val.id+'">Цены</div></div>');
        var  hotelName = $('<div class="hotelName">'+val.hotel+'</div>');
        var  hotelStars = $('<div class="hotelStars"></div>');
        var  hotelCountry = $('<div class="hotelCountry"></div>');
        var  hotelRate = $('<div class="hotelRate"></div>');
		var  hstar = parseInt(val.hotel_star);
        if (hstar>0)
        {
            for($i=1;$i<=hstar;$i++) 
                hotelStars.append('<div class="gStar"></div>');
            hotelStars.append('<div class="clear"></div>');
        }
		else if (val.hotel_star!="")
		{
			  hotelStars.append(val.hotel_star);
		}	
		
        if (val.hotel_rate<=0) { val.hotel_rate = '?'; }
            hotelRate.append('<div class="hotelRateTxt">Рейтинг отеля: '+val.hotel_rate+' </div>');
            
        if (val.hotel_rate!='?') {  val.hotel_rate = val.hotel_rate*20;} else {val.hotel_rate=0;}
        hotelRate.append('<div class="rateMet"><div class="rateMetInner" style="width:'+val.hotel_rate+'%"></div></div>');
        
        if (val.hotel_img!=false) 
            airPic.append(val.hotel_img);
        else
            airPic.append('<img class="noPic" width="70" height="70" src="/upload/no_img.png">');
        /*****
        hotelCountry.append(val.country);
        if (val.resort!="")
            hotelCountry.append(", "+val.resort);
        /*****
        air480.append('<div class="airInfoLine">Ночей: <span class="bold">'+val.nights+'</span></div>');
        air480.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.date+'</span></div>');
        air480.append('<div class="airInfoLine">Размещение: <span class="bold">'+val.room+'</span></div>');
        air480.append('<div class="airInfoLine">Питание: <span class="bold">'+val.meal+'</span></div>');
        air480.append(hotelCountry.clone());
        air480.append(hotelRate.clone());
        /*****    
       
        airHotel.append(hotelName).append(hotelStars).append(hotelCountry).append(hotelRate);
        /*****
        airPrice.append('<div class="airMainPrices"><div class="airMainPrice"><span class="grey"><span class="greyTours">туры </span>от</span><span class="mainPrice"> '+val.price + ' </span> руб.</div></div>');
       
        /*****
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.cityfrom+'</span></div>');
        airInfo.append('<div class="airInfoLine lineRoom">Размещение: <span class="bold">'+val.room+'</span></div>');
        airInfo.append('<div class="airInfoLine">Питание: <span class="bold">'+val.meal+'</span></div>');
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.date+'</span></div>');
        airInfo.append('<div class="airInfoLine">Ночей: <span class="bold">'+val.nights+'</span></div>');
        /*****
        div.append(airPic).append(air480).append(airHotel).append(airPrice).append('<div class="clear"></div>').append(airDop).append('<div class="clear"></div>');
        $("#searchResultTable").append(div);
        $("#searchResultTable").append('<div class="clear"></div>');
    });
    $("#searchResultTable").append(data.pager);
}



function successTours( data )
{
    var  hid = data.hotel,
    div = $('<div class="hotel_tours" id="hotel_'+hid+'_tours"></div>');
    $.each(data.tours, function(i,val){
        var tourDiv = $('<div class="airTourVar" data-req="'+val.reqid+'" data-hid="'+hid+'" data-tid="'+val.tid+'"></div>'),
        tourInner = $('<div class="airTourInner"></div>'),
        tourLine = $('<div class="airTourLine">'+
                        '<div class="tourStartDate">'+val.date_from+'</div>'+
                        '<div class="tourEndDate">'+val.date_to+'</div>'+
                    '</div>'),
        tourNights= $('<div class="airTourLine airNights">'+
                        'Ночей: <span>'+val.nights+'</span>'+
                    '</div>'),            
        tourMR= $('<div class="airTourLine airMR">'+
                        '<span class="airLabel">Питание:</span> '+val.meal_name+' ' +
                        '<span class="airLabel">, Размещение:</span> '+ val.room +			
                    '</div>'),               
          
        tourPrice = $('<div class="airTourPrice">'+
                        '<span>'+val.price+'</span>  руб.'+
                    '</div>');
        tourInner.append(tourLine).append(tourNights).append(tourMR);
        tourDiv.append( tourInner).append(tourPrice);
        div.append(tourDiv);
    });
    $("#hotel_"+hid+"_tours").remove();
    $("#hotel_"+hid+"_info").remove();
    $("#hotel_"+hid).after(div);
}

function successHotelInfo( data )
{
    var  hid = data.hotel,
    div = $('<div class="hotel_info" id="hotel_'+hid+'_info"></div>'),
    hotelData = $('<div class="hotelData"></div>'),
    hotelPics = $('<div class="hotelPics"></div>');
    if(data.info.build!="")
        hotelData.append('<div class="hotelDataLine">'+
                            '<span>Дата постройки:</span> '+data.info.build+" г."+
                        '</div>');
    if(data.info.repair!="")
        hotelData.append('<div class="hotelDataLine">'+
                            '<span>Реставрирован:</span> '+data.info.repair+" г."+
                        '</div>');
    if(data.info.territory!="")
        hotelData.append('<div class="hotelDataLine">'+
                            '<span>Территория отеля:</span> '+data.info.territory+
                        '</div>');
    if(data.info.inroom!="")
        hotelData.append('<div class="hotelDataLine">'+
                            '<span>В номере:</span> '+data.info.inroom+
                        '</div>'); 
    $.each(data.info.photo,function(i,pic){
        hotelPics.append('<a href="'+pic.big+'" data-fancybox="hotel'+hid+'">'+
                            '<img src="'+pic.small+'">'+
                        '</a>');
    });                    
                        
    div.append(hotelPics).append(hotelData);
    $("#hotel_"+hid+"_info").remove();
    $("#hotel_"+hid+"_tours").remove();
    $("#hotel_"+hid).after(div);
}

function successTourInfo( data )
{
    var div = $('<div class="tourHotelWrap"></div>'),
	hotelWrap = $('<div class="tourHotelWrapInner loading"></div>'),
	hotelBlock = $('<div class="hotelBlock"></div>'),
	hotelName = $('<div class="hotelName">'+data.hotel_info.name+'</div>'),
	hotelStars = $('<div class="hotelStars"></div>'),
	hotelCountry = $('<div class="hotelCountry">'+data.hotel_info.country+'</div>'),
	hotelDates = $('<div class="hotelTourDates"></div>'),
	hotelTourInfo = $('<div class="hotelTourInfo"></div>'),
	hotelPrice = $('<div class="hotelPriceInfo"><div class="hotelTourPrice"><span>'+data.tour_info.price+'</span> руб.</div><div class="hotelNextStep loading">Продолжить</div></div>'),
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
	/***************************************
    if(data.tour_info.regular==0)
    {    
        hotelOrderType = $('<div class="hotelOrderType"><div class="orderBuyType active">Купить онлайн</div><div class="orderSendType">Отправить запрос</div></div>');
        hotelOrder.append(hotelOrderType);
    
        var formOnline = $('<form method="post" name="orderOnline"></form>');
        formOnline.append('<div class="orderSubTtl">Возрослые:</div>');
        j=1;
        for (var i=1;i<=data.tour_info.adults;i++)
        {
            pasportForm (formOnline,j,false);
            j++;
        } 
        if (data.tour_info.child>0)
        {
            formOnline.append('<div class="orderSubTtl">Дети:</div>');
            for (var i=1;i<=data.tour_info.child;i++)
            {
                pasportForm (formOnline,j,true);
                j++;
            } 
        }
        var formOnlineDop = $('<div class="orderOnlineDop"></div>');
		formOnlineDop.append('<div class="turistBlockSubTitile">Заказчик</div>');
        formOnlineDop.append('<div class="orderWide"><div class="orderInner"><div class="orderInputText">Ваше ФИО (польностью)</div><div class="orderInputWrap"><input id="userName" type="text" value=""></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш email</div> <div class="orderInputWrap"><input type="text" id="userEmail" name="userEmail" value="" /></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш телефон</div><div class="orderInputWrap"><input id="userPhone" type="text" name="userPhone" value=""></div></div></div>');
        formOnlineDop.append('<div class="clear"></div><div class="orderInfoBlock">Укажите один из способов связи c Вами - email или телефон</div><div class="clear"></div>');
        formOnlineDop.append('<div class="orderBot"><div class="orderInputText">Дополнительная информация</div><div class="orderTextWrap"><textarea id="userComment" name="userComment" ></textarea></div></div>');
        formOnlineDop.append('<input type="hidden" name="ordReq" id="ordReq" value="'+data.tour_info.reqid+'" />');
        formOnlineDop.append('<input type="hidden" name="ordHid" id="ordHid" value="'+data.tour_info.hid+'" />');
        formOnlineDop.append('<input type="hidden" name="ordTid" id="ordTid" value="'+data.tour_info.tid+'" />');
        formOnlineDop.append('<input type="submit" class="orderSend" value="Отправить заявку">');
        
        formOnline.append(formOnlineDop); 
        hotelOrderOnline.addClass('active');
        hotelOrderOnline.append(formOnline);
        hotelOrder.append(hotelOrderOnline);
       
    }
    else
    {
       
        hotelOrderType = $('<div class="hotelOrderType"><div class="orderSendType active">Отправить запрос</div></div>');
        hotelOrder.append(hotelOrderType);
        hotelOrderOffice.addClass('active');
    }    
	/**************************************
	var form = $('<form method="post" name="orderOffice"></form>');
    form.append('<div class="orderLeft"><div class="orderInner"><div class="orderInputText">Ваше имя</div><div class="orderInputWrap"><input id="userName" type="text" value=""></div></div></div>');
    form.append('<div class="orderMid"><div class="orderInner"><div class="orderInputText">Ваш email</div> <div class="orderInputWrap"><input type="text" id="userEmail" name="userEmail" value="" /></div></div></div>');
    form.append('<div class="orderRight"><div class="orderInner"><div class="orderInputText">Ваш телефон</div><div class="orderInputWrap"><input id="userPhone" type="text" name="userPhone" value=""></div></div></div>');
    form.append('<div class="clear"></div><div class="orderInfoBlock">Укажите один из способов связи c Вами - email или телефон</div><div class="clear"></div>');
    form.append('<div class="orderBot"><div class="orderInputText">Дополнительная информация</div><div class="orderTextWrap"><textarea id="userComment" name="userComment" ></textarea></div></div>');
    form.append('<input type="hidden" name="ordReq" id="ordReq" value="'+data.tour_info.reqid+'" />');
    form.append('<input type="hidden" name="ordHid" id="ordHid" value="'+data.tour_info.hid+'" />');
    form.append('<input type="hidden" name="ordTid" id="ordTid" value="'+data.tour_info.tid+'" />');
    form.append('<input type="submit" class="orderSend" value="Отправить заявку">');
	
	hotelOrderOffice.append('<div class="orderFormTitle">Запрос на информацию по туру</div>')
					.append(form);
					
	hotelOrder.append(hotelOrderOffice);
	/**************************************
	hotelWrap.append(hotelBlock)
			 .append(hotelOrder);

	div.append(hotelWrap);

	$.fancybox.open( [div],{"wrapCSS":"turZakazWrap","touch":false} );
	
	$('.hotelPhoto').slick({
        infinite: false,
        speed: 300,
        centerMode: false,
        prevArrow: '<div class="slick-prev"><div class="bIcon"></div></div>',
        nextArrow: '<div class="slick-next"><div class="bIcon"></div></div>'
    });
	
	/**************************************
	
	
	data={req:data.tour_info.reqid,hid:data.tour_info.hid,tid:data.tour_info.tid,act_tour_info:"y"};
	var url = $("form[name=searchForm]").attr('action');
	$.ajax( url, {
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
	
	requestDetail = false;
	
}	



function successHotel( data ) {
    if (data.hotelList != null)
    { 
        var  div = '';
      
        $.each(data.hotelList,function(index,val) {
            div += '<div class="sCbList" id="hotel'+val.id+'W">';
            div += '<label><input id="hotel'+val.id+'" type="checkbox" name="hotel" value="'+val.id+'">';
            div += '<span>'+val.name+'</span>';
            div += '</label></div>';  
        });
         $("#hotelBlock").html(div);
    }
}

function prepareData( )
{
    var city = $("#from").val();
    var country = $("#country").val();
    var date_from = $("#date_from").val();
    var date_till = $("#date_till").val();
    var nights_from = $("#count_days_from").val();
    var nights_till = $("#count_days_till").val();
    var price_from = $("#price_from").val();
    var price_till = $("#price_till").val();
    var adults = $("#count_people").val();
    var children = $("#count_child").val();
    var children_ages = [];
    if (children > 0)
    {
        for (var i=1; i<=children; i++)
            children_ages[i-1] =$("#child_year"+i).val();
    }    
    var region =[];
    $("input[name=region]:checked").each(function(index){
        region[index]= $(this).val();
    })
    var hotel =[];
    $("input[name=hotel]:checked").each(function(index){
        if ($(this).val()!="all" && $(this).val()!=0)
            hotel[index]= $(this).val();
    })
    var stars = [];
    $("input[name=stars]:checked").each(function(index){
            stars[index]= $(this).val();
    })
    var food = [];
    $("input[name=food]:checked").each(function(index){
            food[index]= $(this).val();
    })
    
    data={};
    if (city>0 && country>0)
    {
        data.city=city;
        data.country= country;
        data.search_create=1;
        data.datefrom = date_from;
        data.datetill = date_till;
        data.nightsmin = nights_from;
        data.nightsmax = nights_till;
        data.resort = region;
        data.hotel = hotel;
        data.adults = adults;
        data.children = children;
        data.children_ages = children_ages;
        if (price_from !="")
            data.price_from = price_from;
        if (price_till !="")
            data.price_till = price_till;
        data.stars = stars;
        data.food = food;
    }    
    return data
    
}

/*function successTourUpdate(data)
{
    var html = $('.tourInfoWrap').html();
    var div = $(html);
    //div.find('.tourInfoName').text(data.from+' - '+data.country+', '+data.resort);
    div.find('.nights span').text(data.nights);
    div.find('.tdates span').text('c '+data.chekin+' по '+data.chekout);
    div.find('.tPriceVal').text(data.price);
    div.find('.tourRoom span').text(data.room);
    div.find('.tourFood span').text(data.food);
    div.find('.tourFto span').text(data.tickets_to).addClass(data.tickets_to_class);
    div.find('.tourFbck span').text(data.tickets_back).addClass(data.tickets_back_class);
    div.find('.tourHstop span').text(data.hotel_not_stop).addClass(data.hotel_not_stop_class);
    //div.find('.tName').text(data.hotel_name);
    //div.find('.rateVal').text(data.hotel_rate);
   /* if (data.hotel_rate!='?') {
        data.hotel_rate = data.hotel_rate*10;
        div.find('.tourHotelRateMetInner').css({'width': data.hotel_rate+'%'});
    }
    if (data.hotel_star>0)
    {
        var stars="";
        for($i=1;$i<=data.hotel_star;$i++) 
            stars += '<div class="gStar"></div>';  
        div.find('.tourHotelInfoRight .gStars').html(stars);
    }*
    if (data.hotel_img!="")
    {    
        var picwrap = div.find('.tourHotelInfoLeft');
        if (data.hotel_img.length>1)
        {    
            $.each(data.hotel_img,function(index,val) {
                var celm = $('<div class="tourInfoCaruselItem"></div>');
                var link = $('<a href="'+data.hotel_img_big[index]+'" class="fbox" rel="carusel"></a>');
                link.append(val);
                celm.append(link);
                picwrap.append(celm);
            });
        }
        else
        {
            var link = $('<a href="'+data.hotel_img_big[0]+'" class="fbox" rel="carusel"></a>');
            link.append(data.hotel_img[0]);
            picwrap.append(link);
        }    
        picwrap.addClass('tourInfoCarusel');
    }
    else
    {    
        div.find('.tourHotelInfoLeft').html("<img src='/upload/no_img.png' />").addClass('no-pic');
    }
    
    if (data.hotel_url!="")
    {
        div.find('.tourHotelLink').attr('href',data.hotel_url);
    }
    else
    {
        div.find('.tourHotelLink').remove();
    }
    
    if (data.adults!="")
    {    
        div.find('.tourAdults').text(data.adults);
        if (data.kids!="" && data.kids>0)
        {
            div.find('.tourAdults').after(', детей: <span>'+data.kids+'</span>');
        }    
    }
    if (data.visa_price!="" || data.oil_price!="")
    {    
        if (data.visa_price!="")
            div.find('.tourInfoDop').append('<div class="tourInfoDopLine"><span class="lName">Визовые сборы:</span> <span class="lVal">0 - '+data.visa_price+' '+data.visa_cur+' за человека</span></div>');
        if (data.oil_price!="")
            div.find('.tourInfoDop').append('<div class="tourInfoDopLine"><span class="lName">Топливные сборы:</span> <span class="lVal">'+data.oil_price+' '+data.oil_cur+' за человека</span></div>');
    }
    else
    {
        div.find('.tourInfoDop').hide();
    }    
   
    div.find('.tourInfoOffline').attr('data-req',data.req).attr('data-sor',data.sor).attr('data-off',data.off);
    
    $('.tourInfoUpdate').html(div).prev().addClass('activeTour').find('.airMainPrices').addClass('hidPrice');
    
     
    $('.tourInfoUpdate').find('.tourInfoCarusel').slick({
        infinite: false,
        speed: 300,
        centerMode: false,
        prevArrow: '<div class="slick-prev"><div class="bIcon"></div></div>',
        nextArrow: '<div class="slick-next"><div class="bIcon"></div></div>'
    });
    
    /*$('.tourInfoCarusel').slick('setPosition'); *

}


function successOrder (data)
{
	$.fancybox.close();
    if (data.send==1)
        var mess = $('<div id="claimOkMain">Спасибо за Вашу заявку!<br /><br>В ближайшее время с Вами свяжутся наши менеджеры</div>');
    else
        var mess = $('<div id="claimOkMain">Произошла ошибка!<br /><br>Повторите отправку заявки</div>');
    $.fancybox.open( [mess],{"wrapCSS":"turZakazWrap"} );
}
function pasportForm (form,j,isChild)
{
    var block = $('<div class="turistBlock"></div>');
    var line=$('<div class="onLine"></div>');
    if(isChild)
        line.append('<div class="turistBlockSubTitile">Ребенок '+j+'</div>');
    else
        line.append('<div class="turistBlockSubTitile">Взрослый '+j+'</div>');
    
    line.append('<div class="onName"><div class="orderInputText">Имя (латиницей)</div><div class="orderInputWrap"><input type="text" id="name'+j+'" name="name'+j+'" value="" placeholder="Ivan"/></div></div>');
    line.append('<div class="onSname"><div class="orderInputText">Фамилия (латиницей)</div> <div class="orderInputWrap"><input type="text" id="sname'+j+'" name="sname'+j+'" value="" placeholder="Ivanov"/></div></div>');
    line.append('<div class="onBdate"><div class="orderInputText">Дата рождения</div><div class="orderInputBdWrap" id="bdate'+j+'"><div class="orderBdateD"><input type="text" id="bdated'+j+'" name="bdated'+j+'" value="" placeholder="ДД"/></div>  <div class="orderBdateM"><input type="text" id="bdatem'+j+'" name="bdatem'+j+'" value="" placeholder="ММ"/></div><div class="orderBdateY"><input type="text" id="bdatey'+j+'" name="bdatey'+j+'" value="" placeholder="ГГГГ"/></div></div></div>');
    line.append('<div class="onNat"><div class="orderInputText">Гражд.</div> <div class="orderInputWrap"><input type="text" id="nat'+j+'" name="nat'+j+'" value="RU" /> </div></div>');
    line.append('<div class="onGend"><div class="orderInputText">Пол</div><div class="searchSelect sSelectWrap"><span class="searchSelectText sSelectSpan"> Ж </span><select id="gend'+j+'" class="sSelect"><option selected="selected" value="F">Ж</option><option value="M">М</option></select></div></div>');
    line.append('<div class="clear"></div>');
    block.append(line);
    line=$('<div class="onLine"></div>');
    line.append('<div class="onName"><div class="orderInputText">Серия загранпаспорта</div><div class="orderInputWrap"><input type="text" id="pasSer'+j+'" name="pasSer'+j+'" value="" placeholder="01"/></div></div>');
    line.append('<div class="onSname"><div class="orderInputText">Номер загранпаспорта</div><div class="orderInputWrap"><input type="text" id="pasNom'+j+'" name="pasNom'+j+'" value="" placeholder="23456789"/></div></div>');
    line.append('<div class="onBdate"><div class="orderInputText">Дата выдачи</div><div class="orderInputBdWrap" id="pasFrom'+j+'"><div class="orderBdateD"><input type="text" id="pasFromd'+j+'" name="pasFromd'+j+'" value="" placeholder="ДД"/> </div><div class="orderBdateM"><input type="text" id="pasFromm'+j+'" name="pasFromm'+j+'" value="" placeholder="ММ"/></div><div class="orderBdateY"><input type="text" id="pasFromy'+j+'" name="pasFromy'+j+'" value="" placeholder="ГГГГ"/></div></div></div>');
    line.append('<div class="onNat onTill"><div class="orderInputText">Годен до</div><div class="orderInputBdWrap" id="pasTill'+j+'"><div class="orderBdateD"><input type="text" id="pasTilld'+j+'" name="pasTilld'+j+'" value="" placeholder="ДД"/> </div><div class="orderBdateM"><input type="text" id="pasTillm'+j+'" name="pasTillm'+j+'" value="" placeholder="ММ"/></div><div class="orderBdateY"><input type="text" id="pasTilly'+j+'" name="pasTilly'+j+'" value="" placeholder="ГГГГ"/></div></div></div>');
    line.append('<div class="clear"></div>');
    block.append(line);
    block.append('<div class="orderBot"><div class="orderInputText">Кем выдан</div><div class="orderOnlineTextWrap"><textarea id="pasOut'+j+'" name="pasOut'+j+'"  placeholder="УФМС г. Москва"></textarea></div></div>');
    form.append(block);
}    
function checkDate (name,indx)
{
    res = {};
    var er = false;
    var year = new Date().getFullYear();
    var bd = parseInt($("#"+name+"d"+indx).val());
    var bm = parseInt($("#"+name+"m"+indx).val());
    var by = parseInt($("#"+name+"y"+indx).val());
    
    if (bd<1 || bd>31 || isNaN(bd)) er = true;
    if (bm<1 || bm>12 || isNaN(bm)) er = true;
    if (by<1920 || by>year|| isNaN(by)) er = true;
    if (!er)
    {    
        //bm = bm-1;
        var dt = new Date(by,bm,bd);
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
*/
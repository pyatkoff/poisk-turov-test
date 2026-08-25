
var tid = 0;
reqid = 0,
interval = 3000,
percOut = 0,
tours=0,
requestDetail = true,
hotelTimer = 0,
map = [],
clientID = "",
yclid = "",
utmSource = "",
utmMedium = "",
utmCampaign = "",
utmContent = "",
utmTerm = "",
paramsChanged = false,
dpLoaded = 0;


$(document).ready(function() {
	
	ym(69158110, 'getClientID', function(cID) {
		clientID = cID;
		$('input[name=yaclient]').val(clientID);
	});
	
	yclid       = (Cookies.get('yclid')!==undefined)       ? Cookies.get('yclid') : "" ;
	utmSource   = (Cookies.get('utmSource')!==undefined)   ? Cookies.get('utmSource') : "" ;
	utmMedium   = (Cookies.get('utmMedium')!==undefined)   ? Cookies.get('utmMedium') : "" ;
	utmCampaign = (Cookies.get('utmCampaign')!==undefined) ? Cookies.get('utmCampaign') : "" ;
	utmContent  = (Cookies.get('utmContent')!==undefined)  ? Cookies.get('utmContent') : "" ;
	utmTerm     = (Cookies.get('utmTerm')!==undefined)     ? Cookies.get('utmTerm') : "" ;	

	$('input[name=yaclid]').val(yclid);
    $('input[name=yautmsource]').val(utmSource);
    $('input[name=yautmmedium]').val(utmMedium);
    $('input[name=yautmcampaign]').val(utmCampaign);
    $('input[name=yautmcontent]').val(utmContent);
    $('input[name=yautmterm]').val(utmTerm);
	
	var monthsShort = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'],
    dateFrom 		= new Date( $('#date_from_till').attr("data-datefrom")),
	dateTo      	= new Date( $('#date_from_till').attr("data-dateto"));

	
	
	var datepicker = $('#date_from_till').datepicker({
		minDate: new Date(),
		range: true,
		multipleDatesSeparator: " - ",
        position: ($('body').width()<700) ? "bottom right" : "bottom left",
		onSelect: function(fd, d, picker) {
			var dateStr = "";
			if (d.length==1)
			{
				var mon = d[0].getMonth(),
				day = d[0].getDate(),
				dateF = day+" "+monthsShort[mon];
				dateStr = dateF+" - "+dateF;
				$('input[name=dateFrom]').val(d[0].yyyymmdd());
				$('input[name=dateTo]').val(d[0].yyyymmdd());
			}
			else
			{
				var monF = d[0].getMonth(),
				dayF = d[0].getDate(),
				monT = d[1].getMonth(),
				dayT = d[1].getDate(),
				dateF = dayF+" "+monthsShort[monF],
				dateT = dayT+" "+monthsShort[monT];
				dateStr = dateF+" - "+dateT;
				picker.hide();
				$('input[name=dateFrom]').val(d[0].yyyymmdd());
				$('input[name=dateTo]').val(d[1].yyyymmdd());
			}
			$('#date_from_till_block').html( dateStr);
			if(dpLoaded>2)
			{
				paramsChanged = true;
			}
			dpLoaded++;
		}	
	}).data('datepicker');
	
	datepicker.selectDate([dateFrom ,dateTo]);
	
	$("#date_from_till_block").click(function(){
		datepicker.show();
	});

	var datepickerHelp = $('#help_dates_datepicker').datepicker({
		minDate: new Date(),
		range: true,
		multipleDatesSeparator: " - ",
        position: ($('body').width()<700) ? "bottom right" : "bottom left",
		onSelect: function(fd, d, picker) {
			var dateStr = "";
			if (d.length==1)
			{
				var mon = d[0].getMonth(),
				day = d[0].getDate(),
				dateF = day+" "+monthsShort[mon];
				dateStr = dateF+" - "+dateF;
				$('input[name=help_date_from]').val(d[0].yyyymmdd());
				$('input[name=help_date_till]').val(d[0].yyyymmdd());
			}
			else
			{
				var monF = d[0].getMonth(),
				dayF = d[0].getDate(),
				monT = d[1].getMonth(),
				dayT = d[1].getDate(),
				dateF = dayF+" "+monthsShort[monF],
				dateT = dayT+" "+monthsShort[monT];
				dateStr = dateF+" - "+dateT;
				picker.hide();
				$('input[name=help_date_from]').val(d[0].yyyymmdd());
				$('input[name=help_date_till]').val(d[1].yyyymmdd());
			}
			$('#help_from_till_block').html( dateStr);
		}	
	}).data('datepicker');

	$("#help_from_till_block").click(function(){
		datepickerHelp.show();
	});

	
	$( "#date_till" ).click(function(){
		var val = $( "#date_till" ).val(),
		curDate = new Date(),
		d = curDate.getDate(),
		m =  curDate.getMonth(),
		y = curDate.getFullYear();
		m += 1;
			
		
		
		/*BXMobileApp.UI.DatePicker.setParams({                
			type: "date",
			start_date: val,
			format: "dd.MM.yyyy",
			min_date: d + "." + m + "." + y,
		    callback: function (d)
			{
				var pattern = /(\d{2})\.(\d{2})\.(\d{4})/;
				var dtf = new Date($("#date_from").val().replace(pattern,'$3-$2-$1'));
				var dtt = new Date(d.replace(pattern,'$3-$2-$1'));
				var mPD = 1000 * 60 * 60 * 24;
				var days = (dtt - dtf)/ mPD;
				
				if (days>45)
				{
					var newdate = new Date(dtt.setDate(dtt.getDate()-6)),
					day = newdate.getDate(),
					year = newdate.getFullYear(),
					mon = newdate.getMonth()+1;
					if (day<10)
						day = '0' + day;
					if (mon<10)
						mon = '0' + mon;
					var dt =  day+'.'+mon+'.'+year;
					$( "#date_from" ).val(dt);
				} 
				
				$( "#date_till" ).val(d);  
			}

		});
		BXMobileApp.UI.DatePicker.show();*/
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
     
    $("#hotel_get").click( function(e) {
        if ($(this).is(':checked') && $("#hotel_all").is(':checked'))
        {    
            $("#hotelWrap .searchLabel").removeClass("bigMargin");
            $("#hotelBlock").show().addClass('loaded');
			$("#hotelByName").show();
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
      
    });
    
	$('input[name=hotelName]').keyup(function(){
		if(hotelTimer>0)
			clearTimeout(hotelTimer);
		hotelTimer= setTimeout(function() {
			var region =[],
			cn = $("#country").val(),
			name = $.trim($('input[name=hotelName]').val()),
			data = { mode:"hotel" , country: cn, name: name};
			
            $("input[name=region]:checked").each(function(index){
                region[index]= $(this).val();
            });
            
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
			
			
		}, 400);
		
		
	});
    
    $("#hotelBlock").scroll(function(){
        let scrollHeight  = $(this).scrollTop()/$(this).prop("scrollHeight");
		if (!$("#hotelBlock").hasClass('fullLoaded') && scrollHeight>=0.7)
        {
            let page = parseInt($("#hotelBlock").attr("data-page"));
            page++;
            if (hotelList[page]!== undefined)
            {
                setHotelsData( hotelList[page],true);
                $("#hotelBlock").attr("data-page", page);
            }    
            else
                $("#hotelBlock").addClass('fullLoaded');    
        }     
	});
	
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
		paramsChanged = true;
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
        
    $('body').on('change','.sSelect', function() {
        var vl = $(this).val();
        var txt = ($(this).attr('id')=="from") ?  $(this).children("option[value='"+vl+"']").attr("data-name") :  $(this).children("option[value='"+vl+"']").text();
       
        $(this).prev('span').html(txt);
        
    });    
    
    $('#country').change( function() {
		paramsChanged = true;
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
        
		var data = { mode:"hotel" , country: cn };
		$.ajax( $("form[name=searchForm]").attr('action'), {
			cache: false,
			data: data,
			dataType: "json",
			error: errorHandler,
			success: successHotel,
			type: "POST"
		});
        
    }); 
    
    $("body").on( "change", '.sCbList input', function() {
		
        if ($(this).is(":checked"))
        {
			console.log('click1 sCbList');
            $(this).parent().addClass('activelbl');
            if ($(this).attr("name")=="region")
            {
                //if ($("#hotelBlock").hasClass('loaded'))
               // {    
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
                //}
                
                $('#reg_all').prop('checked', false).prop('disabled', false);
            }  
        }  
        else    
        {
			console.log('click2 sCbList');
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
                //if ($("#hotelBlock").hasClass('loaded')) {
                    $.ajax( $("form[name=searchForm]").attr('action'), {
                        cache: false,
                        data: data,
                        dataType: "json",
                        error: errorHandler,
                        success: successHotel,
                        type: "POST"
                    });
               // }
               
            }  
        }    
		paramsChanged = true;
		return false;
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
				if(parseInt($(this).attr('data-star'))<val)
					cb.prop('checked',false);
				else
					cb.prop('checked',true);
				
			});
		}
		else
		{
			$.each(sib,function(i,v){
				var cb = $(this).find('input');
			
				if(parseInt($(this).attr('data-star'))<val)
					cb.prop('checked',false);
			});
		}
		
		var stars=[];
		$('input[name=stars]:checked').each(function(){
			stars.push($(this).val());
		});
		var cn = $("#country").val(),
		data = { mode:"hotel" , country: cn, resort:region, stars:stars},
		region =[];
		$("input[name=region]:checked").each(function(index){
			region[index]= $(this).val();
		});
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
		
		
	});
	
	$('input[name=food]').change( function() {
		if ($(this).is(":checked"))
		{
			$("#foodWrap").find('input.sCbSearch:checked').not("#"+$(this).attr('id')).prop('checked',false);
		}
	});
	
	
    /*$("#content").on( "click", '.updateInfo', function() {
        $('.airMainPrices.hidPrice').removeClass('hidPrice');
        if ($(this).hasClass('activeBut'))
        {
            $(this).removeClass('activeBut').text('Подробнее');
            $('.tourInfoUpdate').hide(500);
        }
        else
        {   
            $('.updateInfo.activeBut').removeClass('activeBut').text('Подробнее');
            $('.airTour activeTour').removeClass('activeTour');
            $(this).addClass('activeBut').text('Свернуть');
            var r = $(this).attr('data-req');
            var s = $(this).attr('data-sor');
            var o = $(this).attr('data-off');
            $('.tourInfoUpdate').remove();
           
            $('.activeTour').removeClass('activeTour');
            var div = $('<div class="tourInfoUpdate"></div>');
            var html = $('#waitBlock').html();
            $(this).parent().parent().parent().after(div);
            //$(this).parent().parent().parent().append(div);
            $('.tourInfoUpdate').html(html).show(500);
            $("html,body").animate({ scrollTop: $(this).parent().parent().parent().offset().top }, "slow");
            var data = { tur_update: 1 , reqid: r, sorid:s, offid:o};
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
                cache: false,
                data: data,
                dataType: "json",
                error: errorHandler,
                success: successTourUpdate,
                type: "POST"
            });
        }
    });   */
        
    $("form[name=searchForm]").submit(
		function(e)
		{
            e.preventDefault();

			if (!$("#search_start").hasClass('disabled'))
			{	
				$("#searchHelpFormWrap").show();
				$("#search_start").addClass('disabled');
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
				percOut = 0;
				url = $(this).attr('action');
				data = prepareData();
				/*console.log(data);*/
				
				//gtag('event', 'SEND_SEARCH_FORM');
				
				$.ajax( url, {
					cache: false,
					data: data,
					dataType: "json",
					error: errorHandler,
					success: successCreate,
					type: "POST"
				});
			}
        }
    );  

    $("#showMoreResult").click(function(){
        if (reqid>0) 
            getCurrentResult(1, true);    
    }); 
    
	
	$("body").on('click',".airMainPrice",function(){
		$(this).parents('.airTour').find('.hotelTours').click();
	});	
	
    $("body").on('click',".hotelTours",function(){
        if (reqid>0) 
        {     
            var hid =$(this).attr('data-id');
			if ($('#hotel_'+hid+'_tours').length>0)
			{
				$('#hotel_'+hid+'_tours').remove();
				$("html,body").animate({ scrollTop: $(this).parent().parent().offset().top-20  }, "slow");
			}	
			else
			{	
				data={req:reqid, hid:hid, get_hotel_tours:'y' };
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
        }   
    });
    
    $("body").on('click',".hotelInfo",function(){
        if (reqid>0) 
        {     
            var hid =$(this).attr('data-id');
			if ($('#hotel_'+hid+'_info').length>0)
			{
				$('#hotel_'+hid+'_info').remove();
				$("html,body").animate({ scrollTop: $(this).parent().parent().offset().top-20  }, "slow");
			}
			else
			{
				data={req:reqid, hid:hid,get_hotel_info:'y'};
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
        }   
    });
    
	$("body").on('click',".hotelMap",function(){
		
        var hid= $(this).attr('data-id'),
		coordsLat = $(this).attr('data-lat'),
		coordsLon = $(this).attr('data-lon');
		$("#hotel_"+hid+"_tours").remove();
		$("#hotel_"+hid+"_info").remove();
		$("#hotel_"+hid+"_map").remove();
		$("#hotel_"+hid).after("<div id='hotel_"+hid+"_map' class='hotelMapBlock' ><div id='hotel_"+hid+"_map_wrapper' class='hotelMapInner'></div><div class='hotelMapRemove'>Свернуть</div></div>");
		
		
		var myLatlng = new google.maps.LatLng(coordsLat,coordsLon),
		myOptions = {
			zoom: 13,
			center: myLatlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
		map[hid] = new google.maps.Map(document.getElementById("hotel_"+hid+"_map_wrapper"), myOptions);
		
	
		map[hid].setCenter(myLatlng);                    
		var marker = new google.maps.Marker({
			position: myLatlng,
			map: map[hid],
			title:"Hotel" 
		});

		google.maps.event.addDomListener(window, 'resize', function() {
			map[hid].setCenter(myLatlng);
		}); 
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
        $(this).find('#userName').parent().removeClass('errorBord');
        $(this).find('#userPhone').parent().removeClass('errorBord');
        $(this).find('#userEmail').parent().removeClass('errorBord');
        var uname  = $(this).find("#userName").val();
        var uemail = $(this).find("#userEmail").val();
        var uphone = $(this).find("#userPhone").val();
        var utext  = $(this).find("#userComment").val();
        var oreq   = $(this).find("#ordReq").val();
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
				req:oreq, 
				hid: ohid, 
				tid : otid,
				yaclient:yaclient,
				yaclid:yaclid,
				yautmsource:yautmsource,
				yautmmedium:yautmmedium,
				yautmcampaign:yautmcampaign,
				yautmcontent:yautmcontent,
				yautmterm:yautmterm
			};
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
		uPassSerNum  = $("#userPassSerNum").val(),
		uPassD       = $("#userPassD").val(),
		uPassM       = $("#userPassM").val(),
		uPassY       = $("#userPassY").val(),
		uPassWho     = $("#userPassWho").val(),
		oreq   = $("#ordReq").val(),
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
				req:oreq, 
				hid: ohid, 
				tid: otid, 
				tourists:tourists ,
				yaclient:yaclient,
				yaclid:yaclid,
				yautmsource:yautmsource,
				yautmmedium:yautmmedium,
				yautmcampaign:yautmcampaign,
				yautmcontent:yautmcontent,
				yautmterm:yautmterm	
			};
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
	
	$('body').on('click','.removeTours, .removeHotelInfo, .hotelMapRemove',function(){
		var prev = $(this).parent().prev();
		$(this).parent().remove();
		$("html,body").animate({ scrollTop: prev.offset().top-20  }, "slow");
	});
	
	
	
	if($("form[name=searchForm]").attr('data-start')=='y')
		$("form[name=searchForm]").submit();
	
	
	$('body').on('click','#nights-from_till_block',function(){
		$("#nightPickBlock").addClass('active');
        $("#peoplePickBlock").removeClass('active');
	});
	
	$('body').on('click','.pickCell',function(){
		var parent = $(this).parents('.nightPickBlock');
		
		if (parent.hasClass('pickedOne'))
		{
			if (parent.attr('id') == "nightPickBlockHelp")
				recalcNightBlockHelp();
			else	
				recalcNightBlock();
		}
		else
		{
			parent.find('.activeLastPickCell').removeClass('activeLastPickCell');
			parent.find('.activePickCell').removeClass('activePickCell');
			parent.find('.hoverPickCell').removeClass('hoverPickCell');
			parent.find(this).addClass('activePickCell');
			parent.addClass('pickedOne');
		}
	});
	
	$('body').on('mouseenter','.pickCell',function(){
		var parent = $(this).parents('.nightPickBlock'),
		cellName = (parent.attr('id')=="nightPickBlockHelp") ? "#pickCellHelp" : "#pickCell" ;
		
		if (parent.find('.activeLastPickCell').length==0)
		{
			parent.find('.hoverPickCell').removeClass('hoverPickCell');
			parent.find('.activePickHoverdCell').removeClass('activePickHoverdCell');
		
			var val = parseInt($(this).text()),
			activeVal =  parseInt(parent.find('.activePickCell').text());
			if (activeVal>val)
			{
				
				var start = activeVal-val;
				if (start>1)
				{
					if (start>14)
						val = val + (start - 14);
						
					for (var i = (activeVal-1); i >= val; i--) {
					
						if(i==val)
							$(cellName+i).addClass('activePickHoverdCell');
						else	
							$(cellName+i).addClass('hoverPickCell');
						
					}	
				}
				else
					$(this).addClass('activePickHoverdCell');
			}
			else if (activeVal<val)
			{
				
				var start = val - activeVal;
				
				if (start>1)
				{
					if (start>14)
						val = val - (start - 14);
						
					for (var i = (activeVal+1); i <= val; i++) {
						$(cellName+i).addClass('hoverPickCell');
						if(i==val)
							$(cellName+i).addClass('activePickHoverdCell');
					}	
				}
				else
					$(this).addClass('activePickHoverdCell');
			}
			
		}
	});
	
	$('body').click(function(e){
		if ($("#nightPickBlock").hasClass('active'))
		{
			if(
				e.target.className.indexOf('nightPickBlock')<0 &&
				e.target.className.indexOf('nights-from_till_block')<0  &&
				e.target.className.indexOf('nightPickBlockTitle')<0 && 
				e.target.className.indexOf('pickTable')<0 	&& 
				e.target.className.indexOf('pickRow')<0		&& 
				e.target.className.indexOf('pickCell')<0
			)
			{
				recalcNightBlock();
			}	
		}

		if ($("#nightPickBlockHelp").hasClass('active'))
		{
			if(
				e.target.className.indexOf('help_nights_block')<0 &&
				e.target.className.indexOf('nightPickBlock')<0 &&
				e.target.className.indexOf('nights-from_till_block')<0  &&
				e.target.className.indexOf('nightPickBlockTitle')<0 && 
				e.target.className.indexOf('pickTable')<0 	&& 
				e.target.className.indexOf('pickRow')<0		&& 
				e.target.className.indexOf('pickCell')<0
			)
			{
				recalcNightBlockHelp();
			}	
		}	
	});
		
	$('body').on('click','.childAdd',function(){
		var isHelp = ($(this).parents("#peoplePickBlockHelp").length>0) ? true : false,
		
		html =  '<div class="childPickBlockItem">'+
						'<div class="peoplePickBlockTitle">Возраст ребенка:</div>'+
						'<div class="searchSelect">'+
							'<div class="childMinus">-</div>'+
							'<span class="searchSelectText">0</span>'+
							'<select name="child_year_pick" id="child_year_pick" class="sSelect">';
							
			for(i=0;i<=15;i++) {
				var sel = (i==0) ? "selected" : "";
				html += '<option value="'+i+'" '+sel+'>'+i+'</option>';
			}
			html  +=	  '</select>'+
						'</div>'+
					'</div>';
		$(this).prev().append(html);	
		if ($(this).prev().find('.childPickBlockItem').length==3)
			$(this).hide();
		recalcPeopleBlock(isHelp);	
	});
		
	$('body').on('change','select[name=child_year_pick]', function() {
        var isHelp = ($(this).parents("#peoplePickBlockHelp").length>0) ? true : false,
		vl = $(this).val();
        $(this).prev('span').html(vl);
		recalcPeopleBlock(isHelp);
    });    
    
	$('body').on('click','.adultMinus', function() {
		var parent = $(this).parents('.peoplePickBlock'),
		count = parseInt(parent.find(".adultBlock").attr('data-count')),
		isHelp = ($(this).parents("#peoplePickBlockHelp").length>0) ? true : false;
		if (count>=2)
		{
			count--;
			parent.find('.adultBlock span').html(count);
			parent.find(".adultBlock").attr('data-count',count);
			recalcPeopleBlock(isHelp);
		}
	});
	
	$('body').on('click','.adultPlus', function() {
		
		var parent = $(this).parents('.peoplePickBlock'),
		count = parseInt(parent.find(".adultBlock").attr('data-count')),
		isHelp = ($(this).parents("#peoplePickBlockHelp").length>0) ? true : false;
		if (count<=5)
		{
			count++;
			parent.find('.adultBlock span').html(count);
			parent.find(".adultBlock").attr('data-count',count);
			recalcPeopleBlock(isHelp);
		}
	});
	
	$('#peoplePick').click(function(){
		$("#peoplePickBlock").addClass('active');
	});
	
	$('body').on('click','.childMinus', function() {
		var isHelp = ($(this).parents("#peoplePickBlockHelp").length>0) ? true : false;
		$(this).parent().parent().remove();
		$('.childAdd').show();
		recalcPeopleBlock(isHelp);
		
	});
	$('.peoplePickBlockClose').click(function(){
		$(this).parent().removeClass('active');
	});
	
	$('body').on('keyup',".numField",function(){
        var vl = $(this).val().replace(/[^0-9]/g,'');
		if ($(this).attr("data-len")!==undefined)
		{
			var len = parseInt($(this).attr("data-len"));
			
			if (vl.length>len)
				vl = vl.substring(0,len);
		}
        $(this).val(vl);
	});
	
	$('body').on('keyup',".latField",function(){
        var vl = $(this).val().replace(/[^a-zA-Z]/g,'');
		
        $(this).val(vl);
	});
	
	$('.searchHelpFormShow').click(function(){
		var from = $("#from option:selected").text(),
		country  = $("#country option:selected").text(),
		fromTill = $("#date_from_till_block").text(),
		nights   = $("#nights-from_till_block").text(),
		daysFrom = $("input[name=daysFrom]").val(),
		daysTill = $("input[name=daysTill]").val(),
		dateFrom = new Date($("#date_from").val()),
		dateTo   = new Date($("#date_till").val()),
		count_people = $("#count_people").val(),
		count_child  = $("#count_child").val(),
		child_year1  = $("#child_year1").val(),
		child_year2  = $("#child_year2").val(),
		child_year3	 = $("#child_year3").val(),
		peoplePick	 = $("#peoplePick").text();
		$("#help_from").val(from);
		$("#help_country").val(country);
		$("#searchHelpForm").show();
		$("#help_from_till_block").text(fromTill);
		$("#help_nights_block").text(nights);
		$("#help_days_from").val(daysFrom);
		$("#help_days_till").val(daysTill);
		datepickerHelp.selectDate([dateFrom ,dateTo]);
		$("#nightPickBlock .pickCell").each(function(i,cell){
			var id = $(this).attr('id'),
			cClass=$(this).attr('class');
			id = id.replace(/pickCell/g, "pickCellHelp");
			
			$("#"+id).attr('class',cClass);
		});
		$("#peoplePickHelp").html(peoplePick);
		$('#peoplePickBlockHelp .childPickBlock').html($('#peoplePickBlock .childPickBlock').html());
		
		$("#count_people_help").val(count_people);
		$("#count_child_help").val(count_child);
		$("#child_year1_help").val(child_year1);
		$("#child_year2_help").val(child_year2);
		$("#child_year3_help").val(child_year3);
		
		$("html,body").animate({ scrollTop: $("#searchHelpForm").offset().top - 20  }, "slow");
	});
	
	if($("#hotelBlock .sCbList").length==0)
	{
		var cn = $("#country").val(),
		data = { mode:"hotel", country: cn};

		$.ajax( $("form[name=searchForm]").attr('action'), {
			cache: false,
			data: data,
			dataType: "json",
			error: errorHandler,
			success: successHotel,
			type: "POST"
		});
	}

	$('body').on('click','#help_nights_block',function(){
		$("#nightPickBlockHelp").addClass('active');
		$("#peoplePickBlockHelp").removeClass('active');
	});
	
	$('#peoplePickHelp').click(function(){
		$("#peoplePickBlockHelp").addClass('active');
		$("#nightPickBlockHelp").removeClass('active');
	});
	
	var phoneHelpOptions =  {
        onComplete: function(cep) {
           
            $('input[name=help_phone]').addClass('validphone');
            
        },
        onChange: function(cep){
            if (cep.length<18) {
                $('input[name=help_phone]').removeClass('validphone');
            }
        },
        onKeyPress: function(cep, event, currentField, options){
            if (cep.length<18) {
                $('input[name=help_phone]').removeClass('validphone');
            }
        },
        onInvalid: function(val, e, f, invalid, options){
            $('input[name=help_phone]').removeClass('validphone');
        }
    };
    
	$('input[name=help_phone]').mask('+7 (000) 000-00-00',phoneHelpOptions);
	
	$("#searchHelpSend").click(function(){
		$('#help_name').parent().parent().removeClass('errorBord');
        $('#help_phone').parent().parent().removeClass('errorBord');
        var form = $('form[name=helpForm]'),
		uname  = $("#help_name").val(),
        uphone = $("#help_phone").val();
        
        if (uname=="" ||  uphone=="")
        {
            if (uname==""){$('#help_name').attr('placeholder','Укажите Ваше имя').parent().parent().addClass('errorBord');}
            if (uphone==""){$('#help_phone').attr('placeholder','Укажите Ваш телефон').parent().parent().addClass('errorBord');}
			
           
        } 
        else if (!$("#help_phone").hasClass('validphone')){
            $("#help_phone").attr('placeholder','Укажите корректный телефон').parent().parent().addClass('errorBord');
			
        }
        
        else
        {    
			
            $('.orderSend').hide();
			formData = new FormData($('form[name=helpForm]')[0]);  
			formData.append("helpform",1);  
			$(this).hide();
            var url = $("form[name=searchForm]").attr('action');
            $.ajax( url, {
                cache: false,
                data: formData,
                dataType: "json",
                error: errorHandler,
				contentType: false,
				processData: false,
                success: function(res){
					if(res.send==1)
					{
						$("form[name=helpForm]").hide();
						$('.thanksHelpBlock').show();
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

								if(res.data.p1 && res.data.p1>0)
									ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: res.data.p1, currency: 'RUB'});
								if(res.data.p2 && res.data.p2>0)
									ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: res.data.p2, currency: 'RUB'});
							
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
				},
                type: "POST"
            });
        }
	});
	 
	$("#saveparams").click(function(){
		url = $("form[name=searchForm]").attr('action');
		data = prepareData();
		data.savedata = "y";
		$.ajax( url, {
			type: "POST",
			cache: false,
			data: data,
			dataType: "json",
			success: function(res){
				Clipboard.copy(res.link);
				alert("Скопирована ссылка на форму поиска");
			}
		});	
	});
	
});	



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
	{
        tid = setTimeout(checkReqState, interval);
		//setTimeout(function(){var percOut = getRandomInt(2,6); $(".seacrhMetInner").css({'width': percOut+'%'});}, 2000);
	}	
    
    if (data.data.commerce==1)
    {
        window.dataLayer = window.dataLayer || [];
        dataLayer.push({
            "ecommerce": {
                "purchase": {
                    "actionField": {
                        "id" : data.data.id,
                        "revenue" : data.data.revenue,
						"goal_id": 278300417
                    },
                    "products": [
                        {
                            "name": data.data.name,
                            "price": data.data.price,
                            "category": data.data.category
                        }
                        
                    ]
                }
            }
        });
        
       /* gtag('event', 'purchase', {
            "transaction_id": data.data.id,
            "value": data.data.revenue,
            "currency": "RUB",
            "items": [{
                "name": data.data.name,
                "price": data.data.price,
                "category": data.data.category,
                "quantity": 1
            }]
        });*/
		
        VK.Goal('search', {value: data.data.revenue});
        
    }

	 if (data.pretour)
    { 
		ym(69158110, 'reachGoal', 'PRETOUR',  {order_price: data.pretour, currency: 'RUB'} );
		    
		if(data.p1 && data.p1>0)
			ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: data.p1, currency: 'RUB'});
		if(data.p2 && data.p2>0)
			ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: data.p2, currency: 'RUB'});
	}


}

function successUpdate( data ) {
	$("#search_start").removeClass('disabled');
	if (data.percent== 0)
		percOut++;
	else
		percOut = data.percent;
	
    $("#searchProc").html(percOut);
    $(".seacrhMetInner").css({'width': percOut+'%'});
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
	 if (data.pretour)
    { 
		ym(69158110, 'reachGoal', 'PRETOUR',  {order_price: data.pretour, currency: 'RUB'} );
		if(data.p1 && data.p1>0)
			ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: data.p1, currency: 'RUB'});
		if(data.p2 && data.p2>0)
			ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: data.p2, currency: 'RUB'});
	}

	if (data.param_change)
    { 
		ym(69158110, 'reachGoal', 'HOTEL_PICK',  {order_price: data.pretour, currency: 'RUB'} );
		if(data.param_p1 && data.param_p1>0)
			ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: data.param_p1, currency: 'RUB'});
		if(data.param_p2 && data.param_p2>0)
			ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: data.param_p2, currency: 'RUB'});
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

function constructHotelsResult( data ) {
    $("#searchResultTable").html('');
    var tours = data.tours,
    isHotelMode = ($("input[name=hotel_mode]").length>0) ? true : false;
    $.each(tours,function(index,val) {
        var  div = $('<div class="airTour" id="hotel_'+val.id+'"></div>');
        var  airPic = $('<div class="airPic"></div>');
        var  air480 = $('<div class="dop480"></div>');
        var  airHotel = $('<div class="airHotel"></div>');
        var  airPrice = $('<div class="airPrice"></div>');
        var  airInfo = $('<div class="airInfo"></div>');
		var  btns    = '<div class="airDopBtns">'+
							'<div class="airDopBtn hotelTours" data-id="'+val.id+'">Цены</div>';				
		if(!isHotelMode)
		{	
			btns  += '<div class="airDopBtn hotelInfo" data-id="'+val.id+'">Об отеле</div>';			
			if (val.coords_lat	!="" && val.coords_lon!="")
				 btns  += '<div class="airDopBtn hotelMap" data-id="'+val.id+'" data-lat="'+val.coords_lat+'" data-lon="'+val.coords_lon+'">На карте</div>'
		} 
		btns +="</div>";
		
		var  airDop = $(btns);
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
        /*****/
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
        /*****/        
       
        airHotel.append(hotelName).append(hotelStars).append(hotelCountry).append(hotelRate);
        /*****/
        airPrice.append('<div class="airMainPrices"><div class="airMainPrice"><span class="grey"><span class="greyTours">туры </span>от</span><span class="mainPrice"> '+val.price + ' </span> руб.</div></div>');
       
        /*****
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.cityfrom+'</span></div>');
        airInfo.append('<div class="airInfoLine lineRoom">Размещение: <span class="bold">'+val.room+'</span></div>');
        airInfo.append('<div class="airInfoLine">Питание: <span class="bold">'+val.meal+'</span></div>');
        airInfo.append('<div class="airInfoLine">Вылет: <span class="bold">'+val.date+'</span></div>');
        airInfo.append('<div class="airInfoLine">Ночей: <span class="bold">'+val.nights+'</span></div>');
        /*****/
        div.append(airPic).append(air480).append(airHotel).append(airPrice).append('<div class="clear"></div>').append(airDop).append('<div class="clear"></div>');
        $("#searchResultTable").append(div);
        $("#searchResultTable").append('<div class="clear"></div>');
    });
    $("#searchResultTable").append(data.pager);
}

function errorHandler() {
	alert( "Произошла ошибка. Повторите запрос" );
	$("#search_start").removeClass('disabled');
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
	div.append('<div class="removeTours">Свернуть</div>');
    $("#hotel_"+hid+"_tours").remove();
    $("#hotel_"+hid+"_info").remove();
	$("#hotel_"+hid+"_map").remove();
    $("#hotel_"+hid).after(div);
}

function successHotelInfo( data )
{
    var  hid = data.hotel,
    div = $('<div class="hotel_info" id="hotel_'+hid+'_info"></div>'),
    hotelData = $('<div class="hotelData"></div>'),
    hotelPics = $('<div class="hotelPics"></div>');
	if(data.info.desc!="")
        hotelData.append('<div class="hotelDataLine">'+
                            '<span>Описание:</span> '+data.info.desc+
                        '</div>');
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
	div.append('<div class="removeHotelInfo">Свернуть</div>');
    $("#hotel_"+hid+"_info").remove();
    $("#hotel_"+hid+"_tours").remove();
	$("#hotel_"+hid+"_map").remove();
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
	hotelPrice = $('<div class="hotelPriceInfo"><div class="hotelTourPrice"><span>'+data.tour_info.price+'</span> руб.</div><div class="hotelNextStep loading">Продолжить</div><div class="fuelBlock">В том числе топливный сбор: <span></span></div></div>'),
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
        formOnlineDop.append('<div class="orderWide"><div class="orderInner"><div class="orderInputText">Ваше ФИО (полностью)</div><div class="orderInputWrap"><input id="userName" type="text" value=""></div></div></div>');
		
		formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Серия и номер гражд. паспорта</div> <div class="orderInputWrap"><input type="text" id="userPassSerNum" name="userPassSerNum" value="" /></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Дата выдачи паспорта</div> <div class="orderInputBdWrap" id=""><div class="orderBdateD"><input type="text" id="userPassd0" name="userPassd0" value="" placeholder="ДД" class="numField" data-len="2"> </div><div class="orderBdateM"><input type="text" id="userPassm0" name="userPassm0" value="" placeholder="ММ" class="numField" data-len="2"></div><div class="orderBdateY"><input type="text" id="userPassy0" name="userPassy0" value="" placeholder="ГГГГ" class="numField" data-len="4"></div></div></div>');
		
		
		formOnlineDop.append('<div class="clear"></div><div class="orderWide"><div class="orderInner"><div class="orderInputText">Кем выдан</div><div class="orderOnlineTextWrap"><textarea id="userPassWho" name="userPassWho" placeholder="УФМС г. Москва"></textarea></div></div>');
		
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш телефон</div><div class="orderInputWrap"><input id="userPhone" type="text" name="userPhone" value="" placeholder="+7 (___) ___-__-__"></div></div></div>');
        formOnlineDop.append('<div class="onName"><div class="orderInner"><div class="orderInputText">Ваш email</div> <div class="orderInputWrap"><input type="text" id="userEmail" name="userEmail" value="" /></div></div></div>');
        
        formOnlineDop.append('<div class="clear"></div><div class="clear"></div>');
        formOnlineDop.append('<div class="orderBot"><div class="orderInputText">Дополнительная информация</div><div class="orderTextWrap"><textarea id="userComment" name="userComment" ></textarea></div></div>');
        formOnlineDop.append('<input type="hidden" name="ordReq" id="ordReq" value="'+data.tour_info.reqid+'" />');
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
    form.append('<input type="hidden" name="ordReq" id="ordReq" value="'+data.tour_info.reqid+'" />');
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
    if(data['data']['fuel'] && data['data']['fuel']!="0")
    {     
		$('.tourHotelWrapInner .fuelBlock span').html(data['data']['fuel']+" руб.");
        $('.tourHotelWrapInner .fuelBlock').show();
    }    

	
    if(data['data']['price_ecom'])
	{
		window.dataLayer = window.dataLayer || [];
		dataLayer.push({
			"ecommerce": {
				"currencyCode": "RUB",
				"detail": {
					"products": [
						{
							"name":  data['data']['name_ecom'],
							"price": data['data']['price_ecom'],
						}
					]
				}
			}
		});
		ym(69158110, 'reachGoal', 'TOUR_OPEN',  {order_price: data['data']['price_ecom'], currency: 'RUB'});
		ym(69158110, 'reachGoal', 'TOUR_OPEN2', {order_price: data['data']['price_ecom2'], currency: 'RUB'});
		if(data['data']['p1'] && data['data']['p1']>0)
			ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: data['data']['p1'], currency: 'RUB'});
		if(data['data']['p2'] && data['data']['p2']>0)
			ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: data['data']['p2'], currency: 'RUB'});

	}
	
	requestDetail = false;
	
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
}*/

function successHotel( data ) {
    hotelList = [];
    $("#hotelBlock").attr("data-page",0);
    $("#hotelBlock").removeClass('fullLoaded');
    $("#hotelBlock").scrollTop(0);
    if (data.hotelList != null && data.hotelList[0]!= null)
    { 
        $("#hotelBlock").attr("data-page",0);
        $("#hotelBlock").removeClass('fullLoaded');
        hotelList=data.hotelList;
        setHotelsData( hotelList[0]);
		if(hotelList[0].length==1)
		{
			$('#hotelBlock .sCbList input').click();
		}
    }
    else
        $("#hotelBlock").addClass('fullLoaded');
}

function setHotelsData( data , addToEnd = false ) {
     var  div = '';
      
    $.each(data,function(index,val) {
        div += '<div class="sCbList" id="hotel'+val.id+'W">';
        div += '<label><input id="hotel'+val.id+'" type="checkbox" name="hotel" value="'+val.id+'">';
        div += '<span>'+val.name+'</span>';
        div += '</label></div>';  
    });
    if (addToEnd)
        $("#hotelBlock").append(div);
    else    
        $("#hotelBlock").html(div);
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
	var hide_reg_tours = 0;
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
	if ($("#hotel_mode").length>0)
		hotel[0] = $("input[name=hotel]").val();
	else	
		$("input[name=hotel]:checked").each(function(index){
			if ($(this).val()!="all" && $(this).val()!=0)
				hotel[index]= $(this).val();
		});
    var stars = [];
    $("input[name=stars]:checked").each(function(index){
            stars[index]= $(this).val();
    })
    var food = [];
    $("input[name=food]:checked").each(function(index){
            food[index]= $(this).val();
    })
    
	if($("#hide_reg_tours").is(":checked"))
		hide_reg_tours = 1;
	
	if(price_from!="" || price_till!="" || hide_reg_tours==1)
		paramsChanged = true;

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
		data.hide_reg_tours = hide_reg_tours;
		data.params_changed = paramsChanged ? "1" : "0";
    }    
    return data
    
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
							"id": res.data.id,
							"revenue": res.data.revenue,
							"goal_id": 278300417
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
			

				if(res.data.p1 && res.data.p1>0)
					ym(69158110, 'reachGoal', 'PRAETOR',  {order_price: res.data.p1, currency: 'RUB'});
				if(res.data.p2 &&  res.data.p2>0)
					ym(69158110, 'reachGoal', 'PRAETOR2',  {order_price: res.data.p2, currency: 'RUB'});
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

function pasportForm (form,j,isChild)
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
    form.append(block);
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

function recalcNightBlock()
{
	$('#nightPickBlock').removeClass('active');
	$('#nightPickBlock').removeClass('pickedOne');
	if ($('#nightPickBlock').find('.activePickCell').length>0)
	{
		var dayFrom = parseInt($('#nightPickBlock').find('.activePickCell').eq(0).text());
		dayTo = dayFrom;
		if ($('#nightPickBlock').find('.activePickHoverdCell').length>0)
		{
			dayTo = parseInt($('#nightPickBlock').find('.activePickHoverdCell').text());
			if (dayFrom>dayTo)
			{
				let dayTemp = dayTo;
				dayTo = dayFrom;
				dayFrom = dayTemp;
			}
		}
		else if ($('#nightPickBlock').find('.activeLastPickCell').length>0)
		{
			dayTo = parseInt($('#nightPickBlock').find('.activeLastPickCell').text());
			if (dayFrom>dayTo)
			{
				let dayTemp = dayTo;
				dayTo = dayFrom;
				dayFrom = dayTemp;
			}
		}
		$('input[name=daysFrom]').val(dayFrom);
		$('input[name=daysTill]').val(dayTo);
		$("#nights-from_till_block").text(dayFrom+" - "+dayTo);
		paramsChanged = true;
	}
}

function recalcNightBlockHelp()
{
	$('#nightPickBlockHelp').removeClass('active');
	$('#nightPickBlockHelp').removeClass('pickedOne');
	if ($('#nightPickBlockHelp').find('.activePickCell').length>0)
	{
		var dayFrom = parseInt($('#nightPickBlockHelp').find('.activePickCell').eq(0).text());
		dayTo = dayFrom;
		if ($('#nightPickBlockHelp').find('.activePickHoverdCell').length>0)
		{
			dayTo = parseInt($('#nightPickBlockHelp').find('.activePickHoverdCell').text());
			if (dayFrom>dayTo)
			{
				let dayTemp = dayTo;
				dayTo = dayFrom;
				dayFrom = dayTemp;
			}
		}
		else if ($('#nightPickBlockHelp').find('.activeLastPickCell').length>0)
		{
			dayTo = parseInt($('#nightPickBlockHelp').find('.activeLastPickCell').text());
			if (dayFrom>dayTo)
			{
				let dayTemp = dayTo;
				dayTo = dayFrom;
				dayFrom = dayTemp;
			}
		}
		//$('input[name=daysFrom]').val(dayFrom);
		//$('input[name=daysTill]').val(dayTo);
		$("#help_nights_block").text(dayFrom+" - "+dayTo);
	}
}

function recalcPeopleBlock(isHelp = false)
{
	var 
	block = (isHelp) ? "#peoplePickBlockHelp"  : "#peoplePickBlock" ,
	dop = (isHelp) ? "_help"  : "",
	dopHelp = (isHelp) ? "Help"  : "",
	adult = parseInt($(block + ' .adultBlock').attr('data-count')),
	childCount = 0,
	child1 = "",
	child2 = "",
	child3 = "";
	
	var str = "";
	if ($(block + ' .childPickBlockItem').length>0)
	{
		childCount = $(block + ' .childPickBlockItem').length;
		child1 = $(block + ' .childPickBlockItem').eq(0).find('select').val();
		if ($(block + ' .childPickBlockItem').eq(1))
			child2 = $(block + ' .childPickBlockItem').eq(1).find('select').val();
		if ($(block + ' .childPickBlockItem').eq(2))
			child3 = $(block + ' .childPickBlockItem').eq(2).find('select').val();	
		
		str = adult + " взр " + childCount + " реб";
	}
	else
	{
		if (adult==1)
			str = "1 взрослый";
		else
			str = adult+" взрослых";
	}	
	
	console.log("#peoplePick"+dopHelp + " " + str);
	$("#peoplePick"+dopHelp).html(str);
	$("#count_people"+dop).val(adult);
	$("#count_child"+dop).val(childCount);
	$("#child_year1"+dop).val(child1);
	$("#child_year2"+dop).val(child2);
	$("#child_year3"+dop).val(child3);
	if(!isHelp)
		paramsChanged = true;
}


function getRandomInt(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min)) + min; //Максимум не включается, минимум включается
}

Date.prototype.yyyymmdd = function() {
  var mm = this.getMonth() + 1; // getMonth() is zero-based
  var dd = this.getDate();

  return [this.getFullYear(),
          (mm>9 ? '' : '0') + mm,
          (dd>9 ? '' : '0') + dd
         ].join('-');
};


window.Clipboard = (function(window, document, navigator) {
	var textArea,
		copy;

	function isOS() {
		return navigator.userAgent.match(/ipad|iphone/i);
	}

	function createTextArea(text) {
		textArea = document.createElement('textArea');
		textArea.value = text;
		document.body.appendChild(textArea);
	}

	function selectText() {
		var range,
			selection;

		if (isOS()) {
			range = document.createRange();
			range.selectNodeContents(textArea);
			selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
			textArea.setSelectionRange(0, 999999);
		} else {
			textArea.select();
		}
	}

	function copyToClipboard() {        
		document.execCommand('copy');
		document.body.removeChild(textArea);
	}

	copy = function(text) {
		createTextArea(text);
		selectText();
		copyToClipboard();
	};

	return {
		copy: copy
	};
})(window, document, navigator);

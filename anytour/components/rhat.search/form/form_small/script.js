var tid = 0;
reqid = 0,
interval = 3000,
percOut = 0,
tours=0,
requestDetail = true,
hotelTimer = 0,
map = [];
$(document).ready(function() {
	
	
	var monthsShort = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'],
    dateFrom = new Date( $('#date_from_till').attr("data-datefrom")),
	dateTo       = new Date( $('#date_from_till').attr("data-dateto"));

	
	
	var datepicker = $('#date_from_till').datepicker({
		minDate: new Date(),
		range: true,
        position: ($('body').width()<700) ? "bottom right" : "bottom left",
		multipleDatesSeparator: " - ",
		onSelect: function(fd, d, picker) {
			var dateStr = "";
			if (d.length==1)
			{
				var mon = d[0].getMonth(),
				day = d[0].getDate(),
				dateF = day+" "+monthsShort[mon];
				dateStr = dateF+" - "+dateF;
				$('input[name=date_from]').val(d[0].yyyymmdd());
				$('input[name=date_till]').val(d[0].yyyymmdd());
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
				$('input[name=date_from]').val(d[0].yyyymmdd());
				$('input[name=date_till]').val(d[1].yyyymmdd());
			}
			$('#date_from_till_block').html( dateStr);
		}	
	}).data('datepicker');
	
	datepicker.selectDate([dateFrom ,dateTo]);
	
	$("#date_from_till_block").click(function(){
		datepicker.show();
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
    });
    

        
    $('.sSelect').change( function() {
        var vl = $(this).val();
        
        var txt = ($(this).attr('id')=="from") ?  $(this).children("option[value='"+vl+"']").attr("data-name") :  $(this).children("option[value='"+vl+"']").text();
        
        $(this).prev('span').html(txt);
        
    });    
    

    	
	
	
	$('body').on('click','#nights-from_till_block',function(){
		$("#nightPickBlock").addClass('active');
        $("#peoplePickBlock").removeClass('active');
	});
	
	$('body').on('click','.pickCell',function(){
		if ($("#nightPickBlock").hasClass('pickedOne'))
		{
			recalcNightBlock();
		}
		else
		{
			$('.activeLastPickCell').removeClass('activeLastPickCell');
			$('.activePickCell').removeClass('activePickCell');
			$('.hoverPickCell').removeClass('hoverPickCell');
			$(this).addClass('activePickCell');
			$("#nightPickBlock").addClass('pickedOne');
		}
	});
	
	$('body').on('mouseenter','.pickCell',function(){
		if ($('.activeLastPickCell').length==0)
		{
			$('.hoverPickCell').removeClass('hoverPickCell');
			$('.activePickHoverdCell').removeClass('activePickHoverdCell');
		
			var val = parseInt($(this).text()),
			activeVal =  parseInt($('.activePickCell').text());
			if (activeVal>val)
			{
				var start = activeVal-val;
				if (start>1)
				{
					if (start>14)
						val = val + (start - 14);
						
					for (var i = (activeVal-1); i >= val; i--) {
						if(i==val)
							$("#pickCell"+i).addClass('activePickHoverdCell');
						else	
							$("#pickCell"+i).addClass('hoverPickCell');
						
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
						$("#pickCell"+i).addClass('hoverPickCell');
						if(i==val)
							$("#pickCell"+i).addClass('activePickHoverdCell');
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
	});
		
	$('body').on('click','.childAdd',function(){
		var html =  '<div class="childPickBlockItem">'+
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
		$('.childPickBlock').append(html);	
		if ($('.childPickBlockItem').length==3)
			$(this).hide();
		recalcPeopleBlock();	
	});
		
	$('body').on('change','select[name=child_year_pick]', function() {
        var vl = $(this).val();
        $(this).prev('span').html(vl);
		recalcPeopleBlock();
    });    
    
	$('body').on('click','.adultMinus', function() {
		var count = parseInt($(".adultBlock").attr('data-count'));
		if (count>=2)
		{
			count--;
			$('.adultBlock span').html(count);
			$(".adultBlock").attr('data-count',count);
			recalcPeopleBlock();
		}
	});
	
	$('body').on('click','.adultPlus', function() {
		var count = parseInt($(".adultBlock").attr('data-count'));
		if (count<=5)
		{
			count++;
			$('.adultBlock span').html(count);
			$(".adultBlock").attr('data-count',count);
			recalcPeopleBlock();
		}
	});
	
	$('#peoplePick').click(function(){
		$("#peoplePickBlock").addClass('active');
	});
	
	$('body').on('click','.childMinus', function() {
		$(this).parent().parent().remove();
		$('.childAdd').show();
		recalcPeopleBlock();
		
	});
	$('.peoplePickBlockClose').click(function(){
		$("#peoplePickBlock").removeClass('active');
	});
	
	
});	

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
		$('input[name=days_from]').val(dayFrom);
		$('input[name=days_till]').val(dayTo);
		$("#nights-from_till_block").text(dayFrom+" - "+dayTo);
	}
}


function recalcPeopleBlock()
{
	var adult = parseInt($('.adultBlock').attr('data-count')),
	childCount = 0,
	child1 = "",
	child2 = "",
	child3 = "";
	
	var str = "";
	if ($('.childPickBlockItem').length>0)
	{
		childCount = $('.childPickBlockItem').length;
		child1 = $('.childPickBlockItem').eq(0).find('select').val();
		if ($('.childPickBlockItem').eq(1))
			child2 = $('.childPickBlockItem').eq(1).find('select').val();
		if ($('.childPickBlockItem').eq(2))
			child3 = $('.childPickBlockItem').eq(2).find('select').val();	
		
		str = adult + " взр " + childCount + "реб";
	}
	else
	{
		if (adult==1)
			str = "1 взрослый";
		else
			str = adult+" взрослых";
	}	
	$("#peoplePick").html(str);
	$("#count_people").val(adult);
	$("#count_child").val(childCount);
	$("#child_year1").val(child1);
	$("#child_year2").val(child2);
	$("#child_year3").val(child3);
	
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


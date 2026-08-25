<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?
$isAdmin = $USER->IsAdmin();
?>
<div class="pList">
	<div class="pListHead">
		<div class="pListCell dateCell">
			<b>Дата создания</b>
		</div>
		<?if ($isAdmin){?>
		<div class="pListCell statCell">
			<b>Создал</b>
		</div>
		<?}?>
		<div class="pListCell statCell">
			<b>Статус</b>
		</div>
		<div class="pListCell">
			<b>Тип</b>
		</div>
		<div class="pListCell">
			<b>Номер</b>
		</div>
		<div class="pListCell">
			<b>Сумма</b>
		</div>
		<div class="pListCell">
			<b>Комментарий</b>	
		</div>
		<div class="pListCell dateCell">
			<b>Дата оплаты</b>
		</div>
		<div class="pListCell linkCell">
		</div>
	</div>
<?foreach($arResult["ITEMS"] as $i=>$arItem):?>
	<div class="pListItem">
		<div class="pListCell dateCell">
			<?=str_replace(" ","<br />",$arItem["DATE_CREATE"])?>
		</div>
		<?if ($isAdmin){?>
		<div class="pListCell statCell">
			<?=$arItem["CREATED_USER_NAME"]?>
		</div>
		<?}?>
		<div class="pListCell statCell <?if ($arItem["PROPERTIES"]["STATUS"]["VALUE_ENUM_ID"]==2){?>payed<?}?>">
			<?=$arItem["PROPERTIES"]["STATUS"]["VALUE"]?>
		</div>
		<div class="pListCell">
			<?=$arItem["PROPERTIES"]["PAY_TYPE"]["VALUE"]?>
		</div>
		<div class="pListCell">
			<b><?=$arItem["PROPERTIES"]["ORDER_ID"]["VALUE"]?></b>
		</div>
		<div class="pListCell cellSum">
			<div class="sumWrap">
				<span class="sumSpan"><?=number_format($arItem["PROPERTIES"]["SUM"]["VALUE"], 2, '.', ' ');?></span>
				<?if ($arItem["PROPERTIES"]["STATUS"]["VALUE_ENUM_ID"]==1 && $arItem["PROPERTIES"]["TYPE"]["VALUE_ENUM_ID"]==4 ){?>
				<input type="text" class="sumInput" name="sum<?=$arItem["ID"]?>" data-id="<?=$arItem["ID"]?>" value="<?=$arItem["PROPERTIES"]["SUM"]["VALUE"]?>">
				<div class="editBtn"></div>
				<div class="saveBtn" data-id="<?=$arItem["ID"]?>"></div>
				<?}?>
			</div>
		</div>
		<div class="pListCell">
			<?=$arItem["PROPERTIES"]["COMMENT"]["VALUE"]?>
			<?if ($arItem["PROPERTIES"]["PHONE"]["VALUE"]!=""){?>
			<?if ($arItem["PROPERTIES"]["COMMENT"]["VALUE"]!=""){?><br/><?}?>
			Телефон: <?=$arItem["PROPERTIES"]["PHONE"]["VALUE"]?>
			<?}
			if ($arItem["PROPERTIES"]["EMAIL"]["VALUE"]!=""){?>
			<?if ($arItem["PROPERTIES"]["COMMENT"]["VALUE"]!="" || $arItem["PROPERTIES"]["PHONE"]["VALUE"]!=""){?><br/><?}?>
			Email: <?=$arItem["PROPERTIES"]["EMAIL"]["VALUE"]?>
			<?}?>
		</div>
		<div class="pListCell dateCell">
			<?=$arItem["PROPERTIES"]["PAY_DATE"]["VALUE"]?>
		</div>
		<div class="pListCell linkCell">
			<div class="copyBtn" data-id="<?=$arItem["ID"]?>" data-num="<?=$arItem["PROPERTIES"]["ORDER_ID"]["VALUE"]?>" data-sum="<?=$arItem["PROPERTIES"]["SUM"]["VALUE"]?>"></div>
			<?
			if ($arItem["PROPERTIES"]["STATUS"]["VALUE_ENUM_ID"]==1 && $arItem["PROPERTIES"]["PAY_TYPE"]["VALUE"]!=""){?><div data-link="<?=$arItem["PAY_LINK"]?>" class="payLinkIcon"></div><?}?>
			
			
			<?if ($arItem["PROPERTIES"]["BOOK_LINK"]["VALUE"]!=""){?><a href="<?=$arItem["PROPERTIES"]["BOOK_LINK"]["VALUE"]?>" target="_blank" class="bookLinkIcon"></a><?}?>
		</div>
	</div>	
<?endforeach;?>
</div>
<?if($arParams["DISPLAY_BOTTOM_PAGER"]):?>
	<?=$arResult["NAV_STRING"]?>
<?endif;?>


<script>
	$('.payLinkIcon').click(function(){
		
		Clipboard.copy($(this).data('link'));
		var sum = $(this).parent().parent().find('.sumSpan').html();
		alert("Скопирована ссылка на оплату\r\n"+sum);
		
	})
	
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
	
	$('.editBtn').click(function(){
		$(this).hide();
		$(this).siblings( ".saveBtn" ).show();
		$(this).siblings( ".sumInput" ).show();
		$(this).siblings( ".sumSpan" ).hide();
	});
	
	$('.saveBtn').click(function(){
		var sum = $(this).siblings( ".sumInput").val(),
		id = $(this).data("id"),
		btn= $(this);
		
		if (sum!="" && id!="")
		{	
			$.ajax({
				url: '/tf/list/ajax.php',
				data: {sum:sum,id:id},
				cache: false,
				dataType: "json",
				type: 'POST',
				success: function(res){
					
					btn.hide();
					btn.siblings( ".editBtn" ).show();
					btn.siblings( ".sumInput" ).hide();
					btn.siblings( ".sumSpan" ).html(sum).show();
				}
			});
		}		
	});	
	
	$('.copyBtn').click(function(){
		var id = $(this).data('id'),
		num = $(this).data('num'),
		sum = $(this).data('sum');
		
		$.fancybox.open("<div class='copyBlock'><div class='copyTitle'>Скопировать заявку <b>"+num+"</b></div>Выберите тип оплаты:<br /><select name='payType'><option value='5' selected>Полная оплата</option><option value='6'>Предоплата</option></select>Сумма к оплате:<br><input name='sum' value='"+sum+"' type='text'><div class='sendCopy' data-id='"+id+"'>Скопировать</div></div>",{touch:false});
	});
	
	$('body').on('click','.sendCopy', function(){
		var id = $(this).data('id'),
		type   = $(this).parent().find('select').val(),
		sum    = $(this).prev().val();
		$.ajax({
			url: '/tf/list/ajax.php',
			data: {action:'copy',id:id,type:type,sum:sum},
			cache: false,
			dataType: "json",
			type: 'POST',
			success: function(res){
				$.fancybox.close();
				location.reload();
			}
		});
	});
	
	
</script>


<style>
.pList
{
	display:table;
	width:100%;
	color:#000;
	font-size:14px
}
.pListHead,.pListItem
{
	display:table-row;
}
.pListCell
{
	display:table-cell;
	padding: 5px 10px;
	border-bottom:1px solid #2E3192;
	vertical-align:middle;
}
.pListCell.payed
{
	background:#90DB75;
}
.dateCell
{
	width:90px;
	text-align:center;
}

.statCell
{
	text-align:center;
	
}

.pListItem:nth-child(2n+1) .pListCell
{
	background:#ececec;
}

.pListCell.payed
{
	background:#90DB75!important;
}
.payLinkIcon
{
	width:24px;
	height:24px;
	background: #ee1c25 url("<?=$templateFolder?>/link.png") no-repeat center center;
	background-size: 15px 15px;
	cursor:pointer;
	display:inline-block;
}

.bookLinkIcon
{
	width:24px;
	height:24px;
	background:  #2e3192 url("<?=$templateFolder?>/book.png") no-repeat center center;
	background-size: 15px 15px;
	cursor:pointer;
	display:inline-block;
	margin-right:0px;
}

.copyBtn
{
	width:24px;
	height:24px;
	background:  #2e3192 url("<?=$templateFolder?>/copy.png") no-repeat center center;
	background-size: 18px 18px;
	cursor:pointer;
	display:inline-block;
	margin-right:0px;
}

.linkCell
{
	width:24px;
	padding: 5px 3px;
	text-align:right;
}

.editBtn
{
	width:20px;
	height:20px;
	background:  #2e3192 url("<?=$templateFolder?>/edit.png") no-repeat center center;
	background-size: 15px 15px;
	cursor:pointer;
	display:inline-block;
	position:absolute;
	right:-10px;
	top:-2px;
	
}

.saveBtn
{
	width:20px;
	height:20px;
	 
	background:  #e30613 url("<?=$templateFolder?>/save.png") no-repeat center center;
	background-size: 15px 15px;
	cursor:pointer;
	display:inline-block;
	position:absolute;
	right:-10px;
	top:2px;
	display:none;
}

.sumWrap
{
	position:relative;
	
}

.sumInput
{
	width:90%;
	padding:3px 5px;
	border:1px solid red;
	display:none;
	max-width: 80px;
	background:#fff;
}

.cellSum
{
	width:120px;
}


.copyBlock
{
	color:#000;
	text-align:center;
}
.copyTitle
{
	font-size:16px;
	margin-bottom:10px;
}
.copyBlock select
{
	display:block;
	border: 1px solid #2E3192;
	padding: 5px 0px;
	margin: 10px auto 5px;
	width: 180px;
	font-size: 12px;
	text-indent: -2px;
	text-align:center;
	color:#000;
	
}
.sendCopy
{
	display: block;
	background:#ee1c25;
	text-align: center;
	color:#fff;
	text-decoration: none;
	width: 180px;
	height: 30px;
	line-height: 30px;
	margin:0 auto;
	cursor:pointer;
}
.copyBlock input[type="text"] {
	width: 180px;
	border: 1px solid #2E3192;
	height: 30px;
	padding: 10px 5px;
	margin: 0px auto 15px;
	max-width: 300px;
	height: 24px;
	font-size: 14px;
	color:#000;
	text-align:center;
	font-weight:bold;
}

</style>


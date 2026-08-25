<link href="<?=$templateFolder. '/style.css'?>" rel="stylesheet">
<script src="<?=$templateFolder?>/script.js"></script>
<div class="searchSmallWrap">
	<form method="post" name="searchFormSmall" action="/poisk-turov/" autocomplete="off">
	<div class="searchSmallLine">
		<div class="searchSmallItem">
			<div class="searchLabel">Откуда</div>
			<div class="searchSelect">
				<span class="searchSelectText">
					<?if (count($arResult["FORM_PARAMS"]["fromList"])>0) { ?>
						<?foreach ($arResult["FORM_PARAMS"]["fromList"] as $k=>$cname) {
						   if ($k==$arResult["FORM_PARAMS"]["from"]){?><?=$cname?><? break;}
						}
					}?>	
				</span>
				<select id="from" class="sSelect" name="from">
				<?if (count($arResult["FORM_PARAMS"]["fromList"])>0) { ?>
					<?foreach ($arResult["FORM_PARAMS"]["fromList"] as $k=>$cname) {?>
						<option value="<?=$k?>" <?if ($k==$arResult["FORM_PARAMS"]["from"]){?>selected="selected"<?}?>><?=$cname?></option>
					<?}
				}?>	
				</select>
			</div>
		</div>
		<div class="searchSmallItem">
			<div class="searchLabel">Куда</div>
			<div class="searchSelect">
				<span class="searchSelectText">
					<?if (count($arResult["FORM_PARAMS"]["countryList"])>0) { ?>
						<?foreach ($arResult["FORM_PARAMS"]["countryList"] as $k=>$cname) {
						   if ($k==$arResult["FORM_PARAMS"]["country"]){?><?=$cname?><? break;}
						}
					}?>	
				</span>
				<select id="country" class="sSelect" name="country">
				<?if (count($arResult["FORM_PARAMS"]["countryList"])>0) { ?>
					<?foreach ($arResult["FORM_PARAMS"]["countryList"] as $k=>$cname) {?>
						<option value="<?=$k?>" <?if ($k==$arResult["FORM_PARAMS"]["country"]){?>selected="selected"<?}?>><?=$cname?></option>
					<?}
				}?>	
				</select>
			</div>
		</div>
		<div class="searchSmallItem">
			<div class="searchSmallItemGrid">
				<div class="searchSmallItemGridItem">
					<div class="searchLabel">Вылет</div>
					<div class="searchInput">
						<div class="searchSelect">
							<span class="searchSelectPref">С</span>
							<div class="sInputWrap inputDate">
								<div class="dateIcon"></div>    
								<input type="date" name="date_from" class="date_from" id="date_from" value="<?=$arResult["FORM_PARAMS"]["date_from"]?>"> 
							</div>
						</div>
					</div>
				</div>
				<div class="searchSmallItemGridItem">
					<div class="searchLabel">&nbsp;</div>
					<div class="searchInput">
						<div class="searchSelect">
							<span class="searchSelectPref">По</span>
							<div class="sInputWrap inputDate">
								<div class="dateIcon"></div>    
								<input type="date" name="date_till" class="date_till" id="date_till" value="<?=$arResult["FORM_PARAMS"]["date_till"]?>"> 
							</div>
						</div>  
					</div>
				</div>
			</div>
		</div>
		<div class="searchSmallItem">
			<div class="searchSmallItemGrid">
				<div class="searchSmallItemGridItem">
					<div class="searchLabel">Взрослых</div>
					<div class="searchSelect">
						<span class="searchSelectText">2</span>
						<select name="count_people" id="count_people" class="sSelect">
							<?for($i=1;$i<=4;$i++) {?>
								<option value="<?=$i?>" <?if ($i==2){?>selected="selected"<?}?>><?=$i?></option>
							<?}?>
						</select>
					</div>
				</div>
				<div class="searchSmallItemGridItem">		
					<div class="searchLabel">Детей</div>
					<div class="searchSelect">
						<span class="searchSelectText">0</span>
						<select name="count_child" id="count_child" class="sSelect">
							<?for($i=0;$i<=3;$i++) {?>
								<option value="<?=$i?>" ><?=$i?></option>
							<?}?>
						</select>
					</div>
				</div>
			</div>	
		</div>
		<div class="searchSmallItem clearfix">
			<div class="searchLabel">Возраст детей</div>
			<div class="searchInputTpl searchTplLeft">
				<div class="searchSelect disabled">
					
					<span class="searchSelectText">нет</span>
					<select name="child_year1" id="child_year1" disabled class="sSelect">
						<?for($i=0;$i<=18;$i++) {?>
							<option value="<?=$i?>" ><?=$i?></option>
						<?}?>
					</select>
				</div>
			</div>
			<div class="searchInputTpl searchTplMid">
				<div class="searchSelect disabled">
					<span class="searchSelectText">нет</span>
					<select name="child_year2" id="child_year2" disabled class="sSelect">
						<?for($i=0;$i<=18;$i++) {?>
							<option value="<?=$i?>" ><?=$i?></option>
						<?}?>
					</select>
				</div>    
			</div>
			<div class="searchInputTpl searchTplRight">
				<div class="searchSelect disabled">
					<span class="searchSelectText">нет</span>
					<select name="child_year3" id="child_year3" disabled class="sSelect">
						<?for($i=0;$i<=18;$i++) {?>
							<option value="<?=$i?>" ><?=$i?></option>
						<?}?>
					</select>
				</div>    
			</div>
		</div>
		<div class="searchSmallItem">
			<div class="searchSmallItemGrid">
				<div class="searchSmallItemGridItem">
					<div class="searchLabel">Ночей</div>
					<div class="searchInput">
						<div class="searchSelect">
							<span class="searchSelectPref">От</span>
							<span class="searchSelectText searchSelectTextWithPref"><?=$arResult["FORM_PARAMS"]["nights_from"]?></span>
							<select name="count_days_from" id="count_days_from" class="sSelect">
								<?for($i=1;$i<=29;$i++) {?>
									<option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["nights_from"]){?>selected="selected"<?}?> ><?=$i?></option>
								<?}?>
							</select>
						</div>
					</div>
				</div>
				<div class="searchSmallItemGridItem">
					<div class="searchLabel">&nbsp;</div>
					<input type="submit" value="Искать" name="search_start_small" id="search_start_small">
				</div>
			</div>	
		</div>
	</div>
	</form>
</div>
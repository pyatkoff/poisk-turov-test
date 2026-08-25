<?/*?><link href="<?=$templateFolder. '/slick/slick.css'?>" rel="stylesheet">
<link href="<?=$templateFolder. '/style.css?v1.004'?>" rel="stylesheet"><
<script src="<?=$templateFolder?>/slick/slick.min.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAXSFLMaEfAkShI2OVF9cikj9uMQ5pk6ME"></script>
<?/*?><script src="<?=$templateFolder?>/script.js"></script><?*/?>
<div class="searchMainWrap">
    <div class="wrap-invis">
        <div class="formWrap searchFormWrap">
            <div class="formWrapInner">
                <form method="post" name="searchFormSmall" action="/poisk-turov/" autocomplete="off">
                    <?echo bitrix_sessid_post();?>
					<input type="hidden" name="date_from" id="date_from" value="<?=$arResult["FORM_PARAMS"]["date_from"]?>">
					<input type="hidden" name="date_till"   id="date_till" value="<?=$arResult["FORM_PARAMS"]["date_till"]?>">
					<input type="hidden" name="days_from"   id="count_days_from" value="<?=$arResult["FORM_PARAMS"]["nights_from"]?>">
					<input type="hidden" name="days_till"   id="count_days_till" value="<?=$arResult["FORM_PARAMS"]["nights_till"]?>">
					<input type="hidden" name="count_people"  id="count_people" value="<?=$arResult["FORM_PARAMS"]["count_people"]?>">
					<input type="hidden" name="count_child"   id="count_child" value="<?=$arResult["FORM_PARAMS"]["count_child"]?>">
					<input type="hidden" name="child_year1"   id="child_year1" value="">
					<input type="hidden" name="child_year2"   id="child_year2" value="">
					<input type="hidden" name="child_year3"   id="child_year3" value="">
                    <div class="searchTop">
						<div id="fromWrap">
							<div class="searchLabel">Вылет из</div>
							<div class="searchSelect">
								<span class="searchSelectText">
									<?if (count($arResult["FORM_PARAMS"]["fromListFull"])>0) { ?>
										<?foreach ($arResult["FORM_PARAMS"]["fromListFull"] as $k=>$cname) {
										   if ($k==$arResult["FORM_PARAMS"]["from"]){?><?=$cname["NAME2"]?><? break;}
										}
									}?>	
								</span>
								<select id="from"  class="sSelect" name="from">
								<?if (count($arResult["FORM_PARAMS"]["fromListFull"])>0) { ?>
									<?foreach ($arResult["FORM_PARAMS"]["fromListFull"] as $k=>$cname) {?>
										<option value="<?=$k?>" <?if ($k==$arResult["FORM_PARAMS"]["from"]){?>selected="selected"<?}?> data-name="<?=$cname["NAME2"]?>"><?=$cname["NAME"]?></option>
									<?}
								}?>	
								</select>
							</div>
						</div>
						<div class="searchQuadColLeft">
							<div class="searchColInner">
								<div id="countryWrap">
									<div class="searchLabel">Страна</div>
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
							</div> 
						</div>
						<div class="searchQuadColCenter">
							<div class="searchColInner">
								<div id="dateWrap">
									<div class="searchLabel">Вылет</div>
									<div class="searchInput">
										<div class="searchSelect">
											<div class="sInputWrap datesWrap">
												<div id="date_from_till_block"></div>
												<input type="text" name="date_from_till" class="" id="date_from_till" value="" data-datefrom="<?=$arResult["FORM_PARAMS"]["date_from"]?>" data-dateto="<?=$arResult["FORM_PARAMS"]["date_till"]?>"> 
											</div>
										</div>
									</div>
									<div class="clear"></div>
								</div>    
							</div>
						</div>
						<div class="searchQuadColCenter">
							<div class="searchColInner">
								<div id="nightsWrap">
									<div class="searchLabel">Ночей</div>
									<div class="searchInput">
										<div class="searchSelect">
											<div class="sInputWrap nightsWrap">
												<div id="nights-from_till_block" class="nights-from_till_block"><?=$arResult["FORM_PARAMS"]["nights_from"]?> - <?=$arResult["FORM_PARAMS"]["nights_till"]?></div>
												<div id="nightPickBlock" class="nightPickBlock">
													<div class="nightPickBlockTitle">Ночей от:</div>
													<div class="pickTable">
														<div class="pickRow">
															<?for($i=1;$i<=7;$i++){?>
															<div id="pickCell<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
															<?}?>
														</div>
														<div class="pickRow">
															<?for($i=8;$i<=14;$i++){?>
															<div id="pickCell<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
															<?}?>
														</div>
														<div class="pickRow">
															<?for($i=15;$i<=21;$i++){?>
															<div id="pickCell<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
															<?}?>
														</div>
														<div class="pickRow">
															<?for($i=22;$i<=28;$i++){?>
															<div id="pickCell<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
															<?}?>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="clear"></div>
								</div>    
							</div>
						</div>
						<div class="searchQuadColCenter">
							<div class="searchColInner">
								<div id="peopleWrap">
									<div class="searchLabel">Туристы</div>
									<div class="searchInput">
										<div class="searchSelect">
											<div class="sInputWrap peopleWrap">
												<div id="peoplePick"><?=$arResult["FORM_PARAMS"]["count_people"]?> взрослых</div>
												<div id="peoplePickBlock" class="peoplePickBlock">
													<div class="peoplePickBlockTitle">Туристы</div>
													<div class="adultPickBlock">
														<div class="adultMinus">-</div>
														<div class="adultBlock" data-count="<?=$arResult["FORM_PARAMS"]["count_people"]?>"><span><?=$arResult["FORM_PARAMS"]["count_people"]?></span>&nbsp;взрослых</div>
														<div class="adultPlus">+</div>
													</div>
													<div class="childPickBlock">
														
														
													</div>
													<div class="childAdd" data-count="0">Добавить ребенка</div>
													<div class="peoplePickBlockClose">Закрыть</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>	
						</div>
						<div class="searchQuadColRight">
							<div class="searchColInner">
								<input type="submit" value="Искать туры" name="search_start" id="search_start">   
							</div>
						</div>	
					</div>
					
                </form>
            </div>
        </div>
    </div>
 </div>   
<link href="<?=$templateFolder. '/slick/slick.css?v1.001'?>" rel="stylesheet">
<?/*?><link href="<?=$templateFolder. '/style.css?v1.004'?>" rel="stylesheet">
<script src="<?=$templateFolder?>/slick/slick.min.js"></script><?*/?>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAXSFLMaEfAkShI2OVF9cikj9uMQ5pk6ME"></script>
<?
//prf($arResult["FORM_PARAMS"]);
?>
<?/*?><script src="<?=$templateFolder?>/script.js"></script><?*/?>
<div class="searchMainWrap">
    <div class="wrap-invis">
        <div class="formWrap searchFormWrap">
            <div class="formWrapInner">
                <form method="post" name="searchForm" action="<?=$templateFolder?>/ajax.php" autocomplete="off" data-start="<?=$arResult["AUTO_START"]?>">
                    <?echo bitrix_sessid_post();?>
					<input type="hidden" name="dateFrom" id="date_from" value="<?=$arResult["FORM_PARAMS"]["date_from"]?>">
					<input type="hidden" name="dateTo"   id="date_till" value="<?=$arResult["FORM_PARAMS"]["date_till"]?>">
					<input type="hidden" name="daysFrom"   id="count_days_from" value="<?=$arResult["FORM_PARAMS"]["nights_from"]?>">
					<input type="hidden" name="daysTill"   id="count_days_till" value="<?=$arResult["FORM_PARAMS"]["nights_till"]?>">
					<input type="hidden" name="count_people"  id="count_people" value="<?=$arResult["FORM_PARAMS"]["count_people"]?>">
					<input type="hidden" name="count_child"   id="count_child" value="<?=$arResult["FORM_PARAMS"]["count_child"]?>">
					<input type="hidden" name="child_year1"   id="child_year1" value="<?=$arResult["FORM_PARAMS"]["child_year1"]?>">
					<input type="hidden" name="child_year2"   id="child_year2" value="<?=$arResult["FORM_PARAMS"]["child_year2"]?>">
					<input type="hidden" name="child_year3"   id="child_year3" value="<?=$arResult["FORM_PARAMS"]["child_year3"]?>">
					<?if ($arResult["HOTEL_MODE"]){?>
					<input type="hidden" name="hotel_mode"   id="hotel_mode" value="Y">
					<input type="hidden" name="country"   id="country" value="<?=$arResult['COUNTRY']?>">
					<input type="hidden" name="hotel"   id="hotel" value="<?=$arResult['HOTEL']?>">
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
								<select id="from" class="sSelect">
								<?if (count($arResult["FORM_PARAMS"]["fromListFull"])>0) { ?>
									<?foreach ($arResult["FORM_PARAMS"]["fromListFull"] as $k=>$cname) {?>
										<option value="<?=$k?>" <?if ($k==$arResult["FORM_PARAMS"]["from"]){?>selected="selected"<?}?> data-name="<?=$cname["NAME2"]?>"><?=$cname["NAME"]?></option>
									<?}
								}?>	
								</select>
							</div>
						</div>
					</div>
					<div class="searchColLeft">
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
							<div id="priceWrap" class="priceHotelMode">
                                <div class="searchLabel">Цена (руб.)</div>
                                <div class="searchInputDbl searchDblLeft">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">От</span>
                                        <div class="sInputWrap">
                                            <input type="text" name="price_from" id="price_from">
                                        </div>
                                    </div>
                                </div>
                                <div class="searchInputDbl">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">До</span>
                                        <div class="sInputWrap">
                                            <input type="text" name="price_till" id="price_till">
                                        </div>
                                    </div>  
                                </div>
                                 <div class="clear"></div>
                            </div> 
							<div class="regTourBlock cbBlock priceHotelMode">
								<input type="checkbox" value="hide_reg_tours" id="hide_reg_tours" name="hide_reg_tours" class="cb" >
								<label for="hide_reg_tours"><span></span>Скрыть туры на регулярных рейсах</label>
							</div>
							
                        </div>
                    </div>
                    <div class="searchColCenter">
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
                           
							
                            <div id="foodWrap" class="priceHotelMode">
                                <div class="searchLabel">Питание от:
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="food_all" id="food_all" checked="checked" name="food_all" class="allcb" data-for="food">
                                        <label for="food_all" ><span></span>Любое</label>
                                    </div>
                                </div>
                                <div class="searchCbWrap cbBlock">
                                    <?if (count($arResult["FORM_PARAMS"]["foodList"])>0) { $i=0; ?>
                                    <div class="cbLine">
                                        <?foreach ($arResult["FORM_PARAMS"]["foodList"] as $k=>$cname) {  $i++;?>
                                        <div class="cbLineItem">
                                           <input type="checkbox" value="<?=$k?>" id="food_<?=$k?>" name="food" class="sCbSearch">
                                           <label for="food_<?=$k?>"><span></span><?=$cname?></label>
                                        </div>   
                                        <?if($i%4==0 && count($arResult["FORM_PARAMS"]["foodList"])!=$i ) {?><div class="clear"></div></div><div class="cbLine"><?}
                                        }?>
                                    </div>    
                                    <?}?>
                                    <div class="clear"></div>
                                </div>
                            </div> 
                        </div>
                    </div>
                    <div class="searchColRight">
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
                            <div id="sendBtnWrapWrap">
                                <input type="submit" value="Искать туры" name="search_start" id="search_start">
                            </div>    
                        </div>
                    </div>
					<div class="clear"></div>
					
					
					<?} else {?>
					
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
								<select id="from" class="sSelect">
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
										<select id="country" class="sSelect">
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
						<div class="searchQuadColRight">
							<div class="searchColInner">
								<div id="peopleWrap">
									<div class="searchLabel">Туристы</div>
									<div class="searchInput">
										<div class="searchSelect">
											<div class="sInputWrap peopleWrap">
												<div id="peoplePick">
													<?if($arResult["FORM_PARAMS"]["count_child"]>0){?>
														<?=$arResult["FORM_PARAMS"]["count_people"]?> взр <?=$arResult["FORM_PARAMS"]["count_child"]?> реб
													<?}else{?>	
														<?=$arResult["FORM_PARAMS"]["count_people"]?> <?if($arResult["FORM_PARAMS"]["count_people"]==1){?>взрослый<?}else{?>взрослых<?}?>
													<?}?>
												</div>
												<div id="peoplePickBlock" class="peoplePickBlock">
													<div class="peoplePickBlockTitle">Туристы</div>
													<div class="adultPickBlock">
														<div class="adultMinus">-</div>
														<div class="adultBlock" data-count="<?=$arResult["FORM_PARAMS"]["count_people"]?>"><span><?=$arResult["FORM_PARAMS"]["count_people"]?></span>&nbsp;<?if($arResult["FORM_PARAMS"]["count_people"]==1){?>взрослый<?}else{?>взрослых<?}?></div>
														<div class="adultPlus">+</div>
													</div>
													<div class="childPickBlock">
														<?if($arResult["FORM_PARAMS"]["count_child"]>0){
															for($j=1;$j<=$arResult["FORM_PARAMS"]["count_child"];$j++)
															{	?>
																<div class="childPickBlockItem">
																	<div class="peoplePickBlockTitle">Возраст ребенка:</div>
																	<div class="searchSelect">
																		<div class="childMinus">-</div>
																		<span class="searchSelectText"><?=$arResult["FORM_PARAMS"]["child_year".$j]?></span>
																		<select name="child_year_pick" id="child_year_pick" class="sSelect">
																			<?for($i=0;$i<=17;$i++){?>
																			<option value="0" <?if($arResult["FORM_PARAMS"]["child_year".$j]==$i){?>selected<?}?>><?=$i?></option>
																			<?}?>
																		</select>
																	</div>
																</div>
																<?		
															}	
														}?>	
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
					</div>
					<div class="searchColLeft">
                        <div class="searchColInner"> 
                            <div id="regionWrap">
                                <div class="searchLabel">Курорт
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="reg_all" id="reg_all" checked="checked" name="reg_all" class="allcb" data-for="region" disabled />
                                        <label for="reg_all" ><span></span>Любой</label>
                                    </div>
                                </div>
								
                                <div id="regionBlock">
                                <?if (count($arResult["FORM_PARAMS"]["resortList"])>0) { ?>
                                    <?foreach ($arResult["FORM_PARAMS"]["resortList"] as $k=>$cname) {
										$checked = (is_array($arResult["FORM_PARAMS"]["resort"]) && in_array($cname["id"],$arResult["FORM_PARAMS"]["resort"]) || !is_array($arResult["FORM_PARAMS"]["resort"]) && $cname["id"]==$arResult["FORM_PARAMS"]["resort"]) ? true : false;
									?>
                                    <div id="region<?=$k?>W" class="sCbList">
                                        <label <?if($checked){?>class="activelbl"<?}?>>
                                            <input id="region<?=$k?>" type="checkbox" value="<?=$cname["id"]?>" 
											<?if ($checked){?>checked="checked"<?}?> name="region">
                                            <span><?=$cname["name"]?></span>
                                        </label>
                                    </div>    
                                    <?}
                                }?>	
                                </div>
                            </div>
							
							<div class="regTourBlock cbBlock">
								<input type="checkbox" value="hide_reg_tours" id="hide_reg_tours" name="hide_reg_tours" class="cb" >
								<label for="hide_reg_tours"><span></span>Скрыть туры на регулярных рейсах</label>
							</div>
							<?if($arResult["IS_ADMIN"]){?>
							<div id="saveparams">Получить ссылку</div>
							<?}?>
                        </div>
                    </div>
                    <div class="searchColCenter">
                        <div class="searchColInner">
                            <div id="priceWrap">
                                <div class="searchLabel">Цена (руб.)</div>
                                <div class="searchInputDbl searchDblLeft">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">От</span>
                                        <div class="sInputWrap">
                                            <input type="text" name="price_from" id="price_from" value="<?=$arResult["FORM_PARAMS"]["price_from"]?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="searchInputDbl">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">До</span>
                                        <div class="sInputWrap">
                                            <input type="text" name="price_till" id="price_till" value="<?=$arResult["FORM_PARAMS"]["price_till"]?>">
                                        </div>
                                    </div>  
                                </div>
                                 <div class="clear"></div>
                            </div>
							<div id="categoryWrap">
                                <div class="searchLabel">Категория от:
                                    
                                </div>
							
                                <div class="searchCbWrap cbBlock">
                                    <?if (count($arResult["FORM_PARAMS"]["hotelStarList"])>0) { $i=0; ?>
                                    <div class="cbLine">
                                        <?foreach ($arResult["FORM_PARAMS"]["hotelStarList"] as $k=>$cname) {  $i++;
											$checked = "";
											if(intval($cname)>=$arResult["FORM_PARAMS"]["stars"])
												$checked = "checked";
										?>
                                        <div class="cbLineItem" data-star="<?=$k?>">
                                           <input type="checkbox" value="<?=$k?>" id="stars_<?=$k?>" name="stars" class="sCbSearch" <?=$checked?>>
                                           <label for="stars_<?=$k?>" class="<? if (intval($k) >=401 && intval($k) <=404 ) {?>star<?}?>"><span></span><?=$cname?></label>
                                        </div>   
                                        <?if($i%4==0 && count($arResult["FORM_PARAMS"]["hotelStarList"])!=$i) {?><div class="clear"></div></div><div class="cbLine"><?}
                                        }?>
                                    </div>    
                                    <?}?>
                                    <div class="clear"></div>
                                </div>
                            </div>  
                            <div id="foodWrap">
                                <div class="searchLabel">Питание от:
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="food_all" id="food_all" <?if($arResult["FORM_PARAMS"]["meal"]==0){?>checked="checked"<?}?> name="food_all" class="allcb" data-for="food">
                                        <label for="food_all" ><span></span>Любое</label>
                                    </div>
                                </div>
                                <div class="searchCbWrap cbBlock">
                                    <?if (count($arResult["FORM_PARAMS"]["foodList"])>0) { $i=0; ?>
                                    <div class="cbLine">
                                        <?foreach ($arResult["FORM_PARAMS"]["foodList"] as $k=>$cname) {  $i++;
											$checked = "";
											if($arResult["FORM_PARAMS"]["meal"]>0 && $arResult["FORM_PARAMS"]["meal"]==$k)
												$checked = "checked";

										?>
                                        <div class="cbLineItem">
                                           <input type="checkbox" value="<?=$k?>" id="food_<?=$k?>" name="food" class="sCbSearch" <?=$checked ?>>
                                           <label for="food_<?=$k?>"><span></span><?=$cname?></label>
                                        </div>   
                                        <?if($i%4==0 && count($arResult["FORM_PARAMS"]["foodList"])!=$i ) {?><div class="clear"></div></div><div class="cbLine"><?}
                                        }?>
                                    </div>    
                                    <?}?>
                                    <div class="clear"></div>
                                </div>
                            </div> 
                        </div>
                    </div>
                    <div class="searchColRight">
                        <div class="searchColInner">
                            
                            <div id="hotelWrap">
                                <div class="searchLabel">Отель
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="all" id="hotel_all" checked="checked" name="hotel">
                                        <label for="hotel_all" ><span></span>Любой</label>
                                        <input type="checkbox" value="0" id="hotel_get" name="hotel">
                                        <label for="hotel_get"><span></span>Выбрать</label>
                                    </div>
                                </div>
								<div id="hotelByName">
									<input name="hotelName" placeholder="Наименование отеля" type="text" value="" />
								</div>
                                <div id="hotelBlock">
									<?if($arResult["FORM_PARAMS"]["hotel"]!="" && $arResult["FORM_PARAMS"]["hotel_name"]!=""){?>
									<div class="sCbList" id="hotel<?=$arResult["FORM_PARAMS"]["hotel"]?>W">
										<label class="activelbl">
											<input id="hotel<?=$arResult["FORM_PARAMS"]["hotel"]?>" type="checkbox" name="hotel" value="<?=$arResult["FORM_PARAMS"]["hotel"]?>" checked>
											<span><?=$arResult["FORM_PARAMS"]["hotel_name"]?></span>
										</label>
									</div>
									<?}
									elseif($arResult["FORM_PARAMS"]["hotels"] && count($arResult["FORM_PARAMS"]["hotel_names"])>0){
										foreach($arResult["FORM_PARAMS"]["hotels"] as $hid)
										{?>
											<div class="sCbList" id="hotel<?=$hid?>W">
												<label class="activelbl">
													<input id="hotel<?=$hid?>" type="checkbox" name="hotel" value="<?=$hid?>" checked>
													<span><?=$arResult["FORM_PARAMS"]["hotel_names"][$hid]?></span>
												</label>
											</div>
										<?}
									}?>
								</div>
                                <input type="submit" value="Искать туры" name="search_start" id="search_start">
                            </div>    
                        </div>
                    </div>
                    <div class="clear"></div>   
					<?}?>
                </form>
            </div>
        </div>
    </div>
    <div class="wrap-invis">   
        <div id="searchInfo" class="formWrap">
            <div class="formWrapInner">
                <div id="searchStatus"><span class="finish">Поиск туров завершен</span><span class="start">Идет поиск туров</span>, обработано <span id="searchProc">0</span>%</div>
                <div class="seacrhMet">
                    <div class="seacrhMetInner" style="width:0%"></div>
                </div>
                <div id="toursFound">Найдено туров: <span id="searchCount">0</span></div>
                <div id="toursPrice">Цена от: <span id="searchPrice">15 000</span> руб</div>
                
                <div class="clear"></div>
            </div>
        </div>
    </div>
	
    <div id="searchResult" class="wrap-white">
        <div id="searchResultTable" class="wrap-invis">
        </div>
    </div>
	<?//if ($USER->IsAdmin()){?>
	<div id="searchHelpFormWrap" class="wrap-white">
		
		<div class="searchHelpFormTop">Нужна помощь в подборе тура? <div class="searchHelpFormShow">Оставить заявку</div></div>
		
		<div id="searchHelpForm">
			<form name="helpForm" >
				<div class="searchHelpLine">
					<div class="searchLabel">Город вылета</div>
					<div class="searchInput">	
						<div class="searchSelect">
							<div class="sInputWrap">
								<input type="text" name="help_from" id="help_from">
							</div>
						</div>
					</div>
				</div>
				<div class="searchHelpCols">
					
					<div class="searchHelpLine">
						<div class="searchLabel">Страна</div>
						<div class="searchInput">	
							<div class="searchSelect">
								<div class="sInputWrap">
									<input type="text" name="help_country" id="help_country">
								</div>
							</div>
						</div>
					</div>
				
					<div class="searchHelpLine">
						<div class="searchLabel">Вылет</div>
						<div class="searchInput">	
							<div class="searchSelect">
								<div class="sInputWrap">
									<div id="help_from_till_block"></div>
									<input type="hidden" name="help_date_from" id="help_date_from" value="">
									<input type="hidden" name="help_date_till" id="help_date_till" value="">
									<input type="text" name="help_dates_datepicker" id="help_dates_datepicker">
								</div>
							</div>
						</div>
					</div>
				</div>	
				<div class="searchHelpCols">	
					<div class="searchHelpLine">
						<div class="searchLabel">Ночей</div>
						<div class="searchInput">	
							<div class="searchSelect">
								
								<div class="sInputWrap">
									<input type="hidden" name="help_days_from" id="help_days_from" value="<?=$arResult["FORM_PARAMS"]["nights_from"]?>">
									<input type="hidden" name="help_days_till" id="help_days_till" value="<?=$arResult["FORM_PARAMS"]["nights_till"]?>">
									<div id="help_nights_block" class="help_nights_block"><?=$arResult["FORM_PARAMS"]["nights_from"]?> - <?=$arResult["FORM_PARAMS"]["nights_till"]?></div>
									<div id="nightPickBlockHelp" class="nightPickBlock">
										<div class="nightPickBlockTitle">Ночей от:</div>
										<div class="pickTable">
											<div class="pickRow">
												<?for($i=1;$i<=7;$i++){?>
												<div id="pickCellHelp<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
												<?}?>
											</div>
											<div class="pickRow">
												<?for($i=8;$i<=14;$i++){?>
												<div id="pickCellHelp<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
												<?}?>
											</div>
											<div class="pickRow">
												<?for($i=15;$i<=21;$i++){?>
												<div id="pickCellHelp<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
												<?}?>
											</div>
											<div class="pickRow">
												<?for($i=22;$i<=28;$i++){?>
												<div id="pickCellHelp<?=$i?>" class="pickCell <?if ($arResult["FORM_PARAMS"]["nights_from"]==$i || $arResult["FORM_PARAMS"]["nights_till"]==$i){?>activePickCell<?if ($arResult["FORM_PARAMS"]["nights_till"]==$i){?> activeLastPickCell<?}} elseif($i > $arResult["FORM_PARAMS"]["nights_from"] && $i < $arResult["FORM_PARAMS"]["nights_till"]){?>hoverPickCell<?}?>"><?=$i?></div>
												<?}?>
											</div>
										</div>
									</div>	
									
								</div>
							</div>
						</div>
					</div>
					<div class="searchHelpLine">
						<div class="searchLabel">Туристы</div>
						<div class="searchInput">	
							<div class="searchSelect">
								<div class="sInputWrap">
									<input type="hidden" name="count_people_help"  id="count_people_help" value="<?=$arResult["FORM_PARAMS"]["count_people"]?>">
									<input type="hidden" name="count_child_help"   id="count_child_help"  value="<?=$arResult["FORM_PARAMS"]["count_child"]?>">
									<input type="hidden" name="child_year1_help"   id="child_year1_help"  value="<?=$arResult["FORM_PARAMS"]["child_year1"]?>">
									<input type="hidden" name="child_year2_help"   id="child_year2_help"  value="<?=$arResult["FORM_PARAMS"]["child_year2"]?>">
									<input type="hidden" name="child_year3_help"   id="child_year3_help"  value="<?=$arResult["FORM_PARAMS"]["child_year3"]?>">
									
									<div id="peoplePickHelp"><?=$arResult["FORM_PARAMS"]["count_people"]?> взрослых</div>
									<div id="peoplePickBlockHelp" class="peoplePickBlock">
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
				<div class="searchHelpLine">
					<div class="searchLabel">Ваше имя</div>
					<div class="searchInput">	
						<div class="searchSelect">
							<div class="sInputWrap">
								<input type="text" name="help_name" id="help_name">
							</div>
						</div>
					</div>
				</div>
				<div class="searchHelpLine">
					<div class="searchLabel">Телефон</div>
					<div class="searchInput">	
						<div class="searchSelect">
							<div class="sInputWrap">
								<input type="text" name="help_phone" id="help_phone" placeholder="+7 (___) ___-__-__">
							</div>
						</div>
					</div>
				</div>
				<div class="searchHelpLine">
					<div class="searchLabel">Ваши пожелания</div>
					<div class="searchInput">	
						<div class="searchTextArea">
							<textarea name="help_text"></textarea>
						</div>
					</div>
				</div>
				<input type="hidden" name="yaclient"      class="yaClient" value="" />
				<input type="hidden" name="yaclid"        class="yaClid" value="" />
				<input type="hidden" name="yautmsource"   class="yaUtmSource" value="" />
				<input type="hidden" name="yautmmedium"   class="yaUtmMedium" value="" />
				<input type="hidden" name="yautmcampaign" class="yaUtmCampaign" value="" />
				<input type="hidden" name="yautmcontent"  class="yaUtmContent" value="" />
				<input type="hidden" name="yautmterm"     class="yaUtmTerm" value="" />
				<div id="searchHelpSend">Отправить</div>
			</form>
			
			<div class="thanksHelpBlock">
				Спасибо, Ваша заявка успешно отправлена!<br />Наш оператор свяжется с Вами в ближайшее время и предложит подходящие варианты туров 
			</div>
			
		</div>
	</div>
	<?//}?>
	
	
	<div id="showMoreResultWrap">
		<div id="searchCount2">Найдено еще туров: <span class="bold"></span></div>
		<div id="showMoreResult" class="zakaza">Показать</div>
	</div>
    <div style="background:#fff; display:none">
        <div class="wrap-invis" >
                <div class="tourInfoWrap">
                    <div class="tourInfoLeft">
                        <?/*?>
                        <div class="tourInfoName">
                        </div>
                         <?*/?>
                        <div class="tourInfoPriceWrap">
                            <div class="tourInfoPriceBlock">
                                <div class="tourInfoPrice">
                                    <span class="tPriceBlack">Цена:</span> <span class="tPriceVal"></span> руб.
                                </div>
                            </div>
                            <div class="tourInfoButons">
                                <div class="tourInfoDop">
                                    <div class="tourInfoDopTtl">Доплаты:</div>
                                    
                                </div>
                                <div class="tourInfoOffline">
                                    Купить в офисе
                                </div>
                                <?/*?><div class="tourInfoOnline">
                                   Купить online
                                </div><?*/?>
                                <div class="clear"></div>
                            </div>
                        </div>
                      
                        
                        <div class="clear"></div>
                        <div class="tourHotelInfo">
                            <div class="tourHotelInfoLeft">
                              <?/*?>
                                <div class="tourInfoCaruselItem">
                                  
                                        <a href="http://hotels.sletat.ru/i/f/46139_0.jpg" class="fbox" rel="carusel">
                                            <img src="http://hotels.sletat.ru/i/im/46139_0_576_230_1.jpg" />
                                        </a> 
                                                                 
                                </div> 
                                <?*/?>   
                            </div>
                            <div class="tourHotelInfoRight">
                                <?/*?>
                                <div class="tName"></div>
                                <div class="gStars">
                                    <div class="gStar"></div>
                                    <div class="gStar"></div>
                                    <div class="gStar"></div>
                                </div>
                                <div class="tourSubInfoLeft">
                                    <div class="tourHotelRate">
                                        <div class="tourHotelRateTxt">Рейтинг отеля: <span class="rateVal"></span> </div>
                                        <div class="tourHotelRateMet">
                                            <div class="tourHotelRateMetInner" style="width:90%"></div>
                                        </div>
                                    </div>
                                    <a href="/egypt/hotels/7671_viva_sharm_hotel/" target="_blank" class="tourHotelLink">Подробнее об отеле</a>
                                </div>
                                <?*/?>
                                  <div class="tourInfoSubLeft">
                                    <div class="tourInfoSline people">
                                        Взрослых: <span class="tourAdults"></span>
                                    </div>
                                 
                                    <div class="tourInfoSline nights">
                                        Длительность: <span></span>
                                    </div>
                                    <div class="tourInfoSline tdates">
                                        Даты: <span></span>
                                    </div>
                                    
                                </div>
                                <div class="tourInfoSubRight">
                                    <div class="tourInfoSline tourHstop">
                                        Места в отеле: <span></span>
                                    </div>
                                    <div class="tourInfoSline tourFto">
                                        Билеты туда: <span></span>
                                    </div>
                                    <div class="tourInfoSline tourFbck">
                                        Билеты обратно: <span></span>
                                    </div>
                                </div>
                                <div class="tourSubInfoRight">
                                    <div class="tourInfoSline tourRoom">
                                       Размещение: <span></span>
                                    </div>
                                    <div class="tourInfoSline tourFood">
                                       Питание: <span></span>
                                    </div>
                                </div>
                                <div class="clear"></div>
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="tourButtons600">
                            <div class="tourInfoOffline">
                                Купить в офисе
                            </div>
                            <?/*?>
                            <div class="tourInfoOnline">
                               Купить online
                            </div>
                            <?*/?>
                        </div>
                        <div class="tourInfoFooter"></div>
                    </div>                
                    <div class="clear"></div>      
                </div>
        </div>
    </div>

    <div id="waitBlock">
        <div class="waitBlockWrap">
            <div id="waitBlockTtl">Секунду, информация обновляется</div>
            <img src="/upload/loading.gif">
        </div>    
    </div>
 </div>   
<link href="<?=$templateFolder. '/slick/slick.css'?>" rel="stylesheet">
<?/*?><link href="<?=$templateFolder. '/style.css?v1.004'?>" rel="stylesheet"><?*/?>
<script src="<?=$templateFolder?>/slick/slick.min.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAXSFLMaEfAkShI2OVF9cikj9uMQ5pk6ME"></script>
<?/*?><script src="<?=$templateFolder?>/script.js"></script><?*/?>
<div class="searchMainWrap">
    <div class="wrap-invis">
        <div class="formWrap searchFormWrap">
            <div class="formWrapInner">
                <form method="post" name="searchForm" action="<?=$templateFolder?>/ajax.php" autocomplete="off" data-start="<?=$arResult["AUTO_START"]?>">
                    <?echo bitrix_sessid_post();?>
                    <div class="searchColLeft">
                        <div class="searchColInner">
                            <div id="fromWrap">
                                <div class="searchLabel">Откуда</div>
                                <div class="searchSelect">
                                    <span class="searchSelectText">
                                        <?if (count($arResult["FORM_PARAMS"]["fromList"])>0) { ?>
                                            <?foreach ($arResult["FORM_PARAMS"]["fromList"] as $k=>$cname) {
                                               if ($k==$arResult["FORM_PARAMS"]["from"]){?><?=$cname?><? break;}
                                            }
                                        }?>	
                                    </span>
                                    <select id="from" class="sSelect">
                                    <?if (count($arResult["FORM_PARAMS"]["fromList"])>0) { ?>
                                        <?foreach ($arResult["FORM_PARAMS"]["fromList"] as $k=>$cname) {?>
                                            <option value="<?=$k?>" <?if ($k==$arResult["FORM_PARAMS"]["from"]){?>selected="selected"<?}?>><?=$cname?></option>
                                        <?}
                                    }?>	
                                    </select>
                                </div>
                            </div>
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

                            <div id="regionWrap">
                                <div class="searchLabel">Курорт
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="reg_all" id="reg_all" checked="checked" name="reg_all" class="allcb" data-for="region" disabled />
                                        <label for="reg_all" ><span></span>Любой</label>
                                    </div>
                                </div>
                                <div id="regionBlock">
                                <?if (count($arResult["FORM_PARAMS"]["resortList"])>0) {?>
                                    <?foreach ($arResult["FORM_PARAMS"]["resortList"] as $cname) {?>
                                    <div id="region<?=$k?>W" class="sCbList">
                                        <label >
                                            <input id="region<?=$k?>"  type="checkbox" value="<?=$cname["id"]?>" <?if ($cname["id"]==$arResult["FORM_PARAMS"]["resort"]){?>checked="checked"<?}?> name="region">
                                            <span><?=$cname["name"]?></span>
                                        </label>
                                    </div>    
                                    <?}
                                }?>	
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="searchColCenter">
                        <div class="searchColInner">
                            <div id="dateWrap">
                                <div class="searchLabel">Вылет</div>
                                <div class="searchInputDbl searchDblLeft">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">С</span>
                                        <div class="sInputWrap inputDate">
                                            <div class="dateIcon"></div>    
                                            <input type="date" name="date_from" class="date_from" id="date_from" value="<?=$arResult["FORM_PARAMS"]["date_from"]?>"> 
                                        </div>
                                    </div>
                                </div>
                                <div class="searchInputDbl">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">По</span>
                                        <div class="sInputWrap inputDate">
                                            <div class="dateIcon"></div>    
                                            <input type="date" name="date_till" class="date_till" id="date_till" value="<?=$arResult["FORM_PARAMS"]["date_till"]?>"> 
                                        </div>
                                    </div>  
                                </div>
                                 <div class="clear"></div>
                            </div>    
                        
                           
                            <div id="nightsWrap">
                                <div class="searchLabel">Ночей</div>
                                <div class="searchInputDbl searchDblLeft">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">От</span>
                                        <span class="searchSelectText"><?=$arResult["FORM_PARAMS"]["nights_from"]?></span>
                                        <select name="count_days_from" id="count_days_from" class="sSelect">
                                            <?for($i=1;$i<=29;$i++) {?>
                                                <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["nights_from"]){?>selected="selected"<?}?> ><?=$i?></option>
                                            <?}?>
                                        </select>
                                    </div>
                                </div>
                                <div class="searchInputDbl">
                                    <div class="searchSelect">
                                        <span class="searchSelectPref">До</span>
                                        <span class="searchSelectText"><?=$arResult["FORM_PARAMS"]["nights_till"]?></span>
                                        <select name="count_days_till" id="count_days_till" class="sSelect">
                                            <?for($i=1;$i<=24;$i++) {?>
                                                <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["nights_till"]){?>selected="selected"<?}?> ><?=$i?></option>
                                            <?}?>
                                        </select>
                                    </div>    
                                </div>
                                 <div class="clear"></div>
                            </div>
                            <div id="adultsWrap" class="searchInputDbl searchDblLeft">
                                <div class="searchLabel">Взрослых</div>
                                <div class="searchSelect">
                                    <span class="searchSelectText"><?=$arResult["FORM_PARAMS"]["count_people"]?></span>
                                    <select name="count_people" id="count_people" class="sSelect">
                                        <?for($i=1;$i<=4;$i++) {?>
                                            <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["count_people"]){?>selected="selected"<?}?>><?=$i?></option>
                                        <?}?>
                                    </select>
                                </div>
                            </div>
                            <div id="childrenWrap" class="searchInputDbl">
                                <div class="searchLabel">Детей</div>
                                <div class="searchSelect">
                                    <span class="searchSelectText"><?=$arResult["FORM_PARAMS"]["count_child"]?></span>
                                    <select name="count_child" id="count_child" class="sSelect">
                                        <?for($i=0;$i<=3;$i++) {?>
                                            <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["count_child"]){?>selected="selected"<?}?>><?=$i?></option>
                                        <?}?>
                                    </select>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div id="chidrenSelectWrap">
                                <div class="searchLabel">Возраст детей</div>
                                <div class="searchInputTpl searchTplLeft">
                                    <div class="searchSelect <?if ($arResult["FORM_PARAMS"]["child_year1"]==""){?>disabled<?}?>">
                                        
                                        <span class="searchSelectText"><?if ($arResult["FORM_PARAMS"]["child_year1"]!=""){?><?=$arResult["FORM_PARAMS"]["child_year1"]?><?} else {?>нет<?}?></span>
                                        <select name="child_year1" id="child_year1" <?if ($arResult["FORM_PARAMS"]["child_year1"]==""){?>disabled<?}?> class="sSelect">
                                            <?for($i=0;$i<=18;$i++) {?>
                                                <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["child_year1"]){?>selected="selected"<?}?>><?=$i?></option>
                                            <?}?>
                                        </select>
                                    </div>
                                </div>
                                <div class="searchInputTpl searchTplMid">
                                    <div class="searchSelect <?if ($arResult["FORM_PARAMS"]["child_year2"]==""){?>disabled<?}?>">
                                        <span class="searchSelectText"><?if ($arResult["FORM_PARAMS"]["child_year2"]!=""){?><?=$arResult["FORM_PARAMS"]["child_year2"]?><?} else {?>нет<?}?></span>
                                        <select name="child_year2" id="child_year2" <?if ($arResult["FORM_PARAMS"]["child_year2"]==""){?>disabled<?}?> class="sSelect">
                                            <?for($i=0;$i<=18;$i++) {?>
                                                <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["child_year2"]){?>selected="selected"<?}?>><?=$i?></option>
                                            <?}?>
                                        </select>
                                    </div>    
                                </div>
                                <div class="searchInputTpl searchTplRight">
                                    <div class="searchSelect <?if ($arResult["FORM_PARAMS"]["child_year3"]==""){?>disabled<?}?>">
                                        <span class="searchSelectText"><?if ($arResult["FORM_PARAMS"]["child_year3"]!=""){?><?=$arResult["FORM_PARAMS"]["child_year3"]?><?} else {?>нет<?}?></span>
                                        <select name="child_year3" id="child_year3" <?if ($arResult["FORM_PARAMS"]["child_year3"]==""){?>disabled<?}?> class="sSelect">
                                            <?for($i=0;$i<=18;$i++) {?>
                                                <option value="<?=$i?>" <?if ($i==$arResult["FORM_PARAMS"]["child_year3"]){?>selected="selected"<?}?>><?=$i?></option>
                                            <?}?>
                                        </select>
                                    </div>    
                                </div>
                                <div class="clear"></div>
                            </div>
                            
                            <div id="priceWrap">
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
                        </div>
                    </div>
                    <div class="searchColRight">
                        <div class="searchColInner">
                            <div id="categoryWrap">
                                <div class="searchLabel">Категория от:
                                    
                                </div>
                                <div class="searchCbWrap cbBlock">
                                    <?if (count($arResult["FORM_PARAMS"]["hotelStarList"])>0) { $i=0; ?>
                                    <div class="cbLine">
                                        <?foreach ($arResult["FORM_PARAMS"]["hotelStarList"] as $k=>$cname) {  $i++;?>
                                        <div class="cbLineItem" data-star="<?=$k?>">
                                           <input type="checkbox" value="<?=$k?>" id="stars_<?=$k?>" name="stars" class="sCbSearch" <?if($k==2){?>checked<?}?>>
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
                            <div id="hotelWrap">
                                <div class="searchLabel bigMargin">Отель
                                    <div class="dopCb cbBlock">
                                        <input type="checkbox" value="all" id="hotel_all" checked="checked" name="hotel">
                                        <label for="hotel_all" ><span></span>Любой</label>
                                        <input type="checkbox" value="0" id="hotel_get" name="hotel">
                                        <label for="hotel_get"><span></span>Выбрать</label>
                                    </div>
                                </div>
								<div id="hotelByName">
									<input name="hotelName" placeholder="Наименование отеля" type="text" />
								</div>
                                <div id="hotelBlock"></div>
                                <input type="submit" value="Искать туры" name="search_start" id="search_start">
                            </div>    
                        </div>
                    </div>
                    <div class="clear"></div>   
                    
                    <div class="clear"></div>                 
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
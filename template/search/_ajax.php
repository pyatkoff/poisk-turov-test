<?
use Bitrix\Main\Loader;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/search.php');
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
require_once($_SERVER["DOCUMENT_ROOT"] . '/site_conf.php');
$json=array(); 
if (isset($_REQUEST["mode"]))
{	
    $mode = $_REQUEST["mode"]; 
	switch ($mode)
	{
		case "resort":
			$country = intval($_REQUEST["country"]);
            if ($country > 0)
			{
                $regionList= TvApi::GetResortList(array("CID"=>$country));
                
				$json["resortList"] =  $regionList;
            }
			break;
		case "hotel":
            $country = intval($_REQUEST["country"]);
            if ($country > 0)
			{
                $filter = array("CID"=>$country);
                if (is_array($_REQUEST["resort"]))
                    $filter["TID"] = $_REQUEST["resort"];
				if (trim($_REQUEST["name"])!="")
					$filter["%NAME"] = htmlspecialchars(trim($_REQUEST["name"]));	
				$stars = $_REQUEST["stars"];
				if (is_array($stars) && count($stars)>0 && count($stars)<4)
					$filter["STARS"] = $stars;
				
                $hotelList= TvApi::GetHotelList($filter);
                
                $json["hotelList"] =  (count($hotelList)<=100) ? array($hotelList) : array_chunk($hotelList,100);
               
            }
			break; 
		case "savedata":	
			
		break;
	}		
}
elseif (
		(isset($_REQUEST["search_update"]) || isset($_REQUEST["search_result"])) && isset($_REQUEST["reqid"]) ||
        isset($_REQUEST["search_create"]) && isset($_REQUEST["country"]) && isset($_REQUEST["city"]) || 
        isset($_REQUEST["get_hotel_tours"]) && isset($_REQUEST["req"]) && isset($_REQUEST["hid"]) || 
        isset($_REQUEST["get_hotel_info"]) && isset($_REQUEST["req"]) && isset($_REQUEST["hid"]) ||
        isset($_REQUEST["get_tour_info"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["act_tour_info"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["update_detail"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["officeorder"])   && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"])) ||
		isset($_REQUEST["onlineorder"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"]) && isset($_REQUEST["tourists"]))  || 
		isset($_REQUEST["helpform"]) && isset($_REQUEST["help_name"]) && isset($_REQUEST["help_phone"])
       

	)
{    
   

    /***************************************/
    $adults         =   1;
    $children       =   0;
	$nightsMin      =   3;
    $nightsMax      =   11;
	$dateFrom    	=   false;
	$dateTo   	 	=   false;
	$resort			=   false;
	$hotels			=   false;
	$pricefrom		=   false;
	$priceto		=	false;
	$stars			= 	false;
	$meal			= 	false;
    $priceMin       =   0;
    $priceMax       =   0;
    $requestId      =   0;
    $percent        =   0; 
    $percentFound   =   0;
    $tours          =   0; 
    $toursdb        =   0; 
    $page           =   1;
	$requestArr     =   array();
    $result         =   array();
    $error          =   false;
    $tcreate        =   false;
    $tcreateInBase  =   false;
    $tupdate        =   false;
    $tresult        =   false;
    $update         =   false;
    $strArr = array("Stop"=>"нет", "Available" =>"есть", "Request" => "под запрос",  "Unknown" => "");
    $strClass = array("нет"=>"procR", "есть"=>"procG", "под запрос"=>"procY",  ""=>"");
    $time = microtime(true);
    /***************************************/
    if($_REQUEST["helpform"])//помощь в подборе тура
    {  
	
		\Bitrix\Main\Loader::includeModule('iblock');	
		$name  		=   trim(htmlspecialchars($_REQUEST["help_name"]));
        $phone      =   trim(htmlspecialchars($_REQUEST["help_phone"]));
		$from       =   htmlspecialchars($_REQUEST["help_from"]);
		$country    =   htmlspecialchars($_REQUEST["help_country"]);
		$text   	=   htmlspecialchars($_REQUEST["help_text"]);
		$nightsFrom =   htmlspecialchars($_REQUEST["help_days_from"]);
		$nightsTill =   htmlspecialchars($_REQUEST["help_days_till"]);
		$dates      =   htmlspecialchars($_REQUEST["help_dates_datepicker"]);
		$people  	=   htmlspecialchars($_REQUEST["count_people_help"]);
		$child  	=   htmlspecialchars($_REQUEST["count_child_help"]);
		$childYears =   array(
			1=> htmlspecialchars($_REQUEST["child_year1_help"]),
			2=> htmlspecialchars($_REQUEST["child_year2_help"]),
			3=> htmlspecialchars($_REQUEST["child_year3_help"])
		);
		$text   =    trim(htmlspecialchars($_REQUEST["help_text"]));
		$yaclient   =   (htmlspecialchars($_REQUEST["yaclient"])!="null") ? htmlspecialchars($_REQUEST["yaclient"]) : "";
		$yaclid     =   (htmlspecialchars($_REQUEST["yaclid"])!="null") ? htmlspecialchars($_REQUEST["yaclid"]) : "";
		$yautmsource    =   (htmlspecialchars($_REQUEST["yautmsource"])!="null") ? htmlspecialchars($_REQUEST["yautmsource"]) : "";
		$yautmmedium    =   (htmlspecialchars($_REQUEST["yautmmedium"])!="null") ? htmlspecialchars($_REQUEST["yautmmedium"]) : "";
		$yautmcampaign  =   (htmlspecialchars($_REQUEST["yautmcampaign"])!="null") ? htmlspecialchars($_REQUEST["yautmcampaign"]) : "";
		$yautmcontent   =   (htmlspecialchars($_REQUEST["yautmcontent"])!="null") ? htmlspecialchars($_REQUEST["yautmcontent"]) : "";
		$yautmterm      =   (htmlspecialchars($_REQUEST["yautmterm"])!="null") ? htmlspecialchars($_REQUEST["yautmterm"]) : "";

		$json["child"] = $child ;
		$json["child_years"] = $childYears ;
		$peopleStr = "";
		if ($people)
			$peopleStr = "Взрослых: ".$people;
		if ($child)
		{
			$peopleStr .= "; Детей: ".$child;
			if ($child>0)
			{
				$childYearsArr = array();
				for($i=1;$i<=$child;$i++)
					$childYearsArr[]=$childYears[$i];
				$peopleStr .= "(".implode(",",$childYearsArr).")";	
			}		
		}	
		
		if($params["IS_REG"])//для регионов только отправка письма
		{
			$datraArr = array();
			$datraArr[]   = "Имя: ".$name ;
			$datraArr[]   = "Телефон: ".$phone;
			$datraArr[]   = "Город вылета: ".$from; 
			$datraArr[]   = "Страна: ".$country;
			$datraArr[]   = "Даты вылета: ". $dates ;
			$datraArr[]   = "Туристы: ".$peopleStr;
			$datraArr[]   = "Количество ночей: ". $nightsFrom."-".$nightsTill ;
			$datraArr[]   = "Комметарий: ". $text;
			$arFields = array(
				"NAME" => "Заявка от ".date("d.m.Y H:i:s"),
				"IBLOCK_ID" => 10,
				"IBLOCK_SECTION_ID" => 19,
				"PROPERTY_VALUES" => array(
					"REGION"    => $params["REG_PROP_ID"],
				),
				"PREVIEW_TEXT"  => implode("<br>",$datraArr),
				"PREVIEW_TEXT_TYPE" => 'html'
			);
			\CModule::IncludeModule("iblock");
			$ob = new CIblockElement();
			if($ID = $ob->Add($arFields))
			{
				$json["send"] = 1;
				\Bitrix\Main\Mail\Event::send(array( 
					"EVENT_NAME" => "REGION_HELP_ORDER",
					"LID" => $params["SITE_ID"],
					"C_FIELDS" => array(
						"EMAIL" => $params["ORDER_EMAIL"],
						"TEXT" => implode("<br>",$datraArr)
					),
				)); 
	
				$revenue = "";
				$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_help_form","");
				if ($proc!="")
				{
					if (strpos($proc,"%")!==false)
					{
						
					}
					else
					{
						$revenue = $proc;
					}
				}

				
				if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
				{
					$json["data"] = array(
						"commerce" => 1,
						"name"	   => "Заявки в регионах - заявка из формы помощи в поиске",
						"id"	   => $ID,
						"price"	   => $revenue,
						"category" => $country,
						"revenue"  => $revenue
					);
					$_SESSION["COMMERCE_SEND"] = true;

					$pretour = CSiteParams::getPretourData("ECOM_HELPFORM",$tourinfo["price"],$reqData["ADULTS"]);
					if($pretour)
					{
						$json["data"]['p1'] = $pretour['P1'];
						$json["data"]['p2'] = $pretour['P2'];
					}
				}
				else
					$json["data"] = array(
						"commerce" => 0,
					);
				
			}

		}
		else
		{
			$comment = array();
			$comment[]   = "Имя: ".$name ;
			$comment[]   = "Телефон: ".$phone;
			$comment[]   = "Город вылета: ".$from; 
			$comment[]   = "Страна: ".$country;
			$comment[] 	 = "Даты вылета: ". $dates ;
			$comment[] 	 = "Туристы: ".$peopleStr;
			$comment[]   = "Количество ночей: ". $nightsFrom."-".$nightsTill ;
			
			
			if ($text!="")
				$comment[]="Комментарий пользователя: ".$text;
			
			$date = date("d.m.Y H:i:s");
			$props = array(
				"DATE"	   		=> $date,	
				"NAME"     		=> $name,
				"PHONE"    		=> TelegramApi::cleanPhone($phone),
				"COMMENTS" 		=> implode("; ",$comment),
				"DEPARTURE"		=> $from ,
				"PEOPLE"	    => $peopleStr,
				"COUNTRY"		=> $country,
				"YA_CLIENT"		=> $yaclient,
				"YA_CLID"		=> $yaclid ,
				"YA_UTM_SOURCE"	=> $yautmsource,
				"YA_UTM_MEDIUM"	=> $yautmmedium,
				"YA_UTM_CAMPAIGN"=> $yautmcampaign,
				"YA_UTM_CONTENT"=> $yautmcontent,
				"YA_UTM_TERM"	=> $yautmterm,
				"IS_ANYTOUR_ONLINE" => CSiteParams::$isAnytourOnline
			);
			
			$props["STATUS"] = 9;//в очереди
			
			$arLoadProductArray = Array(
				"IBLOCK_ID"        => 4,
				"IBLOCK_SECTION_ID"=> 12,
				"PROPERTY_VALUES"  => $props,
				"NAME"             => "Заявка от ".$props["DATE"],
				"ACTIVE"           => "Y",  
			);
			$el = new CIblockElement();
			$IDH = $el->Add($arLoadProductArray);
			
			$json["send"] = 1;
			$revenue = "";
			$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_help_form","");
			if ($proc!="")
			{
				if (strpos($proc,"%")!==false)
				{
					
				}
				else
				{
					$revenue = $proc;
				}
			}

			
			if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
			{
				$json["data"] = array(
					"commerce" => 1,
					"name"	   => "Заявка из формы помощи в поиске",
					"id"	   => $IDH,
					"price"	   => $revenue,
					"category" => $country,
					"revenue"  => $revenue
				);
				$_SESSION["COMMERCE_SEND"] = true;

				$pretour = CSiteParams::getPretourData("ECOM_HELPFORM",$tourinfo["price"],$reqData["ADULTS"]);
				if($pretour)
				{
					$json["data"]['p1'] = $pretour['P1'];
					$json["data"]['p2'] = $pretour['P2'];
				}
			}
			else
				$json["data"] = array(
					"commerce" => 0,
				);
		}	
		
	}
	elseif($_REQUEST["onlineorder"])//заявка на тур
    {
		$data       =   array('online');
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$name  		=   htmlspecialchars($_REQUEST["name"]);
        $phone      =   htmlspecialchars($_REQUEST["phone"]);
        $email      =   htmlspecialchars($_REQUEST["email"]);
		$comment    =   htmlspecialchars($_REQUEST["text"]);
		$passNum    =   htmlspecialchars($_REQUEST["passnum"]);
		$passWho    =   htmlspecialchars($_REQUEST["passwho"]);
		$passDate   =   htmlspecialchars($_REQUEST["passdate"]["date"]);
		$yaclient   =   (htmlspecialchars($_REQUEST["yaclient"])!="null") ? htmlspecialchars($_REQUEST["yaclient"]) : "";
		$yaclid     =   (htmlspecialchars($_REQUEST["yaclid"])!="undefined") ? htmlspecialchars($_REQUEST["yaclid"]) : "";
		$yautmsource    =   (htmlspecialchars($_REQUEST["yautmsource"])!="null") ? htmlspecialchars($_REQUEST["yautmsource"]) : "";
		$yautmmedium    =   (htmlspecialchars($_REQUEST["yautmmedium"])!="null") ? htmlspecialchars($_REQUEST["yautmmedium"]) : "";
		$yautmcampaign  =   (htmlspecialchars($_REQUEST["yautmcampaign"])!="null") ? htmlspecialchars($_REQUEST["yautmcampaign"]) : "";
		$yautmcontent   =   (htmlspecialchars($_REQUEST["yautmcontent"])!="null") ? htmlspecialchars($_REQUEST["yautmcontent"]) : "";
		$yautmterm      =   (htmlspecialchars($_REQUEST["yautmterm"])!="null") ? htmlspecialchars($_REQUEST["yautmterm"]) : "";
		if(is_array($passDate) && $passDate["date"])
			$passDate = $passDate["date"];

		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid, true);
		if(count($tourinfo)>0)
		{
			$reqData = \RequestDataTable::getByID($requestId);
			$people = "";
			if ($reqData["ADULTS"])
				$people = "Взрослых: ".$reqData["ADULTS"];
			if ($reqData["CHILD"])
			{
				$people .= "; Детей: ".$reqData["CHILD"];
				if ($reqData["CHILD"]>0)
				{
					$childYears = array();
					for($i=1;$i<=$reqData["CHILD"];$i++)
						$childYears[]=$reqData["CHILD_YEAR_".$i];
					$people .= "(".implode(",",$childYears).")";	
				}		
			}	
			
			
			if($params["IS_REG"])//для регионов только отправка письма
			{
				
				$datraArr = array();
				$datraArr[]= "Имя: ". $name;
				$datraArr[]= "Email: ". $email;
				$datraArr[]= "Телефон: ". $phone;
				$datraArr[]= "Страна: ". $tourinfo["country"];
				$datraArr[]= "Дата: ". $tourinfo["date_from"];
				$datraArr[]= "Вылет из: ". $tourinfo["departure"];
				$datraArr[]= "Стоимость: ". $tourinfo["price"];
				$datraArr[]= "Топливный сбор: ". $tourinfo["fuel"];
				$datraArr[]= "Оператор: ". $tourinfo["operator"];
				$datraArr[]= "Ссылка на бронирование: ". $tourinfo["operator_link"];
				
				$datraArr[]= "Количество туристов: ". $people;
				$datraArr[]= "Курорт: ". $tourinfo["resort"];
				$datraArr[]= "Отель: ". $tourinfo["hotel"];
					
				$datraArr[]= "Ночей: ".  $tourinfo["nights"];
				$datraArr[]= "Размещение: ".  $tourinfo["placement"];
				$datraArr[]= "Тип питания: ".  $tourinfo["meal_name"];
				$datraArr[]= "Тип номера: ".   $tourinfo["room"];
				$datraArr[]= "Паспорт заказчика: серия/номер ". $passNum .", дата выдачи ".$passDate.", выдан ".$passWho;
				$datraArr[]= " ";
				$datraArr[]= "Паспортные данные туристов:";
				$tourists  = $_REQUEST["tourists"];
					
				$touristIDs = array();
				$num = 1;
				foreach($tourists as $tourist)
				{	
					$datraArr[]= "Турист №".$num;
					$datraArr[]= "Фамилия: ". $tourist["sname"];
					$datraArr[]= "Имя: ". $tourist["name"];
					$datraArr[]= "Пол: ". ($tourist["gender"]=="F" ? "Ж" : "М");
					$datraArr[]= "Паспорт серия: ".$tourist["pser"];
					$datraArr[]= "Паспорт номер: ".$tourist["pnom"];
					$datraArr[]= "Паспорт дата выдачи: ".$tourist["pasfrom"]["date"];
					$datraArr[]= "Паспорт дата окончания: ". $tourist["pastill"]["date"];
					$datraArr[]= "Паспорт выдан: ".$tourist["pout"];
					$datraArr[]= "Дата рождения: ".$tourist["bdate"]["date"];
					$datraArr[]= "Национальность: ".$tourist["nat"];
					$datraArr[]= " ";
					$num++;
				}
				$datraArr[]= " ";
				$datraArr[]= "Комметарий: ". $comment;
				$arFields = array(
					"NAME" => "Заявка от ".date("d.m.Y H:i:s"),
					"IBLOCK_ID" => 10,
					"IBLOCK_SECTION_ID" => 18,
					"PROPERTY_VALUES" => array(
						"REGION"    => $params["REG_PROP_ID"],
					),
					"PREVIEW_TEXT"  => implode("<br>",$datraArr),
					"PREVIEW_TEXT_TYPE" => 'html'
				);
				\CModule::IncludeModule("iblock");
				$ob = new CIblockElement();
				if($ID = $ob->Add($arFields))
				{
					$json["send"] = 1;
					\Bitrix\Main\Mail\Event::send(array(
						"EVENT_NAME" => "REGION_ONLINE_ORDER",
						"LID" => $params["SITE_ID"],
						"C_FIELDS" => array(
							"EMAIL" => $params["ORDER_EMAIL"],
							"TEXT" => implode("<br>",$datraArr)
						),
					)); 
					
					$revenue = "";
					$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_search_online","");
					if ($proc!="")
					{
						if (strpos($proc,"%")!==false)
						{
							$proc = str_replace("%","",$proc);
							$revenue = intval($tourinfo["price"] * $proc/100);
						}
						else
						{
							$revenue = $proc;
						}
					}

					
					if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
					{
						$json["data"] = array(
							"commerce" => 1,
							"name"	   => "Заявки в регионах - покупка онлайн",
							"id"	   => $ID,
							"price"	   => $tourinfo["price"],
							"category" => $tourinfo["country"],
							"revenue"  => $revenue
						);
						$_SESSION["COMMERCE_SEND"] = true;

						$pretour = CSiteParams::getPretourData("ECOM_ORDERTOUR_ONLINE",$tourinfo["price"],$reqData["ADULTS"]);
						if($pretour)
						{
							$json["data"]['p1'] = $pretour['P1'];
							$json["data"]['p2'] = $pretour['P2'];
						}
					}
					else
						$json["data"] = array(
							"commerce" => 0,
						);
				}	
			}
			else
			{	
				$date = date("d.m.Y H:i:s");
				$props = array(
					"STATUS"   		=> 8,
					"DATE"	   		=> $date,	
					"NAME"     		=> $name,
					"EMAIL"    		=> $email,
					"PHONE"    		=> ($phone!="") ? TelegramApi::cleanPhone($phone) : "",
					"COMMENTS" 		=> $comment,
					"PRICE"	   		=> $tourinfo["price"],
					"FUELCHARGE"	=> $tourinfo["fuel"],
					"OPERATOR"		=> $tourinfo["operator"],
					"DEPARTURE"		=> $tourinfo["departure"],
					"PEOPLE"	    => $people,
					"COUNTRY"		=> $tourinfo["country"],
					"REGION"		=> $tourinfo["resort"],
					"HOTEL"			=> $tourinfo["hotel"],
					"FLYDATE"		=> $tourinfo["date_from"],
					"NIGHTS"		=> $tourinfo["nights"],
					"PLACEMENT"		=> $tourinfo["placement"],
					"MEAL"			=> $tourinfo["meal_name"],
					"ROOM"			=> $tourinfo["room"],
					"OPERATORLINK"	=> $tourinfo["operator_link"],
					"PASSPORT_SER_NUM"	=> $passNum,
					"PASSPORT_WHO"		=> $passWho,
					"PASSPORT_DATE" 	=> $passDate,
					"YA_CLIENT"		=> $yaclient,
					"YA_CLID"		=> $yaclid ,
					"YA_UTM_SOURCE"	=> $yautmsource,
					"YA_UTM_MEDIUM"	=> $yautmmedium,
					"YA_UTM_CAMPAIGN"=> $yautmcampaign,
					"YA_UTM_CONTENT"=> $yautmcontent,
					"YA_UTM_TERM"	=> $yautmterm,
					"IS_ANYTOUR_ONLINE" => CSiteParams::$isAnytourOnline
					
				);
				$props["STATUS"] = 8;//новая
				
				$arLoadProductArray = Array(
					"IBLOCK_ID"        => 4,
					"IBLOCK_SECTION_ID"=> 9,
					"PROPERTY_VALUES"  => $props,
					"NAME"             => "Заявка от ".$props["DATE"],
					"ACTIVE"           => "Y",  
				);
				$el = new CIblockElement();
				$DATA_ID = $el->Add($arLoadProductArray);
				if($DATA_ID )
				{
					$tourists  = $_REQUEST["tourists"];
					
					$touristIDs = array();
					foreach($tourists as $tourist)
					{
						$propsT = array(
							"SURNAME"			=> $tourist["sname"],
							"NAME"				=> $tourist["name"],
							"GENDER"			=> ($tourist["gender"]=="F") ? "female" : "male",
							"PASSPORTSERIES"	=> $tourist["pser"],
							"PASSPORTNUMBER"	=> $tourist["pnom"],
							"PASSPORTISSUEDATE"	=> $tourist["pasfrom"]["date"],
							"PASSPORTENDDATE"	=> $tourist["pastill"]["date"],
							"PASSPORTISSUEDBY"	=> $tourist["pout"],
							"PASSPORTTYPE"		=> 0,
							"BIRTHDATE"			=> $tourist["bdate"]["date"],
							"BIRTHCOUNTRY"		=> "",
							"NATIONALITY"		=> $tourist["nat"],
						);
						
						$arLoadT = Array(
							"IBLOCK_ID"         => CSiteParams::$touristIB,
							"PROPERTY_VALUES"   => $propsT,
							"NAME"              => $propsT["NAME"]." ".$props["SURNAME"],
							"ACTIVE"            => "Y",  
						);
						$IDT = $el->Add($arLoadT);
						if($IDT)
							$touristIDs[]=$IDT;
					}
					CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("TOURIST"=>$touristIDs)); 
					
					
					$hotelIDs = array();
					
					$propsH = array(
						"NAME"		=> $tourinfo["hotel"], 
						"COUNTRY"	=> $tourinfo["country"],
						"REGION"	=> $tourinfo["resort"],
						"STARTDATE"	=> $tourinfo["date_from"],
						"NIGHTS"	=> $tourinfo["nights"],
						"ROOM"		=> $tourinfo["room"],
						"MEAL"		=> $tourinfo["meal_name"],
						"PLACEMENT"	=> $tourinfo["placement"]
					);
					
					$arLoadH = Array(
						"IBLOCK_ID"         => CSiteParams::$hotelsIB,
						"PROPERTY_VALUES"   => $propsH,
						"NAME"              => $propsH["NAME"],
						"ACTIVE"            => "Y",  
					);
					$IDH = $el->Add($arLoadH);
					if($IDH)
						$hotelIDs[]=$IDH;
					
					CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("HOTELS"=>$hotelIDs)); 
				
					
					//TelegramApi::exportNewClaim($DATA_ID);
					
					
					$flights = array();
					
					$arSelectFl = Array("ID","IBLOCK_ID");
					$arFilterFl = Array("IBLOCK_ID"=> CSiteParams::$transportIB, "ACTIVE"=>"Y","PROPERTY_REQID"	=>$requestId,"PROPERTY_HID"	=>$hid, "PROPERTY_TID" =>$tid);
					$flightDb = CIBlockElement::GetList(Array(), $arFilterFl, false, false, $arSelectFl);
					while($flight = $flightDb->fetch())
					{
						$flights[]=$flight["ID"];
					}	
					
					CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("FLIGHTS"=>$flights)); 
					
					CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("STATUS"=>9)); 
					
					$json["send"] = 1;
					$revenue = "";
					$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_search_online","");
					if ($proc!="")
					{
						if (strpos($proc,"%")!==false)
						{
							$proc = str_replace("%","",$proc);
							$revenue = intval($tourinfo["price"] * $proc/100);
						}
						else
						{
							$revenue = $proc;
						}
					}

					
					if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
					{
						$json["data"] = array(
							"commerce" => 1,
							"name"	   => "Покупка онлайн",
							"id"	   => $DATA_ID,
							"price"	   => $tourinfo["price"],
							"category" => $tourinfo["country"],
							"revenue"  => $revenue
						);
						$_SESSION["COMMERCE_SEND"] = true;

						$pretour = CSiteParams::getPretourData("ECOM_ORDERTOUR_ONLINE",$tourinfo["price"],$reqData["ADULTS"]);
						if($pretour)
						{
							$json["data"]['p1'] = $pretour['P1'];
							$json["data"]['p2'] = $pretour['P2'];
						}

					}
					else
						$json["data"] = array(
							"commerce" => 0,
						);
				}
			}
		}
        //$json = $data;
       
    }
	elseif($_REQUEST["officeorder"])//заявка на тур
    {
		$data       =   array();
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$name  		=   htmlspecialchars($_REQUEST["name"]);
        $phone      =   htmlspecialchars($_REQUEST["phone"]);
        $email      =   htmlspecialchars($_REQUEST["email"]);
		$comment    =   htmlspecialchars($_REQUEST["text"]);
		
		$yaclient   =   (htmlspecialchars($_REQUEST["yaclient"])!="null") ? htmlspecialchars($_REQUEST["yaclient"]) : "";
		$yaclid     =   (htmlspecialchars($_REQUEST["yaclid"])!="undefined") ? htmlspecialchars($_REQUEST["yaclid"]) : "";
		$yautmsource    =   (htmlspecialchars($_REQUEST["yautmsource"])!="null") ? htmlspecialchars($_REQUEST["yautmsource"]) : "";
		$yautmmedium    =   (htmlspecialchars($_REQUEST["yautmmedium"])!="null") ? htmlspecialchars($_REQUEST["yautmmedium"]) : "";
		$yautmcampaign  =   (htmlspecialchars($_REQUEST["yautmcampaign"])!="null") ? htmlspecialchars($_REQUEST["yautmcampaign"]) : "";
		$yautmcontent   =   (htmlspecialchars($_REQUEST["yautmcontent"])!="null") ? htmlspecialchars($_REQUEST["yautmcontent"]) : "";
		$yautmterm      =   (htmlspecialchars($_REQUEST["yautmterm"])!="null") ? htmlspecialchars($_REQUEST["yautmterm"]) : "";
		
		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid, true);
		if(count($tourinfo)>0)
		{
			$reqData = \RequestDataTable::getByID($requestId);
			$people = "";
				if ($reqData["ADULTS"])
					$people = "Взрослых: ".$reqData["ADULTS"];
				if ($reqData["CHILD"])
				{
					$people .= "; Детей: ".$reqData["CHILD"];
					if ($reqData["CHILD"]>0)
					{
						$childYears = array();
						for($i=1;$i<=$reqData["CHILD"];$i++)
							$childYears[]=$reqData["CHILD_YEAR_".$i];
						$people .= "(".implode(",",$childYears).")";	
					}		
				}	
			
			
			if($params["IS_REG"])//для регионов только отправка письма
			{	
				$datraArr = array();
				$datraArr[]= "Имя: ". $name;
				$datraArr[]= "Email: ". $email;
				$datraArr[]= "Телефон: ". $phone;
				$datraArr[]= "Страна: ". $tourinfo["country"];
				$datraArr[]= "Дата: ". $tourinfo["date_from"];
				$datraArr[]= "Вылет из: ". $tourinfo["departure"];
				$datraArr[]= "Стоимость: ". $tourinfo["price"];
				$datraArr[]= "Топливный сбор: ". $tourinfo["fuel"];
				$datraArr[]= "Оператор: ". $tourinfo["operator"];
				$datraArr[]= "Ссылка на бронирование: ". $tourinfo["operator_link"];
				
				$datraArr[]= "Количество туристов: ". $people;
				$datraArr[]= "Курорт: ". $tourinfo["resort"];
				$datraArr[]= "Отель: ". $tourinfo["hotel"];
					
				$datraArr[]= "Ночей: ".  $tourinfo["nights"];
				$datraArr[]= "Размещение: ".  $tourinfo["placement"];
				$datraArr[]= "Тип питания: ".  $tourinfo["meal_name"];
				$datraArr[]= "Тип номера: ".   $tourinfo["room"];
				$datraArr[]= "Комметарий: ". $comment;
				
				$arFields = array(
					"NAME" => "Заявка от ".date("d.m.Y H:i:s"),
					"IBLOCK_ID" => 10,
					"IBLOCK_SECTION_ID" => 16,
					"PROPERTY_VALUES" => array(
						"REGION"    => $params["REG_PROP_ID"],
					),
					"PREVIEW_TEXT"  => implode("<br>",$datraArr),
					"PREVIEW_TEXT_TYPE" => 'html'
				);
				\CModule::IncludeModule("iblock");
				$ob = new CIblockElement();
				if($ID = $ob->Add($arFields))
				{
					
					$json["send"] = 1;
					\Bitrix\Main\Mail\Event::send(array(
						"EVENT_NAME" => "REGION_OFFICE_ORDER",
						"LID" => $params["SITE_ID"],
						"C_FIELDS" => array(
							"EMAIL" => $params["ORDER_EMAIL"],
							"TEXT" => implode("<br>",$datraArr)
						),
					)); 
					
					$revenue = "";
					$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_search_office","");
					if ($proc!="")
					{
						if (strpos($proc,"%")!==false)
						{
							$proc = str_replace("%","",$proc);
							$revenue = intval($tourinfo["price"] * $proc/100);
						}
						else
						{
							$revenue = $proc;
						}
					}

					
					if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
					{
						$json["data"] = array(
							"commerce" => 1,
							"name"	   => "Заявки в регионах - покупка в офисе",
							"id"	   => $ID,
							"price"	   => $tourinfo["price"],
							"category" => $tourinfo["country"],
							"revenue"  => $revenue
						);
						$_SESSION["COMMERCE_SEND"] = true;

						$pretour = CSiteParams::getPretourData("ECOM_ORDERTOUR_OFFICE",$tourinfo["price"],$reqData["ADULTS"]);
						if($pretour)
						{
							$json["data"]['p1'] = $pretour['P1'];
							$json["data"]['p2'] = $pretour['P2'];
						}
					}
					else
						$json["data"] = array(
							"commerce" => 0,
						);
				}	
			}
			else
			{	
					
				$date = date("d.m.Y H:i:s");
				$props = array(
					"STATUS"   		=> 8,
					"DATE"	   		=> $date,	
					"NAME"     		=> $name,
					"EMAIL"    		=> $email,
					"PHONE"    		=> ($phone!="") ? TelegramApi::cleanPhone($phone) : "",
					"COMMENTS" 		=> $comment,
					"PRICE"	   		=> $tourinfo["price"],
					"FUELCHARGE"	=> $tourinfo["fuel"],
					"OPERATOR"		=> $tourinfo["operator"],
					"DEPARTURE"		=> $tourinfo["departure"],
					"PEOPLE"	    => $people,
					"COUNTRY"		=> $tourinfo["country"],
					"REGION"		=> $tourinfo["resort"],
					"HOTEL"			=> $tourinfo["hotel"],
					"FLYDATE"		=> $tourinfo["date_from"],
					"NIGHTS"		=> $tourinfo["nights"],
					"PLACEMENT"		=> $tourinfo["placement"],
					"MEAL"			=> $tourinfo["meal_name"],
					"ROOM"			=> $tourinfo["room"],
					"OPERATORLINK"	=> $tourinfo["operator_link"],
					"YA_CLIENT"		=> $yaclient,
					"YA_CLID"		=> $yaclid ,
					"YA_UTM_SOURCE"	=> $yautmsource,
					"YA_UTM_MEDIUM"	=> $yautmmedium,
					"YA_UTM_CAMPAIGN"=> $yautmcampaign,
					"YA_UTM_CONTENT"=> $yautmcontent,
					"YA_UTM_TERM"	=> $yautmterm,
					"IS_ANYTOUR_ONLINE" => CSiteParams::$isAnytourOnline
				);
				
				$props["STATUS"] = 8;//новая
				
				$arLoadProductArray = Array(
					"IBLOCK_ID"        => 4,
					"IBLOCK_SECTION_ID"=> 8,
					"PROPERTY_VALUES"  => $props,
					"NAME"             => "Заявка от ".$props["DATE"],
					"ACTIVE"           => "Y",  
				);
				$el = new CIblockElement();
				$DATA_ID = $el->Add($arLoadProductArray);
				if($DATA_ID )
				{
					//TelegramApi::exportNewClaim($DATA_ID);
					CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("STATUS"=>9)); 
					$json["send"] = 1;
					$revenue = "";
					$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_search_office","");
					if ($proc!="")
					{
						if (strpos($proc,"%")!==false)
						{
							$proc = str_replace("%","",$proc);
							$revenue = intval($tourinfo["price"] * $proc/100);
						}
						else
						{
							$revenue = $proc;
						}
					}

					
					if (empty($_SESSION["COMMERCE_SEND"]) && $revenue!="")
					{
						$json["data"] = array(
							"commerce" => 1,
							"name"	   => "Покупка в офисе",
							"id"	   => $DATA_ID,
							"price"	   => $tourinfo["price"],
							"category" => $tourinfo["country"],
							"revenue"  => $revenue
						);
						$_SESSION["COMMERCE_SEND"] = true;

						$pretour = CSiteParams::getPretourData("ECOM_ORDERTOUR_OFFICE",$tourinfo["price"],$reqData["ADULTS"]);
						if($pretour)
						{
							$json["data"]['p1'] = $pretour['P1'];
							$json["data"]['p2'] = $pretour['P2'];
						}
					}
					else
						$json["data"] = array(
							"commerce" => 0,
						);
				}	
			}
		}
        
       
    } 
	elseif($_REQUEST["update_detail"])//детальная актуализация информации о туре
    {
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		
		$result = TvApi::geTourDetailAct($tid);
		
		file_put_contents($_SERVER["DOCUMENT_ROOT"]."/tv_tmp/flights_tmp.txt",json_encode($result));
		
		$flightStr = array(
			'forward',
			'backward'
		);


		if(!empty($result["flights"]) && is_array($result["flights"]) && count($result["flights"])>0)
		{
			\Bitrix\Main\Loader::includeModule('iblock');	
			$el = new CIblockElement();
			foreach($result["flights"] as $flightArr)
			{
				
				foreach($flightStr as $str)
				{
				
					foreach($flightArr[$str] as $fl)
					{
						//prf($fl);
						$propsT = array(
							"DIRECTION"		=>	($str=="backward") ? "1" : "0",
							"NUMBER"		=>	$fl['number'],
							"DATEFROM"		=>	$fl['departure']["date"],
							"DATETO"		=>	$fl['arrival']["date"],
							"TIMEFROM"		=>	$fl['departure']["time"],
							"TIMETO"		=>	$fl['arrival']["time"],
							"AIRCOMPANY"	=>	$fl['company']["name"],
							"PLANE"			=>	$fl['plane'],
							"TERMINALFROM"	=>	$fl['departure']['port']["name"],
							"TERMINALTO"	=>	$fl['arrival']['port']["name"],
							"REQID"			=>	$requestId,
							"HID"			=>	$hid ,
							"TID"			=>	$tid
						);
						
						$arLoadT = Array(
							"IBLOCK_ID"         => CSiteParams::$transportIB,
							"PROPERTY_VALUES"   => $propsT,
							"NAME"              => $propsT["TERMINALFROM"]." - ".$propsT["TERMINALTO"]." (".$propsT["DATEFROM"]." ".$propsT["TIMEFROM"]." - ".$propsT["DATETO"]." ".$propsT["TIMETO"].")",
							"ACTIVE"            => "Y",  
						);
						$IDT = $el->Add($arLoadT);	
					}
				}
							
			}
		}
	}	
	elseif($_REQUEST["act_tour_info"])//актуализация информации о туре
    {
		$data       =   array();
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$tourinfo   =   \TVApi::geTourAct($tid);
        //add2log($tourinfo);
		if(empty($tourinfo['iserror']) && empty($tourinfo['error']))
		{
            $data['fuel']      = $tourinfo['fuelcharge'];
            /*if($data['fuel']>0)
            {
                $data['fuel']	  = number_format($tourinfo["fuelcharge"],2, '.', ' ');
                $data['fuel']     = str_replace(".00","",$data['fuel']);
                $tourinfo["price"] += $tourinfo["fuelcharge"];
            }   */ 
		    $data['price_ecom'] = intval(floatval($tourinfo["price"])/1000);
			$data['price_ecom2'] = intval(floatval($tourinfo["price"]/($tourinfo["adults"]*500)));

			$pretour = CSiteParams::getPretourData("OPEN_TOUR",$tourinfo["price"],$tourinfo["adults"]);
			if($pretour)
			{
				$data['p1'] = $pretour['P1'];
				$data['p2'] = $pretour['P2'];
			}

			$data['price']	   = number_format($tourinfo["price"],2, '.', ' ');
			$data['price']     = str_replace(".00","",$data['price']);
			$data['date_from'] = $tourinfo['flydate'];
			$data["nights"]    = $tourinfo['nights'];
			$dt = new \Bitrix\Main\Type\Date($tourinfo["flydate"], "d.m.Y");
			$dt->add($tourinfo['nights']." day");
			$data["date_to"]   = $dt->toString();
			$data["name_ecom"] = $tourinfo["hotelname"]." с ".$data['date_from']." по ".$data["date_to"];
			
			$arParams["PRICE"] 			 = $tourinfo["price"];
            $arParams["FUEL"] 			 = $tourinfo["fuelcharge"];
			$arParams["NIGHTS"]          = $tourinfo['nights'];
			$arParams["DATE"]  		     = new \Bitrix\Main\Type\Date($tourinfo["flydate"], "d.m.Y");
			$arParams["OPERATOR_LINK"]   = $tourinfo["operatorlink"];
			
			\TVToursTable::updateTour($requestId,$tid,$arParams);
		}
		
		
		
        $json = array("data"=>$data);
       
    }  
    elseif($_REQUEST["get_tour_info"])//получение информации о туре
    {
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$hotelinfo  =   \TVApi::getHotelDataFull($hid );
		$dopData    =   \HotelsToReqestTable::GetHotelInfo ($requestId,$hid);
		$hotelinfo["desc"] = $dopData["DESCRIPTION"];
		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid);
        $json       =   array("hotel"=>$hid,"hotel_info"=>$hotelinfo,"tour_info"=>$tourinfo);
       
    }  
    elseif($_REQUEST["get_hotel_info"])//получение информации об в отеле
    {
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
		$t1			=   microtime(true);
		$info 		=   \TVApi::getHotelData($hid );
		$t1			= 	microtime(true) - $t1;
		$t2			=   microtime(true);
		$dopData    =   \HotelsToReqestTable::GetHotelInfo ($requestId,$hid);
		$t2			= 	microtime(true) - $t2;
		$info["desc"] = $dopData["DESCRIPTION"];
        $json       =   array("hotel"=>$hid,"info"=>$info,"t1"=>$t1,"t2"=>$t2);
       
    }        
    elseif($_REQUEST["get_hotel_tours"])//получение списка туров в отель
    {
        $requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $json       =   array("hotel"=>$hid,"tours"=>\TVToursTable::getTours($requestId ,$hid));
       
    }    
    else//получение и обновление туров
	{    
        
        /*
        Сначала получим id запроса. Либо это начало поиска и запрос нужно сформировать, либо это обновление информации. 
        В первом случает генерируемся запрос на поиск к TV.
        Во втором случае ищем id в базе и вытаскиваем информацию по нему
        */
		$dep = 0;
		$touristCount = 0;
        if (isset($_REQUEST["search_create"])) //поисковый запрос. сделам запрос к TV и занесем его в базу
        {    
            $tcreate        =   true;
            $countryId      =   intval($_REQUEST["country"]);           
            $cityFromId     =   intval($_REQUEST["city"]);
			if ( isset($_REQUEST["resort"]) && is_array($_REQUEST["resort"]) && count($_REQUEST["resort"])>0)  
               $resort      =   $_REQUEST["resort"];  
			if ( isset($_REQUEST["hotel"]) && is_array($_REQUEST["hotel"]) && count($_REQUEST["hotel"])>0)  
			{
               $hotelArr     =   $_REQUEST["hotel"];     
			   foreach($hotelArr as $hotel)
			   {
				   if ($hotel!="")
				   $hotels[] = $hotel;
			   }
			}   
			
			   
			if ( isset($_REQUEST["adults"]) && intval($_REQUEST["adults"])>0 && intval($_REQUEST["adults"])<5)  
               $adults  = intval($_REQUEST["adults"]);
			   
			if ( isset($_REQUEST["price_from"]) && intval($_REQUEST["price_from"])>0)  
               $pricefrom  = intval($_REQUEST["price_from"]);   
			 if ( isset($_REQUEST["price_till"]) && intval($_REQUEST["price_till"])>0)  
               $priceto  = intval($_REQUEST["price_till"]);   
			   
            if ( isset($_REQUEST["children"]) && intval($_REQUEST["children"])>=0 && intval($_REQUEST["children"])<4)  
               $children  = intval($_REQUEST["children"]);
			if ( isset($_REQUEST["nightsmin"]) && intval($_REQUEST["nightsmin"])>0 && intval($_REQUEST["nightsmin"])<31)  
               $nightsMin = intval($_REQUEST["nightsmin"]);
            if ( isset($_REQUEST["nightsmax"]) && intval($_REQUEST["nightsmax"])>0 && intval($_REQUEST["nightsmax"])<31)  
               $nightsMax = intval($_REQUEST["nightsmax"]);
			if (isset($_REQUEST["stars"]) && is_array($_REQUEST["stars"]) && count($_REQUEST["stars"])>0)  
			{
				/*$starNew = 0;
				$stArr = $_REQUEST["stars"];
				foreach($stArr as $s)
					if ($s>$starNew)
						$starNew = $s;
				if ($starNew>0)
					$stars = $starNew;
				*/
				$starNew = 6;
				$stArr = $_REQUEST["stars"];
				foreach($stArr as $s)
					if ($s<$starNew)
						$starNew = $s;
				if ($starNew<6)
					$stars = $starNew;
				
			}	
			if (isset($_REQUEST["food"]) && is_array($_REQUEST["food"]) && count($_REQUEST["food"])>0)  
			{
				$meal = $_REQUEST["food"][0];
			}	
			
			if (isset($_REQUEST["datefrom"]) && $_REQUEST["datefrom"]!="")
			{ 
				$dateFrom = explode("-",$_REQUEST["datefrom"]);
				$dateFrom = $dateFrom[2].".".$dateFrom[1].'.'.$dateFrom[0];
			}
			
			if (isset($_REQUEST["datetill"]) && $_REQUEST["datetill"]!="")
			{ 
				$dateTo = explode("-",$_REQUEST["datetill"]);
				$dateTo = $dateTo[2].".".$dateTo[1].'.'.$dateTo[0];
			}
			
			$hideRegTours = intval($_REQUEST["hide_reg_tours"]);
			
			$requestArr     		  = array(); 
			$requestArr['country'] 	  = $countryId;
            $requestArr['departure']  = $cityFromId ;
            $requestArr['adults'] 	  = $adults;
			$requestArr['child'] 	  = $children;
            $requestArr['nightsfrom'] = $nightsMin;
            $requestArr['nightsto']   = $nightsMax;
			if($resort)
				$requestArr['resort'] = $resort;
			if($hotels)
				$requestArr['hotels'] = $hotels;
			if($pricefrom)
				$requestArr['pricefrom'] = $pricefrom;
			if($priceto)
				$requestArr['priceto'] = $priceto;				
			if ($stars)
				$requestArr['stars']  = $stars;
			if ($meal)
				$requestArr['meal']  = $meal;	
			if ($dateFrom)
				$requestArr['datefrom']  = $dateFrom;	
			if ($dateTo)
				$requestArr['dateto']  = $dateTo;	

			$touristCount = $adults;

			if($children>0 && $_REQUEST["children_ages"])
			{
				$ages = $_REQUEST["children_ages"];
				foreach($ages as $i=>$age)
					$requestArr['childage'.($i+1)] = $age;
			}
			$requestArr['hideregtours']=$hideRegTours;
			
			if(!$_SESSION["pretour_set"] && !$_SESSION["pretour_sent"])
			{
				$reqhash= md5(json_encode($requestArr)); 
				if($_SESSION["tour_req_data"] && $reqhash!=$_SESSION["tour_req_data"])
					$_SESSION["pretour_set"] = true;
				else
					$_SESSION["tour_req_data"] = $reqhash;
			}
			if(
				!$_SESSION["param_change_set"] 
				&& !$_SESSION["param_change_sent"]
				&& $hotels
				&& $_REQUEST["params_changed"]=="1"
			)
			{
				$_SESSION["param_change_set"] = true;
			}

			//prf($requestArr,'a');
			//add2log($requestArr);
			if($_REQUEST["savedata"])
			{
				$formCode = TvApi::saveSearchForm($requestArr);
				$base = (strpos($_SERVER["HTTP_REFERER"],"poisk-turov-tg")!==false) ? "https://anytour.online/poisk-turov-tg/": "https://anytour.online/poisk-turov/" ;
				$json["link"] = $base.$formCode."/";

			}
			else
			{
				$requestId = TvApi::createSearchReq($requestArr); 
				//add2log($requestId);
				//add2log($cityFromId);
				//$requestId =  3191214769;
				if (is_array($requestId) || $requestId==0 || $requestId==1) {
					sleep(3);
					$requestId = TvApi::createSearchReq($requestArr); 
				}	
					 
				if (!is_array($requestId) && $requestId!="" && $requestId!=0 && $requestId!=1) { //id запроса получили, заносим его в бд
					$rowList = \RequestTable::getList(array('select' => array('ID','PERCENT',"DEPARTURE"),'filter' => array("ID"=>$requestId))); //ищем id запроса в базе
					if($row = $rowList ->fetch()) //запрос там есть - TV вернул тот же ID запроса
					{
						$percentDB = $row["PERCENT"];
						$tcreateInBase = true;
						$dep = $row["DEPARTURE"];
						$reqData = \RequestDataTable::getByID($requestId);
						if ($reqData["ADULTS"])
							$touristCount = $reqData["ADULTS"];

					}
					else
					{    
						
						$arF = array('ID' => $requestId,'TIME' =>  new \Bitrix\Main\Type\DateTime(),'ACTIVE'=> true, 'PERCENT'=>0,'TCOUNT'=>0,"DEPARTURE"=>$cityFromId);
						$res = \RequestTable::add($arF);
						$dep = $cityFromId;
						\RequestDataTable::save($requestId, $requestArr);
						
					}
				} 
				if(is_array($requestId) && $requestId["ERROR"])
				{
					add2log($requestId);    
					add2log($requestArr);
				}
			}
        }
        else //запрос на обновление информации
        {
            if ($_REQUEST["search_update"]) 
				$tupdate = true; 
			else 
				$tresult = true;
				
            $requestId      =   htmlspecialchars($_REQUEST["reqid"]);
            if ($requestId!="")
            {   
                $rowList = \RequestTable::getList(array('select' => array('ID','PERCENT','TCOUNT',"DEPARTURE"),'filter' => array("ID"=>$requestId))); //ищем id запроса в базе
                if($row = $rowList ->fetch()) //запрос там есть, тащим информацию.
                {
                    $percent = $row["PERCENT"];
                    $tours 	 = $row["TCOUNT"];
                    $toursdb = $row["TCOUNT"];
					$dep     = $row["DEPARTURE"];

					$reqData = \RequestDataTable::getByID($requestId);
					if ($reqData["ADULTS"])
						$touristCount = $reqData["ADULTS"];
                }
                else
                {
                    $error =true;
                }  
            }
            else
            {
                $error =true;
            }    
			
        }
       
        /*
            Имеем id запроса. Дальше работаем с ним.
            Обновляем состояние поиска. Если изменится состояние поиска (проценты и кол-во найденных туров), то мы их запишем в БД.
        */
         
		//echo "1" ;
        if ($requestId!="" && $requestId!=0 && !$error) {
			$json["reqid"]    = $requestId;
            $json["tcount"]   = $tours; 
            $json["percent"]  = $percentFound;
            $json["minprice"] = $price;
			
            if (/*$tcreate ||*/ $tupdate) // начало поиска и обновление состояния
            {	
				//echo "3" ;
                $resSatus	  = \TvApi::getReqStatus($requestId);	          //проверяем состояние поиска
				//print_r($resSatus);
                $percentFound = $resSatus["status"]["progress"];   //процент обработанных операторов
                $price 		  = $resSatus["status"]["minprice"];
              
                if ($percentFound > $percent)                                   // обработалось больше операторов
                {    
					//echo "4" ;
                    $upArr = array("PERCENT"=>$percentFound, "TCOUNT"=>$tours);
                    $result = \TvApi::getReqResult($requestId);          //забираем туры
					//print_r($result);
                    $cnt=$result["status"]["toursfound"];
                    if ( $cnt > $tours)                                         //появились новые туры?
                    {
                        $active = ($tours == 0) ? true : false;
                        $tours = $cnt;
                        $upArr["TCOUNT"]=$cnt;
						$price = $result["status"]["minprice"];
                        \TVToursTable::saveTours($requestId,$dep,$result['hotels'],$active);   //добавляем туры в бд    
                    }  
                    \RequestTable::update($requestId,$upArr);                    //обновляем информаци о запросе
                }
                
			
                $json["reqid"]    = $requestId;
                $json["tcount"]   = $tours; 
                $json["percent"]  = $percentFound;
                $json["minprice"] = $price;
            }

			if ($tcreate && $tours>0 || $tupdate && $toursdb==0 && $tours>0 || $tresult)
            {
                //echo "5" ;
                if (isset($_REQUEST["page"]) && intval($_REQUEST["page"])>0)
                    $page = intval($_REQUEST["page"]);
                if (isset($_REQUEST["upd"]) && $_REQUEST["upd"]=='true')
                    $update = true;
                $getRes =  \HotelsToReqestTable::getTours($requestId,$page,$update,true);
                $json["tours"] = $getRes['tours'];
                $json["pager"] = $getRes['pager'];
                if ($tresult)
                {    
                    $json["minprice"] = $json["tours"][0]['price'];
                    $json["tcount"] = $getRes['tcount'];
                    $json["update"] =  ($update) ? 1 : 0;  
                } 
                $json["reqid"] = $requestId;                
            }  
            if ($tcreateInBase)//созданный запрос есть в базе
                $json["percent"] = $percentDB;	

            if($json["minprice"]!="" && $json["minprice"]>0)
            {
				if($_SESSION["pretour_set"] && !$_SESSION["pretour_sent"])
				{
					$json["pretour"] = intval(floatval($json["minprice"])/3900);

					$pretour = CSiteParams::getPretourData("PRETOUR",$json["minprice"],$touristCount);
					if($pretour)
					{
						$json['p1'] = $pretour['P1'];
						$json['p2'] = $pretour['P2'];
					}

					$_SESSION["pretour_sent"] = true;
				}

				if($_SESSION["param_change_set"] && !$_SESSION["param_change_sent"])
				{
					$json["param_change"] = intval(floatval($json["minprice"])/2000);

					$paramChange = CSiteParams::getPretourData("PARAM_CHANGE",$json["minprice"],$touristCount);
					if($paramChange)
					{
						$json['param_p1'] = $paramChange['P1'];
						$json['param_p2'] = $paramChange['P2'];
					}

					$_SESSION["param_change_sent"] = true;
				}

                $json["minprice"]  = number_format(floatval($json["minprice"]),2, '.', ' ');
                $json["minprice"] = str_replace(".00","",$json["minprice"]);
                
            }    
            
            if ($tcreate)  
            {    
                $revenue = "";
                $proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_search_start","");
                if ($proc!="")
                {
                    if (strpos($proc,"%")!==false)
                    {
                        
                    }
                    else
                    {
                        $revenue = $proc;
                    }
                }

                
                if ($revenue!="")
                {
                    $json["data"] = array(
                        "commerce" => 1,
                        "name"	   => "Поиск",
                        "id"	   => "",
                        "price"	   => $revenue,
                        "category" => "",
                        "revenue"  => $revenue
                    );
                }
                else
                    $json["data"] = array(
                        "commerce" => 0,
                    );   
            }    
                
        }
        else
        {
            $error =true;
        }    
        
    }
    $json['time'] = microtime(true) - $time;
}
else
{
   $json['error'] = true;
} 
//print_R ($json);    
echo json_encode($json); 
//echo json_last_error();

<?
use Bitrix\Main\Loader;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/search.php');
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
	
$json=array(); 
if (isset($_REQUEST["mode"]))
{	
	
}
elseif (
		
        isset($_REQUEST["get_tour_info"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"])  ||
		isset($_REQUEST["act_tour_info"]) && isset($_REQUEST["tid"])  ||
		isset($_REQUEST["update_detail"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"])  ||
		isset($_REQUEST["officeorder"])   && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"])) ||
		isset($_REQUEST["onlineorder"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"])  && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"]) && isset($_REQUEST["tourists"]))
       

	)
{    
   

    /***************************************/
    if($_REQUEST["onlineorder"])//заявка на тур
    {
		$data       =   array('online');
		
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$name  		=   htmlspecialchars($_REQUEST["name"]);
        $phone      =   htmlspecialchars($_REQUEST["phone"]);
        $email      =   htmlspecialchars($_REQUEST["email"]);
		$comment    =   htmlspecialchars($_REQUEST["text"]);
		$passNum    =   htmlspecialchars($_REQUEST["passnum"]);
		$passWho    =   htmlspecialchars($_REQUEST["passwho"]);
		$passDate   =   htmlspecialchars($_REQUEST["passdate"]);
		$yaclient   =   (htmlspecialchars($_REQUEST["yaclient"])!="null") ? htmlspecialchars($_REQUEST["yaclient"]) : "";
		$yaclid     =   (htmlspecialchars($_REQUEST["yaclid"])!="undefined") ? htmlspecialchars($_REQUEST["yaclid"]) : "";
		$yautmsource    =   (htmlspecialchars($_REQUEST["yautmsource"])!="null") ? htmlspecialchars($_REQUEST["yautmsource"]) : "";
		$yautmmedium    =   (htmlspecialchars($_REQUEST["yautmmedium"])!="null") ? htmlspecialchars($_REQUEST["yautmmedium"]) : "";
		$yautmcampaign  =   (htmlspecialchars($_REQUEST["yautmcampaign"])!="null") ? htmlspecialchars($_REQUEST["yautmcampaign"]) : "";
		$yautmcontent   =   (htmlspecialchars($_REQUEST["yautmcontent"])!="null") ? htmlspecialchars($_REQUEST["yautmcontent"]) : "";
		$yautmterm      =   (htmlspecialchars($_REQUEST["yautmterm"])!="null") ? htmlspecialchars($_REQUEST["yautmterm"]) : "";
		
		$tourinfo   =   \TVApi::getHotTour($tid, true);
		if(count($tourinfo)>0)
		{
			$people = "";
			if ($tourinfo["adults"])
				$people = "Взрослых: ".$tourinfo["adults"];
			if ($tourinfo["child"])
			{
				$people .= "; Детей: ".$tourinfo["child"];	
			}	
			
			
			$date = date("d.m.Y H:i:s");
			$props = array(
				"STATUS"   		=> 8,
				"DATE"	   		=> $date,	
				"NAME"     		=> $name,
				"EMAIL"    		=> $email,
				"PHONE"    		=> ($phone!="") ? TelegramApi::cleanPhone($phone) : "",
				"COMMENTS" 		=> $comment,
				"PRICE"	   		=> $tourinfo["real_price"],
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
				"PASSPORT_DATE" 	=> $passDate["date"],
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
				"IBLOCK_SECTION_ID"=> 14,
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
				$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_hot_online","");
				if ($proc!="")
				{
					if (strpos($proc,"%")!==false)
					{
						$proc = str_replace("%","",$proc);
						$revenue = intval($tourinfo["real_price"] * $proc/100);
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
						"name"	   => "Горящий тур - покупка онлайн",
						"id"	   => $DATA_ID,
						"price"	   => $tourinfo["real_price"],
						"category" => $tourinfo["country"],
						"revenue"  => $revenue
					);
					$_SESSION["COMMERCE_SEND"] = true;
				}
				else
					$json["data"] = array(
						"commerce" => 0,
					);
                
			}	
		}
        //$json = $data;
       
    }
	elseif($_REQUEST["officeorder"])//заявка на тур
    {
		$data       =   array();
		
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

		$tourinfo   =   \TVApi::getHotTour($tid, true);
		if(count($tourinfo)>0)
		{
			
			$people = "";
			if ($tourinfo["adults"])
				$people = "Взрослых: ".$tourinfo["adults"];
			if ($tourinfo["child"])
			{
				$people .= "; Детей: ".$tourinfo["child"];	
			}	
				
			$date = date("d.m.Y H:i:s");
			$props = array(
				"STATUS"   		=> 8,
				"DATE"	   		=> $date,	
				"NAME"     		=> $name,
				"EMAIL"    		=> $email,
				"PHONE"    		=> ($phone!="") ? TelegramApi::cleanPhone($phone) : "",
				"COMMENTS" 		=> $comment,
				"PRICE"	   		=> $tourinfo["real_price"],
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
				"IBLOCK_SECTION_ID"=> 13,
				"PROPERTY_VALUES"  => $props,
				"NAME"             => "Заявка от ".$props["DATE"],
				"ACTIVE"           => "Y",  
			);
			$el = new CIblockElement();
			$DATA_ID = $el->Add($arLoadProductArray);
			if($DATA_ID )
			{
				
				CIBlockElement::SetPropertyValuesEx($DATA_ID, false, array("STATUS"=>9)); 
				$json["send"] = 1;
				
				$revenue = "";
				$proc = \Bitrix\Main\Config\Option::get("rhat.params", "rhat_ecom_hot_office","");
				if ($proc!="")
				{
					if (strpos($proc,"%")!==false)
					{
						$proc = str_replace("%","",$proc);
						$revenue = intval($tourinfo["real_price"] * $proc/100);
					}
					else
					{
						$revenue = $proc;
					}
				}
				
				if (empty($_SESSION["COMMERCE_SEND"]) && $revenue != "")
				{
					$json["data"] = array(
						"commerce" => 1,
						"name"	   => "Горящий тур - покупка в офисе",
						"id"	   => $DATA_ID,
						"price"	   => $tourinfo["real_price"],
						"category" => $tourinfo["country"],
						"revenue"  => $revenue
					);
					$_SESSION["COMMERCE_SEND"] = true;
				}
				else
					$json["data"] = array(
						"commerce" => 0,
					);
			}	
		}
        
       
    } 
	elseif($_REQUEST["update_detail"])//детальная актуализация информации о туре
    {
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		
		$result     =   TvApi::geTourDetailAct($tid);
		
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
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$tourinfo   =   \TVApi::geTourAct($tid);
        
		if(is_array($tourinfo ) && empty($tourinfo['iserror']) && empty($tourinfo['error']))
		{
            $data['fuel']      = $tourinfo['fuelcharge'];
           
			$data['price']	   = number_format($tourinfo["price"],2, '.', ' ');
			$data['price']     = str_replace(".00","",$data['price']);
			$data['date_from'] = $tourinfo['flydate'];
			$data["nights"]    = $tourinfo['nights'];
			$dt = new \Bitrix\Main\Type\Date($tourinfo["flydate"], "d.m.Y");
			$dt->add($tourinfo['nights']." day");
			$data["date_to"]   = $dt->toString();
			$data["placement"]  = $tourinfo["room"]."/".$tourinfo["placement"];
			$data["adults"]	= $tourinfo["adults"];
			$data["child"]  = $tourinfo["child"];
			
			$arParams["UF_PLACEMENT"] 	     = $tourinfo["room"]."/".$tourinfo["placement"];
			$arParams["UF_REAL_PRICE"] 		 = $tourinfo["price"];
            $arParams["UF_FUEL"] 			 = $tourinfo["fuelcharge"];
			$arParams["UF_NIGHTS"]           = $tourinfo['nights'];
			$arParams["UF_FLY_DATE"]  		 = $tourinfo["flydate"];
			$arParams["UF_LINK"]   	 		 = $tourinfo["operatorlink"];
			$arParams["UF_ADULTS"]   	 	 = $tourinfo["adults"];
			$arParams["UF_CHILD"]   	 	 = $tourinfo["child"];
			$arParams["UF_ACT_DATE"]   	 	 = new \Bitrix\Main\Type\DateTime();
			
			\TVApi::updateHotTour($tid,$arParams);
			
		}
		
		
		
        $json = array("data"=>$data,"log"=>$tourinfo);
       
    }  
    elseif($_REQUEST["get_tour_info"])//получение информации о туре
    {
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$hid        =   htmlspecialchars($_REQUEST["hid"]);  
		$hotelinfo  =   \TVApi::getHotelDataFull($hid);
		//$dopData    =   \HotelsToReqestTable::GetHotelInfo ($requestId,$hid);
		$hotelinfo["desc"] = "";
		$tourinfo   =   \TVApi::getHotTour($tid);
        $json       =   array("hotel"=>$hid,"hotel_info"=>$hotelinfo,"tour_info"=>$tourinfo);
       
    }  
    $json['time'] = microtime(true) - $time;
}
else
{
   $json['error'] = true;
} 
  
echo json_encode($json); 


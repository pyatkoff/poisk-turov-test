<?
use Bitrix\Main\Loader;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/search.php');
require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
	
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
				
                $hotelList= TvApi::GetHotelList($filter);
                
                $json["hotelList"] =  (count($hotelList)<=100) ? array($hotelList) : array_chunk($hotelList,100);
               
            }
			break; 
	}		
}
elseif (
		(isset($_REQUEST["search_update"]) || isset($_REQUEST["search_result"])) && isset($_REQUEST["reqid"]) ||
        isset($_REQUEST["search_create"]) && isset($_REQUEST["country"]) && isset($_REQUEST["city"]) || 
        isset($_REQUEST["get_hotel_tours"]) && isset($_REQUEST["req"]) && isset($_REQUEST["hid"]) || 
        isset($_REQUEST["get_hotel_info"]) && isset($_REQUEST["hid"]) ||
        isset($_REQUEST["get_tour_info"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["act_tour_info"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["update_detail"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) ||
		isset($_REQUEST["officeorder"])   && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"])) ||
		isset($_REQUEST["onlineorder"]) && isset($_REQUEST["hid"]) && isset($_REQUEST["tid"]) && isset($_REQUEST["req"]) && isset($_REQUEST["name"]) && (isset($_REQUEST["email"]) || isset($_REQUEST["phone"]) && isset($_REQUEST["tourists"])) 
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
    /***************************************/
      
	if($_REQUEST["onlineorder"])//заявка на тур
    {
		$data       =   array('online');
		$requestId  =   htmlspecialchars($_REQUEST["req"]);
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $tid        =   htmlspecialchars($_REQUEST["tid"]);
		$name  		=   htmlspecialchars($_REQUEST["name"]);
        $phone      =   htmlspecialchars($_REQUEST["phone"]);
        $email      =   htmlspecialchars($_REQUEST["email"]);
		$comment    =   htmlspecialchars($_REQUEST["text"]);
		
		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid, true);
		if(count($tourinfo)>0)
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
				"COUNTRY"		=> $tourinfo["country"],
				"REGION"		=> $tourinfo["resort"],
				"HOTEL"			=> $tourinfo["hotel"],
				"FLYDATE"		=> $tourinfo["date_from"],
				"NIGHTS"		=> $tourinfo["nights"],
				"PLACEMENT"		=> $tourinfo["placement"],
				"MEAL"			=> $tourinfo["meal_name"],
				"ROOM"			=> $tourinfo["room"],
				"OPERATORLINK"	=> $tourinfo["operator_link"],
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
				$json["send"] = 1;
				$json["data"] = array(
					"name"	   => "Покупка онлайн",
					"id"	   => $DATA_ID,
					"price"	   => $tourinfo["price"],
					"category" => $tourinfo["country"],
					"revenue"  => intval($tourinfo["price"] * 0.1)
				);
				
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
		
		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid, true);
		if(count($tourinfo)>0)
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
				"COUNTRY"		=> $tourinfo["country"],
				"REGION"		=> $tourinfo["resort"],
				"HOTEL"			=> $tourinfo["hotel"],
				"FLYDATE"		=> $tourinfo["date_from"],
				"NIGHTS"		=> $tourinfo["nights"],
				"PLACEMENT"		=> $tourinfo["placement"],
				"MEAL"			=> $tourinfo["meal_name"],
				"ROOM"			=> $tourinfo["room"],
				"OPERATORLINK"	=> $tourinfo["operator_link"],
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
				$json["data"] = array(
					"name"	   => "Покупка в офисе",
					"id"	   => $DATA_ID,
					"price"	   => $tourinfo["price"],
					"category" => $tourinfo["country"],
					"revenue"  => intval($tourinfo["price"] * 0.05)
				);
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
			$data['price']	   = number_format($tourinfo["price"],2, '.', ' ');
			$data['price']     = str_replace(".00","",$data['price']);
			$data['date_from'] = $tourinfo['flydate'];
			$data["nights"]    = $tourinfo['nights'];
			$dt = new \Bitrix\Main\Type\Date($tourinfo["flydate"], "d.m.Y");
			$dt->add($tourinfo['nights']." day");
			$data["date_to"]   = $dt->toString();
			
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
		$tourinfo   =   \TVToursTable::getTour($requestId ,$tid);
        $json       =   array("hotel"=>$hid,"hotel_info"=>$hotelinfo,"tour_info"=>$tourinfo);
       
    }  
    elseif($_REQUEST["get_hotel_info"])//получение информации об в отеле
    {
        $hid        =   htmlspecialchars($_REQUEST["hid"]);
        $json       =   array("hotel"=>$hid,"info"=>\TVApi::getHotelData($hid ));
       
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
				$starNew = 0;
				$stArr = $_REQUEST["stars"];
				foreach($stArr as $s)
					if ($s>$starNew)
						$starNew = $s;
				if ($starNew>0)
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
			
			if($children>0 && $_REQUEST["children_ages"])
			{
				$ages = $_REQUEST["children_ages"];
				foreach($ages as $i=>$age)
					$requestArr['childage'.($i+1)] = $age;
			}
			//prf($requestArr,'a');
            $requestId = TvApi::createSearchReq($requestArr); 
			//$requestId =  3191214769;
            if ($requestId!="") { //id запроса получили, заносим его в бд
                $rowList = \RequestTable::getList(array('select' => array('ID','PERCENT',"DEPARTURE"),'filter' => array("ID"=>$requestId))); //ищем id запроса в базе
                if($row = $rowList ->fetch()) //запрос там есть - TV вернул тот же ID запроса
                {
                    $percentDB = $row["PERCENT"];
                    $tcreateInBase = true;
					$dep = $row["DEPARTURE"];
                }
                else
                {    
                    $arF = array('ID' => $requestId,'TIME' =>  new \Bitrix\Main\Type\DateTime(),'ACTIVE'=> true, 'PERCENT'=>0,'TCOUNT'=>0,"DEPARTURE"=>$cityFromId);
                    $res = \RequestTable::add($arF);
					$dep = $cityFromId;
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
        if ($requestId!="" && !$error) {
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
					//add2log($result);
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
                $json["minprice"]  = number_format($json["minprice"],2, '.', ' ');
                $json["minprice"] = str_replace(".00","",$json["minprice"]);
                
            }    
                
        }
        else
        {
            $error =true;
        }    
        
    }
}
else
{
   $json['error'] = true;
} 
//print_R ($json);    
echo json_encode($json); 
//echo json_last_error();

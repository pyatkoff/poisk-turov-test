<?
use Tourvisor\Tourvisor;
use Tourvisor\Client;
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/php_interface/tv_api/tourvisor/vendor/autoload.php');

class TvApi
{
	static $login = 'info@rhat.ru';//'info@rhat.ru';
	static $pass  = 'emtqlPCG2Yyq';//'emtqlPCG2Yyq';
	
	static $depHL    = 1;
	static $contryHL = 2;
	static $regHL 	 = 3;
	static $mealHL 	 = 4;
	static $starHL 	 = 5;
	static $hotelHL 	 = 6;
	static $operatorHL 	 = 7;
	static $currencyHL 	 = 8;
	static $hotHL 	 	 = 12;
	static $countryPhotoHL 	= 15;
	static $saveRequestHL 	= 22;
	
	public static $params=array(
        "departure"		=> 1, 
        "country"		=> 4, 
        "nightsfrom"	=> 5,
        "nightsto" 		=> 14,
        "adults" 		=> 2,
		"children"		=> 0,
        "resort"		=> false,
        "hotel"			=> false
        
	);
	
	public static function getAllCountryList()
	{
		$res = array();
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$contryHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$filter = array();
		
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array('*')));
		while($item =  $dbData->fetch())
		{	
			$res[]=$item;
		}	
		return $res;
	}
	
	
	/*************************************************************************************************************************/
	/**********************************************ОБНОВЛЕНИЕ ДАННЫХ В БД*****************************************************/
	/*************************************************************************************************************************/

	/*
	*  Обновить список городов отправления в БД
	*
	*/
	public static function updateDepList()
	{
		$res = array();
		$depList = self::getTvDepList();
		if(is_array($depList) && empty($depList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$depHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($depList["departures"] as $dep)
			{
				
				$data = array(
					"UF_DEPID"	=> $dep["id"],
					"UF_NAME"	=> $dep["name"],
					"UF_NAME2"	=> $dep["namefrom"],
					"UF_SORT"	=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_DEPID"	=> $dep["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	
	/*
	*  Обновить список стран в БД
	*
	*/
	public static function updateCountryList()
	{
		$res = array();
		$countryList = self::getTvCountryList();
	
		if(is_array($countryList) && empty($countryList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$contryHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($countryList["countries"] as $cont)
			{
				
				$data = array(
					"UF_CID"	=> $cont["id"],
					"UF_NAME"	=> $cont["name"],
					"UF_SORT"	=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_CID"=> $cont["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	/*
	*  Обновить список курортов в БД
	*
	*/
	public static function updateRegionList()
	{
		$res = array();
		$regionList = self::getTvRegionList();
	
		if(is_array($regionList) && empty($regionList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$regHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($regionList["regions"] as $reg)
			{
				
				$data = array(
					"UF_TID"	=> $reg["id"],
					"UF_CID"	=> $reg["country"],
					"UF_NAME"	=> $reg["name"],
					"UF_SORT"	=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_TID"=> $reg["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	/*
	*  Обновить список отелей в БД
	*
	*/
	public static function updateHotelList($params)
	{
		$res = array();
		
		$hotelList = self::getTvHotelList($params);
		
		if(is_array($hotelList) && empty($hotelList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();

			$hotelsDB = $eclass::getList(['filter'=>["UF_CID"=>$params['CID']],'select'=>["ID","UF_HID"]]);
			while($hotel = $hotelsDB->fetch())
			{	
				$hotelsHID[$hotel["UF_HID"]] = $hotel["ID"];
			}

			$count = 0;
			foreach($hotelList["hotels"] as $hotel)
			{
				
				$data = array(
					"UF_HID"	=> $hotel["id"],
					"UF_NAME"	=> $hotel["name"],
					"UF_CID"	=> $params['CID'],
					"UF_SID"	=> $hotel["stars"],
					"UF_TID"	=> $hotel["regioncode"],
					"UF_STID"	=> $hotel["subregioncode"],
					"UF_RATE"	=> $hotel["rating"],
					"UF_IS_ACTIVE"	=> ($hotel["is_active"]==1) ? true : false,
					"UF_IS_CITY"	=> ($hotel["is_city"]==1) ? true : false,
					"UF_IS_BEACH"	=> ($hotel["is_beach"]==1) ? true : false,
					"UF_IS_FAMILY"	=> ($hotel["is_family"]==1) ? true : false,
					"UF_IS_RELAX"	=> ($hotel["is_relax"]==1) ? true : false,
					"UF_IS_HEALTH"	=> ($hotel["is_health"]==1) ? true : false,
					"UF_IS_DELUXE"	=> ($hotel["is_deluxe"]==1) ? true : false,
					"UF_SORT"		=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_HID"=> $hotel["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
					$hotelsHIDFound[]=$hotel["id"];
				}	
				else
					$resAdd = $eclass::add($data);
				
				//self::updateHotelData($hotel["id"]);
				
				$count++;
				
				//break; 
			}

			foreach($hotelsHID as $hid=>$id)
			{
				if (!in_array($hid,$hotelsHIDFound))
				{
					$eclass::delete($id);
				}
			}

			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	
	/*
	*  Обновить все отели в БД
	*
	*/
	public static function updateAllHotelList()
	{
		$res = array();
		$continue = true;
		$countryList = self::getAllCountryList();
		foreach($countryList as $item)
		{
			if($item["UF_CID"]==85)
				$continue = false;
			if($continue)
				continue;
				
			$res =  self::updateHotelList(array("CID"=>$item["UF_CID"]));
			if($res['ERROR'])
				break;
		}	
		return $res ;
	}
	
	/*
	*  Обновить список типов питания
	*
	*/
	public static function updateMealList()
	{
		$res = array();
		$mealList = self::getTvMealList();
	
		if(is_array($mealList) && empty($mealList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$mealHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($mealList["meals"] as $meal)
			{
				
				$data = array(
					"UF_MID"		=> $meal["id"],
					"UF_NAME"		=> $meal["name"],
					"UF_FULLNAME" 	=> $meal["fullname"],
					"UF_RUS"		=> $meal["russian"],
					"UF_RUSFULL"	=> $meal["russianfull"],
					"UF_SORT"		=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_MID"=> $meal["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	
	/*
	*  Обновить список категорий отеля
	*
	*/
	public static function updateStarList()
	{
		$res = array();
		$starList = self::getTvStarList();
	
		if(is_array($starList) && empty($starList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$starHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($starList["stars"] as $meal)
			{
				
				$data = array(
					"UF_SID"		=> $meal["id"],
					"UF_NAME"		=> $meal["name"],
					
					"UF_SORT"		=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_SID"=> $meal["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	/*
	*  Обновить список операторов
	*
	*/
	public static function updateOperatorList()
	{
		$res = array();
		$opList = self::getTvOperatorList();
	
		if(is_array($opList) && empty($opList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$operatorHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($opList["operators"] as $op)
			{
				
				$data = array(
					"UF_OID"		=> $op["id"],
					"UF_NAME"		=> $op["name"],
					"UF_FULLNAME"	=> $op["fullname"],
					"UF_RUS"		=> $op["russian"],
					"UF_SORT"		=> 500
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_OID"=> $op["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					unset($dat["UF_SORT"]);
					$resAdd = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
		
	/*
	*  Обновить курсы валют операторов
	*
	*/
	public static function updateCurList()
	{
		$res = array();
		$curList = self::getTvCurList();
	
		if(is_array($curList) && empty($curList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$currencyHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$count = 0;
			foreach($curList["currency"] as $cur)
			{
				
				$data = array(
					"UF_OID"		=> $cur["id"],
					"UF_NAME"		=> $cur["name"],
					"UF_USD"		=> $cur["usd"],
					"UF_EUR"		=> $cur["eur"],
					"UF_DATE"		=> new  \Bitrix\Main\Type\DateTime()
				);
				$dbData = $eclass::getList(array("filter"=>array("UF_OID"=> $cur["id"]),"select"=>array("ID")))->fetch();
				if($dbData)
				{	
					$resUpdate = $eclass::update($dbData["ID"],$data);
				}	
				else
					$resAdd = $eclass::add($data);
				
				$count++;
				
				if ($cur["id"]==13)
				{
					$fl = "<span>".date('d.m.Y')."</span>|<span>USD ".$cur["usd"]."</span><span>EUR ".$cur["eur"]."</span>";
					file_put_contents($_SERVER["DOCUMENT_ROOT"]."/currency.txt",$fl);
				}
				
				//break;
			}
			$res["OK"] = $count;
		}
		else
			$res["ERROR"]	;
		
		return $res;
	}
	
	/*
	*  Обновить информацию об отелях
	*
	*/
	public static function updateHotelsFullData($CID,$page = 0,$count = 300)
	{
		$res = 0;
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		
		$dbData = $eclass::getList(array("filter"=>array("UF_CID"=> $CID),"select"=>array("ID","UF_HID"),'limit'=>$count,'offset'=>$page*$count));
		while($hotel = $dbData->fetch())
		{	
			self::updateHotelData($hotel["UF_HID"]);
			//break;
			$res++;
		}
		return $res;
	}
	
	/*
	*  Обновить информацию об отеле расширенной информацией
	*
	*/
	public static function updateHotelData($HID)
	{ 
		$hotelRequest = new \Tourvisor\Requests\HotelRequest();
		$hotelRequest->hotelcode = $HID;
		$hotel = self::sendRequest($hotelRequest);
		
		if(is_array($hotel))
		{
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("filter"=>array("UF_HID"=> $HID),"select"=>array("ID","UF_PHOTO")))->fetch();
			if($dbData)
			{	
				$data = array(
					"UF_RATING"	=> $hotel["rating"],
					"UF_BUILD"	=> $hotel["build"],
					"UF_REPAIR"	=> $hotel['repair'],
					"UF_TERRITORY"=>$hotel['territory'],
					"UF_INROOM" => $hotel['inroom'],
					"UF_SERVICEFREE" => $hotel['servicefree'],
					"UF_SERVICEPAY" => $hotel['servicepay'],
					"UF_CHILD" => $hotel['child'],
					"UF_BEACH" => $hotel['beach'],
					"UF_PLACEMENT" => $hotel['placement'],
				);

				if(is_array($dbData["UF_PHOTO"]) && count($dbData["UF_PHOTO"])>0)
				{
					foreach($dbData["UF_PHOTO"] as $picID)
					{
						CFile::Delete($picID);
					}
				}

				
				if($hotel['coord1']!="" && $hotel['coord2']!="")
					$data["UF_COORDS"] = $hotel['coord1'].",".$hotel['coord2'];
				$ii = 0;$arFile=[];
				if(is_array($hotel["images"]) && count($hotel["images"])>0 )
				{
					foreach($hotel["images"] as $pic)
					{
						$referer = "https://anextours.ru/";
						$opts = array(
							   'http'=>array(
								   'header'=>array("Referer: $referer\r\n")
							   )
						   );
						$context = stream_context_create($opts);
						$buffer = file_get_contents("https:".$pic,false, $context);
						$fname[$ii] = $_SERVER["DOCUMENT_ROOT"].'/upload/hotel_pics/'.date("H-i-s").rand(1,1000).rand(1,1000).".jpg";
						file_put_contents($fname[$ii],$buffer);
						$arFile['n'.$ii] = CFile::MakeFileArray($fname[$ii]); 
						$ii++;						
					}
					$data["UF_PHOTO"] = $arFile;
				}
				
				$resAdd = $eclass::update($dbData["ID"],$data);
				foreach($fname as $fl)
					unlink($fl);
				
				//prf($data); 
			}
		}
		
	}
	
    public static function getHotelData($HID)
	{
		$res = array();
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass = $entity->getDataClass();
        
        $dbData = $eclass::getList(array("filter"=>array("UF_HID"=> $HID),"select"=>array("UF_BUILD","UF_REPAIR","UF_TERRITORY","UF_INROOM","UF_PHOTO")))->fetch();
        if($dbData)
        {	
            $pics = array();
            if(is_array($dbData['UF_PHOTO']) && count($dbData['UF_PHOTO'])>0)
            {
                foreach($dbData['UF_PHOTO'] as $i=>$flID)
                {
                    $fl = \CFile::GetFileArray($flID);
                       
                    $arFileTmp = CFile::ResizeImageGet(
                        $fl,
                        array("width" => 50 , "height" => 50),
                        BX_RESIZE_IMAGE_EXACT,
                        true, array()
                    );

                    $pics[$i]["small"] = $arFileTmp["src"];
                    $pics[$i]['big']   = $fl["SRC"];
                }
            }    
    
            $res = array(
                "build"	    => $dbData["UF_BUILD"],
                "repair"	=> $dbData['UF_REPAIR'],
                "territory" => $dbData['UF_TERRITORY'],
                "inroom"    => $dbData['UF_INROOM'],
                "photo"     => $pics,
            );
            
        }
        
        return $res;
		
	}
    
    public static function getHotelDataFull($HID)
	{
		$res = array();
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass = $entity->getDataClass();
        
        $dbData = $eclass::getList(array("filter"=>array("UF_HID"=> $HID),"select"=>array("*")))->fetch();
        if($dbData)
        {	
            $pics = array();
            if(is_array($dbData['UF_PHOTO']) && count($dbData['UF_PHOTO'])>0)
            {
                foreach($dbData['UF_PHOTO'] as $i=>$flID)
                {
                    $fl = \CFile::GetFileArray($flID);
                       
                    /*$arFileTmp = CFile::ResizeImageGet(
                        $fl,
                        array("width" => 50 , "height" => 50),
                        BX_RESIZE_IMAGE_EXACT,
                        true, array()
                    );
                    
                    $pics[$i]["small"] = $arFileTmp["src"];
                    */
                    if($fl["WIDTH"]>560 || $fl["HEIGHT"]>300)
                    {
                        $arFileTmp = CFile::ResizeImageGet(
                            $fl,
                            array("width" => 560 , "height" =>300),
                            BX_RESIZE_IMAGE_EXACT,
                            true, array()
                        );
                        $fl["SRC"] = $arFileTmp["src"];
                    }    
                    
                    
                    $pics[$i]['big']   = $fl["SRC"];
                }
            }    
			
			$countryStr=array();
			if ($dbData["UF_CID"]!="")
			{
				$countryList = TVToursTable::GetCountryList(array($dbData["UF_CID"]));
				$countryStr[] = $countryList[$dbData["UF_CID"]]["NAME"];
			}	
			$resortIDs = array	();
			if ($dbData["UF_TID"]!="")
			{
				$resortIDs[]=$dbData["UF_TID"];
				if($dbData["UF_STID"]!="")
					$resortIDs[]=$dbData["UF_STID"];
				$resortList=TVToursTable::GetResortList($resortIDs);
				$countryStr[] = $resortList[$dbData["UF_TID"]]["NAME"];
				if($dbData["UF_STID"]!="" && $resortList[$dbData["UF_STID"]])
					$countryStr[] = $resortList[$dbData["UF_STID"]]["NAME"];
				
			}
			$countryStr = implode(", ",$countryStr);
	
		
            $res = array(
				"name"			=> $dbData["UF_NAME"],
				"cid"			=> $dbData["UF_CID"],
				"country"		=> $countryStr,
				"stars"			=> $dbData["UF_SID"],
                "build"	    	=> $dbData["UF_BUILD"],
                "repair"		=> $dbData['UF_REPAIR'],
                "territory" 	=> $dbData['UF_TERRITORY'],
                "inroom"    	=> $dbData['UF_INROOM'],
                "service_free"  => $dbData['UF_SERVICEFREE'],
                "service_pay"   => $dbData['UF_SERVICEPAY'],
                "child"    		=> $dbData['UF_CHILD'],
                "beach"    		=> $dbData['UF_BEACH'],
				"placement" 	=> $dbData['UF_PLACEMENT'],
				"rating" 		=> $dbData['UF_RATING'],
                "photo"    		=> $pics,
				"coords"    	=> $dbData['UF_COORDS'],
            );
            
        }
        
        return $res;
		
	}
    
	public static function exportHotelData($HID)
	{
		$hotelRequest = new \Tourvisor\Requests\HotelRequest();
		$hotelRequest->hotelcode = $HID;
		$hotel = self::sendRequest($hotelRequest);
		return $hotel; 
	}
	
	/*************************************************************************************************************************/
	/**********************************************ПОИСК**********************************************************************/
	/*************************************************************************************************************************/
	/*
	*  Создание поискового запроса
	*
	*/
	public static function createSearchReq($params)
	{
		
		$result = false;
		$searchRequest = new \Tourvisor\Requests\SearchRequest();
		$searchRequest->country     	= (!empty($params['country'])) ? $params['country'] : self::$params['country'];
		$searchRequest->departure  	 	= (!empty($params['departure'])) ? $params['departure'] : self::$params['departure'];
		$searchRequest->nightsfrom 		= (!empty($params['nightsfrom'])) ? $params['nightsfrom'] : self::$params['nightsfrom'];
		$searchRequest->nightsto		= (!empty($params['nightsto'])) ? $params['nightsto'] : self::$params['nightsto'];
		$searchRequest->adults			= (!empty($params['adults'])) ? $params['adults'] : self::$params["adults"];
		$searchRequest->child 			= (!empty($params['child'])) ? $params['child'] : self::$params["children"];
		
		if(!empty($params['resort']))
			$searchRequest->regions   = $params['resort'];
		if(!empty($params['hotels']))
			$searchRequest->hotels   = $params['hotels'];
		if(!empty($params['stars']))
			$searchRequest->stars = $params['stars'];
		if(!empty($params['meal']))
			$searchRequest->meal = $params['meal'];
		if(!empty($params['datefrom']))
			$searchRequest->datefrom = $params['datefrom'];
		if(!empty($params['dateto']))
			$searchRequest->dateto = $params['dateto'];	
			
		if(!empty($params['pricefrom']))
			$searchRequest->pricefrom   = $params['pricefrom'];		
		if(!empty($params['priceto']))
			$searchRequest->priceto   = $params['priceto'];		
		if(!empty($params['childage1']))
			$searchRequest->childage1 = $params['childage1'];
		if(!empty($params['childage2']))
			$searchRequest->childage2 = $params['childage2'];
		if(!empty($params['childage3']))
			$searchRequest->childage3 = $params['childage3'];	
		if(!empty($params['hideregtours']) && $params['hideregtours']==1)
			$searchRequest->hideregular = 1;
		
		//prf($params);
			
		$result = self::sendRequest($searchRequest);
		return $result;
	}
	
	/*
	*  Получение статуса поискового запроса
	*
	*/
	public static function getReqStatus($reqID)
	{
		$res = false;
		$searchRequest = new \Tourvisor\Requests\SearchResultRequest();
		$searchRequest->requestid = $reqID;
		$searchRequest->type  = 'status';
		$res = self::sendRequest($searchRequest);
		return $res;
	}
	
	
	/*
	*  Получение результатов поискового запроса
	*
	*/
	public static function getReqResult($reqID, $onPage = 1000)
	{
		$result = false;
		$searchRequest = new \Tourvisor\Requests\SearchResultRequest();
		$searchRequest->requestid = $reqID;
		$searchRequest->type  = 'result';
		$searchRequest->page = 1;
		$searchRequest->onpage = $onPage;
		$result = self::sendRequest($searchRequest);
		return $result;
	}
	
	/*
	*  Актуализация данных о туре
	*
	*/
	public static function geTourAct($tid)
	{
		$result = array();
		$actRequest = new \Tourvisor\Requests\ActualizeRequest();
		$actRequest->tourid = $tid; 
		$actRequest->request = 1;
		
		$result = self::sendRequest($actRequest);
		return $result;
	}
	
	
	/*
	*  Детальная актуализация данных о туре
	*
	*/
	public static function geTourDetailAct($tid)
	{
		$result = array();
		$actRequest = new \Tourvisor\Requests\ActualizeDetailRequest();
		$actRequest->tourid = $tid; 

		$result = self::sendRequest($actRequest);
		return $result;
	}
	
	
	
	/*************************************************************************************************************************/
	/**********************************************СПРАВОЧНИКИ****************************************************************/
	/*************************************************************************************************************************/
	
	/*
	*  Получить список городов отправления
	*
	*/
	public static function getTvDepList()
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('departure');
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	/*
	*  Получить список стран
	*
	*/
	public static function getTvCountryList($dep = 1)
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('country');
		$listRequest->cndep = $dep;
		$list = self::sendRequest($listRequest);
		return $list;
	}
		
	/*
	*  Получить список курортов
	*
	*/
	public static function getTvRegionList($cid = false)
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('region');
		if($cid)
			$listRequest->regcountry = $cid;
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	/*
	*  Получить список вложенных курортов
	*
	*/
	public static function getTvSubRegionList($cid = false)
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('subregion');
		if($cid)
			$listRequest->regcountry = $cid;
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	
	/*
	*  Получить список отелей
	*
	*/
	public static function getTvHotelList($params)
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('hotel');
		$listRequest->hotcountry = 	$params["CID"];
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	
	
	/*
	*  Получить список типов питания
	*
	*/
	public static function getTvMealList()
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('meal');
		
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	/*
	*  Получить список категорий отеля
	*
	*/
	public static function getTvStarList()
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('stars');
		
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	
	/*
	*  Получить список операторов
	*
	*/
	public static function getTvOperatorList($dep=false,$cid=false)
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('operator');
		if($dep)
			$listRequest->flydeparture = $dep;
		if($cid)
			$listRequest->flycountry = $cid;
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	/*
	*  Получить курсы валют операторов
	*
	*/
	public static function getTvCurList()
	{
		$list = array();
		$listRequest = new \Tourvisor\Requests\ListRequest();
		$listRequest->type = array('currency');
		
		$list = self::sendRequest($listRequest);
		return $list;
	}
	
	
	/*************************************************************************************************************************/
	/**********************************************ОТПРАВКА ЗАПРОСА***********************************************************/
	/*************************************************************************************************************************/
	public static function sendRequest($data)
	{
		$res = array();
		$tourvisor = new Tourvisor(new Client(self::$login, self::$pass));
		try{
			$result = $tourvisor->getResult($data);
			
			$res = json_decode(json_encode($result),true);
		}
		catch(Exception $ex)
		{
			
			$res["ERROR"] =  $ex->getMessage();
		}
		
		return $res;
	}
	
	/*************************************************************************************************************************/
	/**********************************************ФОРМА ПОИСКА***************************************************************/
	/*************************************************************************************************************************/
	public static function prepareForm($arParams)
	{
	
		//prf($arParams);
		$from 		= (is_array($arParams) && isset($arParams["FROM"])) 	? $arParams["FROM"] 	:  self::$params["from"];
        $country 	= (is_array($arParams) && isset($arParams["COUNTRY"]))  ? $arParams["COUNTRY"]  :  self::$params["country"];
        $resort 	= (is_array($arParams) && isset($arParams["RESORT"])) 	? explode(",",$arParams["RESORT"]) 	:  self::$params["resort"];
        $hotel 		= (is_array($arParams) && isset($arParams["HOTEL"])) 	? $arParams["HOTEL"] 	:  self::$params["hotel"];
		$adults     = (is_array($arParams) && isset($arParams["ADULTS"])) 	? $arParams["ADULTS"] 	:  self::$params["adults"];
		$kids       = (is_array($arParams) && isset($arParams["KIDS"])) 	? $arParams["KIDS"] 	:  self::$params["children"];
		$nightsFrom = (is_array($arParams) && isset($arParams["NIGHTS_FROM"])) 	? $arParams["NIGHTS_FROM"] 	: self::$params["nightsfrom"];
		$nightsTo   = (is_array($arParams) && isset($arParams["NIGHTS_TO"])) 	? $arParams["NIGHTS_TO"] 	: self::$params["nightsto"];
		$dateFrom   = (is_array($arParams) && isset($arParams["DATE_FROM"])) 	? $arParams["DATE_FROM"] 	: date("Y-m-d",strtotime("+1 day"));
		$dateTo     = (is_array($arParams) && isset($arParams["DATE_TO"])) 		? $arParams["DATE_TO"] 		: date("Y-m-d",strtotime("+14 day"));
		$dateFromTo = (is_array($arParams) && isset($arParams["DATE_FROM"])) 	? $arParams["DATE_FROM"] 	: date("d.m.Y",strtotime("+1 day"));
		$dateFromTo .= (is_array($arParams) && isset($arParams["DATE_TO"])) 	? " - ".$arParams["DATE_TO"] 	: " - ".date("d.m.Y",strtotime("+14 day"));
		$child_year1= (is_array($arParams) && isset($arParams["CHILD_YEAR1"])) 	? $arParams["CHILD_YEAR1"] 	: "";
		$child_year2= (is_array($arParams) && isset($arParams["CHILD_YEAR2"])) 	? $arParams["CHILD_YEAR2"] 	: "";
		$child_year3= (is_array($arParams) && isset($arParams["CHILD_YEAR3"])) 	? $arParams["CHILD_YEAR3"] 	: "";
		$stars      = (is_array($arParams) && isset($arParams["STARS"])) 		? $arParams["STARS"] 	: 0;
		$meal       = (is_array($arParams) && isset($arParams["MEAL"]) && $arParams["MEAL"]!=999) ? $arParams["MEAL"] : 0;
		$price_from = (is_array($arParams) && isset($arParams["PRICE_FROM"])) 	? $arParams["PRICE_FROM"] 	: "";
		$price_till = (is_array($arParams) && isset($arParams["PRICE_TILL"])) 	? $arParams["PRICE_TILL"] 	: "";
		$hotels     = (is_array($arParams) && isset($arParams["HOTELS"])) 	    ? $arParams["HOTELS"] 	: false;
		

		if ($nightsTo<$nightsFrom)
		{
			$n = 24 - $nightsFrom;
			if ($n>7) 
				$n = 7;
			if( $nightsFrom>$n)
			{
				$nightsTo = $nightsFrom;	
				$nightsFrom = $n;
			}
			else
				$nightsTo = $n;	
		}	
		//prf($nightsFrom." - ".$nightsTo);
        $out = array(
            "from"=>$from,
            "country" => $country,
            "resort"=> $resort,
            "hotel"=> $hotel,
			"hotel_name"=> "",
            "date_from" => $dateFrom,
			"date_till" => $dateTo,
			"date_from_till" => $dateFromTo,
            "nights_from" => $nightsFrom ,
            "nights_till" => $nightsTo,
			"count_people" => $adults,
			"count_child"	=>$kids,
			"child_year1"	=>$child_year1,
			"child_year2"	=>$child_year2,
			"child_year3"	=>$child_year3,
			"stars"			=>$stars,
			"meal"			=>$meal,
			"price_from"   => $price_from,
			"price_till"   => $price_till,
			"hotels"	   => $hotels,
			"hotel_names"  => []
        );

		if($hotel) 
		{
			$hotelItem = self::GetHotelList(array("CID"=>$country,"HID"=>$hotel));
			if(count($hotelItem)>0)
				$out["hotel_name"] = $hotelItem[0]["name"];
		}
		elseif($hotels) 
		{
			$hotelItems = self::GetHotelList(array("CID"=>$country,"HID"=>$hotels));
			foreach($hotelItems as $hotelItem)
				$out["hotel_names"][$hotelItem["id"]] = $hotelItem["name"];
		}
		
        $out["fromList"]         = self::GetFromList(); 
		$out["fromListFull"]     = self::GetFromListFull();
		$out["countryList"]      = self::GetCountryList();
        $out["resortList"]       = self::GetResortList(array("CID"=>$country));
        $out["hotelList"]        = self::GetHotelList(array("CID"=>$country,"MAX"=>100));
        $out["hotelStarList"]    = self::GetHotelStarList();
        $out["foodList"]         = self::GetFoodList();
		
		return $out; 
	}	
	
	public static function GetFromList ($arParams=false)
	{   
		
        $obCache = new \CPHPCache();
		$cacheID = "/search/fromlist";
        $frid = array();
		$arF  = array("UF_ACTIVE"=>true);
        $arS  = array("UF_DEPID","UF_NAME");
        if ($obCache->InitCache(360000, serialize($arF)."_2", $cacheID))
        {
            $frid = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$depHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,'filter'=>$arF ,"order"=>array("UF_SORT"=>"ASC","UF_NAME"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$frid[$data["UF_DEPID"]]=$data["UF_NAME"];
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$depHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($frid);
        } 
                 
		return $frid;
	}
	
	public static function GetFromListFull ($arParams=false)
	{   
		
        $obCache = new \CPHPCache();
		$cacheID = "/search/fromlist_full";
        $frid = array();
		$arF  = array("UF_ACTIVE"=>true);
        $arS  = array("UF_DEPID","UF_NAME","UF_NAME2");
        if ($obCache->InitCache(360000, serialize($arF)."_3", $cacheID))
        {
            $frid = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$depHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,'filter'=>$arF ,"order"=>array("UF_SORT"=>"ASC","UF_NAME"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$frid[$data["UF_DEPID"]]=array("NAME"=>$data["UF_NAME"],"NAME2"=>$data["UF_NAME2"]);
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$depHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($frid);
        } 
                 
		return $frid;
	}
	
	public static function GetCountryList ($arParams=false)
	{
		$obCache = new \CPHPCache();
		$cacheID = "/search/countrylist";
        $cid = array();
		$arF  = array("UF_ACTIVE"=>true);
        $arS  = array("UF_CID","UF_NAME");
        if ($obCache->InitCache(360000, serialize($arF)."_3", $cacheID))
        {
            $cid  = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$contryHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,'filter'=>$arF , "order"=>array("UF_SORT"=>"ASC","UF_NAME"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$cid [$data["UF_CID"]]=$data["UF_NAME"];
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$contryHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($cid );
        } 
                 
		return $cid ;
	}	
	
	public static function GetResortList ($arParams=false)
	{
		$obCache = new \CPHPCache();
		$cacheID = "/search/resortlist";
        $rid = array();
		$arF  = array("UF_CID"=>$arParams["CID"]);
        $arS  = array("UF_TID","UF_NAME");
        if ($obCache->InitCache(360000, serialize($arF).'_2', $cacheID))
        {
            $rid  = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$regHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,"filter"=>$arF,"order"=>array("UF_SORT"=>"ASC","UF_NAME"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$rid[] = array("id"=> $data["UF_TID"], "name" => $data["UF_NAME"]);
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$regHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($rid );
        } 
                 
		return $rid ;
	}

	public static function GetHotelList ($arParams=false)
	{
		$obCache = new \CPHPCache();
		$cacheID = "/search/hotellist";
        $rid  = array();
		$max  = 0; 
		$arF  = array("UF_CID"=>$arParams["CID"]);
		if($arParams["HID"]!="")
			$arF["UF_HID"] = $arParams["HID"];
		if($arParams["TID"]!="")
			$arF["UF_TID"] = $arParams["TID"];
		if($arParams["%NAME"]!="")
			$arF["%UF_NAME"] = $arParams["%NAME"];
		
		if(!empty($arParams["STARS"]))
			$arF["UF_SID"] = $arParams["STARS"];
		
        $arS  = array("UF_HID","UF_NAME");
		if (!empty($arParams["MAX"]))
			$max=$arParams["MAX"];
        if ($obCache->InitCache(0, serialize($arF).'_'.$max, $cacheID))
        {
            $rid  = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$filter = array("select"=> $arS,"filter"=>$arF,"order"=>array("UF_SORT"=>"ASC","UF_NAME"=>"ASC"));
			if ($max>0)
				$filter['limit'] = $max;
			$dbData = $eclass::getList($filter);
			while($data = $dbData->fetch())
			{	
				$rid[] = array("id"=> $data["UF_HID"], "name" => $data["UF_NAME"]);
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$hotelHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($rid );
        } 
                 
		return $rid ;
	}		
	
	public static function GetHotelStarList ($arParams=false)
	{
		$obCache = new \CPHPCache();
		$cacheID = "/search/starlist";
        $sid = array();
		$arF  = array();
        $arS  = array("UF_SID","UF_NAME");
        if ($obCache->InitCache(360000, serialize($arF)."_1", $cacheID))
        {
            $sid  = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$starHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,"order"=>array("UF_SORT"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$sid [$data["UF_SID"]]=$data["UF_NAME"];
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$starHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($sid );
        } 
                 
		return $sid ;
	}	
	
	public static function GetFoodList ($arParams=false)
	{
		$obCache = new \CPHPCache();
		$cacheID = "/search/foodlist";
        $fid = array();
		$arF  = array();
        $arS  = array("UF_MID","UF_NAME");
        if ($obCache->InitCache(360000, serialize($arF)."_1", $cacheID))
        {
            $fid  = $obCache->GetVars();
        }
        elseif ($obCache->StartDataCache())
        {
			global $CACHE_MANAGER;
			
            $CACHE_MANAGER->StartTagCache($cacheID);
			
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$mealHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			
			$dbData = $eclass::getList(array("select"=> $arS,"order"=>array("UF_SORT"=>"ASC")));
			while($data = $dbData->fetch())
			{	
				$fid [$data["UF_MID"]]=$data["UF_NAME"];
			}	
			
			$CACHE_MANAGER->RegisterTag("hl_id_".self::$mealHL);
			$CACHE_MANAGER->EndTagCache();
				
            $obCache->EndDataCache($fid );
        } 
                 
		return $fid ;
	}	
	
	/*************************************************************************************************************************/
	/*****************************************СОХРАНЕНИЕ ПОИСКА***************************************************************/
	/*************************************************************************************************************************/
	public static function saveSearchForm ($arParams)
	{ 
		$res = "";
		\Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$saveRequestHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass  = $entity->getDataClass();
		$code    = randString(10, ["abcdefghijklnmopqrstuvwxyz","0123456789"]);
		$eclass::add(["UF_CODE"=>$code,"UF_REQUEST"=>json_encode($arParams)]);
		
		$res = $code;
		return $res;
	}
	
	public static function getSearchForm ($code)
	{ 
		$res = [];
		\Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$saveRequestHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass  = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>array("=UF_CODE"=> $code)))->fetch();
        if($dbData)
        {
			$res= json_decode($dbData["UF_REQUEST"],true);
		}	
		
		return $res;
	}
	
	/*************************************************************************************************************************/
	/**********************************************ГОРЯЩИЕ ТУРЫ***************************************************************/
	/*************************************************************************************************************************/
	
	/*
	*  Обновить информацию об отеле расширенной информацией
	*
	*/
	public static function getHotTours($params)
	{
		$res = array();
		$hotRequest = new \Tourvisor\Requests\HotToursRequest();
		$hotRequest->city  		= $params["city"];
		$hotRequest->items 		= (!empty($params["items"])) ? $params["items"] : 20;
		if (!empty($params["countries"]))
			$hotRequest->countries	= $params["countries"];
		$hotRequest->picturetype = 1;	
		$hot = self::sendRequest($hotRequest);
	
		if(is_array($hot))
		{
			$res = $hot;
		}
		return $res;
	}	
	
	public static function updateHotTours($from,$cid)
	{
		$params=array(
			"city"=>$from,
			"items"=>18,
			"countries"=>array($cid)
		);
		$res  = self::getHotTours($params);
		//prf($res);
		$activeTours = array();
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		
		$dbData = $eclass::getList(array("filter"=>array("UF_CID"=>$cid,"UF_FROM"=>$from,"UF_ACTIVE"=>true)));
		while($data = $dbData->fetch())
		{	
			if ($data["UF_ACT_DATE"]!="")
			{
				$eclass::update($data["ID"],array("UF_ACTIVE"=>false));
				$activeTours[$data["UF_TID"]]=$data["ID"];
			}	
			else	
				$eclass::delete($data["ID"]);
		}	
		
		
		
		$date =  new  \Bitrix\Main\Type\DateTime();
		if ($res["count"]>0)
		{	
			foreach($res["tours"] as $tour)
			{
				if (!empty($activeTours[$tour["tourid"]]))
					$eclass::update($activeTours[$tour["tourid"]],array("UF_ACTIVE"=>true));
				else
				{	
					$data = array(
						"UF_ACTIVE"		=> 	true,
						"UF_DATE"		=> 	$date,
						"UF_FROM"		=> 	$tour["departurecode"],
						"UF_TID"		=> 	$tour["tourid"],
						"UF_CID"		=> 	$tour["countrycode"],
						"UF_RID"		=> 	$tour["hotelregioncode"],
						"UF_HID"		=> 	$tour["hotelcode"],
						"UF_OLD_PRICE"	=> 	$tour["priceold"],
						"UF_PRICE"		=> 	$tour["price"],
						"UF_FLY_DATE"	=> 	$tour["flydate"],
						"UF_NIGHTS"		=> 	$tour["nights"],
						"UF_MEAL"		=> 	$tour["meal"],
						"UF_FUEL"		=> 	$tour["fuelcharge"],
						"UF_OPERATOR"   => 	$tour["operatorname"]
					);
					$eclass::add($data);
				}
			}
		}
		
	}	
	
	public static function getListHotTours($from, $cid, $rand = false,$limit = 18)
	{
		
		$res  = array();
		 
		$filter = array("UF_FROM"=>$from,"UF_ACTIVE"=>true,">UF_NIGHTS"=>0);
		
		if ($cid!="" && $cid!="all")
			$filter["UF_CID"]=$cid;
		$order = array("ID");
		if ($rand)
			$order = array("RAND");
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		if (!$rand)
			$dbData = $eclass::getList(array("filter"=>$filter,"order"=>$order));
		else
			$dbData = $eclass::getList(array("filter"=>$filter,"order"=>$order,'runtime' => array( new \Bitrix\Main\Entity\ExpressionField('RAND', 'RAND()')),'limit'=>$limit));	
		
		
		while($data = $dbData->fetch())
		{	
			$res[]=$data;
		}	
		
		return $res;
		
	}	
	
	public static function getHotTour($tid, $full=false)
	{
		 
		$res  = array();
		
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		
		$dbData = $eclass::getList(array("filter"=>array("UF_TID"=>$tid)));
		if($row = $dbData->fetch())
		{	
			
			$dateTo   = "";
			$dateFrom = "";
			if ($row["UF_FLY_DATE"] !="")
			{
				$dateFrom =  $row["UF_FLY_DATE"];
				$dt =  new \Bitrix\Main\Type\Date($row["UF_FLY_DATE"]);
           		$dt->add($row["UF_NIGHTS"]." day");
          	 	$dateTo   =  $dt->toString();
			}
            if (!$full)

            {    
                $row["UF_PRICE"] = number_format($row["UF_PRICE"],2, '.', ' ');
                $row["UF_PRICE"] = str_replace(".00","",$row["UF_PRICE"]);
            }
            $FROM_LIST = TVToursTable::GetFromList(array($row["UF_FROM"]));
			$MEAL_LIST = TVToursTable::GetAllMealList();
			$meal = "";
			$row["UF_MEAL"] = ToLower($row["UF_MEAL"]);
			foreach($MEAL_LIST as $m)
				if (ToLower($m["UF_NAME"]) == $row["UF_MEAL"])
					$meal = $m["UF_RUSFULL"];
			
			
            $res["hid"]       	 	= $row["UF_HID"];
            $res["tid"]       	 	= $row["UF_TID"];
            $res["date_from"]	   	= $dateFrom;
            $res["date_to"]  		= $dateTo; 
            $res["nights"]   		= $row["UF_NIGHTS"];
            $res["price"]     		= $row["UF_PRICE"];
			
			$res["departure"]   	= $FROM_LIST[$row["UF_FROM"]]["NAME"];
			$res["meal_name"]		= $meal;
			$res["fuel"]			= $row["UF_FUEL"];
			$res["room"]			= ($row["UF_PLACEMENT"]!="") ? $row["UF_PLACEMENT"]  : "";
			$res["adults"]          = ($row["UF_ADULTS"]) ? $row["UF_ADULTS"] : 2;
            $res["child"]           = ($row["UF_CHILD"]) ? $row["UF_CHILD"] : 0;
            if ($full)
            {
				$res["real_price"]     	= $row["UF_REAL_PRICE"];
                $res["operator"]        = $row["UF_OPERATOR"];
                $res["operator_link"]   = $row["UF_LINK"];
                
                if ($row["UF_CID"])
                {    
                    $COUNTRY_LIST  	= 	TVToursTable::GetCountryList(array($row["UF_CID"]));
                    $res["country"] =   $COUNTRY_LIST[$row["UF_CID"]]["NAME"];
                }   
                if ($row["UF_RID"])
                {    
                    $RESORT_LIST	= 	TVToursTable::GetResortList(array($row["UF_RID"])); 
                    $res["resort"]  =   $RESORT_LIST[$row["UF_RID"]]["NAME"];
                } 
                if ($row["UF_HID"])
                {    
                    $HOTEL_LIST     = 	TVToursTable::GetHotelListHot(array($row["UF_HID"]));
                    $res["hotel"]   =   $HOTEL_LIST[$row["UF_HID"]]["NAME"];
                } 
                
                if ($row["UF_PLACEMENT"])
                {
                    $plArr = explode("/",$row["UF_PLACEMENT"]);
                    $res["room"]        = $plArr[0] ; 
                    $res["placement"]   = $plArr[1] ; 
                }    
                
            }    
            
			
		}	
		
		return $res;
		
	}	

	public static function updateHotTour($tid,$arParams)
	{
		 
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		
		$dbData = $eclass::getList(array("filter"=>array("UF_TID"=>$tid)));
		if($row = $dbData->fetch())
		{		
			 $eclass::update($row["ID"],$arParams);
		}
	}	
	
	public static function removeOldTours()
	{
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$date =  new \Bitrix\Main\Type\DateTime();
		$date->add("-T2H");
		echo $date->toString();
		$dbData = $eclass::getList(array("filter"=>array("UF_ACTIVE"=>false,"<UF_ACT_DATE"=>$date)));
		while($row = $dbData->fetch())
		{		
			$eclass::delete($row["ID"]);
		}
	}
}

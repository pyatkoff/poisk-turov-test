<?
class HotelParser
{
    public const workHL          = 26;
    public const hotelItemsHL    = 36;


    public const workStatusQueue = 44;
    public const workStatusWork  = 45;
    public const workStatusDone  = 46;
    public const workStatusError = 47;

    public const timeout         = 100;

    public static function addWork($country=false,$params=false)
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass = $entity->getDataClass();
        
        $data = array(
            "UF_COUNTRY"  => $country,
            "UF_DATE"     => new  \Bitrix\Main\Type\DateTime(),
            "UF_PARAMS"   => ($params) ? json_encode($params) : "",
            "UF_STATUS"   => self::workStatusQueue,
            "UF_STEP"     => 1
        );
        $eclass::add($data);
    }

    public static function setupParserWork()
    {
        $countryList = self::updateCountryList();
        foreach($countryList as $country)
        {
            self::addWork($country,false);
        }
   
    }
    public static function workAgent()
	{
        //return; 
        //\Bitrix\Main\Config\Option::set('anytour.hotel_parser','active_work',false);
        $aw = \Bitrix\Main\Config\Option::get('anytour.hotel_parser','active_work',false);
        if (!$aw || (time() - $aw) > 60*10) //нет активных задач или последняя задача на выполнении более 10 мин
		{
            $timer = microtime(true);
            \Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$works = [];
			$workDB = $eclass::getList(['order'=>["ID"=>"ASC"],'filter' => ["UF_STATUS"=>[self::workStatusQueue,self::workStatusWork]], 'select' => ["ID"],'limit'=>10]);
			while ($work = $workDB->fetch())
			{
				$works[]=$work["ID"];
			}
            if(count($works)>0)
            {
                foreach($works as $workID)
                {
                    self::execWork($workID,$timer);
                    if ( !self::checkTimer($timer))
                    {
                        break;
                    }
                }
            }
        }    
    }    

    public static function execWork($id,$timer)
	{
        if($id>0)
        {
            \Bitrix\Main\Config\Option::set('anytour.hotel_parser','active_work',time());
            $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
            $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
            $eclass = $entity->getDataClass();

            $hlblockItems = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::hotelItemsHL)->fetch();
			$entityItems  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblockItems);
			$eclassItems = $entityItems->getDataClass();

            $workDB = $eclass::getList(
				[
					'filter' => [
						"=ID" => $id,
						"UF_STATUS"=>[self::workStatusQueue,self::workStatusWork]
                    ], 
					'select' => ["ID","UF_STATUS","UF_COUNTRY","UF_PARAMS","UF_STEP"]
                ]
			);
			$work = $workDB->fetch();
			if($work)
            {
                self::add2log("начало задачи ".$work["ID"]);
                $isStart = false;
                $stop = false;
                $updateArr = [];
                if($work["UF_STATUS"] == self::workStatusQueue){
                    $updateArr["UF_STATUS"]=self::workStatusWork;
                    $updateArr["UF_DATE_START"]=new \Bitrix\Main\Type\DateTime();
                    $isStart = true;    
                }
                $eclass::update($work["ID"],$updateArr);

                $params = ($work["UF_PARAMS"]!="") ? json_decode($work["UF_PARAMS"],true) : "" ;
                $country = intval($work["UF_COUNTRY"]);
                $step = intval($work["UF_STEP"]);
                if($step<=0)
                    $step = 1;
  
                if($step==1)
                {
                    $resUpdate = self::updateHotelList($work["ID"],$country);
                    if($resUpdate["OK"])
                    {
                        $step = 2;
                        self::add2log("задача ".$work["ID"].". обновлено ". $resUpdate["OK"]);
                        $eclass::update($work["ID"],[
                            "UF_STEP"=>$step,
                            ]
                        );

                    }
                    else
                    {
                        self::add2log("задача ".$work["ID"].". ошибка");
                        $eclass::update($work["ID"],[
                            "UF_STATUS"=>self::workStatusError,
                            "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                            ]
                        );
                    }
                } 

                if( self::checkTimer($timer) && $step==2)
                {
                    require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
                    $updateItems = $eclassItems::getList(['filter'=>["=UF_WORK" => $work["ID"], "UF_DONE"=>false],'select'=>["ID","UF_HID"]]);
                    while($updateItem = $updateItems->fetch())
                    {	
                        self::add2log("задача ".$work["ID"].". шаг 2. задание ".$updateItem["UF_HID"]);
                        TvApi::updateHotelData($updateItem["UF_HID"]);
                        self::add2log("задача ".$work["ID"].". шаг 2. задание ".$updateItem["UF_HID"].". конец");
                        $eclassItems::update($updateItem["ID"],["UF_DONE"=>true,"UF_DATE_DONE"=>new \Bitrix\Main\Type\DateTime()]);
                        if( !self::checkTimer($timer)) 
                        {
                            $stop = true;
                            break;
                        }    
                    }
                }
                else
                    $stop = true;

               

                /*if($work["UF_TYPE"]==self::workTypeFull )
                {
                    $page = (is_array($params) && $params["PAGE"]) ? intval($params["PAGE"]) : 0;
                    $count = 200;
                    require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');

                    self::add2log("задача ".$work["ID"].". страница ".$page);
                    $resUpdate = TvApi::updateHotelsFullData($country,$page,$count);
                    self::add2log("задача ".$work["ID"].". обновлено ". $resUpdate);

                    if($resUpdate==$count)
                    {
                        $page++;
                        $params = json_encode(["PAGE"=>$page]);
                        $eclass::update($work["ID"],["UF_PARAMS"=>$params]);
                    }
                    else
                    {
                        $eclass::update($work["ID"],[
                            "UF_STATUS"=>self::workStatusDone,
                            "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                            ]
                        );
                    }
    
                }
                elseif($work["UF_TYPE"]==self::workTypeList)
                {
                    $resUpdate = TvApi::updateHotelList(array("CID"=>$country));
                    if($resUpdate["OK"])
                    {
                        self::add2log("задача ".$work["ID"].". обновлено ". $resUpdate["OK"]);
                        $eclass::update($work["ID"],[
                            "UF_STATUS"=>self::workStatusDone,
                            "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                            ]
                        );
                    }
                    else
                    {
                        self::add2log("задача ".$work["ID"].". ошибка");
                        $eclass::update($work["ID"],[
                            "UF_STATUS"=>self::workStatusError,
                            "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                            ]
                        );
                    }
                }
                */
                self::add2log("завершение итерации задачи ".$work["ID"]);
                if(!$stop)
                {    
                    $eclass::update($work["ID"],[
                        "UF_STATUS"=>self::workStatusDone,
                        "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                        ]
                    );
                    self::add2log("задача ".$work["ID"]." завершена");
                }
                
            }
            \Bitrix\Main\Config\Option::set('anytour.hotel_parser','active_work',false);
        }
    }

    public static function updateHotelList($workID,$countryID)
	{
		$res = array();
        
		require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
		$hotelList = TvApi::getTvHotelList(["CID"=>$countryID]);
		
		if(is_array($hotelList) && empty($hotelList["ERROR"]))
		{	
			\Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(TvApi::$hotelHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();

            $hlblockItems = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::hotelItemsHL)->fetch();
			$entityItems  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblockItems);
			$eclassItems = $entityItems->getDataClass();
            
            $hotelsHID = [];
            $hotelsName = [];
            $hotelsHIDFound = [];
			$hotelsDB = $eclass::getList(['filter'=>["=UF_CID"=>$countryID],'select'=>["ID","UF_HID","UF_NAME"]]);
			while($hotel = $hotelsDB->fetch())
			{	
				$hotelsHID[$hotel["UF_HID"]] = $hotel["ID"];
                $hotelsName[$hotel["UF_HID"]] = $hotel["UF_NAME"];
			}
         
			$count = 0;
			foreach($hotelList["hotels"] as $hotel)
			{
				
				$data = [
					"UF_HID"	=> $hotel["id"],
					"UF_NAME"	=> $hotel["name"],
					"UF_CID"	=> $countryID,
					"UF_SID"	=> $hotel["stars"],
					"UF_TID"	=> $hotel["regioncode"],
					"UF_STID"	=> $hotel["subregioncode"],
					"UF_RATE"	=> $hotel["rating"],
					"UF_IS_ACTIVE"	=> ($hotel["is_active"]==1) ? true : false,
					"UF_IS_CITY"	=> ($hotel["is_city"]==1)   ? true : false,
					"UF_IS_BEACH"	=> ($hotel["is_beach"]==1)  ? true : false,
					"UF_IS_FAMILY"	=> ($hotel["is_family"]==1) ? true : false,
					"UF_IS_RELAX"	=> ($hotel["is_relax"]==1)  ? true : false,
					"UF_IS_HEALTH"	=> ($hotel["is_health"]==1) ? true : false,
					"UF_IS_DELUXE"	=> ($hotel["is_deluxe"]==1) ? true : false,
					"UF_SORT"		=> 500
                ];
                $addItem = false;

				if(!empty($hotelsHID[$hotel["id"]]))
				{	
					unset($data["UF_SORT"]);
					$resAdd = $eclass::update($hotelsHID[$hotel["id"]],$data);
					$hotelsHIDFound[]=$hotel["id"];
                    if($hotelsName[$hotel["id"]]!=$hotel["name"])
                        $addItem = ["UF_HID"=>$hotel["id"]];
				}	
				else
                {
					$resAdd = $eclass::add($data);
                    $addItem = ["UF_HID"=>$hotel["id"]];
                }
				if($addItem)
                {
                    $updateItem = $eclassItems::getRow(['filter'=>["=UF_HID"=>$addItem["UF_HID"],"=UF_WORK"=>$workID],'select'=>["ID"]]);
                    if(!$updateItem)
                    {
                        $addItem["UF_DATE"] = new  \Bitrix\Main\Type\DateTime();
                        $addItem["UF_WORK"] = $workID;
                        $eclassItems::add($addItem);
                    }
                }
				$count++;
				
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

    public static function updateCountryList()
	{
        $res = [];
        require_once($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/tv_api/tvapi.php');
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(TvApi::$contryHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass = $entity->getDataClass();
        
        $countryDB = $eclass::getList(['filter'=>["=UF_ACTIVE"=>true,"=UF_UPDATE_HOTELS"=>true],'select'=>["ID","UF_CID"]]);
        while($country = $countryDB->fetch())
        {	
            $res[] = $country["UF_CID"];
        }
        return $res;
    }

    public static function checkTimer($timer)
	{
        return self::timeout > (microtime(true) - $timer);
    }

    public static function add2log($text,$debug=false)
	{
		if($debug)
			$fp = fopen($_SERVER["DOCUMENT_ROOT"].'/upload/hotel_parser/debug_log.txt', "ab");
		else	
			$fp = fopen($_SERVER["DOCUMENT_ROOT"].'/upload/hotel_parser/log.txt', "ab");
		if (is_array($text))
		  fputs($fp, date("d.m.Y H:i:s")."\n".print_r($text,true)."\n\n");
		else
		  fputs($fp, date("d.m.Y H:i:s")."\n".$text."\n\n");
		fclose($fp);
	}


}
?>
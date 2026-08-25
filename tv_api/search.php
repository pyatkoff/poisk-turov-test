<?
use Bitrix\Main\Loader;
use Bitrix\Main\Entity;

class RequestTable extends Entity\DataManager
{
    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'request_table';
    }
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
            )),
            new Entity\BooleanField('ACTIVE'),
            new Entity\DateTimeField('TIME'),
            new Entity\IntegerField('PERCENT'),
            new Entity\IntegerField('TCOUNT'),
			new Entity\IntegerField('DEPARTURE')
        );
    }
}

class RequestDataTable extends Entity\DataManager
{
    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'request_data_table';
    }
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('REQID', array(
                'primary' => true,
            )),
            new Entity\DateTimeField('TIME'),
			new Entity\IntegerField('DEPARTURE'),
            new Entity\IntegerField('COUNTRY'),
            new Entity\StringField('DATE_FROM'),
			new Entity\StringField('DATE_TO'),
			new Entity\IntegerField('DAYS_FROM'),
			new Entity\IntegerField('DAYS_TO'),
			new Entity\IntegerField('ADULTS'),
			new Entity\IntegerField('CHILD'),
			new Entity\IntegerField('CHILD_YEAR_1'),
			new Entity\IntegerField('CHILD_YEAR_2'),
			new Entity\IntegerField('CHILD_YEAR_3'),
			new Entity\IntegerField('PRICE_FROM'),
			new Entity\IntegerField('PRICE_TO'),
			new Entity\IntegerField('STAR'),
			new Entity\IntegerField('MEAL')
        );
    }
	
	public static function save($requestId,$params)
    {
		$data = array(
			'REQID'			=>	$requestId,
			'TIME'			=>  new \Bitrix\Main\Type\DateTime(),
			'DEPARTURE'		=>  (!empty($params["departure"]) && intval($params["departure"])>0) ? intval($params["departure"]) : 0,
			'COUNTRY'		=>  (!empty($params["country"]) && intval($params["country"])>0) ? intval($params["country"]) : 0,
			'DATE_FROM'		=>  (!empty($params["datefrom"])) ? $params["datefrom"] : "",
			'DATE_TO'		=>  (!empty($params["dateto"]))   ? $params["dateto"] : "",
			'DAYS_FROM'		=>  (!empty($params["nightsfrom"]) && intval($params["nightsfrom"])>0) ? intval($params["nightsfrom"]) : 0,
			'DAYS_TO'		=>  (!empty($params["nightsto"]) && intval($params["nightsto"])>0) ? intval($params["nightsto"]) : 0,
			'ADULTS'		=>  (!empty($params["adults"]) && intval($params["adults"])>0) ? intval($params["adults"]) : 0,
			'CHILD'			=>  (!empty($params["child"]) && intval($params["child"])>0) ? intval($params["child"]) : 0,
			'CHILD_YEAR_1'	=>  (!empty($params["childage1"]) && intval($params["childage1"])>0) ? intval($params["childage1"]) : 0,
			'CHILD_YEAR_2'	=>  (!empty($params["childage2"]) && intval($params["childage2"])>0) ? intval($params["childage2"]) : 0,
			'CHILD_YEAR_3'	=>  (!empty($params["childage3"]) && intval($params["childage3"])>0) ? intval($params["childage3"]) : 0,
			'PRICE_FROM'	=>  (!empty($params["pricefrom"]) && intval($params["pricefrom"])>0) ? intval($params["pricefrom"]) : 0,
			'PRICE_TO'		=>  (!empty($params["priceto"]) && intval($params["priceto"])>0) ? intval($params["priceto"]) : 0,
			'STAR'			=>  (!empty($params["stars"]) && intval($params["stars"])>0) ? intval($params["stars"]) : 0,
			'MEAL'			=>  (!empty($params["meal"]) && intval($params["meal"])>0) ? intval($params["meal"]) : 0,
		);
		
		self::add($data);
		if (is_array($params['resort']) && count($params['resort'])>0)
		{
			RequestDataResortTable::save($requestId,$params['resort']);
		}
		if (is_array($params['hotels']) && count($params['hotels'])>0)
		{
			RequestDataHotelTable::save($requestId,$params['hotels']);
		}
	}

	public static function getByID($requestId)
    {
		$res = array();
		$res = self::getList(array('select' => array('*'),'filter' => array("REQID"=>$requestId)))->fetch(); 
		return $res;
	}
}

class RequestDataResortTable extends Entity\DataManager
{
    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'request_data_resort_table';
    }
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
				'autocomplete' => true
            )),
			new Entity\IntegerField('REQID'),
            new Entity\IntegerField('RESORT')
        );
    }
	public static function save($reqId,$resorts)
    {
		foreach($resorts as $res)
			self::add(array("REQID"=>$reqId,"RESORT"=>$res));
	}
}	

class RequestDataHotelTable extends Entity\DataManager
{
    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'request_data_hotel_table';
    }
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
				'autocomplete' => true
            )),
			new Entity\IntegerField('REQID'),
            new Entity\IntegerField('HOTEL')
        );
    }
	public static function save($reqId,$hotels)
    {
		foreach($hotels as $hotel)
			self::add(array("REQID"=>$reqId,"HOTEL"=>$hotel));
	}
}	

class HotelsToReqestTable extends Entity\DataManager
{
    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'hotels_to_request_table';
    }
    
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\StringField('ID', array(
                'primary' => true,
            )),
			new Entity\IntegerField('REQID', array(
                'primary' => true,
            )),
            new Entity\IntegerField('PRICE'),
			new Entity\BooleanField('ACTIVE'),
			new Entity\StringField('HOTEL_NAME'),
			new Entity\IntegerField('COUNTRY'),
			new Entity\IntegerField('RESORT'),
			new Entity\IntegerField('SUBREGION'),
			new Entity\IntegerField('STARID'),
			new Entity\IntegerField('SORT'),
			new Entity\StringField('DESCRIPTION'),
        );
    }
	
	
	
	public static function getTours($reqId,$page,$update)
    {
        $outArr = array();
		$tDb = array();
		$hotelArr=array();//массив id отелей
		$hotelList = array();
		$countryIDs = array();
		$resortIDs = array();
        /*
		$hotelHashArr=array(); // массив хешей отелей. у них id = 0. вытаскиваем информацию из списка туров.
		$starArr = array(); 
		$starList = array(); 
		
			
		
		$starIDs = array();
		*/
        $pageElement = 10;
        $filter = array("REQID"=>$reqId);
		$t = microtime(true);
        if (!$update) //запрос без обновления при постраничке
            $filter["ACTIVE"] = true;
		else //запрос с обновлением при нажатии кнопки показать
        {
            $filter["ACTIVE"] = false;
            $rowList = TVToursTable::getList(array('select' => array('*'),'order'=>array('PRICE'=>'asc'),'filter' => $filter)); 
            while($row = $rowList ->fetch())
            {
                TVToursTable::update(array("REQID"=>$row["REQID"],"TID"=>$row["TID"]),array("ACTIVE"=>true));
            } 
			
            $t = microtime(true);
         
            
            $rowList = self::getList(array('select' => array('*'),'filter' => $filter)); 
            
            
            $t = microtime(true)-$t;
            //prf($t."!11<br>");
            $t = microtime(true);
            while($row = $rowList ->fetch())
            {
                self::update(array("REQID"=>$row["REQID"],"ID"=>$row["ID"]),array("ACTIVE"=>true));
            } 
			$t = microtime(true)-$t;
            //prf($t."!12<br>");
            $t = microtime(true);
            unset($filter["ACTIVE"]);           
        }    
		
		
        $t = microtime(true);
        $rowListTours = TVToursTable::getList(array('select' => array('REQID'),'order'=>array('PRICE'=>'asc'),'filter' => $filter)); //ищем в бд сохраненные туры
        $outArr["tcount"] = $rowListTours->getSelectedRowsCount();
		$t = microtime(true)-$t;
        //prf($t."!2<br>");
        $t = microtime(true);
		$rowList = self::getList(array('select' => array('ID','PRICE','HOTEL_NAME',"COUNTRY",'RESORT',"SUBREGION","STARID"),'order'=>array('SORT'=>'asc','PRICE'=>'asc'),'filter' => $filter)); //ищем в бд сохраненные туры
        
        $rowList = new CDBResult($rowList);
	    $rowList->NavStart($pageElement,false, $page);
        
        while($row = $rowList ->fetch())
        {
             
            //if ($row["DATE"]) $row["DATE"] = $row["DATE"]->format("d.m.Y");
            $row["PRICE"] = number_format($row["PRICE"],2, '.', ' ');
            $row["PRICE"] = str_replace(".00","",$row["PRICE"]);
            $tDb[]=$row;
           
			if (!in_array($row["ID"],$hotelArr)) 
				$hotelArr[] = $row["ID"];
            
        }
        $t = microtime(true)-$t;
       // prf($t."!3<br>");
        $t = microtime(true);
		//prf($tDb);
		//prf($hotelArr);
        if (count($hotelArr)>0)
            $hotelList=TVToursTable::GetHotelList($hotelArr);
		//prf($hotelList);
		$t = microtime(true)-$t;
       // prf($t."!4<br>");
        $t = microtime(true);
		
		
		foreach($hotelList as $hotel)
		{	
			if ($hotel["COUNTRY"]!=="" && !in_array($hotel["COUNTRY"],$countryIDs))
				$countryIDs[] = $hotel["COUNTRY"];
			if ($hotel["RESORT"]!=="" && !in_array($hotel["RESORT"],$resortIDs))
				$resortIDs[] = $hotel["RESORT"];
			if ($hotel["SUBREGION"]!=="" && $hotel["SUBREGION"]>0 && !in_array($hotel["SUBREGION"],$resortIDs))
				$resortIDs[] = $hotel["SUBREGION"];	
		}
		
		
		if (count($countryIDs)>0)
            $countryList=TVToursTable::GetCountryList($countryIDs);
        if (count($resortIDs)>0)
            $resortList=TVToursTable::GetResortList($resortIDs);
	
		//prf($tDb);
		//prf($countryList);
		//prf($resortList);
		//prf($hotelList);
		
        $i = 0;	
        foreach($tDb as &$tour)
        {
           
			$hid = $tour["ID"];
			$emptyHotel = false;
				
			if ($hotelList[$hid])
			{	
				$hotel = $hotelList[$hid];
				 
				if ($hotel["COUNTRY"] && isset($countryList[$hotel["COUNTRY"]]))
				{    
					$lat = "";
					$lon = "";
					if ($hotel["COORDS"]!="")
					{
						$coordArr = explode(",",$hotel["COORDS"]);
						$lat = $coordArr[0];
						$lon = $coordArr[1];
					}
					$outArr['tours'][$i]["id"] 		    = $hid;
					$outArr['tours'][$i]["country"] 	= $countryList[$hotel["COUNTRY"]]["NAME"];
					$outArr['tours'][$i]["resort"] 		= $resortList[$hotel["RESORT"]]["NAME"];
					$outArr['tours'][$i]["hotel"] 		= $hotel["NAME"];
					$outArr['tours'][$i]["hotel_rate"]  = $hotel["RATE"];
					$outArr['tours'][$i]["hotel_star"]  = $hotel["STAR"];
					$outArr['tours'][$i]["hotel_img"]   = ($hotel["IMG"]) ? "<img src='".$hotel["IMG"]."' />": false;
					$outArr['tours'][$i]["hotel_id"]    = ($emptyHotel) ? "_".$hid : $tour["ID"];
					$outArr['tours'][$i]["coords_lat"]  = $lat;
					$outArr['tours'][$i]["coords_lon"]  = $lon;	
				}
			   
				$outArr['tours'][$i]["price"] = $tour["PRICE"];
				$i++; 
			}
        } 
        $t = microtime(true)-$t;
       // prf($t."!5<br>");
       
        $outArr["pager"] = $rowList->GetPageNavStringEx($navComponentObject, '','search', false);
		
        return $outArr;
    }
	
	public static function GetHotelInfo ($reqId,$hid)
	{
		$hotel = array();
        $rowList = self::getList(array('select' => array('*'),'filter' => array("REQID"=>$reqId,"ID"=>$hid))); 
		if($row = $rowList ->fetch())
		{
			$hotel =$row ;
		} 
		return $hotel;
	}
}

class TVToursTable extends Entity\DataManager
{
	public static	$depHL 		= 1;
	public static	$countryHL 	= 2;
	public static	$resortHL 	= 3;
    public static 	$hotelHL 	= 6;
	public static   $mealHL     = 4;
	public static   $countryPhotoHL = 15;

    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'tours_list_table';
    }
    
    public static function getConnection()
	{
		return $this->init_entity->getConnection();
	}
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('REQID', array(
                'primary' => true,
            )),
			new Entity\IntegerField('TID', array(
                'primary' => true,
            )),
			new Entity\IntegerField('DEPARTURE'),
            new Entity\IntegerField('ADULTS'),
            new Entity\DateTimeField('DATE'),
            new Entity\IntegerField('OPERATOR'),
            new Entity\IntegerField('OPERATOR_NAME'),
			new Entity\StringField('OPERATOR_LINK'),
            new Entity\IntegerField('HOTEL'),
            
            new Entity\IntegerField('KIDS'),
            new Entity\IntegerField('MEAL'),
            new Entity\StringField('MEAL_NAME'),
            new Entity\IntegerField('NIGHTS'),
            new Entity\IntegerField('PRICE'),
            new Entity\IntegerField('FUEL'),
			new Entity\StringField('PLACEMENT'),
            new Entity\StringField('ROOMNAME'),
            new Entity\BooleanField('ACTIVE'),
			new Entity\BooleanField('REGULAR')
			
        );     	
    }
    
	
	public static function getSpecHotels()
    {
		$res = array();
		$specHotels = array(
			4 => array(
				961,//UTOPIA RESORT & RESIDENCE (EX. ALARA PARK HOTEL)
				1335 //SELECTUM FAMILY RESORT (EX. SENTIDO LETOONIA GOLF RESORT)
			)
		);
		$res = $specHotels ;
		return $res;
	}
	
    public static function saveTours($reqId,$dep,$arIn, $active)
    {
        //print_r($arIn);
        $cnt = count($arIn); $tDb=array(); $cheсk = false; $hotelsArr = array();
        //$price=99999999999;
        if ($cnt>0)
        {    
            $rowList = self::getList(array('select' => array('TID'),'filter' => array("REQID"=>$reqId))); //ищем в бд сохраненные туры
            while($row = $rowList ->fetch())
            {
                $tDb[]=$reqId."_".$row["TID"];
                
            }
            //add2log($tDb);

			$hotelsList = HotelsToReqestTable::getList(array('select' => array('*'),'filter' => array("REQID"=>$reqId))); //выбираем сохраненные по запросу отели
			while($row = $hotelsList ->fetch())
			{
				$hotelsArr[$row["ID"]] = $row["PRICE"];
			} 
			
			
            if (count($tDb)>0)
                $cheсk = true;//в базе есть туры, будем добавлять только новые
            
			$specHotels = self::getSpecHotels();
			
            foreach ($arIn as $hotel)
            {
				//prf($hotel);
				$HID = $hotel["id"];
				if (isset($hotelsArr[$HID])) //обновим цену отеля. либо добавим запись
				{
					if ($hotelsArr[$HID]>$hotel["price"])
					{	
						
						$arrKey["ID"]=$HID;
						$arrKey["REQID"]=$reqId;
						$arrData["PRICE"]=$hotel["price"];
						HotelsToReqestTable::update($arrKey,$arrData);
						$hotelsArr[$HID] = $hotel["price"];
					}
				}	
				else
				{
					$sort = (!empty($specHotels[$hotel["countrycode"]]) && in_array($HID,$specHotels[$hotel["countrycode"]])) ? 80 : 100;
					
					if(empty($hotel["name"]))
						$hotel["name"] = "";
					$arrH["ID"]			=	$HID;
					$arrH["REQID"]		=	$reqId;
					$arrH["PRICE"]		=	$hotel["price"];
					$arrH["HOTEL_NAME"]	=	$hotel["name"];
					$arrH["COUNTRY"]  	=	$hotel["countrycode"];
					$arrH["RESORT"]   	=	$hotel["regioncode"];
					$arrH["SUBREGION"]	=	$hotel["subregioncode"];
					$arrH["STARID"]   	=	$hotel["stars"];
					$arrH["DESCRIPTION"]=	(strpos($hotel["description"],"Описание отеля временно отсутствует")===false) ? trim($hotel["description"]) : "" ;
					$arrH["ACTIVE"]   	=	$active;
					$arrH["SORT"]   	=	$sort;
					
					HotelsToReqestTable::add($arrH);
					$hotelsArr[$HID] = $hotel["price"];
				}
				//add2log("ID=".$HID);	
                //add2log($hotel["tours"]);
                
				foreach($hotel["tours"] as $value)
				{
					if ($cheсk && !in_array($reqId."_".$value['tourid'],$tDb) || !$cheсk)
					{    
						$outAr=array();
						$outAr["REQID"]=$reqId;
						$outAr["TID"]=$value['tourid'];
						$outAr["DEPARTURE"]=$dep;
						$outAr["HOTEL"]=$HID;
						$outAr["PRICE"]=$value['price'];
                        $outAr["FUEL"]=$value['fuelcharge'];
						$outAr["NIGHTS"]=$value['nights'];
						$outAr["OPERATOR"]=$value['operatorcode'];
						$outAr["OPERATOR_NAME"]=$value['operatorname'];
						$outAr["DATE"]=new \Bitrix\Main\Type\Date($value["flydate"], "d.m.Y");
						$outAr["PLACEMENT"]=$value['placement'];
						$outAr["ROOMNAME"]=$value['room'];
						$outAr["ADULTS"]=$value['adults'];
						$outAr["KIDS"]=$value['child'];
						$outAr["MEAL"]=$value['mealcode'];
						$outAr["MEAL_NAME"]=$value['mealrussian'];
						$outAr["ACTIVE"]  = $active;
						$outAr["REGULAR"] = ($value['regular']==1) ? true : false;
                        //add2log($outAr);
						$result = self::add($outAr); 
						
						//break;	
					}
				}
            }
        }
        
		/*if ($price==99999999999) $price=false;
        else
        {
            $price = number_format($price,2, '.', ' ');
            $price = str_replace(".00","",$price);
        }   * 
        return $price;*/
    }
    
    public static function getTours($reqId,$hotelId=false)
    {
        $outArr = array();
        $arF = array("REQID"=>$reqId, "ACTIVE"=>true);
        if($hotelId)
            $arF["HOTEL"] = $hotelId;
        $ii=0;
        $mealArr = array();
        $rowList = self::getList(array('select' => array('*'),'filter' =>$arF,"order"=>array("PRICE"=>"asc"))); //ищем в бд сохраненные туры
        while($row = $rowList ->fetch())
        {
            //prf($row);
            $dateFrom =  $row["DATE"]->toString();
            $row["DATE"]->add($row["NIGHTS"]." day");
            $dateTo   =  $row["DATE"]->toString();
            $row["PRICE"] = number_format($row["PRICE"],2, '.', ' ');
            $row["PRICE"] = str_replace(".00","",$row["PRICE"]);
            
            $outArr[$ii]["reqid"]     = $reqId;
            $outArr[$ii]["hid"]       = $row["HOTEL"];
            $outArr[$ii]["tid"]       = $row["TID"];
            $outArr[$ii]["date_from"] = $dateFrom;
            $outArr[$ii]["date_to"]   = $dateTo; 
            $outArr[$ii]["nights"]    = $row["NIGHTS"];
            $outArr[$ii]["price"]     = $row["PRICE"];
            $outArr[$ii]["room"]      = $row["ROOMNAME"]." / ". $row["PLACEMENT"];
            $outArr[$ii]["meal_name"] = $row["MEAL_NAME"];
            $outArr[$ii]["meal"]      = $row["MEAL"];
            $ii++;
            if(!in_array($row["MEAL"], $mealArr))
                $mealArr[]=$row["MEAL"];
        }
        if(count($mealArr)>0)
        {
            $mealName = self::GetMealList($mealArr);
            foreach($outArr as $ii=>$tour)
            {
                if(isset($mealName[$tour["meal"]]))
                    $outArr[$ii]["meal_name"] = $mealName[$tour["meal"]]["NAME"];
            }
        }    
        return $outArr;
    }
	
	public static function getTour($reqId,$tid, $full = false)
    {
        $outArr = array();
        $arF = array("REQID"=>$reqId, "TID"=>$tid);

        $rowList = self::getList(array('select' => array('*'),'filter' =>$arF,"order"=>array("PRICE"=>"asc"))); //ищем в бд сохраненные туры
        if($row = $rowList ->fetch())
        {
            //prf($row);
            $dateFrom =  $row["DATE"]->toString();
            $row["DATE"]->add($row["NIGHTS"]." day");
            $dateTo   =  $row["DATE"]->toString();
			if(!$full)
			{
				$row["PRICE"] = number_format($row["PRICE"],2, '.', ' ');
				$row["PRICE"] = str_replace(".00","",$row["PRICE"]);
            }
            $outArr["reqid"]     = $reqId;
            $outArr["hid"]       = $row["HOTEL"];
            $outArr["tid"]       = $row["TID"];
            $outArr["date_from"] = $dateFrom;
            $outArr["date_to"]   = $dateTo; 
            $outArr["nights"]    = $row["NIGHTS"];
            $outArr["price"]     = $row["PRICE"];
            $outArr["fuel"]      = $row["FUEL"];
			$outArr["adults"]    = $row["ADULTS"];
            $outArr["child"]     = $row["KIDS"];
			if($full)
			{
				$place = $row["ADULTS"]." взр";
				if($row["KIDS"]>0)
					$place .=" + ".$row["KIDS"]." реб";
					
				$outArr["room"]      =  $row["ROOMNAME"];
				$outArr["placement"] =  $place." / ".$row["PLACEMENT"];
			}
			else
				$outArr["room"]      = $row["ROOMNAME"]." / ". $row["PLACEMENT"];
            
			$outArr["meal_name"] = $row["MEAL_NAME"];
			$outArr["departure"] = "";
			$outArr["regular"]   = $row["REGULAR"];
           
		    if($row["DEPARTURE"]!="")
			{
				$from = self::GetFromList(array($row["DEPARTURE"]));
				$outArr["departure"] = $from[$row["DEPARTURE"]]["NAME"];
			}
			if($row["MEAL"]!="")
			{
				$from = self::GetMealList(array($row["MEAL"]));
				$outArr["meal_name"] = $from[$row["MEAL"]]["NAME"];
			}
			
			if($full)
			{
				$outArr["operator"] = $row["OPERATOR_NAME"];
				$outArr["operator_link"] = $row["OPERATOR_LINK"];
				
				$hotel =  self::GetHotelList(array($row["HOTEL"]));
				if($hotel[$row["HOTEL"]])
				{
					$outArr["hotel"] = $hotel[$row["HOTEL"]]["NAME"];
					$country = self::GetCountryList (array($hotel[$row["HOTEL"]]["COUNTRY"]));
					$outArr["country"] = $country[$hotel[$row["HOTEL"]]["COUNTRY"]]["NAME"];
					$resort = self::GetResortList (array($hotel[$row["HOTEL"]]["RESORT"]));
					$outArr["resort"] = $resort[$hotel[$row["HOTEL"]]["RESORT"]]["NAME"];
				}
				else
				{
					$hotel = \HotelsToReqestTable::GetHotelInfo($reqId,$row["HOTEL"]);
					
					$outArr["hotel"] = $hotel["HOTEL_NAME"];
					$country = self::GetCountryList (array($hotel["COUNTRY"]));
					$outArr["country"] = $country[$hotel["COUNTRY"]]["NAME"];
					$resort = self::GetResortList (array($hotel["RESORT"]));
					$outArr["resort"] = $resort[$hotel["RESORT"]]["NAME"];
				}

			}
		   
        }
       
        return $outArr;
    }
	
    public static function updateTour($reqId,$tid,$arParams)
    {
		$arF = array("REQID"=>$reqId, "TID"=>$tid);

        $rowList = self::getList(array('select' => array('*'),'filter' =>$arF)); //ищем в бд сохраненные туры
        if($row = $rowList ->fetch())
        {
			self::update(array("REQID"=>$reqId,"TID"=>$tid),$arParams);
		}	
	}
	
	public static function GetFromList ($arParams=false)
	{   
        $depid = array();
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_DEPID"]=$arParams;
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$depHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($dep = $dbData->fetch())
		{	
			$depid[$dep["UF_DEPID"]] = array("NAME"=>$dep["UF_NAME"],"NAME2"=>$dep["UF_NAME2"]);
		}
        
		return $depid;
	}
    
	
	public static function GetCountryList ($arParams=false)
	{   
        $cid = array();
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_CID"]=$arParams;
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$countryHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($country = $dbData->fetch())
		{	
			$cid[$country["UF_CID"]] = array("NAME"=>$country["UF_NAME"]);
		}
        
		return $cid;
	}
	
	public static function GetResortList ($arParams=false)
	{
        $resid = array();
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_TID"]=$arParams;
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$resortHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($resort = $dbData->fetch())
		{	
			$resid[$resort["UF_TID"]] = array("NAME"=>$resort["UF_NAME"]);
		}
		return $resid;
	}
    
	public static function GetResortListFeed ($CID = false)
	{
        $resid = array();
		$filter  = array();
		if ($CID !=false)
			$filter["UF_CID"]=$CID;
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$resortHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($resort = $dbData->fetch())
		{	
			$resid[$resort["UF_TID"]] = array("NAME"=>$resort["UF_NAME"]);
		}
		return $resid;
	}
    

	public static function GetHotelList ($arParams=false)
	{
	
        $hotid = array();
        	
		$filter  = array();
		$resizePhoto = true;
		if($arParams["NO_RESIZE"])
		{
			$resizePhoto = false;
			unset($arParams["NO_RESIZE"]);
		}
		if ($arParams !=false && is_array($arParams))
			$filter["UF_HID"]=$arParams;
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($hotel = $dbData->fetch())
		{	
			$rate = ($hotel["UF_RATING"]>0) ? $hotel["UF_RATING"] : 0  ;
			$img = false;
			if(is_array($hotel["UF_PHOTO"]) && !empty($hotel["UF_PHOTO"][0]))
			{
				$fl = \CFile::GetFileArray($hotel["UF_PHOTO"][0]);
                if($resizePhoto && $fl["WIDTH"]>170 || $fl["HEIGHT"]>120)
                {    
                    $arFileTmp = CFile::ResizeImageGet(
                        $fl,
                        array("width" => 170 , "height" => 120),
                        BX_RESIZE_IMAGE_EXACT,
                        true, array()
                    );

                    $img = $arFileTmp["src"];
                }
                else
                    $img = $fl["SRC"];
			}	
			$hotid[$hotel["UF_HID"]] = array(
				"NAME"		=>	$hotel["UF_NAME"],
				"RATE"		=>	$rate,
				"IMG"		=>	$img,
				"STAR"		=>	$hotel["UF_SID"],
				"COUNTRY"	=>	$hotel["UF_CID"],
				"RESORT"	=>	$hotel["UF_TID"],
				"SUBREGION"	=>  $hotel["UF_STID"],
				"COORDS"    =>  $hotel["UF_COORDS"]
			);
			
		}	
        
		return $hotid;
	}
	

	public static function GetHotelListFeed ($arParams=false)
	{
	
        $hotid = array();
		$filter  = array();

		if ($arParams !=false && is_array($arParams))
			$filter["UF_HID"]=$arParams;
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($hotel = $dbData->fetch())
		{	
			$rate = ($hotel["UF_RATING"]>0) ? $hotel["UF_RATING"] : 0  ;
			$img = false;
			if(is_array($hotel["UF_PHOTO"]) && !empty($hotel["UF_PHOTO"][0]))
			{
				$fl = \CFile::GetFileArray($hotel["UF_PHOTO"][0]);
                $img = $fl["SRC"];
			}	
			$hotid[$hotel["UF_HID"]] = array(
				"NAME"		=>	$hotel["UF_NAME"],
				//"RATE"		=>	$rate,
				"IMG"		=>	$img,
				"STAR"		=>	$hotel["UF_SID"],
				//"COUNTRY"	=>	$hotel["UF_CID"],
				"RESORT"	=>	$hotel["UF_TID"],
				//"SUBREGION"	=>  $hotel["UF_STID"],
				//"COORDS"    =>  $hotel["UF_COORDS"]
			);
			
		}	
        
		return $hotid;
	}
	
	public static function GetHotelListStat ($arParams=false,$isFeed =false)
	{
	
        $hotid = array();
        	
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_HID"]=$arParams;
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($hotel = $dbData->fetch())
		{	
			
			$arr= array(
				"NAME"		=>	$hotel["UF_NAME"],
				"STAR"		=>	$hotel["UF_SID"],
				"COUNTRY"	=>	$hotel["UF_CID"],
				"RESORT"	=>	$hotel["UF_TID"],
				"SUBREGION"	=>  $hotel["UF_STID"],
			);
			
			if ($isFeed)
			{	
				if(is_array($hotel["UF_PHOTO"]) && !empty($hotel["UF_PHOTO"][0]))
				{
					$fl = \CFile::GetFileArray($hotel["UF_PHOTO"][0]);
					$arr["PIC"] = "https://anextours.ru".$fl["SRC"];
				}	
				else
					$arr["PIC"] ="";
				
				$arr["RATING"] =  ($hotel["UF_RATING"]>0) ? $hotel["UF_RATING"] : "" ;
				$arr["INROOM"] =  $hotel["UF_INROOM"];
				$arr["FREE"]   =  $hotel["UF_FREE"];
				
			}
			
			$hotid[$hotel["UF_HID"]] = $arr;
		}	
        
		return $hotid;
	}

	public static function GetHotelListHot ($arParams=false)
	{
	
        $hotid = array();
        	
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_HID"]=$arParams;
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$hotelHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		$noPicCountries = [];
		while($hotel = $dbData->fetch())
		{	
			$img = false;
			if(is_array($hotel["UF_PHOTO"]) && !empty($hotel["UF_PHOTO"][0]))
			{
				$fl = \CFile::GetFileArray($hotel["UF_PHOTO"][0]);
                if($fl["WIDTH"]>400 || $fl["HEIGHT"]>267)
                {    
                    $arFileTmp = CFile::ResizeImageGet(
                        $fl,
                        array("width" => 400 , "height" => 267),
                        BX_RESIZE_IMAGE_EXACT,
                        true, array()
                    );

                    $img = $arFileTmp["src"];
                }
                else
                    $img = $fl["SRC"];
			}	
			else
			{
				$noPicCountries[]=$hotel["UF_CID"];
			}
			$hotid[$hotel["UF_HID"]] = array(
				"NAME"		=>	$hotel["UF_NAME"],
				"STAR"		=>	$hotel["UF_SID"],
				"CID"		=>  $hotel["UF_CID"],
				"RESORT"	=>  $hotel["UF_TID"],
				"PIC"		=>  $img
			);
			
		}	
		if(count($noPicCountries)>0)
		{
			$countryPics = [];
			$hlblockPic = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$countryPhotoHL)->fetch();
			$entityPic  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblockPic );
			$eclassPic = $entityPic->getDataClass();
			$dbDataPic = $eclassPic::getList(["filter"=>["UF_CID"=>$noPicCountries],"select"=>array("*")]);
			while($pic = $dbDataPic->fetch())
			{
				$countryPics[$pic["UF_CID"]][]=$pic["UF_FILE"];
			}	
			if (count($countryPics)>0)
			{
				$savedPics = [];
				foreach($hotid as $hid=>$hotel)
				{
					if(!$hotel["PIC"] && !empty($countryPics[$hotel["CID"]]))
					{

						$picKey = array_rand($countryPics[$hotel["CID"]]);
						$picID  = $countryPics[$hotel["CID"]][$picKey];
						if (empty($savedPics[$picID]))
						{
							$fl = \CFile::GetFileArray($picID);
							if($fl["WIDTH"]>400 || $fl["HEIGHT"]>267)
							{    
								$arFileTmp = CFile::ResizeImageGet(
									$fl,
									array("width" => 400 , "height" => 267),
									BX_RESIZE_IMAGE_EXACT,
									true, array()
								);

								$img = $arFileTmp["src"];
							}
							else
								$img = $fl["SRC"];
							$hotid[$hid]["PIC"] =$img;	
						}
						else
							$hotid[$hid]["PIC"] = $savedPics[$picID];
					}
				}
			}
			
		}
        
		return $hotid;
	}

    /*    
    public function GetHotelByHid ($hid)
	{

        $hotid = array();
        if (Loader::includeModule("iblock"))
        {
            $arF = array("IBLOCK_ID"=>SlToursTable::$hotelIB, "ACTIVE"=>"Y", "=PROPERTY_HID"=>$hid);

            $arS =  array("ID","IBLOCK_ID","CODE","NAME","PROPERTY_HID","PROPERTY_PROP_HOTELRATE","PROPERTY_PROP_STARNAME","CODE","PROPERTY_CID");
            $hotList =  CIBlockElement::GetList(array("NAME"=>"ASC"), $arF, false, false,$arS);
            if($hotElm=$hotList->fetch())
            {
                //print_r($hotElm);
                
                $hotElm["PROPERTY_PROP_HOTELRATE_VALUE"] = ($hotElm["PROPERTY_PROP_HOTELRATE_VALUE"] > 0 ) ? $hotElm["PROPERTY_PROP_HOTELRATE_VALUE"] : 0;
                $hotElm["PROPERTY_PROP_STARNAME_VALUE"] = ((integer)$hotElm["PROPERTY_PROP_STARNAME_VALUE"] >= 1 &&  (integer)$hotElm["PROPERTY_PROP_STARNAME_VALUE"] <= 5) ? (integer)$hotElm["PROPERTY_PROP_STARNAME_VALUE"] : 0;
                $url = "";
                if($hotElm["PROPERTY_CID_VALUE"]!="")
                {
                    $country = SlToursTable::GetCountryList (array($hotElm["PROPERTY_CID_VALUE"]));
                    if (count($country[$hotElm["PROPERTY_CID_VALUE"]])>0)
                        $url =  "/".$country[$hotElm["PROPERTY_CID_VALUE"]]["CODE"]."/hotels/".$hotElm["ID"]."_".$hotElm["CODE"]."/";
                }        
                $hotid =array("NAME"=>$hotElm["NAME"],"CODE"=>$hotElm["CODE"],"ID"=>$hotElm["ID"],"RATE"=>$hotElm["PROPERTY_PROP_HOTELRATE_VALUE"],"STAR"=>$hotElm["PROPERTY_PROP_STARNAME_VALUE"],"URL"=>$url);
                   
            }
        }
		return $hotid;
	}
    *
    
	public function GetStarList ($arParams=false)
	{
        $curid = array();
        if (Loader::includeModule("highloadblock"))
        {
            $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(SlToursTable::$hotelStarHB)->fetch();
            $entity  = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
            $eclass = $entity->getDataClass();
            $filter = array();
            if ($arParams !=false && is_array($arParams))
                $filter["=UF_SID"]=$arParams;
            $tList = $eclass::getList(
                array(
                    'select' => array('*'),
                    'order' => array("ID"=>"asc"),
                    'filter' => $filter,
                )
            );
            while($t = $tList->fetch())
            {
                $curid[$t["UF_SID"]] = $t["UF_NAME"];
            }
        }        
		return $curid;
	}
	
    */
	public static function GetMealList ($arParams=false)
	{
        $mealid = array();
		$filter  = array();
		if ($arParams !=false && is_array($arParams))
			$filter["UF_MID"]=$arParams;
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$mealHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($meal = $dbData->fetch())
		{	
			$mealid[$meal["UF_MID"]] = array("NAME"=>$meal["UF_RUSFULL"]);
		}
		return $mealid;
	}  
	
	public static function GetAllMealList()
	{
        $mealid = array();
		$filter  = array();
	
			
		\Bitrix\Main\Loader::includeModule('highloadblock');
		$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::$mealHL)->fetch();
		$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
		$eclass = $entity->getDataClass();
		$dbData = $eclass::getList(array("filter"=>$filter,"select"=>array("*")));
		while($meal = $dbData->fetch())
		{	
			$mealid[] = $meal;
		}
		return $mealid;
	}  
    
}

class CountryToursTable extends Entity\DataManager
{

    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'country_tours_table';
    }
    
    public static function getConnection()
	{
		return $this->init_entity->getConnection();
	}
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('REQID', array(
                'primary' => true,
            )),
			new Entity\IntegerField('TID', array(
                'primary' => true,
            )),
			new Entity\IntegerField('COUNTRY'),
			new Entity\IntegerField('DEPARTURE'),
            new Entity\IntegerField('ADULTS'),
            new Entity\DateTimeField('DATE'),
            new Entity\IntegerField('OPERATOR'),
            new Entity\IntegerField('OPERATOR_NAME'),
			new Entity\StringField('OPERATOR_LINK'),
            new Entity\IntegerField('HOTEL'),
            new Entity\IntegerField('STARS'),
            new Entity\IntegerField('KIDS'),
            new Entity\IntegerField('MEAL'),
            new Entity\StringField('MEAL_NAME'),
            new Entity\IntegerField('NIGHTS'),
            new Entity\IntegerField('PRICE'),
            new Entity\IntegerField('FUEL'),
			new Entity\StringField('PLACEMENT'),
            new Entity\StringField('ROOMNAME'),
			new Entity\BooleanField('REGULAR')
			 
        );     	
    }

	public static function saveTours($requestId,$country,$dep,$res)
    {
		foreach($res["hotels"] as $hotel)
		{
			foreach($hotel["tours"] as $value)
			{
				$outAr				=	[];
				$outAr["REQID"]		=	$requestId;
				$outAr["TID"]		=	$value['tourid'];
				$outAr["COUNTRY"]	=	$country;
				$outAr["DEPARTURE"]	=	$dep;
				$outAr["HOTEL"]		=	$hotel['id'];
				$outAr["STARS"]		=	intval($hotel['stars']);
				$outAr["PRICE"]		=	$value['price'];
				$outAr["FUEL"]		=	$value['fuelcharge'];
				$outAr["NIGHTS"]	=	$value['nights'];
				$outAr["OPERATOR"]	=	$value['operatorcode'];
				$outAr["OPERATOR_NAME"]=	$value['operatorname'];
				$outAr["DATE"]		=	new \Bitrix\Main\Type\Date($value["flydate"], "d.m.Y");
				$outAr["PLACEMENT"]	=	$value['placement'];
				$outAr["ROOMNAME"]	=	$value['room'];
				$outAr["ADULTS"]	=	$value['adults'];
				$outAr["KIDS"]		=	$value['child'];
				$outAr["MEAL"]		=	$value['mealcode'];
				$outAr["MEAL_NAME"]	=	$value['mealrussian'];
				$outAr["REGULAR"] 	=   ($value['regular']==1) ? true : false;
				//prf($outAr);
				$result = self::add($outAr); 
			}
		}
	}	

	public static function getTourListById($reqId,$tid)
    {
		$res = [];
		$dbRes = self::getList(["filter"=>["REQID"=>$reqId, "TID"=>$tid]]); 
		while($item = $dbRes->fetch())
		{
			$res[$item["REQID"]."_".$item["TID"]] =$item;
		}
		return $res;
	}	
}

class CountryHotelsMonthTable extends Entity\DataManager
{

    public static function getFilePath()
    {
      return __FILE__;
    }

    public static function getTableName()
    {
      return 'country_hotels_month_table';
    }
    
    public static function getConnection()
	{
		return $this->init_entity->getConnection();
	} 
    
    public static function getConnectionName()
	{
		return 'search';
	}

    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
            )),
			
			new Entity\IntegerField('COUNTRY'),
			new Entity\IntegerField('YEAR'),
            new Entity\IntegerField('MONTH'),
            new Entity\IntegerField('STARS'),
            new Entity\IntegerField('HOTEL'),
			new Entity\StringField('TOUR'),
            new Entity\IntegerField('PRICE')
        );     	
    }

	public static function getByCountry($country)
	{
		$res = [] ;
		$dbRes = self::getList(["order"=>["YEAR"=>"asc","MONTH"=>"asc","STARS"=>"asc","PRICE"=>"asc"],"filter"=>["COUNTRY"=>$country]]); 
		while($item = $dbRes->fetch())
		{
			$res[] =$item;
		}
		return $res;
	}

	public static function clearByCountry($country)
	{
		$dbRes = self::getList(["filter"=>["COUNTRY"=>$country],"select"=>["ID"]]); 
		while($item = $dbRes->fetch())
		{
			self::delete($item["ID"]);
		}
	}
}
?>
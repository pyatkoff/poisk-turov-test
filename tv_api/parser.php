<?
class TourParser
{
    public const workHL          = 16;
    public const workTypeParse   = 6;
    public const workTypeUpdate  = 7;
    public const workTypeClean   = 12;
    public const workTypeFeed    = 13;
    public const workStatusQueue = 8;
    public const workStatusWork  = 9;
    public const workStatusDone  = 10;
    public const workStatusError = 11;
    public const timeout         = 100;

    public static function addWork($country=false,$params=false,$type=false)
    {
        if(!$type)
            $type = self::workTypeParse;
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
        $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $eclass = $entity->getDataClass();
        
        $data = array(
            "UF_TYPE"     => $type,
            "UF_COUNTRY"  => $country,
            "UF_DATE"     => new  \Bitrix\Main\Type\DateTime(),
            "UF_PARAMS"   => ($params) ? json_encode($params) : "",
            "UF_STATUS"   => self::workStatusQueue
        );
        $eclass::add($data);
    }

    public static function setupParserWork()
    {
        self::addWork(false,false,self::workTypeClean);
        $countryList = self::getCountryList();
        foreach($countryList as $country=>$dates)
        {
            //prf($dates);
            $steps      = 0;
            $dep        = 1;
            $addDay 	= 0;
            $days		= 0;
            $dateNow  	= new \Bitrix\Main\Type\Date();
            $dateFrom  	= new \Bitrix\Main\Type\Date($dates["DATE_FROM"]);
            $dateTo     = new \Bitrix\Main\Type\Date($dates["DATE_FROM"]);
            if($dateFrom<=$dateNow)
            {
                $dateFrom = new \Bitrix\Main\Type\Date();
                $dateTo   = new \Bitrix\Main\Type\Date();
                $addDay   = 1;
            }    
           
            $dateToStop = new \Bitrix\Main\Type\Date($dates["DATE_TO"]);
            //prf($dateToStop);
            $stop       = false;
            do
            {
                if($days>=14)
                    $addDay = 14;
               
                $dateFrom->add($addDay ." day");
                if($addDay==1)
                    $dateTo->add("1 day");    
                $dateTo->add("14 day");

                if($days>=14)
                {
                    $dateFrom->add("1 day");
                    $dateTo->add("1 day");
                }    

                if($dateFrom<$dateToStop)
                {
                    $days += 14;	

                    $params = ["DEP" =>$dep , "DATE_FROM"=>$dateFrom->format("d.m.Y"), "DATE_TO"=> $dateTo->format("d.m.Y")];
                    //prf($params["DATE_FROM"]." ".$params["DATE_TO"]."<br>");
                    self::addWork($country,$params);
                }
                else
                    $stop = true;
                $steps++;
            }while(!$stop  &&  $steps<=30);
            
        }
        self::addWork(false,false,self::workTypeFeed);
    }

    public static function workAgent()
	{
        $aw = \Bitrix\Main\Config\Option::get('anytour.parser','active_work',false);
        if (!$aw || (time() - $aw) > 60*10) //нет активных задач или последняя задача на выполнении более 10 мин
		{
            $timer = microtime(true);
            \Bitrix\Main\Loader::includeModule('highloadblock');
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
			$entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
			$eclass = $entity->getDataClass();
			$works = [];
			$workDB = $eclass::getList(array('order'=>array("ID"=>"ASC"),'filter' => array("UF_STATUS"=>[self::workStatusQueue,self::workStatusWork]), 'select' => array("ID"),'limit'=>10));
			while ($work = $workDB->fetch())
			{
				$works[]=$work["ID"];
			}
            if(count($works)>0)
            {
                foreach($works as $workID)
                {
                    
                    self::execWork($workID);
                    if ( self::timeout < (microtime(true) - $timer))
                    {
                        break;
                    }
                }
            }
            
        }    
    }    

    public static function execWork($id)
	{
        if($id>0)
        {
            \Bitrix\Main\Config\Option::set('anytour.parser','active_work',time());
            $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::workHL)->fetch();
            $entity  = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
            $eclass = $entity->getDataClass();
            $workDB = $eclass::getList(
				array(
					'filter' => array(
						"ID" => $id,
						"UF_STATUS"=>[self::workStatusQueue,self::workStatusWork]
					), 
					'select' => array("ID","UF_TYPE","UF_STATUS","UF_COUNTRY","UF_PARAMS","UF_COUNT")
				)
			);
			$work = $workDB->fetch();
			if($work)
            {
                self::add2log("начало задачи ".$work["ID"]);
                if($work["UF_COUNT"]>0)
                    $work["UF_COUNT"]++;
                else
                    $work["UF_COUNT"] = 1;    
                if($work["UF_COUNT"]>5)    
                {
                    $eclass::update($work["ID"],[
                            "UF_STATUS"     => self::workStatusError,
                            "UF_DATE_END"   => new \Bitrix\Main\Type\DateTime(),
                            "UF_COUNT"      => $work["UF_COUNT"],
                            "UF_INFO"       => "Задача вызвана ".$work["UF_COUNT"]." раз"
						]
					);
                    self::add2log("ошибка задачи ".$work["ID"].". превышено количество вызовов");
                }
                else
                {
                
                    $isStart = false;
                    $updateArr = [
                        "UF_COUNT" => $work["UF_COUNT"]
                    ];
                    if($work["UF_STATUS"] == self::workStatusQueue){
                        $updateArr["UF_STATUS"]=self::workStatusWork;
                        $updateArr["UF_DATE_START"]=new \Bitrix\Main\Type\DateTime();
                        $isStart = true;    
                    }

                    $eclass::update($work["ID"],$updateArr);

                    $params = ($work["UF_PARAMS"]!="") ? json_decode($work["UF_PARAMS"],true) : "" ;

                    if($work["UF_TYPE"]==self::workTypeParse)
                    {
                        if($isStart)
                            self::cleanTours($work["UF_COUNTRY"],$params["DATE_FROM"],$params["DATE_TO"]);
                        require_once($_SERVER["DOCUMENT_ROOT"] .'/bitrix/php_interface/tv_api/tvapi.php');
                        require_once($_SERVER["DOCUMENT_ROOT"] .'/bitrix/php_interface/tv_api/search.php');

                        $requestArr     		  = []; 
                        $requestArr['country'] 	  = $work["UF_COUNTRY"];
                        $requestArr['departure']  = $params["DEP"];
                        $requestArr['adults'] 	  = 2;
                        $requestArr['child'] 	  = 0;
                        $requestArr['nightsfrom'] = 5;
                        $requestArr['nightsto']   = 14;
                        $requestArr['datefrom']   = $params["DATE_FROM"];	
                        $requestArr['dateto']     = $params["DATE_TO"];		
                        
                        $requestId = \TvApi::createSearchReq($requestArr); 
                        if ($requestId==0) {
                            sleep(3);
                            $requestId = \TvApi::createSearchReq($requestArr); 
                        }
                        if($requestId>0)
                        {
                            $percent    = 0;
                            $maxTimer   = 30;
                            $timer      = 0;
                            $timerStep  = 3;
                            do
                            {
                                sleep($timerStep);
                                $resSatus =  \TvApi::getReqStatus($requestId);
                                $percent  =  $resSatus["status"]["progress"];
                                $timer    += $timerStep;

                            }while($timer<$maxTimer && $percent<100);
                            
                            if($percent>0)
                            {
                                $result = \TvApi::getReqResult($requestId,10000); 
                                //prf($result);
                                \CountryToursTable::saveTours($requestId,$work["UF_COUNTRY"],$params["DEP"],$result);
                            }
                        }

                    }
                    elseif($work["UF_TYPE"]==self::workTypeClean)
                    {
                        self::cleanTours();
                    }
                    elseif($work["UF_TYPE"]==self::workTypeFeed)
                    {
                        self::exportFeed();
                    }     
                    $eclass::update($work["ID"],array(
                        "UF_STATUS"=>self::workStatusDone,
                        "UF_DATE_END"=>new \Bitrix\Main\Type\DateTime()
                        )
                    );
                    self::add2log("завершение задачи ".$work["ID"]);
                }
            }
            \Bitrix\Main\Config\Option::set('anytour.parser','active_work',false);
        }
    }


    public static function updateCountryData($country) 
    {
        \CountryHotelsMonthTable::clearByCountry($country);
        $minPrice = 0;
        $monData = [];
        $list = \CountryToursTable::getList(["order"=>["PRICE"=>"asc"] ,"filter"=>["COUNTRY"=>$country],"select"=>["REQID","TID","DATE","HOTEL","STARS","PRICE"]]);
        while($item = $list->fetch())
        {
            if($minPrice==0)
                $minPrice = $item["PRICE"];

            //prf($item);
            $dateArr = explode(".",$item["DATE"]);
            $mon  = intval($dateArr[1])."_".$dateArr[2];
            if(
                (empty($monData[$mon][$item["STARS"]]) || count($monData[$mon][$item["STARS"]])<6)&&
                empty($monData[$mon][$item["STARS"]][$item["HOTEL"]])
            )
                $monData[$mon][$item["STARS"]][$item["HOTEL"]]=["ID"=>$item["REQID"]."_".$item["TID"],"PRICE"=>$item["PRICE"]];
        }
        if (count($monData)>0)
        {
            foreach($monData as $my=>$stars)
            {
                $arr = explode("_",$my);
                $month = $arr[0];
                $year  = $arr[1];
                foreach($stars as $star=>$hotels)
                {
                    foreach($hotels as $hotel=>$r)
                    {
                        $data = [
                            'COUNTRY'   => $country,
                            'YEAR'      => $year,
                            'MONTH'     => $month,
                            'STARS'     => $star,
                            'HOTEL'     => $hotel,
                            'TOUR'      => $r["ID"],
                            'PRICE'     => $r["PRICE"],
                        ];
                        \CountryHotelsMonthTable::add($data);
                    }
                }
            }
        }

    }

    public static function cleanTours($country=false,$dateFrom = false, $dateTo = false) 
    {
        require_once($_SERVER["DOCUMENT_ROOT"] .'/bitrix/php_interface/tv_api/search.php');
        $dateFromBx = ($dateFrom) ? new \Bitrix\Main\Type\Date($dateFrom) : false;
        $dateToBx 	= ($dateTo) ? new \Bitrix\Main\Type\Date($dateTo) : new \Bitrix\Main\Type\Date();
        $filter     = [];
        if($country)
            $filter["COUNTRY"]=$country;
        if($dateFromBx)
            $filter["><DATE"]=[$dateFromBx,$dateToBx]; 
        else
            $filter["<=DATE"]=$dateToBx;  
        $list = \CountryToursTable::getList(["filter"=> $filter,"select"=>["REQID","TID"]]);
        while($item = $list->fetch())
        {
            \CountryToursTable::delete(["REQID"=>$item["REQID"],"TID"=>$item["TID"]]);
        }   

      
    }

    public static function exportFeed() 
    {
        require_once($_SERVER["DOCUMENT_ROOT"] .'/bitrix/php_interface/tv_api/search.php');
        $countryList  = array_keys(self::getCountryList()); 
        $result       = [];
        $resultFrom   = [];
        foreach($countryList as $country)
        {
            if($country!=2)
                continue;
            $items      =   [];
            $resortList	=	[];
            $hotelList	=	[];
            $hotelIDs   =   [];

            $res = \CountryToursTable::getList(["order"=>["PRICE"=>"asc"],"filter"=>["COUNTRY"=>$country],'limit'=>100 /*,"select"=>["HOTEL","PRICE"]*/]);
            while($item = $res->fetch())
            {
                $items[]   = $item;
                $hotelIDs[]= $item["HOTEL"];
            }
            if(count($hotelIDs)>0)
            {
                $hotelList=\TVToursTable::getHotelListFeed(array_unique($hotelIDs));
                if(count($hotelList)>0)
                {
                    $resortList=\TVToursTable::getResortListFeed($country);
                }
            }
        
            if(count($items)>0)
            {

                foreach($items as $item)
                {
                    $hotel    = ($hotelList[$item["HOTEL"]]) ? $hotelList[$item["HOTEL"]] : [];

                    $resortID = $hotel["RESORT"];
                    $dateFrom = $item["DATE"]->format("Y-m-d");
                    $dateTill = $item["DATE"];
                    $dateTill->add("14 day");
                    $dateTill = $dateTill->format("Y-m-d");
                    $result[] = [
                        $resortID,
                        1,
                        $hotel["NAME"]." ".$hotel["STAR"]."*, ".$item["NIGHTS"]." ночей, c ".$item["DATE"]->format("d.m.Y"),
                        "https://www.anytour.com/poisk-turov/?country=".$country."&hotel=".$item["HOTEL"]."&date_from=".$dateFrom."&date_till=".$dateTill."&days_from=".$item["NIGHTS"]."&days_till=14",
                        "https://www.anytour.com".$hotel["IMG"],
                        $resortList[$resortID]["NAME"],
                        "Москва",
                        $item["PRICE"]." RUB",
                        ""
                    ];

                    $resultFrom[] = [
                        $resortID,
                        1,
                        $hotel["NAME"]." ".$hotel["STAR"]."*, ".$item["NIGHTS"]." ночей, c ".$item["DATE"]->format("d.m.Y"),
                        "https://www.anytour.com/poisk-turov/?from=1&country=".$country."&hotel=".$item["HOTEL"]."&date_from=".$dateFrom."&date_till=".$dateTill."&days_from=".$item["NIGHTS"]."&days_till=14",
                        "https://www.anytour.com".$hotel["IMG"],
                        $resortList[$resortID]["NAME"],
                        "Москва",
                        $item["PRICE"]." RUB",
                        ""
                    ];
                }
            }
        }
        
        require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/classes/general/csv_data.php");
        $file = $_SERVER["DOCUMENT_ROOT"] . "/tour_feed.csv";
        $file_from = $_SERVER["DOCUMENT_ROOT"] . "/tour_from_feed.csv";
        $file_id = fopen($file, "w+");
        fclose($file_id);
        $file_id_from = fopen($file_from, "w+");
        fclose($file_id_from);
        $fields_type = 'R'; //дописываем строки в файл
        $delimiter = ",";   //разделитель для csv-файла
        $csvFile = new \CCSVData($fields_type, false);
        $csvFile->SetFieldsType($fields_type);
        $csvFile->SetDelimiter($delimiter);
        $csvFile->SetFirstHeader(true);
        $header = [
            0=>"Destination ID",
            1=>"Origin ID",
            2=>"Title",
            3=>"Final URL",
            4=>"Image URL",
            5=>"Destination name",
            6=>"Origin name",
            7=>"Price",
            8=>"Sale price"
        ];
        $csvFile->SaveFile($file,$header);
        foreach($result as $str)
            $csvFile->SaveFile($file,$str);

        $csvFileFrom = new \CCSVData($fields_type, false);
        $csvFileFrom->SetFieldsType($fields_type);
        $csvFileFrom->SetDelimiter($delimiter);
        $csvFileFrom->SetFirstHeader(true);
        $header = [
            0=>"Destination ID",
            1=>"Origin ID",
            2=>"Title",
            3=>"Final URL",
            4=>"Image URL",
            5=>"Destination name",
            6=>"Origin name",
            7=>"Price",
            8=>"Sale price"
        ];
        $csvFileFrom->SaveFile($file_from,$header);
        foreach($resultFrom as $str)
            $csvFileFrom->SaveFile($file_from,$str);
 
    }

    public static function getCountryList() 
    {
        return [
            2 => ["DATE_FROM"=>"01.06.2023","DATE_TO"=>"31.10.2023"],
            1 => ["DATE_FROM"=>"01.06.2023","DATE_TO"=>"30.11.2023"]
        ];
    }



    public static function add2log($text,$debug=false)
	{
		if($debug)
			$fp = fopen($_SERVER["DOCUMENT_ROOT"].'/upload/parser/debug_log.txt', "ab");
		else	
			$fp = fopen($_SERVER["DOCUMENT_ROOT"].'/upload/parser/log.txt', "ab");
		if (is_array($text))
		  fputs($fp, date("d.m.Y H:i:s")."\n".print_r($text,true)."\n\n");
		else
		  fputs($fp, date("d.m.Y H:i:s")."\n".$text."\n\n");
		fclose($fp);
	}


}
?>
<?
use Bitrix\Main\Loader;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$json=array(); 
if (
    (   isset($_REQUEST["search_update"]) || isset($_REQUEST["search_result"])) && isset($_REQUEST["reqid"]) || 
        isset($_REQUEST["search_create"]) && isset($_REQUEST["country"]) && isset($_REQUEST["city"]) ||
        isset($_REQUEST["tur_update"]) && isset($_REQUEST["reqid"]) && isset($_REQUEST["offid"]) && isset($_REQUEST["sorid"]) ||
		isset($_REQUEST["office_order"]) && isset($_REQUEST["req"]) && isset($_REQUEST["off"]) && isset($_REQUEST["sor"])
        
    )
{    
    \Bitrix\Main\Loader::includeModule('rhat.search');
   
    /***************************************/
    $adults         =   1;
    $children       =   0;
    $children_ages  =   array();
    $nightsMin      =   5;
    $nightsMax      =   20;
    $priceMin       =   0;
    $priceMax       =   0;
    $meals          =   array();
    $stars          =   array();
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
	if (isset($_REQUEST["office_order"])) //заявка на тур
    {
		$params					 = 	 array();
        $params["requestId"]     =   intval($_REQUEST["req"]);
        $params["offId"]         =   intval($_REQUEST["off"]);           
        $params["sorId"]         =   intval($_REQUEST["sor"]);
		$params["num"]           =   intval($_REQUEST["num"]);
        if ($params["requestId"]>0 &&  $params["offId"]>0 && $params["sorId"] >0)
        {    
            $params["name"]      =   trim($_REQUEST["name"]);
            $params["email"]     =   trim($_REQUEST["email"]);
            $params["phone"]     =   trim($_REQUEST["phone"]);
            $params["text"]      =   trim($_REQUEST["text"]);
			$params["buy"]      =   $_REQUEST["buy"];
            
			
			$json["send"] = \Rhat\Search\Api::orderTour($params);
        }
        else
            $json["send"]=0; 
    }
    elseif (isset($_REQUEST["tur_update"])) //актуализация цены
    { 
        $requestId    =   intval($_REQUEST["reqid"]);
		
        if ( intval($_REQUEST["reqid"])>0 && intval($_REQUEST["offid"])>0 &&  intval($_REQUEST["sorid"])>0)
        {   
            $json = \Rhat\Search\Api::updateTour(array("requestId"=>intval($_REQUEST["reqid"]),"offid"=>intval($_REQUEST["offid"]),"sorid"=>intval($_REQUEST["sorid"])));
        } 
        else
        {
            $error = true;	
        }    
        
    }
    else //получение и обновление туров
    {    
    
        /*
        Сначала получим id запроса. Либо это начало поиска и запрос нужно сформировать, либо это обновление информации. 
        В первом случает генерируемся запрос на поиск к слетать.
        Во втором случае ищем id в базе и вытаскиваем информацию по нему
        */
      
        if (isset($_REQUEST["search_create"])) //поисковый запрос. сделам запрос к слетать и занесем его в базу
        {    
            $tcreate        =   true;
            $countryId      =   intval($_REQUEST["country"]);           
            $cityFromId     =   intval($_REQUEST["city"]);
            $datefrom       =   $_REQUEST["datefrom"];           
            $datetill       =   $_REQUEST["datetill"];
			if ( is_array($_REQUEST["resort"]))
				$resort   = $_REQUEST["resort"];
			else
			{	
				$resortArr = \Rhat\Search\Api::GetResortList(array("CID"=>$countryId));
				foreach($resortArr as $resorItem)
					$resort[]=$resorItem["id"];
				
			}
			
            $hotel          =   ( is_array($_REQUEST["hotel"])) ? $_REQUEST["hotel"] : array();
            if ( isset($_REQUEST["nightsmin"]) && intval($_REQUEST["nightsmin"])>0 && intval($_REQUEST["nightsmin"])<31)  
               $nightsMin = intval($_REQUEST["nightsmin"]);
            if ( isset($_REQUEST["nightsmax"]) && intval($_REQUEST["nightsmax"])>0 && intval($_REQUEST["nightsmax"])<31)  
               $nightsMax = intval($_REQUEST["nightsmax"]);
            if ( isset($_REQUEST["adults"]) && intval($_REQUEST["adults"])>0 && intval($_REQUEST["adults"])<5)  
               $adults  = intval($_REQUEST["adults"]);
            if ( isset($_REQUEST["children"]) && intval($_REQUEST["children"])>=0 && intval($_REQUEST["children"])<4)  
               $children  = intval($_REQUEST["children"]);
            if ($children >0 && isset($_REQUEST["children_ages"]) && is_array($_REQUEST["children_ages"]) && count($_REQUEST["children_ages"])==$children)  
               $children_ages  = $_REQUEST["children_ages"];
           
            if ( isset($_REQUEST["price_from"]) && intval($_REQUEST["price_from"])>0)  
               $priceMin  = intval($_REQUEST["price_from"]); 
            if ( isset($_REQUEST["price_till"]) && intval($_REQUEST["price_till"])>0)  
               $priceMax  = intval($_REQUEST["price_till"]);     
            
            if (isset($_REQUEST["stars"]) && is_array($_REQUEST["stars"]) && count($_REQUEST["stars"])>0)  
                $stars = $_REQUEST["stars"];
            if (isset($_REQUEST["food"]) && is_array($_REQUEST["food"]) && count($_REQUEST["food"])>0)  
                $meals = $_REQUEST["food"];
            
            $requestArr['countryId'] = $countryId;
            $requestArr['cityFromId'] = $cityFromId;
          
            $requestArr['adults'] = $adults;
            $requestArr['nightsMin'] = $nightsMin;
            $requestArr['nightsMax'] = $nightsMax;
            $requestArr['departFrom'] = $datefrom;
            $requestArr['departTo'] = $datetill;   
            $requestArr['hotelIsNotInStop'] = 'true';
            $requestArr['hasTickets'] = 'true';
            $requestArr['ticketsIncluded'] = 'true';
            if (count($resort)>0)
                $requestArr['cities'] = $resort;
            if (count($hotel)>0)
                $requestArr['hotels'] = $hotel;
            if ($children>0 && count($children_ages)>0)
            {    
                $requestArr['kids'] =  $children;
                $requestArr['kidsAges'] = $children_ages;
            }
            if (count($priceMin)>0)
                $requestArr['priceMin'] = $priceMin;
            if (count($priceMax)>0)
                $requestArr['priceMax'] = $priceMax;
            if (count($stars)>0)
                $requestArr['stars'] = $stars;
            if (count($food)>0)
                $requestArr['meals'] = $food;
            
           
		    $result 	= \Rhat\Search\Api::createSearchRequest($requestArr);
			
            $error		= $result['error'];
			$requestId	= $result['requestId'];
        }
        else //запрос на обновление информации
        {
            if ($_REQUEST["search_update"]) 
				$tupdate = true;
			else 
				$tresult = true;
			
            $requestId      =   intval($_REQUEST["reqid"]);
            if ($requestId>0)
            {   
				$result = \Rhat\Search\Api::getSearchRequest($requestId);
				
                $percent		= $result['percent'];
				$tours			= $result['tours'];
				$toursdb		= $result['toursdb'];
				$error			= $result['error'];
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
        
		//var_dump($error);
		//var_dump($tcreate);
		//var_dump($tupdate);
        if ($requestId>0 && !$error) {
           
            if ($tcreate || $tupdate) // начало поиска и обновление состояния
            {
				$resGet = \Rhat\Search\Api::updateSearchTours(array("requestId"=>$requestId,"percent"=>$percent,"tours"=>$tours));
				//var_dump($resGet);
                $json["reqid"]    = $requestId;
                $json["tcount"]   = $resGet["tours"]; 
				$tours			  = $resGet["tours"];
                $json["percent"]  = $resGet["percentFound"];
                $json["minprice"] = $resGet["price"];
            }
          
            
            if ($tcreate && $tours>0 || $tupdate && $toursdb==0 && $tours>0 || $tresult)
            {
                
                if (isset($_REQUEST["page"]) && intval($_REQUEST["page"])>0)
                    $page = intval($_REQUEST["page"]);
                if (isset($_REQUEST["upd"]) && $_REQUEST["upd"]=='true')
                    $update = true;
                $getRes =  \Rhat\Search\RsearchToursListTable::getTours($requestId,$page,$update);
				
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
                        
        }
        else
        {
             $error =true;
        }    
        //echo "<pre>";
        //print_r($requestId); echo "<br />";
       // print_r($percentFound);echo "<br />";
       // print_r($upArr);echo "<br />"; 
       // print_r($reqArray);
       // print_r($result);
        //echo "</pre>";
        //echo "1";
    }
}
else
{
   $json['error'] = true;
} 

echo json_encode($json); 
 

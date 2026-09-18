<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-pagination.php';

function pneed(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}

$terminal=AnyTourAndromedaPaginationV1::nextTarget(20,0,0,'complete',0,29);
pneed($terminal===['terminal'=>true,'target'=>19],'real_terminal_empty');

$firstEmpty=AnyTourAndromedaPaginationV1::nextTarget(1,0,0,'complete',0,1);
pneed($firstEmpty===['terminal'=>true,'target'=>0],'empty_search_terminal');

$normal=AnyTourAndromedaPaginationV1::nextTarget(19,29,40,'partial',0,29);
pneed($normal===['terminal'=>false,'target'=>29],'normal_advertised_page');

foreach([
    [20,0,1,'complete',0],
    [20,0,0,'partial',0],
    [20,0,0,'complete',1],
    [20,19,0,'complete',0],
] as $case){
    $failed=false;
    try{
        AnyTourAndromedaPaginationV1::nextTarget($case[0],$case[1],$case[2],$case[3],$case[4],29);
    }catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_pages_invalid';}
    pneed($failed,'invalid_terminal_shape_'.implode('_',$case));
}

echo "ANDROMEDA_PAGINATION_CONTRACT_OK terminal_page20=1 data_pages19=1 invalid_shapes=4\n";

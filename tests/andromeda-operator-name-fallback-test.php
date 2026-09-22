<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/api-andromeda-search3-preview.php';

function ok(bool $value,string $message): void {
    if(!$value){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
}

$expected=[
    '13'=>'ANEX',
    '18'=>'Библио-Глобус',
    '25'=>'FUN&SUN',
    '43'=>'Intourist',
];
foreach($expected as $id=>$name){
    ok(anytour_andromeda_search3_known_operator_name($id)===$name,'known '.$id);
    ok(anytour_andromeda_search3_operator_name([],$id)===$name,'fallback '.$id);
}
ok(anytour_andromeda_search3_operator_name(['43'=>'Интурист'],'43')==='Intourist','compatible observed alias');
ok(anytour_andromeda_search3_operator_name(['25'=>'Fun&Sun (RU)'],'25')==='FUN&SUN','compatible funsun alias');
ok(anytour_andromeda_search3_operator_name(['777'=>'Some Operator'],'777')==='Some Operator','unknown observed preserved');
ok(anytour_andromeda_search3_operator_name([],'777')===null,'unknown missing remains unavailable');

$conflict=false;
try{anytour_andromeda_search3_operator_name(['43'=>'Pegas'],'43');}
catch(DomainException $e){$conflict=$e->getMessage()==='operator_identity_conflict';}
ok($conflict,'known id conflict fails closed');

echo "ANDROMEDA_OPERATOR_NAME_FALLBACK_OK\n";

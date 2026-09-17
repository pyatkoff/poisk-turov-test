#!/usr/bin/env python3
from pathlib import Path
endpoint=Path('v2/api-andromeda-search3-preview.php').read_text()
for required in ('static function($next)use($path,&$state)','$saved=anytour_andromeda_search3_save($path,$next);','if($saved)$state=$next;','return $saved;',"['created_at'=>$state['store']['created_at'],'session'=>$client->privateSession()]", "['directory'=>$directory,'store'=>$state['store'],'created_at'=>$number===1?$state['store']['created_at']:$first['store']['created_at']]"):
    assert required in endpoint, required
assert 'static function($next)use($path){return anytour_andromeda_search3_save($path,$next);}' not in endpoint
print('Andromeda page-session checkpoint state sync PASS')

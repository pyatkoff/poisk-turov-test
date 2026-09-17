#!/usr/bin/env python3
from pathlib import Path
entry=Path('v2/api-andromeda-search3-preview.php').read_text()
core_path=Path('v2/api-andromeda-search3-preview-core.php')
endpoint=core_path.read_text() if core_path.is_file() else entry
for required in ('static function($next)use($path,&$state)','$saved=anytour_andromeda_search3_save($path,$next);','if($saved)$state=$next;','return $saved;',"['created_at'=>$state['store']['created_at'],'session'=>$client->privateSession()]", "['directory'=>$directory,'store'=>$state['store'],'created_at'=>$number===1?$state['store']['created_at']:$first['store']['created_at']]"):
    assert required in endpoint, required
assert 'static function($next)use($path){return anytour_andromeda_search3_save($path,$next);}' not in endpoint
if core_path.is_file():
    assert "require_once __DIR__.'/api-andromeda-search3-preview-core.php';" in entry
    assert 'AnyTourAndromedaSearch3PageOrchestrator::run($request,$runner)' in entry
print('Andromeda page-session checkpoint state sync PASS')

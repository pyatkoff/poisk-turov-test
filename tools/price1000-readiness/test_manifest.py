import copy
from admit import validate_manifest
base={'phase':'readiness','files':{n:'x' for n in ['run.py','admit.py','feeds.php']},'expected_files':['run.py','admit.py','feeds.php']}
checks=0
def test(c,accept):
 global checks
 try:validate_manifest(c);passed=True
 except RuntimeError:passed=False
 assert passed is accept,c;checks+=1
test(base,True)
for n in base['files']:
 c=copy.deepcopy(base);del c['files'][n];test(c,False)
 c['expected_files'].remove(n);test(c,False)
c=copy.deepcopy(base);c['files']['install.php']='x';c['expected_files'].append('install.php');test(c,False)
c=copy.deepcopy(base);c['expected_files'].append('extra');test(c,False)
c=copy.deepcopy(base);c['phase']='activation';test(c,False)
c['files']['install.php']='x';c['expected_files'].append('install.php');test(c,True)
c['expected_files'].append('missing');test(c,False)
print({'ok':True,'checks':checks,'remote_calls':0})

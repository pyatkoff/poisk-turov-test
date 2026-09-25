"""Actual PHP entry boundary; no DB connection, supplier request or server mutation."""
import json, shutil, subprocess, tempfile, unittest
from pathlib import Path

DATA=Path(__file__).resolve().parents[1]/'v2/data'

class DestinationEntry(unittest.TestCase):
    def entry(self, relative, method, expected_status, expected_error, alias=False):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp); folder=root/relative; folder.mkdir(parents=True)
            for name in ('search3-destination-read-v1.php','db-v1.php','anytour-destination-catalog-v1.php'):
                shutil.copyfile(DATA/name,folder/name)
            entry=folder/'search3-destination-read-v1.php'
            if alias:
                link=root/'_preview/search3-local-candidate/data';link.parent.mkdir(parents=True,exist_ok=True)
                link.symlink_to(folder,target_is_directory=True);entry=link/entry.name
            code='$_SERVER["SCRIPT_FILENAME"]=$argv[1]; $_SERVER["REQUEST_METHOD"]=$argv[2]; $_SERVER["SCRIPT_NAME"]="/_preview/search3-local-candidate/data/search3-destination-read-v1.php"; $_SERVER["HTTP_HOST"]="anytoour.ru"; register_shutdown_function(function(){echo "\\nSTATUS=".http_response_code();}); include $argv[1];'
            r=subprocess.run(['php','-r',code,str(entry),method],check=True,capture_output=True,text=True)
            body,status=r.stdout.rsplit('\nSTATUS=',1)
            self.assertEqual(int(status),expected_status)
            self.assertEqual(json.loads(body),{'ok':False,'error':expected_error})

    def test_production_and_siblings_rejected_despite_spoofed_script_name(self):
        for path in ('data','_preview/search3-next-candidate/data','_preview/search3-site-candidate/data'):
            with self.subTest(path=path): self.entry(path,'GET',403,'Destination catalogue is isolated to local preview')

    def test_local_get_only_before_database(self):
        self.entry('_preview/search3-local-candidate/data','POST',405,'Only GET is allowed')

    def test_symlink_cannot_imitate_local_identity(self):
        self.entry('foreign/data','GET',403,'Destination catalogue is isolated to local preview',alias=True)

if __name__=='__main__': unittest.main()

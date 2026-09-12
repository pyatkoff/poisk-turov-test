<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/scripts/diagnostics/hotel_match_posh_club_current_correct.php';
exec('php '.escapeshellarg($path).' --self-test',$out,$code);
if($code!==0){fwrite(STDERR,implode("\n",$out)."\n");exit(1);} 
if(!str_contains(implode("\n",$out),'PASS rows=3'))exit(2);
$src=file_get_contents($path);
foreach(['2000042757','2000051422','2000073047','132075','131024','111423','compare','evidence_sha256','postcommit_readback_failed','crystal_bay_changed'] as $needle){if(!str_contains($src,$needle)){fwrite(STDERR,"missing:$needle\n");exit(3);}}
if(str_contains($src,'2000041085')){fwrite(STDERR,"Crystal Bay must not be in executable plan\n");exit(4);} 
echo "MATCH Posh writer contract test PASS\n";

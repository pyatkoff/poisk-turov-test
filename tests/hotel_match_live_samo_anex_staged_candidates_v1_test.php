<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_anex_staged_candidates_v1.php';
$b=['automated_status'=>'strong_candidate','suggested_target'=>7,'rank1_target'=>7,'tv'=>7,'country_match'=>true,'distance_m'=>100.0,'manual'=>false,'pair_excluded'=>false,'effective_other'=>false,'existing_mapping'=>false,'source_target_count'=>1,'target_source_count'=>1];
if(hmsasc_classify($b)!=='candidate_staged_strong_unique') throw new RuntimeException('candidate');
$x=$b;$x['distance_m']=5001;if(hmsasc_classify($x)!=='coordinate_conflict') throw new RuntimeException('distance');
$x=$b;$x['existing_mapping']=true;if(hmsasc_classify($x)!=='existing_non_effective_mapping') throw new RuntimeException('existing');
$x=$b;$x['source_target_count']=2;if(hmsasc_classify($x)!=='ambiguous_staged_source') throw new RuntimeException('ambiguous');
echo "MATCH_STAGED_CANDIDATES_TEST_OK\n";

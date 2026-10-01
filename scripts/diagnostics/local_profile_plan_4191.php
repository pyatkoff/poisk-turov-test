<?php
/**
 * Fixed LOCAL #4191 read-only planner. Invoked only by the permanent executor.
 * Existing saved-content owner decides each delta. No apply or acquisition path.
 */
declare(strict_types=1);

const LPP_BATCH = 'local4191-20260930';
const LPP_OPERATION = 'int-andromeda-local-profile-plan-4191-20261001-v1';
const LPP_D1_SOURCE = 'a1968cb819c126be8e7d287f9176acb2647e6f67';
const LPP_IDS = '5227,4930,5251,5116,5077,5221,5455,5185,6191,5904,5222,5197,5483,4941,5557,4991,4922,5200,5352,5451,5229,4925,5038,11756,6185,5464,4868,5154,5250,5173,5021,4996,11344,13190,4850,6223,7478,5655,5126,4919,4975,4946,5306,4970,4891,7032,5460,4853,5353,4932,15772,7452,5554,5106,5073,6078,6829,9838,5144,4939,6232,13687,5467,4972,5048,4947,4971,7252,4914,6153,5167,4465,5461,13628,5480,5184,4642,11032,5023,5971,7472,4456,11348,4496,5927,5062,6004,5145,4897,4968,5149,4887,13969,13048,4911,4864,5213,5153,5015,5014,4886,5262,4881,13878,6156,5349,9852,4901,6105,4917,14075,5685,6200,5772,5449,12278,12277,7561,5268,5039,9880,9888,4865,5117,11331,5929,14284,5181,5051,12124,5264,4903,5432,4900,4928,4870,13138,5027,4943,5450,10794,4869,4973,4473,12368,4463,7473,9854,5551,5183,5033,4988,6030,4885,5640,5290,4876,5678,4944,4937,4852,5459,10825,4452,5232,13378,5102,5235,13957,6809,4989,4915,5730,10833,4916,4912,4974,5076,5162,5156,5350,6264,6297,5209,12740,4474,5252,5367,13225,6349,4861,5567,4933,6113,5180,5573,4896,4909,4439,4976,4908,4918,6545,5155,9640,6149,5112,4978,13846,5075,4998,4990,4874,4924,5576,9821,14762,4905,7567,5243,4910,4883,13246,5462,4884,4432,15641,12385,5078,5122,5007,13040,11610,4906,5571,4927,13633,4948,4437,5188,6141,5018,5178,6124,5295,5582,4440,5139,5030,5440,9837,5001,5273,5560,5476,6221,5841,5159,4894,6216,6998,5458,4940,4871,5566,5050,4879,7213,5187,5190,5677,13627,5276,14904,5207,7109,15824,11332,14417,5277,7262,7107,7048,15809,7188,14518,7136,5310,15325,14681,7257,4995,5082,6088,7167,5304,6146,7193,7068,15964,5152,5308,7058,5302,7201,5245,7287,7303,5869,7298,5248,5642,7259,9866,15019,6621,15769,13877,7157,7053,15557,7185,7031,15447,7163,7174,5309,4878,14884,5074,11809,4234,4310,4297,4195,4105,4178,4229,4173,4167,4298,4117,4125,4296,4127,4303,4135,4200,4194,4131,4157,4124,4226,4107,4158,4111,4179,4165,4184,4187,4259,4183,4266,4164,4170,4156';
const LPP_FIELDS = [
    'description','primaryImage','images','address','place','build','repair','square',
    'hotelInformation.infrastructure','hotelInformation.services',
    'hotelInformation.meals','hotelInformation.roomTypes',
];

function lpp_need(bool $value, string $reason): void {
    if (!$value) throw new RuntimeException($reason);
}
function lpp_ids(): array {
    $ids = array_map('intval', explode(',', LPP_IDS));
    lpp_need(count($ids) === 366 && count(array_unique($ids)) === 366 && min($ids) > 0, 'queue_integrity');
    return $ids;
}
function lpp_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function lpp_file(string $path, int $maximum): string {
    lpp_need(is_file($path) && !is_link($path) && realpath($path) === $path
        && filesize($path) > 0 && filesize($path) <= $maximum, 'private_file');
    $bytes = file_get_contents($path);
    lpp_need(is_string($bytes), 'private_read');
    return $bytes;
}
function lpp_save(string $path, array $value): string {
    $bytes = lpp_json($value)."\n";
    lpp_need(strlen($bytes) <= 32 * 1024 * 1024 && !file_exists($path) && !is_link($path), 'private_output');
    $stream = fopen($path, 'x');
    lpp_need(is_resource($stream), 'private_output');
    try {
        lpp_need(chmod($path, 0600), 'private_permissions');
        lpp_need(fwrite($stream, $bytes) === strlen($bytes) && fflush($stream), 'private_output');
        if (function_exists('fsync')) lpp_need(fsync($stream), 'private_flush');
    } finally { fclose($stream); }
    return hash('sha256', $bytes);
}

/** Verify the historical producer seal; reading it never resumes its sync/intake. */
function lpp_d1_values(array $plan, array $result, string $reserved, string $started, string $exitCode): array {
    lpp_need($reserved === "owner_claim=5880018336\nsource=".LPP_D1_SOURCE."\n"
        && trim($exitCode) === '0', 'd1_markers');
    $digest = $plan['planSha256'] ?? null;
    lpp_need(is_string($digest) && preg_match('/^[a-f0-9]{64}$/D', $digest) === 1
        && ($plan['status'] ?? null) === 'prepared_read_only'
        && ($plan['schemaVersion'] ?? null) === 1 && ($plan['limit'] ?? null) === 80
        && ($plan['supplierCalls'] ?? null) === 0 && ($plan['writes'] ?? null) === 0
        && ($plan['retryBefore'] ?? null) === '1970-01-01 00:00:00'
        && is_array($plan['selected'] ?? null) && array_is_list($plan['selected'])
        && count($plan['selected']) === 80, 'd1_plan');
    $core = $plan;
    unset($core['status'], $core['planSha256']);
    lpp_need(hash_equals($digest, hash('sha256', lpp_json($core)))
        && $started === "plan=$digest\nsource=".LPP_D1_SOURCE."\n", 'd1_plan_digest');
    lpp_need(($result['status'] ?? null) === 'completed'
        && ($result['planSha256'] ?? null) === $digest && ($result['selected'] ?? null) === 80
        && ($result['success'] ?? null) === 80 && ($result['notFound'] ?? null) === 0
        && ($result['failed'] ?? null) === 0 && ($result['httpAttempts'] ?? null) === 80
        && ($result['canonicalProfileWrites'] ?? null) === 0 && ($result['mappingWrites'] ?? null) === 0,
        'd1_terminal');
    $own = $legacy = [];
    foreach ($plan['selected'] as $row) {
        lpp_need(is_array($row) && is_int($row['anytourHotelId'] ?? null) && $row['anytourHotelId'] > 0
            && is_int($row['tourvisorHotelId'] ?? null) && $row['tourvisorHotelId'] > 0
            && array_key_exists('previousDetailStatus', $row) && $row['previousDetailStatus'] === null
            && array_key_exists('previousDetailFetchedAt', $row) && $row['previousDetailFetchedAt'] === null,
            'd1_identity');
        lpp_need(!isset($own[$row['anytourHotelId']]) && !isset($legacy[$row['tourvisorHotelId']]), 'd1_duplicate');
        $own[$row['anytourHotelId']] = true;
        $legacy[$row['tourvisorHotelId']] = true;
    }
    return ['state'=>'verified_terminal_manifest','ownIds'=>array_keys($own),
        'legacyIds'=>array_keys($legacy),'planSha256'=>$digest];
}
function lpp_d1(string $home): array {
    $dir = $home.'/.anytour-ops/hc1-tv-missing-content-20260927-d1';
    try {
        lpp_need(is_dir($dir) && !is_link($dir) && realpath($dir) === $dir, 'd1_root');
        // Require the original completed marker and all original seals, never create one.
        lpp_file($dir.'/completed', 1024);
        $planBytes = lpp_file($dir.'/plan.json', 8 * 1024 * 1024);
        $resultBytes = lpp_file($dir.'/result.json', 8 * 1024 * 1024);
        $plan = json_decode($planBytes, true, 96, JSON_THROW_ON_ERROR);
        $result = json_decode($resultBytes, true, 96, JSON_THROW_ON_ERROR);
        lpp_need(is_array($plan) && is_array($result), 'd1_json');
        $proof = lpp_d1_values($plan, $result, lpp_file($dir.'/reserved', 4096),
            lpp_file($dir.'/started', 4096), lpp_file($dir.'/exit-code', 64));
        $proof['privateManifestSha256'] = hash('sha256', $planBytes);
        $proof['privateTerminalSha256'] = hash('sha256', $resultBytes);
        return $proof;
    } catch (Throwable) {
        // Unknown intersection authorizes no source plan and no later write.
        return ['state'=>'unknown_held','ownIds'=>[],'legacyIds'=>[]];
    }
}

/** Integrity checks on the existing accepted alias; never name-based identity resolution. */
function lpp_alias(array $row, int $own): ?int {
    $local = $row['local_id'] ?? null;
    if ((!is_int($local) && !is_string($local))
        || preg_match('/^[1-9][0-9]{0,18}$/D', (string)$local) !== 1
        || filter_var($local, FILTER_VALIDATE_INT) === false) return null;
    $raw = $row['alias_source_json'] ?? null;
    $sha = $row['alias_source_sha256'] ?? null;
    if (($row['alias_acquired_via'] ?? null) !== 'canonical_local_alias_v1'
        || !is_string($raw) || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
        || !hash_equals($sha, hash('sha256', $raw))) return null;
    try { $proof = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (Throwable) { return null; }
    $keys = ['accepted_local_hotel_id','canonical_hotel_id','derived_from_namespace','derived_from_source_sha256','schema_version'];
    if (!is_array($proof) || count($proof) !== count($keys) || array_diff($keys, array_keys($proof))
        || ($proof['schema_version'] ?? null) !== 1 || ($proof['accepted_local_hotel_id'] ?? null) !== (int)$local
        || ($proof['canonical_hotel_id'] ?? null) !== $own || ($proof['derived_from_namespace'] ?? null) !== 'legacy_catalog'
        || !is_string($proof['derived_from_source_sha256'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/D', $proof['derived_from_source_sha256']) !== 1) return null;
    return (int)$local;
}
function lpp_profile_valid(array $row): bool {
    $raw = $row['profile_json'] ?? null;
    $sha = $row['profile_sha256'] ?? null;
    if (!is_string($raw) || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
        || !hash_equals($sha, hash('sha256', $raw)) || (int)($row['revision'] ?? 0) < 1) return false;
    try { $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable) { return false; }
    return is_array($value);
}
function lpp_current_rows(PDO $db): array {
    lpp_need(!$db->inTransaction(), 'caller_transaction');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $ids = lpp_ids();
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $query = $db->prepare("SELECT h.id,h.is_active,h.revision,h.profile_json,h.profile_sha256,
                s.external_key AS local_id,s.acquired_via AS alias_acquired_via,
                s.source_json AS alias_source_json,s.source_sha256 AS alias_source_sha256
            FROM anytour_hotels h LEFT JOIN anytour_hotel_sources s
                ON s.anytour_hotel_id=h.id AND s.namespace='anytour_local_id'
            WHERE h.id IN ($marks) ORDER BY h.id,s.id");
        $query->execute($ids);
        $byId = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $byId[(int)$row['id']][] = $row;
        $db->commit();
        return $byId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/** A callback lets contract tests prove every exclusion occurs before owner plan(). */
function lpp_classify(array $byId, array $d1, callable $plan): array {
    $rows = []; $counts = []; $profiles = $aliases = $prepared = 0;
    foreach (lpp_ids() as $own) {
        $current = $byId[$own] ?? [];
        $out = ['anytourHotelId'=>$own,'state'=>'PROFILE_UNAVAILABLE','safeToApply'=>false];
        if (count($current) > 1) {
            ++$profiles; $out['state'] = 'ALIAS_HELD';
        } elseif (count($current) === 1 && (int)($current[0]['is_active'] ?? 0) === 1) {
            ++$profiles;
            $row = $current[0];
            $out['expectedRevision'] = (int)($row['revision'] ?? 0);
            $out['expectedProfileSha256'] = $row['profile_sha256'] ?? null;
            $local = lpp_alias($row, $own);
            if ($local === null) $out['state'] = 'ALIAS_HELD';
            elseif (!lpp_profile_valid($row)) $out['state'] = 'PROFILE_INTEGRITY_HELD';
            else {
                ++$aliases; $out['localHotelId'] = $local;
                $out['expectedAliasSha256'] = $row['alias_source_sha256'];
                if (($d1['state'] ?? null) !== 'verified_terminal_manifest') {
                    $out['state'] = 'D1_MANIFEST_UNKNOWN_HELD';
                } elseif (in_array($own, $d1['ownIds'], true) || in_array($local, $d1['legacyIds'], true)) {
                    $out['state'] = 'D1_OVERLAP_HELD';
                } else {
                    // The existing owner revalidates accepted identity, raw card,
                    // imported/manual origin and freshness. It alone creates the patch.
                    $scope = [['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>LPP_FIELDS]];
                    try {
                        $candidate = $plan($scope);
                        lpp_need(($candidate['status'] ?? null) === 'prepared_read_only'
                            && ($candidate['writes'] ?? null) === 0 && ($candidate['supplierCalls'] ?? null) === 0
                            && ($candidate['limit'] ?? null) === 1 && ($candidate['activeProfiles'] ?? null) === 1
                            && ($candidate['scannedProfiles'] ?? null) === 1
                            && ($candidate['contentPolicy'] ?? null) === 'sync_imported_retained_tv_v1'
                            && is_array($candidate['selected'] ?? null) && count($candidate['selected']) <= 1,
                            'owner_plan_contract');
                        ++$prepared;
                        $out['ownerPlan'] = $candidate;
                        $selected = $candidate['selected'][0] ?? null;
                        if ($selected !== null) {
                            lpp_need(($selected['anytourHotelId'] ?? null) === $own
                                && ($selected['localHotelId'] ?? null) === $local, 'owner_plan_identity');
                            if (($selected['expectedRevision'] ?? null) !== $out['expectedRevision']
                                || ($selected['expectedProfileSha256'] ?? null) !== $out['expectedProfileSha256']
                                || ($selected['expectedAliasSha256'] ?? null) !== $out['expectedAliasSha256']) {
                                $out['state'] = 'CURRENT_DRIFT_HELD';
                            } else $out['state'] = 'RETAINED_DELTA_PREPARED';
                        } else {
                            $held = $candidate['held'][$own] ?? [];
                            $reasons = array_values($held);
                            $out['state'] = $held === [] ? 'RETAINED_NO_DELTA'
                                : ((in_array('SYNC_FULL_CARD_UNAVAILABLE', $reasons, true)
                                    || in_array('SYNC_SAVED_PROFILE_UNAVAILABLE', $reasons, true)
                                    || (count(array_unique($reasons)) === 1 && $reasons[0] === 'source_missing_preserved'))
                                    ? 'SOURCE_MISSING' : 'SOURCE_PROVENANCE_HELD');
                        }
                    } catch (Throwable $e) {
                        $out['state'] = 'SOURCE_PROVENANCE_HELD';
                        $message = $e->getMessage();
                        $out['holdReason'] = preg_match('/^(?:ANYTOUR_|SYNC_)[A-Z0-9_]+$/D', $message) === 1
                            ? $message : 'owner_plan_failed_private_review';
                    }
                }
            }
        }
        $counts[$out['state']] = ($counts[$out['state']] ?? 0) + 1;
        $rows[] = $out;
    }
    ksort($counts);
    return ['rows'=>$rows,'profiles_read'=>$profiles,'aliases_validated'=>$aliases,
        'source_plans_prepared'=>$prepared,'classification_counts'=>$counts];
}

function lpp_main(array $argv): int {
    lpp_need(PHP_SAPI === 'cli' && count($argv) === 2 && $argv[1] === '--plan-only', 'disabled');
    $root = (string)getenv('ANYTOUR_ROOT');
    $dir = (string)getenv('LOCAL_PROFILE_PLAN_DIR');
    $head = (string)getenv('LOCAL_PROFILE_SOURCE_SHA');
    $control = (string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    $home = (string)getenv('HOME');
    lpp_need($root === $home.'/www/anytoour.ru' && realpath($root) === $root
        && realpath($dir) === $dir && dirname($dir) === $home.'/.anytoour-int-executor'
        && basename($dir) === LPP_OPERATION && preg_match('/^[a-f0-9]{40}$/D', $head) === 1
        && preg_match('/^[a-f0-9]{40}$/D', $control) === 1,
        'runtime_scope');
    $reservation = json_decode(lpp_file($dir.'/reservation.json', 65536), true, 32, JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id'] ?? null) === LPP_OPERATION
        && ($reservation['source_sha'] ?? null) === $head
        && ($reservation['mode'] ?? null) === 'local-profile-plan-4191', 'reservation');
    foreach (['local-plan-started.json','local-plan.json','local-plan-receipt.json'] as $file) {
        lpp_need(!file_exists($dir.'/'.$file) && !is_link($dir.'/'.$file), 'no_replay');
    }
    lpp_save($dir.'/local-plan-started.json', ['operation_id'=>LPP_OPERATION,'source_sha'=>$head,'mode'=>'read_only']);
    $d1 = lpp_d1($home);
    $bootstrap = $root.(is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    lpp_need(is_file($bootstrap) && !is_link($bootstrap) && realpath($bootstrap) === $bootstrap, 'bootstrap');
    $_SERVER['DOCUMENT_ROOT'] = $root;
    require_once $bootstrap;
    require_once dirname(__DIR__, 2).'/v2/data/anytour-profile-enrichment-v1.php';
    $db = v2_data_db();
    lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'mysql_required');
    // Even an unexpected owner call cannot write through this connection.
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $current = lpp_current_rows($db);
    $through = gmdate('Y-m-d H:i:s');
    $owner = new AnyTourProfileEnrichmentV1($db);
    $audit = lpp_classify($current, $d1, static fn(array $scope): array => $owner->plan(1, $through, $scope, true));
    $private = ['schema_version'=>1,'batch'=>LPP_BATCH,'operation_id'=>LPP_OPERATION,'source_sha'=>$head,
        'control_source_sha'=>$control,
        'demand_through'=>$through,'d1_exclusion'=>$d1,'safe_to_apply'=>false] + $audit;
    $digest = lpp_save($dir.'/local-plan.json', $private);
    $receipt = [
        'schema_version'=>1,'state'=>'completed_read_only','operation_id'=>LPP_OPERATION,'source_sha'=>$head,
        'control_source_sha'=>$control,
        'batch'=>LPP_BATCH,'requested_profiles'=>366,
        'profiles_read'=>$audit['profiles_read'],'aliases_validated'=>$audit['aliases_validated'],
        'source_plans_prepared'=>$audit['source_plans_prepared'],'classification_counts'=>$audit['classification_counts'],
        'd1_exclusion_state'=>$d1['state'],'private_plan_sha256'=>$digest,'provider_http_calls'=>0,
        'database_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0,'safe_to_apply'=>false,
    ];
    lpp_save($dir.'/local-plan-receipt.json', $receipt);
    echo lpp_json($receipt)."\n";
    return 0;
}
if (PHP_SAPI === 'cli' && realpath((string)($argv[0] ?? '')) === __FILE__) {
    try { exit(lpp_main($argv)); }
    catch (Throwable) {
        // Never expose PDO messages, source contents or configuration.
        fwrite(STDERR, "local_profile_plan_failed_no_replay\n");
        exit(2);
    }
}

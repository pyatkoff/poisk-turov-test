<?php
/**
 * Reviewed exact CURRENT-offer -> hotel-local room/meal decision importer.
 *
 * This tool never discovers or guesses equivalence. It writes only an explicitly
 * reviewed manifest after re-proving CURRENT offer evidence, accepted hotel identity
 * and the exact hotel-local target. No supplier I/O and no concept creation.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../v2/data/anytour-hotel-stay-catalog-v2.php';
require_once __DIR__ . '/../../v2/data/anytour-provider-identity-bridge-v1.php';

final class AnyTourHotelStayReviewedImportV2
{
    public const MAX_DECISIONS = 500;
    private const CURRENT_ROW_CAP = 15000;
    private const PROVIDERS = ['tourvisor'=>true,'anex'=>true,'andromeda'=>true];
    private const STATES = ['accepted'=>true,'rejected'=>true,'conflict'=>true];

    public function __construct(private PDO $pdo) {}

    private static function exactKeys(array $value,array $expected): bool
    {
        return count($value)===count($expected)
            && array_diff($expected,array_keys($value))===[]
            && array_diff(array_keys($value),$expected)===[];
    }

    private static function id(mixed $value): int
    {
        if ((!is_int($value)&&!is_string($value))
            || !preg_match('/^[1-9][0-9]*$/D',(string)$value)
            || filter_var($value,FILTER_VALIDATE_INT)===false) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_ID');
        }
        return (int)$value;
    }

    private static function text(mixed $value,int $limit,string $error): string
    {
        if (!is_string($value)||trim($value)===''||strlen($value)>$limit
            || !preg_match('//u',$value)||preg_match('/[\x00-\x1f\x7f]/',$value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function digest(mixed $value,string $error): string
    {
        if (!is_string($value)||!preg_match('/^[0-9a-f]{64}$/D',$value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function canon(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $list=$value===[]||array_keys($value)===range(0,count($value)-1);
        if ($list) return array_map([self::class,'canon'],$value);
        ksort($value,SORT_STRING);
        foreach ($value as $key=>$item) $value[$key]=self::canon($item);
        return $value;
    }

    private static function json(mixed $value): string
    {
        return json_encode(
            self::canon($value),
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR
        );
    }

    private static function decisionKey(array $decision): string
    {
        return self::json([
            $decision['anytourHotelId'],$decision['legacyHotelId'],$decision['provider'],
            $decision['providerHotelRefDigest'],$decision['operatorRaw'],
            $decision['kind'],$decision['raw'],
        ]);
    }

    private static function normalizeManifest(array $manifest): array
    {
        $top=['schemaVersion','reviewBatchId','decisions'];
        if (!self::exactKeys($manifest,$top)||($manifest['schemaVersion']??null)!==1) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_MANIFEST');
        }
        $batch=self::digest($manifest['reviewBatchId']??null,'HOTEL_STAY_REVIEWED_IMPORT_BATCH');
        $items=$manifest['decisions']??null;
        if (!is_array($items)||!array_is_list($items)||$items===[]||count($items)>self::MAX_DECISIONS) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_DECISIONS');
        }

        $expected=[
            'anytourHotelId','legacyHotelId','provider','providerHotelRefDigest','operatorRaw',
            'kind','raw','state','targetLocalKey','evidenceRef','evidenceSha256','reviewedBy',
        ];
        $out=[];$seen=[];
        foreach ($items as $item) {
            if (!is_array($item)||!self::exactKeys($item,$expected)) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_DECISION');
            }
            $hotelId=self::id($item['anytourHotelId']);
            $legacyId=self::id($item['legacyHotelId']);
            $provider=$item['provider']??null;
            if (!is_string($provider)||!isset(self::PROVIDERS[$provider])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_PROVIDER');
            }
            $providerDigest=self::digest(
                $item['providerHotelRefDigest']??null,
                'HOTEL_STAY_REVIEWED_IMPORT_PROVIDER_HOTEL'
            );
            $operator=self::text($item['operatorRaw']??null,240,'HOTEL_STAY_REVIEWED_IMPORT_OPERATOR');
            $kind=$item['kind']??null;
            if (!in_array($kind,['room','meal'],true)) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_KIND');
            }
            $raw=self::text($item['raw']??null,512,'HOTEL_STAY_REVIEWED_IMPORT_RAW');
            $state=$item['state']??null;
            if (!is_string($state)||!isset(self::STATES[$state])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_STATE');
            }
            $target=$item['targetLocalKey']??null;
            if ($state==='accepted') {
                $target=self::text($target,128,'HOTEL_STAY_REVIEWED_IMPORT_TARGET');
            } elseif ($target!==null) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_TARGET');
            }
            $evidenceRef=self::text($item['evidenceRef']??null,255,'HOTEL_STAY_REVIEWED_IMPORT_EVIDENCE');
            $evidenceSha=self::digest($item['evidenceSha256']??null,'HOTEL_STAY_REVIEWED_IMPORT_EVIDENCE');
            $reviewedBy=self::text($item['reviewedBy']??null,128,'HOTEL_STAY_REVIEWED_IMPORT_REVIEWER');

            $scope=AnyTourHotelStayCatalogV2::offerScope(
                $provider,$legacyId,$providerDigest,$operator
            );
            if ($scope===null) throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_OPERATOR');

            $normalized=[
                'anytourHotelId'=>$hotelId,
                'legacyHotelId'=>$legacyId,
                'provider'=>$provider,
                'providerHotelRefDigest'=>$providerDigest,
                'operatorRaw'=>$operator,
                'kind'=>$kind,
                'raw'=>$raw,
                'state'=>$state,
                'targetLocalKey'=>$target,
                'evidenceRef'=>$evidenceRef,
                'evidenceSha256'=>$evidenceSha,
                'reviewedBy'=>$reviewedBy,
                'scope'=>$scope,
            ];
            $key=self::decisionKey($normalized);
            if (isset($seen[$key])) throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_DUPLICATE');
            $seen[$key]=true;
            $out[]=$normalized;
        }
        usort($out,static fn(array $a,array $b)=>strcmp(self::decisionKey($a),self::decisionKey($b)));
        return ['schemaVersion'=>1,'reviewBatchId'=>$batch,'decisions'=>$out];
    }

    private function outsideTransaction(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $this->pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $this->pdo->inTransaction()) {
            throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_DEDICATED_MYSQL');
        }
    }

    private static function currentAt(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function currentEvidence(array $decisions,DateTimeImmutable $now): array
    {
        $tuples=[];
        foreach ($decisions as $decision) {
            $key=self::json([
                $decision['anytourHotelId'],$decision['legacyHotelId'],$decision['provider'],
                $decision['providerHotelRefDigest'],
            ]);
            $tuples[$key]=[
                $decision['anytourHotelId'],$decision['legacyHotelId'],$decision['provider'],
                $decision['providerHotelRefDigest'],
            ];
        }

        $clauses=[];$params=[
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        ];
        foreach ($tuples as [$hotelId,$legacyId,$provider,$digest]) {
            $clauses[]='(o.anytour_hotel_id=? AND o.legacy_hotel_id=? AND o.provider=? AND o.provider_hotel_ref_digest=?)';
            array_push($params,$hotelId,$legacyId,$provider,$digest);
        }
        $sql='SELECT o.id,o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.provider_hotel_ref_digest,
                o.payload_json,o.payload_sha256,o.last_seen_at
              FROM anytour_offers o
              JOIN anytour_offer_scope_state s
                ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256
               AND s.latest_complete_refresh_token IS NOT NULL
               AND s.latest_complete_refresh_token=o.last_refresh_token
              JOIN anytour_hotels h ON h.id=o.anytour_hotel_id AND h.is_active=1
              WHERE o.is_active=1 AND o.expires_at>? AND ('.implode(' OR ',$clauses).')
              ORDER BY o.last_seen_at DESC,o.id DESC
              LIMIT '.(self::CURRENT_ROW_CAP+1);
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>self::CURRENT_ROW_CAP) {
            throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_EVIDENCE_TRUNCATED');
        }
        $rows=AnyTourProviderIdentityBridgeV1::filterOfferRows($this->pdo,$rows);

        $wanted=[];
        foreach ($decisions as $decision) $wanted[self::decisionKey($decision)]=true;
        $found=[];
        foreach ($rows as $row) {
            $raw=(string)$row['payload_json'];
            if (!is_string($row['payload_sha256']??null)
                || !hash_equals((string)$row['payload_sha256'],hash('sha256',$raw))) {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_PAYLOAD_INTEGRITY');
            }
            try { $payload=json_decode($raw,true,128,JSON_THROW_ON_ERROR); }
            catch (Throwable $error) {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_PAYLOAD_INTEGRITY',0,$error);
            }
            if (!is_array($payload)
                || ($payload['provider']??null)!==$row['provider']
                || !is_array($payload['identity']??null)
                || ($payload['identity']['provider_hotel_ref_digest']??null)!==$row['provider_hotel_ref_digest']) {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_PAYLOAD_INTEGRITY');
            }
            $operator=is_array($payload['operator']??null)?($payload['operator']['raw']??null):null;
            if (!is_string($operator)) continue;
            $tour=$payload['tour']??null;
            if (!is_array($tour)) continue;
            foreach (['room','meal'] as $kind) {
                $part=$tour[$kind]??null;
                $value=is_array($part)?($part['raw']??null):null;
                if (!is_string($value)||$value==='') continue;
                $candidate=[
                    'anytourHotelId'=>(int)$row['anytour_hotel_id'],
                    'legacyHotelId'=>(int)$row['legacy_hotel_id'],
                    'provider'=>(string)$row['provider'],
                    'providerHotelRefDigest'=>(string)$row['provider_hotel_ref_digest'],
                    'operatorRaw'=>$operator,
                    'kind'=>$kind,
                    'raw'=>$value,
                ];
                $key=self::json([
                    $candidate['anytourHotelId'],$candidate['legacyHotelId'],$candidate['provider'],
                    $candidate['providerHotelRefDigest'],$candidate['operatorRaw'],
                    $candidate['kind'],$candidate['raw'],
                ]);
                if (isset($wanted[$key])) $found[$key]=true;
            }
        }

        foreach ($decisions as $decision) {
            if (!isset($found[self::decisionKey($decision)])) {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_CURRENT_OFFER_EVIDENCE');
            }
        }
        return $found;
    }

    private static function targetMaps(
        AnyTourHotelStayCatalogV2 $catalog,
        array $decisions
    ): array {
        $hotelIds=[];
        foreach ($decisions as $decision) $hotelIds[$decision['anytourHotelId']]=true;
        $targets=[];
        foreach (array_chunk(array_keys($hotelIds),AnyTourHotelStayCatalogV2::HOTEL_BATCH_LIMIT) as $chunk) {
            $rooms=$catalog->roomsForHotels($chunk);
            $meals=$catalog->mealsForHotels($chunk);
            foreach ($chunk as $hotelId) {
                $targets[$hotelId]=['room'=>[],'meal'=>[]];
                foreach ($rooms[$hotelId]??[] as $concept) {
                    $targets[$hotelId]['room'][$concept['localKey']]=$concept;
                }
                foreach ($meals[$hotelId]??[] as $concept) {
                    $targets[$hotelId]['meal'][$concept['localKey']]=$concept;
                }
            }
        }
        return $targets;
    }

    private function buildPlan(array $manifest,DateTimeImmutable $now): array
    {
        $catalog=new AnyTourHotelStayCatalogV2($this->pdo);
        if (!$catalog->readable()) throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_SCHEMA');

        $this->currentEvidence($manifest['decisions'],$now);
        $targets=self::targetMaps($catalog,$manifest['decisions']);

        $requests=[];
        foreach ($manifest['decisions'] as $decision) {
            $requests[]=[
                'anytourHotelId'=>$decision['anytourHotelId'],
                'legacyHotelId'=>$decision['legacyHotelId'],
                'provider'=>$decision['provider'],
                'providerHotelRefDigest'=>$decision['providerHotelRefDigest'],
                'operatorRaw'=>$decision['operatorRaw'],
                'roomRaw'=>$decision['kind']==='room'?$decision['raw']:null,
                'mealRaw'=>$decision['kind']==='meal'?$decision['raw']:null,
            ];
        }
        $matches=[];
        foreach (array_chunk($requests,AnyTourHotelStayCatalogV2::OFFER_BATCH_LIMIT) as $chunk) {
            array_push($matches,...$catalog->resolveOfferFactsBatch($chunk));
        }
        if (count($matches)!==count($manifest['decisions'])) {
            throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_MATCH_COUNT');
        }

        $entries=[];$insertCount=0;$existingCount=0;
        foreach ($manifest['decisions'] as $index=>$decision) {
            $targetId=null;$targetSnapshot=null;
            if ($decision['state']==='accepted') {
                $targetSnapshot=$targets[$decision['anytourHotelId']][$decision['kind']][$decision['targetLocalKey']]??null;
                if (!is_array($targetSnapshot)) {
                    throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_TARGET_UNAVAILABLE');
                }
                $targetId=(int)$targetSnapshot['id'];
            }

            $part=$matches[$index][$decision['kind']]??null;
            $status=is_array($part)?($part['status']??null):null;
            $canonical=is_array($part)?($part['canonical']??null):null;
            $action=null;
            if ($status==='unmapped') {
                $action='insert';++$insertCount;
            } elseif ($decision['state']==='accepted' && $status==='accepted'
                && is_array($canonical)
                && ($canonical['localKey']??null)===$decision['targetLocalKey']) {
                $action='existing-identical';++$existingCount;
            } elseif (in_array($decision['state'],['rejected','conflict'],true)
                && $status===$decision['state']) {
                $action='existing-identical';++$existingCount;
            } else {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_DECISION_EXISTS');
            }

            $entries[]=[
                'decisionKey'=>hash('sha256',self::decisionKey($decision)),
                'anytourHotelId'=>$decision['anytourHotelId'],
                'legacyHotelId'=>$decision['legacyHotelId'],
                'provider'=>$decision['provider'],
                'providerHotelRefDigest'=>$decision['providerHotelRefDigest'],
                'operatorRaw'=>$decision['operatorRaw'],
                'operatorKey'=>$decision['scope']['operatorKey'],
                'kind'=>$decision['kind'],
                'raw'=>$decision['raw'],
                'state'=>$decision['state'],
                'targetLocalKey'=>$decision['targetLocalKey'],
                'targetId'=>$targetId,
                'targetRevision'=>$targetSnapshot['revision']??null,
                'evidenceRef'=>$decision['evidenceRef'],
                'evidenceSha256'=>$decision['evidenceSha256'],
                'reviewedBy'=>$decision['reviewedBy'],
                'action'=>$action,
            ];
        }

        $body=[
            'schemaVersion'=>1,
            'reviewBatchId'=>$manifest['reviewBatchId'],
            'currentAt'=>self::currentAt($now),
            'decisionCount'=>count($entries),
            'insertCount'=>$insertCount,
            'existingIdenticalCount'=>$existingCount,
            'decisions'=>$entries,
            'supplierCalls'=>0,
            'automaticAccepts'=>0,
            'conceptCreates'=>0,
        ];
        return $body+['planSha256'=>hash('sha256',self::json($body))];
    }

    public function plan(array $manifest,DateTimeImmutable $now): array
    {
        $this->outsideTransaction();
        $manifest=self::normalizeManifest($manifest);
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $plan=$this->buildPlan($manifest,$now);
            $this->pdo->commit();
            return $plan;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function apply(array $manifest,string $expectedPlanSha,DateTimeImmutable $now): array
    {
        $this->outsideTransaction();
        self::digest($expectedPlanSha,'HOTEL_STAY_REVIEWED_IMPORT_PLAN_SHA');
        $manifest=self::normalizeManifest($manifest);

        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->beginTransaction();
        $inserted=0;
        try {
            $plan=$this->buildPlan($manifest,$now);
            if (!hash_equals($expectedPlanSha,$plan['planSha256'])) {
                throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_PLAN_DRIFT');
            }
            $catalog=new AnyTourHotelStayCatalogV2($this->pdo);
            foreach ($manifest['decisions'] as $index=>$decision) {
                $entry=$plan['decisions'][$index];
                if ($entry['action']==='existing-identical') continue;
                if ($entry['action']!=='insert') {
                    throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_ACTION');
                }
                $catalog->recordDecision(
                    $decision['scope'],
                    ['kind'=>$decision['kind'],'keyKind'=>'label','externalKey'=>$decision['raw']],
                    $decision['anytourHotelId'],
                    $decision['state'],
                    $entry['targetId'],
                    [
                        'ref'=>$decision['evidenceRef'],
                        'sha256'=>$decision['evidenceSha256'],
                        'reviewedBy'=>$decision['reviewedBy'],
                    ]
                );
                ++$inserted;
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        try {
            $verified=$this->plan($manifest,$now);
            if ($verified['insertCount']!==0
                || $verified['existingIdenticalCount']!==count($manifest['decisions'])) {
                return [
                    'status'=>'committed_unverified',
                    'planSha256'=>$expectedPlanSha,
                    'inserted'=>$inserted,
                    'verified'=>0,
                    'noReplay'=>true,
                    'supplierCalls'=>0,
                    'automaticAccepts'=>0,
                    'conceptCreates'=>0,
                ];
            }
            return [
                'status'=>'committed_verified',
                'planSha256'=>$expectedPlanSha,
                'inserted'=>$inserted,
                'alreadyPresent'=>count($manifest['decisions'])-$inserted,
                'verified'=>count($manifest['decisions']),
                'noReplay'=>true,
                'supplierCalls'=>0,
                'automaticAccepts'=>0,
                'conceptCreates'=>0,
            ];
        } catch (Throwable $error) {
            return [
                'status'=>'committed_unverified',
                'planSha256'=>$expectedPlanSha,
                'inserted'=>$inserted,
                'verified'=>0,
                'noReplay'=>true,
                'verifyError'=>$error->getMessage(),
                'supplierCalls'=>0,
                'automaticAccepts'=>0,
                'conceptCreates'=>0,
            ];
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    $manifestPath=null;$mode='plan';$expected=null;$now=null;
    foreach (array_slice($argv,1) as $arg) {
        if (str_starts_with($arg,'--manifest=')) $manifestPath=substr($arg,11);
        elseif ($arg==='--mode=plan') $mode='plan';
        elseif ($arg==='--mode=apply') $mode='apply';
        elseif (str_starts_with($arg,'--expected-plan-sha=')) $expected=substr($arg,20);
        elseif (str_starts_with($arg,'--now=')) $now=substr($arg,6);
        else throw new InvalidArgumentException(
            'Usage: --manifest=FILE [--mode=plan|--mode=apply --expected-plan-sha=SHA] [--now=YYYY-MM-DDTHH:MM:SSZ]'
        );
    }
    if (!is_string($manifestPath)||$manifestPath===''||!is_file($manifestPath)) {
        throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_MANIFEST_FILE');
    }
    $raw=file_get_contents($manifestPath);
    $manifest=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_MANIFEST');

    if ($now===null) $clock=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    else {
        $clock=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$now,new DateTimeZone('UTC'));
        if (!$clock||$clock->format('Y-m-d\TH:i:s\Z')!==$now) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEWED_IMPORT_NOW');
        }
    }

    $dsn=(string)(getenv('ANYTOUR_HOTEL_STAY_REVIEWED_IMPORT_DSN')?:'');
    $user=(string)(getenv('ANYTOUR_HOTEL_STAY_REVIEWED_IMPORT_USER')?:'');
    $password=(string)(getenv('ANYTOUR_HOTEL_STAY_REVIEWED_IMPORT_PASSWORD')?:'');
    if ($dsn==='') throw new RuntimeException('HOTEL_STAY_REVIEWED_IMPORT_DSN');
    $pdo=new PDO($dsn,$user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]);
    $importer=new AnyTourHotelStayReviewedImportV2($pdo);
    $result=$mode==='plan'
        ? $importer->plan($manifest,$clock)
        : $importer->apply(
            $manifest,
            self::digest($expected,'HOTEL_STAY_REVIEWED_IMPORT_PLAN_SHA'),
            $clock
        );
    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR
    ).PHP_EOL;
}

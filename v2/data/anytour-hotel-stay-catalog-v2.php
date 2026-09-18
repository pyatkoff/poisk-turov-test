<?php
/**
 * LOCAL V2 hotel-scoped room/meal concepts.
 *
 * No supplier I/O, no identity acceptance and no global canonical stay dictionary.
 * A room/meal concept only has meaning inside its own accepted AnyTour hotel.
 */
declare(strict_types=1);

final class AnyTourHotelStayCatalogV2
{
    public const BATCH_LIMIT = 100;
    public const HOTEL_BATCH_LIMIT = 1000;
    public const OFFER_BATCH_LIMIT = 1000;
    private const PROVIDERS = ['tourvisor'=>true,'anex'=>true,'andromeda'=>true];
    private const TABLES = [
        'anytour_hotel_room_concepts_v2',
        'anytour_hotel_meal_concepts_v2',
        'anytour_hotel_stay_mappings_v2',
    ];

    public function __construct(private PDO $pdo) {}

    private static function text(mixed $value, int $limit): string
    {
        if (!is_string($value) || trim($value)==='' || strlen($value)>$limit
            || !preg_match('//u', $value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_TEXT');
        }
        return $value;
    }

    private static function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT)===false) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_ID');
        }
        return (int)$value;
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private static function facts(array $facts): array
    {
        $json=self::json($facts);
        if (strlen($json)>16384) throw new InvalidArgumentException('HOTEL_STAY_V2_FACTS_SIZE');
        return $facts;
    }

    public static function scope(array $value): array
    {
        $namespace=self::text($value['namespace'] ?? null,64);
        if (!preg_match('/^[a-z0-9][a-z0-9_.:-]*$/D',$namespace)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_NAMESPACE');
        }
        return [
            'namespace'=>$namespace,
            'hotelKey'=>self::text($value['hotelKey'] ?? null,128),
            'operatorKey'=>self::text($value['operatorKey'] ?? null,128),
        ];
    }

    /**
     * Exact offer-to-stay scope. No operator normalization or cross-provider equivalence:
     * provider, provider-hotel digest and raw operator label all participate byte-for-byte.
     */
    public static function offerScope(
        string $provider,
        int $legacyHotelId,
        string $providerHotelRefDigest,
        mixed $operatorRaw
    ): ?array {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_OFFER_PROVIDER');
        }
        self::id($legacyHotelId);
        if (!preg_match('/^[0-9a-f]{64}$/D',$providerHotelRefDigest)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_OFFER_HOTEL_DIGEST');
        }
        if ($operatorRaw===null) return null;
        $operator=self::text($operatorRaw,240);
        $operatorKey='offer-exact-v1:'.hash('sha256',$provider."\0".$providerHotelRefDigest."\0".$operator);
        return self::scope([
            'namespace'=>'anytour_local_id',
            'hotelKey'=>(string)$legacyHotelId,
            'operatorKey'=>$operatorKey,
        ]);
    }

    private static function offerReference(string $kind, mixed $raw): ?array
    {
        if ($raw===null || !is_string($raw) || trim($raw)==='' || strlen($raw)>512
            || !preg_match('//u',$raw) || preg_match('/[\x00-\x1f\x7f]/',$raw)) {
            return null;
        }
        return ['kind'=>$kind,'keyKind'=>'label','externalKey'=>$raw];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value)===count($expected)
            && array_diff($expected,array_keys($value))===[]
            && array_diff(array_keys($value),$expected)===[];
    }

    private static function localAliasValid(array $row, string $hotelKey, int $hotelId): bool
    {
        if (($row['acquired_via'] ?? null)!=='canonical_local_alias_v1'
            || !is_string($row['source_json'] ?? null)
            || !is_string($row['source_sha256'] ?? null)
            || !preg_match('/^[0-9a-f]{64}$/D',$row['source_sha256'])
            || !hash_equals($row['source_sha256'],hash('sha256',$row['source_json']))) {
            return false;
        }
        try {
            $source=json_decode($row['source_json'],true,16,JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }
        $keys=[
            'accepted_local_hotel_id','canonical_hotel_id','derived_from_namespace',
            'derived_from_source_sha256','schema_version',
        ];
        return is_array($source)
            && self::exactKeys($source,$keys)
            && ($source['schema_version'] ?? null)===1
            && ($source['accepted_local_hotel_id'] ?? null)===(int)$hotelKey
            && ($source['canonical_hotel_id'] ?? null)===$hotelId
            && ($source['derived_from_namespace'] ?? null)==='legacy_catalog'
            && is_string($source['derived_from_source_sha256'] ?? null)
            && preg_match('/^[0-9a-f]{64}$/D',$source['derived_from_source_sha256'])===1;
    }

    public static function reference(array $value): array
    {
        if (!in_array($value['kind'] ?? null,['room','meal'],true)
            || !in_array($value['keyKind'] ?? null,['code','label'],true)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_REFERENCE');
        }
        return [
            'kind'=>$value['kind'],
            'keyKind'=>$value['keyKind'],
            'externalKey'=>self::text($value['externalKey'] ?? null,512),
        ];
    }

    private function requireWriteContext(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || !$this->pdo->inTransaction()) {
            throw new RuntimeException('HOTEL_STAY_V2_CALLER_TRANSACTION_REQUIRED');
        }
    }

    private function one(string $sql, array $params): array|false
    {
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function readable(): bool
    {
        $slots=implode(',',array_fill(0,count(self::TABLES),'?'));
        $stmt=$this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$slots.')'
        );
        $stmt->execute(self::TABLES);
        return (int)$stmt->fetchColumn()===count(self::TABLES);
    }

    private static function conceptDto(array $row, string $kind): array
    {
        $facts=json_decode((string)$row['facts_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($facts)) throw new RuntimeException('HOTEL_STAY_V2_FACTS');
        return [
            'kind'=>$kind,
            'id'=>(int)$row['id'],
            'hotelId'=>(int)$row['anytour_hotel_id'],
            'localKey'=>(string)$row['local_key'],
            'nameRu'=>(string)$row['name_ru'],
            'facts'=>$facts,
            'revision'=>(int)$row['revision'],
        ];
    }

    private function conceptsForHotels(string $kind, array $hotelIds): array
    {
        if (!array_is_list($hotelIds) || count($hotelIds)>self::HOTEL_BATCH_LIMIT) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_HOTEL_BATCH');
        }
        $ids=[];
        foreach ($hotelIds as $hotelId) $ids[self::id($hotelId)]=true;
        if ($ids===[]) return [];

        $ids=array_keys($ids);
        $result=[];
        foreach ($ids as $hotelId) $result[$hotelId]=[];
        $table=$kind==='room'?'anytour_hotel_room_concepts_v2':'anytour_hotel_meal_concepts_v2';
        $slots=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->pdo->prepare(
            'SELECT c.* FROM '.$table.' c JOIN anytour_hotels h ON h.id=c.anytour_hotel_id
             WHERE c.anytour_hotel_id IN ('.$slots.') AND c.is_active=1 AND h.is_active=1
             ORDER BY c.anytour_hotel_id,c.id'
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['anytour_hotel_id']][]=self::conceptDto($row,$kind);
        }
        return $result;
    }

    public function rooms(int $hotelId): array
    {
        return $this->roomsForHotels([$hotelId])[$hotelId] ?? [];
    }

    public function meals(int $hotelId): array
    {
        return $this->mealsForHotels([$hotelId])[$hotelId] ?? [];
    }

    public function roomsForHotels(array $hotelIds): array
    {
        return $this->conceptsForHotels('room',$hotelIds);
    }

    public function mealsForHotels(array $hotelIds): array
    {
        return $this->conceptsForHotels('meal',$hotelIds);
    }

    public function createConcept(string $kind, int $hotelId, string $localKey, string $nameRu, array $facts): int
    {
        if (!in_array($kind,['room','meal'],true)) throw new InvalidArgumentException('HOTEL_STAY_V2_KIND');
        self::id($hotelId);
        self::text($localKey,128);
        self::text($nameRu,255);
        $facts=self::facts($facts);
        $this->requireWriteContext();

        if (!$this->one('SELECT id FROM anytour_hotels WHERE id=? AND is_active=1 FOR UPDATE',[$hotelId])) {
            throw new RuntimeException('HOTEL_STAY_V2_HOTEL_UNAVAILABLE');
        }

        $table=$kind==='room'?'anytour_hotel_room_concepts_v2':'anytour_hotel_meal_concepts_v2';
        $stmt=$this->pdo->prepare(
            'INSERT INTO '.$table.'
             (anytour_hotel_id,local_key,name_ru,facts_json,revision,is_active,created_at,updated_at)
             VALUES (?,?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([$hotelId,$localKey,$nameRu,self::json($facts)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function targetExists(string $kind, int $hotelId, int $targetId): bool
    {
        $table=$kind==='room'?'anytour_hotel_room_concepts_v2':'anytour_hotel_meal_concepts_v2';
        return $this->one(
            'SELECT id FROM '.$table.' WHERE id=? AND anytour_hotel_id=? AND is_active=1 FOR UPDATE',
            [$targetId,$hotelId]
        )!==false;
    }

    /**
     * Record one exact reviewed supplier fact. Raw supplier key is preserved byte-for-byte.
     * There is deliberately no code/label guessing and no cross-hotel target lookup.
     */
    public function recordDecision(
        array $scope,
        array $reference,
        int $hotelId,
        string $state,
        ?int $targetId,
        array $evidence
    ): int {
        $scope=self::scope($scope);
        $reference=self::reference($reference);
        self::id($hotelId);
        if (!in_array($state,['pending','accepted','rejected','conflict'],true)
            || ($state==='accepted')!==($targetId!==null)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_STATE_TARGET');
        }
        if ($targetId!==null) self::id($targetId);

        $evidenceRef=self::text($evidence['ref'] ?? null,255);
        $reviewedBy=self::text($evidence['reviewedBy'] ?? null,128);
        $sha=$evidence['sha256'] ?? null;
        if (!is_string($sha) || !preg_match('/^[0-9a-f]{64}$/D',$sha)) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_EVIDENCE_SHA');
        }

        $this->requireWriteContext();
        $source=$this->one(
            'SELECT s.anytour_hotel_id FROM anytour_hotel_sources s
             JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
             WHERE s.namespace=? AND s.external_key=? AND h.is_active=1 FOR UPDATE',
            [$scope['namespace'],$scope['hotelKey']]
        );
        if (!$source || (int)$source['anytour_hotel_id']!==$hotelId) {
            throw new RuntimeException('HOTEL_STAY_V2_SOURCE_DRIFT');
        }
        if ($targetId!==null && !$this->targetExists($reference['kind'],$hotelId,$targetId)) {
            throw new RuntimeException('HOTEL_STAY_V2_CROSS_HOTEL_TARGET');
        }

        $stmt=$this->pdo->prepare(
            'INSERT INTO anytour_hotel_stay_mappings_v2
             (namespace,external_hotel_key,operator_key,kind,key_kind,external_key,anytour_hotel_id,
              room_concept_id,meal_concept_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $scope['namespace'],$scope['hotelKey'],$scope['operatorKey'],
            $reference['kind'],$reference['keyKind'],$reference['externalKey'],$hotelId,
            $reference['kind']==='room'?$targetId:null,
            $reference['kind']==='meal'?$targetId:null,
            $state,$evidenceRef,$sha,$reviewedBy
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Resolve exact reviewed room/meal decisions for a bounded cached-offer cohort.
     * Caller supplies only already identity-validated AnyTour/legacy pairs.
     * No mapping is created, no raw supplier value is normalized, and missing operator
     * evidence fails closed instead of borrowing another operator/provider decision.
     */
    public function resolveOfferFactsBatch(array $requests): array
    {
        if (!array_is_list($requests) || count($requests)>self::OFFER_BATCH_LIMIT) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_OFFER_BATCH');
        }
        if ($requests===[]) return [];

        $expected=[
            'anytourHotelId','legacyHotelId','provider','providerHotelRefDigest',
            'operatorRaw','roomRaw','mealRaw',
        ];
        $prepared=[];$wanted=[];
        foreach ($requests as $request) {
            if (!is_array($request) || !self::exactKeys($request,$expected)) {
                throw new InvalidArgumentException('HOTEL_STAY_V2_OFFER_REQUEST');
            }
            $hotelId=self::id($request['anytourHotelId']);
            $legacyId=self::id($request['legacyHotelId']);
            $scope=self::offerScope(
                (string)$request['provider'],
                $legacyId,
                (string)$request['providerHotelRefDigest'],
                $request['operatorRaw']
            );
            $room=self::offerReference('room',$request['roomRaw']);
            $meal=self::offerReference('meal',$request['mealRaw']);
            $prepared[]=[
                'hotelId'=>$hotelId,'scope'=>$scope,'room'=>$room,'meal'=>$meal,
            ];
            if ($scope!==null) {
                $wanted[self::json([$scope['hotelKey'],$scope['operatorKey']])]=[
                    $scope['hotelKey'],$scope['operatorKey'],
                ];
            }
        }

        $byRef=[];
        if ($wanted!==[]) {
            $clauses=[];$params=['anytour_local_id'];
            foreach ($wanted as [$hotelKey,$operatorKey]) {
                $clauses[]='(m.external_hotel_key=? AND m.operator_key=?)';
                $params[]=$hotelKey;$params[]=$operatorKey;
            }
            $sql='SELECT m.external_hotel_key,m.operator_key,m.kind,m.key_kind,m.external_key,m.anytour_hotel_id,m.state,
                m.room_concept_id,m.meal_concept_id,
                s.acquired_via,s.source_json,s.source_sha256,
                r.local_key AS room_local_key,r.name_ru AS room_name,r.facts_json AS room_facts,
                r.revision AS room_revision,r.is_active AS room_active,
                p.local_key AS meal_local_key,p.name_ru AS meal_name,p.facts_json AS meal_facts,
                p.revision AS meal_revision,p.is_active AS meal_active
                FROM anytour_hotel_stay_mappings_v2 m
                JOIN anytour_hotel_sources s
                  ON s.namespace=m.namespace AND s.external_key=m.external_hotel_key
                 AND s.anytour_hotel_id=m.anytour_hotel_id
                JOIN anytour_hotels h ON h.id=m.anytour_hotel_id AND h.is_active=1
                LEFT JOIN anytour_hotel_room_concepts_v2 r
                  ON r.id=m.room_concept_id AND r.anytour_hotel_id=m.anytour_hotel_id
                LEFT JOIN anytour_hotel_meal_concepts_v2 p
                  ON p.id=m.meal_concept_id AND p.anytour_hotel_id=m.anytour_hotel_id
                WHERE m.namespace=? AND ('.implode(' OR ',$clauses).')';
            $stmt=$this->pdo->prepare($sql);$stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $hotelKey=(string)$row['external_hotel_key'];
                $hotelId=(int)$row['anytour_hotel_id'];
                if (!self::localAliasValid($row,$hotelKey,$hotelId)) continue;
                $key=self::json([
                    $hotelKey,(string)$row['operator_key'],(string)$row['kind'],
                    (string)$row['key_kind'],(string)$row['external_key'],
                ]);
                if (isset($byRef[$key])) throw new RuntimeException('HOTEL_STAY_V2_CONFLICTING_MAPPING');
                $byRef[$key]=$row;
            }
        }

        $resolved=[];
        foreach ($prepared as $item) {
            $scope=$item['scope'];
            if ($scope===null) {
                $resolved[]=[
                    'source'=>'anytour-hotel-stay-v2',
                    'exactScope'=>false,
                    'reason'=>'operator-unresolved',
                    'room'=>['status'=>$item['room']===null?'missing':'unmapped','canonical'=>null],
                    'meal'=>['status'=>$item['meal']===null?'missing':'unmapped','canonical'=>null],
                ];
                continue;
            }

            $parts=[];
            foreach (['room','meal'] as $kind) {
                $ref=$item[$kind];
                if ($ref===null) {
                    $parts[$kind]=['status'=>'missing','canonical'=>null];
                    continue;
                }
                $key=self::json([
                    $scope['hotelKey'],$scope['operatorKey'],$ref['kind'],$ref['keyKind'],$ref['externalKey'],
                ]);
                $row=$byRef[$key] ?? null;
                if ($row===null) {
                    $parts[$kind]=['status'=>'unmapped','canonical'=>null];
                    continue;
                }
                if ((int)$row['anytour_hotel_id']!==$item['hotelId']) {
                    $parts[$kind]=['status'=>'source-drift','canonical'=>null];
                    continue;
                }

                $status=(string)$row['state'];$canonical=null;
                if ($status==='accepted') {
                    if ($kind==='room' && (int)($row['room_active'] ?? 0)===1) {
                        $canonical=self::conceptDto([
                            'id'=>$row['room_concept_id'],'anytour_hotel_id'=>$item['hotelId'],
                            'local_key'=>$row['room_local_key'],'name_ru'=>$row['room_name'],
                            'facts_json'=>$row['room_facts'],'revision'=>$row['room_revision'],
                        ],'room');
                    } elseif ($kind==='meal' && (int)($row['meal_active'] ?? 0)===1) {
                        $canonical=self::conceptDto([
                            'id'=>$row['meal_concept_id'],'anytour_hotel_id'=>$item['hotelId'],
                            'local_key'=>$row['meal_local_key'],'name_ru'=>$row['meal_name'],
                            'facts_json'=>$row['meal_facts'],'revision'=>$row['meal_revision'],
                        ],'meal');
                    } else {
                        $status='target-unavailable';
                    }
                }
                $parts[$kind]=['status'=>$status,'canonical'=>$canonical];
            }
            $resolved[]=[
                'source'=>'anytour-hotel-stay-v2',
                'exactScope'=>true,
                'reason'=>null,
                'room'=>$parts['room'],
                'meal'=>$parts['meal'],
            ];
        }
        return $resolved;
    }

    /**
     * Resolve only exact reviewed facts inside the exact source hotel/operator scope.
     * Missing/unresolved source identity never creates a canonical concept.
     */
    public function resolve(array $scope, array $references): array
    {
        $scope=self::scope($scope);
        if (!array_is_list($references) || !$references || count($references)>self::BATCH_LIMIT) {
            throw new InvalidArgumentException('HOTEL_STAY_V2_REFERENCE_BATCH');
        }
        $refs=[];
        foreach ($references as $value) {
            if (!is_array($value)) throw new InvalidArgumentException('HOTEL_STAY_V2_REFERENCE_OBJECT');
            $refs[]=self::reference($value);
        }

        $source=$this->one(
            'SELECT s.anytour_hotel_id,h.is_active
             FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
             WHERE s.namespace=? AND s.external_key=?',
            [$scope['namespace'],$scope['hotelKey']]
        );
        $hotelId=$source && (int)$source['is_active']===1?(int)$source['anytour_hotel_id']:null;
        if ($hotelId===null) {
            return [
                'source'=>'anytour-hotel-stay-v2',
                'hotelId'=>null,
                'items'=>array_map(fn($ref)=>['reference'=>$ref,'status'=>'hotel-unresolved','canonical'=>null],$refs),
            ];
        }

        $stmt=$this->pdo->prepare(
            'SELECT m.*,r.local_key AS room_local_key,r.name_ru AS room_name,r.facts_json AS room_facts,r.revision AS room_revision,r.is_active AS room_active,
                    p.local_key AS meal_local_key,p.name_ru AS meal_name,p.facts_json AS meal_facts,p.revision AS meal_revision,p.is_active AS meal_active
             FROM anytour_hotel_stay_mappings_v2 m
             LEFT JOIN anytour_hotel_room_concepts_v2 r
               ON r.id=m.room_concept_id AND r.anytour_hotel_id=m.anytour_hotel_id
             LEFT JOIN anytour_hotel_meal_concepts_v2 p
               ON p.id=m.meal_concept_id AND p.anytour_hotel_id=m.anytour_hotel_id
             WHERE m.namespace=? AND m.external_hotel_key=? AND m.operator_key=? AND m.anytour_hotel_id=?'
        );
        $stmt->execute([$scope['namespace'],$scope['hotelKey'],$scope['operatorKey'],$hotelId]);
        $byRef=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key=self::json([$row['kind'],$row['key_kind'],$row['external_key']]);
            if (isset($byRef[$key])) throw new RuntimeException('HOTEL_STAY_V2_CONFLICTING_MAPPING');
            $byRef[$key]=$row;
        }

        $items=[];
        foreach ($refs as $ref) {
            $row=$byRef[self::json([$ref['kind'],$ref['keyKind'],$ref['externalKey']])] ?? null;
            $status=$row['state'] ?? 'unmapped';
            $canonical=null;
            if ($status==='accepted') {
                if ($ref['kind']==='room' && (int)($row['room_active'] ?? 0)===1) {
                    $canonical=self::conceptDto([
                        'id'=>$row['room_concept_id'],'anytour_hotel_id'=>$hotelId,
                        'local_key'=>$row['room_local_key'],'name_ru'=>$row['room_name'],
                        'facts_json'=>$row['room_facts'],'revision'=>$row['room_revision'],
                    ],'room');
                } elseif ($ref['kind']==='meal' && (int)($row['meal_active'] ?? 0)===1) {
                    $canonical=self::conceptDto([
                        'id'=>$row['meal_concept_id'],'anytour_hotel_id'=>$hotelId,
                        'local_key'=>$row['meal_local_key'],'name_ru'=>$row['meal_name'],
                        'facts_json'=>$row['meal_facts'],'revision'=>$row['meal_revision'],
                    ],'meal');
                } else {
                    $status='target-unavailable';
                }
            }
            $items[]=['reference'=>$ref,'status'=>$status,'canonical'=>$canonical];
        }

        return ['source'=>'anytour-hotel-stay-v2','hotelId'=>$hotelId,'items'=>$items];
    }
}

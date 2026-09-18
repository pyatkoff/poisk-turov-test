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

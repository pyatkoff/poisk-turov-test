<?php
/** Local room/meal identities. No endpoint, connection, schema install or supplier calls. */
declare(strict_types=1);

final class AnyTourStayCatalog
{
    public const BATCH_LIMIT = 100;
    public const HOTEL_BATCH_LIMIT = 1000;
    public function __construct(private PDO $pdo) {}

    private static function text(mixed $value, int $limit): string
    {
        if (!is_string($value) || trim($value)==='' || strlen($value)>$limit
            || !preg_match('//u', $value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Expected bounded nonempty UTF-8 text');
        }
        // Preserve case, spaces, leading zeros and qualifiers of external identifiers.
        return $value;
    }
    private static function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT)===false) throw new InvalidArgumentException('Invalid local ID');
        return (int)$value;
    }
    public static function scope(array $value): array
    {
        $namespace = self::text($value['namespace'] ?? null, 64);
        if (!preg_match('/^[a-z0-9][a-z0-9_.:-]*$/D', $namespace)) throw new InvalidArgumentException('Invalid namespace');
        return ['namespace'=>$namespace, 'hotelKey'=>self::text($value['hotelKey'] ?? null,128),
            'operatorKey'=>self::text($value['operatorKey'] ?? null,128)];
    }
    public static function reference(array $value): array
    {
        if (!in_array($value['kind'] ?? null,['room','meal'],true)
            || !in_array($value['keyKind'] ?? null,['code','label'],true)) throw new InvalidArgumentException('Invalid reference kind');
        return ['kind'=>$value['kind'],'keyKind'=>$value['keyKind'],
            'externalKey'=>self::text($value['externalKey'] ?? null,512)];
    }
    public static function roomFacts(array $facts): array
    {
        $allowed = ['view','building','bedrooms','areaM2','maxOccupancy'];
        foreach ($facts as $key=>$value) {
            if (!in_array($key,$allowed,true)) throw new InvalidArgumentException('Unknown canonical room fact');
            if ($value===null) continue;
            if (in_array($key,['view','building'],true)) self::text($value,255);
            elseif ($key==='areaM2') {
                if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value<=0 || $value>10000) {
                    throw new InvalidArgumentException('Invalid room area');
                }
            } elseif (!is_int($value) || $value<($key==='bedrooms'?0:1) || $value>100) {
                throw new InvalidArgumentException('Invalid room capacity or bedroom count');
            }
        }
        ksort($facts);
        return $facts;
    }
    private static function json(array $value): string
    {
        return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    private function writeContext(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || !$this->pdo->inTransaction()) {
            throw new RuntimeException('Explicit caller-owned MySQL transaction required');
        }
    }
    private function one(string $sql, array $params): array|false
    {
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function meals(): array
    {
        $rows=$this->pdo->query('SELECT id,code,name_ru,family_code,qualifiers_json FROM anytour_meal_plans WHERE is_active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(self::mealDto(...),$rows);
    }
    private static function mealDto(array $row): array
    {
        $qualifiers=json_decode($row['qualifiers_json'],true,32,JSON_THROW_ON_ERROR);
        if (!is_array($qualifiers)) throw new RuntimeException('Invalid meal qualifiers');
        return ['id'=>(int)$row['id'],'code'=>$row['code'],'nameRu'=>$row['name_ru'],
            'familyCode'=>$row['family_code'],'qualifiers'=>$qualifiers];
    }
    private static function roomDto(array $row): array
    {
        $facts=json_decode($row['facts_json'],true,32,JSON_THROW_ON_ERROR);
        if (!is_array($facts)) throw new RuntimeException('Invalid room facts');
        return ['id'=>(int)$row['id'],'hotelId'=>(int)$row['anytour_hotel_id'],
            'localKey'=>$row['local_key'],'nameRu'=>$row['name_ru'],'categoryCode'=>$row['category_code'],
            'facts'=>self::roomFacts($facts),'revision'=>(int)$row['revision']];
    }
    public function rooms(int $hotelId): array
    {
        self::id($hotelId);
        $stmt=$this->pdo->prepare('SELECT r.* FROM anytour_hotel_rooms r JOIN anytour_hotels h ON h.id=r.anytour_hotel_id
            WHERE r.anytour_hotel_id=? AND r.is_active=1 AND h.is_active=1 ORDER BY r.id');
        $stmt->execute([$hotelId]);
        return array_map(self::roomDto(...),$stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    /** Read first-party room dictionaries for a bounded set of canonical hotels in one query. */
    public function roomsForHotels(array $hotelIds): array
    {
        if (!array_is_list($hotelIds) || count($hotelIds)>self::HOTEL_BATCH_LIMIT) {
            throw new InvalidArgumentException('Expected <=1000 canonical hotel IDs');
        }
        $ids=[];
        foreach ($hotelIds as $hotelId) $ids[self::id($hotelId)]=true;
        if (!$ids) return [];
        $ids=array_keys($ids);$result=[];
        foreach ($ids as $hotelId) $result[$hotelId]=[];
        $slots=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->pdo->prepare('SELECT r.* FROM anytour_hotel_rooms r JOIN anytour_hotels h ON h.id=r.anytour_hotel_id
            WHERE r.anytour_hotel_id IN ('.$slots.') AND r.is_active=1 AND h.is_active=1 ORDER BY r.anytour_hotel_id,r.id');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int)$row['anytour_hotel_id']][]=self::roomDto($row);
        return $result;
    }
    /** Insert only; no overwrite/upsert, allocation of supplier-shaped local IDs, or commit. */
    public function createRoom(int $hotelId, string $localKey, string $nameRu, ?string $categoryCode, array $facts): int
    {
        self::id($hotelId); self::text($localKey,128); self::text($nameRu,255);
        if ($categoryCode!==null) self::text($categoryCode,64);
        $facts=self::roomFacts($facts); $this->writeContext();
        if (!$this->one('SELECT id FROM anytour_hotels WHERE id=? AND is_active=1 FOR UPDATE',[$hotelId])) {
            throw new RuntimeException('Canonical hotel unavailable');
        }
        $stmt=$this->pdo->prepare('INSERT INTO anytour_hotel_rooms
            (anytour_hotel_id,local_key,name_ru,category_code,facts_json,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())');
        $stmt->execute([$hotelId,$localKey,$nameRu,$categoryCode,self::json($facts)]);
        return (int)$this->pdo->lastInsertId();
    }
    /** Exact reviewed decision only. Existing decisions, including negative ones, are never replaced. */
    public function recordDecision(array $scope, array $reference, int $hotelId, string $state, ?int $targetId, array $evidence): int
    {
        $scope=self::scope($scope); $reference=self::reference($reference); self::id($hotelId);
        if (!in_array($state,['pending','accepted','rejected','conflict'],true)
            || ($state==='accepted')!==($targetId!==null)) throw new InvalidArgumentException('State/target mismatch');
        if ($targetId!==null) self::id($targetId);
        $ref=self::text($evidence['ref'] ?? null,255); $reviewer=self::text($evidence['reviewedBy'] ?? null,128);
        $hash=$evidence['sha256'] ?? null;
        if (!is_string($hash) || !preg_match('/^[0-9a-f]{64}$/D',$hash)) throw new InvalidArgumentException('Evidence digest required');
        $this->writeContext();
        // Read/lock an EXISTING hotel-source relation, never create a MATCH decision here.
        $source=$this->one('SELECT s.anytour_hotel_id FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
            WHERE s.namespace=? AND s.external_key=? AND h.is_active=1 FOR UPDATE',[$scope['namespace'],$scope['hotelKey']]);
        if (!$source || (int)$source['anytour_hotel_id']!==$hotelId) throw new RuntimeException('Reviewed source hotel changed or unresolved');
        if ($targetId!==null) {
            $target=$reference['kind']==='room'
                ? $this->one('SELECT id FROM anytour_hotel_rooms WHERE id=? AND anytour_hotel_id=? AND is_active=1 FOR UPDATE',[$targetId,$hotelId])
                : $this->one('SELECT id FROM anytour_meal_plans WHERE id=? AND is_active=1 FOR UPDATE',[$targetId]);
            if (!$target) throw new RuntimeException('Reviewed target unavailable or belongs to another hotel');
        }
        $stmt=$this->pdo->prepare('INSERT INTO anytour_stay_mappings
            (namespace,external_hotel_key,operator_key,kind,key_kind,external_key,anytour_hotel_id,room_id,meal_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $stmt->execute([$scope['namespace'],$scope['hotelKey'],$scope['operatorKey'],$reference['kind'],$reference['keyKind'],
            $reference['externalKey'],$hotelId,$reference['kind']==='room'?$targetId:null,
            $reference['kind']==='meal'?$targetId:null,$state,$ref,$hash,$reviewer]);
        return (int)$this->pdo->lastInsertId();
    }
    /** One SQL snapshot per 1..100 references. No name guessing or code-to-label fallback. */
    public function resolve(array $scope, array $references): array
    {
        $scope=self::scope($scope);
        if (!array_is_list($references) || !$references || count($references)>self::BATCH_LIMIT) throw new InvalidArgumentException('Expected 1..100 references');
        $refs=[]; $conditions=[]; $params=[$scope['operatorKey']];
        foreach ($references as $value) {
            if (!is_array($value)) throw new InvalidArgumentException('Reference must be an object');
            $ref=self::reference($value); $refs[]=$ref;
            $conditions[]='(m.kind=? AND m.key_kind=? AND m.external_key=?)';
            array_push($params,$ref['kind'],$ref['keyKind'],$ref['externalKey']);
        }
        array_push($params,$scope['namespace'],$scope['hotelKey']);
        $sql='SELECT s.anytour_hotel_id AS source_hotel,h.is_active AS hotel_active,
            m.id AS mapping_id,m.anytour_hotel_id AS mapped_hotel,m.kind,m.key_kind,m.external_key,m.state,
            r.id AS room_id,r.local_key AS room_local_key,r.name_ru AS room_name,r.category_code,r.facts_json,r.revision,r.is_active AS room_active,
            p.id AS meal_id,p.code,p.name_ru AS meal_name,p.family_code,p.qualifiers_json,p.is_active AS meal_active
            FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
            LEFT JOIN anytour_stay_mappings m ON m.namespace=s.namespace AND m.external_hotel_key=s.external_key
                AND m.operator_key=? AND ('.implode(' OR ',$conditions).')
            LEFT JOIN anytour_hotel_rooms r ON r.id=m.room_id AND r.anytour_hotel_id=m.anytour_hotel_id
            LEFT JOIN anytour_meal_plans p ON p.id=m.meal_id
            WHERE s.namespace=? AND s.external_key=?';
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $hotelId=$rows && (int)$rows[0]['hotel_active']===1?(int)$rows[0]['source_hotel']:null;
        $byRef=[];
        foreach ($rows as $row) if ($row['mapping_id']!==null) {
            $key=self::json([$row['kind'],$row['key_kind'],$row['external_key']]);
            if (isset($byRef[$key])) throw new RuntimeException('Conflicting exact mappings');
            $byRef[$key]=$row;
        }
        $items=[];
        foreach ($refs as $ref) {
            $row=$byRef[self::json(array_values($ref))] ?? null;
            $status=$hotelId===null?'hotel-unresolved':($row['state'] ?? 'unmapped');
            $canonical=null;
            if ($hotelId!==null && $row && (int)$row['mapped_hotel']!==$hotelId) $status='source-drift';
            elseif ($status==='accepted') {
                if ($ref['kind']==='room' && (int)$row['room_active']===1) {
                    $canonical=self::roomDto(['id'=>$row['room_id'],'anytour_hotel_id'=>$hotelId,'local_key'=>$row['room_local_key'],'name_ru'=>$row['room_name'],
                        'category_code'=>$row['category_code'],'facts_json'=>$row['facts_json'],'revision'=>$row['revision']]);
                } elseif ($ref['kind']==='meal' && (int)$row['meal_active']===1) {
                    $canonical=self::mealDto(['id'=>$row['meal_id'],'name_ru'=>$row['meal_name'],'code'=>$row['code'],
                        'family_code'=>$row['family_code'],'qualifiers_json'=>$row['qualifiers_json']]);
                } else $status='target-unavailable';
            }
            $items[]=['reference'=>$ref,'status'=>$status,'canonical'=>$canonical];
        }
        return ['source'=>'anytour-stay-catalog','hotelId'=>$hotelId,'items'=>$items];
    }
}

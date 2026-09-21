<?php
/** First-party SEARCH categories and reviewed native catalogue IDs. Read-only. */
declare(strict_types=1);
final class AnyTourSearchMealCatalogV1
{
    public const SOURCE='anytour-search-meal-v1';
    public const LIMIT=1000;
    public function __construct(private PDO $pdo) {}
    public static function scope(string $provider,string $key): void
    {
        if (!in_array($provider,['tourvisor','anex','andromeda'],true)
            || preg_match('/^[a-zA-Z0-9_.:-]{1,128}$/D',$key)!==1) {
            throw new InvalidArgumentException('SEARCH_MEAL_SCOPE');
        }
    }
    private static function id(mixed $id): int
    {
        if ((!is_int($id)&&!is_string($id)) || preg_match('/^[1-9][0-9]*$/D',(string)$id)!==1
            || filter_var($id,FILTER_VALIDATE_INT)===false || (int)$id>9007199254740991) {
            throw new InvalidArgumentException('SEARCH_MEAL_ID');
        }
        return (int)$id;
    }
    private function tables(array $names): bool
    {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')');
        $stmt->execute($names);return (int)$stmt->fetchColumn()===count($names);
    }
    private static function plan(array $row): array
    {
        return ['id'=>self::id($row['id']),'code'=>(string)$row['code'],'nameRu'=>(string)$row['name_ru']];
    }
    private static function evidence(array $row): bool
    {
        return is_string($row['evidence_sha256']??null)
            && preg_match('/^[0-9a-f]{64}$/D',$row['evidence_sha256'])===1
            && is_string($row['evidence_ref']??null) && trim($row['evidence_ref'])!==''
            && is_string($row['reviewed_by']??null) && trim($row['reviewed_by'])!=='';
    }
    /** Exact provider catalogue scope; all local labels, only accepted native IDs. */
    public function catalogue(string $provider,string $scopeKey): array
    {
        self::scope($provider,$scopeKey);
        $result=['source'=>self::SOURCE,'provider'=>$provider,'scopeKey'=>$scopeKey,'available'=>false,'plans'=>[]];
        if (!$this->tables(['anytour_meal_plans'])) return $result+['revision'=>null];
        $ready=$this->tables(['anytour_search_meal_provider_mappings_v1']);
        $sql=$ready?
            "SELECT p.id,p.code,p.name_ru,m.external_id,m.evidence_ref,m.evidence_sha256,m.reviewed_by
             FROM anytour_meal_plans p LEFT JOIN anytour_search_meal_provider_mappings_v1 m
             ON m.meal_plan_id=p.id AND m.state='accepted' AND m.provider=? AND m.scope_key=?
             WHERE p.is_active=1 ORDER BY p.id,m.external_id LIMIT 10001":
            'SELECT id,code,name_ru FROM anytour_meal_plans WHERE is_active=1 ORDER BY id LIMIT 1001';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($ready?[$provider,$scopeKey]:[]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>10000) throw new RuntimeException('SEARCH_MEAL_LIMIT');
        $plans=[];$ids=[];
        foreach ($rows as $row) {
            $id=self::id($row['id']);$plan=self::plan($row);
            if (!isset($plans[$id])) $plans[$id]=$plan+['nativeIds'=>[]];
            elseif (array_intersect_key($plans[$id],$plan)!==$plan) throw new RuntimeException('SEARCH_MEAL_CONFLICT');
            if (($row['external_id']??null)===null || !self::evidence($row)) continue;
            $native=$row['external_id'];
            if (!is_string($native)||trim($native)===''||strlen($native)>128||!preg_match('//u',$native)||preg_match('/[\x00-\x1f\x7f]/',$native)) throw new RuntimeException('SEARCH_MEAL_NATIVE_ID');
            if (isset($ids['id:'.$native])) throw new RuntimeException('SEARCH_MEAL_CONFLICT');
            $ids['id:'.$native]=$id;$plans[$id]['nativeIds'][]=$native;
        }
        if (count($plans)>self::LIMIT) throw new RuntimeException('SEARCH_MEAL_LIMIT');
        $result['available']=$ready;$result['plans']=array_values($plans);
        $result['revision']=hash('sha256',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        return $result;
    }
    /** Source adapters call this; it never sends a request or fabricates IDs. */
    public function nativeIds(string $provider,string $scopeKey,array $localIds): array
    {
        if (!array_is_list($localIds)||count($localIds)>100) throw new InvalidArgumentException('SEARCH_MEAL_SELECTION');
        $wanted=[];foreach($localIds as $id)$wanted[self::id($id)]=true;
        self::scope($provider,$scopeKey);if (!$wanted) return [];
        $snapshot=$this->catalogue($provider,$scopeKey);
        if (!$snapshot['available']) throw new RuntimeException('SEARCH_MEAL_UNAVAILABLE');
        $plans=array_column($snapshot['plans'],null,'id');$native=[];
        foreach ($wanted as $id=>$_) {
            if (!isset($plans[$id])||!$plans[$id]['nativeIds']) throw new RuntimeException('SEARCH_MEAL_UNMAPPED');
            foreach($plans[$id]['nativeIds'] as $code)$native['id:'.$code]=$code;
        }
        return array_values($native);
    }
    /** Preserve exact hotel stay concepts, attaching a separate reviewed search category. */
    public function attachSearchPlans(array $hotels): array
    {
        if (!$this->tables(['anytour_meal_plans','anytour_search_meal_memberships_v1','anytour_hotel_meal_concepts_v2'])) return $hotels;
        $wanted=[];
        foreach ($hotels as $hotel) {
            $hotelId=self::id($hotel['anytourHotelId']);
            foreach ($hotel['offers'] as $offer) {
                $match=$offer['stayMatch']??null;$part=$match['meal']??null;$c=$part['canonical']??null;
                if (($match['source']??null)!=='anytour-hotel-stay-v2'||($match['exactScope']??null)!==true
                    ||($part['status']??null)!=='accepted'||($c['kind']??null)!=='meal'
                    ||($c['hotelId']??null)!==$hotelId) continue;
                $id=self::id($c['id']);$wanted[$hotelId.':'.$id]=[$hotelId,$id];
            }
        }
        $plans=[];
        foreach(array_chunk(array_values($wanted),self::LIMIT) as $chunk) {
            $where=[];$params=[];
            foreach($chunk as [$hotelId,$id]){$where[]='(c.anytour_hotel_id=? AND c.id=?)';$params[]=$hotelId;$params[]=$id;}
            $stmt=$this->pdo->prepare("SELECT c.anytour_hotel_id,c.id AS concept_id,c.revision,p.id,p.code,p.name_ru,m.evidence_ref,m.evidence_sha256,m.reviewed_by
                FROM anytour_search_meal_memberships_v1 m JOIN anytour_hotel_meal_concepts_v2 c ON c.id=m.meal_concept_id AND c.anytour_hotel_id=m.anytour_hotel_id AND c.is_active=1 AND c.revision=m.meal_concept_revision
                JOIN anytour_hotels h ON h.id=c.anytour_hotel_id AND h.is_active=1
                JOIN anytour_meal_plans p ON p.id=m.meal_plan_id AND p.is_active=1
                WHERE m.state='accepted' AND (".implode(' OR ',$where).')');
            $stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                if (!self::evidence($row)) continue;
                $key=$row['anytour_hotel_id'].':'.$row['concept_id'];
                if (isset($plans[$key])) throw new RuntimeException('SEARCH_MEAL_CONFLICT');
                $plans[$key]=['source'=>self::SOURCE,'hotelId'=>(int)$row['anytour_hotel_id'],
                    'conceptId'=>(int)$row['concept_id'],'conceptRevision'=>(int)$row['revision'],'plan'=>self::plan($row)];
            }
        }
        foreach ($hotels as &$hotel) foreach ($hotel['offers'] as &$offer) {
            $match=$offer['stayMatch']??null;$part=$match['meal']??null;$c=$part['canonical']??null;
            if (($match['source']??null)!=='anytour-hotel-stay-v2'||($match['exactScope']??null)!==true||($part['status']??null)!=='accepted'||($c['kind']??null)!=='meal'||($c['hotelId']??null)!==(int)$hotel['anytourHotelId']) continue;
            $key=$hotel['anytourHotelId'].':'.($c['id']??'');
            if (isset($wanted[$key],$plans[$key]) && ($c['revision']??null)===$plans[$key]['conceptRevision']) $offer['searchMeal']=$plans[$key];
        }
        unset($hotel,$offer);return $hotels;
    }
}

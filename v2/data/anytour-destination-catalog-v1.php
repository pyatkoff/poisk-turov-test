<?php
/** Independent AnyTour destination IDs and exact reviewed provider bridges. Read-only. */
declare(strict_types=1);
final class AnyTourDestinationCatalogV1
{
    public const LIMIT=1000;
    private const PROVIDERS=['tourvisor'=>true,'anex'=>true,'samo'=>true,'andromeda'=>true];
    private const KINDS=['country'=>true,'region'=>true,'subregion'=>true];
    public function __construct(private PDO $pdo) {}
    private static function id(mixed $v): int {
        if((!is_int($v)&&!is_string($v))||preg_match('/^[1-9][0-9]*$/D',(string)$v)!==1||filter_var($v,FILTER_VALIDATE_INT)===false||(int)$v>9007199254740991)throw new InvalidArgumentException('DESTINATION_ID');
        return(int)$v;
    }
    private static function provider(string $v): string {if(!isset(self::PROVIDERS[$v]))throw new InvalidArgumentException('DESTINATION_PROVIDER');return$v;}
    private static function kind(string $v): string {if(!isset(self::KINDS[$v]))throw new InvalidArgumentException('DESTINATION_KIND');return$v;}
    private static function external(mixed $v): string {
        if(!is_string($v)||trim($v)===''||strlen($v)>128||!preg_match('//u',$v)||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('DESTINATION_EXTERNAL_ID');
        return$v;
    }
    public function readable(): bool {
        $s=$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_destinations_v1','anytour_destination_sources_v1')");
        return(int)$s->fetchColumn()===2;
    }
    private static function dto(array $r): array {
        return['id'=>self::id($r['id']),'kind'=>self::kind((string)$r['kind']),'parentId'=>$r['parent_id']===null?null:self::id($r['parent_id']),'nameRu'=>(string)$r['name_ru'],'slug'=>(string)$r['slug'],'revision'=>self::id($r['revision'])];
    }
    public function children(int $parentId,string $kind): array {
        self::id($parentId);self::kind($kind);if($kind==='country')throw new InvalidArgumentException('DESTINATION_PARENT_KIND');
        if(!$this->readable())return[];
        $s=$this->pdo->prepare('SELECT id,kind,parent_id,name_ru,slug,revision FROM anytour_destinations_v1 WHERE parent_id=? AND kind=? AND is_active=1 ORDER BY name_ru,id LIMIT 1001');
        $s->execute([$parentId,$kind]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>self::LIMIT)throw new RuntimeException('DESTINATION_LIMIT');
        return array_map(self::dto(...),$rows);
    }
    /** Local IDs -> exact native IDs. Missing one selected mapping blocks the whole selection. */
    public function nativeIds(string $provider,string $kind,array $localIds): array {
        self::provider($provider);self::kind($kind);
        if(!array_is_list($localIds)||count($localIds)>100)throw new InvalidArgumentException('DESTINATION_SELECTION');
        $ids=[];foreach($localIds as $id)$ids[self::id($id)]=true;$ids=array_keys($ids);if(!$ids)return[];
        if(!$this->readable())throw new RuntimeException('DESTINATION_UNAVAILABLE');
        $slots=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->pdo->prepare("SELECT d.id,d.kind,d.is_active,s.external_id,s.state,s.evidence_ref,s.evidence_sha256,s.reviewed_by
          FROM anytour_destinations_v1 d LEFT JOIN anytour_destination_sources_v1 s
          ON s.anytour_destination_id=d.id AND s.provider=? AND s.kind=d.kind AND s.state='accepted'
          WHERE d.id IN ($slots) AND d.kind=? ORDER BY d.id,s.external_id LIMIT 10001");
        $s->execute([$provider,...$ids,$kind]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>10000)throw new RuntimeException('DESTINATION_LIMIT');
        $found=[];$native=[];
        foreach($rows as $r){
            $id=self::id($r['id']);if((int)$r['is_active']!==1)continue;$found[$id]??=[];
            if($r['external_id']===null)continue;
            if(!is_string($r['evidence_sha256'])||preg_match('/^[a-f0-9]{64}$/D',$r['evidence_sha256'])!==1||trim((string)$r['evidence_ref'])===''||trim((string)$r['reviewed_by'])==='')throw new RuntimeException('DESTINATION_EVIDENCE');
            $ext=self::external($r['external_id']);$key='id:'.$ext;if(isset($native[$key])&&$native[$key]!==$id)throw new RuntimeException('DESTINATION_CONFLICT');
            $native[$key]=$id;$found[$id][]=$ext;
        }
        foreach($ids as $id)if(empty($found[$id]))throw new RuntimeException('DESTINATION_UNMAPPED');
        return array_map(fn($key)=>substr($key,3),array_keys($native));
    }
    /** Exact native ID -> local ID; no names or adjacent hierarchy guessing. */
    public function localId(string $provider,string $kind,string $externalId): ?int {
        self::provider($provider);self::kind($kind);$externalId=self::external($externalId);if(!$this->readable())return null;
        $s=$this->pdo->prepare("SELECT d.id,d.kind,d.is_active,s.evidence_ref,s.evidence_sha256,s.reviewed_by FROM anytour_destination_sources_v1 s JOIN anytour_destinations_v1 d ON d.id=s.anytour_destination_id WHERE s.provider=? AND s.kind=? AND s.external_id=? AND s.state='accepted' LIMIT 2");
        $s->execute([$provider,$kind,$externalId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>1)throw new RuntimeException('DESTINATION_CONFLICT');if(!$rows||(int)$rows[0]['is_active']!==1)return null;
        $r=$rows[0];if(!is_string($r['evidence_sha256'])||preg_match('/^[a-f0-9]{64}$/D',$r['evidence_sha256'])!==1||trim((string)$r['evidence_ref'])===''||trim((string)$r['reviewed_by'])==='')throw new RuntimeException('DESTINATION_EVIDENCE');
        return self::id($r['id']);
    }
}

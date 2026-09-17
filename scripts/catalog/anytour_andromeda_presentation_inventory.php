<?php
/**
 * LOCAL read-only inventory of already-saved Andromeda hotel-level presentation evidence.
 * No supplier/network calls, mapping writes or canonical-profile mutation.
 */
declare(strict_types=1);

final class AnyTourAndromedaPresentationInventory
{
    private const PROVIDER_NAMESPACE = 'andromeda_catalog';
    private const LOCAL_ALIAS_NAMESPACE = 'anytour_local_id';
    private const LOCAL_ALIAS_ACQUIRED_VIA = 'canonical_local_alias_v1';
    private const MAX_ACCEPTED = 50000;
    private const MAX_SEARCH_FILES = 5000;
    private const MAX_FILE_BYTES = 32_000_000;
    private const ALIAS_KEYS = [
        'accepted_local_hotel_id','canonical_hotel_id','derived_from_namespace',
        'derived_from_source_sha256','schema_version',
    ];

    public function __construct(private PDO $pdo, private string $siteRoot) {}

    private static function stable(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class,'stable'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key=>$item) $value[$key] = self::stable($item);
        return $value;
    }

    private static function json(array $value): string
    {
        return json_encode(self::stable($value), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D',$value)
            && filter_var($value,FILTER_VALIDATE_INT)!==false) return (int)$value;
        return null;
    }

    private static function providerId(mixed $value): ?string
    {
        if ((!is_string($value) && !is_int($value)) || is_bool($value)) return null;
        $value = trim((string)$value);
        return preg_match('/\A[1-9][0-9]{0,19}\z/D',$value) ? $value : null;
    }

    private static function text(mixed $value, int $max=1024): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value==='' || strlen($value)>$max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)) return null;
        return $value;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value)===count($expected)
            && array_diff($expected,array_keys($value))===[]
            && array_diff(array_keys($value),$expected)===[];
    }

    private static function safeOperatorUrl(mixed $value): ?array
    {
        if (!is_string($value) || strlen($value)>4096 || preg_match('/[\x00-\x20\x7f]/',$value)) return null;
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string)($parts['scheme']??''))!=='https'
            || isset($parts['user']) || isset($parts['pass'])) return null;
        $host = strtolower((string)($parts['host']??''));
        if ($host==='' || !preg_match('/\A(?:[a-z0-9-]+\.)*(?:anextour\.(?:ru|com)|anex\.(?:ru|com)|intourist\.ru|fstravel\.com|bgoperator\.ru)\z/D',$host)) {
            return null;
        }
        $whole = rawurldecode((string)($parts['path']??'') . '?' . (string)($parts['query']??''));
        if (preg_match('/(?:sid|token|password|secret|auth|session|email|phone|yclid|gclid|api.?key)/i',$whole)) return null;
        return ['host'=>$host,'sha256'=>hash('sha256',$value)];
    }

    private static function coordinates(array $row): bool
    {
        $lat = $row['latitude'] ?? $row['lat'] ?? null;
        $lon = $row['longitude'] ?? $row['lon'] ?? $row['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) return false;
        $lat=(float)$lat; $lon=(float)$lon;
        return is_finite($lat) && is_finite($lon) && $lat>=-90 && $lat<=90 && $lon>=-180 && $lon<=180;
    }

    private static function hasGeoRef(array $row): bool
    {
        foreach (['townKey','town_key','townId','town_id','townToKey','townToId','regionKey','regionId','stateKey','state_id','stateId'] as $key) {
            if (self::providerId($row[$key]??null)!==null) return true;
        }
        foreach (['townName','regionName','stateName','countryName','parentName'] as $key) {
            if (self::text($row[$key]??null)!==null) return true;
        }
        return false;
    }

    private static function hasStarRef(array $row): bool
    {
        foreach (['star','starKey','starId','category'] as $key) {
            $value = $row[$key]??null;
            if ((is_string($value)||is_int($value)||is_float($value)) && trim((string)$value)!=='') return true;
        }
        return false;
    }

    private static function profile(array $row): array
    {
        $json = $row['profile_json']??null; $sha=$row['profile_sha256']??null;
        if (!is_string($json)||!is_string($sha)||!preg_match('/\A[a-f0-9]{64}\z/D',$sha)
            || !hash_equals($sha,hash('sha256',$json))) throw new RuntimeException('PROFILE_INTEGRITY');
        try { $profile=json_decode($json,true,512,JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('PROFILE_JSON'); }
        if (!is_array($profile)) throw new RuntimeException('PROFILE_JSON');
        return $profile;
    }

    private static function profileState(array $profile): array
    {
        $description=self::text($profile['description']??null)!==null;
        $primary=self::safeOperatorUrl($profile['primaryImage']??null)!==null
            || (is_string($profile['primaryImage']??null) && str_starts_with($profile['primaryImage'],'https://'));
        $images=is_array($profile['images']??null) && $profile['images']!==[];
        $info=is_array($profile['hotelInformation']??null)?$profile['hotelInformation']:[];
        $infra=is_array($info['infrastructure']??null)&&$info['infrastructure']!==[];
        $services=is_array($info['services']??null)&&$info['services']!==[];
        $traits=is_array($profile['traits']??null)&&$profile['traits']!==[];
        $identity=self::text($profile['name']??null)!==null
            && is_array($profile['country']??null) && self::text($profile['country']['name']??null)!==null;
        return [
            'identity_ready'=>$identity,
            'identity_only'=>$identity&&!$description&&!$primary&&!$images&&!$infra&&!$services&&!$traits,
            'missing_primary_image'=>!$primary,
            'missing_gallery'=>!$images,
            'missing_description'=>!$description,
            'missing_category'=>!is_numeric($profile['category']??null)||(float)$profile['category']<=0,
            'missing_coordinates'=>!is_array($profile['coordinates']??null)
                || !is_numeric($profile['coordinates']['latitude']??null)
                || !is_numeric($profile['coordinates']['longitude']??null),
        ];
    }

    private static function alias(array $row): array
    {
        $local=self::positiveInt($row['local_hotel_id']??null);
        $own=self::positiveInt($row['anytour_hotel_id']??null);
        if ($local===null||$own===null||($row['acquired_via']??null)!==self::LOCAL_ALIAS_ACQUIRED_VIA
            || !is_string($row['source_json']??null)||!is_string($row['source_sha256']??null)
            || !preg_match('/\A[a-f0-9]{64}\z/D',$row['source_sha256'])
            || !hash_equals($row['source_sha256'],hash('sha256',$row['source_json']))) {
            throw new RuntimeException('ALIAS_INTEGRITY');
        }
        try { $source=json_decode($row['source_json'],true,32,JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('ALIAS_JSON'); }
        if (!is_array($source)||!self::exactKeys($source,self::ALIAS_KEYS)
            || ($source['schema_version']??null)!==1
            || ($source['accepted_local_hotel_id']??null)!==$local
            || ($source['canonical_hotel_id']??null)!==$own
            || ($source['derived_from_namespace']??null)!=='legacy_catalog'
            || !is_string($source['derived_from_source_sha256']??null)
            || !preg_match('/\A[a-f0-9]{64}\z/D',$source['derived_from_source_sha256'])) {
            throw new RuntimeException('ALIAS_SEMANTICS');
        }
        return ['local'=>$local,'own'=>$own];
    }

    private static function safeRead(string $path, int $limit=self::MAX_FILE_BYTES): array
    {
        $real=realpath($path);
        if ($real===false||$real!==$path||is_link($path)||!is_file($path)) throw new RuntimeException('UNSAFE_FILE');
        $fh=@fopen($path,'rb'); if(!$fh) throw new RuntimeException('UNREADABLE_FILE');
        try {
            $before=fstat($fh);
            if (!is_array($before)||($before['nlink']??0)!==1||($before['size']??0)<1||$before['size']>$limit) throw new RuntimeException('FILE_BOUNDS');
            $raw=stream_get_contents($fh,$limit+1); $after=fstat($fh);
            if (!is_string($raw)||strlen($raw)!==$before['size']||!is_array($after)
                || $after['size']!==$before['size']||$after['mtime']!==$before['mtime']||$after['ino']!==$before['ino']) {
                throw new RuntimeException('FILE_CHANGED');
            }
        } finally { fclose($fh); }
        try { $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('FILE_JSON'); }
        if (!is_array($decoded)) throw new RuntimeException('FILE_JSON');
        return [$decoded,hash('sha256',$raw),strlen($raw)];
    }

    private function configuredEvidence(): array
    {
        $candidates=[
            'local_candidate'=>$this->siteRoot.'/_preview/search3-local-candidate/.andromeda-private.php',
            'integration_saved_fallback'=>$this->siteRoot.'/_preview/search3-anex-candidate/.andromeda-private.php',
        ];
        foreach ($candidates as $label=>$path) {
            $real=realpath($path);
            if ($real===false||$real!==$path||is_link($path)||!is_file($path)) continue;
            $config=require $path;
            if (!is_array($config)||($config['enabled']??false)!==true) { unset($config); continue; }
            $catalogPaths=[];
            foreach ([$config['catalog_path']??null] as $value) if (is_string($value)&&$value!=='') $catalogPaths[$value]=true;
            if (is_array($config['catalog_country_paths']??null)) {
                foreach ($config['catalog_country_paths'] as $value) if (is_string($value)&&$value!=='') $catalogPaths[$value]=true;
            }
            $searchDir=is_string($config['search_state_dir']??null)?$config['search_state_dir']:null;
            unset($config);
            $safeCatalog=[];
            foreach (array_keys($catalogPaths) as $value) {
                $realCatalog=realpath($value);
                if ($realCatalog!==false&&$realCatalog===$value&&!is_link($value)&&is_file($value)) $safeCatalog[]=$value;
            }
            sort($safeCatalog,SORT_STRING);
            $safeSearchDir=null;
            if ($searchDir!==null) {
                $realDir=realpath($searchDir);
                if ($realDir!==false&&$realDir===$searchDir&&!is_link($searchDir)&&is_dir($searchDir)) $safeSearchDir=$searchDir;
            }
            if ($safeCatalog!==[]||$safeSearchDir!==null) {
                return ['label'=>$label,'catalog_paths'=>$safeCatalog,'search_state_dir'=>$safeSearchDir];
            }
        }
        return ['label'=>'none','catalog_paths'=>[],'search_state_dir'=>null];
    }

    private static function catalogHotelRows(array $saved): array
    {
        foreach ([
            $saved['all']['payload']['HOTELS']??null,
            $saved['data']['HOTELS']??null,
            $saved['payload']['HOTELS']??null,
            $saved['HOTELS']??null,
        ] as $rows) if (is_array($rows)&&array_is_list($rows)) return $rows;
        return [];
    }

    private static function addHost(array &$hosts, ?array $url): bool
    {
        if ($url===null) return false;
        $host=$url['host']; $hosts[$host]=($hosts[$host]??0)+1; return true;
    }

    private static function evidenceRow(): array
    {
        return [
            'catalog_seen'=>false,'catalog_name'=>false,'catalog_geo'=>false,'catalog_coordinates'=>false,
            'catalog_star_ref'=>false,'catalog_media_candidate'=>false,'catalog_detail_candidate'=>false,
            'search_seen'=>false,'search_name'=>false,'search_image_candidate'=>false,'search_detail_candidate'=>false,
        ];
    }

    private function savedEvidence(array $configured, array $acceptedIds): array
    {
        $evidence=[]; foreach ($acceptedIds as $id) $evidence[$id]=self::evidenceRow();
        $hosts=['catalog_media'=>[],'catalog_detail'=>[],'search_image'=>[],'search_detail'=>[]];
        $catalogFiles=0; $catalogRows=0; $catalogDigests=[];
        foreach ($configured['catalog_paths'] as $path) {
            [$saved,$digest]=self::safeRead($path); ++$catalogFiles; $catalogDigests[]=$digest;
            foreach (self::catalogHotelRows($saved) as $row) {
                if (!is_array($row)) continue;
                $id=self::providerId($row['id']??$row['hotelKey']??null);
                if ($id===null||!isset($evidence[$id])) continue;
                ++$catalogRows; $target=&$evidence[$id]; $target['catalog_seen']=true;
                $target['catalog_name']=$target['catalog_name']||self::text($row['name']??$row['lName']??null)!==null;
                $target['catalog_geo']=$target['catalog_geo']||self::hasGeoRef($row);
                $target['catalog_coordinates']=$target['catalog_coordinates']||self::coordinates($row);
                $target['catalog_star_ref']=$target['catalog_star_ref']||self::hasStarRef($row);
                foreach (['mediaUrl','image_url','hotelImage'] as $field) {
                    $url=self::safeOperatorUrl($row[$field]??null);
                    if (self::addHost($hosts['catalog_media'],$url)) $target['catalog_media_candidate']=true;
                }
                foreach (['url','www','hotelUrl','hotel_url'] as $field) {
                    $url=self::safeOperatorUrl($row[$field]??null);
                    if (self::addHost($hosts['catalog_detail'],$url)) $target['catalog_detail_candidate']=true;
                }
                unset($target);
            }
        }

        $searchFiles=0; $searchComplete=0; $searchRows=0; $searchDigests=[]; $skipped=[];
        $dir=$configured['search_state_dir'];
        if (is_string($dir)) {
            $names=scandir($dir);
            if (!is_array($names)) throw new RuntimeException('SEARCH_DIR_READ');
            $names=array_values(array_filter($names,static fn(string $name): bool =>
                preg_match('/\A[a-f0-9]{64}-(?:1|[1-9][0-9]{8,10}-[1-9][0-9]{0,3})\.json\z/D',$name)===1));
            sort($names,SORT_STRING);
            if (count($names)>self::MAX_SEARCH_FILES) throw new RuntimeException('SEARCH_FILE_BOUND');
            foreach ($names as $name) {
                ++$searchFiles; $path=$dir.'/'.$name;
                try { [$state,$digest]=self::safeRead($path); }
                catch (Throwable $error) { $skipped[$error->getMessage()]=($skipped[$error->getMessage()]??0)+1; continue; }
                if (!in_array($state['status']??null,['complete','partial'],true)) continue;
                ++$searchComplete; $searchDigests[]=$digest;
                $offers=$state['store']['snapshot']['offers']??null;
                if (!is_array($offers)||!array_is_list($offers)) continue;
                foreach ($offers as $row) {
                    if (!is_array($row)||($row['supplier_namespace']??null)!==self::PROVIDER_NAMESPACE) continue;
                    $id=self::providerId($row['external_hotel_id']??null);
                    if ($id===null||!isset($evidence[$id])) continue;
                    ++$searchRows; $target=&$evidence[$id]; $target['search_seen']=true;
                    $content=is_array($row['hotel_content']??null)?$row['hotel_content']:[];
                    $target['search_name']=$target['search_name']
                        || self::text($row['hotelName']??$row['hotel_name']??$row['name']??null)!==null
                        || self::text($content['hotelName']??$content['hotel_name']??$content['name']??null)!==null;
                    foreach ([$row['hotelImage']??null,$content['hotelImage']??null,$row['image_url']??null,$content['image_url']??null] as $candidate) {
                        $url=self::safeOperatorUrl($candidate);
                        if (self::addHost($hosts['search_image'],$url)) $target['search_image_candidate']=true;
                    }
                    foreach ([$row['hotelUrl']??null,$content['hotelUrl']??null,$row['hotel_url']??null,$content['hotel_url']??null] as $candidate) {
                        $url=self::safeOperatorUrl($candidate);
                        if (self::addHost($hosts['search_detail'],$url)) $target['search_detail_candidate']=true;
                    }
                    unset($target);
                }
            }
        }
        foreach ($hosts as &$values) ksort($values,SORT_STRING); unset($values);
        ksort($skipped,SORT_STRING);
        sort($catalogDigests,SORT_STRING); sort($searchDigests,SORT_STRING);
        return [
            'by_id'=>$evidence,
            'corpus'=>[
                'catalog_files'=>$catalogFiles,'catalog_rows_for_current_accepted'=>$catalogRows,
                'catalog_corpus_sha256'=>hash('sha256',implode("\n",$catalogDigests)),
                'search_files_listed'=>$searchFiles,'search_files_complete_or_partial'=>$searchComplete,
                'search_rows_for_current_accepted'=>$searchRows,
                'search_corpus_sha256'=>hash('sha256',implode("\n",$searchDigests)),
                'search_files_skipped'=>$skipped,
            ],
            'url_hosts'=>$hosts,
        ];
    }

    public function run(string $operation, string $baseReleaseSha): array
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/D',$operation)) throw new InvalidArgumentException('OPERATION');
        if (!preg_match('/\A[a-f0-9]{40}\z/D',$baseReleaseSha)) throw new InvalidArgumentException('BASE_SHA');
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$this->pdo->inTransaction()) throw new RuntimeException('MYSQL');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
        try {
            $sql="SELECT i.external_hotel_id,i.local_hotel_id,
                         s.anytour_hotel_id,s.acquired_via,s.source_json,s.source_sha256,
                         h.profile_json,h.profile_sha256,
                         COALESCE(d.user_searches,0) AS user_searches,COALESCE(d.observations,0) AS observations
                  FROM andromeda_hotel_identities i
                  JOIN anytour_hotel_sources s ON s.namespace='anytour_local_id'
                    AND CAST(s.external_key AS CHAR)=CAST(i.local_hotel_id AS CHAR)
                  JOIN anytour_hotels h ON h.id=s.anytour_hotel_id AND h.is_active=1
                  LEFT JOIN (
                    SELECT hotel_id,SUM(source='user_search') AS user_searches,COUNT(*) AS observations
                    FROM tour_price_observations GROUP BY hotel_id
                  ) d ON d.hotel_id=i.local_hotel_id
                  WHERE i.supplier_namespace='andromeda_catalog'
                    AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL
                  ORDER BY CAST(i.external_hotel_id AS UNSIGNED),i.external_hotel_id";
            $rows=$this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows)>self::MAX_ACCEPTED) throw new RuntimeException('ACCEPTED_BOUND');
            $accepted=[]; $canonicalByExternal=[]; $duplicateExternal=0; $duplicateCanonical=[];
            foreach ($rows as $row) {
                $external=self::providerId($row['external_hotel_id']??null);
                if ($external===null) throw new RuntimeException('EXTERNAL_ID');
                $alias=self::alias($row); $profile=self::profile($row); $state=self::profileState($profile);
                if (isset($accepted[$external])) { ++$duplicateExternal; continue; }
                $accepted[$external]=[
                    'external'=>$external,'local'=>$alias['local'],'own'=>$alias['own'],'state'=>$state,
                    'user_searches'=>(int)$row['user_searches'],'observations'=>(int)$row['observations'],
                ];
                $duplicateCanonical[$alias['own']]=($duplicateCanonical[$alias['own']]??0)+1;
                $canonicalByExternal[]=$external;
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $configured=$this->configuredEvidence();
        $saved=$this->savedEvidence($configured,$canonicalByExternal);
        $coverage=[
            'accepted_current'=>count($accepted),'duplicate_external_rows_ignored'=>$duplicateExternal,
            'canonical_hotels'=>count(array_unique(array_column($accepted,'own'))),
            'identity_only'=>0,'missing_primary_image'=>0,'missing_gallery'=>0,'missing_description'=>0,
            'missing_category'=>0,'missing_coordinates'=>0,
            'catalog_seen'=>0,'search_seen'=>0,'catalog_media_candidate'=>0,'search_image_candidate'=>0,
            'any_media_candidate'=>0,'detail_link_candidate'=>0,
            'identity_only_with_any_media_candidate'=>0,'identity_only_with_search_image_candidate'=>0,
            'missing_primary_with_any_media_candidate'=>0,'missing_gallery_with_any_media_candidate'=>0,
            'identity_only_demanded_with_any_media_candidate'=>0,'identity_only_with_detail_link_only'=>0,
            'missing_category_with_catalog_star_ref'=>0,'missing_coordinates_with_catalog_coordinates'=>0,
        ];
        $canonicalMedia=[];
        foreach ($accepted as $external=>$item) {
            foreach (['identity_only','missing_primary_image','missing_gallery','missing_description','missing_category','missing_coordinates'] as $key) {
                if ($item['state'][$key]) ++$coverage[$key];
            }
            $ev=$saved['by_id'][$external]??self::evidenceRow();
            if ($ev['catalog_seen']) ++$coverage['catalog_seen'];
            if ($ev['search_seen']) ++$coverage['search_seen'];
            if ($ev['catalog_media_candidate']) ++$coverage['catalog_media_candidate'];
            if ($ev['search_image_candidate']) ++$coverage['search_image_candidate'];
            $media=$ev['catalog_media_candidate']||$ev['search_image_candidate'];
            $detail=$ev['catalog_detail_candidate']||$ev['search_detail_candidate'];
            if ($media) { ++$coverage['any_media_candidate']; $canonicalMedia[$item['own']]=true; }
            if ($detail) ++$coverage['detail_link_candidate'];
            if ($item['state']['identity_only']&&$media) ++$coverage['identity_only_with_any_media_candidate'];
            if ($item['state']['identity_only']&&$ev['search_image_candidate']) ++$coverage['identity_only_with_search_image_candidate'];
            if ($item['state']['missing_primary_image']&&$media) ++$coverage['missing_primary_with_any_media_candidate'];
            if ($item['state']['missing_gallery']&&$media) ++$coverage['missing_gallery_with_any_media_candidate'];
            if ($item['state']['identity_only']&&$media&&($item['user_searches']>0||$item['observations']>0)) {
                ++$coverage['identity_only_demanded_with_any_media_candidate'];
            }
            if ($item['state']['identity_only']&&!$media&&$detail) ++$coverage['identity_only_with_detail_link_only'];
            if ($item['state']['missing_category']&&$ev['catalog_star_ref']) ++$coverage['missing_category_with_catalog_star_ref'];
            if ($item['state']['missing_coordinates']&&$ev['catalog_coordinates']) ++$coverage['missing_coordinates_with_catalog_coordinates'];
        }
        ksort($coverage,SORT_STRING);
        $multiCanonical=0; foreach ($duplicateCanonical as $count) if ($count>1) ++$multiCanonical;
        return [
            'schema_version'=>1,'operation'=>$operation,'status'=>'completed_read_only','base_release_sha'=>$baseReleaseSha,
            'captured_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),
            'saved_evidence_source'=>$configured['label'],
            'configured_catalog_files'=>count($configured['catalog_paths']),
            'configured_search_state_dir'=>$configured['search_state_dir']===null?false:true,
            'coverage'=>$coverage,
            'canonical_hotels_with_any_media_candidate'=>count($canonicalMedia),
            'canonical_hotels_with_multiple_accepted_andromeda_refs'=>$multiCanonical,
            'corpus'=>$saved['corpus'],'url_hosts'=>$saved['url_hosts'],
            'interpretation'=>[
                'media_candidates_are_not_auto_accept'=>true,
                'detail_links_are_not_images'=>true,
                'supplier_runtime_dependency_created'=>false,
                'room_meal_fields_excluded'=>true,
                'traits_excluded'=>true,
            ],
            'database_writes'=>0,'mapping_writes'=>0,'profile_writes'=>0,'supplier_calls'=>0,'network_calls'=>0,
        ];
    }
}

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        $args=[];
        foreach (array_slice($argv,1) as $arg) {
            if (!preg_match('/\A--(operation|base-release-sha|site-root)=(.+)\z/D',$arg,$m)||isset($args[$m[1]])) throw new InvalidArgumentException('USAGE');
            $args[$m[1]]=$m[2];
        }
        if (!isset($args['operation'],$args['base-release-sha'],$args['site-root'])||count($args)!==3) throw new InvalidArgumentException('USAGE');
        $root=realpath($args['site-root']);
        if ($root===false||$root!==rtrim($args['site-root'],'/')) throw new RuntimeException('SITE_ROOT');
        $helper=$root.'/data/db-v1.php'; if(!is_file($helper)) $helper=$root.'/v2/data/db-v1.php';
        if (!is_file($helper)) throw new RuntimeException('DB_HELPER');
        require_once $helper;
        if (!function_exists('v2_data_db')) throw new RuntimeException('DB_CONTRACT');
        $pdo=v2_data_db(); if(!$pdo instanceof PDO) throw new RuntimeException('DB');
        $inventory=new AnyTourAndromedaPresentationInventory($pdo,$root);
        echo json_encode($inventory->run($args['operation'],$args['base-release-sha']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable $error) {
        fwrite(STDERR,'ANYTOUR_ANDROMEDA_PRESENTATION_INVENTORY_FAILED code=' . preg_replace('/[^A-Z0-9_]/','_',strtoupper($error->getMessage())) . "\n");
        exit(1);
    }
}

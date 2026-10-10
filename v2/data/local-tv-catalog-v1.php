<?php
/** Permanent observed-TV catalogue. No automatic schema installation or HTTP. */
declare(strict_types=1);
require_once __DIR__ . '/hotel-details-v1.php';

final class LocalTvCatalogV1
{
    public const SOURCE = 'anytour-local-tv-catalog-v1';
    public const READ_LIMIT = 100;
    private bool $mysql;

    public function __construct(private PDO $pdo)
    {
        $this->mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    public static function enabled(): bool
    {
        return getenv('ANYTOUR_LOCAL_TV_CATALOG_ENABLED') === '1'
            || (defined('ANYTOUR_LOCAL_TV_CATALOG_ENABLED') && constant('ANYTOUR_LOCAL_TV_CATALOG_ENABLED') === true)
            || (is_file(__DIR__.'/local-tv-catalog-enabled.json') && !is_link(__DIR__.'/local-tv-catalog-enabled.json')
                && file_get_contents(__DIR__.'/local-tv-catalog-enabled.json') === "{\"registry\":true,\"dailyHttpBudget\":0}\n");
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function ids(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || !$values || count($values) > self::READ_LIMIT) {
            throw new InvalidArgumentException('Expected 1..100 explicit hotel IDs');
        }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
                || filter_var($value, FILTER_VALIDATE_INT) === false) throw new InvalidArgumentException('Invalid hotel ID');
            $ids[(int)$value] = (int)$value;
        }
        return array_values($ids);
    }

    private static function tvId(mixed $value): int
    {
        $ids = self::ids([$value]);
        if ($ids[0] > 4294967295) throw new InvalidArgumentException('Invalid Tourvisor ID');
        return $ids[0];
    }

    private static function time(string $value): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d H:i:s') !== $value) throw new InvalidArgumentException('UTC timestamp required');
        return $value;
    }

    public static function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $text = (string)$value;
        $text = preg_replace('#<(?:script|style)\b[^>]*>.*?</(?:script|style)>#is', '', $text) ?? $text;
        $text = preg_replace('#</?(?:p|br|div|li|h[1-6])\b[^>]*>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $text = trim(preg_replace('/[\t\r ]+/u', ' ', $text) ?? $text);
        return $text === '' ? null : $text;
    }

    private static function present(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) return false;
        if (is_array($value)) {
            foreach ($value as $part) if (self::present($part)) return true;
            return false;
        }
        return true; // Zero and false are real source values.
    }

    /** Sparse responses cannot remove good leaves. Lists remain atomic and ordered. */
    private static function merge(array $old, array $fresh): array
    {
        foreach ($fresh as $key => $value) {
            if (!self::present($value)) continue;
            $old[$key] = is_array($value) && !array_is_list($value)
                ? self::merge(is_array($old[$key] ?? null) ? $old[$key] : [], $value) : $value;
        }
        return $old;
    }

    private static function pathValue(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) return null;
            $value = $value[$key];
        }
        return $value;
    }

    private static function setPath(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path); $leaf = array_pop($keys); $cursor = &$data;
        foreach ($keys as $key) {
            if (!is_array($cursor[$key] ?? null)) $cursor[$key] = [];
            $cursor = &$cursor[$key];
        }
        $cursor[$leaf] = $value;
    }

    private static function leaves(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix . $key;
            if (is_array($value) && $value !== [] && !array_is_list($value)) $out += self::leaves($value, $path . '.');
            else $out[$path] = $value;
        }
        return $out;
    }

    /** Full decoded response is retained separately, including unknown fields and photo captions. */
    public static function normalize(array $payload, int $expectedId): array
    {
        $hotel = v2_hotel_detail_object($payload);
        if ($hotel === null || self::tvId($hotel['id'] ?? null) !== $expectedId
            || self::text($hotel['name'] ?? null) === null) throw new DomainException('source_identity_or_empty_response');
        if (v2_hotel_detail_is_generic_product_name($hotel['name'])) throw new DomainException('generic_accommodation_product');
        $n = v2_hotel_detail_normalized($hotel);
        $gallery = []; $seen = [];
        foreach ((array)($hotel['images'] ?? []) as $image) {
            $url = v2_hotel_detail_https_url(is_array($image) ? ($image['url'] ?? $image['src'] ?? null) : $image);
            if ($url === null || v2_hotel_detail_is_regional_placeholder_url($url) || isset($seen[$url])) continue;
            $seen[$url] = true;
            $gallery[] = ['url' => $url, 'caption' => self::text(is_array($image) ? ($image['caption'] ?? $image['description'] ?? null) : null)];
        }
        $primary = v2_hotel_detail_https_url($hotel['primaryImage'] ?? $hotel['picturelink'] ?? null);
        if ($primary !== null && !v2_hotel_detail_is_regional_placeholder_url($primary) && !isset($seen[$primary])) {
            array_unshift($gallery, ['url'=>$primary,'caption'=>null]);
        }
        $images = array_column($gallery, 'url');
        $info = [];
        foreach (['infrastructure','services','meals'] as $field) $info[$field] = $hotel[$field] ?? [];
        $info['roomTypes'] = $hotel['roomTypes'] ?? null;
        $content = [
            'name'=>self::text($hotel['name']), 'country'=>$hotel['country'] ?? null,
            'region'=>$hotel['region'] ?? null,'subRegion'=>$hotel['subRegion'] ?? null,
            'category'=>$n['category'],'rating'=>$n['rating'],
            'description'=>self::text($hotel['common']['description'] ?? null),
            'primaryImage'=>$primary !== null && !v2_hotel_detail_is_regional_placeholder_url($primary) ? $primary : ($images[0] ?? null),
            'images'=>$images,'photoDetails'=>$gallery,'hotelInformation'=>$info,
            'coordinates'=>$n['latitude'] !== null && $n['longitude'] !== null
                ? ['latitude'=>$n['latitude'],'longitude'=>$n['longitude']] : null,
        ];
        foreach (['address','place','phone','site','build','repair','square'] as $field) {
            $content[$field] = self::text($hotel['common'][$field] ?? null);
        }
        // Preserve all meaningful blocks; only these safe content groups are projected publicly.
        $content['descriptionSections'] = [];
        $labels = ['description'=>'Об отеле','place'=>'Расположение','beach'=>'Пляж','territory'=>'Территория',
            'inRoom'=>'В номере','child'=>'Для детей','animation'=>'Развлечения','free'=>'Бесплатные услуги',
            'servicesPay'=>'Платные услуги','available'=>'Услуги в отеле','pools'=>'Бассейны',
            'pool'=>'Бассейны','sport'=>'Спорт','internet'=>'Интернет','placement'=>'Размещение',
            'food'=>'Питание','roomTypes'=>'Номера отеля'];
        foreach (['common'=>'Сведения об отеле','infrastructure'=>'Инфраструктура','services'=>'Услуги',
            'meals'=>'Питание','descriptions'=>'Описание'] as $group => $groupLabel) {
            if (!is_array($hotel[$group] ?? null)) continue;
            foreach ($hotel[$group] as $key => $value) {
                if (in_array($key, ['id','latitude','longitude','phone','site','address','build','repair','square'], true)) continue;
                if (($group==='common' && in_array($key,['description','place'],true))
                    || ($group==='infrastructure' && in_array($key,['beach','territory'],true))
                    || ($group==='services' && in_array($key,['available','inRoom','child','animation','free','servicesPay'],true))) continue;
                if (!is_string($value) || self::text($value) === null) continue;
                $field=in_array($group,['infrastructure','services','meals'],true)?'hotelInformation.'.$group.'.'.$key
                    :($group==='common'?$key:null);
                $content['descriptionSections'][] = ['label'=>$labels[$key] ?? $groupLabel,'text'=>self::text($value),'field'=>$field];
            }
        }
        $absent = [];
        foreach (['description','primaryImage','images','coordinates','address','place',
            'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'] as $field) {
            if (!self::present(self::pathValue($content, $field))) $absent[] = $field;
        }
        return ['content'=>$content,'absent'=>$absent];
    }

    /** Called only with server-trusted real Tourvisor rows; no tours or popularity conditions. */
    public function discover(array $hotels, string $source, string $now): int
    {
        self::time($now);
        if (!in_array($source, ['user_search','scheduled_monitor','hot_tours'], true)) throw new InvalidArgumentException('Real search origin required');
        $count = 0;
        foreach ($hotels as $hotel) {
            if (!is_array($hotel)) continue;
            try { $id = self::tvId($hotel['id'] ?? null); } catch (InvalidArgumentException) { continue; }
            if (v2_hotel_detail_is_generic_product_name($hotel['name'] ?? null)) continue;
            $discovery = array_intersect_key($hotel, array_flip(['name','country','region','subRegion','category']));
            $count += $this->register($id, $now, $now, $discovery);
        }
        return $count;
    }

    private function register(int $id, string $first, string $last, array $discovery): int
    {
        $sql = 'INSERT ' . ($this->mysql ? 'IGNORE ' : 'OR IGNORE ') . 'INTO local_tv_hotels
            (id,first_seen_at,last_seen_at,pending_since,discovery_json,content_json,manual_json,source_absent_json)
            VALUES (?,?,?,?,?,?,?,?)';
        $q = $this->pdo->prepare($sql);
        $q->execute([$id,$first,$last,$first,self::json($discovery),'{}','{}','[]']);
        $created = $q->rowCount();
        // Do not reset pending_since, retry state or good content on a repeat observation.
        $q = $this->pdo->prepare('UPDATE local_tv_hotels SET last_seen_at=CASE WHEN last_seen_at<? THEN ? ELSE last_seen_at END,
            first_seen_at=CASE WHEN first_seen_at>? THEN ? ELSE first_seen_at END,
            pending_since=CASE WHEN pending_since>? THEN ? ELSE pending_since END WHERE id=?');
        $q->execute([$last,$last,$first,$first,$first,$first,$id]);
        return $created;
    }

    /** Surviving genuine observations only, never the entire supplier dictionary. */
    public function backfillObserved(): int
    {
        $sources = ["SELECT hotel_id,observed_at AS seen FROM tour_price_observations
            WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours')",
            'SELECT hotel_id,fetched_at AS seen FROM hot_tours_current WHERE hotel_id>0'];
        // These optional stores contain already-observed offers/evidence, not dictionaries.
        $optional = [
            'tour_operator_identity_observations'=>"SELECT hotel_id,first_seen_at AS seen FROM tour_operator_identity_observations
                WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours')
                UNION ALL SELECT hotel_id,last_seen_at AS seen FROM tour_operator_identity_observations
                WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours')",
            'anytour_offers'=>"SELECT legacy_hotel_id AS hotel_id,observed_at AS seen FROM anytour_offers
                WHERE provider='tourvisor' AND legacy_hotel_id>0
                UNION ALL SELECT legacy_hotel_id AS hotel_id,last_seen_at AS seen FROM anytour_offers
                WHERE provider='tourvisor' AND legacy_hotel_id>0",
            'anytour_offer_price_observations'=>"SELECT provider_local_hotel_id AS hotel_id,observed_at AS seen
                FROM anytour_offer_price_observations WHERE provider='tourvisor' AND provider_local_hotel_id>0",
        ];
        foreach ($optional as $table=>$sql) {
            $exists=$this->pdo->prepare($this->mysql ? 'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
                : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
            $exists->execute([$table]); if ($exists->fetchColumn()) $sources[]=$sql;
        }
        $q = $this->pdo->query('SELECT hotel_id,MIN(seen) AS first_seen,MAX(seen) AS last_seen FROM ('
            . implode(' UNION ALL ',$sources) . ') observations GROUP BY hotel_id ORDER BY MIN(seen),hotel_id');
        $count = 0;
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            try { $id = self::tvId($r['hotel_id']); } catch (InvalidArgumentException) { continue; }
            $count += $this->register($id,self::time($r['first_seen']),self::time($r['last_seen']),[]);
        }
        return $count;
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('Caller transaction cannot be nested');
        $this->pdo->beginTransaction();
    }

    private function locked(int $id): array
    {
        $q = $this->pdo->prepare('SELECT * FROM local_tv_hotels WHERE id=?' . ($this->mysql ? ' FOR UPDATE' : ''));
        $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new DomainException('hotel_not_registered');
        return $r;
    }

    /** The raw response and effective content commit together; readback is outside COMMIT. */
    public function saveSource(int $id, array $payload, string $fetchedAt): array
    {
        self::time($fetchedAt); $normalized = self::normalize($payload, self::tvId($id));
        $raw = self::json($payload); $hash = hash('sha256',$raw);
        $this->begin(); $committing=false;
        try {
            $r = $this->locked($id);
            if ($r['source_fetched_at'] !== null && $r['source_fetched_at'] > $fetchedAt) {
                $this->pdo->commit(); return ['changed'=>false,'reason'=>'older_source_preserved'];
            }
            $content = self::merge(json_decode($r['content_json'],true,512,JSON_THROW_ON_ERROR),$normalized['content']);
            $json = self::json($content);
            $changed = $json !== $r['content_json'] || $hash !== $r['source_sha256'] || $r['state'] !== 'ready';
            $revision = (int)$r['revision'] + ($changed ? 1 : 0);
            $q = $this->pdo->prepare("UPDATE local_tv_hotels SET content_json=?,source_json=?,source_sha256=?,
                source_fetched_at=?,source_absent_json=?,revision=?,state='ready',next_attempt_at=NULL,last_error=NULL WHERE id=?");
            $q->execute([$json,$raw,$hash,$fetchedAt,self::json($normalized['absent']),$revision,$id]);
            $committing=true; $this->pdo->commit();
        } catch (Throwable $e) {
            if ($committing) throw new RuntimeException('source_commit_unknown',0,$e);
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        try {
            $q = $this->pdo->prepare('SELECT content_json,source_sha256,revision FROM local_tv_hotels WHERE id=?');
            $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { throw new RuntimeException('post_commit_readback_unknown',0,$e); }
        if (!$r || $r['source_sha256'] !== $hash || $r['content_json'] !== $json || (int)$r['revision'] !== $revision) {
            throw new RuntimeException('post_commit_readback_unknown');
        }
        return ['changed'=>$changed,'sourceAbsent'=>$normalized['absent'],'revision'=>$revision];
    }

    public function fail(int $id, string $reason, string $now): void
    {
        $retry = (new DateTimeImmutable(self::time($now),new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s');
        $q = $this->pdo->prepare("UPDATE local_tv_hotels SET state='retry',next_attempt_at=?,last_error=? WHERE id=?");
        $q->execute([$retry,mb_substr(str_replace(["\n","\r"],' ',$reason),0,1000),$id]);
    }

    /** A retained generic product is not a hotel, but only pristine rows may be excluded automatically. */
    private function excludeGeneric(int $id): bool
    {
        $q = $this->pdo->prepare("UPDATE local_tv_hotels SET state='excluded',next_attempt_at=NULL,
            last_error='generic_accommodation_product' WHERE id=? AND state<>'ready' AND source_json IS NULL
            AND discovery_json IN ('{}','[]') AND content_json='{}' AND manual_json='{}'");
        $q->execute([$id]);
        return $q->rowCount() === 1;
    }

    /** Privileged callers supply the observed revision; an empty manual value is intentional. */
    public function setManualFields(int $id, array $fields, int $expectedRevision): void
    {
        $this->begin(); $committing=false;
        try {
            $r=$this->locked(self::tvId($id));
            if ((int)$r['revision']!==$expectedRevision) throw new DomainException('manual_revision_conflict');
            $manual=json_decode($r['manual_json'],true,512,JSON_THROW_ON_ERROR);
            foreach ($fields as $path=>$value) {
                if (!is_string($path) || !preg_match('/^(?:name|country|region|subRegion|category|rating|description|primaryImage|images|photoDetails|coordinates|address|place|build|repair|square|hotelInformation)(?:\.[A-Za-z0-9_]+)*$/D',$path)) {
                    throw new InvalidArgumentException('Invalid manual content field');
                }
                $manual[$path]=$value;
            }
            $q=$this->pdo->prepare('UPDATE local_tv_hotels SET manual_json=?,revision=revision+1 WHERE id=?');
            $q->execute([self::json($manual),$id]); $committing=true; $this->pdo->commit();
        } catch (Throwable $e) {
            if ($committing) throw new RuntimeException('manual_commit_unknown',0,$e);
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Only a proven legacy_catalog edge supplies old LOCAL -> TV, never numeric/name equality. */
    public function migrateLinks(string $now): array
    {
        self::time($now); $transferred = 0; $issues = [];
        $q = $this->pdo->query("SELECT DISTINCT a.id FROM anytour_hotels a JOIN anytour_hotel_sources s
            ON s.anytour_hotel_id=a.id AND s.namespace='legacy_catalog'
            JOIN local_tv_hotels l ON CAST(s.external_key AS UNSIGNED)=l.id ORDER BY a.id");
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $oldId) {
            $committing=false; $committed=false;
            try {
                $this->begin();
                $q = $this->pdo->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?'
                    . ($this->mysql ? ' FOR UPDATE' : ''));
                $q->execute([$oldId]); $old = $q->fetch(PDO::FETCH_ASSOC);
                $q = $this->pdo->prepare('SELECT namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at
                    FROM anytour_hotel_sources WHERE anytour_hotel_id=? ORDER BY namespace,external_key');
                $q->execute([$oldId]); $sources = $q->fetchAll(PDO::FETCH_ASSOC); $tvIds = [];
                foreach ($sources as $s) if ($s['namespace']==='legacy_catalog') $tvIds[] = self::tvId($s['external_key']);
                $tvIds = array_values(array_unique($tvIds));
                if (count($tvIds)!==1) throw new DomainException('ambiguous_old_local_to_tv');
                $tvId = $tvIds[0]; $target = $this->locked($tvId);
                if (!hash_equals($old['profile_sha256'],hash('sha256',$old['profile_json']))) throw new DomainException('legacy_profile_integrity');
                $snapshot = self::json(['profile'=>$old,'sources'=>$sources]);
                $q = $this->pdo->prepare('SELECT * FROM local_tv_legacy_links WHERE old_local_id=?'); $q->execute([$oldId]);
                $existing = $q->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    if ((int)$existing['tv_id']!==$tvId || $existing['snapshot_sha256']!==hash('sha256',$snapshot)) {
                        throw new DomainException('legacy_changed_after_migration_requires_review');
                    }
                    $this->pdo->commit(); continue;
                }
                $profile = json_decode($old['profile_json'],true,512,JSON_THROW_ON_ERROR);
                $imported = []; $originKnown = false;
                foreach ($sources as $s) {
                    if (!hash_equals($s['source_sha256'],hash('sha256',$s['source_json']))) throw new DomainException('legacy_source_integrity');
                    if ($s['namespace']==='legacy_catalog' && $s['acquired_via']==='saved_catalog') {
                        $seed = json_decode($s['source_json'],true,512,JSON_THROW_ON_ERROR);
                        if ((int)($seed['id']??0)!==$tvId) throw new DomainException('legacy_source_identity');
                        $imported = $seed; unset($imported['id'],$imported['detailsFetchedAt'],$imported['type']);
                        foreach (['country','region','subRegion'] as $field) if (is_array($imported[$field]??null)) unset($imported[$field]['id']);
                        $imported['traits']=[];
                        $imported['hotelInformation']=['meals'=>$seed['meals']??[],'roomTypes'=>$seed['roomTypes']??null,
                            'services'=>$seed['services']??[],'infrastructure'=>$seed['infrastructure']??[]];
                        unset($imported['meals'],$imported['roomTypes'],$imported['services'],$imported['infrastructure']);
                        $originKnown = true;
                    }
                }
                // A known enrichment receipt proves only its changed fields.
                // Untouched manual fields stay protected even when another field was imported.
                $cursor=$old['profile_sha256'];$revision=(int)$old['revision'];$visited=[];
                while($revision>1){
                    $receipts=[];
                    foreach($sources as $s){
                        if(!in_array($s['namespace'],['profile_sync:retained_tv_v1','profile_enrichment:legacy_saved_v1'],true))continue;
                        $receipt=json_decode($s['source_json'],true,512,JSON_THROW_ON_ERROR);
                        if(($receipt['canonical_hotel_id']??null)===(int)$oldId
                            &&($receipt['accepted_local_hotel_id']??null)===$tvId
                            &&($receipt['result_profile_sha256']??null)===$cursor
                            &&($receipt['result_revision']??null)===$revision)$receipts[]=$receipt;
                    }
                    if(count($receipts)!==1||isset($visited[$cursor]))break;
                    $visited[$cursor]=true;$receipt=$receipts[0];
                    foreach(($receipt['fields_updated']??$receipt['fields_filled']??[])as $field){
                        if(is_string($field))self::setPath($imported,$field,self::pathValue($profile,$field));
                    }
                    $cursor=$receipt['previous_profile_sha256']??'';$revision--;
                }
                $manual = json_decode($target['manual_json'],true,512,JSON_THROW_ON_ERROR); $held = [];
                foreach (self::leaves($profile) as $field=>$value) {
                    if (in_array(explode('.',$field)[0],['id','catalog','revision','detailsAvailable','detailsFetchedAt'],true)) continue;
                    if (!$originKnown || self::json($value)!==self::json(self::pathValue($imported,$field))) {
                        if (array_key_exists($field,$manual) && self::json($manual[$field])!==self::json($value)) {
                            throw new DomainException('conflicting_legacy_field:' . $field);
                        }
                        $manual[$field]=$value; $held[]=$field;
                    }
                }
                $content = self::merge($profile,json_decode($target['content_json'],true,512,JSON_THROW_ON_ERROR));
                $q=$this->pdo->prepare('INSERT INTO local_tv_legacy_links
                    (old_local_id,tv_id,snapshot_json,snapshot_sha256,migration_issues_json,migrated_at) VALUES (?,?,?,?,?,?)');
                $q->execute([$oldId,$tvId,$snapshot,hash('sha256',$snapshot),self::json(['preservedFields'=>$held,'originKnown'=>$originKnown]),$now]);
                $contentJson=self::json($content); $manualJson=self::json($manual);
                $q=$this->pdo->prepare('UPDATE local_tv_hotels SET content_json=?,manual_json=?,revision=revision+1 WHERE id=?');
                $q->execute([$contentJson,$manualJson,$tvId]);
                $committing=true; $this->pdo->commit(); $committing=false; $committed=true;
                $q=$this->pdo->prepare('SELECT l.content_json,l.manual_json,l.revision,b.snapshot_sha256
                    FROM local_tv_hotels l JOIN local_tv_legacy_links b ON b.tv_id=l.id WHERE b.old_local_id=?');
                $q->execute([$oldId]); $check=$q->fetch(PDO::FETCH_ASSOC);
                if (!$check || $check['content_json']!==$contentJson || $check['manual_json']!==$manualJson
                    || (int)$check['revision']!==(int)$target['revision']+1 || $check['snapshot_sha256']!==hash('sha256',$snapshot)) {
                    throw new RuntimeException('migration_post_commit_readback_unknown');
                }
                $transferred++;
            } catch (Throwable $e) {
                if ($committing) throw new RuntimeException('migration_commit_unknown',0,$e);
                if ($committed) throw new RuntimeException('migration_post_commit_readback_unknown',0,$e);
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $issues[(string)$oldId]=$e->getMessage();
            }
        }
        return ['transferred'=>$transferred,'issues'=>$issues];
    }

    /** Import only newer retained descriptions. No HTTP, old profile or bridge writer. */
    public function dailyRetained(int $limit): array
    {
        if ($limit<1 || $limit>3000) throw new InvalidArgumentException('Invalid retained collector bound');
        $q=$this->pdo->query("SELECT l.id,c.raw_json,c.source_hash,c.fetched_at FROM local_tv_hotels l
            JOIN catalog_hotel_details c ON c.hotel_id=l.id
            WHERE c.status='success' AND c.raw_json IS NOT NULL AND c.source_hash IS NOT NULL AND c.fetched_at IS NOT NULL
                AND l.state<>'excluded'
                AND (l.source_fetched_at IS NULL OR c.fetched_at>l.source_fetched_at)
            ORDER BY l.pending_since,l.id LIMIT $limit");
        $pending=$q->fetchAll(PDO::FETCH_ASSOC);
        $report=['selected'=>count($pending),'filled'=>0,'retainedSource'=>0,'httpRequests'=>0,'skipped'=>[],'excluded'=>[]];
        foreach ($pending as $r) {
            $id=(int)$r['id'];
            if (!hash_equals($r['source_hash'],hash('sha256',$r['raw_json']))) {
                $report['skipped'][(string)$id]='retained_hash'; continue;
            }
            try {
                $payload=json_decode($r['raw_json'],true,512,JSON_THROW_ON_ERROR);
                if (!is_array($payload)) throw new DomainException('invalid_retained_card');
                self::time($r['fetched_at']); self::normalize($payload,$id);
            } catch (Throwable $e) {
                $reason=$e instanceof DomainException?$e->getMessage():'invalid_retained_card';
                if ($reason==='generic_accommodation_product' && $this->excludeGeneric($id)) {
                    $report['excluded'][(string)$id]=$reason;
                } else {
                    $report['skipped'][(string)$id]=$reason;
                }
                continue;
            }
            // Unknown COMMIT or readback propagates; neither it nor an error UPDATE is retried.
            $this->saveSource($id,$payload,$r['fetched_at']);
            $report['filled']++; $report['retainedSource']++;
        }
        return $report+$this->counts();
    }

    /** FIFO unfinished work, then stale successful cards; popularity is not a queue input. */
    public function daily(callable $fetch, int $limit, int $httpBudget, string $now, int $freshDays=30): array
    {
        self::time($now);
        if ($limit<1 || $limit>3000 || $httpBudget<0 || $httpBudget>3000 || $freshDays<1 || $freshDays>365) {
            throw new InvalidArgumentException('Invalid collector bounds');
        }
        $cutoff=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->modify('-'.$freshDays.' days')->format('Y-m-d H:i:s');
        $q=$this->pdo->prepare("SELECT id,state,source_fetched_at FROM local_tv_hotels
            WHERE (next_attempt_at IS NULL OR next_attempt_at<=?) AND (state<>'ready' OR source_fetched_at<?)
            ORDER BY CASE WHEN state='ready' THEN 1 ELSE 0 END,pending_since,id LIMIT $limit");
        $q->execute([$now,$cutoff]); $pending=$q->fetchAll(PDO::FETCH_ASSOC);
        $report=['selected'=>count($pending),'filled'=>0,'retainedSource'=>0,'fetchedSource'=>0,'httpRequests'=>0,
            'errors'=>[],'sourceAbsent'=>[],'budgetDeferred'=>0];
        $read=$this->pdo->prepare('SELECT raw_json,source_hash,fetched_at FROM catalog_hotel_details WHERE hotel_id=?');
        foreach ($pending as $row) {
            $id=(int)$row['id'];
            try {
                $read->execute([$id]); $retained=$read->fetch(PDO::FETCH_ASSOC); $payload=null;
                if ($retained && is_string($retained['raw_json']) && is_string($retained['source_hash'])
                    && hash_equals($retained['source_hash'],hash('sha256',$retained['raw_json']))) {
                    try {
                        $candidate=json_decode($retained['raw_json'],true,512,JSON_THROW_ON_ERROR);
                        if (!is_array($candidate)) throw new DomainException('invalid_retained_card');
                        self::normalize($candidate,$id); self::time($retained['fetched_at']);
                        if ($row['source_fetched_at']===null || $retained['fetched_at']>$row['source_fetched_at']) $payload=$candidate;
                    } catch (Throwable) { $payload=null; }
                }
                $usingRetained=$payload!==null;
                if ($usingRetained) { $fetched=$retained['fetched_at']; }
                else {
                    if ($report['httpRequests']>=$httpBudget) { $report['budgetDeferred']++; continue; }
                    $report['httpRequests']++; $payload=$fetch($id); $fetched=$now;
                    if (!is_array($payload)) throw new DomainException('invalid_source_response');
                    self::normalize($payload,$id); $report['fetchedSource']++;
                }
                $result=$this->saveSource($id,$payload,$fetched);
                $report['filled']++;
                if ($usingRetained) $report['retainedSource']++;
                if (!empty($result['sourceAbsent'])) $report['sourceAbsent'][(string)$id]=$result['sourceAbsent'];
            } catch (Throwable $e) {
                if (in_array($e->getMessage(),['post_commit_readback_unknown','source_commit_unknown'],true)) throw $e;
                $report['errors'][(string)$id]=$e->getMessage();
                try { $this->fail($id,$e->getMessage(),$now); }
                catch (Throwable) { throw new RuntimeException('error_state_save_unknown',0,$e); }
            }
        }
        return $report + $this->counts();
    }

    public function counts(): array
    {
        $r=$this->pdo->query("SELECT COUNT(*) AS registered,
            SUM(CASE WHEN state<>'excluded' THEN 1 ELSE 0 END) AS discovered,
            SUM(CASE WHEN state='ready' THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN state NOT IN ('ready','excluded') THEN 1 ELSE 0 END) AS unfinished,
            SUM(CASE WHEN state='excluded' THEN 1 ELSE 0 END) AS excludedCount FROM local_tv_hotels")->fetch(PDO::FETCH_ASSOC);
        return array_map('intval',$r);
    }

    public function read(array $values, bool $oldLocal=false): array
    {
        $ids=self::ids($values); $marks=implode(',',array_fill(0,count($ids),'?'));
        $sql=$oldLocal ? "SELECT l.*,b.old_local_id FROM local_tv_hotels l JOIN local_tv_legacy_links b ON b.tv_id=l.id WHERE b.old_local_id IN ($marks) AND l.state<>'excluded'"
            : "SELECT l.* FROM local_tv_hotels l WHERE l.id IN ($marks) AND l.state<>'excluded'";
        $q=$this->pdo->prepare($sql); $q->execute($ids); $found=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $found[(int)($oldLocal?$r['old_local_id']:$r['id'])]=$r;
        $items=$missing=$links=[];
        foreach ($ids as $id) {
            if (!isset($found[$id])) { $missing[]=$id; continue; }
            $r=$found[$id]; $profile=self::merge(json_decode($r['discovery_json'],true,512,JSON_THROW_ON_ERROR),json_decode($r['content_json'],true,512,JSON_THROW_ON_ERROR));
            $manual=json_decode($r['manual_json'],true,512,JSON_THROW_ON_ERROR);
            foreach ($manual as $field=>$value) self::setPath($profile,$field,$value);
            // Supplemental text follows the effective field, including manual overrides.
            foreach ($profile['descriptionSections']??[] as $index=>$section) {
                if (is_string($section['field']??null)) $profile['descriptionSections'][$index]['text']=self::text(self::pathValue($profile,$section['field']))??'';
            }
            // Explicit allowlist: raw/source/MATCH/private snapshot bytes never leave this reader.
            $profile=array_intersect_key($profile,array_flip(['name','country','region','subRegion','category','rating',
                'description','primaryImage','images','photoDetails','hotelInformation','descriptionSections',
                'coordinates','address','place','build','repair','square']));
            $profile['images']=v2_hotel_detail_images(['images'=>$profile['images']??[]],null);
            $primary=v2_hotel_detail_https_url($profile['primaryImage']??null);
            $profile['primaryImage']=$primary!==null&&!v2_hotel_detail_is_regional_placeholder_url($primary)?$primary:($profile['images'][0]??null);
            $profile += ['id'=>(int)$r['id'],'catalog'=>'local-tv','revision'=>(int)$r['revision'],
                'detailsAvailable'=>$r['source_json']!==null,'detailsFetchedAt'=>$r['source_fetched_at'],
                'sourceAbsent'=>json_decode($r['source_absent_json'],true,512,JSON_THROW_ON_ERROR),
                'manualFields'=>array_values(array_filter(array_keys($manual),static fn($field)=>array_key_exists(explode('.',$field)[0],$profile))),
                'contentState'=>$r['state']];
            if ($oldLocal) $links[]=['oldLocalId'=>$id,'tourvisorHotelId'=>(int)$r['id']];
            $items[(int)$r['id']]=$profile;
        }
        return ['source'=>self::SOURCE,'catalog'=>'local-tv','items'=>array_values($items),
            'requestedIds'=>$ids,'missingIds'=>$missing,'links'=>$links];
    }
}

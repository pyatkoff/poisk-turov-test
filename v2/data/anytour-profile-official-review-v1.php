<?php
/** Reviewed official content policy for the existing LOCAL profile owner. No SQL writes or network. */
declare(strict_types=1);
require_once __DIR__ . '/anytour-canonical-catalog-v1.php';

trait AnyTourProfileOfficialReviewV1
{
    public const OFFICIAL_POLICY = 'official_review_missing_only_v1';
    public const OFFICIAL26_POLICY = 'official_review_missing_only_v2';
    public const OFFICIAL_NAMESPACE = 'profile_enrichment:official_review_v1';
    public const OFFICIAL_ACQUIRED_VIA = 'profile_fill_official_v1';
    private const OFFICIAL_FIELDS = ['description','images','primaryImage'];
    private const OFFICIAL_BATCH = 11;
    private const OFFICIAL26_BATCH = 26;

    private static function officialSha(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function officialUrl(mixed $value): bool
    {
        if (!is_string($value) || strlen($value) > 4096 || preg_match('/[\x00-\x20\x7f]/', $value)
            || filter_var($value, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($value);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']);
    }

    /** Pins and approval are supplied by the separately admitted, finite carrier. */
    private static function officialApproved(array $approved, bool $mixed26 = false): array
    {
        $batch = $mixed26 ? self::OFFICIAL26_BATCH : self::OFFICIAL_BATCH;
        $policy = $mixed26 ? self::OFFICIAL26_POLICY : self::OFFICIAL_POLICY;
        if (!self::exactKeys($approved, ['manifestJson','manifestSha256','actionsSha256','anytourHotelIds','approvalRef'])
            || !is_string($approved['manifestJson']) || strlen($approved['manifestJson']) > 131072
            || !self::officialSha($approved['manifestSha256']) || !self::officialSha($approved['actionsSha256'])
            || !hash_equals($approved['manifestSha256'], hash('sha256', $approved['manifestJson']))
            || !is_array($approved['anytourHotelIds']) || !array_is_list($approved['anytourHotelIds'])
            || count($approved['anytourHotelIds']) !== $batch
            || !is_string($approved['approvalRef'])
            || preg_match('~\Ahttps://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/issues/[1-9][0-9]*\#issuecomment-[1-9][0-9]*\z~D', $approved['approvalRef']) !== 1) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_ENVELOPE');
        }
        $ids = [];
        foreach ($approved['anytourHotelIds'] as $id) {
            if (!is_int($id) || $id < 1 || isset($ids[$id])) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_IDS');
            $ids[$id] = true;
        }
        ksort($ids, SORT_NUMERIC);
        try { $manifest = json_decode($approved['manifestJson'], true, 64, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_MANIFEST_JSON'); }
        if (!is_array($manifest) || !self::exactKeys($manifest, [
                'schema_version','source_policy','original_package_sha256','approval_ref',
                'proposed_actions','proposed_actions_sha256','targets',
            ]) || $manifest['schema_version'] !== ($mixed26 ? 2 : 1) || $manifest['source_policy'] !== $policy
            || !self::officialSha($manifest['original_package_sha256']) || $manifest['approval_ref'] !== $approved['approvalRef']
            || $manifest['proposed_actions_sha256'] !== $approved['actionsSha256']
            || !is_array($manifest['proposed_actions']) || !array_is_list($manifest['proposed_actions'])
            || count($manifest['proposed_actions']) !== $batch
            || !hash_equals($approved['actionsSha256'], hash('sha256', self::json($manifest['proposed_actions'])))
            || !is_array($manifest['targets']) || !array_is_list($manifest['targets'])
            || count($manifest['targets']) !== $batch) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_MANIFEST');
        }
        $actions = [];
        $fieldCounts = array_fill_keys(self::OFFICIAL_FIELDS, 0);
        foreach ($manifest['proposed_actions'] as $action) {
            // Hash the owner's original review actions above; normalize only inside this owner.
            $review26 = $mixed26 ? self::official26Action($action) : null;
            if ($review26 !== null) $action = $review26['action'];
            if (!is_array($action) || !self::exactKeys($action, [
                    'anytourHotelId','workId','observedRevision','mutation','fields','guards_required_before_apply','runtime_admission',
                ]) || !is_int($action['anytourHotelId']) || !isset($ids[$action['anytourHotelId']])
                || isset($actions[$action['anytourHotelId']]) || !is_string($action['workId'])
                || preg_match('/\AEXP[0-9]{3}\z/D', $action['workId']) !== 1 || $action['observedRevision'] !== 1
                || $action['mutation'] !== 'fill_only_if_stored_field_still_missing' || $action['runtime_admission'] !== null
                || !is_array($action['guards_required_before_apply']) || !array_is_list($action['guards_required_before_apply'])
                || $action['guards_required_before_apply'] === []
                || !is_array($action['fields']) || !array_is_list($action['fields'])
                || !in_array(count($action['fields']), $mixed26 ? [1,3] : [3], true)) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_ACTION');
            }
            foreach ($action['guards_required_before_apply'] as $guard) {
                if (!is_string($guard) || trim($guard) === '') throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_ACTION');
            }
            $values = [];
            foreach ($action['fields'] as $field) {
                if (!is_array($field) || !self::exactKeys($field, ['field','before_public_projection','proposed_value','decision'])
                    || !is_string($field['field']) || !in_array($field['field'], self::OFFICIAL_FIELDS, true)
                    || array_key_exists($field['field'], $values) || $field['decision'] !== 'proposed_not_applied'
                    || $field['before_public_projection'] !== ($field['field'] === 'images' ? [] : null)) {
                    throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_FIELD');
                }
                $values[$field['field']] = $field['proposed_value'];
            }
            if (!is_string($values['description']) || trim($values['description']) === '' || strlen($values['description']) > 12000
                || strip_tags($values['description']) !== $values['description']) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_VALUES');
            }
            if (count($values) === 3 && (!self::officialUrl($values['primaryImage'])
                || !is_array($values['images']) || !array_is_list($values['images'])
                || count($values['images']) < 1 || count($values['images']) > 10
                || $values['primaryImage'] !== $values['images'][0])) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_VALUES');
            }
            $seen = [];
            foreach ($values['images'] ?? [] as $url) {
                if (!self::officialUrl($url) || isset($seen[$url])) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_IMAGES');
                $seen[$url] = true;
            }
            $actions[$action['anytourHotelId']] = ['workId'=>$action['workId'],'values'=>$values];
            if ($review26 !== null) $actions[$action['anytourHotelId']] += $review26['guards'];
            foreach (array_keys($values) as $field) ++$fieldCounts[$field];
        }
        if ($mixed26 && $fieldCounts !== ['description'=>26,'images'=>25,'primaryImage'=>25]) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_FIELD_COUNTS');
        }
        $targets = [];
        foreach ($manifest['targets'] as $target) {
            if (!is_array($target) || !self::exactKeys($target, ['anytourHotelId','expectedIdentity','sources','photos'])
                || !is_int($target['anytourHotelId']) || !isset($actions[$target['anytourHotelId']]) || isset($targets[$target['anytourHotelId']])
                || !is_array($target['expectedIdentity']) || !self::exactKeys($target['expectedIdentity'], ['name','country','region'])
                || !is_string($target['expectedIdentity']['name']) || trim($target['expectedIdentity']['name']) === ''
                || !is_string($target['expectedIdentity']['country']) || trim($target['expectedIdentity']['country']) === ''
                || ($target['expectedIdentity']['region'] !== null && (!is_string($target['expectedIdentity']['region']) || trim($target['expectedIdentity']['region']) === ''))
                || !is_array($target['sources']) || !array_is_list($target['sources']) || count($target['sources']) < 1 || count($target['sources']) > 16
                || !is_array($target['photos']) || !array_is_list($target['photos'])) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_TARGET');
            }
            $sourceUrls = [];
            foreach ($target['sources'] as $source) {
                if (!is_array($source) || !self::exactKeys($source, $mixed26
                        ? ['url','checked_at_utc','content_sha256','capture_format'] : ['url','checked_at_utc','content_sha256'])
                    || !self::officialUrl($source['url']) || isset($sourceUrls[$source['url']])
                    || !is_string($source['checked_at_utc'])
                    || ($source['content_sha256'] !== null && !self::officialSha($source['content_sha256']))
                    || ($mixed26 && (!self::officialSha($source['content_sha256'])
                        || !in_array($source['capture_format'], ['raw_html','retrieved_web_text_not_origin_html'], true)))) {
                    throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_SOURCE');
                }
                // Retain actual capture precision; UTC may be written as Z or +00:00.
                $timeParts = [];
                if (preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})(?:\.([0-9]{1,6}))?(?:Z|\+00:00)\z/D', $source['checked_at_utc'], $timeParts) !== 1) {
                    throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_SOURCE_TIME');
                }
                $canonicalTime = $timeParts[1] . '.' . str_pad($timeParts[2] ?? '', 6, '0') . '+00:00';
                $checked = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $canonicalTime);
                if ($checked === false || $checked->format('Y-m-d\TH:i:s.uP') !== $canonicalTime) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_SOURCE_TIME');
                $sourceUrls[$source['url']] = true;
            }
            $photoUrls = [];
            foreach ($target['photos'] as $photo) {
                if (!is_array($photo) || !self::exactKeys($photo, ['url','source_page_url','sha256'])
                    || !self::officialUrl($photo['url']) || !is_string($photo['source_page_url'])
                    || !isset($sourceUrls[$photo['source_page_url']]) || !self::officialSha($photo['sha256'])) {
                    throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_PHOTO');
                }
                $photoUrls[] = $photo['url'];
            }
            $id = $target['anytourHotelId'];
            if ($photoUrls !== ($actions[$id]['values']['images'] ?? [])) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_PHOTO_COVERAGE');
            if ($mixed26 && $target['expectedIdentity']['name'] !== $actions[$id]['canonicalName']) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_IDENTITY');
            }
            $targets[$id] = $target + $actions[$id];
        }
        ksort($targets, SORT_NUMERIC);
        if (array_keys($targets) !== array_keys($ids)) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL_IDS');
        return [
            'manifestSha256'=>$approved['manifestSha256'],'actionsSha256'=>$approved['actionsSha256'],
            'originalPackageSha256'=>$manifest['original_package_sha256'],'approvalRef'=>$approved['approvalRef'],'targets'=>$targets,
            'sourcePolicy'=>$policy,'strictPublicBefore'=>$mixed26,
        ];
    }

    /** Exact schema of the reviewed 26-hotel actions; no broader arbitrary patch admission. */
    private static function official26Action(mixed $action): array
    {
        $preconditions = ['fresh_profile_required','revision_and_before_match_required',
            'raw_source_and_manual_override_guards_required','atomic_existing_owner_write_required','zero_automatic_replay_on_unknown'];
        if (!is_array($action) || !self::exactKeys($action, ['work_id','anytour_hotel_id','canonical_name',
                'observed_revision','observed_public_profile_sha256','field_changes','unchanged_profile_fields','preconditions'])
            || !is_string($action['canonical_name']) || trim($action['canonical_name']) === ''
            || !self::officialSha($action['observed_public_profile_sha256'])
            || !is_array($action['field_changes']) || !array_is_list($action['field_changes'])
            || !in_array(count($action['field_changes']), [1,3], true)
            || !is_array($action['unchanged_profile_fields']) || !array_is_list($action['unchanged_profile_fields'])
            || $action['unchanged_profile_fields'] === []
            || !is_array($action['preconditions']) || !self::exactKeys($action['preconditions'], $preconditions)) {
            throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_ACTION');
        }
        foreach ($preconditions as $guard) {
            if ($action['preconditions'][$guard] !== true) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_GUARD');
        }
        $fields = []; $names = [];
        foreach ($action['field_changes'] as $field) {
            if (!is_array($field) || !self::exactKeys($field, ['field','before','after'])
                || !is_string($field['field']) || !in_array($field['field'], self::OFFICIAL_FIELDS, true)
                || isset($names[$field['field']])) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_FIELD');
            $names[$field['field']] = true;
            $fields[] = ['field'=>$field['field'],'before_public_projection'=>$field['before'],
                'proposed_value'=>$field['after'],'decision'=>'proposed_not_applied'];
        }
        if (count($fields) === 1 && !isset($names['description'])) throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_PARTIAL');
        $preserved = [];
        foreach ($action['unchanged_profile_fields'] as $field) {
            if (!is_string($field) || $field === '' || isset($preserved[$field]) || isset($names[$field])) {
                throw new InvalidArgumentException('ANYTOUR_PROFILE_OFFICIAL26_PRESERVED');
            }
            $preserved[$field] = true;
        }
        return [
            'action'=>['anytourHotelId'=>$action['anytour_hotel_id'],'workId'=>$action['work_id'],
                'observedRevision'=>$action['observed_revision'],'mutation'=>'fill_only_if_stored_field_still_missing',
                'fields'=>$fields,'guards_required_before_apply'=>$preconditions,'runtime_admission'=>null],
            'guards'=>['canonicalName'=>$action['canonical_name'],'expectedPublicProfileSha256'=>$action['observed_public_profile_sha256'],
                'unchangedProfileFields'=>array_keys($preserved)],
        ];
    }

    private function officialSchema(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_MYSQL');
        $wanted = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'];
        $rows = $this->pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_catalog_control','anytour_hotels','anytour_hotel_sources')")->fetchAll(PDO::FETCH_ASSOC);
        $tables = [];
        foreach ($rows as $row) $tables[$row['TABLE_NAME']] = $row;
        foreach ($wanted as $table) {
            if (($tables[$table]['ENGINE'] ?? null) !== 'InnoDB' || ($tables[$table]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_SCHEMA');
            }
        }
        if ((int)$this->pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn() !== 1) {
            throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_SCHEMA_VERSION');
        }
    }

    /** Bind all existing source rows without requiring or creating any foreign-ID alias. */
    private function officialSources(int $id, bool $lock): array
    {
        $query = $this->pdo->prepare('SELECT id,namespace,HEX(external_key) AS external_key_hex,acquired_via,
            source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources
            WHERE anytour_hotel_id=? ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 256) throw new DomainException('ANYTOUR_PROFILE_OFFICIAL_SOURCE_BOUND');
        foreach ($rows as &$row) {
            if (!is_string($row['source_json']) || !self::officialSha($row['source_sha256'])
                || !hash_equals($row['source_sha256'], hash('sha256', $row['source_json']))) {
                throw new DomainException('ANYTOUR_PROFILE_OFFICIAL_SOURCE_INTEGRITY');
            }
            $row['id'] = self::positiveInt($row['id']);
            $row['external_key_hex'] = strtolower($row['external_key_hex']);
            unset($row['source_json']);
        }
        unset($row);
        return $rows;
    }

    private function officialSnapshot(array $approval, bool $lock): array
    {
        $read = $this->pdo->prepare('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
        $selected = [];
        foreach ($approval['targets'] as $id=>$target) {
            $read->execute([$id]); $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['is_active'] !== 1 || (int)$row['revision'] !== 1) {
                throw new DomainException('ANYTOUR_PROFILE_OFFICIAL_PROFILE_STATE');
            }
            $profile = self::decodeProfile($row);
            $identity = ['name'=>$profile['name'] ?? null,'country'=>$profile['country']['name'] ?? null,'region'=>$profile['region']['name'] ?? null];
            if (self::stable($identity) !== self::stable($target['expectedIdentity'])) {
                throw new DomainException('ANYTOUR_PROFILE_OFFICIAL_IDENTITY');
            }
            if ($approval['strictPublicBefore']) {
                $public = array_merge(AnyTourCanonicalCatalog::publicProfile($profile), ['id'=>$id,'catalog'=>'anytour','revision'=>1]);
                if (!hash_equals($target['expectedPublicProfileSha256'], hash('sha256', self::json($public)))) {
                    throw new DomainException('ANYTOUR_PROFILE_OFFICIAL26_PUBLIC_BEFORE');
                }
                $unchanged = array_values(array_diff(array_keys($public), array_keys($target['values'])));
                $expectedUnchanged = $target['unchangedProfileFields'];
                sort($unchanged, SORT_STRING); sort($expectedUnchanged, SORT_STRING);
                if ($unchanged !== $expectedUnchanged) throw new DomainException('ANYTOUR_PROFILE_OFFICIAL26_PRESERVED_FIELDS');
            }
            $sources = $this->officialSources($id, $lock);
            foreach ($sources as $source) {
                // These existing content operations cannot be reconciled with the approved revision 1 baseline.
                if (in_array($source['namespace'], [self::PROVENANCE_NAMESPACE,self::SYNC_NAMESPACE,self::OFFICIAL_NAMESPACE], true)) {
                    throw new DomainException('ANYTOUR_PROFILE_OFFICIAL_PRIOR_CONTENT_OPERATION');
                }
            }
            $patch = []; $preserved = [];
            foreach (self::OFFICIAL_FIELDS as $field) {
                if (!array_key_exists($field, $target['values'])) { $preserved[] = $field; continue; }
                if (self::missing(self::ownValue($profile, $field))) $patch[$field] = $target['values'][$field];
                elseif ($approval['strictPublicBefore']) throw new DomainException('ANYTOUR_PROFILE_OFFICIAL26_RAW_NOT_MISSING');
                else $preserved[] = $field;
            }
            $selected[] = [
                'anytourHotelId'=>$id,'expectedProfileSha256'=>$row['profile_sha256'],'expectedRevision'=>1,
                'beforeProfileJson'=>$row['profile_json'],'sourceStateSha256'=>hash('sha256', self::json($sources)),
                'sourceFingerprint'=>hash('sha256', self::json($target)),
                'patch'=>$patch,'preservedFields'=>$preserved,
            ];
        }
        $core = [
            'schemaVersion'=>1,'sourcePolicy'=>$approval['sourcePolicy'],
            'manifestSha256'=>$approval['manifestSha256'],'actionsSha256'=>$approval['actionsSha256'],
            'originalPackageSha256'=>$approval['originalPackageSha256'],'approvalRef'=>$approval['approvalRef'],
            'selected'=>$selected,'roomMealFieldsExcluded'=>true,'traitsExcluded'=>true,
        ];
        $core['planSha256'] = hash('sha256', self::json($core));
        return $core;
    }

    /** New statements after commit, including the real public catalogue reader. */
    private function officialReadback(array $snapshot, array $expected): array
    {
        $read = $this->pdo->prepare('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');
        foreach ($snapshot['selected'] as $item) {
            $id = $item['anytourHotelId']; $want = $expected[$id];
            $read->execute([$id]); $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['is_active'] !== 1 || (int)$row['revision'] !== $want['revision']
                || !hash_equals($want['profileSha256'], (string)$row['profile_sha256'])) {
                throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_PROFILE');
            }
            self::decodeProfile($row);
            $remaining = []; $provenanceFound = $want['provenanceSha256'] === null;
            foreach ($this->officialSources($id, false) as $source) {
                if ($want['provenanceSha256'] !== null && $source['namespace'] === self::OFFICIAL_NAMESPACE
                    && $source['external_key_hex'] === bin2hex($want['externalKey'])) {
                    if ($source['acquired_via'] !== self::OFFICIAL_ACQUIRED_VIA || $source['source_sha256'] !== $want['provenanceSha256']) {
                        throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_PROVENANCE');
                    }
                    $provenanceFound = true;
                } else $remaining[] = $source;
            }
            if (!$provenanceFound || !hash_equals($item['sourceStateSha256'], hash('sha256', self::json($remaining)))) {
                throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_SOURCES');
            }
        }
        $consumer = (new AnyTourCanonicalCatalog($this->pdo))->read(array_keys($expected));
        if ($consumer['missingIds'] !== [] || count($consumer['items']) !== count($expected)) {
            throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_CONSUMER');
        }
        $readback = [];
        foreach ($consumer['items'] as $public) {
            $id = $public['id']; $want = $expected[$id] ?? null;
            if (!is_array($want)) throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_CONSUMER');
            $expectedPublic = array_merge(AnyTourCanonicalCatalog::publicProfile(json_decode($want['profileJson'], true, 512, JSON_THROW_ON_ERROR)),
                ['id'=>$id,'catalog'=>'anytour','revision'=>$want['revision']]);
            if (self::json($expectedPublic) !== self::json($public)) throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_POSTCOMMIT_CONSUMER');
            $readback[] = [
                'anytourHotelId'=>$id,'revision'=>$want['revision'],'profileSha256'=>$want['profileSha256'],'changedFields'=>$want['fields'],
                'descriptionPresent'=>is_string($public['description'] ?? null) && trim($public['description']) !== '',
                'primaryImagePresent'=>is_string($public['primaryImage'] ?? null) && trim($public['primaryImage']) !== '',
                'imagesCount'=>is_array($public['images'] ?? null) ? count($public['images']) : 0,
                'detailsAvailable'=>$public['detailsAvailable'] ?? null,
            ];
        }
        return ['readbackVerified'=>true,'consumerReadbackVerified'=>true,
            'consumerReadbackSha256'=>hash('sha256', self::json($consumer)),'readback'=>$readback];
    }
}

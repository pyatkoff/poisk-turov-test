<?php
declare(strict_types=1);

/**
 * Pure durable-state contract for one selected Andromeda quote operation.
 * Runtime persistence/locking is owned by the existing private Search3 gateway.
 * No supplier/private identity is retained here: only provenance digests and the
 * already browser-safe completed result may be persisted.
 */
final class AnyTourAndromedaQuoteAttemptState
{
    private const KEYS = ['version', 'status', 'context_sha256', 'operation_sha256', 'result'];

    public static function reserve(string $contextSha256, string $operationSha256): array
    {
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        return [
            'version' => 1,
            'status' => 'reserved',
            'context_sha256' => $contextSha256,
            'operation_sha256' => $operationSha256,
            'result' => null,
        ];
    }

    public static function completed(array $reserved, array $result): array
    {
        self::assertReserved($reserved);
        self::assertPublicResult($result);
        $next = $reserved;
        $next['status'] = 'completed';
        $next['result'] = $result;
        return $next;
    }

    public static function unknown(array $reserved): array
    {
        self::assertReserved($reserved);
        $next = $reserved;
        $next['status'] = 'unknown';
        return $next;
    }

    /**
     * A completed quote may be reused locally only for the exact current context.
     * Reserved/unknown operations are sealed and can never authorize another call.
     */
    public static function replay(array $state, string $contextSha256, string $operationSha256): array
    {
        self::assertState($state);
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        if (!hash_equals($state['context_sha256'], $contextSha256)
            || !hash_equals($state['operation_sha256'], $operationSha256)
            || $state['status'] !== 'completed' || !is_array($state['result'])) {
            throw new RuntimeException('ANDROMEDA_QUOTE_REPLAY_REFUSED');
        }
        self::assertPublicResult($state['result']);
        return $state['result'];
    }

    private static function assertReserved(array $state): void
    {
        self::assertState($state);
        if ($state['status'] !== 'reserved' || $state['result'] !== null) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
    }

    private static function assertState(array $state): void
    {
        $keys = array_keys($state);
        $expected = self::KEYS;
        sort($keys);
        sort($expected);
        if ($keys !== $expected || ($state['version'] ?? null) !== 1
            || !in_array($state['status'] ?? null, ['reserved', 'unknown', 'completed'], true)
            || !is_string($state['context_sha256'] ?? null)
            || !is_string($state['operation_sha256'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
        self::assertDigest($state['context_sha256']);
        self::assertDigest($state['operation_sha256']);
        if ($state['status'] === 'completed') {
            if (!is_array($state['result'])) throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        } elseif ($state['result'] !== null) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
    }

    private static function assertPublicResult(array $result): void
    {
        if (($result['schema_version'] ?? null) !== 1
            || ($result['provider'] ?? null) !== 'andromeda'
            || ($result['selection_enabled'] ?? null) !== true
            || ($result['booking_enabled'] ?? null) !== false
            || !is_int($result['local_id'] ?? null) || $result['local_id'] < 1
            || !in_array($result['state'] ?? null, ['quote_verified', 'flight_selection_required'], true)
            || !is_bool($result['flight_selection_required'] ?? null)
            || !is_array($result['flights'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }

        // Search/package/final are distinct supplier facts. Validate shape only;
        // never infer equality, add fees, convert currencies or replace one with another.
        self::assertMoney($result['search_price'] ?? null, false);
        if (($result['package_price'] ?? null) !== null) {
            self::assertMoney($result['package_price'], false);
        }

        if ($result['state'] === 'quote_verified') {
            if (($result['quote_state'] ?? null) !== 'verified'
                || ($result['final_price_verified'] ?? null) !== true
                || $result['flight_selection_required'] !== false) {
                throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
            }
            self::assertMoney($result['final_price'] ?? null, false);
        } elseif (($result['quote_state'] ?? null) !== 'unverified'
            || ($result['final_price_verified'] ?? null) !== false
            || ($result['final_price'] ?? null) !== null
            || $result['flight_selection_required'] !== true) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }

        if ($result['flights'] !== []
            && array_keys($result['flights']) !== range(0, count($result['flights']) - 1)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }
        self::assertNoPrivateKeys($result);
    }

    private static function assertMoney(mixed $value, bool $allowZero): void
    {
        if (!is_array($value)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
        $keys = array_keys($value);
        sort($keys);
        if ($keys !== ['amount', 'currency']) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
        $amount = $value['amount'];
        $currency = $value['currency'];
        if (!is_string($amount)
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $amount) !== 1
            || (!$allowZero && preg_match('/[1-9]/', $amount) !== 1)
            || !is_string($currency)
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
    }

    private static function assertNoPrivateKeys(array $value): void
    {
        $private = ['supplier_offer_id', 'supplier_hotel_id', 'claiminc', 'claimdocument', 'sid', 'uid', 'catalogkey'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string)$key), $private, true)) {
                throw new RuntimeException('ANDROMEDA_QUOTE_PRIVATE_STATE');
            }
            if (is_array($item)) self::assertNoPrivateKeys($item);
        }
    }

    private static function assertDigest(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('ANDROMEDA_QUOTE_PROVENANCE_INVALID');
        }
    }
}

/** Private, context-wide ledger for freshly issued bounded flight repricing. */
final class AnyTourAndromedaFlightRepricingState
{
    private const KEYS = ['version','provider','context_sha256','expires_at','flight_state_sha256',
        'session_sid','initial_pricing','status','claim','pairs','head_pair','action_count',
        'last_started_ms','active_pair','active_token'];

    public static function create(string $context, string $flightStateDigest, string $sid,
        array $claim, array $initialQuote, int $expiresAt): array
    {
        AnyTourAndromedaQuoteAttemptState::completed(
            AnyTourAndromedaQuoteAttemptState::reserve($context, hash('sha256','andromeda-selected-quote-v1')), $initialQuote);
        if (($initialQuote['state'] ?? null) !== 'flight_selection_required') self::invalid();
        $state = ['version'=>2,'provider'=>'andromeda','context_sha256'=>$context,'expires_at'=>$expiresAt,
            'flight_state_sha256'=>$flightStateDigest,'session_sid'=>$sid,
            'initial_pricing'=>['search_price'=>$initialQuote['search_price'],
                'package_price'=>$initialQuote['package_price'] ?? null,
                'search_price_estimate'=>$initialQuote['search_price_estimate'] ?? null],
            'status'=>'ready','claim'=>$claim,'pairs'=>[],'head_pair'=>null,'action_count'=>0,
            'last_started_ms'=>0,'active_pair'=>null,'active_token'=>null];
        self::validate($state);
        return $state;
    }

    public static function metadata(array $state): array
    {
        self::validate($state);
        $enabled = $state['status'] === 'ready';
        return ['enabled'=>$enabled,'max_pairs'=>3,'used_pairs'=>count($state['pairs']),
            'remaining_pairs'=>$enabled ? 3-count($state['pairs']) : 0];
    }

    public static function begin(array $state, string $context, array $selection, array $selected,
        string $token, int $now): array
    {
        self::current($state, $context, $now);
        if ($state['status'] !== 'ready') throw new RuntimeException('ANDROMEDA_QUOTE_REPLAY_REFUSED');
        self::selection($selection); self::token($token);
        $selection=['provider'=>'andromeda','outbound_ref'=>$selection['outbound_ref'],'return_ref'=>$selection['return_ref']];
        $uids = self::uids($selected);
        $key = self::pairKey($context, $selection);
        if (isset($state['pairs'][$key])) {
            $pair = $state['pairs'][$key];
            if ($pair['selection'] !== $selection || $pair['selected_uids'] !== $uids
                || $pair['status'] !== 'completed') self::invalid();
            return ['state'=>$state,'replay'=>$pair['result']];
        }
        if (count($state['pairs']) >= 3) throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_BUDGET');
        $state['pairs'][$key] = ['selection'=>$selection,'selected_uids'=>$uids,'operation_token'=>$token,
            'status'=>'reserved','action_count'=>0,'result'=>null];
        $state['status']='reserved'; $state['active_pair']=$key; $state['active_token']=$token;
        self::validate($state);
        return ['state'=>$state,'replay'=>null];
    }

    /** Reserve before dispatch, including pacing across different request instances. */
    public static function spend(array $state, string $token, int $nowMs): array
    {
        self::current($state, $state['context_sha256'] ?? '', intdiv($nowMs,1000));
        self::active($state, $token);
        $key=$state['active_pair'];
        if ($state['action_count'] >= 9 || $state['pairs'][$key]['action_count'] >= 3) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_BUDGET');
        }
        $started=max($nowMs,$state['last_started_ms']+1050);
        if (intdiv($started,1000) >= $state['expires_at']) throw new DomainException('offer_expired');
        ++$state['action_count']; ++$state['pairs'][$key]['action_count'];
        $state['last_started_ms']=$started;
        self::validate($state);
        return ['state'=>$state,'wait_ms'=>$started-$nowMs];
    }

    /** The new supplier head and its public receipt are committed in one envelope. */
    public static function completed(array $state, string $token, array $claim, array $result, int $now): array
    {
        self::current($state, $state['context_sha256'] ?? '', $now);
        self::active($state, $token);
        $key=$state['active_pair']; $pair=$state['pairs'][$key];
        self::claim($claim); self::claimPair($claim,$pair['selected_uids']);
        self::result($result,$state['context_sha256'],$key,$pair['selection'],$state['expires_at']);
        self::pricing($result,$state['initial_pricing']);
        if ($result['verified_at']>$now) self::invalid();
        $buyer=[];
        foreach (($claim['claimDocument'][0]['buyerMoneys'] ?? []) as $block) {
            foreach ((is_array($block) ? ($block['buyerClaimMoney'] ?? []) : []) as $row) if (is_array($row)) $buyer[]=$row;
        }
        if (count($buyer)!==1 || self::amount($buyer[0]['net'] ?? null)!==$result['final_price']['amount']
            || ($buyer[0]['currency'] ?? null)!==$result['final_price']['currency']) self::invalid();
        unset($result['repricing']);
        $state['pairs'][$key]['status']='completed'; $state['pairs'][$key]['result']=$result;
        $state['claim']=$claim; $state['head_pair']=$key;
        $state['status']='ready'; $state['active_pair']=null; $state['active_token']=null;
        self::validate($state);
        return $state;
    }

    /** Any uncertain mutable result seals the whole context, including cached pairs. */
    public static function unknown(array $state, string $token): array
    {
        self::validate($state); self::token($token);
        $known=false;
        foreach ($state['pairs'] as $pair) if (hash_equals($pair['operation_token'],$token)) $known=true;
        if (!$known) self::invalid();
        $state['status']='unknown';
        self::validate($state);
        return $state;
    }

    public static function current(array $state, string $context, int $now): void
    {
        self::validate($state);
        if (!hash_equals($state['context_sha256'],$context)) self::invalid();
        if ($now >= $state['expires_at']) throw new DomainException('offer_expired');
    }

    public static function pairKey(string $context, array $selection): string
    {
        self::digest($context); self::selection($selection);
        return hash('sha256',json_encode([$context,$selection['outbound_ref'],$selection['return_ref']],JSON_THROW_ON_ERROR));
    }

    public static function validate(array $state): void
    {
        $keys=array_keys($state); $expected=self::KEYS; sort($keys); sort($expected);
        if ($keys!==$expected || $state['version']!==2 || $state['provider']!=='andromeda'
            || !is_int($state['expires_at']) || $state['expires_at']<1
            || !is_string($state['session_sid']) || preg_match('/^[A-Za-z0-9_-]{1,256}$/D',$state['session_sid'])!==1
            || !in_array($state['status'],['ready','reserved','unknown'],true)
            || !is_array($state['pairs']) || count($state['pairs'])>3
            || !is_int($state['action_count']) || $state['action_count']<0 || $state['action_count']>9
            || !is_int($state['last_started_ms']) || $state['last_started_ms']<0) self::invalid();
        self::digest($state['context_sha256']); self::digest($state['flight_state_sha256']); self::claim($state['claim']);
        $pricing=$state['initial_pricing'];
        if (!is_array($pricing) || array_keys($pricing)!==['search_price','package_price','search_price_estimate']) self::invalid();
        self::money($pricing['search_price']);
        if ($pricing['package_price']!==null) self::money($pricing['package_price']);
        if ($pricing['search_price_estimate']!==null) self::money($pricing['search_price_estimate'],true);
        $spent=0; $reserved=[];
        foreach ($state['pairs'] as $key=>$pair) {
            self::digest($key);
            if (!is_array($pair) || array_keys($pair)!==['selection','selected_uids','operation_token','status','action_count','result']
                || !in_array($pair['status'] ?? null,['reserved','completed'],true)
                || !is_int($pair['action_count']) || $pair['action_count']<0 || $pair['action_count']>3) self::invalid();
            self::selection($pair['selection']); self::uidMap($pair['selected_uids']); self::token($pair['operation_token']);
            if ($key!==self::pairKey($state['context_sha256'],$pair['selection'])) self::invalid();
            $spent+=$pair['action_count'];
            if ($pair['status']==='completed') {
                if ($pair['action_count']<1) self::invalid();
                self::result($pair['result'],$state['context_sha256'],$key,$pair['selection'],$state['expires_at']);
                self::pricing($pair['result'],$pricing);
            } else { if ($pair['result']!==null) self::invalid(); $reserved[]=$key; }
        }
        if ($spent!==$state['action_count']) self::invalid();
        if ($state['active_pair']===null || $state['active_token']===null) {
            if ($state['active_pair']!==null || $state['active_token']!==null || $reserved!==[] || $state['status']==='reserved') self::invalid();
        } elseif (!is_string($state['active_pair']) || $reserved!==[$state['active_pair']]
            || $state['status']==='ready' || ($state['pairs'][$state['active_pair']]['operation_token'] ?? null)!==$state['active_token']) self::invalid();
        if ($state['head_pair']!==null) {
            if (!is_string($state['head_pair']) || ($state['pairs'][$state['head_pair']]['status'] ?? null)!=='completed') self::invalid();
            self::claimPair($state['claim'],$state['pairs'][$state['head_pair']]['selected_uids']);
        }
        if (strlen(json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))>3000000) self::invalid();
    }

    private static function active(array $state, string $token): void
    {
        self::token($token);
        if ($state['status']!=='reserved' || !is_string($state['active_token'])
            || !hash_equals($state['active_token'],$token)) throw new RuntimeException('ANDROMEDA_QUOTE_REPLAY_REFUSED');
    }
    private static function result(mixed $result,string $context,string $key,array $selection,int $expires): void
    {
        if (!is_array($result) || ($result['state'] ?? null)!=='quote_verified'
            || !is_int($result['verified_at'] ?? null) || $result['verified_at']<1 || $result['verified_at']>=$expires
            || ($result['expires_at'] ?? null)!==$expires || !is_array($result['flights'] ?? null) || count($result['flights'])!==2
            || ($result['flights'][0]['direction'] ?? null)!=='0' || ($result['flights'][1]['direction'] ?? null)!=='1'
            || ($result['flights'][0]['flight_ref'] ?? null)!==$selection['outbound_ref']
            || ($result['flights'][1]['flight_ref'] ?? null)!==$selection['return_ref']) self::invalid();
        AnyTourAndromedaQuoteAttemptState::completed(AnyTourAndromedaQuoteAttemptState::reserve($context,$key),$result);
    }
    private static function pricing(array $result,array $pricing): void
    {
        if (($result['search_price'] ?? null)!==$pricing['search_price']
            || ($result['package_price'] ?? null)!==$pricing['package_price']
            || ($result['search_price_estimate'] ?? null)!==$pricing['search_price_estimate']) self::invalid();
        if ($result['final_price']['currency']!==$pricing['search_price']['currency']) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_CURRENCY');
        }
    }
    private static function claim(array $claim): void
    {
        if (!is_array($claim['claimDocument'] ?? null) || array_keys($claim['claimDocument'])!==[0] || !is_array($claim['claimDocument'][0])
            || ($claim['claimDocument'][0]['condition'] ?? null)!=='ccOffer'
            || strlen(json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))>2097152) self::invalid();
    }
    private static function claimPair(array $claim,array $uids): void
    {
        $rows=[];
        foreach (($claim['claimDocument'][0]['transports'] ?? []) as $block) {
            foreach ((is_array($block) ? ($block['transport'] ?? []) : []) as $row) {
                if (!is_array($row) || ($row['type'] ?? null)!=='ttAvia') continue;
                $direction=(string)($row['direction'] ?? '');
                if (!in_array($direction,['0','1'],true) || isset($rows[$direction])) self::invalid();
                $rows[$direction]=$row['uid'] ?? null;
            }
        }
        ksort($rows,SORT_STRING);
        if ($rows!==$uids) self::invalid();
    }
    private static function uids(array $selected): array
    {
        if (array_keys($selected)!==[0,1]) self::invalid();
        $uids=[];
        foreach ([0,1] as $direction) {
            if (!is_array($selected[$direction]) || (string)($selected[$direction]['direction'] ?? '')!==(string)$direction
                || ($selected[$direction]['type'] ?? null)!=='ttAvia') self::invalid();
            $uids[$direction]=$selected[$direction]['uid'] ?? null;
        }
        self::uidMap($uids); return $uids;
    }
    private static function uidMap(mixed $uids): void
    {
        if (!is_array($uids) || array_keys($uids)!==[0,1]) self::invalid();
        foreach ($uids as $uid) if (!is_string($uid) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$uid)!==1) self::invalid();
    }
    private static function selection(array $selection): void
    {
        $keys=array_keys($selection); sort($keys);
        if ($keys!==['outbound_ref','provider','return_ref'] || ($selection['provider'] ?? null)!=='andromeda') self::invalid();
        foreach (['outbound_ref','return_ref'] as $key) if (!is_string($selection[$key]) || preg_match('/^flight_[a-f0-9]{32}$/D',$selection[$key])!==1) self::invalid();
        if ($selection['outbound_ref']===$selection['return_ref']) self::invalid();
    }
    private static function money(mixed $money,bool $estimate=false): void
    {
        if (!is_array($money)) self::invalid();
        $keys=array_keys($money); sort($keys); $expected=$estimate?['amount','currency','source']:['amount','currency'];
        if ($keys!==$expected || self::amount($money['amount'] ?? null)===null
            || preg_match('/[1-9]/',$money['amount'])!==1 || !is_string($money['currency'] ?? null)
            || preg_match('/^[A-Z0-9_]{2,8}$/D',$money['currency'])!==1
            || ($estimate && $money['source']!=='derived_search_estimate')) self::invalid();
    }
    private static function amount(mixed $value): ?string
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) $value=(string)$value;
        return is_string($value) && preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$value)===1 ? $value : null;
    }
    private static function digest(mixed $value): void { if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D',$value)!==1) self::invalid(); }
    private static function token(mixed $value): void { if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/D',$value)!==1) self::invalid(); }
    private static function invalid(): never { throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID'); }
}

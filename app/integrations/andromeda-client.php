<?php
declare(strict_types=1);

/** Offline-first protocol client. No default network transport or runtime consumer. */
final class AnyTourAndromedaClient
{
    private const ENDPOINT = 'https://gateway.samo.ru/api/';
    private const BODY_LIMIT = 2097152; // Local safety budget, not a supplier limit.
    private $transport;
    private $enabled;
    private $sid = null;
    private $expires = 0;
    private $requests = 0;
    private $priceAttempted = false;

    /** Transport accepts a secret-bearing URL and must never log it. */
    public function __construct(callable $transport, bool $enabled = false)
    {
        $this->transport = $transport;
        $this->enabled = $enabled;
    }

    public function __debugInfo(): array
    {
        return ['enabled' => $this->enabled, 'requests' => $this->requests];
    }

    public function __serialize(): array
    {
        throw new RuntimeException('ANDROMEDA_SERIALIZATION_DISABLED');
    }

    public function login(string $username, string $password): void
    {
        $this->sid = null;
        $this->expires = 0;
        if ($username === '' || strlen($username) > 256 || $password === '' || strlen($password) > 4096) {
            throw new RuntimeException('ANDROMEDA_CREDENTIALS_REQUIRED');
        }
        $nonce = random_bytes(32);
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $digest = base64_encode(sha1($nonce . $created . md5($password), true));
        $started = time();
        $reply = $this->send('login', [
            'username' => $username, 'password' => $digest,
            'nonce' => base64_encode($nonce), 'created' => $created,
        ]);
        if (!isset($reply['sid']) || !is_string($reply['sid'])
            || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $reply['sid'])) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        $this->sid = $reply['sid'];
        $this->expires = $started + 3000; // Conservative local expiry; wiki says ~1h.
    }

    /** Trusted private server storage only; never include this in public projections. */
    public function privateSession(): array {
        return $this->sid!==null && time()<$this->expires ? ['sid'=>$this->sid,'expires'=>$this->expires] : [];
    }

    public function restorePrivateSession(array $state): void {
        if (!is_string($state['sid']??null) || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D',$state['sid'])
            || !is_int($state['expires']??null) || $state['expires']<=time() || $state['expires']>time()+3000)
            throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        $this->sid=$state['sid'];$this->expires=$state['expires'];
    }

    public function ensureLogin(string $username,string $password): void {
        if(!$this->privateSession())$this->login($username,$password);
    }

    /** Read-only dictionaries only. Price/booking and arbitrary parameters are excluded. */
    public function catalog(string $action, array $params = []): array
    {
        $keys = ['townfrom' => [], 'state' => ['TOWNFROMINC'], 'all' => ['TOWNFROMINC', 'STATEINC']];
        if (!array_key_exists($action, $keys)) throw new RuntimeException('ANDROMEDA_ACTION_NOT_ALLOWED');
        $actual = array_keys($params);
        $expected = $keys[$action];
        sort($actual);
        sort($expected);
        if ($actual !== $expected) throw new RuntimeException('ANDROMEDA_INVALID_PARAMS');
        foreach ($params as $value) {
            if (!is_int($value) || $value <= 0) throw new RuntimeException('ANDROMEDA_INVALID_PARAMS');
        }
        if ($this->sid === null || time() >= $this->expires) {
            $this->sid = null;
            throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        }
        $sid = $this->sid;
        $reply = $this->send($action, ['sid' => $sid] + $params);
        $required = ['townfrom' => ['TOWNFROM'], 'state' => ['STATE'],
            'all' => ['CHECKIN_BEG', 'TOWNTO', 'STARS', 'HOTELS', 'MEAL', 'CURRENCY', 'OPERATORS']];
        $result = [];
        foreach ($required[$action] as $key) {
            if (!isset($reply[$key]) || !is_array($reply[$key])) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
            $result[$key] = $reply[$key];
        }
        // Fail closed if a supplier echo contains the session, including encoded forms.
        $this->rejectSessionEcho($result, $sid);
        foreach ($result as $key => $rows) {
            if ($key !== 'CHECKIN_BEG') $this->validateDictionaryRows($rows);
        }
        return $result;
    }


    /** Explicit single-page pilot; never called by the live search renderer. */
    public static function priceProbeParams(): array
    {
        return ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260918',
            'CHECKIN_END'=>'20260918','NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8,
            'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'MEAL'=>'5',
            'OPERATORS'=>'5','PACKETTYPE'=>0,'PAGE'=>1];
    }

    public function priceProbe(): array
    {
        return $this->price(self::priceProbeParams());
    }

    /** One validated price page per server request; no automatic pagination/retry. */
    public static function validatePriceParams(array $params): void
    {
        $required=['TOWNFROMINC','STATEINC','CHECKIN_BEG','CHECKIN_END','NIGHTS_FROM','NIGHTS_TILL','ADULT','CHILD','CURRENCYINC','PACKETTYPE','PAGE'];
        if (array_diff($required,array_keys($params)) || array_diff(array_keys($params),array_merge($required,['MEAL','OPERATORS','AGES','HOTELS']))) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        foreach(['TOWNFROMINC','STATEINC','NIGHTS_FROM','NIGHTS_TILL','ADULT','CURRENCYINC'] as $key)
            if(!is_int($params[$key]) || $params[$key]<1) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        if(!is_int($params['PAGE']) || $params['PAGE']<1 || $params['PAGE']>1000 || $params['PACKETTYPE']!==0 || !is_int($params['CHILD']) || $params['CHILD']<0 || $params['CHILD']>3
            || $params['ADULT']>6 || $params['NIGHTS_FROM']>$params['NIGHTS_TILL'] || $params['NIGHTS_TILL']>28) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        foreach(['CHECKIN_BEG','CHECKIN_END'] as $key){
            $date=is_string($params[$key])?DateTimeImmutable::createFromFormat('!Ymd',$params[$key]):false;
            if(!$date || $date->format('Ymd')!==$params[$key]) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        }
        if($params['CHECKIN_BEG']>$params['CHECKIN_END'] || (new DateTimeImmutable($params['CHECKIN_BEG']))->diff(new DateTimeImmutable($params['CHECKIN_END']))->days>21) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        foreach(['MEAL','OPERATORS','AGES','HOTELS'] as $key) if(isset($params[$key]) && (!is_string($params[$key]) || strlen($params[$key])>300 || !preg_match('/^[0-9]+(?:,[0-9]+)*$/D',$params[$key]))) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
        if(isset($params['HOTELS']) && (count(explode(',',$params['HOTELS']))>30 || preg_match('/(?:^|,)0(?:,|$)/D',$params['HOTELS']))) throw new InvalidArgumentException('ANDROMEDA_INVALID_PARAMS');
    }

    public function price(array $params): array
    {
        self::validatePriceParams($params);
        if ($this->priceAttempted) throw new RuntimeException('ANDROMEDA_PRICE_REPLAY_REFUSED');
        if ($this->sid === null || time() >= $this->expires) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        $this->priceAttempted = true; // Reserve before sending, including unknown failures.
        $sid = $this->sid;
        $reply = $this->send('price', ['sid'=>$sid] + $params);
        $this->rejectSessionEcho($reply, $sid);
        if (!isset($reply['PAGE'], $reply['PAGES_COUNT'], $reply['PRICES'])
            || !is_array($reply['PRICES'])
            || !is_int($reply['PAGE']) || $reply['PAGE'] !== $params['PAGE']
            || !is_int($reply['PAGES_COUNT']) || $reply['PAGES_COUNT'] < 0) {
            throw new RuntimeException('ANDROMEDA_INVALID_PRICE_RESPONSE');
        }
        if (count($reply['PRICES']) > 2000) throw new RuntimeException('ANDROMEDA_PRICE_ROW_BUDGET');
        if ($reply['PAGES_COUNT'] === 0 && count($reply['PRICES']) > 0) throw new RuntimeException('ANDROMEDA_INVALID_PRICE_RESPONSE');
        return $reply; // Raw evidence only; no price arithmetic, mapping or selection.
    }

    private function validateDictionaryRows(array $rows): void
    {
        $seen = [];
        $index = 0;
        foreach ($rows as $key => $row) {
            if ($key !== $index++ || !is_array($row)
                || !isset($row['id'], $row['name'])
                || (!is_int($row['id']) && !is_string($row['id']))
                || !preg_match('/^[1-9][0-9]*$/D', (string) $row['id'])
                || strlen((string) $row['id']) > 32
                || !is_string($row['name']) || trim($row['name']) === '') {
                throw new RuntimeException('ANDROMEDA_INVALID_DICTIONARY');
            }
            // Preserve opaque numeric strings; reject duplicate logical IDs.
            $id = 'id:' . (string) $row['id'];
            if (isset($seen[$id])) throw new RuntimeException('ANDROMEDA_INVALID_DICTIONARY');
            $seen[$id] = true;
        }
    }

    private function rejectSessionEcho(array $value, string $sid): void
    {
        foreach ($value as $key => $item) {
            foreach ([$key, is_string($item) ? $item : ''] as $text) {
                $text = (string) $text;
                for ($i = 0; $i < 8; ++$i) {
                    if (strpos($text, $sid) !== false) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
                    $next = rawurldecode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($next === $text) break;
                    $text = $next;
                    if ($i === 7) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
                }
            }
            if (is_array($item)) $this->rejectSessionEcho($item, $sid);
        }
    }

    private function send(string $action, array $params): array
    {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_DISABLED');
        if ($this->requests >= 4) throw new RuntimeException('ANDROMEDA_REQUEST_BUDGET');
        ++$this->requests;
        $url = self::ENDPOINT . '?' . http_build_query(
            ['version' => '1.01', 'action' => $action] + $params, '', '&', PHP_QUERY_RFC3986);
        try {
            $response = ($this->transport)($url, [
                'method' => 'GET', 'verify_peer' => true, 'verify_host' => 2,
                'follow_redirects' => false, 'timeout' => 20,
                'max_response_bytes' => self::BODY_LIMIT,
            ]);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])
            || !is_int($response['status']) || !is_string($response['body'])) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        if ($response['status'] !== 200) throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
        if (strlen($response['body']) > self::BODY_LIMIT) throw new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE');
        try {
            $reply = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        if (!is_array($reply)) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        if (array_key_exists('error', $reply)) {
            $this->sid = null;
            $this->expires = 0;
            throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');
        }
        return $reply;
    }
}

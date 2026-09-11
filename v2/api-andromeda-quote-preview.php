<?php
declare(strict_types=1);

require_once __DIR__ . '/api-andromeda-search3-preview.php';
$quoteApp = is_file(__DIR__.'/app/integrations/andromeda-selected-quote.php')
    ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
require_once $quoteApp . '/andromeda-selected-offer.php';
require_once $quoteApp . '/andromeda-claim-actions.php';
require_once $quoteApp . '/andromeda-selected-quote.php';

/** Resolve a retained offer privately, under the same search/session authority as offer_detail. */
function anytour_andromeda_quote_resolve(array $request, PDO $pdo, array $saved, array $config, string $session): array
{
    $criteria = anytour_andromeda_search3_params($request, $pdo, $saved);
    $number = $criteria['PAGE'];
    $base = $criteria; unset($base['PAGE']);
    $ref = hash('sha256', 'paged-v1' . $session . json_encode($base));
    $context = $request['offer_context'] ?? [];
    if (($context['provider'] ?? null) !== 'andromeda' || ($context['search_ref'] ?? null) !== $ref
        || ($context['page'] ?? null) !== $number || !is_int($context['generation'] ?? null)
        || !is_string($context['offer_ref'] ?? null)) throw new InvalidArgumentException();

    $directory = dirname($config['catalog_path']) . '/searches';
    $firstPath = $directory . '/' . $ref . '-1.json';
    if (!is_file($firstPath) || is_link($firstPath)) throw new DomainException('offer_expired');
    $lock = fopen($directory . '/' . $ref . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_SH)) throw new RuntimeException();
    try {
        $first = json_decode(file_get_contents($firstPath), true, 32, JSON_THROW_ON_ERROR);
        $now = time();
        if (!in_array($first['status'] ?? null, ['complete', 'partial'], true)
            || $now >= ($first['store']['expires_at'] ?? 0)) throw new DomainException('offer_expired');
        $path = $number === 1 ? $firstPath : $directory . '/' . $ref . '-' . $first['store']['created_at'] . '-' . $number . '.json';
        if (!is_file($path) || is_link($path)) throw new DomainException('offer_expired');
        $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!in_array($state['status'] ?? null, ['complete', 'partial'], true)
            || ($state['store']['snapshot']['page'] ?? null) !== $number) throw new DomainException('offer_expired');
        $allows = static fn(array $offer): bool => anytour_andromeda_search3_mapping_allows(
            $pdo, (int)$request['params']['countryId'], $offer);
        return AnyTourAndromedaSelectedOffer::resolve(
            new AnyTourAndromedaOfferStore($state['store'], true), $context, $allows, $now);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function anytour_andromeda_quote_run(array $request, PDO $pdo, array $saved, array $config, string $session): array
{
    $resolved = anytour_andromeda_quote_resolve($request, $pdo, $saved, $config, $session);
    $budgetDirectory = dirname($config['catalog_path']);
    $transport = new AnyTourAndromedaTransport(false, true);
    $client = new AnyTourAndromedaClient(
        static function (string $url, array $options) use ($transport, $budgetDirectory): array {
            anytour_andromeda_search3_budget($budgetDirectory);
            return $transport($url, $options);
        }, true, true
    );
    $client->login($config['username'], $config['password']);
    $sid = $client->privateSession()['sid'] ?? null;
    if (!is_string($sid)) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
    $actions = new AnyTourAndromedaClaimActions($sid,
        static fn() => anytour_andromeda_search3_budget($budgetDirectory));
    return AnyTourAndromedaSelectedQuote::run($resolved, $client, $actions);
}

function anytour_andromeda_quote_http(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (!is_file(__DIR__.'/.andromeda-private.php')) anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    $config = require __DIR__.'/.andromeda-private.php';
    if (($config['enabled'] ?? false) !== true) anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') anytour_anex_search3_out(['ok'=>false,'error'=>'method_not_allowed'],405);
    if (!in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin', ['same-origin','none'], true)) anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'AnyTourSearch3'
        || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://anytoour.ru')) {
        anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);

    session_name('ANYTOUR_ANDROMEDA_SEARCH3');
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
    if (!session_start()) anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],503);
    $session = session_id(); session_write_close();

    try {
        $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($request) || ($request['action'] ?? null) !== 'quote') throw new InvalidArgumentException();
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
        require_once $root . (is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        $pdo = v2_data_db();
        $saved = anytour_andromeda_search3_catalog($config, $request);
        $saved['excluded_operator_ids'] = $config['excluded_operator_ids'] ?? [];
        anytour_andromeda_search3_params($request, $pdo, $saved);
        $data = anytour_andromeda_quote_run($request, $pdo, $saved, $config, $session);
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    } catch (OverflowException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429);
    } catch (DomainException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'quote_not_available'],422);
    } catch (InvalidArgumentException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    } catch (Throwable $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],502); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) anytour_andromeda_quote_http();

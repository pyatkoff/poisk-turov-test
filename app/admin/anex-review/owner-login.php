<?php
declare(strict_types=1);
require_once __DIR__ . '/owner-auth.php';

/** HTTP boundary for the separately approved AnyTour owner account. */
final class AnexReviewOwnerLogin
{
    public const ROUTE = '/_preview/search3-anex-candidate/anex-owner-login.php';
    public const PANEL = '/_preview/search3-anex-candidate/anex-hotel-review.php';
    public const COOKIE = 'ANYTOUR_REVIEW_OWNER';
    private AnexReviewOwnerAuth $auth;
    private $pdoFactory;

    public function __construct(string $configFile, string $documentRoot)
    {
        $root = realpath($documentRoot);
        $file = realpath($configFile);
        if (!$root || !$file || $file !== $configFile || is_link($file) || !is_file($file)
            || ($file === $root || strpos($file, $root . '/') === 0)
            || (fileperms($file) & 0777) !== 0600 || (lstat($file)['nlink'] ?? 0) !== 1) throw new RuntimeException('owner_config_unavailable', 503);
        // Validate the containing directory BEFORE executing its configuration.
        $this->auth = new AnexReviewOwnerAuth(dirname($file), $root);
        // This fixed, private installer-owned file is never selected by HTTP input.
        $config = require $file;
        if (!is_array($config) || !is_string($config['private_directory'] ?? null)
            || $config['private_directory'] !== dirname($file)
            || !is_callable($config['pdo_factory'] ?? null)) throw new RuntimeException('owner_config_unavailable', 503);
        $this->pdoFactory = $config['pdo_factory'];
        $sessions = $config['private_directory'] . '/sessions';
        if (realpath($sessions) !== $sessions || is_link($sessions) || !is_dir($sessions)
            || (fileperms($sessions) & 0777) !== 0700) throw new RuntimeException('owner_sessions_unavailable', 503);
        if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['HTTPS'] ?? '') !== '1') {
            throw new RuntimeException('owner_https_required', 403);
        }
        if (session_status() !== PHP_SESSION_NONE) throw new RuntimeException('owner_session_collision', 503);
        foreach (['session.use_strict_mode'=>'1','session.use_only_cookies'=>'1','session.use_trans_sid'=>'0',
            'session.gc_maxlifetime'=>'28800','session.save_handler'=>'files'] as $key=>$value) {
            if (ini_set($key, $value) === false || ini_get($key) !== $value) throw new RuntimeException('owner_session_settings', 503);
        }
        if (session_name(self::COOKIE) === false || session_save_path($sessions) === false
            || session_name() !== self::COOKIE || session_save_path() !== $sessions
            || !session_set_cookie_params(['lifetime'=>0, 'path'=>'/_preview/search3-anex-candidate/',
                'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict'])) throw new RuntimeException('owner_session_settings', 503);
        if (!session_start()) throw new RuntimeException('owner_sessions_unavailable', 503);
        if (!is_string($_SESSION['owner_csrf'] ?? null)) $_SESSION['owner_csrf'] = bin2hex(random_bytes(32));
    }

    public function csrf(): string { return $_SESSION['owner_csrf']; }

    public function context(): array
    {
        if (!is_array($_SESSION['owner_claim'] ?? null)) throw new RuntimeException('owner_session_invalid', 403);
        try { $context = $this->auth->principal($_SESSION['owner_claim']); }
        catch (Throwable $e) { throw new RuntimeException('owner_session_invalid', 403); }
        $context['pdo_factory'] = $this->pdoFactory;
        return $context;
    }

    public function post(array $server, array $input): string
    {
        if (($server['REQUEST_METHOD'] ?? '') !== 'POST' || ($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site'
            || (isset($server['HTTP_ORIGIN']) && $server['HTTP_ORIGIN'] !== 'https://anytoour.ru')
            || !is_string($input['csrf'] ?? null) || !hash_equals($this->csrf(), $input['csrf'])) {
            throw new RuntimeException('owner_csrf_invalid', 403);
        }
        $action = $input['action'] ?? null;
        if ($action === 'logout') {
            $_SESSION = [];
            if (!session_destroy()) throw new RuntimeException('owner_logout_failed', 503);
            setcookie(self::COOKIE, '', ['expires'=>time()-3600, 'path'=>'/_preview/search3-anex-candidate/',
                'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
            return self::ROUTE;
        }
        if (!in_array($action, ['login','enroll'], true) || !is_string($input['password'] ?? null)) {
            throw new RuntimeException('owner_credentials_invalid', 403);
        }
        if ($action === 'enroll' && (!is_string($input['token'] ?? null)
            || !is_string($input['confirmation'] ?? null) || !hash_equals($input['password'], $input['confirmation']))) {
            throw new RuntimeException('owner_credentials_invalid', 403);
        }
        $claim = $action === 'enroll' ? $this->auth->enroll($input['token'], $input['password']) : $this->auth->login($input['password']);
        if (!session_regenerate_id(true)) throw new RuntimeException('owner_sessions_unavailable', 503);
        $_SESSION = ['owner_claim'=>$claim, 'owner_csrf'=>bin2hex(random_bytes(32))];
        return self::PANEL;
    }
}

function anex_owner_private_config(string $entryDirectory): string
{
    if (defined('ANYTOUR_ANEX_OWNER_CONFIG')) return (string)constant('ANYTOUR_ANEX_OWNER_CONFIG');
    // Only the scoped deployed layout resolves this installer-created file.
    return dirname($entryDirectory, 4) . '/.anytoour-anex/review-owner/config.php';
}

function anex_owner_login_html(string $csrf, string $nonce, string $error = '', bool $authenticated = false, string $enrollmentToken = ''): string
{
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вход владельца — AnyTour</title><style nonce="'.$e($nonce).'">body{margin:0;background:#f4f6f8;color:#15283c;font:17px/1.5 system-ui,sans-serif}main{max-width:440px;margin:5vh auto;padding:24px;box-sizing:border-box}h1{font-size:28px}form{display:grid;gap:14px}label{display:grid;gap:6px}input,button{font:inherit;padding:12px;border:1px solid #9babb9;border-radius:8px;min-width:0;box-sizing:border-box;width:100%}button{background:#174a7b;color:white;cursor:pointer}a{color:#174a7b}p{overflow-wrap:anywhere}.error{color:#9e2424}[hidden]{display:none!important}</style></head><body><main><h1>Вход владельца</h1>';
    if ($error !== '') $html .= '<p class="error" role="alert">'.$e($error).'</p>';
    $csrfField = '<input type="hidden" name="csrf" value="'.$e($csrf).'">';
    if ($authenticated) {
        return $html.'<p><a href="'.AnexReviewOwnerLogin::PANEL.'">Открыть проверку отелей</a></p><form method="post">'.$csrfField.'<input type="hidden" name="action" value="logout"><button>Выйти</button></form></main></body></html>';
    }
    $enrolling = (bool)preg_match('/\A[0-9a-f]{64}\z/D', $enrollmentToken);
    $html .= '<p id="intro">'.($enrolling ? 'Одноразовая активация доступа владельца.' : 'Введите пароль для просмотра сохранённых отелей.').'</p><form method="post" id="login"'.($enrolling?' hidden':'').'>'.$csrfField.'<input type="hidden" name="action" value="login"><label>Пароль<input type="password" name="password" autocomplete="current-password" required minlength="12" maxlength="128"></label><button>Войти</button></form>';
    $html .= '<form method="post" id="enroll"'.($enrolling?'':' hidden').'>'.$csrfField.'<input type="hidden" name="action" value="enroll"><input type="hidden" name="token" id="setup-token" value="'.($enrolling?$e($enrollmentToken):'').'"><label>Новый пароль<input type="password" name="password" autocomplete="new-password" required minlength="12" maxlength="128"></label><label>Повторите пароль<input type="password" name="confirmation" autocomplete="new-password" required minlength="12" maxlength="128"></label><p>Не менее 12 символов. Сохраните пароль в своём менеджере паролей.</p><button>Создать вход</button></form><noscript>Для одноразовой активации включите JavaScript. Обычный вход работает без него.</noscript>';
    // Fragment is never sent in the GET URL, Referer or analytics. It is consumed only by an explicit POST.
    $html .= '<script nonce="'.$e($nonce).'">(()=>{const t=location.hash.slice(1);if(t){history.replaceState(null,"",location.pathname);if(/^[0-9a-f]{64}$/.test(t)){document.getElementById("setup-token").value=t;document.getElementById("login").hidden=true;document.getElementById("enroll").hidden=false;document.getElementById("intro").textContent="Одноразовая активация доступа владельца.";}}})();</script></main></body></html>';
    return $html;
}

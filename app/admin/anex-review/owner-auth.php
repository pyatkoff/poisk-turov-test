<?php
declare(strict_types=1);

/** One private owner account. No HTTP, cookies, application DB or supplier effects. */
final class AnexReviewOwnerAuth
{
    private string $directory;
    private $clock;

    public function __construct(string $directory, string $documentRoot, ?callable $clock = null)
    {
        $root = realpath($documentRoot);
        $real = realpath($directory);
        if ($root === false || !is_dir($root) || $root === '/' || $real === false || $directory !== $real
            || $real === $root || strncmp($real, $root . '/', strlen($root) + 1) === 0
            || !is_dir($real) || (fileperms($real) & 0777) !== 0700) {
            throw new RuntimeException('owner_private_directory_invalid');
        }
        // Reject symlinks even where realpath happens to resolve the same target.
        $part = '';
        foreach (explode('/', ltrim($directory, '/')) as $segment) {
            $part .= '/' . $segment;
            if (is_link($part)) throw new RuntimeException('owner_private_directory_invalid');
        }
        $this->directory = $real;
        $this->clock = $clock ?? static function (): int { return time(); };
    }

    public static function bootstrap(string $directory, string $documentRoot, string $token,
        int $expiresAt, ?callable $clock = null): void
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $token)) throw new RuntimeException('owner_bootstrap_invalid');
        self::bootstrapHash($directory, $documentRoot, hash('sha256', $token), $expiresAt, $clock);
    }

    public static function bootstrapHash(string $directory, string $documentRoot, string $tokenHash,
        int $expiresAt, ?callable $clock = null): void
    {
        $self = new self($directory, $documentRoot, $clock);
        $now = $self->now();
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $tokenHash) || $expiresAt <= $now || $expiresAt > $now + 3600) {
            throw new RuntimeException('owner_bootstrap_invalid');
        }
        $path = $directory . '/owner.lock';
        $old = umask(0077);
        try { $lock = @fopen($path, 'x+b'); } finally { umask($old); }
        if ($lock === false) throw new RuntimeException('owner_bootstrap_exists');
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('owner_lock_unavailable');
            if (file_exists($directory . '/owner.json') || is_link($directory . '/owner.json')) {
                throw new RuntimeException('owner_bootstrap_exists');
            }
            $self->save(['schema' => 1, 'actor' => 'owner:' . bin2hex(random_bytes(16)),
                'credential_version' => bin2hex(random_bytes(16)), 'password_hash' => null,
                'setup_hash' => $tokenHash, 'setup_expires_at' => $expiresAt,
                'failed_count' => 0, 'failed_since' => 0]);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function enroll(string $token, string $password): array
    {
        return $this->credentials($token, $password, true);
    }

    public function login(string $password): array
    {
        return $this->credentials('', $password, false);
    }

    private function now(): int
    {
        $now = ($this->clock)();
        if (!is_int($now) || $now < 1) throw new RuntimeException('owner_clock_invalid');
        return $now;
    }

    private function file(string $name): string
    {
        $path = $this->directory . '/' . $name;
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (is_link($path) || !is_file($path) || (fileperms($path) & 0777) !== 0600
            || !is_array($stat) || $stat['nlink'] !== 1
            || filesize($path) > 16384) throw new RuntimeException('owner_private_state_invalid');
        return $path;
    }

    private function read(): array
    {
        $raw = file_get_contents($this->file('owner.json'));
        $state = is_string($raw) ? json_decode($raw, true, 8) : null;
        if (!is_array($state) || ($state['schema'] ?? null) !== 1
            || !is_string($state['actor'] ?? null) || !preg_match('/\Aowner:[0-9a-f]{32}\z/D', $state['actor'])
            || !is_string($state['credential_version'] ?? null)
            || !preg_match('/\A[0-9a-f]{32}\z/D', $state['credential_version'])
            || !array_key_exists('password_hash', $state) || !array_key_exists('setup_hash', $state)
            || !is_int($state['setup_expires_at'] ?? null)
            || !is_int($state['failed_count'] ?? null) || $state['failed_count'] < 0 || $state['failed_count'] > 5
            || !is_int($state['failed_since'] ?? null) || $state['failed_since'] < 0) {
            throw new RuntimeException('owner_private_state_invalid');
        }
        if ($state['password_hash'] === null) {
            if (!is_string($state['setup_hash']) || !preg_match('/\A[0-9a-f]{64}\z/D', $state['setup_hash'])) {
                throw new RuntimeException('owner_private_state_invalid');
            }
        } elseif (!is_string($state['password_hash']) || strlen($state['password_hash']) > 255
            || (password_get_info($state['password_hash'])['algoName'] ?? 'unknown') === 'unknown'
            || $state['setup_hash'] !== null || $state['setup_expires_at'] !== 0) {
            throw new RuntimeException('owner_private_state_invalid');
        }
        return $state;
    }

    private function save(array $state): void
    {
        $old = umask(0077);
        try { $temporary = tempnam($this->directory, '.owner-'); } finally { umask($old); }
        if ($temporary === false) throw new RuntimeException('owner_state_write_failed');
        try {
            $raw = json_encode($state, JSON_THROW_ON_ERROR);
            if (!chmod($temporary, 0600) || file_put_contents($temporary, $raw) !== strlen($raw)
                || !rename($temporary, $this->directory . '/owner.json')) {
                throw new RuntimeException('owner_state_write_failed');
            }
        } finally { if (file_exists($temporary)) unlink($temporary); }
    }

    private function withState(callable $callback)
    {
        clearstatcache(true, $this->directory);
        if (realpath($this->directory) !== $this->directory || !is_dir($this->directory)
            || (fileperms($this->directory) & 0777) !== 0700) {
            throw new RuntimeException('owner_private_directory_invalid');
        }
        $handle = @fopen($this->file('owner.lock'), 'r+b');
        if ($handle === false) throw new RuntimeException('owner_lock_unavailable');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('owner_lock_unavailable');
            $state = $this->read();
            return $callback($state);
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function credentials(string $token, string $password, bool $enroll): array
    {
        return $this->withState(function (array $state) use ($token, $password, $enroll): array {
            $now = $this->now();
            if ($state['failed_since'] > $now) throw new RuntimeException('owner_clock_invalid');
            if ($state['failed_since'] !== 0 && $now - $state['failed_since'] >= 900) {
                $state['failed_count'] = 0;
                $state['failed_since'] = 0;
            }
            if ($state['failed_count'] >= 5) throw new RuntimeException('owner_rate_limited');
            $bounds = strlen($password) >= 12 && strlen($password) <= 128;
            $valid = $enroll
                ? ($state['password_hash'] === null && $state['setup_expires_at'] > $now
                    && preg_match('/\A[0-9a-f]{64}\z/D', $token)
                    && hash_equals((string)$state['setup_hash'], hash('sha256', $token)) && $bounds)
                : ($state['password_hash'] !== null && $bounds
                    && password_verify(self::passwordMaterial($password), $state['password_hash']));
            if (!$valid) {
                if ($state['failed_since'] === 0) $state['failed_since'] = $now;
                $state['failed_count']++;
                $this->save($state);
                throw new RuntimeException('owner_credentials_invalid');
            }
            if ($enroll) {
                $state['password_hash'] = password_hash(self::passwordMaterial($password), PASSWORD_DEFAULT);
                if (!is_string($state['password_hash'])) throw new RuntimeException('owner_state_write_failed');
                $state['setup_hash'] = null;
                $state['setup_expires_at'] = 0;
                $state['credential_version'] = bin2hex(random_bytes(16));
            }
            $state['failed_count'] = 0;
            $state['failed_since'] = 0;
            $this->save($state);
            return ['actor' => $state['actor'], 'credential_version' => $state['credential_version'],
                'issued_at' => $now, 'last_seen' => $now];
        });
    }

    private static function passwordMaterial(string $password): string
    {
        // Fixed 64 ASCII bytes prevent PASSWORD_DEFAULT/bcrypt's 72-byte truncation.
        return base64_encode(hash('sha384', $password, true));
    }

    public function principal(array &$claim): array
    {
        try {
            $principal = $this->withState(function (array $state) use ($claim): array {
                $now = $this->now();
                if ($state['password_hash'] === null || ($claim['actor'] ?? null) !== $state['actor']
                    || !is_string($claim['credential_version'] ?? null)
                    || !hash_equals($state['credential_version'], $claim['credential_version'])
                    || !is_int($claim['issued_at'] ?? null) || !is_int($claim['last_seen'] ?? null)
                    || $claim['issued_at'] > $now || $claim['last_seen'] > $now
                    || $claim['last_seen'] < $claim['issued_at']
                    || $now - $claim['issued_at'] >= 28800 || $now - $claim['last_seen'] >= 1800) {
                    throw new RuntimeException('owner_session_invalid');
                }
                return ['authenticated' => true, 'actor' => $state['actor'],
                    'expires_at' => min($claim['issued_at'] + 28800, $now + 1800),
                    'capabilities' => ['anex:review'], 'write_enabled' => false,
                    'pair_exclusions_enforced' => false];
            });
            $claim['last_seen'] = $this->now();
            return $principal;
        } catch (Throwable $error) { $claim = []; throw $error; }
    }

    public function revoke(array &$claim): void { $claim = []; }
}

<?php
declare(strict_types=1);

/** This validates a TRUSTED session adapter result, never request parameters. */
final class AnexReviewAccess
{
    public static function principal(array $session): string
    {
        $actor = $session['actor'] ?? null;
        if (!is_string($actor) || $actor === '' || strlen($actor) > 200
            || !preg_match('/\A[a-zA-Z0-9:@._-]+\z/D', $actor)
            || ($session['authenticated'] ?? false) !== true
            || !is_int($session['expires_at'] ?? null) || $session['expires_at'] <= time()
            || !in_array('anex:review', $session['capabilities'] ?? [], true)) {
            throw new RuntimeException('review_forbidden', 403);
        }
        return $actor;
    }

    public static function post(array $server, array $input, string $csrf): void
    {
        if (($server['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('method_not_allowed', 405);
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $csrf)
            || !is_string($input['csrf'] ?? null) || !hash_equals($csrf, $input['csrf'])
            || ($server['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') === 'cross-site') {
            throw new RuntimeException('review_csrf', 403);
        }
    }
}

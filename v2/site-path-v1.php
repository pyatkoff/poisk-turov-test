<?php
/**
 * Internal AnyTour route helper.
 *
 * Production keeps its canonical root-relative paths. An isolated whole-site
 * preview may opt into a path prefix without changing route semantics or
 * external contracts. The helper is intentionally idempotent so shared
 * renderers can safely normalize links that were already prefixed upstream.
 */

function v2_site_base_path(): string
{
    if (defined('V2_SITE_BASE_PATH')) {
        $base = trim((string)V2_SITE_BASE_PATH);
    } else {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = preg_match('#^(/_preview/search3-site-candidate)(?:/|$)#', $script, $match)
            ? (string)$match[1]
            : '';
    }

    if ($base === '' || $base === '/') return '';
    if ($base[0] !== '/' || str_starts_with($base, '//') || preg_match('/[?#\x00-\x1F\x7F]/', $base)) {
        throw new InvalidArgumentException('Invalid AnyTour site base path');
    }
    return '/' . trim($base, '/');
}

function v2_site_preview_mode(): bool
{
    return v2_site_base_path() !== '';
}

function v2_site_href(string $href): string
{
    $href = trim($href);
    if ($href === '' || $href[0] !== '/' || str_starts_with($href, '//')) return $href;

    $base = v2_site_base_path();
    if ($base === '') return $href;
    if ($href === $base || str_starts_with($href, $base . '/') || str_starts_with($href, $base . '?') || str_starts_with($href, $base . '#')) {
        return $href;
    }
    return $base . ($href === '/' ? '/' : $href);
}

/**
 * Preview-only safety net for shared content pages. It rewrites only first-party
 * root-relative navigation/form targets; scripts, images, canonical URLs and
 * external links are deliberately untouched.
 */
function v2_site_rewrite_preview_navigation(string $html): string
{
    if (!v2_site_preview_mode() || $html === '') return $html;
    $rewritten = preg_replace_callback(
        '/\b(href|action)=("|\')(\/(?!\/)[^"\']*)(\2)/i',
        static function (array $match): string {
            return $match[1] . '=' . $match[2] . v2_site_href($match[3]) . $match[4];
        },
        $html
    );
    return is_string($rewritten) ? $rewritten : $html;
}

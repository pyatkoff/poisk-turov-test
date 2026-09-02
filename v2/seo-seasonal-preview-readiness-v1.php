<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-review-page-v1.php';

/**
 * Structural/content readiness diagnostics for review-only seasonal previews.
 * A score of 100 means the preview is internally coherent for review only.
 * It never enables publication, indexation, sitemap inclusion or route launch.
 */
function v2_seo_seasonal_preview_readiness(?int $nowEpoch = null): array
{
    $catalog = v2_seo_seasonal_preview_catalog();
    $records = v2_seo_seasonal_review_content_prototypes();
    $rendererSource = (string) @file_get_contents(__DIR__ . '/seo-seasonal-review-page-v1.php');
    $integrity = function_exists('v2_seo_seasonal_preview_integrity')
        ? v2_seo_seasonal_preview_integrity(__DIR__)
        : ['state' => 'blocked', 'blocked' => [['code' => 'preview_integrity_contract_missing']]];

    $integrityBlockedByKey = [];
    foreach (($integrity['blocked'] ?? []) as $blocked) {
        if (!is_array($blocked)) {
            continue;
        }
        $key = trim((string) ($blocked['key'] ?? ''));
        if ($key !== '') {
            $integrityBlockedByKey[$key][] = (string) ($blocked['code'] ?? 'integrity_blocked');
        }
    }

    $pages = [];
    $totalScore = 0;
    $allReviewReady = $catalog !== [] && ($integrity['state'] ?? '') === 'review_ready';

    foreach ($catalog as $previewKey => $preview) {
        $errors = [];
        $dimensions = [];
        $contentKey = trim((string) ($preview['content_key'] ?? ''));
        $record = $records[$contentKey] ?? null;
        $path = (string) ($preview['path'] ?? '');
        $searchState = is_array($preview['search_state'] ?? null) ? $preview['search_state'] : [];

        $identityOk = is_array($record)
            && str_starts_with($path, '/_preview/seo2/seasonal/')
            && (int) ($record['country_id'] ?? 0) === (int) ($searchState['country'] ?? 0)
            && !isset($integrityBlockedByKey[(string) $previewKey]);

        if ($identityOk && ($record['region_id'] ?? null) !== null) {
            $identityOk = (int) $record['region_id'] === (int) ($searchState['region'] ?? 0);
        } elseif ($identityOk && array_key_exists('region', $searchState)) {
            $identityOk = false;
        }
        $dimensions['identity_integrity'] = $identityOk;
        if (!$identityOk) {
            $errors[] = 'identity_integrity';
        }

        $content = null;
        if (is_array($record)) {
            try {
                $content = v2_seo_seasonal_render_review_content($record, $nowEpoch);
            } catch (Throwable $e) {
                $content = null;
            }
        }

        $claims = is_array($content['claims'] ?? null) ? $content['claims'] : [];
        $sourcedOk = ($content['state'] ?? '') === 'rendered_review_only_seasonal_content' && $claims !== [];
        if ($sourcedOk) {
            foreach ($claims as $claim) {
                if (!is_array($claim)
                    || trim((string) ($claim['source_id'] ?? '')) === ''
                    || trim((string) ($claim['source_url'] ?? '')) === '') {
                    $sourcedOk = false;
                    break;
                }
            }
        }
        $dimensions['sourced_content'] = $sourcedOk;
        if (!$sourcedOk) {
            $errors[] = 'sourced_content';
        }

        $handoffOk = $identityOk && (int) ($searchState['country'] ?? 0) > 0;
        $dimensions['search_handoff_integrity'] = $handoffOk;
        if (!$handoffOk) {
            $errors[] = 'search_handoff_integrity';
        }

        $boundaryOk = is_array($record)
            && ($integrity['publication_allowed'] ?? true) === false
            && ($integrity['indexation_allowed'] ?? true) === false
            && ($integrity['sitemap_allowed'] ?? true) === false
            && ($integrity['canonical_allowed'] ?? true) === false
            && ($integrity['route_launch_allowed'] ?? true) === false;

        if ($boundaryOk) {
            foreach (['publication_allowed', 'indexation_allowed', 'sitemap_allowed', 'route_creation_allowed'] as $flag) {
                if (($record[$flag] ?? true) !== false) {
                    $boundaryOk = false;
                    break;
                }
                if ($content !== null && ($content[$flag] ?? true) !== false) {
                    $boundaryOk = false;
                    break;
                }
            }
        }
        if ($content !== null && ($content['publication_candidates'] ?? null) !== []) {
            $boundaryOk = false;
        }
        $dimensions['publication_boundary'] = $boundaryOk;
        if (!$boundaryOk) {
            $errors[] = 'publication_boundary';
        }

        $routeFile = __DIR__ . '/' . trim($path, '/') . '/index.php';
        $routeSource = is_file($routeFile) ? (string) @file_get_contents($routeFile) : '';
        $routeOk = ($integrity['state'] ?? '') === 'review_ready'
            && $routeSource !== ''
            && str_contains($routeSource, "v2_seo_render_seasonal_preview('" . (string) $previewKey . "')")
            && $rendererSource !== ''
            && !str_contains($rendererSource, 'rel="canonical"')
            && !str_contains($rendererSource, 'property="og:url"')
            && str_contains($rendererSource, 'content="noindex,follow"')
            && v2_seo_seasonal_preview_headers() === ['X-Robots-Tag: noindex, follow'];
        $dimensions['route_and_head_integrity'] = $routeOk;
        if (!$routeOk) {
            $errors[] = 'route_and_head_integrity';
        }

        $passed = count(array_filter($dimensions, static fn($value): bool => $value === true));
        $score = $passed * 20;
        $reviewReady = $score === 100;
        $totalScore += $score;
        if (!$reviewReady) {
            $allReviewReady = false;
        }

        $pages[(string) $previewKey] = [
            'path' => $path,
            'page_key' => is_array($record) ? (string) ($record['page_key'] ?? '') : '',
            'score' => $score,
            'review_ready' => $reviewReady,
            'dimensions' => $dimensions,
            'errors' => array_values(array_unique($errors)),
            'publication_allowed' => false,
            'indexation_allowed' => false,
            'sitemap_allowed' => false,
        ];
    }

    $count = count($pages);
    return [
        'state' => $count > 0 && $allReviewReady ? 'review_ready' : 'blocked',
        'preview_count' => $count,
        'average_score' => $count > 0 ? (int) round($totalScore / $count) : 0,
        'all_review_ready' => $count > 0 && $allReviewReady,
        'pages' => $pages,
        'publication_candidates' => [],
        'publication_allowed' => false,
        'indexation_allowed' => false,
        'sitemap_allowed' => false,
        'explicit_launch_approval_required' => true,
    ];
}

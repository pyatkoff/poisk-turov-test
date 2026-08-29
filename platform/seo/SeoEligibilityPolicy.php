<?php

declare(strict_types=1);

namespace AnyTour\Platform\Seo;

use InvalidArgumentException;

final class SeoEligibilityPolicy
{
    /** @param array<string,array<string,mixed>> $pageTypes */
    public function __construct(private readonly array $pageTypes)
    {
    }

    /** @param array<string,mixed> $page @return array{eligible:bool,checks:array<string,string>} */
    public function evaluate(array $page): array
    {
        $pageType = (string) $page['page_type'];
        if (!isset($this->pageTypes[$pageType])) {
            throw new InvalidArgumentException('Unknown SEO page type: ' . $pageType);
        }

        $rule = $this->pageTypes[$pageType];
        $checks = [];
        $checks['roles'] = $this->containsAll($page['roles'], $rule['required_roles']) ? 'pass' : 'fail';
        $checks['blocks'] = $this->containsAll($page['blocks'], $rule['required_blocks']) ? 'pass' : 'fail';
        $checks['quality_score'] = $page['quality_score'] >= (int) $rule['min_quality_score'] ? 'pass' : 'fail';
        $checks['inventory'] = !(bool) $rule['requires_inventory'] || $page['inventory_available'] ? 'pass' : 'fail';
        $checks['canonical_unique'] = $page['canonical_unique'] ? 'pass' : 'fail';
        $checks['http'] = $page['http_ok'] ? 'pass' : 'fail';
        $checks['schema'] = $page['schema_ok'] ? 'pass' : 'fail';
        $checks['breadcrumbs'] = $page['breadcrumbs_ok'] ? 'pass' : 'fail';
        $checks['internal_links'] = $page['internal_links_ok'] ? 'pass' : 'fail';

        return ['eligible' => !in_array('fail', $checks, true), 'checks' => $checks];
    }

    /** @param list<string> $actual @param list<string> $required */
    private function containsAll(array $actual, array $required): bool
    {
        return array_diff($required, $actual) === [];
    }
}

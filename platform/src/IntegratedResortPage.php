<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use AnyTour\Platform\Seo\MetadataResolver;
use AnyTour\Platform\Seo\SeoEligibilityPolicy;
use RuntimeException;

final class IntegratedResortPage
{
    /** @param array<string,array<string,mixed>> $pageTypes @param array<string,array<string,string>> $metadataTemplates */
    public function __construct(
        private readonly PageAssembler $assembler,
        private readonly SeoPageRepository $seoPages,
        private readonly MetadataResolver $metadata,
        private readonly SeoEligibilityPolicy $eligibility,
        private readonly array $pageTypes,
        private readonly array $metadataTemplates,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(string $entityKey, bool $inventoryAvailable = true): array
    {
        $page = $this->assembler->resort($entityKey);
        $resort = $page['entity'];
        $country = $page['country'];
        $registry = $this->seoPages->findByRouteKey($entityKey);
        if ($registry === null || ($registry['page_type'] ?? null) !== 'resort') {
            throw new RuntimeException('SEO registry resort page not found: ' . $entityKey);
        }

        $blocks = array_values(array_map(static fn (array $block): string => (string) $block['key'], $page['blocks']));
        $resortData = is_array($resort['data'] ?? null) ? $resort['data'] : [];
        $countryData = is_array($country['data'] ?? null) ? $country['data'] : [];
        $variables = [
            'resort_name' => (string) $resort['name'],
            'resort_name_accusative' => (string) ($resortData['name_accusative'] ?? $resort['name']),
            'resort_name_prepositional' => (string) ($resortData['name_prepositional'] ?? $resort['name']),
            'resort_slug' => (string) $resort['slug'],
            'country_name' => (string) $country['name'],
            'country_name_prepositional' => (string) ($countryData['name_prepositional'] ?? $country['name']),
            'country_slug' => (string) $country['slug'],
            'year' => date('Y'),
        ];

        $manual = [
            'title' => $registry['manual_title'] ?: ($page['overrides']['seo_title'] ?? null),
            'description' => $registry['manual_description'] ?: ($page['overrides']['seo_description'] ?? null),
            'h1' => $registry['manual_h1'] ?: ($page['overrides']['seo_h1'] ?? null),
            'canonical' => $registry['manual_canonical_path'] ?: ($page['overrides']['seo_canonical'] ?? null),
        ];

        $meta = $this->metadata->resolve($this->metadataTemplates['resort'], $variables, $manual);
        if (($registry['canonical_path'] ?? '') !== '' && ($manual['canonical'] ?? null) === null) {
            $meta['canonical'] = (string) $registry['canonical_path'];
        }

        $eligibility = $this->eligibility->evaluate([
            'page_type' => 'resort',
            'roles' => ['country', 'resort'],
            'blocks' => $blocks,
            'quality_score' => (int) $registry['quality_score'],
            'inventory_available' => $inventoryAvailable,
            'canonical_unique' => true,
            'http_ok' => true,
            'schema_ok' => true,
            'breadcrumbs_ok' => true,
            'internal_links_ok' => true,
        ]);

        $tourvisorCountryId = (string) ($page['search_intent']['tourvisor_country_id'] ?? '');
        $searchUrl = '/poisk-turov/';
        if ($tourvisorCountryId !== '') {
            $searchUrl .= '?' . http_build_query(['country' => $tourvisorCountryId], '', '&', PHP_QUERY_RFC3986);
        }

        return $page + [
            'seo' => [
                'registry' => $registry,
                'metadata' => $meta,
                'eligible' => $eligibility['eligible'],
                'checks' => $eligibility['checks'],
                'index_status' => (string) $registry['index_status'],
                'robots' => ($registry['index_status'] === 'index' && $eligibility['eligible']) ? 'index,follow' : 'noindex,follow',
            ],
            'search_url' => $searchUrl,
        ];
    }
}

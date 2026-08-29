<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use AnyTour\Platform\Seo\MetadataResolver;
use AnyTour\Platform\Seo\SeoEligibilityPolicy;

final class IntegratedCountryPage
{
    /** @param array<string,array<string,mixed>> $pageTypes @param array<string,array<string,string>> $metadataTemplates */
    public function __construct(
        private readonly PageAssembler $assembler,
        private readonly MetadataResolver $metadata,
        private readonly SeoEligibilityPolicy $eligibility,
        private readonly array $pageTypes,
        private readonly array $metadataTemplates,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(string $entityKey, int $qualityScore = 90, bool $inventoryAvailable = true): array
    {
        $page = $this->assembler->country($entityKey);
        $entity = $page['entity'];
        $blocks = array_values(array_map(static fn (array $block): string => (string) $block['key'], $page['blocks']));
        $data = is_array($entity['data'] ?? null) ? $entity['data'] : [];

        $variables = [
            'country_name' => (string) $entity['name'],
            'country_name_prepositional' => (string) ($data['name_prepositional'] ?? $entity['name']),
            'country_slug' => (string) $entity['slug'],
            'year' => date('Y'),
        ];

        $manual = [
            'title' => $page['overrides']['seo_title'] ?? null,
            'description' => $page['overrides']['seo_description'] ?? null,
            'h1' => $page['overrides']['seo_h1'] ?? null,
            'canonical' => $page['overrides']['seo_canonical'] ?? null,
        ];

        $meta = $this->metadata->resolve($this->metadataTemplates['country'], $variables, $manual);
        $eligibility = $this->eligibility->evaluate([
            'page_type' => 'country',
            'roles' => ['country'],
            'blocks' => $blocks,
            'quality_score' => $qualityScore,
            'inventory_available' => $inventoryAvailable,
            'canonical_unique' => true,
            'http_ok' => true,
            'schema_ok' => true,
            'breadcrumbs_ok' => true,
            'internal_links_ok' => count($page['children']['resorts'] ?? []) > 0,
        ]);

        $tourvisorCountryId = (string) ($page['search_intent']['tourvisor_country_id'] ?? '');
        $searchUrl = '/poisk-turov/';
        if ($tourvisorCountryId !== '') {
            $searchUrl .= '?' . http_build_query(['country' => $tourvisorCountryId], '', '&', PHP_QUERY_RFC3986);
        }

        return $page + [
            'seo' => [
                'metadata' => $meta,
                'eligible' => $eligibility['eligible'],
                'checks' => $eligibility['checks'],
                'default_index_status' => 'noindex',
            ],
            'search_url' => $searchUrl,
        ];
    }
}

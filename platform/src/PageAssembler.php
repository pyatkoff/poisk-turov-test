<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use RuntimeException;

final class PageAssembler
{
    public function __construct(
        private readonly EntityRepository $entities,
        private readonly ContentRepository $content,
    ) {
    }

    /** @return array<string,mixed> */
    public function country(string $entityKey): array
    {
        $country = $this->entities->findByKey($entityKey);
        if ($country === null || $country['entity_type'] !== 'country' || $country['status'] !== 'active') {
            throw new RuntimeException('Active country not found: ' . $entityKey);
        }

        $countryId = (int) $country['id'];

        return [
            'page_type' => 'country',
            'entity' => $country,
            'external_ids' => $this->entities->externalIds($countryId),
            'children' => [
                'resorts' => $this->entities->childrenOf($countryId, 'resort'),
            ],
            'blocks' => $this->content->blocks('entity', $countryId),
            'overrides' => $this->content->overrides('entity', $countryId),
            'search_intent' => [
                'country_slug' => (string) $country['slug'],
                'tourvisor_country_id' => $this->entities->externalIds($countryId)['tourvisor:country'] ?? null,
            ],
        ];
    }
}

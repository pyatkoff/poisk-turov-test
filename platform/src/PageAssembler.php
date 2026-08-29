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
        $externalIds = $this->entities->externalIds($countryId);

        return [
            'page_type' => 'country',
            'entity' => $country,
            'external_ids' => $externalIds,
            'children' => [
                'resorts' => $this->entities->childrenOf($countryId, 'resort'),
            ],
            'blocks' => $this->content->blocks('entity', $countryId),
            'overrides' => $this->content->overrides('entity', $countryId),
            'search_intent' => [
                'country_slug' => (string) $country['slug'],
                'tourvisor_country_id' => $externalIds['tourvisor:country'] ?? null,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function resort(string $entityKey): array
    {
        $resort = $this->entities->findByKey($entityKey);
        if ($resort === null || $resort['entity_type'] !== 'resort' || $resort['status'] !== 'active') {
            throw new RuntimeException('Active resort not found: ' . $entityKey);
        }
        if ($resort['parent_id'] === null) {
            throw new RuntimeException('Resort has no parent country: ' . $entityKey);
        }

        $country = $this->entities->findById((int) $resort['parent_id']);
        if ($country === null || $country['entity_type'] !== 'country' || $country['status'] !== 'active') {
            throw new RuntimeException('Active parent country not found for resort: ' . $entityKey);
        }

        $resortId = (int) $resort['id'];
        $countryId = (int) $country['id'];
        $countryExternalIds = $this->entities->externalIds($countryId);
        $resortExternalIds = $this->entities->externalIds($resortId);

        return [
            'page_type' => 'resort',
            'entity' => $resort,
            'country' => $country,
            'external_ids' => $resortExternalIds,
            'children' => [
                'hotels' => $this->entities->childrenOf($resortId, 'hotel'),
            ],
            'blocks' => $this->content->blocks('entity', $resortId),
            'overrides' => $this->content->overrides('entity', $resortId),
            'search_intent' => [
                'country_slug' => (string) $country['slug'],
                'resort_slug' => (string) $resort['slug'],
                'tourvisor_country_id' => $countryExternalIds['tourvisor:country'] ?? null,
                'tourvisor_resort_id' => $resortExternalIds['tourvisor:resort'] ?? null,
            ],
        ];
    }
}

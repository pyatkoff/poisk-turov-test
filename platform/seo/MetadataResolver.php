<?php

declare(strict_types=1);

namespace AnyTour\Platform\Seo;

final class MetadataResolver
{
    /**
     * @param array{title?:string,description?:string,h1?:string,canonical?:string} $templates
     * @param array<string,string|int|float|null> $variables
     * @param array{title?:?string,description?:?string,h1?:?string,canonical?:?string} $manual
     * @return array{title:string,description:string,h1:string,canonical:string}
     */
    public function resolve(array $templates, array $variables, array $manual = []): array
    {
        $resolved = [];
        foreach (['title', 'description', 'h1', 'canonical'] as $field) {
            $override = trim((string) ($manual[$field] ?? ''));
            if ($override !== '') {
                $resolved[$field] = $override;
                continue;
            }

            $resolved[$field] = $this->interpolate((string) ($templates[$field] ?? ''), $variables);
        }

        return $resolved;
    }

    /** @param array<string,string|int|float|null> $variables */
    private function interpolate(string $template, array $variables): string
    {
        if ($template === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            static function (array $match) use ($variables): string {
                $key = $match[1];
                if (!array_key_exists($key, $variables) || $variables[$key] === null) {
                    return '';
                }
                return trim((string) $variables[$key]);
            },
            $template
        ) ?? $template;
    }
}

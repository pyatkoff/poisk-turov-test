<?php

declare(strict_types=1);

namespace AnyTour\Platform\Seo;

final class MetadataResolver
{
    /** @param array{title?:string,description?:string,h1?:string,canonical?:string} $templates @param array<string,string|int|float|null> $variables @param array{title?:?string,description?:?string,h1?:?string,canonical?:?string} $manual @return array{title:string,description:string,h1:string,canonical:string} */
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
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($variables): string {
            return array_key_exists($m[1], $variables) && $variables[$m[1]] !== null ? trim((string) $variables[$m[1]]) : '';
        }, $template) ?? $template;
    }
}

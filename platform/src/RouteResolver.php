<?php

declare(strict_types=1);

namespace AnyTour\Platform;

final class RouteResolver
{
    /** @return array{page_type:string,route_key:string}|null */
    public function resolve(string $requestPath): ?array
    {
        $path = '/' . trim((string) parse_url($requestPath, PHP_URL_PATH), '/') . '/';
        if (!preg_match('#^/country/([a-z0-9-]+)/$#', $path, $match)) {
            return null;
        }

        return [
            'page_type' => 'country',
            'route_key' => 'country:' . $match[1],
        ];
    }
}

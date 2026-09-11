<?php
declare(strict_types=1);

final class AnyTourThreeProviderOperator
{
    public static function fromSearch(string $provider, mixed $raw): array
    {
        if (!in_array($provider, ['tourvisor', 'anex', 'andromeda'], true)
            || !is_string($raw) || trim($raw) === ''
            || (function_exists('mb_strlen') ? mb_strlen($raw, 'UTF-8') : strlen($raw)) > 120
            || preg_match('/[\x00-\x1F\x7F]/u', $raw)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OPERATOR');
        }
        $raw = trim($raw);
        if ($provider === 'anex') {
            $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $raw));
            if (!in_array($key, ['anex', 'anextour'], true)) {
                throw new InvalidArgumentException('THREE_PROVIDER_OPERATOR_ANEX');
            }
        }
        return [
            'raw' => $raw,
            'canonical_name' => $provider === 'anex' ? 'ANEX' : null,
            'canonical_verified' => $provider === 'anex',
            'identity_source' => $provider === 'anex' ? 'provider_fixed' : 'raw_label_only',
            'filter_status' => $provider === 'tourvisor' ? 'verified' : 'unsupported',
            'cross_provider_equivalence_verified' => false,
            'supplier_code_exposed' => false,
        ];
    }
}

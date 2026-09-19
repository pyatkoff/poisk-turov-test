<?php
declare(strict_types=1);

/**
 * Read-only facts from an ORIGINAL supplier reply. No client, network, pricing,
 * flight selection or persistence. A reported markup is not classified as fuel.
 */
final class AnyTourAndromedaOriginalTransportFacts
{
    private const LIMIT = 2097152;
    private const MONEY = ['markup', 'price', 'amount', 'value', 'currency', 'currencyAlias', 'currencyCode',
        'quantity', 'count', 'routeIndex', 'direction', 'unit', 'included', 'isIncluded',
        'perPerson', 'perPax', 'required', 'packet', 'common'];
    private int $nodes = 0;
    private array $rows = [];
    private array $issues = [];

    public static function fromJson(string $json, array $context): array
    {
        if (strlen($json) > self::LIMIT) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_SIZE');
        try {
            $reply = json_decode($json, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('ORIGINAL_TRANSPORT_JSON');
        }
        if (!is_array($reply) || array_is_list($reply)) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_SHAPE');
        return (new self())->inspect($reply, $context, hash('sha256', $json));
    }

    private function inspect(array $reply, array $context, string $hash): array
    {
        $ctx = [];
        foreach (['operatorKey', 'tourKey'] as $key) {
            $ctx[$key] = self::id($context[$key] ?? null);
            if ($ctx[$key] === null) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_CONTEXT');
        }
        foreach (['programKey', 'spoKey', 'departureId', 'countryId'] as $key) {
            $ctx[$key] = self::id($context[$key] ?? null);
            if (isset($context[$key]) && $ctx[$key] === null) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_CONTEXT');
        }
        $date = $context['checkIn'] ?? null;
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_CONTEXT');
        $ctx['checkIn'] = $date;
        foreach (['nights' => [1, 28], 'adult' => [1, 6], 'child' => [0, 3]] as $key => [$lo, $hi]) {
            $n = $context[$key] ?? null;
            if (!is_int($n) || $n < $lo || $n > $hi) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_CONTEXT');
            $ctx[$key] = $n;
        }
        $ctx['currency'] = self::currency($context['currency'] ?? null);
        if ($ctx['currency'] === null) throw new InvalidArgumentException('ORIGINAL_TRANSPORT_CONTEXT');
        if (array_key_exists('error', $reply)) throw new RuntimeException('ORIGINAL_TRANSPORT_SUPPLIER_ERROR');

        $bindings = [];
        foreach ($this->records($reply['claimDocument'] ?? [], 'claimDocument') as [$doc, $path]) {
            $observed = [];
            foreach (['operatorKey', 'tourKey'] as $key) {
                $observed[$key] = self::id($doc[$key] ?? null);
                if (array_key_exists($key, $doc) && ($observed[$key] === null || $observed[$key] !== $ctx[$key])) {
                    throw new RuntimeException('ORIGINAL_TRANSPORT_CONTEXT_MISMATCH');
                }
            }
            foreach (['checkIn', 'nights', 'adult', 'child'] as $key) {
                if (!array_key_exists($key, $doc)) continue;
                $v = $doc[$key];
                if ((!is_string($v) && !is_int($v)) || (string)$v !== (string)$ctx[$key]) {
                    throw new RuntimeException('ORIGINAL_TRANSPORT_CONTEXT_MISMATCH');
                }
                $observed[$key] = $v;
            }
            $bindings[] = ['path' => $path, 'reported' => $observed,
                'tourKey_matches' => $observed['tourKey'] !== null];
            $this->transports($doc, $path, 'current');
        }
        foreach ($this->records($reply['variants'] ?? [], 'variants') as [$variant, $path]) {
            $this->transports($variant, $path, 'alternative');
        }
        // Some replies expose transport directly. Do not guess its selection role.
        $this->transports($reply, '$', 'unspecified');
        return [
            'version' => 1, 'source' => 'original_supplier_reply', 'raw_reply_sha256' => $hash,
            'context' => $ctx, 'claim_bindings' => $bindings,
            'transport_count' => count($this->rows), 'transports' => $this->rows,
            'shape_issues' => $this->issues, 'shape_complete' => $this->issues === [],
            'fuel_interpretation' => 'not_established', 'arithmetic_applied' => false,
            'finalPriceReady' => false, 'supplier_calls' => 0,
        ];
    }

    /** Only known structural keys are traversed; no passenger/session fields. */
    private function transports(array $parent, string $path, string $scope): void
    {
        if (array_key_exists('transports', $parent)) {
            foreach ($this->records($parent['transports'], $path . '.transports') as [$block, $bp]) {
                if (!array_key_exists('transport', $block)) { $this->issues[] = $bp . ':missing_transport'; continue; }
                $this->transportRows($block['transport'], $bp . '.transport', $scope);
            }
        }
        if (array_key_exists('transport', $parent)) {
            $this->transportRows($parent['transport'], $path . '.transport', $scope);
        }
    }

    private function transportRows(mixed $value, string $path, string $scope): void
    {
        foreach ($this->records($value, $path) as [$transport, $tp]) {
            $details = [];
            $present = array_key_exists('details', $transport);
            if ($present) $this->details($transport['details'], $tp . '.details', $details);
            $type = $transport['type'] ?? null;
            // Diagnostic scope is all transport types, not just ttAvia.
            $this->rows[] = [
                'path' => $tp, 'scope' => $scope,
                'type' => is_string($type) && preg_match('/\Att[A-Za-z]{1,20}\z/D', $type) ? $type : null,
                'money_fields' => $this->moneyFields($transport),
                'details_state' => !$present ? 'missing' : ($transport['details'] === null ? 'null' : (is_array($transport['details']) ? 'present' : 'invalid')),
                'details' => $details,
            ];
            if (count($this->rows) > 2000) throw new RuntimeException('ORIGINAL_TRANSPORT_COMPLEXITY');
        }
    }

    private function details(mixed $value, string $path, array &$out): void
    {
        foreach ($this->records($value, $path) as [$detail, $dp]) {
            if (array_key_exists('detail', $detail)) {
                // Keep wrapper-level money separately; never inherit currency silently.
                if (array_intersect(array_keys($detail), self::MONEY) !== []) {
                    $out[] = ['path' => $dp, 'money_fields' => $this->moneyFields($detail)];
                }
                $this->details($detail['detail'], $dp . '.detail', $out);
            } else {
                $out[] = ['path' => $dp, 'money_fields' => $this->moneyFields($detail)];
            }
        }
    }

    private function moneyFields(array $record): array
    {
        $out = [];
        foreach (self::MONEY as $key) {
            if ($key !== 'markup' && !array_key_exists($key, $record)) continue;
            $out[$key] = $this->fact($record, $key, 0);
        }
        return $out;
    }

    private function fact(array $parent, string $key, int $depth): array
    {
        if (!array_key_exists($key, $parent)) return ['state' => 'missing'];
        $v = $parent[$key];
        if ($v === null) return ['state' => 'null'];
        if (is_array($v)) {
            if ($depth >= 8 || ++$this->nodes > 10000) throw new RuntimeException('ORIGINAL_TRANSPORT_COMPLEXITY');
            $children = [];
            foreach ($this->records($v, 'money') as [$row, $unused]) {
                $fields = [];
                foreach (self::MONEY as $field) if (array_key_exists($field, $row)) $fields[$field] = $this->fact($row, $field, $depth + 1);
                $children[] = $fields;
            }
            return ['state' => 'structured', 'items' => $children];
        }
        if (in_array($key, ['currency', 'currencyAlias', 'currencyCode'], true)) {
            $currency = self::currency($v);
            return $currency === null ? ['state' => 'invalid', 'type' => gettype($v)] : ['state' => 'reported', 'value' => $currency];
        }
        if (in_array($key, ['included', 'isIncluded', 'perPerson', 'perPax', 'required', 'packet', 'common'], true)) {
            return in_array($v, [true, false, 0, 1, 'true', 'false', '0', '1'], true)
                ? ['state' => 'reported', 'value' => $v] : ['state' => 'invalid', 'type' => gettype($v)];
        }
        if ($key === 'unit') {
            return is_string($v) && in_array($v, ['person', 'per_person', 'pax', 'per_pax', 'party', 'per_party', 'package', 'leg', 'per_leg', 'room', 'per_room', 'adult', 'child'], true)
                ? ['state' => 'reported', 'value' => $v] : ['state' => 'unrecognized', 'type' => gettype($v)];
        }
        if (is_int($v)) $text = (string)$v;
        elseif (is_float($v) && is_finite($v)) $text = json_encode($v, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        elseif (is_string($v)) $text = $v;
        else return ['state' => 'invalid', 'type' => gettype($v)];
        if (!preg_match('/\A-?(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $text)) return ['state' => 'invalid', 'type' => gettype($v)];
        return ['state' => preg_match('/[1-9]/', $text) ? 'reported' : 'reported_zero', 'value' => $text, 'type' => gettype($v)];
    }

    private function records(mixed $value, string $path): array
    {
        if (++$this->nodes > 10000 || strlen($path) > 600) throw new RuntimeException('ORIGINAL_TRANSPORT_COMPLEXITY');
        if (!is_array($value)) { $this->issues[] = $path . ':invalid_container'; return []; }
        if ($value === []) return [];
        if (!array_is_list($value)) return [[$value, $path]];
        $out = [];
        foreach ($value as $i => $row) {
            if (!is_array($row)) { $this->issues[] = $path . '[' . $i . ']:invalid_row'; continue; }
            $out[] = [$row, $path . '[' . $i . ']'];
        }
        return $out;
    }

    private static function id(mixed $v): ?string
    {
        if (is_int($v)) $v = (string)$v;
        return is_string($v) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $v) ? $v : null;
    }

    private static function currency(mixed $v): ?string
    {
        if (is_int($v)) $v = (string)$v;
        return is_string($v) && preg_match('/\A(?:[A-Z]{3}|[1-9][0-9]{2})\z/D', $v) ? $v : null;
    }
}

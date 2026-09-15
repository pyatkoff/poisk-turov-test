<?php
declare(strict_types=1);

/**
 * Private retained state for one ambiguous Andromeda flight choice.
 * Raw supplier UIDs/claim stay server-side; the browser sees only opaque refs.
 */
final class AnyTourAndromedaFlightSelection
{
    public static function buildState(array $claim, array $options, string $contextSha256,
        ?callable $refFactory = null): array
    {
        self::assertDigest($contextSha256);
        if (array_keys($options) !== [0, 1]) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
        }
        $items = [];
        $refs = ['0' => [], '1' => []];
        foreach (['0', '1'] as $direction) {
            if (!is_array($options[$direction]) || $options[$direction] === []) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
            }
            foreach ($options[$direction] as $index => $item) {
                self::assertItem($item, $direction);
                $ref = $refFactory === null
                    ? 'flight_' . bin2hex(random_bytes(16))
                    : $refFactory($direction, $index, $item);
                if (!is_string($ref) || preg_match('/^flight_[a-f0-9]{32}$/D', $ref) !== 1
                    || isset($items[$ref])) {
                    throw new RuntimeException('ANDROMEDA_FLIGHT_REF_INVALID');
                }
                $items[$ref] = ['direction' => $direction, 'item' => $item];
                $refs[$direction][] = $ref;
            }
        }
        return [
            'state' => [
                'version' => 1,
                'provider' => 'andromeda',
                'context_sha256' => $contextSha256,
                'claim' => $claim,
                'items' => $items,
            ],
            'refs' => $refs,
        ];
    }

    /** Resolve one outbound + one return without exposing or accepting supplier UIDs. */
    public static function select(array $state, string $contextSha256, array $selection): array
    {
        self::assertDigest($contextSha256);
        if (($state['version'] ?? null) !== 1 || ($state['provider'] ?? null) !== 'andromeda'
            || !is_string($state['context_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $state['context_sha256']) !== 1
            || !hash_equals($state['context_sha256'], $contextSha256)
            || !is_array($state['claim'] ?? null) || !is_array($state['items'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_STATE_INVALID');
        }
        $keys = array_keys($selection);
        sort($keys);
        if ($keys !== ['outbound_ref', 'provider', 'return_ref']
            || ($selection['provider'] ?? null) !== 'andromeda') {
            throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
        }
        $wanted = ['0' => $selection['outbound_ref'] ?? null, '1' => $selection['return_ref'] ?? null];
        $selected = [];
        foreach ($wanted as $direction => $ref) {
            $direction = (string)$direction;
            if (!is_string($ref) || preg_match('/^flight_[a-f0-9]{32}$/D', $ref) !== 1
                || !isset($state['items'][$ref]) || !is_array($state['items'][$ref])) {
                throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
            }
            $record = $state['items'][$ref];
            if (($record['direction'] ?? null) !== $direction || !is_array($record['item'] ?? null)) {
                throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
            }
            self::assertItem($record['item'], $direction);
            $selected[$direction] = $record['item'];
        }
        return ['claim' => $state['claim'], 'selected' => $selected];
    }

    private static function assertItem(mixed $item, string $direction): void
    {
        if (!is_array($item)
            || (string)($item['direction'] ?? '') !== $direction
            || ($item['type'] ?? null) !== 'ttAvia'
            || !is_string($item['uid'] ?? null)
            || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $item['uid']) !== 1) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
        }
    }

    private static function assertDigest(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_CONTEXT_INVALID');
        }
    }
}

<?php
declare(strict_types=1);

/**
 * Strict provider-local operator binding for MATCH COMMON4.
 * Pure evidence helper: no network, DB, mapping or runtime writes.
 */

function hm_common4_normalize_name(string $value): string
{
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower(strtr($value, [
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я',
        ]));
    }
    $value = str_replace(['&', '+'], [' and ', ' and '], $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function hm_common4_aliases(): array
{
    return [
        'anex' => ['ANEX', 'ANEX Tour', 'Анекс', 'Анекс Тур'],
        'funsun' => ['FUN&SUN', 'FUN SUN', 'FUN and SUN', 'FUN&SUN Russia', 'FUN&SUN (RU)', 'ФАН&САН', 'ФАН САН'],
        'biblio_globus' => ['Библио-Глобус', 'Библио Глобус', 'Biblio Globus', 'Biblio-Globus', 'BiblioGlobus', 'Biblioglobus'],
        'intourist' => ['Интурист', 'Intourist', 'NTK Intourist', 'НТК Интурист'],
    ];
}

function hm_common4_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['operators', 'OPERATORS', 'items', 'results'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key])) return $payload[$key];
    }
    return [];
}

function hm_common4_text(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (!isset($row[$key]) || !is_scalar($row[$key])) continue;
        $value = trim((string)$row[$key]);
        if ($value === '') continue;
        return function_exists('mb_substr') ? mb_substr($value, 0, 256, 'UTF-8') : substr($value, 0, 256);
    }
    return '';
}

function hm_common4_row_name(array $row): string
{
    return hm_common4_text($row, ['name', 'lName', 'NAME', 'operator', 'operatorName', 'fullName', 'russianName']);
}

function hm_common4_row_full_name(array $row): string
{
    return hm_common4_text($row, ['fullName', 'full_name', 'FULLNAME']);
}

function hm_common4_row_russian_name(array $row): string
{
    return hm_common4_text($row, ['russianName', 'russian_name', 'RUSSIANNAME']);
}

function hm_common4_row_id(array $row, string $namespace): ?string
{
    $keys = $namespace === 'tourvisor'
        ? ['id', 'operatorId', 'operator_id']
        : ['id', 'operatorKey', 'operatorId', 'operator_id'];
    foreach ($keys as $key) {
        if (!isset($row[$key]) || !is_scalar($row[$key])) continue;
        $value = trim((string)$row[$key]);
        if ($namespace === 'tourvisor') {
            if (preg_match('/^[1-9][0-9]{0,9}$/D', $value) === 1) return $value;
        } elseif (preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $value) === 1 && $value !== '0') {
            return $value;
        }
    }
    return null;
}

function hm_common4_resolve(array $payload, string $namespace, ?array $required = null): array
{
    if (!in_array($namespace, ['tourvisor', 'samo'], true)) {
        throw new InvalidArgumentException('unsupported_namespace');
    }
    $aliases = hm_common4_aliases();
    $required ??= array_keys($aliases);
    foreach ($required as $canonical) {
        if (!isset($aliases[$canonical])) throw new InvalidArgumentException('unknown_canonical_operator:' . $canonical);
    }

    $wanted = [];
    foreach ($aliases as $canonical => $names) {
        foreach ($names as $name) {
            $norm = hm_common4_normalize_name($name);
            if ($norm === '') throw new RuntimeException('empty_alias:' . $canonical);
            if (isset($wanted[$norm]) && $wanted[$norm] !== $canonical) throw new RuntimeException('alias_collision:' . $norm);
            $wanted[$norm] = $canonical;
        }
    }

    $hits = [];
    foreach (hm_common4_rows($payload) as $row) {
        if (!is_array($row)) continue;
        $name = hm_common4_row_name($row);
        $norm = hm_common4_normalize_name($name);
        $canonical = $wanted[$norm] ?? null;
        if ($canonical === null || !in_array($canonical, $required, true)) continue;
        $id = hm_common4_row_id($row, $namespace);
        if ($id === null) continue;
        $key = $canonical . '|' . $id;
        $hits[$canonical][$key] = [
            'canonical_operator' => $canonical,
            'namespace' => $namespace,
            'provider_operator_id' => $id,
            'provider_operator_name' => $name,
            'provider_operator_full_name' => hm_common4_row_full_name($row),
            'provider_operator_russian_name' => hm_common4_row_russian_name($row),
            'normalized_name' => $norm,
        ];
    }

    $resolved = [];
    $usedIds = [];
    foreach ($required as $canonical) {
        $candidates = array_values($hits[$canonical] ?? []);
        if (count($candidates) !== 1) throw new RuntimeException('operator_binding_' . $canonical . '_count_' . count($candidates));
        $row = $candidates[0];
        $id = $row['provider_operator_id'];
        if (isset($usedIds[$id]) && $usedIds[$id] !== $canonical) throw new RuntimeException('operator_id_collision:' . $id);
        $usedIds[$id] = $canonical;
        $resolved[$canonical] = $row;
    }
    return $resolved;
}

function hm_common4_resolve_pair(array $tourvisor, array $samo, ?array $required = null): array
{
    return [
        'tourvisor' => hm_common4_resolve($tourvisor, 'tourvisor', $required),
        'samo' => hm_common4_resolve($samo, 'samo', $required),
    ];
}

function hm_common4_read_json(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || strlen($raw) > 8 * 1024 * 1024) throw new RuntimeException('dictionary_read');
    $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('dictionary_shape');
    return $value;
}

if (($argv[1] ?? '') === '--resolve') {
    if (count($argv) !== 4) {
        fwrite(STDERR, "usage: --resolve TV_OPERATORS.json SAMO_OPERATORS.json\n");
        exit(2);
    }
    $result = hm_common4_resolve_pair(hm_common4_read_json($argv[2]), hm_common4_read_json($argv[3]));
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}

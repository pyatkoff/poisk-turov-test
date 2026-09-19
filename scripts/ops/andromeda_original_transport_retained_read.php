<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/diagnostics/andromeda_original_transport_facts.php';

/** Reads only exact, retained original package and PRICE checkpoints. No client or DB. */
function anytour_original_transport_retained_read(string $directory): array
{
    if (is_link($directory) || !is_dir($directory) || basename($directory) !== 'searches') {
        throw new RuntimeException('RETAINED_TRANSPORT_DIRECTORY');
    }
    $readCount = 0;
    $read = static function(string $path) use (&$readCount): array {
        if (++$readCount > 100 || is_link($path) || !is_file($path)) throw new RuntimeException('RETAINED_TRANSPORT_FILE');
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 3000000) throw new RuntimeException('RETAINED_TRANSPORT_SIZE');
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) !== $size) throw new RuntimeException('RETAINED_TRANSPORT_READ');
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($data)) throw new RuntimeException('RETAINED_TRANSPORT_JSON');
        return [$data, hash('sha256', $bytes)];
    };
    $paths = []; $inventory = 0;
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) continue;
        if (++$inventory > 100000) throw new RuntimeException('RETAINED_TRANSPORT_INVENTORY');
        if ($entry->isLink() || !$entry->isFile()) continue;
        if (!preg_match('/\A([a-f0-9]{64})-([0-9]{1,12})-([0-9]{1,4})-(offer_[a-f0-9]{64})-package\.json\z/D', $entry->getFilename(), $m)) continue;
        $paths[] = ['file'=>$entry->getFilename(), 'mtime'=>$entry->getMTime(), 'ref'=>$m[1], 'created'=>$m[2], 'page'=>(int)$m[3], 'offer'=>$m[4]];
    }
    usort($paths, static fn(array $a,array $b):int => ($b['mtime'] <=> $a['mtime']) ?: strcmp($a['file'],$b['file']));
    $rows = []; $skips = []; $examined = 0;
    foreach (array_slice($paths, 0, 32) as $item) {
        if (count($rows) >= 12) break;
        ++$examined;
        try {
            [$env,$packageHash] = $read($directory . '/' . $item['file']);
            $record = $env['record'] ?? null;
            if (!is_array($record) || ($record['status'] ?? null) !== 'captured' || !is_array($record['private_package'] ?? null)) throw new RuntimeException('NOT_CAPTURED');
            $binding = $record['context'] ?? [];
            if (($binding['search_ref'] ?? null) !== $item['ref'] || ($binding['offer_ref'] ?? null) !== $item['offer'] || ($binding['page'] ?? null) !== $item['page']) throw new RuntimeException('PACKAGE_FILENAME_BINDING');
            $pageFile = $item['ref'] . ($item['page']===1 ? '-1' : '-' . $item['created'] . '-' . $item['page']) . '.json';
            [$page,$pageHash] = $read($directory . '/' . $pageFile);
            $store = $page['store'] ?? [];
            if ((string)($store['created_at'] ?? '') !== $item['created']) throw new RuntimeException('PAGE_CREATED_BINDING');
            if (!is_int($binding['generation'] ?? null) || ($page['generation'] ?? null) !== $binding['generation']) throw new RuntimeException('PAGE_GENERATION_BINDING');
            $matches = [];
            foreach (($store['snapshot']['offers'] ?? []) as $offer) if (is_array($offer) && ($offer['offer_ref'] ?? null) === $item['offer']) $matches[]=$offer;
            if (count($matches)!==1) throw new RuntimeException('PRICE_OFFER_BINDING');
            $offer = $matches[0]; $tc = $offer['transport_context'] ?? [];
            $context = ['operatorKey'=>$offer['operator_ref'] ?? null, 'tourKey'=>$tc['tour_ref'] ?? null,
                'programKey'=>$tc['program_ref'] ?? null, 'spoKey'=>$tc['spo_ref'] ?? null,
                'checkIn'=>$offer['check_in'] ?? null, 'nights'=>$offer['nights'] ?? null,
                'adult'=>$offer['adults'] ?? null, 'child'=>$offer['children'] ?? null,
                'currency'=>$offer['price']['currency'] ?? null];
            $original = json_encode($record['private_package'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $facts = AnyTourAndromedaOriginalTransportFacts::fromJson($original, $context);
            // Original HTTP bytes are not retained by this older store. Explicitly label re-encoding.
            $facts['input_representation'] = 'retained_original_package_reencoded_json_not_http_bytes';
            $rows[] = ['package_checkpoint_sha256'=>$packageHash, 'price_checkpoint_sha256'=>$pageHash,
                'retained_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$item['mtime']), 'facts'=>$facts];
        } catch (Throwable $error) {
            $reason = $error->getMessage();
            if (!preg_match('/\A[A-Z_]{1,96}\z/D',$reason)) $reason='INVALID_RETAINED_DATA';
            $skips[$reason]=($skips[$reason] ?? 0)+1;
        }
    }
    ksort($skips,SORT_STRING);
    return ['version'=>1,'state'=>'completed_read_only','scope'=>'latest_retained_original_packages_not_fresh_search',
        'matched_checkpoint_files'=>count($paths),'examined'=>$examined,'reported'=>count($rows),'skips'=>$skips,
        'rows'=>$rows,'supplier_calls'=>0,'db_connections'=>0,'db_writes'=>0,'retained_file_writes'=>0,
        'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0];
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    try {
        if (count($argv)!==2) throw new RuntimeException('RETAINED_TRANSPORT_ARGUMENT');
        echo json_encode(anytour_original_transport_retained_read($argv[1]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $error) { fwrite(STDERR,"RETAINED_TRANSPORT_FAILED\n"); exit(2); }
}

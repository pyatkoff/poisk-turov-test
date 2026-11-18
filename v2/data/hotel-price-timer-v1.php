<?php
/** Private CLI entry for an unattended catch-up when the hourly collector has missed its turn. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function hotel_price_timer_command(array $command): array
{
    $proc = proc_open($command, [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['redirect',1]], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('process_unavailable');
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($proc), $output];
}

function hotel_price_timer_crontab(): string
{
    [$code, $body] = hotel_price_timer_command(['/usr/bin/env','LC_ALL=C','crontab','-l']);
    if ($code === 0) return $body;
    if ($code === 1 && preg_match('/\Ano crontab for [^\r\n]+\s*\z/', $body)) return '';
    throw new RuntimeException('cannot_read_service_crontab');
}

function hotel_price_timer_proposed_cron(string $existing, string $entry): string
{
    $count = 0;
    foreach (explode("\n", $existing) as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) continue;
        if ($line === $entry) { ++$count; continue; }
        if (str_contains($line,'hotel-price-timer-v1.php') || str_contains($line,'top500-daily-collector-v1.php'))
            throw new RuntimeException('another_hotel_price_timer_present');
    }
    if ($count > 1) throw new RuntimeException('duplicate_hotel_price_timer');
    if ($count === 1) return $existing;
    return $existing.($existing !== '' && !str_ends_with($existing,"\n") ? "\n" : '').$entry."\n";
}

function hotel_price_timer_save(string $path, array $row): void
{
    $temp = tempnam(dirname($path), '.receipt-');
    if (!$temp) throw new RuntimeException('cannot_create_receipt');
    try {
        $json = json_encode($row, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp,$path))
            throw new RuntimeException('cannot_save_receipt');
    } finally { if (file_exists($temp)) unlink($temp); }
}

function hotel_price_timer_result(int $code, string $log): array
{
    $terminal = [];
    $gaps = null;
    foreach (explode("\n", $log) as $line) {
        if (str_starts_with($line,'TOP500_PRICE_GAPS '))
            $gaps = json_decode(substr($line,strlen('TOP500_PRICE_GAPS ')),true,32,JSON_THROW_ON_ERROR);
        if ($line === 'TOP500_MONTHLY_BUSY') $terminal[] = ['state'=>'busy'];
        if (str_starts_with($line,'TOP500_MONTHLY_RECENT ')) $terminal[] = ['state'=>'recent'];
        if (str_starts_with($line,'TOP500_MONTHLY_DONE ')) {
            $done = json_decode(substr($line,strlen('TOP500_MONTHLY_DONE ')),true,32,JSON_THROW_ON_ERROR);
            foreach (['processed','logical_processed','observations_written','failed','partial','fresh_targets','pending_targets'] as $key)
                if (!is_int($done[$key]??null) || $done[$key]<0) throw new RuntimeException('invalid_collector_totals');
            $terminal[] = ['state'=>$code===0 && $done['failed']===0 ? 'collected' : 'partial','result'=>$done,'price_gaps'=>$gaps];
        }
    }
    if (count($terminal)!==1 || !in_array($code,[0,2],true) || ($code!==0 && $terminal[0]['state']!=='partial'))
        return ['state'=>'failed','exit'=>$code];
    return $terminal[0]+['exit'=>$code];
}

function hotel_price_timer_main(array $args): int
{
    if (count($args)!==2 || !in_array($args[1],['--install','--status','--tick'],true)) return 2;
    $data = '/var/www/anytoour/data/www/anytoour.ru/data';
    if (__DIR__!==$data || realpath(__FILE__)!==$data.'/hotel-price-timer-v1.php') throw new RuntimeException('unexpected_location');
    $dir = dirname($data,3).'/hotel-price-monitor';
    $entry = '*/15 * * * * /usr/bin/php '.$data.'/hotel-price-timer-v1.php --tick';
    umask(0077);
    if ($args[1] === '--status') {
        $current=hotel_price_timer_crontab();
        $out=['registered'=>hotel_price_timer_proposed_cron($current,$entry)===$current];
        foreach (['last-check','last-collection','last-error'] as $name) {
            $path=$dir.'/'.$name.'.json';
            $out[$name]=is_file($path)?json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR):null;
        }
        echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),"\n";
        return 0;
    }
    if (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('cannot_create_private_directory');
    if (realpath($dir)!==$dir || (fileperms($dir)&077)!==0) throw new RuntimeException('invalid_private_directory');
    $lock=fopen($dir.'/timer.lock','c');
    if (!$lock) throw new RuntimeException('cannot_open_timer_lock');
    if (!flock($lock,LOCK_EX|LOCK_NB)) return $args[1]==='--tick'?0:1;
    if ($args[1]==='--install') {
        $before=hotel_price_timer_crontab();
        $after=hotel_price_timer_proposed_cron($before,$entry);
        if ($after!==$before) {
            $temp=tempnam($dir,'cron-');
            if (!$temp) throw new RuntimeException('cannot_stage_crontab');
            try {
                if (file_put_contents($temp,$after)!==strlen($after) || hotel_price_timer_crontab()!==$before)
                    throw new RuntimeException('service_crontab_changed');
                [$code]=hotel_price_timer_command(['crontab',$temp]);
                if ($code!==0 || hotel_price_timer_crontab()!==$after) throw new RuntimeException('crontab_install_unverified');
            } finally { unlink($temp); }
        }
        echo "HOTEL_PRICE_TIMER_REGISTERED\n"; return 0;
    }
    $started=gmdate('c');
    [$code,$log]=hotel_price_timer_command([PHP_BINARY,$data.'/top500-daily-collector-v1.php',
        '--if-idle-minutes=65','--preferred-departure-id=1','--months=12','--budget=100','--max-seconds=1500','--page-gap-reserve=0']);
    $receipt=['started_at'=>$started,'finished_at'=>gmdate('c')]+hotel_price_timer_result($code,$log);
    hotel_price_timer_save($dir.'/last-check.json',$receipt);
    if (isset($receipt['result'])) hotel_price_timer_save($dir.'/last-collection.json',$receipt);
    if (in_array($receipt['state'],['failed','partial'],true)) hotel_price_timer_save($dir.'/last-error.json',$receipt);
    return in_array($receipt['state'],['failed','partial'],true)?1:0;
}

if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    try { exit(hotel_price_timer_main($argv)); }
    catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(2); }
}

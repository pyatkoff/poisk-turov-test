<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/hotel-price-timer-v1.php';
function timer_check(bool $ok,string $label):void { if (!$ok) throw new RuntimeException($label); }
$entry='*/15 * * * * /usr/bin/php /srv/data/hotel-price-timer-v1.php --tick';
foreach (['', "# notes\n5 * * * * /usr/bin/true\n", "10 * * * * /usr/bin/true"] as $before) {
    $after=hotel_price_timer_proposed_cron($before,$entry);
    timer_check(str_starts_with($after,$before) && substr_count($after,$entry)===1,'preserve other entries');
    timer_check(hotel_price_timer_proposed_cron($after,$entry)===$after,'install is idempotent');
}
foreach ([$entry."\n".$entry."\n",'15 * * * * /usr/bin/php /elsewhere/top500-daily-collector-v1.php'] as $conflict) {
    try { hotel_price_timer_proposed_cron($conflict,$entry); throw new LogicException('ambiguous timer accepted'); }
    catch (RuntimeException $expected) {}
}
$totals=['processed'=>6,'logical_processed'=>18,'observations_written'=>250,'failed'=>0,'partial'=>0,'fresh_targets'=>40,'pending_targets'=>20];
$done='TOP500_MONTHLY_DONE '.json_encode($totals,JSON_THROW_ON_ERROR);
timer_check(hotel_price_timer_result(0,$done)['state']==='collected','positive collection');
timer_check(hotel_price_timer_result(2,$done)['state']==='partial','partial preserves original result');
timer_check(hotel_price_timer_result(0,'TOP500_MONTHLY_BUSY')['state']==='busy','overlap skipped');
timer_check(hotel_price_timer_result(0,'TOP500_MONTHLY_RECENT idle_minutes=65')['state']==='recent','healthy schedule skipped');
foreach ([[0,''],[2,'TOP500_MONTHLY_BUSY'],[0,$done."\n".$done],[255,$done]] as [$code,$log])
    timer_check(hotel_price_timer_result($code,$log)['state']==='failed','unknown or contradictory outcome');
echo "HOTEL_PRICE_TIMER_OK\n";

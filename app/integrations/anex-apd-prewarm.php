<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-program-apd-store.php';

/** Pure orchestration around the persisted APD store. Supplier transport is injected. */
final class AnyTourAnexApdPrewarmV1
{
    public static function ttlSeconds(string $dateBeg, DateTimeImmutable $now): int
    {
        $departure=DateTimeImmutable::createFromFormat('!Y-m-d',$dateBeg,new DateTimeZone('UTC'));
        if(!$departure||$departure->format('Y-m-d')!==$dateBeg) throw new InvalidArgumentException('ANEX_PREWARM_DATE');
        $today=$now->setTimezone(new DateTimeZone('UTC'))->setTime(0,0);
        $days=(int)$today->diff($departure)->format('%r%a');
        if($days<=3) return 3600;
        if($days<=14) return 3*3600;
        if($days<=45) return 8*3600;
        return 18*3600;
    }

    /**
     * @param callable(array):array $reader criteria -> sanitized AdditionalPricesDaily payload
     */
    public static function run(
        PDO $db,
        int $departureId,
        int $countryId,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        DateTimeImmutable $seenSince,
        DateTimeImmutable $now,
        int $limit,
        callable $reader
    ): array {
        if($limit<1||$limit>5000) throw new InvalidArgumentException('ANEX_PREWARM_LIMIT');
        $contexts=AnyTourAnexProgramApdStoreV1::prewarmContexts(
            $db,$departureId,$countryId,$dateFrom,$dateTo,$seenSince,$now,$limit
        );
        $counts=['rate'=>0,'empty'=>0,'ambiguous'=>0,'unknown'=>0,'error'=>0];
        $processed=[];
        foreach($contexts as $row){
            $criteria=[
                'supplier_program_id'=>(int)$row['supplier_program_id'],
                'date_beg'=>(string)$row['date_beg'],
                'nights'=>(int)$row['nights'],
                'supplier_currency_id'=>(int)$row['supplier_currency_id'],
            ];
            try{
                $payload=$reader($criteria);
                if(!is_array($payload)) throw new RuntimeException('ANEX_PREWARM_READER');
                $expires=$now->modify('+'.self::ttlSeconds($criteria['date_beg'],$now).' seconds');
                $saved=AnyTourAnexProgramApdStoreV1::recordApd($db,$criteria,$payload,$now,$expires);
                $state=$saved['state']??null;
                if(!is_string($state)||!array_key_exists($state,$counts)) throw new RuntimeException('ANEX_PREWARM_STORE');
                ++$counts[$state];
                $processed[]=['criteria'=>$criteria,'state'=>$state,'expires_at'=>$saved['expires_at']??null];
            }catch(RuntimeException $error){
                if($error->getMessage()==='ANEX_B2B_DAILY_UNKNOWN'){
                    ++$counts['unknown'];
                    $processed[]=['criteria'=>$criteria,'state'=>'unknown'];
                    continue;
                }
                ++$counts['error'];
                $processed[]=['criteria'=>$criteria,'state'=>'error','reason'=>self::reason($error->getMessage())];
            }
        }
        return [
            'source'=>'anex-apd-prewarm-v1',
            'status'=>$counts['error']===0?'complete':'partial',
            'queued'=>count($contexts),
            'processed'=>count($processed),
            'counts'=>$counts,
            'items'=>$processed,
        ];
    }

    private static function reason(string $value): string
    {
        return preg_match('/\A[A-Z0-9_:-]{1,96}\z/D',$value)?$value:'ANEX_PREWARM_ERROR';
    }
}

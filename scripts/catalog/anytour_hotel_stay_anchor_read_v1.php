<?php
/** Fixed retained-tour identity -> CURRENT hotel-local stay review. No supplier or DB writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/anytour_hotel_stay_offer_candidates_v2.php';
require_once __DIR__.'/anytour_hotel_stay_review_packet_v2.php';

final class AnyTourHotelStayAnchorReadV1
{
    public const TOUR_ID='25282424201500';
    public const OPERATION='int-andromeda-local-stay-firstclass-read-20261025-v1';
    public const MAX_BYTES=2097152;
    private const ERRORS=[
        'HOTEL_STAY_ANCHOR_MISSING'=>'anchor_missing',
        'HOTEL_STAY_ANCHOR_PAYLOAD_INVALID'=>'anchor_payload_invalid',
        'HOTEL_STAY_ANCHOR_IDENTITY_AMBIGUOUS'=>'anchor_identity_ambiguous',
        'HOTEL_STAY_ANCHOR_IDENTITY_UNAVAILABLE'=>'anchor_identity_unavailable',
        'HOTEL_STAY_ANCHOR_ROW_LIMIT'=>'anchor_row_limit',
        'HOTEL_STAY_ANCHOR_CURRENT_ROW_LIMIT'=>'current_row_limit',
        'HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY'=>'current_payload_invalid',
    ];

    public static function json(mixed $value): string
    {
        $canonical=static function(mixed $item) use (&$canonical): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item,SORT_STRING);
            foreach ($item as $key=>$part) $item[$key]=$canonical($part);
            return $item;
        };
        return json_encode($canonical($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    public static function read(PDO $pdo, ?DateTimeImmutable $now=null): array
    {
        $receipt=[
            'schemaVersion'=>1,'source'=>'anytour-hotel-stay-anchor-read-v1','state'=>'read_failed',
            'tourvisorTourId'=>self::TOUR_ID,'supplierCalls'=>0,'databaseWrites'=>0,'automaticAccepts'=>0,
            'anchorRetainedRows'=>0,'anchorExpiredRows'=>0,'currentValidatedOffers'=>0,'returnedCohorts'=>0,
            'reviewItems'=>0,'missingTables'=>[],'inventorySha256'=>null,'reviewPacketSha256'=>null,
        ];
        $inventory=null;$packet=null;
        try {
            $inventory=(new AnyTourHotelStayOfferCandidatesV2($pdo))->collectForTourvisorAnchor(self::TOUR_ID,50,$now);
            $packet=AnyTourHotelStayReviewPacketV2::build($inventory,100);
            $receipt['state']=$inventory['returnedCohorts']===0?'no_current_cohorts':'completed_read_only';
            $receipt['anchorRetainedRows']=$inventory['anchorEvidence']['retainedRows'];
            $receipt['anchorExpiredRows']=$inventory['anchorEvidence']['expiredRows'];
            $receipt['currentValidatedOffers']=$inventory['currentIdentityValidatedOffers'];
            $receipt['returnedCohorts']=$inventory['returnedCohorts'];
            $receipt['reviewItems']=$packet['actionableItemCount'];
            $receipt['inventorySha256']=hash('sha256',self::json($inventory));
            $receipt['reviewPacketSha256']=hash('sha256',self::json($packet));
        } catch (AnyTourHotelStayOfferCandidatesSchemaException $error) {
            $receipt['state']='schema_missing';$receipt['missingTables']=$error->missingTables;
            $inventory=null;$packet=null;
        } catch (Throwable $error) {
            $receipt['state']=self::ERRORS[$error->getMessage()]??'read_failed';
            $inventory=null;$packet=null;
        }
        $result=['receipt'=>$receipt,'inventory'=>$inventory,'reviewPacket'=>$packet];
        if (strlen(self::json($result))>self::MAX_BYTES-1024) {
            $receipt['state']='result_size_limit';$receipt['inventorySha256']=null;$receipt['reviewPacketSha256']=null;
            return ['receipt'=>$receipt,'inventory'=>null,'reviewPacket'=>null];
        }
        return $result;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');
        $operation=(string)getenv('ANYTOUR_OPERATION_ID');
        if (!preg_match('/^[a-f0-9]{40}$/D',$source)||!preg_match('/^[a-f0-9]{40}$/D',$control)
            || $operation!==AnyTourHotelStayAnchorReadV1::OPERATION
            || $site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            || $dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            || str_starts_with($dir.'/',$site.'/')) {
            throw new RuntimeException('CLI_SCOPE');
        }
        $path=$dir.'/local-stay-anchor-read.json';
        if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $_SERVER['DOCUMENT_ROOT']=$site;
        require_once __DIR__.'/../../v2/data/db-v1.php';
        $result=AnyTourHotelStayAnchorReadV1::read(v2_data_db());
        $result['receipt']+=['operationId'=>$operation,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelStayAnchorReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayAnchorReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);
        try { $handle=fopen($path,'x+b'); } finally { umask($old); }
        if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {
            if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) {
                throw new RuntimeException('CLI_RECEIPT_WRITE');
            }
        } finally { fclose($handle); }
        echo $json;
        exit($result['receipt']['state']==='read_failed'?1:0);
    } catch (Throwable $error) {
        fwrite(STDERR,"LOCAL_STAY_ANCHOR_READ_FAILED\n");exit(1);
    }
}

<?php
declare(strict_types=1);

/**
 * Applies only explicitly accepted Andromeda hotel identities.
 * Rows must be a current, deduplicated projection whose local target still exists.
 */
final class AnyTourAndromedaHotelResolver {
    private const MAX_ROWS = 50000;
    private $index;
    private $version;

    private function __construct(array $index,string $version) {
        $this->index=$index; $this->version=$version;
    }

    public static function fromRows(array $rows,string $version): self {
        if (!preg_match('/^[a-f0-9]{64}$/D',$version) || count($rows)>self::MAX_ROWS)
            throw new UnexpectedValueException('ANDROMEDA_MAPPING_INVALID');
        $index=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new UnexpectedValueException('ANDROMEDA_MAPPING_INVALID');
            $namespace=self::id($row['supplier_namespace']??null);
            $external=self::id($row['external_hotel_id']??null);
            $key=$namespace.':'.$external;
            if (array_key_exists($key,$index) || !in_array($row['decision_status']??null,['accepted','rejected'],true))
                throw new UnexpectedValueException('ANDROMEDA_MAPPING_INVALID');
            if ($row['decision_status']==='rejected') { $index[$key]=null; continue; }
            $target=self::localId($row['catalog_hotel_id']??null);
            $existing=self::localId($row['existing_catalog_hotel_id']??null);
            if ($target===null || $target!==$existing)
                throw new UnexpectedValueException('ANDROMEDA_MAPPING_INVALID');
            $index[$key]=$target;
        }
        return new self($index,$version);
    }

    public function apply(array $page): array {
        if (($page['provider']??null)!=='andromeda' || !isset($page['offers'])
            || !is_array($page['offers']) || array_values($page['offers'])!==$page['offers']
            || ($page['selection_enabled']??null)!==false)
            throw new UnexpectedValueException('ANDROMEDA_PROJECTION_INVALID');
        $mapped=0;
        foreach ($page['offers'] as &$offer) {
            if (!is_array($offer) || ($offer['provider']??null)!=='andromeda'
                || !array_key_exists('local_hotel_id',$offer) || $offer['local_hotel_id']!==null
                || ($offer['selection_enabled']??null)!==false)
                throw new UnexpectedValueException('ANDROMEDA_PROJECTION_INVALID');
            $namespace=self::id($offer['supplier_namespace']??null);
            $external=self::id($offer['external_hotel_id']??null);
            $key=$namespace.':'.$external;
            if (array_key_exists($key,$this->index) && $this->index[$key]!==null) {
                $offer['local_hotel_id']=$this->index[$key]; ++$mapped;
            }
        }
        unset($offer);
        $page['mapping_version']=$this->version;
        $page['mapped_offer_count']=$mapped;
        return $page;
    }

    public function version(): string { return $this->version; }

    private static function id($value): string {
        if (is_int($value)) $value=(string)$value;
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$value))
            throw new UnexpectedValueException('ANDROMEDA_MAPPING_INVALID');
        return $value;
    }
    private static function localId($value): ?int {
        if (is_int($value)) $value=(string)$value;
        return is_string($value) && preg_match('/^[1-9][0-9]{0,9}$/D',$value)
            && (float)$value<=2147483647 ? (int)$value : null;
    }
}

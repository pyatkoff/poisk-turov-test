<?php

declare(strict_types=1);

require __DIR__ . '/../../../v2/lead-price-v1.php';

$selected = v2_lead_price_summary(70941, 87807);
if ($selected['basePrice'] !== 70941 || $selected['selectedPrice'] !== 87807 || $selected['delta'] !== 16866) {
    exit(1);
}

$unchanged = v2_lead_price_summary(70941, 70941);
if ($unchanged['selectedPrice'] !== 70941 || $unchanged['delta'] !== 0) {
    exit(2);
}

echo "PRICE_CONTRACT_OK\n";

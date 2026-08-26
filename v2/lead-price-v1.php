<?php
function v2_lead_price_summary($basePrice,$flightPrice): array
{
    $base=max(0,(int)round((float)$basePrice));
    $flight=max(0,(int)round((float)$flightPrice));
    $selected=$flight>0?$flight:$base;
    return ['basePrice'=>$base,'selectedPrice'=>$selected,'delta'=>$base>0&&$selected>0?$selected-$base:0];
}

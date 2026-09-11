<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-room-placement.php';

$checks = 0;
function rp_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('rp_check_'.$checks); }

$tv = AnyTourThreeProviderRoomPlacement::normalize('tourvisor', ' Standard-Room ', 'DBL / 2 ADL');
rp_check($tv['provider'] === 'tourvisor');
rp_check($tv['room']['raw'] === 'Standard-Room');
rp_check($tv['room']['normalized'] === 'standard room');
rp_check($tv['placement']['raw'] === 'DBL / 2 ADL');
rp_check($tv['placement']['normalized'] === 'dbl 2 adl');
rp_check($tv['room_placement_separate'] === true);
rp_check($tv['cross_provider_equivalence_verified'] === false);
rp_check($tv['package_identity_verified'] === false);
rp_check($tv['room']['comparison_scope'] === 'display_label_only');

$anex = AnyTourThreeProviderRoomPlacement::normalize('anex', 'Garden   Room', 'Double');
rp_check($anex['room']['normalized'] === 'garden room');
rp_check($anex['placement']['normalized'] === 'double');

$andromeda = AnyTourThreeProviderRoomPlacement::normalize('andromeda', 'Economy With French Bed', null);
rp_check($andromeda['placement'] === null);
rp_check($andromeda['room']['normalized'] === 'economy with french bed');

$russian = AnyTourThreeProviderRoomPlacement::normalize('andromeda', 'стандарт, вид на море', '2 взрослых');
rp_check($russian['room']['normalized'] === 'стандарт вид на море');
rp_check($russian['placement']['normalized'] === '2 взрослых');

$roomOnly = AnyTourThreeProviderRoomPlacement::normalize('tourvisor', 'DBL', null);
rp_check($roomOnly['room']['normalized'] === 'dbl' && $roomOnly['placement'] === null);

$bad = [
    fn () => AnyTourThreeProviderRoomPlacement::normalize('other', 'Standard', 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', '', 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', '   ', 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', 123, 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', '123', 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', 'Standard', '456'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', "Standard\nRoom", 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', str_repeat('A', 181), 'DBL'),
    fn () => AnyTourThreeProviderRoomPlacement::normalize('anex', 'Standard', []),
];
foreach ($bad as $case) {
    try { $case(); rp_check(false); } catch (InvalidArgumentException $e) { rp_check(true); }
}

$rawRoom = 'Standard-Room';
$rawPlacement = 'DBL / 2 ADL';
AnyTourThreeProviderRoomPlacement::normalize('tourvisor', $rawRoom, $rawPlacement);
rp_check($rawRoom === 'Standard-Room' && $rawPlacement === 'DBL / 2 ADL');

echo 'Three-provider room/placement: '.$checks." checks passed; supplier/DB/mapping=0.\n";

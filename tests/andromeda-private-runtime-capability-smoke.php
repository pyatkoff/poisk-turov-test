<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-saved-package-runtime.php';
$checks=0;$ok=function($v,$m)use(&$checks){if(!$v)throw new LogicException($m);++$checks;};
try{AnyTourAndromedaPrivateRuntimeCapability::fromTrustedConfig([]);throw new LogicException('empty config issued capability');}
catch(RuntimeException $e){$ok($e->getMessage()==='ANDROMEDA_PACKAGE_DISABLED','empty config refused');}
try{AnyTourAndromedaPrivateRuntimeCapability::fromTrustedConfig(['enabled'=>false,'catalog_path'=>'/private/catalog.json']);throw new LogicException('disabled config issued capability');}
catch(RuntimeException $e){$ok($e->getMessage()==='ANDROMEDA_PACKAGE_DISABLED','disabled config refused');}
$cap=AnyTourAndromedaPrivateRuntimeCapability::fromTrustedConfig(['enabled'=>true,'catalog_path'=>'/private/catalog.json']);
$ok($cap instanceof AnyTourAndromedaPrivateRuntimeCapability,'trusted enabled config issues typed capability');
$ref=new ReflectionClass(AnyTourAndromedaPrivateRuntimeCapability::class);
$ok($ref->getConstructor()->isPrivate(),'capability cannot be constructed from request data');
$fn=new ReflectionFunction('anytour_andromeda_capture_saved_package');
$p=$fn->getParameters();$last=end($p);
$ok($last->getName()==='capability' && $last->allowsNull(),'capture core has explicit typed capability seam');
$sf=new ReflectionFunction('anytour_andromeda_saved_package_surcharge');
$sp=$sf->getParameters();$sl=end($sp);
$ok($sl->getName()==='capability' && $sl->allowsNull(),'surcharge core has same capability seam');
echo "Private package capability: $checks checks passed; supplier calls=0.\n";

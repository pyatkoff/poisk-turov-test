<?php

declare(strict_types=1);

use AnyTour\Platform\ContentRepository;
use AnyTour\Platform\Database;
use AnyTour\Platform\EntityRepository;
use AnyTour\Platform\PageAssembler;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/EntityRepository.php';
require_once dirname(__DIR__) . '/src/ContentRepository.php';
require_once dirname(__DIR__) . '/src/PageAssembler.php';

$entityKey = $argv[1] ?? 'country:turkey';
$pdo = Database::connectFromEnvironment();
$entities = new EntityRepository($pdo);
$content = new ContentRepository($pdo);
$assembler = new PageAssembler($entities, $content);
$page = $assembler->country($entityKey);

fwrite(STDOUT, json_encode($page, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

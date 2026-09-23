<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use MediaAuth;
use MediaConfig;
use MediaDatabase;

Config::load(__DIR__ . '/../..');

$db = Database::connection();

Auth::requireLogin($db);

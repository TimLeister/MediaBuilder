<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use Media\Auth;
use Media\Config;
use Media\Database;

Config::load(__DIR__ . '/../..');

$db = Database::connection();

Auth::requireLogin($db);

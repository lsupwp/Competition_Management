<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap_env.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: ($_ENV['APP_TIMEZONE'] ?? 'Asia/Bangkok'));

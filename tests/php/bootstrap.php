<?php

declare(strict_types=1);

/**
 * Bootstrap для PHPUnit тестов SDK.
 *
 * 1. Подключает автозагрузчик Composer (PHPUnit, Guzzle и т.д.)
 * 2. Подключает сгенерированный SDK из clients/php (если есть)
 *
 * Сгенерированный SDK не подключается через Composer path repository,
 * так как его composer.json может не содержать поле "name" в формате,
 * ожидаемом Composer при использовании как path repo.
 */
$baseDir = dirname(__DIR__, 2);
$sdkAutoload = $baseDir . '/clients/php/autoload.php';

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists($sdkAutoload)) {
    require_once $sdkAutoload;
}

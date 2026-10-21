<?php

declare(strict_types=1);

/**
 * Bootstrap для PHPUnit тестов SDK.
 *
 * 1. Подключает автозагрузчик Composer (PHPUnit, Guzzle и т.д.)
 * 2. Подключает сгенерированный SDK из clients/php (vendor/autoload.php или PSR-4 из lib/)
 */
$baseDir = dirname(__DIR__, 2);
$sdkDir = $baseDir . '/clients/php';

require_once __DIR__ . '/vendor/autoload.php';

// Подключение SDK: либо vendor/autoload.php (если в clients/php запускали composer install), либо ручной PSR-4
$sdkVendorAutoload = $sdkDir . '/vendor/autoload.php';
if (file_exists($sdkVendorAutoload)) {
    require_once $sdkVendorAutoload;
} elseif (is_dir($sdkDir . '/lib')) {
    spl_autoload_register(static function (string $class) use ($sdkDir): void {
        $prefix = 'OpenAPI\\Client\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative = substr($class, $len);
        $file = $sdkDir . '/lib/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

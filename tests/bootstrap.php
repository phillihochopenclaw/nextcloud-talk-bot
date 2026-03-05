<?php
declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 * 
 * This file sets up the testing environment for Nextcloud Talk Bot tests.
 * It provides mock implementations of Nextcloud core classes.
 */

// Define test constants
define('PHPUNIT_TEST', true);
define('PHPUNIT_RUNNING', true);
define('NEXTCLOUD_ROOT_DIR', sys_get_temp_dir() . '/nextcloud_test');

// Create temp directory if needed
if (!is_dir(NEXTCLOUD_ROOT_DIR)) {
    mkdir(NEXTCLOUD_ROOT_DIR, 0755, true);
}

// Register autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define the OCP namespace mocks for testing
// These provide the interfaces that Nextcloud core provides

// Load framework mocks
require_once __DIR__ . '/Framework/Controller.php';
require_once __DIR__ . '/Framework/JSONResponse.php';
require_once __DIR__ . '/Framework/Http.php';
require_once __DIR__ . '/Framework/MockRequest.php';
require_once __DIR__ . '/Framework/MockConfig.php';

// Create needed directories
$dirs = [
    NEXTCLOUD_ROOT_DIR . '/data',
    NEXTCLOUD_ROOT_DIR . '/config',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

echo "PHPUnit bootstrap completed.\n";
<?php
/**
 * Debug script to test RemoteLocations class loading
 */

// WordPress bootstrap
define('ABSPATH', '/Users/chubes/Local Sites/community-stage/app/public/');
require_once ABSPATH . 'wp-config.php';

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing RemoteLocations class loading...\n";

try {
    echo "1. Testing RemoteLocationsOperations...\n";
    $operations = new \DataMachine\Core\Database\RemoteLocations\RemoteLocationsOperations();
    echo "   ✓ RemoteLocationsOperations loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ RemoteLocationsOperations failed: " . $e->getMessage() . "\n";
}

try {
    echo "2. Testing RemoteLocationsSecurity...\n";
    $security = new \DataMachine\Core\Database\RemoteLocations\RemoteLocationsSecurity();
    echo "   ✓ RemoteLocationsSecurity loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ RemoteLocationsSecurity failed: " . $e->getMessage() . "\n";
}

try {
    echo "3. Testing RemoteLocationsSync...\n";
    $sync = new \DataMachine\Core\Database\RemoteLocations\RemoteLocationsSync();
    echo "   ✓ RemoteLocationsSync loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ RemoteLocationsSync failed: " . $e->getMessage() . "\n";
}

try {
    echo "4. Testing RemoteLocations main class...\n";
    $remote_locations = new \DataMachine\Core\Database\RemoteLocations\RemoteLocations();
    echo "   ✓ RemoteLocations loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ RemoteLocations failed: " . $e->getMessage() . "\n";
}

echo "Debug complete.\n";
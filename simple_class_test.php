<?php
/**
 * Simple class loading test without WordPress
 */

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing class loading without WordPress context...\n";

// Test if classes can be found by autoloader
$classes_to_test = [
    '\DataMachine\Core\Database\RemoteLocations\RemoteLocations',
    '\DataMachine\Core\Database\RemoteLocations\RemoteLocationsOperations',
    '\DataMachine\Core\Database\RemoteLocations\RemoteLocationsSecurity', 
    '\DataMachine\Core\Database\RemoteLocations\RemoteLocationsSync'
];

foreach ($classes_to_test as $class) {
    if (class_exists($class)) {
        echo "✓ Class found: {$class}\n";
    } else {
        echo "✗ Class NOT found: {$class}\n";
        
        // Try to find the expected file path
        $class_parts = explode('\\', $class);
        $filename = end($class_parts) . '.php';
        
        // Convert namespace to path
        $namespace_path = str_replace(['DataMachine\\Core\\', '\\'], ['inc/core/', '/'], $class);
        $expected_path = __DIR__ . '/' . dirname($namespace_path) . '/' . $filename;
        
        echo "   Expected file: {$expected_path}\n";
        echo "   File exists: " . (file_exists($expected_path) ? 'YES' : 'NO') . "\n";
    }
}
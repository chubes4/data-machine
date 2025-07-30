<?php
echo "Direct test starting...\n";
echo "Current directory: " . getcwd() . "\n";

if (file_exists('vendor/autoload.php')) {
    echo "Autoload file exists\n";
    require_once 'vendor/autoload.php';
    echo "Autoload included\n";
} else {
    echo "Autoload file NOT found\n";
    exit(1);
}

$class = '\DataMachine\Core\Database\RemoteLocations\RemoteLocations';
echo "Testing class: {$class}\n";

if (class_exists($class)) {
    echo "Class exists!\n";
} else {
    echo "Class does NOT exist\n";
}

echo "Test complete\n";
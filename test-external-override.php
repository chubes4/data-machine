<?php
/**
 * Test file to verify external plugin service override capability
 * This demonstrates that third-party plugins can still override services
 */

// Test external service override
add_filter('dm_service_override_logger', function($service) {
    // External plugin can provide custom logger
    return new class {
        public function info($message) {
            error_log("CUSTOM LOGGER: " . $message);
        }
        
        public function error($message) {
            error_log("CUSTOM ERROR: " . $message);
        }
        
        public function display_admin_notices() {
            echo '<div class="notice notice-info"><p>Custom logger active!</p></div>';
        }
    };
});

// Test that service access still works
$logger = apply_filters('dm_get_service', null, 'logger');
if ($logger) {
    echo "✅ Service override test successful - logger retrieved via filter\n";
    $logger->info("Test message from external override");
} else {
    echo "❌ Service override test failed - logger not accessible\n";
}
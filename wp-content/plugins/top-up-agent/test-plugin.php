<?php
// Test autoloading and basic functionality
require_once __DIR__ . '/vendor/autoload.php';

// Test basic class instantiation
try {
    $main = new TopUpAgent\Main();
    echo "✓ TopUpAgent\Main class loads successfully\n";
} catch (Exception $e) {
    echo "✗ Error loading TopUpAgent\Main: " . $e->getMessage() . "\n";
}

try {
    $setup = new TopUpAgent\Setup();
    echo "✓ TopUpAgent\Setup class loads successfully\n";
} catch (Exception $e) {
    echo "✗ Error loading TopUpAgent\Setup: " . $e->getMessage() . "\n";
}

try {
    $settings = new TopUpAgent\Settings();
    echo "✓ TopUpAgent\Settings class loads successfully\n";
} catch (Exception $e) {
    echo "✗ Error loading TopUpAgent\Settings: " . $e->getMessage() . "\n";
}

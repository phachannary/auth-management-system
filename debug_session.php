<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Start session
session_start();

echo "=== DEBUGGING SESSION DATA ===\n\n";

echo "Current session data:\n";
print_r($_SESSION);

echo "\nLaravel session:\n";
if (function_exists('session')) {
    echo "Username in session: " . (session('username') ?? 'NOT SET') . "\n";
    echo "Success message: " . (session('success') ?? 'NOT SET') . "\n";
} else {
    echo "Laravel session not available\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "POSSIBLE ISSUES:\n";
echo "1. Username not in session → Hidden field is empty\n";
echo "2. Session cleared after registration → Username lost\n";
echo "3. Different session domain → Session not persisting\n";
echo "\n";
echo "TO FIX:\n";
echo "1. Make sure user registers first\n";
echo "2. Check if username is stored in session after registration\n";
echo "3. Verify session configuration in .env\n";

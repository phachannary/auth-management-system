<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CognitoService;

echo "=== DEBUGGING VERIFICATION PROCESS ===\n\n";

// Test data - replace with actual user data
$testUsername = "nary"; // Use the username from your image
$testCode = "123456"; // Replace with actual code from email

echo "Testing with:\n";
echo "- Username: $testUsername\n";
echo "- Code: $testCode\n\n";

$cognito = new CognitoService();

// Step 1: Test the confirmSignUp method directly
echo "Step 1: Testing confirmSignUp...\n";
$result = $cognito->confirmSignUp($testUsername, $testCode);

echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Step 2: Try to login after verification
if ($result['success']) {
    echo "Step 2: Testing login after verification...\n";
    
    // You need to provide the actual password
    $password = "your_password_here"; // Replace with actual password
    
    $loginResult = $cognito->initiateAuth($testUsername, $password);
    
    if ($loginResult['success']) {
        echo "✅ Login successful after verification!\n";
        echo "User is now CONFIRMED and can login.\n";
    } else {
        echo "❌ Login failed: " . $loginResult['error'] . "\n";
    }
} else {
    echo "❌ Verification failed. Let's check why...\n\n";
    
    // Common issues
    $error = $result['error'];
    echo "Error Analysis:\n";
    echo "- Error: $error\n";
    
    if (strpos($error, 'Invalid verification code') !== false) {
        echo "→ The code is wrong. Check the email again.\n";
    } elseif (strpos($error, 'CodeMismatchException') !== false) {
        echo "→ Code mismatch. It might have expired.\n";
    } elseif (strpos($error, 'NotAuthorizedException') !== false) {
        echo "→ User might already be confirmed. Try logging in.\n";
    } elseif (strpos($error, 'User does not exist') !== false) {
        echo "→ User not found. Check registration.\n";
    } elseif (strpos($error, 'SecretHash') !== false) {
        echo "→ SecretHash issue. Check client secret.\n";
    }
}

echo "\n=== CHECKING COGNITO CONFIG ===\n";
echo "Client ID: " . env('COGNITO_CLIENT_ID') . "\n";
echo "Client Secret: " . (env('COGNITO_CLIENT_SECRET') ? "SET" : "NOT SET") . "\n";
echo "User Pool: " . env('COGNITO_USER_POOL_ID') . "\n";
echo "Region: " . env('COGNITO_REGION') . "\n";

// Step 3: Test with a new verification code
echo "\nStep 3: Requesting new verification code...\n";
$resendResult = $cognito->resendConfirmationCode($testUsername);
echo "Resend result: " . json_encode($resendResult, JSON_PRETTY_PRINT) . "\n";

if ($resendResult['success']) {
    echo "✅ New code sent! Check your email.\n";
    echo "Then run this script again with the new code.\n";
}

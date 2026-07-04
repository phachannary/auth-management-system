<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

echo "=== REQUEST NEW CODE AND VERIFY ===\n\n";

$client = new CognitoIdentityProviderClient([
    'version' => 'latest',
    'region'  => env('COGNITO_REGION'),
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$username = "nary"; // Replace with actual username

echo "Step 1: Requesting new verification code for user: $username\n";

// Calculate SecretHash for resend
$clientId = env('COGNITO_CLIENT_ID');
$clientSecret = env('COGNITO_CLIENT_SECRET');
$secretHash = base64_encode(hash_hmac('sha256', $username . $clientId, $clientSecret, true));

try {
    $result = $client->resendConfirmationCode([
        'ClientId' => $clientId,
        'Username' => $username,
        'SecretHash' => $secretHash,
    ]);
    
    echo "✅ New verification code sent successfully!\n";
    echo "Check your email for the new 6-digit code.\n\n";
    
    echo "The code will expire in 24 hours.\n";
    echo "After receiving the code, run the verification script below:\n\n";
    
    echo "php verify_with_new_code.php\n\n";
    
    // Create the verification script
    $verifyScript = '<?php
require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

$client = new CognitoIdentityProviderClient([
    "version" => "latest",
    "region"  => env("COGNITO_REGION"),
    "credentials" => [
        "key"    => env("AWS_ACCESS_KEY_ID"),
        "secret" => env("AWS_SECRET_ACCESS_KEY"),
    ],
]);

$username = "' . $username . '";
echo "Enter the 6-digit verification code from your email: ";
$code = trim(fgets(STDIN));

if (strlen($code) !== 6 || !is_numeric($code)) {
    echo "❌ Invalid code format. Must be 6 digits.\n";
    exit(1);
}

$clientId = env("COGNITO_CLIENT_ID");
$clientSecret = env("COGNITO_CLIENT_SECRET");
$secretHash = base64_encode(hash_hmac("sha256", $username . $clientId, $clientSecret, true));

$params = [
    "ClientId" => $clientId,
    "Username" => $username,
    "ConfirmationCode" => $code,
    "SecretHash" => $secretHash,
];

try {
    echo "Verifying...\n";
    $result = $client->confirmSignUp($params);
    
    echo "✅ SUCCESS! User is now confirmed.\n";
    echo "You can now login with your username and password.\n";
    
} catch (AwsException $e) {
    echo "❌ Verification failed: " . $e->getAwsErrorMessage() . "\n";
}
';
    
    file_put_contents('verify_with_new_code.php', $verifyScript);
    echo "Created verify_with_new_code.php - run it after you receive the email.\n";
    
} catch (\Aws\Exception\AwsException $e) {
    echo "❌ Failed to send new code: " . $e->getAwsErrorMessage() . "\n";
}

<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

echo "=== DIAGNOSING USER STATUS ===\n\n";

$client = new CognitoIdentityProviderClient([
    'version' => 'latest',
    'region'  => env('COGNITO_REGION'),
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$username = "nary";

echo "Checking user: $username\n\n";

// Try to authenticate - this will tell us the actual status
try {
    $authResult = $client->initiateAuth([
        'AuthFlow' => 'USER_PASSWORD_AUTH',
        'ClientId' => env('COGNITO_CLIENT_ID'),
        'AuthParameters' => [
            'USERNAME' => $username,
            'PASSWORD' => 'Password123!', // You'll need to provide the actual password
            'SECRET_HASH' => base64_encode(hash_hmac('sha256', $username . env('COGNITO_CLIENT_ID'), env('COGNITO_CLIENT_SECRET'), true))
        ],
    ]);
    
    echo "✅ User can authenticate!\n";
    echo "This means the user is already CONFIRMED.\n";
    echo "The issue might be:\n";
    echo "1. User was already confirmed earlier\n";
    echo "2. Email verification is separate from account confirmation\n";
    
} catch (\Aws\Exception\AwsException $e) {
    $errorCode = $e->getAwsErrorCode();
    $errorMessage = $e->getAwsErrorMessage();
    
    echo "Authentication failed with: $errorCode\n";
    echo "Message: $errorMessage\n\n";
    
    if ($errorCode === 'NotAuthorizedException') {
        if (strpos($errorMessage, 'User is not confirmed') !== false) {
            echo "❌ User is NOT CONFIRMED\n";
            echo "This is the expected state - user needs verification\n";
            echo "The verification code should work if it's fresh\n";
        } else {
            echo "❌ Incorrect password or user not found\n";
        }
    } elseif ($errorCode === 'UserNotFoundException') {
        echo "❌ User does not exist in Cognito\n";
        echo "Check if registration was successful\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "IMPORTANT DISTINCTION:\n";
echo "1. EMAIL VERIFIED = Email address is valid\n";
echo "2. ACCOUNT CONFIRMED = User can login\n";
echo "\n";
echo "AWS Cognito has TWO separate steps:\n";
echo "- Verify email attribute (optional)\n";
echo "- Confirm signup (required for login)\n";
echo "\n";
echo "Your issue might be that email is verified\n";
echo "but account is not yet CONFIRMED\n";

<?php
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

$username = "nary";
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

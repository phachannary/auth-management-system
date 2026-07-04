<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

echo "=== DELETE USER FROM AWS COGNITO ===\n\n";

$client = new CognitoIdentityProviderClient([
    'version' => 'latest',
    'region'  => env('COGNITO_REGION'),
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$username = "nary"; // Change this to the username you want to delete

echo "Attempting to delete user: $username\n\n";

try {
    $result = $client->adminDeleteUser([
        'UserPoolId' => env('COGNITO_USER_POOL_ID'),
        'Username' => $username,
    ]);

    echo "✅ User '$username' deleted successfully!\n";
    echo "You can now register again with the same username/email.\n";

} catch (\Aws\Exception\AwsException $e) {
    $errorCode = $e->getAwsErrorCode();
    $errorMessage = $e->getAwsErrorMessage();

    echo "❌ Failed to delete user: $errorCode\n";
    echo "Error: $errorMessage\n\n";

    if ($errorCode === 'NotAuthorizedException') {
        echo "This is likely a permissions issue.\n";
        echo "Your IAM user needs 'cognito-idp:AdminDeleteUser' permission.\n\n";

        echo "ALTERNATIVE: Try self-service delete (if user can login):\n";
        echo "1. First login as the user to get tokens\n";
        echo "2. Then use DeleteUser with AccessToken\n";
    } elseif ($errorCode === 'UserNotFoundException') {
        echo "User doesn't exist in Cognito.\n";
    } else {
        echo "Check IAM permissions for Cognito operations.\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "If admin delete fails due to permissions:\n";
echo "1. Go to AWS Console → Cognito → User Pools\n";
echo "2. Select your user pool: " . env('COGNITO_USER_POOL_ID') . "\n";
echo "3. Go to 'Users' tab\n";
echo "4. Find the user and delete manually\n";
echo "5. Or update IAM policy to add cognito-idp:AdminDeleteUser\n";

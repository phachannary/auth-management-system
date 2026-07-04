<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

echo "Checking User Status in AWS Cognito...\n\n";

$client = new CognitoIdentityProviderClient([
    'version' => 'latest',
    'region'  => env('COGNITO_REGION'),
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$username = "nary"; // change this

try {

    echo "Fetching user from Cognito...\n\n";

    // ✅ BEST WAY: Admin API (no login required)
    $result = $client->adminGetUser([
        'UserPoolId' => env('COGNITO_USER_POOL_ID'),
        'Username'   => $username,
    ]);

    echo "✅ User found!\n\n";

    echo "User Details:\n";
    echo "- Username: " . $result['Username'] . "\n";
    echo "- User Status: " . ($result['UserStatus'] ?? 'UNKNOWN') . "\n";
    echo "- Enabled: " . (($result['Enabled'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "- User Created: " . ($result['UserCreateDate'] ?? 'N/A') . "\n";
    echo "- Last Modified: " . ($result['UserLastModifiedDate'] ?? 'N/A') . "\n\n";

    echo "Attributes:\n";

    $email = null;
    $emailVerified = null;

    foreach (($result['UserAttributes'] ?? []) as $attribute) {
        if ($attribute['Name'] === 'email') {
            $email = $attribute['Value'];
        }

        if ($attribute['Name'] === 'email_verified') {
            $emailVerified = $attribute['Value'] === 'true' ? 'Yes' : 'No';
        }
    }

    echo "- Email: " . ($email ?? 'N/A') . "\n";
    echo "- Email Verified: " . ($emailVerified ?? 'N/A') . "\n";

    echo "\nPossible Status Values:\n";
    echo "- UNCONFIRMED: User not verified\n";
    echo "- CONFIRMED: User can login\n";
    echo "- ARCHIVED: User archived\n";
    echo "- COMPROMISED: Security issue detected\n";
    echo "- RESET_REQUIRED: Password reset required\n";
    echo "- FORCE_CHANGE_PASSWORD: Must change password\n";

} catch (\Aws\Exception\AwsException $e) {

    echo "❌ Error: " . $e->getAwsErrorMessage() . "\n";

    if (strpos($e->getAwsErrorMessage(), 'User does not exist') !== false) {
        echo "\n💡 User not found in Cognito.\n";
        echo "Check:\n";
        echo "1. Username spelling\n";
        echo "2. Correct User Pool ID\n";
        echo "3. Correct AWS region\n";
    }
}

echo "\n==================================================\n";
echo "AWS Cognito Configuration Check:\n";
echo "- User Pool ID: " . env('COGNITO_USER_POOL_ID') . "\n";
echo "- Region: " . env('COGNITO_REGION') . "\n";
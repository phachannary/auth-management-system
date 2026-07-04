<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

echo "=== CHECKING COGNITO USER POOL CONFIG ===\n\n";

$client = new CognitoIdentityProviderClient([
    'version' => 'latest',
    'region'  => env('COGNITO_REGION'),
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
]);

try {
    // Describe the user pool to check configuration
    $result = $client->describeUserPool([
        'UserPoolId' => env('COGNITO_USER_POOL_ID'),
    ]);
    
    $pool = $result['UserPool'];
    
    echo "User Pool Configuration:\n";
    echo "- Name: " . $pool['Name'] . "\n";
    echo "- ID: " . $pool['Id'] . "\n";
    echo "- Status: " . $pool['Status'] . "\n\n";
    
    echo "Verification Settings:\n";
    
    // Check email verification
    if (isset($pool['AutoVerifiedAttributes'])) {
        echo "- Auto Verified Attributes: " . implode(', ', $pool['AutoVerifiedAttributes']) . "\n";
    }
    
    if (isset($pool['VerificationMessageTemplate'])) {
        echo "- Email Message: " . $pool['VerificationMessageTemplate']['EmailMessage'] . "\n";
        echo "- Email Subject: " . $pool['VerificationMessageTemplate']['EmailSubject'] . "\n";
    }
    
    echo "\nUser Creation Settings:\n";
    echo "- Allow Admin Create User Only: " . ($pool['AdminCreateUserConfig']['AllowAdminCreateUserOnly'] ? 'Yes' : 'No') . "\n";
    
    echo "\nPolicies:\n";
    echo "- Password Policy: Minimum length " . $pool['Policies']['PasswordPolicy']['MinimumLength'] . "\n";
    
    // Check app clients
    echo "\nApp Clients:\n";
    $clients = $client->listUserPoolClients([
        'UserPoolId' => env('COGNITO_USER_POOL_ID'),
    ]);
    
    foreach ($clients['UserPoolClients'] as $appClient) {
        echo "- Client ID: " . $appClient['ClientId'] . "\n";
        echo "  Name: " . $appClient['ClientName'] . "\n";
        echo "  Secret: " . ($appClient['ClientSecret'] ? 'Yes' : 'No') . "\n";
        echo "  Explicit Auth Flows: " . implode(', ', $appClient['ExplicitAuthFlows']) . "\n";
        echo "  Prevent User Existence Errors: " . ($appClient['PreventUserExistenceErrors'] ? 'Yes' : 'No') . "\n\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "COMMON ISSUES:\n";
    echo "1. If 'ALLOW_USER_PASSWORD_AUTH' is missing, password auth won't work\n";
    echo "2. If 'Prevent User Existence Errors' is TRUE, it hides real errors\n";
    echo "3. If email is in 'AutoVerifiedAttributes', verification is automatic\n";
    echo "4. Check if the user needs to be confirmed manually\n";
    
} catch (\Aws\Exception\AwsException $e) {
    echo "❌ Error checking user pool: " . $e->getAwsErrorMessage() . "\n";
    echo "This might be an IAM permissions issue\n";
}

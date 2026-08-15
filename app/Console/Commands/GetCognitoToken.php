<?php

namespace App\Console\Commands;

use App\Services\CognitoService;
use Illuminate\Console\Command;

class GetCognitoToken extends Command
{
    protected $signature = 'cognito:token {email} {password}';
    protected $description = 'Get a Cognito access token for API testing';

    public function handle(CognitoService $cognito)
    {
        $email    = $this->argument('email');
        $password = $this->argument('password');

        $this->info("Authenticating: $email ...");

        $result = $cognito->initiateAuth($email, $password);

        if (!$result['success']) {
            $this->error('Authentication failed: ' . $result['error']);
            return 1;
        }

        $this->newLine();
        $this->info('✅ Authentication successful!');
        $this->newLine();

        $tokens = $result['data']['AuthenticationResult'] ?? [];

        $this->line('ACCESS TOKEN (use this in Postman):');
        $this->newLine();
        $this->line($tokens['AccessToken'] ?? 'N/A');
        $this->newLine();
        $this->line('---');
        $this->line('ID TOKEN:');
        $this->line($tokens['IdToken'] ?? 'N/A');
        $this->newLine();
        $this->line('Expires in: ' . ($tokens['ExpiresIn'] ?? 3600) . ' seconds');

        return 0;
    }
}

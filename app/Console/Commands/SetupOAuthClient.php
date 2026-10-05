<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupOAuthClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth:setup-client
                            {--client-id=ds1_test_client : The OAuth client ID}
                            {--client-secret= : The OAuth client secret (generates random if not provided)}
                            {--redirect-uri=https://ds1.authmanagement.com/auth/callback : The redirect URI}
                            {--force : Update existing client even if it has a secret}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up or update OAuth client configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clientId = $this->option('client-id');
        $clientSecret = $this->option('client-secret');
        $redirectUri = $this->option('redirect-uri');
        $force = $this->option('force');

        $this->info("Setting up OAuth client: {$clientId}");

        // Generate secret if not provided
        if (empty($clientSecret)) {
            $clientSecret = Str::random(64);
            $this->info("Generated random client secret");
        }

        // Check if client exists
        $client = OAuthClient::where('client_id', $clientId)->first();

        if ($client) {
            $this->info("Client exists with ID: {$clientId}");
            $this->info("Current secret: " . ($client->client_secret ? 'SET' : 'NULL'));
            $this->info("Current confidential: " . ($client->confidential ? 'true' : 'false'));
            $this->info("Current active: " . ($client->active ? 'true' : 'false'));

            if ($client->client_secret && !$force) {
                $this->warn("Client already has a secret. Use --force to update it.");
                return 1;
            }

            // Update the client
            $client->update([
                'client_secret' => $clientSecret,
                'redirect_uris' => [$redirectUri],
                'confidential' => true,
                'active' => true,
            ]);

            $this->info("Updated OAuth client successfully");
        } else {
            // Create new client
            OAuthClient::create([
                'name' => 'DS1 Production Client',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uris' => [$redirectUri],
                'scopes' => ['openid', 'profile', 'email'],
                'confidential' => true,
                'active' => true,
            ]);

            $this->info("Created new OAuth client successfully");
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['Client ID', $clientId],
                ['Client Secret', $clientSecret],
                ['Redirect URI', $redirectUri],
                ['Confidential', 'true'],
                ['Active', 'true'],
            ]
        );

        $this->warn("\nIMPORTANT: Update DS1 .env file with:");
        $this->warn("AUTH_SERVER_CLIENT_SECRET={$clientSecret}");

        return 0;
    }
}

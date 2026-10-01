<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OAuthClient;
use Illuminate\Support\Str;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create a test OAuth client for DS1
        OAuthClient::create([
            'name' => 'DS1 Test Client',
            'client_id' => 'ds1_test_client',
            'client_secret' => 'ds1_test_secret_12345',
            'redirect_uris' => [
                'http://localhost:3000/auth/callback',
                'http://localhost:8000/auth/callback',
                'https://ds1.authmanagement.com/auth/callback',
            ],
            'scopes' => ['openid', 'profile', 'email'],
            'confidential' => true,
            'active' => true,
        ]);

        // Create a test OAuth client for mobile apps (public client)
        OAuthClient::create([
            'name' => 'Mobile App Test Client',
            'client_id' => 'mobile_test_client',
            'client_secret' => null, // Public client
            'redirect_uris' => [
                'myapp://auth/callback',
                'exp://auth/callback',
            ],
            'scopes' => ['openid', 'profile', 'email'],
            'confidential' => false,
            'active' => true,
        ]);

        $this->command->info('OAuth clients created successfully.');
        $this->command->info('DS1 Client ID: ds1_test_client');
        $this->command->info('DS1 Client Secret: ds1_test_secret_12345');
    }
}

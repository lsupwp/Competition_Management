<?php

namespace App\Services;

use League\OAuth2\Client\Provider\Google;

class GoogleAuthService
{
    private Google $provider;

    public function __construct()
    {
        // Load environment variables if not already loaded
        if (!isset($_ENV['GOOGLE_CLIENT_ID'])) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
            $dotenv->load();
        }
        
        $this->provider = new Google([
            'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirectUri' => $_ENV['GOOGLE_REDIRECT_URI'],
        ]);
    }

    public function getAuthUrl(): string
    {
        return $this->provider->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile']
        ]);
    }

    public function authenticate(string $code): ?array
    {
        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            $user = $this->provider->getResourceOwner($token);

            return [
                'google_id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'avatar_url' => $user->getAvatar(),
                'email_verified' => $user->toArray()['email_verified'] ?? false,
            ];
        } catch (\Exception $e) {
            error_log('Google Auth Error: ' . $e->getMessage());
            return null;
        }
    }
}

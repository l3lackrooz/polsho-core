<?php

namespace App\Domain\Market\Infrastructure\Notifications;

use App\Domain\Market\Contracts\FcmAccessTokenProvider;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

class GoogleFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?string $cachedToken = null;

    private int $expiresAt = 0;

    public function __construct(private readonly ConfigRepository $config) {}

    public function token(): string
    {
        if ($this->cachedToken !== null && $this->expiresAt > time()) {
            return $this->cachedToken;
        }

        $credentialsPath = (string) $this->config->get('services.fcm.credentials', '');
        $credentials = $credentialsPath !== ''
            ? new ServiceAccountCredentials(self::SCOPE, $credentialsPath)
            : ApplicationDefaultCredentials::getCredentials(self::SCOPE);
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return an FCM access token.');
        }

        $expiresIn = max(120, (int) ($token['expires_in'] ?? 3600));
        $this->cachedToken = $accessToken;
        $this->expiresAt = time() + $expiresIn - 60;

        return $accessToken;
    }
}

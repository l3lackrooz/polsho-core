<?php

namespace App\Services\BotMessaging;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

class BotMessagingManager
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly HttpFactory $http,
    ) {}

    public function client(BotPlatform|string $platform): TelegramLikeBotClient
    {
        $platform = $this->normalizePlatform($platform);
        $config = (array) $this->config->get(sprintf('services.%s_bot', $platform->value), []);
        $baseUrl = (string) ($config['base_url'] ?? '');
        $token = (string) ($config['token'] ?? '');
        $timeout = (int) ($config['timeout'] ?? 10);

        if ($baseUrl === '' || $token === '') {
            throw new InvalidArgumentException(sprintf('Missing bot configuration for [%s].', $platform->value));
        }

        return new TelegramLikeBotClient($this->http, $baseUrl, $token, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendMessage(BotPlatform|string $platform, int|string $chatId, string $text, array $options = []): array
    {
        return $this->client($platform)->sendMessage($chatId, $text, $options);
    }

    /**
     * @param array<int, array{platform: BotPlatform|string, chat_id: int|string, options?: array<string, mixed>}> $targets
     * @return array<int, array<string, mixed>>
     */
    public function broadcastMessage(array $targets, string $text): array
    {
        $responses = [];

        foreach ($targets as $target) {
            $responses[] = $this->sendMessage(
                $target['platform'],
                $target['chat_id'],
                $text,
                $target['options'] ?? [],
            );
        }

        return $responses;
    }

    private function normalizePlatform(BotPlatform|string $platform): BotPlatform
    {
        if ($platform instanceof BotPlatform) {
            return $platform;
        }

        return BotPlatform::from(strtolower($platform));
    }
}

<?php

namespace App\Services\BotMessaging;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class TelegramLikeBotClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        return $this->request('sendMessage', array_merge($options, [
            'chat_id' => $chatId,
            'text' => $text,
        ]));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendPhoto(int|string $chatId, string $photo, array $options = []): array
    {
        return $this->request('sendPhoto', array_merge($options, [
            'chat_id' => $chatId,
            'photo' => $photo,
        ]));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendDocument(int|string $chatId, string $document, array $options = []): array
    {
        return $this->request('sendDocument', array_merge($options, [
            'chat_id' => $chatId,
            'document' => $document,
        ]));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendChatAction(int|string $chatId, string $action, array $options = []): array
    {
        return $this->request('sendChatAction', array_merge($options, [
            'chat_id' => $chatId,
            'action' => $action,
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request(string $method, array $payload = []): array
    {
        $response = $this->client()
            ->post(sprintf('bot%s/%s', $this->token, $method), $this->clean($payload))
            ->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException(sprintf('Invalid bot API response for [%s].', $method));
        }

        if (($data['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($data['description'] ?? sprintf('Bot API call [%s] failed.', $method)));
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function clean(array $payload): array
    {
        $cleaned = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->clean($value);
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}

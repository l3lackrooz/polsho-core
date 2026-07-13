<?php

namespace Tests\Unit;

use App\Services\BotMessaging\BotMessagingManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotMessagingManagerTest extends TestCase
{
    public function test_it_can_send_a_message_via_telegram_configuration(): void
    {
        Config::set('services.telegram_bot', [
            'base_url' => 'https://api.telegram.org',
            'token' => 'telegram-token',
            'timeout' => 10,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123],
            ]),
        ]);

        $response = app(BotMessagingManager::class)->sendMessage('telegram', '1001', 'Hello Telegram');

        $this->assertTrue($response['ok']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottelegram-token/sendMessage'
                && $request['chat_id'] === '1001'
                && $request['text'] === 'Hello Telegram';
        });
    }

    public function test_it_can_broadcast_one_message_to_multiple_platforms(): void
    {
        Config::set('services.telegram_bot', [
            'base_url' => 'https://api.telegram.org',
            'token' => 'telegram-token',
            'timeout' => 10,
        ]);

        Config::set('services.bale_bot', [
            'base_url' => 'https://tapi.bale.ai',
            'token' => 'bale-token',
            'timeout' => 10,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
            'https://tapi.bale.ai/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 2],
            ]),
        ]);

        $responses = app(BotMessagingManager::class)->broadcastMessage([
            ['platform' => 'telegram', 'chat_id' => '1001'],
            ['platform' => 'bale', 'chat_id' => '2002'],
        ], 'Shared alert');

        $this->assertCount(2, $responses);
        Http::assertSentCount(2);
    }
}

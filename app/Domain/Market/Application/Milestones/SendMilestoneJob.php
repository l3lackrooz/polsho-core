<?php

namespace App\Domain\Market\Application\Milestones;

use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\Services\PushNotificationTargetResolver;
use App\Domain\Market\Application\Services\PushProviderRegistry;
use App\Domain\Market\Infrastructure\Persistence\Models\PushDevice;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendMilestoneJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public int $tries = 1; // The persistent outbox controls bounded retries.

    public int $timeout = 45;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(config('queue.queues.notifications'));
    }

    public function handle(PushProviderRegistry $providers, PushNotificationTargetResolver $resolver): void
    {
        $delivery = DB::transaction(function () {
            $row = MilestoneDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $row || in_array($row->status, ['sent', 'failed', 'skipped'])
                || $row->available_at?->isFuture() || $row->lease_until?->isFuture()) {
                return null;
            }
            $event = $row->event;
            if (! $event || $event->expires_at->isPast() || ! $event->rule?->enabled || $event->revision != $event->rule->revision) {
                $row->update(['status' => 'skipped', 'error' => 'Expired or rule paused/changed.']);

                return null;
            }
            if ($row->attempts >= 3) {
                $row->update(['status' => 'failed', 'error' => 'Delivery attempt limit reached.']);

                return null;
            }
            $row->update(['status' => 'sending', 'lease_until' => now()->addSeconds(60), 'attempts' => $row->attempts + 1]);

            return $row;
        });
        if (! $delivery) {
            return;
        }
        try {
            $user = User::find($delivery->user_id);
            $target = $user?->market_milestones_enabled ? collect($resolver->forUser($user))->first(fn ($target) => $target->provider === $delivery->provider
                && hash('sha256', $target->address) === $delivery->target_hash) : null;
            if (! $target) {
                $delivery->update(['status' => 'skipped', 'lease_until' => null, 'error' => 'Device no longer eligible.']);

                return;
            }
            $event = $delivery->event;
            $fa = str_starts_with(strtolower($delivery->locale), 'fa');
            $level = rtrim(rtrim(number_format((float) $event->level, 8, '.', ','), '0'), '.');
            $price = rtrim(rtrim(number_format((float) $event->price, 8, '.', ','), '0'), '.');
            $unit = $event->quote_unit === 'IRT' ? ($fa ? 'تومان' : 'toman') : $event->quote_unit;
            $arrow = $event->direction === 'up' ? '↑' : '↓';
            $time = $event->created_at->copy()->timezone($fa ? 'Asia/Tehran' : 'UTC')->format('H:i');
            $mode = match ($event->price_source) {
                'best_buy' => $fa ? 'بهترین قیمت خرید' : 'best buy',
                'best_sell' => $fa ? 'بهترین قیمت فروش' : 'best sell',
                default => $fa ? 'آخرین قیمت' : 'last price',
            };
            $source = $event->provider_name ?: $event->provider;
            $title = $fa ? "{$mode} {$event->symbol} به {$level} {$unit} رسید {$arrow}" : "{$event->symbol} {$mode} crossed {$level} {$unit} {$arrow}";
            $body = $fa ? "{$source}: {$price} {$unit} · {$time} تهران" : "{$source}: {$price} {$unit} · {$time} UTC";
            $matches = $event->matches ?? [];
            if (count($matches) > 1) {
                $title = $fa ? "بهترین قیمت‌های {$event->symbol} از {$level} {$unit} عبور کردند {$arrow}"
                    : "{$event->symbol} best prices crossed {$level} {$unit} {$arrow}";
                $lines = [];
                foreach ($matches as $match) {
                    $label = $match['price_source'] === 'best_buy' ? ($fa ? 'خرید' : 'Buy') : ($fa ? 'فروش' : 'Sell');
                    $observed = rtrim(rtrim(number_format((float) $match['price'], 8, '.', ','), '0'), '.');
                    $exchange = $match['provider_name'] ?: $match['provider'];
                    $lines[] = "{$label} · {$exchange}: {$observed} {$unit}";
                }
                $body = implode(' | ', $lines)." · {$time}";
            }
            if ($fa) {
                $title = strtr($title, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
                $body = strtr($body, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
            }
            $result = $providers->provider($target->provider)->send($target, new PushNotificationMessage(
                title: $title, body: $body,
                data: ['type' => 'market_milestone', 'event_id' => (string) $event->id,
                    'instrument' => $event->symbol, 'price_source' => $event->price_source,
                    'matches' => $event->matches ?? [], 'provider' => $event->provider, 'provider_market_id' => $event->provider_market_id, 'price' => $event->price, 'level' => $event->level, 'direction' => $event->direction,
                    'quote_timestamp' => (string) $event->quote_timestamp, 'route' => '/', 'schema_version' => 1],
                deepLink: 'polsho://', expiresAt: $event->expires_at->timestamp,
            ));
            if ($result->invalidTarget && $target->pushDeviceId) {
                PushDevice::whereKey($target->pushDeviceId)->where('token_hash', $delivery->target_hash)->update([
                    'enabled' => false, 'provider_token' => null, 'token_hash' => null, 'invalidated_at' => now(),
                ]);
            }
            $delivery->update(['status' => $result->status, 'provider_message_id' => $result->providerMessageId,
                'error' => $result->error, 'lease_until' => null]);
        } catch (Throwable $error) {
            $delivery->update(['status' => $delivery->attempts >= 3 ? 'failed' : 'pending',
                'available_at' => now()->addSeconds($delivery->attempts === 1 ? 10 : 60),
                'lease_until' => null, 'error' => mb_substr($error->getMessage(), 0, 1000)]);
        }
    }
}

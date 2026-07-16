<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PriceAlertSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_alert_tables_expose_the_flutter_alert_contract(): void
    {
        $this->assertTrue(Schema::hasTable('price_alerts'));
        $this->assertTrue(Schema::hasColumns('price_alerts', [
            'instrument_id',
            'provider_market_id',
            'scope',
            'condition',
            'target_price',
            'status',
            'repeat',
            'notify_push',
            'notify_in_app',
            'last_triggered_at',
        ]));
        $this->assertTrue(Schema::hasColumns('price_alert_events', [
            'price_alert_id',
            'type',
            'payload',
            'occurred_at',
        ]));
        $this->assertTrue(Schema::hasColumns('push_devices', [
            'user_id',
            'installation_id',
            'platform',
            'provider',
            'provider_token',
            'token_hash',
            'enabled',
            'last_seen_at',
            'invalidated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('price_alert_push_deliveries', [
            'notification_delivery_id',
            'push_device_id',
            'platform',
            'provider',
            'provider_target',
            'target_hash',
            'status',
            'attempts',
            'provider_message_id',
            'error',
            'sent_at',
        ]));
    }
}

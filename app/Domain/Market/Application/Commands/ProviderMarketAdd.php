<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Actions\CreateProviderMarket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

class ProviderMarketAdd extends Command
{
    protected $signature = 'provider:market:add';
    protected $description = 'Add a new provider market';

    public function handle()
    {
        $this->info("── Add New Provider Market ──");

        // Get active providers and instruments
        $providers = $this->getProviders();
        $instruments = $this->getInstruments();

        // Check if available
        if (empty($providers) || empty($instruments)) {
            $this->error('Not enough available providers or instruments.');
            return Command::FAILURE;
        }

        // Select provider
        $providerId = select(
            label: 'Select Provider',
            options: $this->getProviders()
        );

        // Select instrument
        $instrumentId = select(
            label: 'Select Instrument',
            options: $this->getInstruments()
        );

        // Remote symbol
        $remoteSymbol = text(
            label: 'Remote Symbol',
            placeholder: 'e.g. BTCUSDT, btc_usdt, btc-irr',
            validate: fn ($v) =>
                !empty($v)
                ? null
                : 'Remote symbol cannot be empty.'
        );

        // Preview
        $this->info("\nPreview\n");

        table(
            headers: ['Field', 'Value'],
            rows: [
                ['Provider', $providerId],
                ['Instrument', $instrumentId],
                ['Remote Symbol', $remoteSymbol],
                ['Status', 'active']
            ]
        );

        if (!confirm('Create this provider market?')) {
            $this->warn('Cancelled.');
            return Command::SUCCESS;
        }

        // Insert into database
        try {
            app(CreateProviderMarket::class)->execute([
                'provider_id' => $providerId,
                'instrument_id' => $instrumentId,
                'remote_symbol' => $remoteSymbol,
                'status' => 'active',
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info("Provider market for '$remoteSymbol' created successfully.");
        return Command::SUCCESS;
    }

    private function getProviders()
    {
        return DB::table('market_providers')
            ->where('status', 'active')
            ->pluck('name','id')
            ->toArray(); // پیام خطای مناسب در صورت عدم وجود
    }

    private function getInstruments()
    {
        return DB::table('instruments')
            ->where('status', 'active')
            ->pluck('symbol','id')
            ->toArray(); // پیام خطای مناسب در صورت عدم وجود
    }
}

<?php

namespace App\Domain\Market\Application\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;

class InstrumentCreate extends Command
{
    protected $signature = 'market:instrument:add';
    protected $description = 'Create trading instrument';

    public function handle()
    {
        info("── Create Instrument ──");

        $assets = $this->getAssets();

        if (count($assets) < 2) {
            error('Not enough active assets.');
            return Command::FAILURE;
        }

        // Map symbol → object so we can access IDs without extra queries
        $assetMap = $this->getAssetsMap();

        // Base asset
        $base = select(
            label: 'Select Base Asset',
            options: array_keys($assetMap)
        );

        // Quote assets (remove base)
        $quoteOptions = array_diff(array_keys($assetMap), [$base]);

        $quote = select(
            label: 'Select Quote Asset',
            options: array_values($quoteOptions)
        );

        $symbol = "{$base}-{$quote}";

        // Duplicate check
        $existing = DB::table('instruments')
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            error("Instrument $symbol already exists.");
            table(
                headers: ['Field', 'Value'],
                rows: [
                    ['ID', $existing->id],
                    ['Symbol', $existing->symbol],
                    ['Base', $existing->base_asset_id],
                    ['Quote', $existing->quote_asset_id],
                    ['Status', $existing->status],
                    ['Created At', $existing->created_at],
                ]
            );

            return Command::FAILURE;
        }

        // Preview
        info("\nPreview\n");

        table(
            headers: ['Field', 'Value'],
            rows: [
                ['Base', $base],
                ['Quote', $quote],
                ['Symbol', $symbol],
                ['Status', 'active']
            ]
        );

        if (!confirm('Create this instrument?')) {
            warning('Cancelled.');
            return Command::SUCCESS;
        }

        // Insert using preloaded map
        DB::table('instruments')->insert([
            'base_asset_id' => $assetMap[$base]->id,
            'quote_asset_id' => $assetMap[$quote]->id,
            'symbol'        => $symbol,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        info("✔ Instrument '$symbol' created successfully.");

        return Command::SUCCESS;
    }

    private function getAssets()
    {
        return DB::table('assets')
            ->where('status', 'active')
            ->pluck('symbol')
            ->toArray();
    }

    private function getAssetsMap()
    {
        return DB::table('assets')
            ->where('status', 'active')
            ->get()
            ->keyBy('symbol')
            ->toArray();
    }
}

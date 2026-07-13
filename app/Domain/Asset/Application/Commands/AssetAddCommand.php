<?php

namespace App\Domain\Asset\Application\Commands;

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

class AssetAddCommand extends Command
{
    protected $signature = 'asset:add';
    protected $description = 'Add a new asset (TUI version)';

    public function handle()
    {
        info('── Add New Asset ──');

        // Symbol
        $symbol = strtoupper(text(
            label: 'Asset Symbol',
            placeholder: 'e.g. BTC, USDT, IRR',
            validate: fn ($v) =>
                preg_match('/^[A-Za-z0-9]{2,20}$/', $v)
                ? null
                : 'Only letters/numbers. Min 2 chars.'
        ));

        // Duplicate check
        if (DB::table('assets')->where('symbol', $symbol)->exists()) {
            error("Asset '$symbol' already exists.");
            return Command::FAILURE;
        }

        // Optional name
        $name = text(
            label: 'Asset Name (optional)',
            placeholder: 'e.g. Bitcoin'
        );

        // Asset type
        $type = select(
            label: 'Asset Type',
            options: [
                'crypto'     => 'Crypto',
                'token'      => 'Token (ERC20, BEP20, etc)',
                'stablecoin' => 'Stablecoin',
                'fiat'       => 'Fiat currency',
                'commodity'  => 'Commodity',
            ],
            default: 'crypto'
        );

        // Precision
        $precision = text(
            label: 'Precision (decimals)',
            default: '8',
            validate: fn ($v) =>
                ctype_digit($v) && (int) $v >= 0 && (int) $v <= 18
                    ? null
                    : 'Must be a number between 0 and 18.'
        );

        // Status
        $status = select(
            label: 'Status',
            options: ['active', 'inactive'],
            default: 'active'
        );

        // Metadata JSON
        $metadataJson = text(
            label: 'Metadata JSON (optional)',
            placeholder: '{"contract_address": "0x...", "chain": "eth"}',
            validate: function ($value) {
                if ($value === '' || $value === null) {
                    return null;
                }
                return json_decode($value, true) !== null
                    ? null
                    : 'Invalid JSON format.';
            }
        );

        $metadata = $metadataJson ? json_decode($metadataJson, true) : null;

        // Preview
        info("\nPreview\n");

        table(
            headers: ['Field', 'Value'],
            rows: [
                ['Symbol', $symbol],
                ['Name', $name ?: '-'],
                ['Type', $type],
                ['Precision', $precision],
                ['Status', $status],
                ['Metadata', $metadata ? json_encode($metadata) : '-'],
            ]
        );

        if (!confirm('Create this asset?')) {
            warning('Cancelled.');
            return Command::SUCCESS;
        }

        // Save
        DB::table('assets')->insert([
            'symbol'     => $symbol,
            'name'       => $name ?: null,
            'type'       => CurrencyType::from($type),
            'precision'  => (int) $precision,
            'status'     => $status,
            'metadata'   => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        info("✔ Asset '$symbol' created successfully.");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use App\Domain\Market\Infrastructure\Persistence\MarketSnapshotWriter;
use App\Domain\Market\Infrastructure\Support\Processing\ProcessedMarketBatchStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessProviderQuotesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param array<int, array<string, mixed>> $quotes
     */
    public function __construct(
        private readonly array $quotes,
    ) {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(
        MarketSnapshotWriter $snapshots,
        LatestQuoteStore $store,
        ProcessedMarketBatchStore $processedBatches,
    ): void {
        $quotes = array_map(
            static fn (array $payload): QuoteDTO => QuoteDTO::fromArray($payload),
            $this->quotes,
        );

        if ($quotes === []) {
            return;
        }

        $processedBatches->remember($this->batchKey($quotes), function () use ($quotes, $snapshots, $store): void {
            $snapshots->insertMany($quotes);

            $touchedInstruments = [];

            foreach ($quotes as $quote) {
                $store->put($quote);
                $touchedInstruments[$quote->instrument] = true;
            }

            //BroadcastAggregatedQuotesJob::dispatch(array_keys($touchedInstruments));
//            AggregateInstrumentJob::dispatch($touchedInstruments);
            //dispatch(new AggregateInstrumentJob(array_keys($touchedInstruments)));
            foreach (array_keys($touchedInstruments) as $instrument) {
                dispatch(new AggregateInstrumentJob($instrument));
            }

        });
    }

    /**
     * @param array<int, QuoteDTO> $quotes
     */
    private function batchKey(array $quotes): string
    {
        return json_encode(
            array_map(
                static fn (QuoteDTO $quote): array => $quote->toArray(),
                $quotes,
            ),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}

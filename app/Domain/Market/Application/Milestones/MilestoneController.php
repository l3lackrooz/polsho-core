<?php

namespace App\Domain\Market\Application\Milestones;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MilestoneController extends Controller
{
    use RespondsWithApi;

    public function index()
    {
        return $this->respond(MilestoneRule::with('instrument.quoteAsset')
            ->orderByDesc('updated_at')->get()->map(fn ($rule) => $this->present($rule)));
    }

    public function show(Instrument $instrument)
    {
        $rule = MilestoneRule::where('instrument_id', $instrument->id)->first();

        return $this->respond(['rule' => $rule ? $this->present($rule) : null,
            'instrument' => $instrument->load('quoteAsset'),
            'audience' => 'Registered users with enabled push devices and Market milestone alerts turned on. Anonymous installations are excluded.']);
    }

    public function update(Request $request, Instrument $instrument)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'price_source' => ['prohibited'],
            'provider_market_id' => ['prohibited'],
            'step' => ['required', 'numeric', 'gt:0', 'max:999999999999999', 'regex:/^\d{1,15}(\.\d{1,8})?$/'],
            'direction' => ['required', Rule::in(['both', 'up', 'down'])],
            'cooldown_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'confirmation_quotes' => ['required', 'integer', 'min:2', 'max:5'],
        ]);
        $data['provider_market_id'] = null;
        $data['price_source'] = 'best_market';
        abort_if($data['enabled'] && $instrument->status !== 'active', 422, 'Activate the instrument first.');
        $rule = DB::transaction(function () use ($instrument, $data, $request) {
            // Lock the instrument as well: concurrent first-time creation has no rule row to lock yet.
            Instrument::whereKey($instrument->id)->lockForUpdate()->firstOrFail();
            $rule = MilestoneRule::where('instrument_id', $instrument->id)->lockForUpdate()->first()
                ?? new MilestoneRule(['instrument_id' => $instrument->id, 'revision' => 0]);
            $rule->fill($data);
            if (! $rule->exists || $rule->isDirty()) {
                $rule->state = null;
                $rule->configured_at = now()->getTimestampMs();
                $rule->revision++;
                $rule->updated_by = $request->user()->id;
                $rule->save();
            }

            return $rule;
        });

        return $this->respond($this->present($rule->fresh()));
    }

    public function events(Request $request, Instrument $instrument)
    {
        $rule = MilestoneRule::where('instrument_id', $instrument->id)->first();

        return $this->respondPaginated(MilestoneEvent::where('rule_id', $rule?->id ?? 0)
            ->withCount(['deliveries', 'deliveries as accepted_count' => fn ($q) => $q->where('status', 'sent'),
                'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
                'deliveries as skipped_count' => fn ($q) => $q->where('status', 'skipped')])
            ->orderByDesc('id')->paginate(20));
    }

    private function present(MilestoneRule $rule): array
    {
        $prices = [];
        $step = BigDecimal::of($rule->step);
        foreach (['best_buy', 'best_sell'] as $side) {
            $track = $rule->state[$side] ?? [];
            $anchor = isset($track['anchor']) ? BigDecimal::of($track['anchor']) : null;
            $prices[] = [...$track, 'price_source' => $side,
                'next_up' => $anchor ? (string) $anchor->dividedBy($step, 0, RoundingMode::FLOOR)->plus(1)->multipliedBy($step) : null,
                'next_down' => $anchor ? (string) $anchor->dividedBy($step, 0, RoundingMode::CEILING)->minus(1)->multipliedBy($step) : null,
                'stale' => ! isset($track['timestamp']) || $track['timestamp'] < now()->subMinute()->getTimestampMs()];
        }

        return [...$rule->attributesToArray(),
            'instrument' => $rule->instrument->only(['id', 'symbol', 'status']), 'prices' => $prices];
    }
}

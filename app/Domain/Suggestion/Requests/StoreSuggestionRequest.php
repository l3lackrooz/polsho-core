<?php

namespace App\Domain\Suggestion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['add_instrument', 'instrument_on_exchange', 'add_exchange'])],
            'subject' => ['required', 'string', 'max:120'],
            // Only meaningful for an instrument request.
            'market_kind' => ['nullable', Rule::in(['crypto', 'fiat', 'gold', 'other'])],
            // Required as the target exchange when asking to list an instrument
            // on a specific exchange; optional (a preferred exchange) otherwise.
            'exchange' => ['nullable', 'string', 'max:120', Rule::requiredIf($this->input('type') === 'instrument_on_exchange')],
            'website' => ['nullable', 'url', 'max:2048'],
            'note' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

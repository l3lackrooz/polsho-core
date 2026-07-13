<?php

namespace App\Domain\Market\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:market_providers,id'],
            'instrument_id' => [
                'required',
                'integer',
                'exists:instruments,id',
                // An instrument can only be assigned to a provider once
                Rule::unique('provider_markets', 'instrument_id')
                    ->where('provider_id', $this->integer('provider_id')),
            ],
            'remote_symbol' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instrument_id.unique' => 'This instrument is already assigned to the selected provider.',
        ];
    }
}

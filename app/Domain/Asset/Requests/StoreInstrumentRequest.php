<?php

namespace App\Domain\Asset\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstrumentRequest extends FormRequest
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
            'base_asset_id' => ['required', 'integer', 'exists:assets,id'],
            'quote_asset_id' => ['required', 'integer', 'exists:assets,id', 'different:base_asset_id'],
            'symbol' => ['required', 'string', 'max:64', 'unique:instruments,symbol'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

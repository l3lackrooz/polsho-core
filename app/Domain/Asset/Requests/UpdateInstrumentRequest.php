<?php

namespace App\Domain\Asset\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstrumentRequest extends FormRequest
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
            'base_asset_id' => ['sometimes', 'integer', 'exists:assets,id'],
            'quote_asset_id' => ['sometimes', 'integer', 'exists:assets,id', 'different:base_asset_id'],
            'symbol' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('instruments', 'symbol')->ignore($this->route('instrument')),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

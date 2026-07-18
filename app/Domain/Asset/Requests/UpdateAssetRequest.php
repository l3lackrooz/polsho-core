<?php

namespace App\Domain\Asset\Requests;

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
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
            'symbol' => [
                'sometimes',
                'string',
                'max:32',
                Rule::unique('assets', 'symbol')->ignore($this->route('asset')),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'precision' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'type' => ['sometimes', Rule::enum(CurrencyType::class)],
            'is_base_currency' => ['sometimes', 'boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

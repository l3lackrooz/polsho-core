<?php

namespace App\Domain\Asset\Requests;

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
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
            'symbol' => ['required', 'string', 'max:32', 'unique:assets,symbol'],
            'name' => ['required', 'string', 'max:255'],
            'precision' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'type' => ['sometimes', Rule::enum(CurrencyType::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

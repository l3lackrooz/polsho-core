<?php

namespace App\Domain\Market\Requests;

use App\Domain\Market\Actions\ListAvailableDrivers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarketProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:market_providers,name'],
            'driver' => [
                'required',
                'string',
                'max:255',
                'unique:market_providers,driver',
                // Only drivers that actually ship with the codebase
                Rule::in(app(ListAvailableDrivers::class)->classes()),
            ],
            'slug' => ['nullable', 'string', 'max:255', 'unique:market_providers,slug'],
            'base_url' => ['required', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'is_default' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'config' => ['nullable', 'array'],
        ];
    }
}

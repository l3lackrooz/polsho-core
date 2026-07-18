<?php

namespace App\Domain\Market\Requests;

use App\Domain\Market\Actions\ListAvailableDrivers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketProviderRequest extends FormRequest
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
        $provider = $this->route('provider');

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('market_providers', 'name')->ignore($provider),
            ],
            'driver' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('market_providers', 'driver')->ignore($provider),
                // Only drivers that actually ship with the codebase
                Rule::in(app(ListAvailableDrivers::class)->classes()),
            ],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('market_providers', 'slug')->ignore($provider),
            ],
            'base_url' => ['sometimes', 'url', 'max:255'],
            'homepage_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'is_default' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
        ];
    }
}

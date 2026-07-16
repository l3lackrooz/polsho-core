<?php

namespace App\Domain\Market\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['installation_id' => $this->route('installationId')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $providers = match ($this->input('platform')) {
            'android' => ['pushe'],
            'ios' => ['fcm'],
            default => [],
        };

        return [
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'provider' => ['required', Rule::in($providers)],
            'provider_token' => [
                'nullable',
                'string',
                'max:4096',
                Rule::requiredIf($this->input('provider') === 'fcm'),
                Rule::prohibitedIf($this->input('provider') === 'pushe'),
            ],
            'app_version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
        ];
    }
}

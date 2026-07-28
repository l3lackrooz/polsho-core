<?php

namespace App\Domain\Market\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertLiveActivityPushTokenRequest extends FormRequest
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
        return [
            'installation_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['push_to_start', 'activity_update'])],
            'activity_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->input('kind') === 'activity_update'),
                Rule::prohibitedIf($this->input('kind') === 'push_to_start'),
            ],
            'price_alert_id' => [
                'nullable',
                'integer',
                'exists:price_alerts,id',
                Rule::prohibitedIf($this->input('kind') === 'push_to_start'),
            ],
            'token' => ['required', 'string', 'max:4096'],
        ];
    }
}

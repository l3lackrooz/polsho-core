<?php

namespace App\Domain\Market\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reassigning to another provider/instrument is intentionally not
     * supported — detach and re-add instead.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remote_symbol' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

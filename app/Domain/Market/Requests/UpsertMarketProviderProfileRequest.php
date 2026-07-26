<?php

namespace App\Domain\Market\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMarketProviderProfileRequest extends FormRequest
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
        $localizedKeys = implode(',', config('content_locales.supported', ['fa', 'en']));

        return [
            'type' => ['sometimes', Rule::in(['exchange', 'reference_source'])],
            'publication_status' => ['sometimes', Rule::in(['draft', 'published'])],
            'summary' => ['nullable', 'array:'.$localizedKeys],
            'summary.*' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'array:'.$localizedKeys],
            'description.*' => ['nullable', 'string', 'max:30000'],
            'seo_title' => ['nullable', 'array:'.$localizedKeys],
            'seo_title.*' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'array:'.$localizedKeys],
            'seo_description.*' => ['nullable', 'string', 'max:320'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'kyc_required' => ['nullable', 'boolean'],
            'fee_url' => ['nullable', 'url', 'max:2048'],
            'support_url' => ['nullable', 'url', 'max:2048'],
            'terms_url' => ['nullable', 'url', 'max:2048'],
            'android_app_url' => ['nullable', 'url', 'max:2048'],
            'ios_app_url' => ['nullable', 'url', 'max:2048'],
            'facts' => ['nullable', 'array', 'max:50'],
            'facts.*' => ['array'],
            'facts.*.key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'facts.*.label' => ['required_with:facts', 'array:'.$localizedKeys],
            'facts.*.label.*' => ['nullable', 'string', 'max:255'],
            'facts.*.value' => ['required_with:facts', 'array:'.$localizedKeys],
            'facts.*.value.*' => ['nullable', 'string', 'max:2000'],
            'sources' => ['nullable', 'array', 'max:30'],
            'sources.*' => ['array'],
            'sources.*.label' => ['required_with:sources', 'array:'.$localizedKeys],
            'sources.*.label.*' => ['nullable', 'string', 'max:255'],
            'sources.*.url' => ['required_with:sources', 'url', 'max:2048'],
            'sources.*.published_at' => ['nullable', 'date'],
            'last_reviewed_at' => ['nullable', 'date'],
        ];
    }
}

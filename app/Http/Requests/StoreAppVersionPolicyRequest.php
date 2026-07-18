<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppVersionPolicyRequest extends FormRequest
{
    protected const VERSION_PATTERN = '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(['android', 'ios']), Rule::unique('app_version_policies', 'platform')],
            'latest_version' => ['required', 'string', 'max:32', 'regex:'.self::VERSION_PATTERN],
            'latest_build' => ['required', 'integer', 'min:1'],
            'minimum_version' => ['required', 'string', 'max:32', 'regex:'.self::VERSION_PATTERN],
            'minimum_build' => ['required', 'integer', 'min:1'],
            'store_url' => ['required', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

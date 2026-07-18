<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAppVersionPolicyRequest extends StoreAppVersionPolicyRequest
{
    public function rules(): array
    {
        $policy = $this->route('version_policy');

        return [
            'platform' => [
                'sometimes',
                Rule::in(['android', 'ios']),
                Rule::unique('app_version_policies', 'platform')->ignore($policy),
            ],
            'latest_version' => ['sometimes', 'string', 'max:32', 'regex:'.self::VERSION_PATTERN],
            'latest_build' => ['sometimes', 'integer', 'min:1'],
            'minimum_version' => ['sometimes', 'string', 'max:32', 'regex:'.self::VERSION_PATTERN],
            'minimum_build' => ['sometimes', 'integer', 'min:1'],
            'store_url' => ['sometimes', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

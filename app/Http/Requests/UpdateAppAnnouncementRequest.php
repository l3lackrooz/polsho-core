<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAppAnnouncementRequest extends StoreAppAnnouncementRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'nullable', Rule::in(['android', 'ios'])],
            'presentation' => ['sometimes', Rule::in(['banner', 'modal'])],
            'type' => ['sometimes', Rule::in(['info', 'warning', 'critical'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'title_translations' => ['sometimes', 'nullable', 'array', 'max:6'],
            'title_translations.*' => ['nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'string', 'max:4000'],
            'message_translations' => ['sometimes', 'nullable', 'array', 'max:6'],
            'message_translations.*' => ['nullable', 'string', 'max:4000'],
            'action_label' => ['nullable', 'string', 'max:80', 'required_with:action_url'],
            'action_label_translations' => ['sometimes', 'nullable', 'array', 'max:6'],
            'action_label_translations.*' => ['nullable', 'string', 'max:80'],
            'action_url' => ['nullable', 'url', 'max:2048'],
            'is_dismissible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}

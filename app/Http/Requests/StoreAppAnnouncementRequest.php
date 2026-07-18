<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'presentation' => ['sometimes', Rule::in(['banner', 'modal'])],
            'type' => ['sometimes', Rule::in(['info', 'warning', 'critical'])],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4000'],
            'action_label' => ['nullable', 'string', 'max:80', 'required_with:action_url'],
            'action_url' => ['nullable', 'url', 'max:2048'],
            'is_dismissible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $current = $this->route('announcement');
            $isDismissible = $this->has('is_dismissible')
                ? $this->boolean('is_dismissible')
                : ($current?->is_dismissible ?? true);
            if ($isDismissible) {
                return;
            }

            $actionUrl = $this->input('action_url', $current?->action_url);
            $actionLabel = $this->input('action_label', $current?->action_label);
            if (! is_string($actionUrl) || $actionUrl === '') {
                $validator->errors()->add('action_url', 'An undismissable announcement needs an action URL.');
            }
            if (! is_string($actionLabel) || $actionLabel === '') {
                $validator->errors()->add('action_label', 'An undismissable announcement needs an action label.');
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\\+[1-9][0-9]{7,14}$/',
                Rule::unique('users', 'phone')->ignore($this->user()),
            ],
        ];
    }
}

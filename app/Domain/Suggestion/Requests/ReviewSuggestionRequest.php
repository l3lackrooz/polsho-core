<?php

namespace App\Domain\Suggestion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backoffice triage: an admin can only move a suggestion through its lifecycle
 * and attach an internal note — never rewrite what the user asked for.
 */
class ReviewSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['under_review', 'planned', 'added', 'declined'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

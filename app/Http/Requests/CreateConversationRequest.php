<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * // YB - 24-08-2026 code comment
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * // YB - 24-08-2026 code comment
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:direct,group'],
            'recipient_id' => ['required_if:type,direct', 'nullable', 'integer', 'exists:users,id', 'different:auth_user_id'],
            'title' => ['required_if:type,group', 'nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'participant_ids' => ['required_if:type,group', 'nullable', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id', 'different:auth_user_id'],
        ];
    }

    /**
     * Prepare inputs for validation.
     *
     * // YB - 24-08-2026 code comment
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'auth_user_id' => $this->user()?->id,
        ]);
    }
}

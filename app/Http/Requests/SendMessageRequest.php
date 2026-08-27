<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * // YB - 24-08-2026 code comment
     */
    public function authorize(): bool
    {
        return true; // Authorized via policy in service / controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * // YB - 24-08-2026 code comment
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $conversation = $this->route('conversation');
        $conversationId = $conversation instanceof \App\Models\Conversation ? $conversation->id : $conversation;

        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,zip,txt'],
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where(function ($query) use ($conversationId) {
                    if ($conversationId) {
                        $query->where('conversation_id', $conversationId);
                    }
                }),
            ],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * // YB - 24-08-2026 code comment
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Please enter a message or select an attachment to send.',
            'attachment.max' => 'The attachment size must not exceed 20MB.',
            'reply_to_id.exists' => 'The referenced message is invalid or belongs to another conversation.',
        ];
    }
}

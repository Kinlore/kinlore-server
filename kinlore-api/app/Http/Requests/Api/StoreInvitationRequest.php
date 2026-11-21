<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Admin access is checked by the `can:admin` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|email|max:255|unique:users,email',
            'role' => 'nullable|in:partner,family',
            'permissions' => 'nullable|array',
            'permissions.add_content' => 'boolean',
            'permissions.comment' => 'boolean',
            'permissions.like' => 'boolean',
            'permissions.download' => 'boolean',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ];
    }
}

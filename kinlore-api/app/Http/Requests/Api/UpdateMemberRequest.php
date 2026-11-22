<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
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
            'role' => 'nullable|in:admin,partner,family',
            'permissions' => 'nullable|array',
            'permissions.add_content' => 'boolean',
            'permissions.comment' => 'boolean',
            'permissions.like' => 'boolean',
            'permissions.download' => 'boolean',
            'password' => 'nullable|string|min:8|max:255',
        ];
    }
}

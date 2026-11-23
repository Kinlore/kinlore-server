<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Admin access is checked by the `can:admin` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['nullable', Rule::enum(Role::class)],
            'permissions' => 'nullable|array',
            'permissions.add_content' => 'boolean',
            'permissions.comment' => 'boolean',
            'permissions.like' => 'boolean',
            'permissions.download' => 'boolean',
            'password' => 'nullable|string|min:8|max:255',
        ];
    }
}

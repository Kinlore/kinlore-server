<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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
            'email' => 'nullable|email|max:255|unique:users,email',
            'role' => ['nullable', Rule::enum(Role::class)->only([Role::Partner, Role::Family])],
            'permissions' => 'nullable|array',
            'permissions.add_content' => 'boolean',
            'permissions.comment' => 'boolean',
            'permissions.like' => 'boolean',
            'permissions.download' => 'boolean',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ];
    }
}

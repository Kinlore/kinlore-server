<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Only invited users register here; the first admin is created on the web setup page.
     * Checked before validation, so the app gets the setup address even with an empty form.
     */
    public function authorize(): bool
    {
        return User::exists();
    }

    #[\Override]
    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Finish setup in a web browser at '.app(AuthService::class)->setupUrl());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'invite_token' => 'required|string|max:64',
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}

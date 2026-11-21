<?php

namespace App\Http\Requests\Api;

use App\Models\User;
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

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Finish setup in a web browser at ' . url('/setup'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'invite_token' => 'required|string|max:64',
        ];
    }
}

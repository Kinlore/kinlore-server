<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The first-admin form on the web setup page (docs/API_CONTRACT.md section 3).
 */
class SetupRequest extends FormRequest
{
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
            // The value itself is checked with hash_equals in AuthService::createFirstAdmin.
            'setup_token' => [filled(config('app.setup_token')) ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Services\FamilyService;
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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'family_name' => 'nullable|string|max:'.FamilyService::NAME_MAX_LENGTH,
            'password' => 'required|string|min:8|max:255|confirmed',
            // The value itself is checked with hash_equals in AuthService::createFirstAdmin.
            'setup_token' => [filled(config('app.setup_token')) ? 'required' : 'nullable', 'string', 'max:255'],
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

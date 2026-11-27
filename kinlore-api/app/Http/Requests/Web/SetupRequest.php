<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Services\FamilyService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The first-admin form on the web setup page (docs/reference/api.md section 3).
 */
class SetupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            // Built from first_name and last_name in prepareForValidation; the account keeps one name.
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            // The family name the app shows. Empty: "<first> <last> Family", as the page suggests.
            'friendly_name' => 'nullable|string|max:'.FamilyService::NAME_MAX_LENGTH,
            'password' => 'required|string|min:8|max:255|confirmed',
            // The value itself is checked with hash_equals in AuthService::createFirstAdmin.
            'setup_token' => 'required|string|max:255',
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
        $first = is_string($this->input('first_name')) ? $this->input('first_name') : '';
        $last = is_string($this->input('last_name')) ? $this->input('last_name') : '';
        $this->merge(['name' => trim((string) preg_replace('/\s+/', ' ', "$first $last"))]);
    }
}

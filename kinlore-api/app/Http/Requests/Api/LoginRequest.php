<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
            // Shown in the app's list of signed-in devices; empty becomes null (ConvertEmptyStringsToNull).
            'device_name' => ['nullable', 'string', 'max:60', 'not_regex:/[\x00-\x1F\x7F]/'],
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

<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class StoreKidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Kid::class);
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
            'date_of_birth' => 'required|date|before:today',
            'avatar_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ];
    }
}

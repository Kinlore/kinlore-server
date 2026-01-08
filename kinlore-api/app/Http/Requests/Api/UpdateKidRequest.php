<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $kid = $this->route('kid');

        return $kid instanceof Kid
            ? $this->user()->can('update', $kid)
            : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'avatar_color' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
        ];
    }
}

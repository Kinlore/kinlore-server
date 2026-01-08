<?php

namespace App\Http\Requests\Api;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Server::class);
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
            'settings' => 'nullable|array',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IndexMomentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'moment_type' => ['nullable', 'string', 'regex:/^(photo|video|story|milestone|growth)(,(photo|video|story|milestone|growth))*$/'],
            'liked' => 'nullable|in:true,false,1,0',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}

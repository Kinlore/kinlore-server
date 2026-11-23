<?php

namespace App\Http\Requests\Api;

use App\Enums\MomentType;
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
        $types = implode('|', array_column(MomentType::cases(), 'value'));

        return [
            'kid_id' => 'nullable|uuid|exists:kids,id',
            'moment_type' => ['nullable', 'string', "regex:/^({$types})(,({$types}))*$/"],
            'liked' => 'nullable|in:true,false,1,0',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}

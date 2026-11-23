<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\MomentType;
use Illuminate\Foundation\Http\FormRequest;

class IndexMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // A comma-separated list of types, so Rule::enum does not fit here.
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

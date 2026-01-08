<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class IndexPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $kid = $this->route('kid');

        return $kid instanceof Kid
            ? $this->user()->can('view', $kid)
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
            'per_page' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'device_asset_id' => 'nullable|string',
            'moment_type' => 'nullable|string|in:photo,video,story,milestone',
            'sort_direction' => 'nullable|in:asc,desc',
        ];
    }
}

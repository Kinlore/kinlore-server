<?php

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMomentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $moment = $this->route('moment');

        return $moment instanceof Moment
            ? $this->user()->can('update', $moment->kid)
            : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
        ];

        $moment = $this->route('moment');

        if ($moment instanceof Moment && $moment->moment_type === 'story') {
            $rules['text'] = 'nullable|string|max:50000';
        }

        if ($moment instanceof Moment && $moment->moment_type === 'milestone') {
            $rules['custom_title'] = 'nullable|string|max:255';
            $rules['custom_description'] = 'nullable|string|max:5000';
            $rules['milestone_date'] = 'nullable|date';
            $rules['notes'] = 'nullable|string|max:5000';
        }

        return $rules;
    }
}

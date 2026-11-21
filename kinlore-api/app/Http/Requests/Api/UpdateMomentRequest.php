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
            ? $this->user()->can('update', $moment)
            : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('moment')?->moment_type;

        $rules = [
            'title' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'exif_date' => 'sometimes|nullable|date',
        ];

        if ($type === 'milestone') {
            $rules['milestone_id'] = 'sometimes|required|string|exists:milestones,id';
        }

        if ($type === 'growth') {
            $rules += StoreMomentRequest::GROWTH_RULES;
            $rules['exif_date'] = 'sometimes|required|date';
        }

        return $rules;
    }

    /**
     * Older clients sent milestone_date and notes for milestones.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('milestone_date') && !$this->has('exif_date')) {
            $this->merge(['exif_date' => $this->input('milestone_date')]);
        }

        if ($this->has('notes') && !$this->has('description')) {
            $this->merge(['description' => $this->input('notes')]);
        }
    }
}

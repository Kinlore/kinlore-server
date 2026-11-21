<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'date_of_birth' => 'sometimes|nullable|date|before_or_equal:today',
            'due_date' => 'sometimes|nullable|date',
            'avatar_color' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
        ];
    }

    /**
     * The kid must keep a date of birth or a due date after the update.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $kid = $this->route('kid');
                $dob = $this->exists('date_of_birth') ? $this->input('date_of_birth') : $kid?->date_of_birth;
                $due = $this->exists('due_date') ? $this->input('due_date') : $kid?->due_date;

                if (empty($dob) && empty($due)) {
                    $validator->errors()->add('date_of_birth', 'A date of birth or a due date is required.');
                }
            },
        ];
    }
}

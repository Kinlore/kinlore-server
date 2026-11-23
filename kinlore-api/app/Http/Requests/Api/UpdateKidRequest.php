<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateKidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kid = $this->route('kid');

        return $kid instanceof Kid && $this->user()->can('update', $kid);
    }

    /** @return array<string, mixed> */
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
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
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

<?php

namespace App\Http\Requests\Api;

use App\Models\UserMilestone;
use Illuminate\Foundation\Http\FormRequest;

class ShowUserMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $userMilestone = $this->route('userMilestone');

        return $userMilestone instanceof UserMilestone
            ? $this->user()->can('view', $userMilestone->kid)
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
        ];
    }
}

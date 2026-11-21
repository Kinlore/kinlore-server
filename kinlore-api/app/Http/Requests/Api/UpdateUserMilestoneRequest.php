<?php

namespace App\Http\Requests\Api;

use App\Models\UserMilestone;
use App\Services\MilestoneService;
use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateUserMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $userMilestone = $this->route('userMilestone');

        return $userMilestone instanceof UserMilestone
            ? $this->user()->can('update', $userMilestone->kid)
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
            'custom_title' => 'nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->fails()) {
                return;
            }

            $iconId = $this->input('icon_image_id');
            if (!$iconId) {
                return;
            }

            $service = app(MilestoneService::class);

            if (!$service->validateIconImage($this->user(), $iconId)) {
                throw new HttpResponseException(ApiResponse::error('Invalid icon image', 422));
            }
        });
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use App\Services\MilestoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreUserMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $kidId = $this->input('kid_id');

        if (!$kidId) {
            return true;
        }

        $kid = Kid::find($kidId);

        if (!$kid) {
            return true;
        }

        return $this->user()->can('view', $kid);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kid_id' => 'required|uuid|exists:kids,id',
            'milestone_id' => 'nullable|string|exists:milestones,id',
            'custom_title' => 'required_without:milestone_id|nullable|string|max:255',
            'custom_description' => 'nullable|string|max:5000',
            'milestone_date' => 'required|date',
            'notes' => 'nullable|string|max:5000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
            'moment_id' => 'nullable|uuid|exists:moments,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->fails()) {
                return;
            }

            $service = app(MilestoneService::class);

            $milestoneId = $this->input('milestone_id');
            if ($milestoneId && !$service->validateMilestone($milestoneId)) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Invalid milestone',
                ], 422));
            }

            $iconId = $this->input('icon_image_id');
            if ($iconId && !$service->validateIconImage($this->user(), $iconId)) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Invalid icon image',
                ], 422));
            }

            $momentId = $this->input('moment_id');
            if ($momentId) {
                $kid = Kid::find($this->input('kid_id'));
                if ($kid && !$service->validateMoment($kid, $momentId)) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'message' => 'Invalid moment',
                    ], 422));
                }
            }
        });
    }
}

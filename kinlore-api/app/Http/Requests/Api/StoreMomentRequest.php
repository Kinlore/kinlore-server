<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class StoreMomentRequest extends FormRequest
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
        $hasFile = $this->hasFile('file');
        $momentType = $this->input('moment_type');

        $rules = [
            'kid_id' => 'required|uuid|exists:kids,id',
            'moment_type' => 'nullable|in:photo,video,story,milestone',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'exif_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'camera_make' => 'nullable|string|max:255',
            'camera_model' => 'nullable|string|max:255',
            'orientation' => 'nullable|integer|between:1,8',
        ];

        if ($hasFile || in_array($momentType, ['photo', 'video', null], true)) {
            $rules['file'] = 'required_without:moment_type|file|max:512000|mimes:jpeg,jpg,png,gif,webp,heic,heif,mp4,mov,avi,webm,mkv,m4v';
            $rules['device_asset_id'] = 'nullable|string|max:255';
        }

        if ($momentType === 'story') {
            $rules['description'] = 'required|string|max:5000';
        }

        if ($momentType === 'milestone') {
            $rules['milestone_id'] = 'required|string|exists:milestones,id';
            $rules['description'] = 'nullable|string|max:5000';
        }

        return $rules;
    }
}

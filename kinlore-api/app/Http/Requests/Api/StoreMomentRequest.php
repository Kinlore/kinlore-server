<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\MomentType;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMomentRequest extends FormRequest
{
    /** Growth measurements, also used by UpdateMomentRequest. */
    public const array GROWTH_RULES = [
        'height_cm' => 'sometimes|nullable|numeric|between:1,250',
        'weight_kg' => 'sometimes|nullable|numeric|between:0.1,200',
        'head_circumference_cm' => 'sometimes|nullable|numeric|between:1,100',
    ];

    public function authorize(): bool
    {
        if (! $this->user()->hasPermission('add_content') || ! $this->user()->can('create', Moment::class)) {
            return false;
        }

        $kid = Kid::find($this->input('kid_id'));

        return ! $kid || $this->user()->can('view', $kid);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasFile = $this->hasFile('file');
        $momentType = $this->input('moment_type');

        $rules = [
            'kid_id' => 'required|uuid|exists:kids,id',
            'moment_type' => ['nullable', Rule::enum(MomentType::class)],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'exif_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'camera_make' => 'nullable|string|max:255',
            'camera_model' => 'nullable|string|max:255',
            'orientation' => 'nullable|integer|between:1,8',
        ];

        if ($hasFile || in_array($momentType, [MomentType::Photo->value, MomentType::Video->value, null], true)) {
            $rules['file'] = 'required|file|max:512000|mimes:jpeg,jpg,png,gif,webp,heic,heif,mp4,mov,avi,webm,mkv,m4v';
            $rules['device_asset_id'] = 'nullable|string|max:255';
        }

        if ($momentType === MomentType::Story->value) {
            $rules['description'] = 'required|string|max:5000';
        }

        if ($momentType === MomentType::Milestone->value) {
            $rules['milestone_id'] = 'required|string|exists:milestones,id';
            $rules['description'] = 'nullable|string|max:5000';
        }

        if ($momentType === MomentType::Growth->value) {
            $rules['exif_date'] = 'required|date';
            $rules += self::GROWTH_RULES;
            $rules['height_cm'] = 'required_without_all:weight_kg,head_circumference_cm|nullable|numeric|between:1,250';
        }

        return $rules;
    }

    #[\Override]
    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('You do not have permission to add content.');
    }
}

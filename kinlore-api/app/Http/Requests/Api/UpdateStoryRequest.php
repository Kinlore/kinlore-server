<?php

namespace App\Http\Requests\Api;

use App\Models\Story;
use App\Services\StoryService;
use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateStoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $story = $this->route('story');

        return $story instanceof Story
            ? $this->user()->can('view', $story->kid)
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
            'title' => 'sometimes|required|string|max:255',
            'text' => 'sometimes|required|string|max:50000',
            'icon_image_id' => 'nullable|uuid|exists:media,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $iconId = $this->input('icon_image_id');

            if (!$iconId) {
                return;
            }

            $service = app(StoryService::class);

            if (!$service->validateIconImage($this->user(), $iconId)) {
                throw new HttpResponseException(ApiResponse::error('Invalid icon image', 422));
            }
        });
    }
}

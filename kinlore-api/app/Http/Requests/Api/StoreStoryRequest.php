<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use App\Services\StoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreStoryRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'text' => 'required|string|max:50000',
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
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Invalid icon image',
                ], 422));
            }
        });
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Story;
use Illuminate\Foundation\Http\FormRequest;

class ShowStoryRequest extends FormRequest
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
        ];
    }
}

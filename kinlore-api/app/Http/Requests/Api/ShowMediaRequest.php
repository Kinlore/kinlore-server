<?php

namespace App\Http\Requests\Api;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

class ShowMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media
            ? $this->user()->can('view', $media->kid)
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

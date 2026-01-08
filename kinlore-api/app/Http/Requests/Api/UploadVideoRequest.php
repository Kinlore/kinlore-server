<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm',
                'max:' . (env('VIDEO_MAX_SIZE_MB', 500) * 1024),
            ],
            'original_filename' => 'nullable|string|max:255',
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class UploadedMediaIdentifiersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $kid = $this->route('kid');

        return $kid instanceof Kid
            ? $this->user()->can('view', $kid)
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

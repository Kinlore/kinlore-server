<?php

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Foundation\Http\FormRequest;

class ServePhotoFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $moment = $this->route('moment');

        return $moment instanceof Moment
            ? $this->user()->can('view', $moment->kid)
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

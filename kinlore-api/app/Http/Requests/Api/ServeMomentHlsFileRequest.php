<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServeMomentHlsFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'regex:/^[a-zA-Z0-9_-]+\.(m3u8|ts)$/'],
        ];
    }

    /** The filename comes from the route, not the query string. */
    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge(['filename' => $this->route('filename')]);
    }

    #[\Override]
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ApiResponse::error('Invalid filename format', 400));
    }
}

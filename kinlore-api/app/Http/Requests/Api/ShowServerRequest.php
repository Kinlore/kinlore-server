<?php

namespace App\Http\Requests\Api;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class ShowServerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route('server');

        return $server instanceof Server
            ? $this->user()->can('view', $server)
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

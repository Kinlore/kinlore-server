<?php

namespace App\Http\Requests\Api;

use App\Models\Invitation;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route('server');

        return $server instanceof Server
            ? $this->user()->can('create', [Invitation::class, $server])
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
            'role' => 'nullable|in:viewer,contributor',
            'email' => 'nullable|email',
            'expires_in_days' => 'nullable|integer|min:1|max:90',
        ];
    }
}

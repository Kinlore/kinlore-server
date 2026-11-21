<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PushSyncRequest extends FormRequest
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
            'changes' => 'required|array',
            'changes.kids' => 'sometimes|array',
            'changes.moments' => 'sometimes|array',
            'changes.*.created' => 'sometimes|array',
            'changes.*.updated' => 'sometimes|array',
            'changes.*.deleted' => 'sometimes|array',
            'lastPulledAt' => 'nullable|integer',
        ];
    }
}

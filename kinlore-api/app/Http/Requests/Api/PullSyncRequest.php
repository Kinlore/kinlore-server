<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PullSyncRequest extends FormRequest
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
            'lastPulledAt' => 'nullable|integer',
            'schemaVersion' => 'required|integer',
            'migration' => 'nullable',
            'cursor' => 'nullable|uuid',
            'limit' => 'nullable|integer|min:1|max:'.\App\Services\SyncService::PULL_PAGE_SIZE,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PushSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
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

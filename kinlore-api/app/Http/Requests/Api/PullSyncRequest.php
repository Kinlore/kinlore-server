<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Services\SyncService;
use Illuminate\Foundation\Http\FormRequest;

class PullSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lastPulledAt' => 'nullable|integer',
            'schemaVersion' => 'required|integer',
            'migration' => 'nullable',
            'cursor' => 'nullable|uuid',
            'limit' => 'nullable|integer|min:1|max:'.SyncService::PULL_PAGE_SIZE,
        ];
    }
}

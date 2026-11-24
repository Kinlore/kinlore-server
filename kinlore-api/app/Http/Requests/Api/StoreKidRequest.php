<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class StoreKidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Kid::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // A kid is born, expecting, or both (docs/API_CONTRACT.md section 5)
            // tomorrow: a parent east of UTC is already a day ahead of the server
            'date_of_birth' => 'required_without:due_date|nullable|date|before_or_equal:tomorrow',
            'due_date' => 'required_without:date_of_birth|nullable|date',
            'avatar_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ];
    }
}

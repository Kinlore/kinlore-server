<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Services\FamilyService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required|string|max:'.FamilyService::NAME_MAX_LENGTH];
    }
}

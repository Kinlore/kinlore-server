<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class IndexKidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Kid::class);
    }
}

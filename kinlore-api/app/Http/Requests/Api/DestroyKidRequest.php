<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Kid;
use Illuminate\Foundation\Http\FormRequest;

class DestroyKidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kid = $this->route('kid');

        return $kid instanceof Kid && $this->user()->can('delete', $kid);
    }
}

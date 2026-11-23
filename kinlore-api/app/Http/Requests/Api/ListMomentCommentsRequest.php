<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Foundation\Http\FormRequest;

class ListMomentCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $moment = $this->route('moment');

        return $moment instanceof Moment && $this->user()->can('view', $moment);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Foundation\Http\FormRequest;

class ServeMomentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $moment = $this->route('moment');

        if (! $moment instanceof Moment || ! $this->user()->can('view', $moment->kid)) {
            return false;
        }

        return $this->route('type') !== 'original' || $this->user()->hasPermission('download');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UnlikeMomentRequest extends FormRequest
{
    public function authorize(): bool|Response
    {
        $moment = $this->route('moment');

        if (! $moment instanceof Moment || ! $this->user()->can('view', $moment)) {
            return false;
        }

        return $this->user()->hasPermission('like') ?: Response::deny('You are not allowed to like moments.');
    }
}

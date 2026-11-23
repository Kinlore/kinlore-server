<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CommentMomentRequest extends FormRequest
{
    public function authorize(): bool|Response
    {
        $moment = $this->route('moment');

        if (! $moment instanceof Moment || ! $this->user()->can('view', $moment)) {
            return false;
        }

        return $this->user()->hasPermission('comment') ?: Response::deny('You are not allowed to comment on moments.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
        ];
    }
}

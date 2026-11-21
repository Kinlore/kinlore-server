<?php

namespace App\Http\Requests\Api;

use App\Models\Moment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class CommentMomentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $moment = $this->route('moment');

        if (!$moment instanceof Moment || !$this->user()->can('view', $moment)) {
            return false;
        }

        return $this->user()->hasPermission('comment') ?: Response::deny('You are not allowed to comment on moments.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
        ];
    }
}

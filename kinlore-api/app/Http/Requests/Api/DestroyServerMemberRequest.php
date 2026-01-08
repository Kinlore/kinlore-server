<?php

namespace App\Http\Requests\Api;

use App\Models\Server;
use App\Models\ServerMember;
use Illuminate\Foundation\Http\FormRequest;

class DestroyServerMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route('server');
        $member = $this->route('member');

        if (!$server instanceof Server || !$member instanceof ServerMember) {
            return false;
        }

        if ($member->server_id !== $server->id) {
            abort(404, 'Member not found in this server');
        }

        return $this->user()->can('delete', $member);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
        ];
    }
}

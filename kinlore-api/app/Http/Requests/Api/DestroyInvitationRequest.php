<?php

namespace App\Http\Requests\Api;

use App\Models\Invitation;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class DestroyInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route('server');
        $invitation = $this->route('invitation');

        if (!$server instanceof Server || !$invitation instanceof Invitation) {
            return false;
        }

        if ($invitation->server_id !== $server->id) {
            abort(404, 'Invitation not found in this server');
        }

        return $this->user()->can('delete', $invitation);
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

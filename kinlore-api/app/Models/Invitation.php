<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'token', 'email', 'role', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by',
    'can_add_content', 'can_comment', 'can_like', 'can_download',
])]
#[Hidden(['token'])]
class Invitation extends Model
{
    use HasUuids;

    #[\Override]
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'can_add_content' => 'boolean',
            'can_comment' => 'boolean',
            'can_like' => 'boolean',
            'can_download' => 'boolean',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /** Permissions as they will apply: only `family` members are limited (docs/API_CONTRACT.md section 1). */
    public function permissionsArray(): array
    {
        return array_combine(User::PERMISSIONS, array_map(
            fn (string $p): bool => $this->role->canManageContent() || (bool) $this->getAttribute("can_{$p}"),
            User::PERMISSIONS
        ));
    }

    /** Invitation item for the admin endpoints (docs/API_CONTRACT.md section 3). */
    public function toItem(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'permissions' => $this->permissionsArray(),
            'invited_by' => $this->inviter ? ['id' => $this->inviter->id, 'name' => $this->inviter->name] : null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    public function inviteLink(): string
    {
        return "kinlore://invite/{$this->token}?server=" . rawurlencode((string) config('app.url'));
    }

    public function inviteUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . "/invite/{$this->token}";
    }
}

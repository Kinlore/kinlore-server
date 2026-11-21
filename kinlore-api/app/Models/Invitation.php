<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'token', 'email', 'role', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by',
        'can_add_content', 'can_comment', 'can_like', 'can_download',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'can_add_content' => 'boolean',
        'can_comment' => 'boolean',
        'can_like' => 'boolean',
        'can_download' => 'boolean',
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /** Permissions as they will apply: only `family` members are limited (docs/API_CONTRACT.md section 1). */
    public function permissionsArray(): array
    {
        return array_combine(User::PERMISSIONS, array_map(
            fn ($p) => $this->role !== 'family' || (bool) $this->getAttribute("can_{$p}"),
            User::PERMISSIONS
        ));
    }

    /** Invitation item for the admin endpoints (docs/API_CONTRACT.md section 3). */
    public function toItem(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'permissions' => $this->permissionsArray(),
            'invited_by' => $this->inviter ? ['id' => $this->inviter->id, 'name' => $this->inviter->name] : null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    public function inviteLink(): string
    {
        return "kinlore://invite/{$this->token}?server=" . rawurlencode(config('app.url'));
    }

    public function inviteUrl(): string
    {
        return rtrim(config('app.url'), '/') . "/invite/{$this->token}";
    }
}

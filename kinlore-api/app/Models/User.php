<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'can_add_content', 'can_comment', 'can_like', 'can_download'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const array PERMISSIONS = ['add_content', 'comment', 'like', 'download'];

    /** Same defaults as the columns, so a new model has them before it is reloaded. */
    protected $attributes = [
        'role' => Role::Family->value,
        'can_add_content' => true,
        'can_comment' => true,
        'can_like' => true,
        'can_download' => true,
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'can_add_content' => 'boolean',
            'can_comment' => 'boolean',
            'can_like' => 'boolean',
            'can_download' => 'boolean',
        ];
    }

    // Roles and permissions: docs/API_CONTRACT.md section 1.
    protected function isAdmin(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->role === Role::Admin);
    }

    public function canManageContent(): bool
    {
        return $this->role->canManageContent();
    }

    /** @param string $permission one of self::PERMISSIONS */
    public function hasPermission(string $permission): bool
    {
        if (! in_array($permission, self::PERMISSIONS, true)) {
            throw new \InvalidArgumentException("Unknown permission: {$permission}");
        }

        return $this->canManageContent() || (bool) $this->getAttribute("can_{$permission}");
    }

    /** @return array{add_content: bool, comment: bool, like: bool, download: bool} */
    public function permissionsArray(): array
    {
        return array_combine(self::PERMISSIONS, array_map($this->hasPermission(...), self::PERMISSIONS));
    }

    /** User payload shared by login, register, /auth/me and members (docs/API_CONTRACT.md section 2). */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_admin' => $this->is_admin,
            'permissions' => $this->permissionsArray(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function kids(): HasMany
    {
        return $this->hasMany(Kid::class);
    }

    public function moments(): HasManyThrough
    {
        return $this->hasManyThrough(Moment::class, Kid::class);
    }

    /** Moments this user uploaded (moments.user_id). */
    public function uploadedMoments(): HasMany
    {
        return $this->hasMany(Moment::class, 'user_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(MomentInteraction::class);
    }

    public function getTotalMomentsCount(): int
    {
        return $this->moments()->count();
    }

    public function getStorageUsed(): int
    {
        return (int) $this->moments()->sum('file_size');
    }

    public function getStorageUsedFormatted(): string
    {
        $bytes = $this->getStorageUsed();

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }
}

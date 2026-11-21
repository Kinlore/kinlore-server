<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_add_content',
        'can_comment',
        'can_like',
        'can_download',
    ];

    public const ROLES = ['admin', 'partner', 'family'];

    public const PERMISSIONS = ['add_content', 'comment', 'like', 'download'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_add_content' => 'boolean',
            'can_comment' => 'boolean',
            'can_like' => 'boolean',
            'can_download' => 'boolean',
        ];
    }

    /**
     * Roles and permissions (docs/API_CONTRACT.md section 1)
     */

    // is_admin is derived from role; setting it keeps older call sites working
    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    public function setIsAdminAttribute($value): void
    {
        $this->attributes['role'] = $value ? 'admin' : ($this->attributes['role'] ?? 'family');
    }

    /** Admin and partner may change any kid and any content. */
    public function canManageContent(): bool
    {
        return in_array($this->role, ['admin', 'partner'], true);
    }

    /** @param string $permission one of self::PERMISSIONS */
    public function hasPermission(string $permission): bool
    {
        if (!in_array($permission, self::PERMISSIONS, true)) {
            throw new \InvalidArgumentException("Unknown permission: {$permission}");
        }

        return $this->canManageContent() || (bool) $this->getAttribute("can_{$permission}");
    }

    /** @return array{add_content: bool, comment: bool, like: bool, download: bool} */
    public function permissionsArray(): array
    {
        return array_combine(self::PERMISSIONS, array_map(fn ($p) => $this->hasPermission($p), self::PERMISSIONS));
    }

    /**
     * Relationships
     */
    
    public function kids()
    {
        return $this->hasMany(Kid::class);
    }

    public function moments()
    {
        return $this->hasManyThrough(Moment::class, Kid::class);
    }

    /** Moments this user uploaded (moments.user_id). */
    public function uploadedMoments()
    {
        return $this->hasMany(Moment::class, 'user_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function customMilestones()
    {
        return $this->hasMany(CustomMilestone::class);
    }

    public function interactions()
    {
        return $this->hasMany(MomentInteraction::class);
    }

    /**
     * Helper Methods
     */
    
    public function getTotalMomentsCount()
    {
        return $this->moments()->count();
    }

    public function getStorageUsed()
    {
        return $this->moments()->sum('file_size');
    }

    public function getStorageUsedFormatted()
    {
        $bytes = $this->getStorageUsed();
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }
}

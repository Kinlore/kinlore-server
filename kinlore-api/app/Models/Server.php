<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'owner_id', 'settings'];
    
    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(ServerMember::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'server_members')
            ->withPivot('role', 'joined_at', 'metadata')
            ->withTimestamps();
    }

    public function kids()
    {
        return $this->hasMany(Kid::class);
    }

    public function moments()
    {
        return $this->hasManyThrough(Moment::class, Kid::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
    
    /**
     * Helper Methods
     */
    
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }
    
    public function getMemberRole(User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        return $member ? $member->role : null;
    }
    
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}

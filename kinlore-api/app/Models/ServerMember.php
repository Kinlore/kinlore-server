<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerMember extends Model
{
    use HasFactory;
    
    protected $fillable = ['user_id', 'server_id', 'role', 'invited_by', 'metadata'];
    
    protected $casts = [
        'joined_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
    
    /**
     * Helper Methods
     */
    
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
    
    public function isContributor(): bool
    {
        return $this->role === 'contributor';
    }
    
    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }
    
    public function canInvite(): bool
    {
        return $this->isOwner();
    }
    
    public function canRemoveMembers(): bool
    {
        return $this->isOwner();
    }
    
    public function canUpload(): bool
    {
        return in_array($this->role, ['owner', 'contributor']);
    }
}

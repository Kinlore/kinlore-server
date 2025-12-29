<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['server_id', 'token', 'email', 'role', 'invited_by', 'expires_at', 'accepted_at'];
    
    protected $hidden = ['token']; // Hide by default for security
    
    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $appends = ['invite_link'];

    /**
     * Relationships
     */

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
    
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
    
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
    
    public function isPending(): bool
    {
        return !$this->isExpired() && !$this->isAccepted() && !$this->trashed();
    }
    
    public function getInviteLinkAttribute(): string
    {
        return "kinlore://invite/{$this->token}";
    }
    
    /**
     * Generate a unique token
     */
    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (self::where('token', $token)->exists());
        
        return $token;
    }
}

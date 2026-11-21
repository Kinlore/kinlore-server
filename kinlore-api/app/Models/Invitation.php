<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUuids;

    protected $fillable = ['token', 'email', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by'];

    protected $hidden = ['token'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}

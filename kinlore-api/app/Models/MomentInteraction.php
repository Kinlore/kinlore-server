<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MomentInteraction extends Model
{
    use HasFactory;

    // a like or comment bumps the moment, so the next sync pull sends the new counts to every phone
    protected $touches = ['moment'];

    protected $fillable = [
        'moment_id',
        'user_id',
        'type',
        'content',
    ];

    /**
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moment()
    {
        return $this->belongsTo(Moment::class);
    }

    /**
     * Scopes
     */

    public function scopeLikes($query)
    {
        return $query->where('type', 'like');
    }

    public function scopeComments($query)
    {
        return $query->where('type', 'comment');
    }
}

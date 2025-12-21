<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MomentInteraction extends Model
{
    use HasFactory;

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

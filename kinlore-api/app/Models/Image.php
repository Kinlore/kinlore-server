<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'images';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'filename',
        'storage_path',
        'url',
        'mime_type',
        'file_size',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
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

    public function milestones()
    {
        return $this->hasMany(Milestone::class, 'icon_image_id');
    }

    public function customMilestones()
    {
        return $this->hasMany(CustomMilestone::class, 'icon_image_id');
    }

    /**
     * Scopes
     */
    
    public function scopeSystemImages($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeUserImages($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}

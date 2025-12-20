<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Album extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'albums';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kid_id',
        'name',
        'description',
        'cover_photo_url',
        'photos_count',
    ];

    protected $casts = [
        'photos_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    
    public function kid()
    {
        return $this->belongsTo(Kid::class);
    }

    public function moments()
    {
        return $this->belongsToMany(Moment::class, 'album_moment')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('album_moment.sort_order');
    }

    /**
     * Helper Methods
     */
    
    public function addMoment(Moment $moment, $sortOrder = null)
    {
        if ($sortOrder === null) {
            $sortOrder = $this->moments()->max('album_moment.sort_order') + 1;
        }

        $this->moments()->attach($moment->id, ['sort_order' => $sortOrder]);
        $this->increment('photos_count');
    }

    public function removeMoment(Moment $moment)
    {
        $this->moments()->detach($moment->id);
        $this->decrement('photos_count');
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-update photos_count
        static::created(function ($album) {
            $album->photos_count = 0;
            $album->saveQuietly();
        });
    }
}

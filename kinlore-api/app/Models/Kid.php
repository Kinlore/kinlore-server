<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Kid extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'kids';

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
        'name',
        'date_of_birth',
        'avatar_color',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'synced_at' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'age',
    ];

    /**
     * Relationships
     */
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moments()
    {
        return $this->hasMany(Moment::class);
    }

    /**
     * Computed Attributes
     */
    
    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }

        $now = Carbon::now();
        $dob = Carbon::parse($this->date_of_birth);
        
        return $dob->diffInYears($now);
    }

    public function getAgeInMonthsAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return Carbon::parse($this->date_of_birth)->diffInMonths(Carbon::now());
    }

    public function getAgeDetailedAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }

        $now = Carbon::now();
        $dob = Carbon::parse($this->date_of_birth);
        
        $years = $dob->diffInYears($now);
        $months = $dob->copy()->addYears($years)->diffInMonths($now);
        
        if ($years > 0) {
            return $years . ' year' . ($years > 1 ? 's' : '') . 
                   ($months > 0 ? ', ' . $months . ' month' . ($months > 1 ? 's' : '') : '');
        }
        
        return $months . ' month' . ($months > 1 ? 's' : '');
    }

    /**
     * Helper Methods
     */
    
    public function getMomentsCount()
    {
        return $this->moments()->count();
    }

    public function getLatestMoment()
    {
        return $this->moments()
            ->orderBy('exif_date', 'desc')
            ->first();
    }

    public function getFirstMoment()
    {
        return $this->moments()
            ->orderBy('exif_date', 'asc')
            ->first();
    }

    /**
     * Scopes
     */
    
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithMomentsCount($query)
    {
        return $query->withCount('moments');
    }

    public function scopeUpdatedSince($query, $timestamp)
    {
        return $query->where('updated_at', '>', Carbon::createFromTimestamp($timestamp / 1000));
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Set default avatar color if not provided
        static::creating(function ($kid) {
            if (empty($kid->avatar_color)) {
                $kid->avatar_color = self::randomAvatarColor();
            }
        });

        // Update synced_at on update
        static::updated(function ($kid) {
            if (!$kid->isDirty('synced_at')) {
                $kid->synced_at = now()->timestamp * 1000;
                $kid->saveQuietly(); // Save without triggering events
            }
        });
    }

    /**
     * Generate random avatar color
     */
    public static function randomAvatarColor()
    {
        $colors = [
            '#FF6B6B', // Red
            '#4ECDC4', // Teal
            '#45B7D1', // Blue
            '#FFA07A', // Salmon
            '#98D8C8', // Mint
            '#F7DC6F', // Yellow
            '#BB8FCE', // Purple
            '#85C1E2', // Sky Blue
            '#F8B88B', // Peach
            '#A3E4D7', // Aqua
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * API Resource formatting
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth->format('Y-m-d'),
            'avatar_color' => $this->avatar_color,
            'age' => $this->age,
            'age_detailed' => $this->age_detailed,
            'moments_count' => $this->moments_count ?? $this->getMomentsCount(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

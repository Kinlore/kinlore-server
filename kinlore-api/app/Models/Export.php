<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * A ZIP of the whole family library on the API server's disk (docs/API_CONTRACT.md section 8).
 */
class Export extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'status',
        'path',
        'size_bytes',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Absolute path of the ZIP file, or null when there is none. */
    public function fullPath(): ?string
    {
        return $this->path ? storage_path('app/' . $this->path) : null;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'size_bytes' => $this->size_bytes,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'download_url' => $this->status === self::STATUS_READY
                ? URL::temporarySignedRoute('exports.download', now()->addHours(24), ['export' => $this->id])
                : null,
        ];
    }
}

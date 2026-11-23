<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * A ZIP of the whole family library on the API server's disk (docs/API_CONTRACT.md section 8).
 */
#[Fillable(['user_id', 'status', 'path', 'size_bytes', 'error', 'completed_at'])]
class Export extends Model
{
    use HasUuids;

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'size_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Absolute path of the ZIP file, or null when there is none. */
    public function fullPath(): ?string
    {
        return $this->path ? storage_path('app/' . $this->path) : null;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'size_bytes' => $this->size_bytes,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'download_url' => $this->status === ExportStatus::Ready
                ? URL::temporarySignedRoute('exports.download', now()->addHours(24), ['export' => $this->id])
                : null,
        ];
    }
}

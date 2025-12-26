<?php

namespace App\Services;

use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling moment operations.
 */
class MomentService
{
    public function __construct(
        private FileStorageService $storage
    ) {}

    /**
     * Get moments for a kid with optional filters.
     */
    public function getMomentsForKid(Kid $kid, array $filters = []): LengthAwarePaginator
    {
        $query = $kid->moments()
            ->with(['video', 'milestone', 'customMilestone'])
            ->orderByDate($filters['sort_direction'] ?? 'desc');

        // Filter by date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }

        // Filter by device
        if (!empty($filters['device_asset_id'])) {
            $query->byDevice($filters['device_asset_id']);
        }

        // Filter by moment type
        if (!empty($filters['moment_type'])) {
            $query->where('moment_type', $filters['moment_type']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get a single moment formatted for API.
     */
    public function getMoment(Moment $moment): array
    {
        $moment->load(['video', 'milestone', 'customMilestone']);
        
        return $moment->toArray();
    }

    /**
     * Delete a moment with its files.
     */
    public function deleteMoment(Moment $moment): void
    {
        try {
            $moment->deleteWithFiles();
        } catch (\Exception $e) {
            Log::error('Failed to delete moment', [
                'moment_id' => $moment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Serve file through authenticated proxy.
     */
    public function serveFile(Moment $moment, string $type): array
    {
        $pathMap = [
            'original' => $moment->getRawOriginal('photo_url'),
            'medium' => $moment->getRawOriginal('medium_url'),
            'thumbnail' => $moment->getRawOriginal('thumbnail_url'),
        ];

        if (!isset($pathMap[$type])) {
            throw new \InvalidArgumentException('Invalid file type. Use: original, medium, or thumbnail');
        }

        $path = $pathMap[$type];

        if (empty($path)) {
            throw new \RuntimeException('File not found');
        }

        if (!$this->storage->exists($path)) {
            throw new \RuntimeException('File not found in storage');
        }

        $content = $this->storage->get($path);
        $size = $this->storage->size($path);

        // Determine MIME type
        $mimeType = ($type === 'thumbnail' || $type === 'medium')
            ? 'image/jpeg'
            : ($moment->mime_type ?? 'application/octet-stream');

        return [
            'content' => $content,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Check file status (diagnostic).
     */
    public function checkFileStatus(Moment $moment): array
    {
        $photoUrl = $moment->getRawOriginal('photo_url');
        $mediumUrl = $moment->getRawOriginal('medium_url');
        $thumbnailUrl = $moment->getRawOriginal('thumbnail_url');

        return [
            'moment_id' => $moment->id,
            'paths' => [
                'photo_url' => $photoUrl,
                'medium_url' => $mediumUrl,
                'thumbnail_url' => $thumbnailUrl,
            ],
            'exists' => [
                'photo' => $photoUrl ? $this->storage->exists($photoUrl) : false,
                'medium' => $mediumUrl ? $this->storage->exists($mediumUrl) : false,
                'thumbnail' => $thumbnailUrl ? $this->storage->exists($thumbnailUrl) : false,
            ],
            'sizes' => [
                'photo' => $photoUrl && $this->storage->exists($photoUrl) 
                    ? $this->storage->size($photoUrl) : 0,
                'medium' => $mediumUrl && $this->storage->exists($mediumUrl) 
                    ? $this->storage->size($mediumUrl) : 0,
                'thumbnail' => $thumbnailUrl && $this->storage->exists($thumbnailUrl) 
                    ? $this->storage->size($thumbnailUrl) : 0,
            ],
        ];
    }

    /**
     * Get uploaded identifiers for duplicate detection.
     */
    public function getUploadedIdentifiers(Kid $kid): array
    {
        return $kid->moments()
            ->where(function ($query) {
                $query->whereNotNull('device_asset_id')
                      ->orWhereNotNull('device_fingerprint');
            })
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(fn ($moment) => [
                'device_asset_id' => $moment->device_asset_id,
                'device_fingerprint' => $moment->device_fingerprint,
                'file_size' => $moment->file_size,
                'filename' => $moment->original_filename,
            ])
            ->toArray();
    }

    /**
     * Format moment for API response (legacy support).
     */
    public function formatMomentForApi(Moment $moment): array
    {
        return [
            'id' => $moment->id,
            'kid_id' => $moment->kid_id,
            'moment_type' => $moment->moment_type ?? 'photo',
            'title' => $moment->title,
            'description' => $moment->description,
            'photo_url' => $moment->photo_url,
            'medium_url' => $moment->medium_url,
            'thumbnail_url' => $moment->thumbnail_url,
            'file_size' => $moment->file_size,
            'file_size_formatted' => $moment->file_size_formatted,
            'mime_type' => $moment->mime_type,
            'has_location' => $moment->has_location,
            'latitude' => $moment->latitude,
            'longitude' => $moment->longitude,
            'exif_date' => $moment->exif_date?->toIso8601String(),
            'camera_make' => $moment->camera_make,
            'camera_model' => $moment->camera_model,
            'created_at' => $moment->created_at->toIso8601String(),
            'updated_at' => $moment->updated_at->toIso8601String(),
        ];
    }
}

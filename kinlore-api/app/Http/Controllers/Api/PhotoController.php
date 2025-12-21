<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Log;

class PhotoController extends Controller
{
    /**
     * Display a listing of moments for a kid.
     */
    public function index(Request $request, Kid $kid)
    {
        $this->authorize('view', $kid);

        $perPage = $request->input('per_page', 20);
        $sortBy = $request->input('sort_by', 'exif_date');
        $sortDirection = $request->input('sort_direction', 'desc');

        $query = $kid->moments();

        if ($sortBy === 'exif_date') {
            $query->orderByRaw('COALESCE(exif_date, created_at) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $moments = $query->paginate($perPage);

        $moments->getCollection()->transform(function ($moment) {
            return $this->formatMoment($moment);
        });

        return response()->json([
            'success' => true,
            'data' => $moments->items(),
            'meta' => [
                'current_page' => $moments->currentPage(),
                'last_page' => $moments->lastPage(),
                'per_page' => $moments->perPage(),
                'total' => $moments->total(),
            ],
        ]);
    }

    /**
     * Store a newly created moment (photo upload).
     */
    public function store(Request $request, Kid $kid)
    {
        Log::info('Photo upload request received');
        $this->authorize('view', $kid);

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,heic|max:20480',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'device_asset_id' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        Log::info('Photo upload request validated');

        try {
            $photo = $request->file('photo');
            $uuid = Str::uuid();
            $extension = $photo->getClientOriginalExtension();

            $originalPath = "moments/{$kid->id}/originals/{$uuid}.{$extension}";
            $mediumPath = "moments/{$kid->id}/medium/{$uuid}.jpg";
            $thumbnailPath = "moments/{$kid->id}/thumbnails/{$uuid}.jpg";

            Storage::disk('seaweedfs')->put(
                $originalPath,
                file_get_contents($photo->getRealPath()),
                'private'
            );

            Log::info('Original photo uploaded');

            $mediumImage = Image::read($photo->getRealPath())
                ->scale(width: 1200)
                ->toJpeg(quality: 85);

            Log::info('Medium photo processed');

            Storage::disk('seaweedfs')->put($mediumPath, (string) $mediumImage, 'private');

            Log::info('Medium photo uploaded');

            $thumbnailImage = Image::read($photo->getRealPath())
                ->cover(300, 300)
                ->toJpeg(quality: 80);

            Log::debug('Thumbnail photo processed');

            Storage::disk('seaweedfs')->put($thumbnailPath, (string) $thumbnailImage, 'private');

            Log::debug('Thumbnail photo uploaded');

            $exifData = $this->extractExifData($photo);

            Log::debug('Exif data extracted');

            $moment = Moment::create([
                'id' => $uuid,
                'kid_id' => $kid->id,
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'photo_url' => $originalPath,
                'medium_url' => $mediumPath,
                'thumbnail_url' => $thumbnailPath,
                'file_size' => $photo->getSize(),
                'mime_type' => $photo->getMimeType(),
                'original_filename' => $photo->getClientOriginalName(),
                'device_asset_id' => $validated['device_asset_id'] ?? null,
                'device_fingerprint' => $this->generateFingerprint($photo),
                'uploaded_from_device' => $request->header('User-Agent'),
                'uploaded_from_ip' => $request->ip(),
                'exif_date' => $exifData['date'] ?? null,
                'latitude' => $exifData['latitude'] ?? null,
                'longitude' => $exifData['longitude'] ?? null,
                'orientation' => $exifData['orientation'] ?? null,
                'camera_make' => $exifData['make'] ?? null,
                'camera_model' => $exifData['model'] ?? null,
                'tags' => $validated['tags'] ?? null,
            ]);

            Log::debug('Moment created');

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'data' => $this->formatMoment($moment),
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Photo upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Photo upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified moment.
     */
    public function destroy(Moment $moment)
    {
        $this->authorize('delete', $moment);

        try {
            Storage::disk('seaweedfs')->delete([
                $moment->getRawOriginal('photo_url'),
                $moment->getRawOriginal('medium_url'),
                $moment->getRawOriginal('thumbnail_url'),
            ]);

            $moment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Moment deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Moment deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete moment',
            ], 500);
        }
    }

    /**
     * Get list of uploaded identifiers for duplicate detection.
     */
    public function uploadedIdentifiers(Request $request, Kid $kid)
    {
        $this->authorize('view', $kid);

        $identifiers = $kid->moments()
            ->whereNotNull('device_asset_id')
            ->orWhereNotNull('device_fingerprint')
            ->get(['device_asset_id', 'device_fingerprint', 'file_size', 'original_filename'])
            ->map(function ($moment) {
                return [
                    'device_asset_id' => $moment->device_asset_id,
                    'device_fingerprint' => $moment->device_fingerprint,
                    'file_size' => $moment->file_size,
                    'filename' => $moment->original_filename,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $identifiers,
        ]);
    }

    /**
     * Extract EXIF data from photo.
     */
    private function extractExifData($photo)
    {
        try {
            $exif = @exif_read_data($photo->getRealPath());

            if (!$exif) {
                return [];
            }

            $data = [];

            // Extract date
            if (isset($exif['DateTimeOriginal'])) {
                try {
                    $data['date'] = \Carbon\Carbon::createFromFormat(
                        'Y:m:d H:i:s',
                        $exif['DateTimeOriginal']
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse EXIF date: ' . $e->getMessage());
                }
            }

            // Extract GPS coordinates
            if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
                $data['latitude'] = $this->getGps(
                    $exif['GPSLatitude'],
                    $exif['GPSLatitudeRef'] ?? 'N'
                );
                $data['longitude'] = $this->getGps(
                    $exif['GPSLongitude'],
                    $exif['GPSLongitudeRef'] ?? 'E'
                );
            }

            // Extract orientation
            $data['orientation'] = $exif['Orientation'] ?? null;

            // Extract camera info
            $data['make'] = isset($exif['Make']) ? trim($exif['Make']) : null;
            $data['model'] = isset($exif['Model']) ? trim($exif['Model']) : null;

            return $data;

        } catch (\Exception $e) {
            \Log::warning('EXIF extraction failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert GPS coordinates to decimal.
     */
    private function getGps($coordinate, $hemisphere)
    {
        if (!is_array($coordinate) || count($coordinate) < 2) {
            return null;
        }

        $degrees = $this->gps2Num($coordinate[0]);
        $minutes = $this->gps2Num($coordinate[1]);
        $seconds = isset($coordinate[2]) ? $this->gps2Num($coordinate[2]) : 0;

        $flip = ($hemisphere === 'W' || $hemisphere === 'S') ? -1 : 1;

        return round($flip * ($degrees + $minutes / 60 + $seconds / 3600), 8);
    }

    /**
     * Convert GPS fraction to number.
     */
    private function gps2Num($coordPart)
    {
        $parts = explode('/', (string) $coordPart);

        if (count($parts) <= 0) {
            return 0;
        }

        if (count($parts) == 1) {
            return (float) $parts[0];
        }

        return (float) $parts[0] / (float) $parts[1];
    }

    /**
     * Generate fingerprint for duplicate detection.
     */
    private function generateFingerprint($photo)
    {
        return md5_file($photo->getRealPath());
    }

    /**
     * Format moment for API response.
     */
    private function formatMoment(Moment $moment)
    {
        return [
            'id' => $moment->id,
            'kid_id' => $moment->kid_id,
            'title' => $moment->title,
            'description' => $moment->description,
            'photo_url' => $this->getSignedUrl($moment->getRawOriginal('photo_url')),
            'medium_url' => $this->getSignedUrl($moment->getRawOriginal('medium_url')),
            'thumbnail_url' => $this->getSignedUrl($moment->getRawOriginal('thumbnail_url')),
            'file_size' => $moment->file_size,
            'file_size_formatted' => $moment->file_size_formatted,
            'mime_type' => $moment->mime_type,
            'exif_date' => $moment->exif_date?->toIso8601String(),
            'display_date' => $moment->display_date->toIso8601String(),
            'latitude' => $moment->latitude,
            'longitude' => $moment->longitude,
            'has_location' => $moment->has_location,
            'camera_make' => $moment->camera_make,
            'camera_model' => $moment->camera_model,
            'tags' => $moment->tags,
            'created_at' => $moment->created_at->toIso8601String(),
            'updated_at' => $moment->updated_at->toIso8601String(),
        ];
    }

    /**
     * Generate signed URL for MinIO.
     */
    private function getSignedUrl($path, $expiration = 60)
    {
        if (empty($path)) {
            return null;
        }

        try {
            return Storage::disk('seaweedfs')->temporaryUrl(
                $path,
                now()->addMinutes($expiration)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to generate signed URL: ' . $e->getMessage());
            return Storage::disk('seaweedfs')->url($path);
        }
    }
}

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

        $query->withCount([
            'likes',
            'comments'
        ]);
        
        // Eager load is_liked for current user
        // We use withExists with a constraint
        $query->withExists(['likes as is_liked' => function ($query) {
            $query->where('user_id', auth()->id());
        }]);

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
            'exif_date' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'camera_make' => 'nullable|string|max:255',
            'camera_model' => 'nullable|string|max:255',
            'orientation' => 'nullable|integer|between:1,8',
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
                'exif_date' => isset($validated['exif_date']) ? \Carbon\Carbon::parse($validated['exif_date']) : null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'camera_make' => $validated['camera_make'] ?? null,
                'camera_model' => $validated['camera_model'] ?? null,
                'orientation' => $validated['orientation'] ?? null,
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
     * Display the specified moment.
     */
    public function show(Moment $moment)
    {
        $this->authorize('view', $moment->kid);

        return response()->json([
            'success' => true,
            'data' => $this->formatMoment($moment),
        ]);
    }

    /**
     * Serve a file from SeaweedFS through authenticated proxy.
     */
    public function serveFile(Moment $moment, string $type)
    {
        $this->authorize('view', $moment->kid);

        // Map type to path field
        $pathMap = [
            'original' => 'photo_url',
            'medium' => 'medium_url',
            'thumbnail' => 'thumbnail_url',
        ];

        if (!isset($pathMap[$type])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Use: original, medium, or thumbnail',
            ], 400);
        }

        $path = $moment->getRawOriginal($pathMap[$type]);

        if (empty($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        try {
            $disk = Storage::disk('seaweedfs');

            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage',
                ], 404);
            }

            $fileContent = $disk->get($path);
            
            // Set correct MIME type
            if ($type === 'thumbnail' || $type === 'medium') {
                $mimeType = 'image/jpeg';
            } else {
                $mimeType = $moment->mime_type ?? 'image/jpeg';
            }
            
            $fileSize = $disk->size($path);

            return response($fileContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Length', $fileSize)
                ->header('Cache-Control', 'private, max-age=31536000')
                ->header('Content-Disposition', 'inline');

        } catch (\Exception $e) {
            Log::error('Failed to serve file: ' . $e->getMessage(), [
                'moment_id' => $moment->id,
                'type' => $type,
                'path' => $path,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file',
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
            'photo_url' => $moment->getRawOriginal('photo_url'),
            'medium_url' => $moment->getRawOriginal('medium_url'),
            'thumbnail_url' => $moment->getRawOriginal('thumbnail_url'),
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
            'is_liked' => $moment->is_liked ?? false,
            'likes_count' => $moment->likes_count ?? 0,
            'comments_count' => $moment->comments_count ?? 0,
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

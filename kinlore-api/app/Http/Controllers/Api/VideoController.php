<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    /**
     * List user's videos with optional filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Video::forUser($user->id)
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $status = $request->input('status');
            if (in_array($status, ['uploading', 'processing', 'completed', 'failed'])) {
                $query->where('status', $status);
            }
        }

        $videos = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $videos->items(),
            'meta' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
            ],
        ]);
    }

    /**
     * Upload a new video.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm',
                'max:' . (env('VIDEO_MAX_SIZE_MB', 500) * 1024), // Convert MB to KB
            ],
            'original_filename' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $file = $request->file('video');
        
        // Generate unique ID for the video
        $videoId = Str::uuid()->toString();
        
        // Get file info
        $originalFilename = $validated['original_filename'] ?? $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        
        // Store original file
        $storagePath = "videos/{$videoId}/original." . $file->getClientOriginalExtension();
        
        try {
            Storage::disk('seaweedfs')->put($storagePath, file_get_contents($file->getRealPath()));
        } catch (\Exception $e) {
            Log::error('Video upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to store video file',
            ], 500);
        }
        
        // Create video record
        $video = Video::create([
            'id' => $videoId,
            'user_id' => $user->id,
            'original_filename' => $originalFilename,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'status' => Video::STATUS_UPLOADING,
            'storage_path' => $storagePath,
        ]);
        
        // Dispatch processing job
        ProcessVideoJob::dispatch($videoId);
        
        Log::info('Video uploaded and queued for processing', [
            'video_id' => $videoId,
            'user_id' => $user->id,
            'file_size' => $fileSize,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Video uploaded successfully. Processing will begin shortly.',
            'data' => [
                'id' => $video->id,
                'status' => $video->status,
                'original_filename' => $video->original_filename,
                'file_size' => $video->file_size,
                'file_size_formatted' => $video->file_size_formatted,
            ],
        ], 201);
    }

    /**
     * Get video processing status.
     */
    public function status(Video $video)
    {
        $this->authorize('view', $video);

        return response()->json([
            'success' => true,
            'data' => $video->toStatusArray(),
        ]);
    }

    /**
     * Get video details.
     */
    public function show(Video $video)
    {
        $this->authorize('view', $video);

        return response()->json([
            'success' => true,
            'data' => $video->toArray(),
        ]);
    }

    /**
     * Delete a video.
     */
    public function destroy(Video $video)
    {
        $this->authorize('delete', $video);

        try {
            $video->deleteWithFiles();
            
            return response()->json([
                'success' => true,
                'message' => 'Video deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Video deletion failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete video',
            ], 500);
        }
    }

    /**
     * Serve HLS playlist or segment through authenticated proxy.
     */
    public function serveHLS(Video $video, string $filename)
    {
        $this->authorize('view', $video);

        if (!$video->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Video is not ready for playback',
            ], 400);
        }

        $path = "videos/{$video->id}/hls/{$filename}";

        try {
            $contents = Storage::disk('seaweedfs')->get($path);
            
            $contentType = str_ends_with($filename, '.m3u8') 
                ? 'application/vnd.apple.mpegurl' 
                : 'video/MP2T';

            return response($contents, 200)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'max-age=3600');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }
    }

    /**
     * Serve video thumbnail through authenticated proxy.
     */
    public function serveThumbnail(Video $video)
    {
        $this->authorize('view', $video);

        $path = $video->getRawThumbnailUrl();

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Thumbnail not available',
            ], 404);
        }

        try {
            $contents = Storage::disk('seaweedfs')->get($path);
            
            return response($contents, 200)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'max-age=86400');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Thumbnail not found',
            ], 404);
        }
    }
}

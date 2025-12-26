<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling file storage operations with SeaweedFS.
 */
class FileStorageService
{
    private string $disk;

    public function __construct(string $disk = 'seaweedfs')
    {
        $this->disk = $disk;
    }

    /**
     * Store file content to storage.
     */
    public function store(string $path, string $content, string $visibility = 'private'): bool
    {
        return Storage::disk($this->disk)->put($path, $content, $visibility);
    }

    /**
     * Store uploaded file to storage.
     */
    public function storeFile(string $path, $file, string $visibility = 'private'): bool
    {
        return Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
            $visibility
        );
    }

    /**
     * Get file content from storage.
     */
    public function get(string $path): ?string
    {
        try {
            return Storage::disk($this->disk)->get($path);
        } catch (\Exception $e) {
            Log::error("Failed to get file: {$path}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if file exists in storage.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get file size.
     */
    public function size(string $path): int
    {
        try {
            return Storage::disk($this->disk)->size($path);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Delete files from storage.
     */
    public function delete(array $paths): bool
    {
        $paths = array_filter($paths);
        
        if (empty($paths)) {
            return true;
        }

        try {
            return Storage::disk($this->disk)->delete($paths);
        } catch (\Exception $e) {
            Log::error('Failed to delete files', ['paths' => $paths, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete a directory.
     */
    public function deleteDirectory(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->deleteDirectory($path);
        } catch (\Exception $e) {
            Log::error("Failed to delete directory: {$path}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate a temporary signed URL.
     */
    public function temporaryUrl(string $path, int $expirationMinutes = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $path,
                now()->addMinutes($expirationMinutes)
            );
        } catch (\Exception $e) {
            Log::error("Failed to generate temporary URL for: {$path}", ['error' => $e->getMessage()]);
            return Storage::disk($this->disk)->url($path);
        }
    }

    /**
     * Get the storage disk instance.
     */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->disk);
    }
}

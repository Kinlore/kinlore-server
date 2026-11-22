<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
        // A stream keeps a large video out of PHP memory
        $stream = fopen($file->getRealPath(), 'r');
        try {
            return Storage::disk($this->disk)->put($path, $stream, $visibility);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
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

    /**
     * Rewrite every file an HLS playlist references into a signed $route URL that expires at $expires.
     * Players cannot send auth headers, so each variant playlist and segment carries its own signature.
     *
     * @param array{content: string, size: int, mime_type: string} $file
     */
    public function signPlaylist(array $file, string $route, array $params, int $expires): array
    {
        if (!str_ends_with($file['mime_type'], 'mpegurl')) {
            return $file;
        }

        $sign = fn (string $ref) => str_contains($ref, '://')
            ? $ref
            : URL::temporarySignedRoute($route, Carbon::createFromTimestamp($expires), $params + ['filename' => basename($ref)]);

        $lines = array_map(function (string $line) use ($sign) {
            $ref = trim($line);

            if ($ref === '') {
                return $line;
            }

            // Tags can reference files too, e.g. #EXT-X-MEDIA:URI="audio.m3u8"
            return $ref[0] === '#'
                ? preg_replace_callback('/URI="([^"]+)"/', fn ($m) => 'URI="' . $sign($m[1]) . '"', $line)
                : $sign($ref);
        }, preg_split('/\r?\n/', $file['content']));

        $file['content'] = implode("\n", $lines);
        $file['size'] = strlen($file['content']);

        return $file;
    }
}

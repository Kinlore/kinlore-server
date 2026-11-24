<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// File storage on SeaweedFS
class FileStorageService
{
    public function __construct(
        private readonly string $disk = 'seaweedfs',
    ) {}

    /** @param string|resource $content */
    public function store(string $path, mixed $content, string $visibility = 'private'): void
    {
        // The disk has 'throw' => false, so a failed write only shows as false
        if (! Storage::disk($this->disk)->put($path, $content, $visibility)) {
            throw new \RuntimeException("Cannot store file: {$path}");
        }
    }

    public function storeFile(string $path, UploadedFile $file, string $visibility = 'private'): void
    {
        // A stream keeps a large video out of PHP memory
        $stream = fopen($file->getRealPath(), 'r');
        try {
            $this->store($path, $stream, $visibility);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function get(string $path): ?string
    {
        try {
            return Storage::disk($this->disk)->get($path);
        } catch (\Exception $e) {
            Log::error("Failed to get file: {$path}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function size(string $path): int
    {
        try {
            return Storage::disk($this->disk)->size($path);
        } catch (\Exception) {
            return 0;
        }
    }

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

    public function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Rewrite every file an HLS playlist references into a signed $route URL that expires at $expires.
     * Players cannot send auth headers, so each variant playlist and segment carries its own signature.
     *
     * @param  array{content: string, size: int, mime_type: string}  $file
     */
    public function signPlaylist(array $file, string $route, array $params, int $expires): array
    {
        if (! str_ends_with($file['mime_type'], 'mpegurl')) {
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
                ? preg_replace_callback('/URI="([^"]+)"/', fn (array $m): string => 'URI="'.$sign($m[1]).'"', $line)
                : $sign($ref);
        }, preg_split('/\r?\n/', $file['content']));

        $file['content'] = implode("\n", $lines);
        $file['size'] = strlen($file['content']);

        return $file;
    }
}

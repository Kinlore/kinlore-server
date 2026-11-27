<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Detailed health check (requires authentication)
     * Shows all system components status
     */
    public function detailed(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'healthy';

        // 1. Database
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'ok') {
            $overallStatus = 'unhealthy';
        }

        // 2. Storage (SeaweedFS)
        $checks['storage'] = $this->checkStorage();
        if ($checks['storage']['status'] !== 'ok') {
            $overallStatus = 'degraded';
        }

        // 3. Cache
        $checks['cache'] = $this->checkCache();
        if ($checks['cache']['status'] !== 'ok') {
            $overallStatus = 'degraded';
        }

        // 4. Disk Space
        $checks['disk_space'] = $this->checkDiskSpace();

        // 5. Memory
        $checks['memory'] = $this->checkMemory();

        // 6. Public address for invite and setup links: a warning only, the server still works
        $checks['public_url'] = PublicUrl::isConfigured()
            ? ['status' => 'ok', 'url' => PublicUrl::base()]
            : ['status' => 'warning', 'url' => PublicUrl::base(), 'message' => 'APP_URL is empty or a placeholder; links use the address of this request. Set APP_URL to the public address.'];

        // 7. PHP Version
        $checks['php'] = [
            'version' => PHP_VERSION,
            'extensions' => $this->getRequiredExtensions(),
        ];

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'connection' => config('database.default'),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function checkStorage(): array
    {
        try {
            $start = microtime(true);
            $disk = config('filesystems.default');

            // Test write, read and delete; the disks set throw => false, so failures come back as false or null
            $testFile = 'health-checks/test-'.time().'.txt';
            $written = 'Health check: '.now();
            if (! Storage::disk($disk)->put($testFile, $written) || Storage::disk($disk)->get($testFile) !== $written) {
                throw new \RuntimeException('Storage write or read failed');
            }
            Storage::disk($disk)->delete($testFile);

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'disk' => $disk,
                'driver' => config("filesystems.disks.{$disk}.driver"),
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage connection failed',
                'disk' => $disk ?? 'unknown',
            ];
        }
    }

    /** @return array<string, mixed> */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_'.time();
            $value = 'test_'.uniqid();

            // Test write
            Cache::put($key, $value, 60);

            // Test read
            $retrieved = Cache::get($key);

            // Test delete
            Cache::forget($key);

            $responseTime = round((microtime(true) - $start) * 1000, 2);
            $works = $retrieved === $value;

            return [
                'status' => $works ? 'ok' : 'error',
                'driver' => config('cache.default'),
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function checkDiskSpace(): array
    {
        $storageBasePath = storage_path();
        $freeSpace = disk_free_space($storageBasePath);
        $totalSpace = disk_total_space($storageBasePath);
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);

        return [
            'path' => $storageBasePath,
            'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'usage_percent' => $usagePercent,
            'status' => $usagePercent > 90 ? 'warning' : 'ok',
        ];
    }

    /** @return array<string, mixed> */
    private function checkMemory(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        return [
            'limit' => $memoryLimit,
            'current_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($memoryPeak / 1024 / 1024, 2),
        ];
    }

    /** @return array<string, bool> */
    private function getRequiredExtensions(): array
    {
        $required = ['pdo', 'pdo_pgsql', 'mbstring', 'gd', 'zip', 'bcmath', 'exif'];

        return array_combine($required, array_map(extension_loaded(...), $required));
    }
}

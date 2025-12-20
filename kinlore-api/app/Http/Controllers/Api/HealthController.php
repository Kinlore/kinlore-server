<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Basic health check (public)
     * Used by Docker healthcheck and load balancers
     */
    public function check()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => config('app.name'),
            'environment' => config('app.env'),
        ], 200);
    }

    /**
     * Detailed health check (requires authentication)
     * Shows all system components status
     */
    public function detailed(Request $request)
    {
        $checks = [];
        $overallStatus = 'healthy';

        // 1. Database
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'ok') {
            $overallStatus = 'unhealthy';
        }

        // 2. Storage (MinIO)
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

        // 6. PHP Version
        $checks['php'] = [
            'version' => PHP_VERSION,
            'extensions' => $this->getRequiredExtensions(),
        ];

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'user' => [
                //'id' => $request->user()->id,
                //'email' => $request->user()->email,
            ],
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            // Get database info
            $dbName = DB::connection()->getDatabaseName();
            $version = DB::select('SELECT version()')[0]->version ?? 'unknown';

            // Test a query
            $usersCount = DB::table('users')->count();

            return [
                'status' => 'ok',
                'connection' => config('database.default'),
                'database' => $dbName,
                'version' => $version,
                'response_time_ms' => $responseTime,
                'users_count' => $usersCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connection' => config('database.default'),
            ];
        }
    }

    private function checkStorage()
    {
        try {
            $start = microtime(true);
            $disk = config('filesystems.default');
            
            // Test write
            $testFile = 'health-checks/test-' . time() . '.txt';
            Storage::disk($disk)->put($testFile, 'Health check: ' . now());
            
            // Test read
            $content = Storage::disk($disk)->get($testFile);
            
            // Test delete
            Storage::disk($disk)->delete($testFile);
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'disk' => $disk,
                'driver' => config("filesystems.disks.{$disk}.driver"),
                'endpoint' => config("filesystems.disks.{$disk}.endpoint"),
                'bucket' => config("filesystems.disks.{$disk}.bucket"),
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'disk' => $disk ?? 'unknown',
            ];
        }
    }

    private function checkCache()
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            $value = 'test_' . uniqid();
            
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

    private function checkDiskSpace()
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

    private function checkMemory()
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

    private function getRequiredExtensions()
    {
        $required = ['pdo', 'pdo_pgsql', 'mbstring', 'gd', 'zip', 'bcmath', 'exif'];
        $loaded = [];

        foreach ($required as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return $loaded;
    }
}
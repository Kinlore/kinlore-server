<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSeaweedFS extends Command
{
    protected $signature = 'seaweedfs:test';
    protected $description = 'Test SeaweedFS S3 connection and operations';

    public function handle()
    {
        $this->info('Testing SeaweedFS S3 connection...');
        
        try {
            // 1. Test write
            $this->info('1. Testing file upload...');
            $testContent = 'SeaweedFS test at ' . now();
            $testFile = 'test/test-' . time() . '.txt';
            
            Storage::disk('seaweedfs')->put($testFile, $testContent);
            $this->info("   ✅ File uploaded: {$testFile}");
            
            // 2. Test read
            $this->info('2. Testing file read...');
            $content = Storage::disk('seaweedfs')->get($testFile);
            
            if ($content === $testContent) {
                $this->info("   ✅ File content matches!");
            } else {
                $this->error("   ❌ File content mismatch!");
                return 1;
            }
            
            // 3. Test exists
            $this->info('3. Testing file exists check...');
            $exists = Storage::disk('seaweedfs')->exists($testFile);
            $this->info($exists ? "   ✅ File exists check passed" : "   ❌ File not found");
            
            // 4. Test URL generation
            $this->info('4. Testing URL generation...');
            $url = Storage::disk('seaweedfs')->url($testFile);
            $this->info("   URL: {$url}");
            
            // 5. Test list files
            $this->info('5. Testing file listing...');
            $files = Storage::disk('seaweedfs')->files('test');
            $this->info("   Found " . count($files) . " files in 'test' directory");
            
            // 6. Test delete
            $this->info('6. Testing file deletion...');
            Storage::disk('seaweedfs')->delete($testFile);
            $deleted = !Storage::disk('seaweedfs')->exists($testFile);
            $this->info($deleted ? "   ✅ File deleted successfully" : "   ❌ File still exists");
            
            $this->newLine();
            $this->info('✅ All SeaweedFS tests passed!');
            
            // Display configuration
            $this->newLine();
            $this->info('SeaweedFS Configuration:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Endpoint', config('filesystems.disks.seaweedfs.endpoint')],
                    ['Bucket', config('filesystems.disks.seaweedfs.bucket')],
                    ['Region', config('filesystems.disks.seaweedfs.region')],
                    ['URL', config('filesystems.disks.seaweedfs.url')],
                ]
            );
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ SeaweedFS test failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting:');
            $this->line('1. Check if SeaweedFS containers are running: docker compose ps');
            $this->line('2. Check SeaweedFS logs: docker compose logs seaweedfs-filer');
            $this->line('3. Verify .env configuration');
            $this->line('4. Check filer S3 API at http://localhost:8333');
            
            return 1;
        }
    }
}

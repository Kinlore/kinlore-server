<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MomentType;
use App\Enums\VideoStatus;
use App\Models\Moment;
use App\Services\FileStorageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Run once after the update that added moment sizes (docs/reference/commands.md): fills the display size of older photos and videos.
 * A video gets the size its processing saved. A photo gets the size of its medium preview (oriented, aspect kept):
 * the real size up to 1200 pixels wide, else that ratio at 1200 pixels wide, because the original size was never saved.
 */
#[Signature('kinlore:media-dimensions {--dry-run : Show what would be filled, change nothing}')]
#[Description('Fill the width and height of photo and video moments that have none')]
class MediaDimensions extends Command
{
    public function handle(FileStorageService $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $filled = 0;
        $unreadable = 0;

        Moment::query()
            ->with('media:id,video_metadata')
            ->whereIn('moment_type', [MomentType::Photo, MomentType::Video])
            ->where(fn ($q) => $q->whereNull('width')->orWhereNull('height'))
            // A video still processing gets its size from the job; a failed one has no preview.
            ->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', VideoStatus::Ready))
            ->chunkById(200, function ($moments) use ($storage, $dryRun, &$filled, &$unreadable): void {
                foreach ($moments as $moment) {
                    $size = $this->size($moment, $storage);
                    if (! $size) {
                        $unreadable++;
                        Log::warning('kinlore:media-dimensions cannot read the size', ['moment_id' => $moment->id]);

                        continue;
                    }

                    // A query update: updated_at changes, so phones get the row on their next sync; edited_at
                    // stays, so this is no edit and causes no sync conflict.
                    if (! $dryRun) {
                        Moment::whereKey($moment->id)->update($size);
                    }
                    $filled++;
                }
            });

        $this->info(($dryRun ? 'Would fill' : 'Filled')." {$filled} moments. Cannot read {$unreadable}.");

        return self::SUCCESS;
    }

    /** @return array{width: int, height: int}|null */
    private function size(Moment $moment, FileStorageService $storage): ?array
    {
        $metadata = $moment->media?->video_metadata;
        if (($metadata['width'] ?? 0) > 0 && ($metadata['height'] ?? 0) > 0) {
            return ['width' => (int) $metadata['width'], 'height' => (int) $metadata['height']];
        }

        $path = $moment->getRawOriginal('medium_url');
        $bytes = $path ? $storage->get($path) : null;
        $info = $bytes ? @getimagesizefromstring($bytes) : false;

        return $info && $info[0] > 0 && $info[1] > 0 ? ['width' => $info[0], 'height' => $info[1]] : null;
    }
}

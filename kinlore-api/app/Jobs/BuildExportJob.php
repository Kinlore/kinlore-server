<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds the export ZIP (docs/API_CONTRACT.md section 8):
 * {Kid name}/{YYYY}/{YYYY-MM-DD}_{type}_{id8}.{ext}, {Kid name}/memories.json and kinlore-export.json.
 */
class BuildExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const KEEP = 3;

    public int $tries = 1;

    public int $timeout = 3600;

    /** @var string[] temp copies of originals, deleted after the ZIP is closed */
    private array $tempFiles = [];

    public function __construct(public string $exportId)
    {
    }

    public function handle(): void
    {
        $export = Export::find($this->exportId);

        if (!$export) {
            return;
        }

        $export->update(['status' => Export::STATUS_PROCESSING]);

        try {
            $export->path = "exports/{$export->id}.zip";
            $this->build($export->fullPath());

            $export->update([
                'status' => Export::STATUS_READY,
                'path' => $export->path,
                'size_bytes' => filesize($export->fullPath()),
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('BuildExportJob: Export failed', ['export_id' => $export->id, 'error' => $e->getMessage()]);
            @unlink($export->fullPath());
            $export->update(['status' => Export::STATUS_FAILED, 'path' => null, 'error' => $e->getMessage(), 'completed_at' => now()]);
        } finally {
            array_map('unlink', array_filter($this->tempFiles, 'file_exists'));
        }

        $this->pruneOldExports();
    }

    private function build(string $zipPath): void
    {
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create {$zipPath}");
        }

        $kids = Kid::orderBy('created_at')->get();
        $folders = [];

        foreach ($kids as $kid) {
            $folder = $this->folderName($kid, $folders);
            $memories = [];

            $moments = Moment::where('kid_id', $kid->id)
                ->forPayload()
                ->with(['comments' => fn ($q) => $q->with('user:id,name')->orderBy('created_at')])
                ->orderByDate('asc')
                ->get();

            foreach ($moments as $moment) {
                $memory = collect($moment->toArray())
                    ->except(['photo_url', 'medium_url', 'thumbnail_url', 'hls_url', 'is_liked'])
                    ->all();
                $memory['comments'] = $moment->comments->map(fn ($c) => [
                    'author' => $c->user?->name,
                    'content' => $c->content,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()->all();
                $memory['file'] = $this->addOriginal($zip, $folder, $moment);
                $memories[] = $memory;
            }

            $zip->addFromString("{$folder}/memories.json", $this->json($memories));
        }

        $zip->addFromString('kinlore-export.json', $this->json([
            'instance_name' => config('app.name'),
            'exported_at' => now()->toIso8601String(),
            'kids' => $kids->map(fn (Kid $kid) => [
                'id' => $kid->id,
                'name' => $kid->name,
                'folder' => $folders[$kid->id],
                'date_of_birth' => $kid->date_of_birth?->toDateString(),
                'due_date' => $kid->due_date?->toDateString(),
            ])->values()->all(),
        ]));

        if (!$zip->close()) {
            throw new \RuntimeException('Cannot write the ZIP file');
        }
    }

    /**
     * Copy the original of a photo or video into the ZIP. Returns its path in the ZIP, or null.
     */
    private function addOriginal(ZipArchive $zip, string $folder, Moment $moment): ?string
    {
        $source = $moment->getRawOriginal('photo_url');

        if (!in_array($moment->moment_type, ['photo', 'video'], true) || empty($source)) {
            return null;
        }

        $in = Storage::disk('seaweedfs')->readStream($source);
        if (!$in) {
            Log::warning('BuildExportJob: Original missing', ['moment_id' => $moment->id, 'path' => $source]);
            return null;
        }

        // shortcut: ZipArchive reads added files on close(), so every original sits in temp until then; needs free temp space equal to the library
        $temp = tempnam(sys_get_temp_dir(), 'kinlore-export-');
        $out = fopen($temp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        fclose($in);
        $this->tempFiles[] = $temp;

        $date = $moment->display_date;
        $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'bin';
        $name = sprintf('%s/%s/%s_%s_%s.%s', $folder, $date->format('Y'), $date->format('Y-m-d'), $moment->moment_type, self::shortId($moment->id), $ext);
        $zip->addFile($temp, $name);

        return $name;
    }

    /** Kid name made safe for a folder; a second kid with the same name gets its id appended. */
    /**
     * Ids are UUIDv7: the first characters are a timestamp and repeat for items made close together,
     * so names use the random end of the id.
     */
    private static function shortId(string $id): string
    {
        return substr(str_replace('-', '', $id), -12);
    }

    private function folderName(Kid $kid, array &$folders): string
    {
        // leading dots are dropped so a kid named "." or ".." cannot make a path that leaves the archive root
        $name = ltrim(trim(preg_replace('/[\/\\\\:*?"<>|]+/', '-', $kid->name)), '.') ?: 'Kid';

        if (in_array($name, $folders, true)) {
            $name .= ' ' . self::shortId($kid->id);
        }

        return $folders[$kid->id] = $name;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Keep the newest exports only. */
    private function pruneOldExports(): void
    {
        Export::orderByDesc('created_at')->orderByDesc('id')->get()->slice(self::KEEP)
            ->each(function (Export $old) {
                if ($old->fullPath()) {
                    @unlink($old->fullPath());
                }
                $old->delete();
            });
    }
}

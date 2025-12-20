<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SyncController extends Controller
{
    /**
     * Pull changes from server (WatermelonDB sync).
     */
    public function pull(Request $request)
    {
        $validated = $request->validate([
            'lastPulledAt' => 'nullable|integer',
            'schemaVersion' => 'required|integer',
            'migration' => 'nullable',
        ]);

        $lastPulledAt = $validated['lastPulledAt']
            ? Carbon::createFromTimestamp($validated['lastPulledAt'] / 1000)
            : null;

        $user = $request->user();

        // Get kids changes
        $kidsQuery = Kid::where('user_id', $user->id);
        
        if ($lastPulledAt) {
            $kidsQuery->where('updated_at', '>', $lastPulledAt);
        }

        $kids = $kidsQuery->get();

        // Separate created, updated, deleted
        $kidsCreated = [];
        $kidsUpdated = [];
        $kidsDeleted = [];

        foreach ($kids as $kid) {
            $kidData = [
                'id' => $kid->id,
                'name' => $kid->name,
                'date_of_birth' => $kid->date_of_birth->timestamp * 1000,
                'avatar_color' => $kid->avatar_color,
                'created_at' => $kid->created_at->timestamp * 1000,
                'updated_at' => $kid->updated_at->timestamp * 1000,
            ];

            if ($kid->deleted_at) {
                $kidsDeleted[] = $kid->id;
            } elseif (!$lastPulledAt || $kid->created_at > $lastPulledAt) {
                $kidsCreated[] = $kidData;
            } else {
                $kidsUpdated[] = $kidData;
            }
        }

        // Get moments changes
        $momentsQuery = Moment::whereHas('kid', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        });

        if ($lastPulledAt) {
            $momentsQuery->where('updated_at', '>', $lastPulledAt);
        }

        $moments = $momentsQuery->get();

        // Separate created, updated, deleted
        $momentsCreated = [];
        $momentsUpdated = [];
        $momentsDeleted = [];

        foreach ($moments as $moment) {
            $momentData = [
                'id' => $moment->id,
                'kid_id' => $moment->kid_id,
                'title' => $moment->title,
                'description' => $moment->description,
                'photo_url' => $moment->getRawOriginal('photo_url'),
                'medium_url' => $moment->getRawOriginal('medium_url'),
                'thumbnail_url' => $moment->getRawOriginal('thumbnail_url'),
                'file_size' => $moment->file_size,
                'exif_date' => $moment->exif_date ? $moment->exif_date->timestamp * 1000 : null,
                'latitude' => $moment->latitude,
                'longitude' => $moment->longitude,
                'created_at' => $moment->created_at->timestamp * 1000,
                'updated_at' => $moment->updated_at->timestamp * 1000,
            ];

            if ($moment->deleted_at) {
                $momentsDeleted[] = $moment->id;
            } elseif (!$lastPulledAt || $moment->created_at > $lastPulledAt) {
                $momentsCreated[] = $momentData;
            } else {
                $momentsUpdated[] = $momentData;
            }
        }

        return response()->json([
            'changes' => [
                'kids' => [
                    'created' => $kidsCreated,
                    'updated' => $kidsUpdated,
                    'deleted' => $kidsDeleted,
                ],
                'moments' => [
                    'created' => $momentsCreated,
                    'updated' => $momentsUpdated,
                    'deleted' => $momentsDeleted,
                ],
            ],
            'timestamp' => now()->timestamp * 1000,
        ]);
    }

    /**
     * Push changes to server (WatermelonDB sync).
     */
    public function push(Request $request)
    {
        $validated = $request->validate([
            'changes' => 'required|array',
            'lastPulledAt' => 'nullable|integer',
        ]);

        $changes = $validated['changes'];
        $user = $request->user();

        // Process kids changes
        if (isset($changes['kids'])) {
            // Created kids
            if (isset($changes['kids']['created'])) {
                foreach ($changes['kids']['created'] as $kidData) {
                    Kid::updateOrCreate(
                        ['id' => $kidData['id']],
                        [
                            'user_id' => $user->id,
                            'name' => $kidData['name'],
                            'date_of_birth' => Carbon::createFromTimestamp($kidData['date_of_birth'] / 1000),
                            'avatar_color' => $kidData['avatar_color'],
                        ]
                    );
                }
            }

            // Updated kids
            if (isset($changes['kids']['updated'])) {
                foreach ($changes['kids']['updated'] as $kidData) {
                    $kid = Kid::find($kidData['id']);
                    if ($kid && $kid->user_id === $user->id) {
                        $kid->update([
                            'name' => $kidData['name'],
                            'date_of_birth' => Carbon::createFromTimestamp($kidData['date_of_birth'] / 1000),
                            'avatar_color' => $kidData['avatar_color'],
                        ]);
                    }
                }
            }

            // Deleted kids
            if (isset($changes['kids']['deleted'])) {
                foreach ($changes['kids']['deleted'] as $kidId) {
                    $kid = Kid::find($kidId);
                    if ($kid && $kid->user_id === $user->id) {
                        $kid->delete();
                    }
                }
            }
        }

        // Process moments changes (similar logic)
        // Note: Moments are typically created via upload endpoint, not sync

        return response()->json([
            'success' => true,
            'message' => 'Changes synced successfully',
        ]);
    }
}

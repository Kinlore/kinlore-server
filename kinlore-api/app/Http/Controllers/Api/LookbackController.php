<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MomentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexLookbackRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Look-back: on this day, then and now (docs/reference/api.md section 7).
 */
class LookbackController extends Controller
{
    private const string DISPLAY_DATE = 'COALESCE(exif_date, created_at)';

    public function index(IndexLookbackRequest $request): JsonResponse
    {
        $date = Carbon::parse($request->validated('date') ?? today()->toDateString());
        $user = $request->user();

        return ApiResponse::success([
            'on_this_day' => $this->onThisDay($user, $date),
            'then_and_now' => $this->thenAndNow($user),
        ]);
    }

    /**
     * Photo, video and story moments of the same month and day in earlier years, newest year first.
     */
    private function onThisDay(User $user, Carbon $date): array
    {
        $monthDay = DB::getDriverName() === 'pgsql'
            ? 'to_char('.self::DISPLAY_DATE.", 'MM-DD')"
            : "strftime('%m-%d', ".self::DISPLAY_DATE.')';

        return Moment::has('kid')
            ->forPayload($user)
            ->whereIn('moment_type', [MomentType::Photo, MomentType::Video, MomentType::Story])
            ->whereRaw("{$monthDay} = ?", [$date->format('m-d')])
            ->whereRaw(self::DISPLAY_DATE.' < ?', [$date->copy()->startOfYear()->toDateTimeString()])
            ->orderByDate('desc')
            ->get()
            ->groupBy(fn (Moment $m) => $m->display_date->year)
            ->map(fn ($moments, $year) => [
                'years_ago' => $date->year - $year,
                'date' => $moments->first()->display_date->toDateString(),
                'moments' => $moments->take(10)->values()->toArray(),
            ])
            ->values()
            ->all();
    }

    /**
     * For each kid, the earliest and the latest photo when they are at least 6 months apart.
     */
    private function thenAndNow(User $user): array
    {
        $pairs = [];

        foreach (Kid::orderBy('created_at')->get() as $kid) {
            $photos = fn () => Moment::forPayload($user)->where('kid_id', $kid->id)->where('moment_type', MomentType::Photo);
            $then = $photos()->orderByDate('asc')->first();
            $now = $photos()->orderByDate('desc')->first();

            if ($then && $now && $then->display_date->copy()->addMonths(6)->lte($now->display_date)) {
                $pairs[] = [
                    'kid_id' => $kid->id,
                    'kid_name' => $kid->name,
                    'then' => $then->toArray(),
                    'now' => $now->toArray(),
                ];
            }
        }

        return $pairs;
    }
}

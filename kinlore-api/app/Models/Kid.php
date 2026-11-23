<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\KidPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'date_of_birth', 'due_date', 'avatar_color'])]
#[Appends(['age'])]
#[UsePolicy(KidPolicy::class)]
class Kid extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    #[\Override]
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moments(): HasMany
    {
        return $this->hasMany(Moment::class);
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(Carbon::now()) : null,
        );
    }

    protected function ageInMonths(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_of_birth ? (int) $this->date_of_birth->diffInMonths(Carbon::now()) : null,
        );
    }

    protected function ageDetailed(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $dob = $this->date_of_birth;
            if (! $dob) {
                return null;
            }

            $now = Carbon::now();

            // Carbon 3 returns floats; whole numbers only
            $years = (int) $dob->diffInYears($now);
            $months = (int) $dob->copy()->addYears($years)->diffInMonths($now);

            if ($years > 0) {
                return $years.' year'.($years > 1 ? 's' : '').
                       ($months > 0 ? ', '.$months.' month'.($months > 1 ? 's' : '') : '');
            }

            return $months.' month'.($months > 1 ? 's' : '');
        });
    }

    protected function isExpecting(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->date_of_birth === null);
    }

    public function getMomentsCount(): int
    {
        return $this->moments()->count();
    }

    public function getLatestMoment(): ?Moment
    {
        return $this->moments()->orderBy('exif_date', 'desc')->first();
    }

    public function getFirstMoment(): ?Moment
    {
        return $this->moments()->orderBy('exif_date', 'asc')->first();
    }

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (Kid $kid): void {
            if (empty($kid->avatar_color)) {
                $kid->avatar_color = self::randomAvatarColor();
            }
        });
    }

    public static function randomAvatarColor(): string
    {
        $colors = [
            '#FF6B6B', // Red
            '#4ECDC4', // Teal
            '#45B7D1', // Blue
            '#FFA07A', // Salmon
            '#98D8C8', // Mint
            '#F7DC6F', // Yellow
            '#BB8FCE', // Purple
            '#85C1E2', // Sky Blue
            '#F8B88B', // Peach
            '#A3E4D7', // Aqua
        ];

        return $colors[array_rand($colors)];
    }

    /** API resource format (docs/API_CONTRACT.md section 5). */
    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'avatar_color' => $this->avatar_color,
            'age' => $this->age,
            'age_months' => $this->age_in_months,
            'is_expecting' => $this->is_expecting,
            'age_detailed' => $this->age_detailed,
            'moments_count' => $this->moments_count ?? $this->getMomentsCount(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

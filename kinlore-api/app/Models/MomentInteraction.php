<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// a like or comment bumps the moment, so the next sync pull sends the new counts to every phone
#[Touches(['moment'])]
#[Fillable(['moment_id', 'user_id', 'type', 'content'])]
class MomentInteraction extends Model
{
    #[\Override]
    protected function casts(): array
    {
        return ['type' => InteractionType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }
}

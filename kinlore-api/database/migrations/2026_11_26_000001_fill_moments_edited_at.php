<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Moments from before 1.1.0 have no edited_at: their last change is the best known edit time.
        DB::table('moments')->whereNull('edited_at')->update(['edited_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // Nothing to undo: edited_at was empty before.
    }
};

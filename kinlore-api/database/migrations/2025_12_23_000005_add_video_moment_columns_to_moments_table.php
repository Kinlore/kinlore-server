<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check if milestone_id column exists with wrong type and fix it
        if (Schema::hasColumn('moments', 'milestone_id')) {
            // Get the column type
            $columnType = DB::selectOne("
                SELECT data_type 
                FROM information_schema.columns 
                WHERE table_name = 'moments' AND column_name = 'milestone_id'
            ");
            
            // If it's uuid type, we need to drop and recreate as string
            if ($columnType && $columnType->data_type === 'uuid') {
                Schema::table('moments', function (Blueprint $table) {
                    // Drop foreign key if exists
                    try {
                        $table->dropForeign(['milestone_id']);
                    } catch (\Exception $e) {
                        // Ignore if foreign key doesn't exist
                    }
                    // Drop index if exists
                    try {
                        $table->dropIndex(['milestone_id']);
                    } catch (\Exception $e) {
                        // Ignore if index doesn't exist
                    }
                    $table->dropColumn('milestone_id');
                });
            }
        }
        
        Schema::table('moments', function (Blueprint $table) {
            // Video association (nullable - moment can exist without video)
            if (!Schema::hasColumn('moments', 'video_id')) {
                $table->uuid('video_id')->nullable()->after('kid_id');
            }
            
            // Moment type categorization
            if (!Schema::hasColumn('moments', 'moment_type')) {
                $table->enum('moment_type', ['photo', 'video', 'text', 'mixed'])
                      ->default('photo')
                      ->after('video_id');
            }
            
            // Milestone associations (both nullable, only one should be set)
            // Note: milestone_id uses string to match the milestones table's string primary key
            if (!Schema::hasColumn('moments', 'milestone_id')) {
                $table->string('milestone_id')->nullable()->after('moment_type');
            }
            if (!Schema::hasColumn('moments', 'custom_milestone_id')) {
                $table->uuid('custom_milestone_id')->nullable()->after('milestone_id');
            }
            
            // Story/narrative text
            if (!Schema::hasColumn('moments', 'story_text')) {
                $table->text('story_text')->nullable()->after('description');
            }
        });
        
        // Add foreign keys and indexes in a separate statement
        Schema::table('moments', function (Blueprint $table) {
            // Check and add foreign keys
            $foreignKeys = DB::select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'moments' AND constraint_type = 'FOREIGN KEY'
            ");
            $existingFks = array_map(fn($fk) => $fk->constraint_name, $foreignKeys);
            
            if (!in_array('moments_video_id_foreign', $existingFks) && Schema::hasTable('videos')) {
                $table->foreign('video_id')
                      ->references('id')
                      ->on('videos')
                      ->nullOnDelete();
            }
                  
            if (!in_array('moments_milestone_id_foreign', $existingFks) && Schema::hasTable('milestones')) {
                $table->foreign('milestone_id')
                      ->references('id')
                      ->on('milestones')
                      ->nullOnDelete();
            }
                  
            if (!in_array('moments_custom_milestone_id_foreign', $existingFks) && Schema::hasTable('custom_milestones')) {
                $table->foreign('custom_milestone_id')
                      ->references('id')
                      ->on('custom_milestones')
                      ->nullOnDelete();
            }
        });
        
        // Add indexes
        Schema::table('moments', function (Blueprint $table) {
            $indexes = DB::select("
                SELECT indexname 
                FROM pg_indexes 
                WHERE tablename = 'moments'
            ");
            $existingIndexes = array_map(fn($idx) => $idx->indexname, $indexes);
            
            if (!in_array('moments_video_id_index', $existingIndexes)) {
                $table->index('video_id');
            }
            if (!in_array('moments_milestone_id_index', $existingIndexes)) {
                $table->index('milestone_id');
            }
            if (!in_array('moments_custom_milestone_id_index', $existingIndexes)) {
                $table->index('custom_milestone_id');
            }
            if (!in_array('moments_moment_type_index', $existingIndexes)) {
                $table->index('moment_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['video_id']);
            $table->dropForeign(['milestone_id']);
            $table->dropForeign(['custom_milestone_id']);
            
            // Drop indexes
            $table->dropIndex(['video_id']);
            $table->dropIndex(['milestone_id']);
            $table->dropIndex(['custom_milestone_id']);
            $table->dropIndex(['moment_type']);
            
            // Drop columns
            $table->dropColumn([
                'video_id',
                'moment_type',
                'milestone_id',
                'custom_milestone_id',
                'story_text',
            ]);
        });
    }
};

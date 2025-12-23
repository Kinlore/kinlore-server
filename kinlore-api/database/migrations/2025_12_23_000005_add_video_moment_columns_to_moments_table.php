<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            // Video association (nullable - moment can exist without video)
            $table->uuid('video_id')->nullable()->after('kid_id');
            
            // Moment type categorization
            $table->enum('moment_type', ['photo', 'video', 'text', 'mixed'])
                  ->default('photo')
                  ->after('video_id');
            
            // Milestone associations (both nullable, only one should be set)
            $table->uuid('milestone_id')->nullable()->after('moment_type');
            $table->uuid('custom_milestone_id')->nullable()->after('milestone_id');
            
            // Story/narrative text
            $table->text('story_text')->nullable()->after('description');

            // Foreign keys
            $table->foreign('video_id')
                  ->references('id')
                  ->on('videos')
                  ->nullOnDelete();
                  
            $table->foreign('milestone_id')
                  ->references('id')
                  ->on('milestones')
                  ->nullOnDelete();
                  
            $table->foreign('custom_milestone_id')
                  ->references('id')
                  ->on('custom_milestones')
                  ->nullOnDelete();

            // Indexes
            $table->index('video_id');
            $table->index('milestone_id');
            $table->index('custom_milestone_id');
            $table->index('moment_type');
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

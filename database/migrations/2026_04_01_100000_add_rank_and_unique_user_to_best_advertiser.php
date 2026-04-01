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
        Schema::table('best_advertiser', function (Blueprint $table) {
            // Add rank column with default 0
            $table->unsignedInteger('rank')->default(0)->after('max_listings');
            
            // Add unique constraint to user_id
            $table->unique('user_id');
            
            // Add index on rank for better performance
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('best_advertiser', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique(['user_id']);
            
            // Drop index
            $table->dropIndex(['rank']);
            
            // Drop rank column
            $table->dropColumn('rank');
        });
    }
};

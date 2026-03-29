<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_banners', function (Blueprint $table) {
            $table->string('banner_path_en')->nullable()->after('banner_path');
        });
    }

    public function down(): void
    {
        Schema::table('category_banners', function (Blueprint $table) {
            $table->dropColumn('banner_path_en');
        });
    }
};

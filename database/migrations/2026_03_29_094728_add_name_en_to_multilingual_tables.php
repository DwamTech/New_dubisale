<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('makes', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('models', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });

        Schema::table('category_main_sections', function (Blueprint $table) {
            $table->string('name_en', 191)->nullable()->after('name');
        });

        Schema::table('category_sub_section', function (Blueprint $table) {
            $table->string('name_en', 191)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        foreach (['governorates', 'cities', 'makes', 'models'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('name_en');
            });
        }

        Schema::table('category_main_sections', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });

        Schema::table('category_sub_section', function (Blueprint $table) {
            $table->dropColumn('name_en');
        });
    }
};
